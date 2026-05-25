<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once "config.php";

$validBouquets = ["black-moon", "blue-evening", "moonlight", "custom"];
$inputFormat = "json";
$outputFormat = "json";

if (isset($_GET["format"])) {
    $fmt = strtolower($_GET["format"]);
    if ($fmt === "xml") {
        $outputFormat = "xml";
    }
} else {
    $accept = $_SERVER["HTTP_ACCEPT"] ?? "";
    if (strpos($accept, "application/xml") !== false || strpos($accept, "text/xml") !== false) {
        $outputFormat = "xml";
    }
}

function parseInput(): array
{
    global $inputFormat;
    $contentType = $_SERVER["CONTENT_TYPE"] ?? "";

    if (strpos($contentType, "application/json") !== false) {
        $inputFormat = "json";
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    if (
        strpos($contentType, "application/xml") !== false ||
        strpos($contentType, "text/xml") !== false
    ) {
        $inputFormat = "xml";
        $raw = file_get_contents("php://input");
        $xml = simplexml_load_string($raw);
        if ($xml === false) {
            return [];
        }
        $json = json_encode($xml);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    return $_POST ?: [];
}

function outputJson(array $data, int $code = 200): void
{
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function outputXml(array $data, int $code = 200): void
{
    http_response_code($code);
    header("Content-Type: application/xml; charset=utf-8");

    $xml = new SimpleXMLElement(
        '<?xml version="1.0" encoding="UTF-8"?><response></response>',
    );
    arrayToXml($data, $xml);
    echo $xml->asXML();
    exit();
}

function arrayToXml(array $data, SimpleXMLElement $xml): void
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $child = $xml->addChild(is_numeric($key) ? "item" : $key);
            arrayToXml($value, $child);
        } else {
            $xml->addChild(
                is_numeric($key) ? "item" : $key,
                htmlspecialchars((string) $value),
            );
        }
    }
}

function getAuthUser(): ?array
{
    if (
        !isset($_SERVER["HTTP_AUTHORIZATION"]) &&
        !isset($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])
    ) {
        if (function_exists("apache_request_headers")) {
            $headers = apache_request_headers();
            if (isset($headers["Authorization"])) {
                $_SERVER["HTTP_AUTHORIZATION"] = $headers["Authorization"];
            }
        }
    }

    $authHeader =
        $_SERVER["HTTP_AUTHORIZATION"] ??
        ($_SERVER["REDIRECT_HTTP_AUTHORIZATION"] ?? "");
    if (preg_match("/Basic\s+(.+)/i", $authHeader, $m)) {
        $decoded = base64_decode($m[1]);
        if ($decoded && strpos($decoded, ":") !== false) {
            [$login, $password] = explode(":", $decoded, 2);
            return ["login" => $login, "password" => $password];
        }
    }
    return null;
}

function validateInput(array $data): array
{
    global $validBouquets;
    $errors = [];

    $data["name"] = trim($data["name"] ?? "");
    if (empty($data["name"])) {
        $errors["name"] = "Поле обязательно для заполнения";
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\.\-]+$/u', $data["name"])) {
        $errors["name"] = "Допустимы только буквы, пробелы, дефисы и точки";
    }

    $data["phone"] = trim($data["phone"] ?? "");
    if (empty($data["phone"])) {
        $errors["phone"] = "Поле обязательно для заполнения";
    } elseif (!preg_match('/^\+?\d[\d\s\-\(\)]{6,20}$/', $data["phone"])) {
        $errors["phone"] =
            "Допустимы только цифры, знак +, пробелы, дефисы и скобки";
    }

    $bouquets = $data["bouquets"] ?? [];
    if (!is_array($bouquets)) {
        $bouquets = [$bouquets];
    }
    $selectedBouquets = [];
    foreach ($bouquets as $b) {
        if (in_array((string) $b, $validBouquets)) {
            $selectedBouquets[] = (string) $b;
        }
    }
    if (empty($selectedBouquets)) {
        $errors["bouquets"] = "Выберите хотя бы один букет";
    }
    $data["bouquets"] = $selectedBouquets;

    $data["message"] = trim($data["message"] ?? "");
    if (
        $data["message"] !== "" &&
        !preg_match(
            '/^[а-яА-ЯёЁa-zA-Z0-9\s\.\,\!\?\-\:\;\"\'\@\#\$\%\&\(\)\+\=\/\_\~]+$/u',
            $data["message"],
        )
    ) {
        $errors["message"] =
            "Допустимы только буквы, цифры, пробелы и знаки препинания";
    }

    return ["data" => $data, "errors" => $errors];
}

try {
    $auth = getAuthUser();
    $input = parseInput();

    if (empty($input)) {
        outputJson(
            ["success" => false, "message" => "Нет данных для обработки"],
            400,
        );
    }

    $validation = validateInput($input);
    $data = $validation["data"];
    $errors = $validation["errors"];

    if (!empty($errors)) {
        $resp = ["success" => false, "errors" => $errors];
        if ($outputFormat === "json") {
            outputJson($resp, 422);
        } else {
            outputXml($resp, 422);
        }
    }

    $pdo = getDB();

    if ($auth) {
        $stmt = $pdo->prepare(
            "SELECT id, password_hash FROM orders WHERE login = ?",
        );
        $stmt->execute([$auth["login"]]);
        $user = $stmt->fetch();

        if (
            !$user ||
            !password_verify($auth["password"], $user["password_hash"])
        ) {
            $resp = [
                "success" => false,
                "message" => "Неверный логин или пароль",
            ];
            if ($outputFormat === "json") {
                outputJson($resp, 401);
            } else {
                outputXml($resp, 401);
            }
        }

        $orderId = $user["id"];
        $stmt = $pdo->prepare(
            "UPDATE orders SET name = ?, phone = ?, message = ? WHERE id = ?",
        );
        $stmt->execute([
            $data["name"],
            $data["phone"],
            $data["message"],
            $orderId,
        ]);

        $stmt = $pdo->prepare("DELETE FROM order_bouquets WHERE order_id = ?");
        $stmt->execute([$orderId]);

        $stmt = $pdo->prepare(
            "INSERT INTO order_bouquets (order_id, bouquet_id) VALUES (?, (SELECT id FROM bouquets WHERE code = ?))",
        );
        foreach ($data["bouquets"] as $bouquetCode) {
            $stmt->execute([$orderId, $bouquetCode]);
        }

        $resp = ["success" => true, "message" => "Данные успешно обновлены"];
    } else {
        $login = substr(bin2hex(random_bytes(4)), 0, 8);
        $password = substr(bin2hex(random_bytes(6)), 0, 12);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO orders (name, phone, message, login, password_hash) VALUES (?, ?, ?, ?, ?)",
        );
        $stmt->execute([
            $data["name"],
            $data["phone"],
            $data["message"],
            $login,
            $passwordHash,
        ]);
        $orderId = $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO order_bouquets (order_id, bouquet_id) VALUES (?, (SELECT id FROM bouquets WHERE code = ?))",
        );
        foreach ($data["bouquets"] as $bouquetCode) {
            $stmt->execute([$orderId, $bouquetCode]);
        }

        $profileUrl =
            (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on"
                ? "https"
                : "http") .
            "://" .
            $_SERVER["HTTP_HOST"] .
            rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/") .
            "/index.php?login=" .
            urlencode($login) .
            "&password=" .
            urlencode($password);

        $resp = [
            "success" => true,
            "login" => $login,
            "password" => $password,
            "profile_url" => $profileUrl,
        ];
    }

    if ($outputFormat === "json") {
        outputJson($resp);
    } else {
        outputXml($resp);
    }
} catch (PDOException $e) {
    $resp = [
        "success" => false,
        "message" => "Ошибка базы данных",
    ];
    if ($outputFormat === "json") {
        outputJson($resp, 500);
    } else {
        outputXml($resp, 500);
    }
} catch (Exception $e) {
    $resp = ["success" => false, "message" => "Внутренняя ошибка сервера"];
    if ($outputFormat === "json") {
        outputJson($resp, 500);
    } else {
        outputXml($resp, 500);
    }
}
