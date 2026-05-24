<?php

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
session_start();

require_once __DIR__ . '/functions.php';

function arrayToXml($data, &$xml) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sub = $xml->addChild(is_numeric($key) ? 'item' : str_replace(' ', '_', $key));
            arrayToXml($value, $sub);
        } else {
            $xml->addChild(str_replace(' ', '_', $key), htmlspecialchars((string)$value));
        }
    }
}

function sendJsonResponse($data, $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendXmlResponse($data, $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/xml; charset=UTF-8');
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><response></response>');
    arrayToXml($data, $xml);
    echo $xml->asXML();
    exit;
}

function sendError($message, $http_code = 400, $format = 'json') {
    if ($format === 'form') {
        $_SESSION['errors'] = ['general' => $message];
        header('Location: ../b_lab_6/form.php');
        exit;
    }
    $data = ['success' => false, 'errors' => ['general' => $message]];
    if ($format === 'xml') {
        sendXmlResponse($data, $http_code);
    } else {
        sendJsonResponse($data, $http_code);
    }
}

function redirectForm($data, $errors = null, $credentials = null, $success = false) {
    if ($credentials !== null) {
        $_SESSION['credentials'] = $credentials;
    }
    if ($success) {
        $_SESSION['success'] = true;
        setcookie('save', '1', time() + 24 * 60 * 60, '/');
    }
    saveToCookies($data, $errors);
    header('Location: ../b_lab_6/form.php');
    exit;
}

function mapFrontendFields($raw) {
    $mapped = [];
    $mapped['full_name'] = trim($raw['name'] ?? '');
    $mapped['phone'] = trim($raw['phone'] ?? '');

    $bouquet = trim($raw['bouquet'] ?? '');
    $message = trim($raw['message'] ?? '');
    $bio = '';
    if (!empty($bouquet)) {
        $bio .= 'Выбранный букет: ' . $bouquet;
    }
    if (!empty($message)) {
        if (!empty($bio)) $bio .= "\n";
        $bio .= 'Пожелания: ' . $message;
    }
    $mapped['biography'] = $bio;

    $mapped['email'] = trim($raw['email'] ?? 'user@example.com');
    $mapped['birth_date'] = trim($raw['birth_date'] ?? date('Y-m-d'));
    $mapped['gender'] = $raw['gender'] ?? 'male';
    $mapped['agreement'] = isset($raw['agreement']) && ($raw['agreement'] === '1' || $raw['agreement'] === 1 || $raw['agreement'] === true) ? '1' : '1';
    $mapped['languages'] = $raw['languages'] ?? ['PHP'];

    return $mapped;
}

function apiSaveNewApplication($data) {
    $pdo = getDbConnection();
    $login = bin2hex(random_bytes(8));
    $password = bin2hex(random_bytes(8));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $languages_db = [];
    $stmt = $pdo->query("SELECT id, name FROM programming_language ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $languages_db[$row['name']] = $row['id'];
    }

    try {
        $pdo->beginTransaction();
        $sql_app = "INSERT INTO application (full_name, phone, email, birth_date, gender, biography, agreement, login, password_hash)
                    VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :agreement, :login, :password_hash)";
        $stmt_app = $pdo->prepare($sql_app);
        $stmt_app->execute([
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':birth_date' => $data['birth_date'],
            ':gender' => $data['gender'],
            ':biography' => $data['biography'],
            ':agreement' => $data['agreement'],
            ':login' => $login,
            ':password_hash' => $password_hash
        ]);

        $application_id = $pdo->lastInsertId();
        $sql_link = "INSERT INTO application_language (application_id, language_id) VALUES (?, ?)";
        $stmt_link = $pdo->prepare($sql_link);
        foreach ($data['languages'] as $lang_name) {
            if (isset($languages_db[$lang_name])) {
                $stmt_link->execute([$application_id, $languages_db[$lang_name]]);
            }
        }
        $pdo->commit();
        return ['login' => $login, 'password' => $password];
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        return false;
    }
}

function apiUpdateApplication($app_id, $data) {
    $pdo = getDbConnection();
    $languages_db = [];
    $stmt = $pdo->query("SELECT id, name FROM programming_language ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $languages_db[$row['name']] = $row['id'];
    }

    try {
        $pdo->beginTransaction();
        $sql_app = "UPDATE application SET full_name = :full_name, phone = :phone, email = :email, birth_date = :birth_date,
                    gender = :gender, biography = :biography, agreement = :agreement WHERE id = :id";
        $stmt_app = $pdo->prepare($sql_app);
        $stmt_app->execute([
            ':full_name' => $data['full_name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':birth_date' => $data['birth_date'],
            ':gender' => $data['gender'],
            ':biography' => $data['biography'],
            ':agreement' => $data['agreement'],
            ':id' => $app_id
        ]);

        $stmt = $pdo->prepare("DELETE FROM application_language WHERE application_id = ?");
        $stmt->execute([$app_id]);

        $sql_link = "INSERT INTO application_language (application_id, language_id) VALUES (?, ?)";
        $stmt_link = $pdo->prepare($sql_link);
        foreach ($data['languages'] as $lang_name) {
            if (isset($languages_db[$lang_name])) {
                $stmt_link->execute([$app_id, $languages_db[$lang_name]]);
            }
        }
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if (isset($pdo)) $pdo->rollBack();
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Only POST method is allowed', 405, 'json');
}

$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($content_type, 'application/json') !== false) {
    $format = 'json';
    $input = file_get_contents('php://input');
    $raw = json_decode($input, true);
    if ($raw === null) {
        sendError('Invalid JSON input', 400, 'json');
    }
} elseif (strpos($content_type, 'application/xml') !== false || strpos($content_type, 'text/xml') !== false) {
    $format = 'xml';
    $input = file_get_contents('php://input');
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($input);
    if ($xml === false) {
        sendError('Invalid XML input', 400, 'xml');
    }
    $raw = json_decode(json_encode($xml), true);
    if (isset($raw['languages']['item']) && !is_array($raw['languages']['item'])) {
        $raw['languages'] = [$raw['languages']['item']];
    } elseif (isset($raw['languages']['item']) && is_array($raw['languages']['item'])) {
        $raw['languages'] = $raw['languages']['item'];
    }
    if (isset($raw['agreement'])) {
        $raw['agreement'] = $raw['agreement'] === '1' || $raw['agreement'] === 1 || $raw['agreement'] === true || $raw['agreement'] === 'true' ? '1' : '';
    }
} else {
    $format = 'form';
    $raw = $_POST;
}

if (empty($raw) || !is_array($raw)) {
    sendError('No data received', 400, $format);
}

if (isset($raw['full_name']) || isset($raw['email'])) {
    $data = [
        'full_name' => trim($raw['full_name'] ?? ''),
        'phone' => trim($raw['phone'] ?? ''),
        'email' => trim($raw['email'] ?? ''),
        'birth_date' => $raw['birth_date'] ?? '',
        'gender' => $raw['gender'] ?? '',
        'biography' => trim($raw['biography'] ?? ''),
        'agreement' => isset($raw['agreement']) ? '1' : '',
        'languages' => $raw['languages'] ?? []
    ];
} else {
    $data = mapFrontendFields($raw);
}

$errors = validateFormData($data);
if (!empty($errors)) {
    if ($format === 'form') {
        redirectForm($data, $errors);
    }
    if ($format === 'xml') {
        sendXmlResponse(['success' => false, 'errors' => ['error' => implode('; ', $errors)]], 400);
    } else {
        sendJsonResponse(['success' => false, 'errors' => $errors], 400);
    }
}

$auth_login = $_SERVER['PHP_AUTH_USER'] ?? '';
$auth_password = $_SERVER['PHP_AUTH_PW'] ?? '';

if (!empty($auth_login) && !empty($auth_password)) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, password_hash FROM application WHERE login = :login");
    $stmt->execute([':login' => $auth_login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($auth_password, $user['password_hash'])) {
        if ($format === 'form') {
            $_SESSION['errors'] = ['auth' => 'Неверный логин или пароль.'];
            redirectForm($data, $_SESSION['errors']);
        }
        if ($format === 'xml') {
            sendXmlResponse(['success' => false, 'errors' => ['error' => 'Invalid login or password']], 401);
        } else {
            sendJsonResponse(['success' => false, 'errors' => ['auth' => 'Invalid login or password']], 401);
        }
    }

    if (apiUpdateApplication($user['id'], $data)) {
        if ($format === 'form') {
            redirectForm($data, null, null, true);
        }
        if ($format === 'xml') {
            sendXmlResponse(['success' => true, 'message' => 'Data updated successfully'], 200);
        } else {
            sendJsonResponse(['success' => true, 'message' => 'Data updated successfully'], 200);
        }
    } else {
        if ($format === 'form') {
            $_SESSION['errors'] = ['general' => 'Update failed'];
            redirectForm($data, $_SESSION['errors']);
        }
        if ($format === 'xml') {
            sendXmlResponse(['success' => false, 'errors' => ['error' => 'Update failed']], 500);
        } else {
            sendJsonResponse(['success' => false, 'errors' => ['general' => 'Update failed']], 500);
        }
    }
}

$result = apiSaveNewApplication($data);
if ($result === false) {
    if ($format === 'form') {
        $_SESSION['errors'] = ['general' => 'Save failed'];
        redirectForm($data, $_SESSION['errors']);
    }
    if ($format === 'xml') {
        sendXmlResponse(['success' => false, 'errors' => ['error' => 'Save failed']], 500);
    } else {
        sendJsonResponse(['success' => false, 'errors' => ['general' => 'Save failed']], 500);
    }
}

if ($format === 'form') {
    redirectForm($data, null, $result, true);
}

$profile_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/form.php';

if ($format === 'xml') {
    sendXmlResponse([
        'success' => true,
        'login' => $result['login'],
        'password' => $result['password'],
        'profile_url' => $profile_url
    ], 201);
} else {
    sendJsonResponse([
        'success' => true,
        'login' => $result['login'],
        'password' => $result['password'],
        'profile_url' => $profile_url
    ], 201);
}
