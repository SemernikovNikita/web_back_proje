<?php
ob_start();
session_start();
require_once "config.php";

$errorMessages = [];
$oldValues = [];
$successMessage = "";
$isLoggedIn = isset($_SESSION["order_id"]);

// Fallback: если сессия не работает, логинимся по кукам
if (!$isLoggedIn && isset($_COOKIE["order_uid"]) && isset($_COOKIE["order_upass"])) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM orders WHERE id = ?");
        $stmt->execute([(int) $_COOKIE["order_uid"]]);
        $user = $stmt->fetch();
        if ($user && password_verify($_COOKIE["order_upass"], $user["password_hash"])) {
            $_SESSION["order_id"] = $user["id"];
            $_SESSION["order_password"] = $_COOKIE["order_upass"];
            $isLoggedIn = true;
        }
    } catch (PDOException $e) {
    }
}

$editData = null;

$validBouquets = ["black-moon", "blue-evening", "moonlight", "custom"];
$bouquetNames = [
    "black-moon" => "Полуночный сад (4 290 Р)",
    "blue-evening" => "Сияние ночи (5 490 Р)",
    "moonlight" => "Лунные розы (6 850 Р)",
    "custom" => "Индивидуальный ночной букет",
];

if ($isLoggedIn) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT o.*, GROUP_CONCAT(b.code) as bouquet_codes
            FROM orders o
            LEFT JOIN order_bouquets ob ON o.id = ob.order_id
            LEFT JOIN bouquets b ON ob.bouquet_id = b.id
            WHERE o.id = ?
            GROUP BY o.id
        ");
        $stmt->execute([$_SESSION["order_id"]]);
        $editData = $stmt->fetch();
    } catch (PDOException $e) {
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["login_action"])) {
        $loginUser = trim($_POST["login_username"] ?? "");
        $loginPass = $_POST["login_password"] ?? "";
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare(
                "SELECT id, password_hash FROM orders WHERE login = ?",
            );
            $stmt->execute([$loginUser]);
            $user = $stmt->fetch();
            if ($user && password_verify($loginPass, $user["password_hash"])) {
                session_regenerate_id(true);
                $_SESSION["order_id"] = $user["id"];
                $_SESSION["order_password"] = $loginPass;
                setcookie("order_uid", $user["id"], time() + 3600, "/");
                setcookie("order_upass", $loginPass, time() + 3600, "/");
                setcookie(
                    "success_message",
                    "Вы успешно вошли в систему",
                    0,
                    "/",
                );
            } else {
                setcookie("error_login", "Неверный логин или пароль", 0, "/");
            }
        } catch (PDOException $e) {
            setcookie("error_login", "Ошибка базы данных", 0, "/");
        }
        header("Location: index.php");
        exit();
    }

    if (isset($_POST["logout"])) {
        session_destroy();
        header("Location: index.php");
        exit();
    }

    if (isset($_POST["submit"])) {
        $errors = [];
        $old = [];

        $name = trim($_POST["name"] ?? "");
        $old["name"] = $name;
        if (empty($name)) {
            $errors["name"] = "Поле обязательно для заполнения";
        } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\.\-]+$/u', $name)) {
            $errors["name"] = "Допустимы только буквы, пробелы, дефисы и точки";
        }

        $phone = trim($_POST["phone"] ?? "");
        $old["phone"] = $phone;
        if (empty($phone)) {
            $errors["phone"] = "Поле обязательно для заполнения";
        } elseif (!preg_match('/^\+?\d[\d\s\-\(\)]{6,20}$/', $phone)) {
            $errors["phone"] =
                "Допустимы только цифры, знак +, пробелы, дефисы и скобки";
        }

        $bouquets = $_POST["bouquets"] ?? [];
        $old["bouquets"] = $bouquets;
        $selectedBouquets = [];
        foreach ((array) $bouquets as $b) {
            if (in_array($b, $validBouquets)) {
                $selectedBouquets[] = $b;
            }
        }
        if (empty($selectedBouquets)) {
            $errors["bouquets"] = "Выберите хотя бы один букет";
        }

        $message = trim($_POST["message"] ?? "");
        $old["message"] = $message;
        if (
            $message !== "" &&
            !preg_match(
                '/^[а-яА-ЯёЁa-zA-Z0-9\s\.\,\!\?\-\:\;\"\'\@\#\$\%\&\(\)\+\=\/\_\~]+$/u',
                $message,
            )
        ) {
            $errors["message"] =
                "Допустимы только буквы, цифры, пробелы и знаки препинания";
        }

        if (!empty($errors)) {
            foreach ($errors as $field => $error) {
                setcookie("error_{$field}", $error, 0, "/");
            }
            foreach ($old as $field => $value) {
                if (is_array($value)) {
                    setcookie("old_{$field}", json_encode($value), 0, "/");
                } else {
                    setcookie("old_{$field}", $value, 0, "/");
                }
            }
            header("Location: index.php");
            exit();
        }

        try {
            $pdo = getDB();
            $login = substr(bin2hex(random_bytes(4)), 0, 8);
            $password = substr(bin2hex(random_bytes(6)), 0, 12);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            if ($isLoggedIn) {
                $orderId = $_SESSION["order_id"];
                $stmt = $pdo->prepare(
                    "UPDATE orders SET name = ?, phone = ?, message = ? WHERE id = ?",
                );
                $stmt->execute([$name, $phone, $message, $orderId]);
                $stmt = $pdo->prepare(
                    "DELETE FROM order_bouquets WHERE order_id = ?",
                );
                $stmt->execute([$orderId]);
                $successMessage = "Данные успешно обновлены";
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO orders (name, phone, message, login, password_hash) VALUES (?, ?, ?, ?, ?)",
                );
                $stmt->execute([
                    $name,
                    $phone,
                    $message,
                    $login,
                    $passwordHash,
                ]);
                $orderId = $pdo->lastInsertId();
                $_SESSION["order_id"] = $orderId;
                $_SESSION["order_password"] = $password;
                setcookie("created_login", $login, 0, "/");
                setcookie("created_password", $password, 0, "/");
                $successMessage = "created";
            }

            $stmt = $pdo->prepare(
                "INSERT INTO order_bouquets (order_id, bouquet_id) VALUES (?, (SELECT id FROM bouquets WHERE code = ?))",
            );
            foreach ($selectedBouquets as $bouquetCode) {
                $stmt->execute([$orderId, $bouquetCode]);
            }

            setcookie("saved_name", $name, time() + 365 * 24 * 60 * 60, "/");
            setcookie("saved_phone", $phone, time() + 365 * 24 * 60 * 60, "/");
            setcookie(
                "saved_bouquets",
                json_encode($selectedBouquets),
                time() + 365 * 24 * 60 * 60,
                "/",
            );
            setcookie(
                "saved_message",
                $message,
                time() + 365 * 24 * 60 * 60,
                "/",
            );
            setcookie("success_message", $successMessage ?: "updated", 0, "/");

            foreach (["name", "phone", "bouquets", "message"] as $f) {
                setcookie("old_{$f}", "", time() - 3600, "/");
            }
        } catch (PDOException $e) {
            setcookie(
                "error_general",
                "Ошибка базы данных",
                0,
                "/",
            );
        }

        header("Location: index.php");
        exit();
    }
}

foreach (["name", "phone", "bouquets", "message"] as $field) {
    if (isset($_COOKIE["error_{$field}"])) {
        $errorMessages[$field] = $_COOKIE["error_{$field}"];
        setcookie("error_{$field}", "", time() - 3600, "/");
    }
}
if (isset($_COOKIE["error_login"])) {
    $errorMessages["login"] = $_COOKIE["error_login"];
    setcookie("error_login", "", time() - 3600, "/");
}
if (isset($_COOKIE["error_general"])) {
    $errorMessages["general"] = $_COOKIE["error_general"];
    setcookie("error_general", "", time() - 3600, "/");
}

if (isset($_COOKIE["success_message"])) {
    $msg = $_COOKIE["success_message"];
    if ($msg === "created" && isset($_COOKIE["created_login"])) {
        $l = htmlspecialchars($_COOKIE["created_login"], ENT_QUOTES);
        $p = htmlspecialchars($_COOKIE["created_password"] ?? "", ENT_QUOTES);
        $ul = urlencode($l);
        $up = urlencode($p);
        $successMessage = "Спасибо, заказ принят!<br>Ваш логин: <b>{$l}</b><br>Ваш пароль: <b>{$p}</b><br>Профиль: <a href='index.php?login={$ul}&password={$up}' style='color:#155724;'>Войти в профиль</a>";
        setcookie("created_login", "", time() - 3600, "/");
        setcookie("created_password", "", time() - 3600, "/");
    } elseif ($msg === "created") {
        $successMessage = "Спасибо, заказ принят! Сохраните логин и пароль для входа в профиль.";
    } else {
        $successMessage = htmlspecialchars($msg, ENT_QUOTES);
    }
    setcookie("success_message", "", time() - 3600, "/");
}

foreach (["name", "phone", "message"] as $field) {
    if (isset($_COOKIE["old_{$field}"])) {
        $oldValues[$field] = $_COOKIE["old_{$field}"];
        setcookie("old_{$field}", "", time() - 3600, "/");
    }
}
if (isset($_COOKIE["old_bouquets"])) {
    $oldValues["bouquets"] = json_decode($_COOKIE["old_bouquets"], true) ?: [];
    setcookie("old_bouquets", "", time() - 3600, "/");
}

if ($isLoggedIn && empty($oldValues) && $editData) {
    $oldValues["name"] = $editData["name"];
    $oldValues["phone"] = $editData["phone"];
    $oldValues["message"] = $editData["message"] ?? "";
    $oldValues["bouquets"] = $editData["bouquet_codes"]
        ? explode(",", $editData["bouquet_codes"])
        : [];
} elseif (empty($oldValues)) {
    foreach (["name", "phone", "message"] as $field) {
        if (isset($_COOKIE["saved_{$field}"])) {
            $oldValues[$field] = $_COOKIE["saved_{$field}"];
        }
    }
    if (isset($_COOKIE["saved_bouquets"])) {
        $oldValues["bouquets"] =
            json_decode($_COOKIE["saved_bouquets"], true) ?: [];
    }
}

if (!$isLoggedIn && isset($_GET["login"]) && isset($_GET["password"])) {
    $loginParam = trim($_GET["login"]);
    $passParam = $_GET["password"];
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "SELECT id, password_hash FROM orders WHERE login = ?",
        );
        $stmt->execute([$loginParam]);
        $user = $stmt->fetch();
        if ($user && password_verify($passParam, $user["password_hash"])) {
            session_regenerate_id(true);
            $_SESSION["order_id"] = $user["id"];
            $_SESSION["order_password"] = $passParam;
            setcookie("order_uid", $user["id"], time() + 3600, "/");
            setcookie("order_upass", $passParam, time() + 3600, "/");
            header("Location: index.php");
            exit();
        } else {
            $errorMessages["login"] = "Неверный логин или пароль";
        }
    } catch (PDOException $e) {
        $errorMessages["general"] = "Ошибка базы данных";
    }
}

// Если сессия не сохранилась (редирект не работает), логинимся через куки
if (!$isLoggedIn && isset($_COOKIE["order_uid"]) && isset($_COOKIE["order_upass"])) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM orders WHERE id = ?");
        $stmt->execute([(int) $_COOKIE["order_uid"]]);
        $user = $stmt->fetch();
        if ($user && password_verify($_COOKIE["order_upass"], $user["password_hash"])) {
            $_SESSION["order_id"] = $user["id"];
            $_SESSION["order_password"] = $_COOKIE["order_upass"];
            $isLoggedIn = true;
        }
    } catch (PDOException $e) {
    }
}

$hasErrors = !empty($errorMessages);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LunaFlora - Ночные букеты</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container header-container">
            <a href="#" class="logo"><i class="fas fa-moon"></i>.LunaFlora</a>
            <nav>
                <ul>
                    <li><a href="#home">Главная</a></li>
                    <li><a href="#catalog">Коллекция</a></li>
                    <li><a href="#contact">Заказ</a></li>
                    <li><a href="#contacts">Контакты</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li>
                            <form method="POST" style="display:inline;">
                                <button type="submit" name="logout" class="btn btn-sm btn-danger">Выйти</button>
                            </form>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <section class="hero" id="home">
        <video class="hero-video" autoplay muted loop playsinline>
            <source src="pv.mp4" type="video/mp4">
        </video>
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-content">
                <h1>Цветы, которые сияют в темноте</h1>
                <p>Эксклюзивные букеты, созданные для особых моментов. Доставка, индивидуальный подход и цветы, которые запомнятся навсегда.</p>
                <div class="hero-btns">
                    <a href="#catalog" class="btn">Исследовать коллекцию</a>
                    <a href="#contact" class="btn btn-outline">Заказать консультацию</a>
                </div>
            </div>
        </div>
    </section>

    <section class="slider-section" id="catalog">
        <div class="container">
            <div class="section-title">
                <h2>Ночная коллекция</h2>
                <p>Цветы, которые раскрывают свою красоту при лунном свете. Эксклюзивные композиции для романтических вечеров.</p>
            </div>
            <?php
            $slides = [
                ["img" => "p1.jpg", "alt" => "Лунные розы", "name" => "Лунные розы (6 850 Р)"],
                ["img" => "p2.jpg", "alt" => "Полуночный сад", "name" => "Полуночный сад (4 290 Р)"],
                ["img" => "p3.jpg", "alt" => "Сияние ночи", "name" => "Сияние ночи (5 490 Р)"],
            ];
            ?>
            <div class="slider-container">
                <div class="slider" id="slider">
                    <?php foreach ($slides as $s): ?>
                        <div class="slide">
                            <img src="<?= $s["img"] ?>" alt="<?= htmlspecialchars($s["alt"]) ?>">
                            <div class="slide-overlay">
                                <div class="slide-caption">
                                    <h3><?= htmlspecialchars($s["name"]) ?></h3>
                                    <a href="#contact" class="btn">Заказать</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="slider-nav">
                    <button class="slider-btn" id="prevBtn">
                        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button class="slider-btn" id="nextBtn">
                        <svg viewBox="0 0 24 24"><path d="M10 6l6 6-6 6"/></svg>
                    </button>
                </div>
                <div class="slider-dots" id="sliderDots"></div>
            </div>
        </div>
    </section>

    <section class="contact-section" id="contact">
        <div class="container">
            <div class="section-title">
                <h2><?= $isLoggedIn
                    ? "Редактирование заказа"
                    : "Заказать букет" ?></h2>
                <p>Мы работаем с 8:00 до 20:00</p>
            </div>

            <?php if ($hasErrors): ?>
                <div class="error-box">
                    <?php foreach ($errorMessages as $field => $msg): ?>
                        <p><strong><?= htmlspecialchars(
                            $field === "login"
                                ? "Авторизация"
                                : ($field === "general"
                                    ? "Ошибка"
                                    : ucfirst($field)),
                        ) ?>:</strong> <?= htmlspecialchars($msg) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="success-box">
                    <p><?= $successMessage ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$isLoggedIn): ?>
            <div class="login-form">
                <h4><i class="fas fa-lock"></i> Уже есть логин и пароль?</h4>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="login_username">Логин</label>
                            <input type="text" id="login_username" name="login_username" class="form-control" placeholder="Ваш логин">
                        </div>
                        <div class="form-group">
                            <label for="login_password">Пароль</label>
                            <input type="password" id="login_password" name="login_password" class="form-control" placeholder="Ваш пароль">
                        </div>
                        <button type="submit" name="login_action" class="btn btn-sm">Войти</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="contact-container">
                <div class="contact-info">
                    <h3>Доставка</h3>
                    <p>Наши курьеры доставят букет точно в указанное время.</p>
                    <div class="contact-details">
                        <div class="contact-item">
                            <div>
                                <h4>Время работы</h4>
                                <p>8:00 - 20:00</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div>
                                <h4>Телефон</h4>
                                <p>+7 (000) 000-00-00</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div>
                                <p>ул. Какая-то, 1, Краснодар</p>
                            </div>
                        </div>
                    </div>
                    <?php if ($isLoggedIn): ?>
                        <div style="margin-top:20px;padding:15px;background:var(--success-bg);border:1px solid var(--success-color);border-radius:8px;">
                            <p style="color:#d4edda;">Вы авторизованы.</p>
                            <p style="color:#d4edda;font-size:0.85rem;margin-top:5px;">Вы можете редактировать свои данные ниже.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="contact-form">
                    <?php if ($isLoggedIn): ?>
                        <p style="margin-bottom:15px;color:var(--text-secondary);">
                            <i class="fas fa-edit"></i> Редактирование заказа #<?= $_SESSION[
                                "order_id"
                            ] ?>
                            <?php if ($editData): ?>
                                <span class="edit-badge">Создан: <?= htmlspecialchars(
                                    $editData["created_at"],
                                ) ?></span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <form id="orderForm" action="index.php" method="POST" data-api-url="api.php">
                        <div class="form-group">
                            <label for="name">Ваше имя *</label>
                            <input type="text" id="name" name="name" class="form-control<?= isset(
                                $errorMessages["name"],
                            )
                                ? " error"
                                : "" ?>" placeholder="Как к вам обращаться?" value="<?= htmlspecialchars(
    $oldValues["name"] ?? "",
) ?>" required>
                            <?php if (isset($errorMessages["name"])): ?>
                                <div class="field-error"><?= htmlspecialchars(
                                    $errorMessages["name"],
                                ) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="phone">Телефон *</label>
                            <input type="tel" id="phone" name="phone" class="form-control<?= isset(
                                $errorMessages["phone"],
                            )
                                ? " error"
                                : "" ?>" placeholder="+7 (___) ___-__-__" value="<?= htmlspecialchars(
    $oldValues["phone"] ?? "",
) ?>" required>
                            <?php if (isset($errorMessages["phone"])): ?>
                                <div class="field-error"><?= htmlspecialchars(
                                    $errorMessages["phone"],
                                ) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Выберите букеты *</label>
                            <div class="checkbox-group">
                                <?php foreach (
                                    $bouquetNames
                                    as $code => $name
                                ): ?>
                                    <label>
                                        <input type="checkbox" name="bouquets[]" value="<?= $code ?>" <?= isset(
    $oldValues["bouquets"],
) && in_array($code, (array) $oldValues["bouquets"])
    ? "checked"
    : "" ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errorMessages["bouquets"])): ?>
                                <div class="field-error"><?= htmlspecialchars(
                                    $errorMessages["bouquets"],
                                ) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="message">Особые пожелания</label>
                            <textarea id="message" name="message" class="form-control<?= isset(
                                $errorMessages["message"],
                            )
                                ? " error"
                                : "" ?>" placeholder="Опишите атмосферу, которую хотите создать..."><?= htmlspecialchars(
    $oldValues["message"] ?? "",
) ?></textarea>
                            <?php if (isset($errorMessages["message"])): ?>
                                <div class="field-error"><?= htmlspecialchars(
                                    $errorMessages["message"],
                                ) ?></div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" name="submit" class="btn" style="width:100%;"><?= $isLoggedIn
                            ? "Обновить заказ"
                            : "Заказать ночную доставку" ?></button>
                    </form>

                    <?php if ($isLoggedIn && $editData): ?>
                    <div style="margin-top:20px;padding:15px;background:var(--bg-darker);border-radius:8px;border:1px solid var(--border-color);">
                        <p style="color:var(--text-secondary);font-size:0.85rem;">
                            <i class="fas fa-info-circle"></i> Для изменения данных через API используйте:
                            <br>Логин: <b><?= htmlspecialchars(
                                $editData["login"],
                            ) ?></b>
                            <br>Пароль: <b><?= htmlspecialchars(
                                $_SESSION["order_password"] ?? "********",
                            ) ?></b>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <a href="#" class="footer-logo"><i class="fas fa-moon"></i> LunaFlora</a>
                </div>
                <div class="footer-column">
                    <h3>Навигация</h3>
                    <ul class="footer-links">
                        <li><a href="#home">Главная</a></li>
                        <li><a href="#contact">Заказ</a></li>
                    </ul>
                </div>
                <div class="footer-column" id="contacts">
                    <h3>Контакты</h3>
                    <ul class="footer-links">
                        <li>+7 (000) 000-00-00</li>
                        <li>surname@gmail.com</li>
                        <li>ул. Неизвестная 1</li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <script>
    window.API_URL = 'api.php';
    window.IS_LOGGED_IN = <?= $isLoggedIn && $editData ? "true" : "false" ?>;
    <?php if ($isLoggedIn && $editData): ?>
    window.USER_LOGIN = '<?= htmlspecialchars(
        $editData["login"],
        ENT_QUOTES,
    ) ?>';
    window.USER_PASSWORD = '<?= htmlspecialchars(
        $_SESSION["order_password"] ?? "",
        ENT_QUOTES,
    ) ?>';
    <?php endif; ?>
    </script>
    <script src="script.js"></script>
</body>
</html>
