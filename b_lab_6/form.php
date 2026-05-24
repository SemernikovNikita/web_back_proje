<?php

header("Content-Type: text/html; charset=UTF-8");
session_start();

require_once __DIR__ . '/functions.php';

initCsrfToken();
$csrf_token = $_SESSION['csrf_token'];

$messages = [];
$credentials = null;

if (!empty($_SESSION['credentials'])) {
    $credentials = $_SESSION['credentials'];
    unset($_SESSION['credentials']);
    $messages[] = '<div class="success-message">Спасибо, результаты сохранены.<br>Ваш логин: <b>' . htmlspecialchars($credentials['login']) . '</b><br>Ваш пароль: <b>' . htmlspecialchars($credentials['password']) . '</b><br>Сохраните их для редактирования данных.</div>';
} elseif (!empty($_COOKIE["save"])) {
    setcookie("save", "", 100000);
    $messages[] = '<div class="success-message">Спасибо, результаты сохранены.</div>';
}

$errors = [];
$errors["full_name"] = !empty($_COOKIE["fio_error"]);
$errors["phone"] = !empty($_COOKIE["phone_error"]);
$errors["email"] = !empty($_COOKIE["email_error"]);
$errors["birth_date"] = !empty($_COOKIE["birth_date_error"]);
$errors["gender"] = !empty($_COOKIE["gender_error"]);
$errors["biography"] = !empty($_COOKIE["biography_error"]);
$errors["agreement"] = !empty($_COOKIE["agreement_error"]);
$errors["languages"] = !empty($_COOKIE["languages_error"]);

$error_messages = [];

if ($errors["full_name"]) {
    setcookie("fio_error", "", 100000);
    setcookie("fio_value", "", 100000);
    $error_messages["full_name"] =
        "ФИО обязательно для заполнения. Используйте только буквы, пробелы и дефисы.";
}

if ($errors["phone"]) {
    setcookie("phone_error", "", 100000);
    setcookie("phone_value", "", 100000);
    $error_messages["phone"] =
        "Телефон обязателен. Формат: цифры, знак +, пробелы, дефисы, скобки (10-20 символов).";
}

if ($errors["email"]) {
    setcookie("email_error", "", 100000);
    setcookie("email_value", "", 100000);
    $error_messages["email"] = "Email обязателен. Формат: example@domain.com";
}

if ($errors["birth_date"]) {
    setcookie("birth_date_error", "", 100000);
    setcookie("birth_date_value", "", 100000);
    $error_messages["birth_date"] =
        "Дата рождения обязательна. Формат: ГГГГ-ММ-ДД";
}

if ($errors["gender"]) {
    setcookie("gender_error", "", 100000);
    setcookie("gender_value", "", 100000);
    $error_messages["gender"] = "Пожалуйста, выберите пол.";
}

if ($errors["biography"]) {
    setcookie("biography_error", "", 100000);
    setcookie("biography_value", "", 100000);
    $error_messages["biography"] =
        "Биография не должна превышать 1000 символов.";
}

if ($errors["agreement"]) {
    setcookie("agreement_error", "", 100000);
    setcookie("agreement_value", "", 100000);
    $error_messages["agreement"] =
        "Необходимо подтвердить согласие с контрактом.";
}

if ($errors["languages"]) {
    setcookie("languages_error", "", 100000);
    setcookie("languages_value", "", 100000);
    $error_messages["languages"] =
        "Выберите хотя бы один язык программирования.";
}

$values = [];
$values["full_name"] = empty($_COOKIE["fio_value"])
    ? ""
    : htmlspecialchars($_COOKIE["fio_value"], ENT_QUOTES, 'UTF-8');
$values["phone"] = empty($_COOKIE["phone_value"])
    ? ""
    : htmlspecialchars($_COOKIE["phone_value"], ENT_QUOTES, 'UTF-8');
$values["email"] = empty($_COOKIE["email_value"])
    ? ""
    : htmlspecialchars($_COOKIE["email_value"], ENT_QUOTES, 'UTF-8');
$values["birth_date"] = empty($_COOKIE["birth_date_value"])
    ? ""
    : htmlspecialchars($_COOKIE["birth_date_value"], ENT_QUOTES, 'UTF-8');
$values["gender"] = empty($_COOKIE["gender_value"])
    ? ""
    : htmlspecialchars($_COOKIE["gender_value"], ENT_QUOTES, 'UTF-8');
$values["biography"] = empty($_COOKIE["biography_value"])
    ? ""
    : htmlspecialchars($_COOKIE["biography_value"], ENT_QUOTES, 'UTF-8');
$values["agreement"] = empty($_COOKIE["agreement_value"])
    ? ""
    : $_COOKIE["agreement_value"];
$values["languages"] = empty($_COOKIE["languages_value"])
    ? []
    : explode(",", $_COOKIE["languages_value"]);

$has_errors = false;
foreach ($errors as $e) {
    if ($e) {
        $has_errors = true;
        break;
    }
}

if (!empty($_SESSION['user_id']) && !$has_errors) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT full_name, phone, email, birth_date, gender, biography, agreement, login FROM application WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($app) {
        $_SESSION['user_login'] = $app['login'];
        $values["full_name"] = htmlspecialchars($app['full_name']);
        $values["phone"] = htmlspecialchars($app['phone']);
        $values["email"] = htmlspecialchars($app['email']);
        $values["birth_date"] = htmlspecialchars($app['birth_date']);
        $values["gender"] = htmlspecialchars($app['gender']);
        $values["biography"] = htmlspecialchars($app['biography']);
        $values["agreement"] = $app['agreement'];

        $stmt_lang = $pdo->prepare("SELECT pl.name FROM programming_language pl JOIN application_language al ON pl.id = al.language_id WHERE al.application_id = :id");
        $stmt_lang->execute([':id' => $_SESSION['user_id']]);
        $values["languages"] = $stmt_lang->fetchAll(PDO::FETCH_COLUMN);
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('fio_value', '', time() - 3600, '/');
    setcookie('phone_value', '', time() - 3600, '/');
    setcookie('email_value', '', time() - 3600, '/');
    setcookie('birth_date_value', '', time() - 3600, '/');
    setcookie('gender_value', '', time() - 3600, '/');
    setcookie('biography_value', '', time() - 3600, '/');
    setcookie('agreement_value', '', time() - 3600, '/');
    setcookie('languages_value', '', time() - 3600, '/');
    header('Location: form.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форма регистрации</title>
    <style>
        .error-field {
            border: 2px solid red !important;
            background-color: #ffe6e6;
        }

        .error-message {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }

        .success-message {
            color: green;
            background-color: #e8f5e9;
            border: 1px solid green;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .form-group {
            margin-bottom: 20px;
            padding: 10px;
        }

        label {
            font-weight: bold;
        }

        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        textarea {
            max-width: 500px;
            min-height: 100px;
        }

        select[multiple] {
            min-height: 120px;
        }

        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }

        .radio-group {
            margin-top: 5px;
        }

        .radio-group label {
            font-weight: normal;
            margin-right: 15px;
        }

        .messages {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div id="api-messages" class="messages">
    <?php
    if (!empty($messages)) {
        foreach ($messages as $message) {
            echo $message;
        }
    }

    foreach ($error_messages as $field => $message) {
        echo '<div class="error-message" style="margin-bottom: 10px;">❌ ' .
            $message .
            "</div>";
    }
    ?>
</div>

<div style="text-align:center; margin: 10px 0;">
<?php if (empty($_SESSION['user_id'])): ?>
    <a href="login.php" style="font-size:16px; color:#4CAF50; text-decoration:none; border:1px solid #4CAF50; padding:8px 16px; border-radius:4px;">Войти для редактирования данных</a>
<?php else: ?>
    <span>Вы вошли как: <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b></span>
    <a href="form.php?logout=1" style="margin-left:15px; color:#6495ed;">Выйти</a>
<?php endif; ?>
</div>

<form action="index.php" method="POST" data-api-url="api.php" data-login="<?php echo isset($_SESSION['user_login']) ? htmlspecialchars($_SESSION['user_login'], ENT_QUOTES, 'UTF-8') : ''; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <h2>Анкета пользователя</h2>

<?php if (!empty($_SESSION['user_id'])): ?>
    <div class="form-group">
        <label for="api_password">Пароль (для подтверждения изменений):</label><br>
        <input type="password" id="api_password" name="api_password" placeholder="Введите ваш пароль">
    </div>
<?php endif; ?>


    <div class="form-group">
        <label for="full_name">ФИО: <span style="color: red;">*</span></label><br>
        <input type="text"
               id="full_name"
               name="full_name"
               value="<?php echo $values["full_name"]; ?>"
               placeholder="Иванов Иван Иванович"
               class="<?php echo $errors["full_name"] ? "error-field" : ""; ?>">
        <?php if ($errors["full_name"]): ?>
            <span class="error-message"><?php echo $error_messages[
                "full_name"
            ]; ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="phone">Телефон: <span style="color: red;">*</span></label><br>
        <input type="tel"
               id="phone"
               name="phone"
               value="<?php echo $values["phone"]; ?>"
               placeholder="+7 (123) 456-78-90"
               class="<?php echo $errors["phone"] ? "error-field" : ""; ?>">
        <?php if ($errors["phone"]): ?>
            <span class="error-message"><?php echo $error_messages[
                "phone"
            ]; ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="email">E-mail: <span style="color: red;">*</span></label><br>
        <input type="email"
               id="email"
               name="email"
               value="<?php echo $values["email"]; ?>"
               placeholder="example@domain.com"
               class="<?php echo $errors["email"] ? "error-field" : ""; ?>">
        <?php if ($errors["email"]): ?>
            <span class="error-message"><?php echo $error_messages[
                "email"
            ]; ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="birth_date">Дата рождения: <span style="color: red;">*</span></label><br>
        <input type="date"
               id="birth_date"
               name="birth_date"
               value="<?php echo $values["birth_date"]; ?>"
               class="<?php echo $errors["birth_date"]
                   ? "error-field"
                   : ""; ?>">
        <?php if ($errors["birth_date"]): ?>
            <span class="error-message"><?php echo $error_messages[
                "birth_date"
            ]; ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label>Пол: <span style="color: red;">*</span></label><br>
        <div class="radio-group">
            <label>
                <input type="radio" name="gender" value="male"
                    <?php echo $values["gender"] == "male"
                        ? "checked"
                        : ""; ?>> Мужской
            </label>
            <label>
                <input type="radio" name="gender" value="female"
                    <?php echo $values["gender"] == "female"
                        ? "checked"
                        : ""; ?>> Женский
            </label>
        </div>
        <?php if ($errors["gender"]): ?>
            <span class="error-message"><?php echo $error_messages[
                "gender"
            ]; ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="languages">Любимые языки программирования: <span style="color: red;">*</span></label><br>
        <select name="languages[]" id="languages" multiple size="5"
                class="<?php echo $errors["languages"]
                    ? "error-field"
                    : ""; ?>">
            <option value="PHP" <?php echo in_array("PHP", $values["languages"])
                ? "selected"
                : ""; ?>>PHP</option>
            <option value="Python" <?php echo in_array(
                "Python",
                $values["languages"],
            )
                ? "selected"
                : ""; ?>>Python</option>
            <option value="Java" <?php echo in_array(
                "Java",
                $values["languages"],
            )
                ? "selected"
                : ""; ?>>Java</option>
            <option value="JavaScript" <?php echo in_array(
                "JavaScript",
                $values["languages"],
            )
                ? "selected"
                : ""; ?>>JavaScript</option>
            <option value="C++" <?php echo in_array("C++", $values["languages"])
                ? "selected"
                : ""; ?>>C++</option>
            <option value="C#" <?php echo in_array("C#", $values["languages"])
                ? "selected"
                : ""; ?>>C#</option>
            <option value="Ruby" <?php echo in_array(
                "Ruby",
                $values["languages"],
            )
                ? "selected"
                : ""; ?>>Ruby</option>
            <option value="Go" <?php echo in_array("Go", $values["languages"])
                ? "selected"
                : ""; ?>>Go</option>
        </select>
        <small>Удерживайте Ctrl (Cmd на Mac) для выбора нескольких языков</small>
        <?php if ($errors["languages"]): ?>
            <span class="error-message"><?php echo $error_messages[
                "languages"
            ]; ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="biography">Биография:</label><br>
        <textarea id="biography"
                  name="biography"
                  placeholder="Расскажите немного о себе..."
                  class="<?php echo $errors["biography"]
                      ? "error-field"
                      : ""; ?>"><?php echo $values["biography"]; ?></textarea>
        <?php if ($errors["biography"]): ?>
            <span class="error-message"><?php echo $error_messages[
                "biography"
            ]; ?></span>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="agreement" value="1"
                <?php echo $values["agreement"] == "1" ? "checked" : ""; ?>>
            С контрактом ознакомлен(а) <span style="color: red;">*</span>
        </label>
        <?php if ($errors["agreement"]): ?>
            <span class="error-message"><?php echo $error_messages[
                "agreement"
            ]; ?></span>
        <?php endif; ?>
    </div>

    <input type="submit" name="submit" value="Сохранить">
</form>
<script src="form.js"></script>
</body>
</html>
