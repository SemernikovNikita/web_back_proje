<?php

header("Content-Type: text/html; charset=UTF-8");
session_start();

require_once __DIR__ . '/functions.php';

$messages = [];

if (!empty($_SESSION['credentials'])) {
    $credentials = $_SESSION['credentials'];
    unset($_SESSION['credentials']);
    $messages[] = '<div class="msg-box msg-success">Спасибо, результаты сохранены.<br>Ваш логин: <b>' . htmlspecialchars($credentials['login']) . '</b><br>Ваш пароль: <b>' . htmlspecialchars($credentials['password']) . '</b><br>Сохраните их для редактирования данных.</div>';
} elseif (!empty($_COOKIE["save"])) {
    setcookie("save", "", 100000);
    $messages[] = '<div class="msg-box msg-success">Спасибо, данные сохранены.</div>';
}

if (!empty($_SESSION['errors'])) {
    $err = $_SESSION['errors'];
    unset($_SESSION['errors']);
    if (is_array($err)) {
        foreach ($err as $e) {
            $messages[] = '<div class="msg-box msg-error">' . htmlspecialchars(is_string($e) ? $e : '') . '</div>';
        }
    } else {
        $messages[] = '<div class="msg-box msg-error">' . htmlspecialchars($err) . '</div>';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: form.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ букета</title>
    <link rel="stylesheet" href="../frontend/style.css">
    <style>
        body {
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: "Cascadia code", monospace;
        }
        .page-header {
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-dark);
        }
        .page-header .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .form-page {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .form-box {
            background: var(--bg-dark);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 35px 30px;
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow);
        }
        .form-box h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.8rem;
        }
        .form-box .form-group {
            margin-bottom: 20px;
        }
        .form-box .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .form-box .form-control {
            width: 100%;
            padding: 14px;
            background: var(--bg-dark);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            color: var(--text-primary);
            box-sizing: border-box;
        }
        .form-box .form-control:focus {
            border-color: var(--bg-light);
            outline: none;
        }
        .form-box .btn {
            width: 100%;
            background: var(--bg-light);
            color: #13071d;
            padding: 14px 30px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        .form-box .btn:hover {
            background: var(--primary-color);
            color: var(--bg-light);
        }
        .form-box .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-box select.form-control {
            min-height: 120px;
        }
        .form-box small {
            color: var(--text-secondary);
            display: block;
            margin-top: 5px;
        }
        .form-box textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        .auth-links {
            text-align: center;
            margin-bottom: 20px;
        }
        .auth-links a {
            color: var(--bg-light);
            text-decoration: none;
            border: 1px solid var(--bg-light);
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            display: inline-block;
            transition: background 0.3s ease;
        }
        .auth-links a:hover {
            background: var(--bg-light);
            color: #13071d;
        }
        .auth-info {
            text-align: center;
            margin-bottom: 20px;
            color: var(--text-secondary);
        }
        .auth-info a {
            color: var(--bg-light);
            margin-left: 10px;
        }
        .msg-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        .msg-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .msg-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .msg-box a {
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">
            <a href="form.php" class="logo">LunaFlora</a>
        </div>
    </div>

    <div class="form-page">
        <div class="form-box">
            <div id="api-messages">
                <?php foreach ($messages as $msg) echo $msg; ?>
            </div>

            <?php if (empty($_SESSION['user_id'])): ?>
                <div class="auth-links">
                    <a href="login.php">Войти для редактирования</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['user_id'])): ?>
                <div class="auth-info">
                    Вы вошли как: <b><?php echo htmlspecialchars($_SESSION['user_name']); ?></b>
                    <a href="form.php?logout=1">Выйти</a>
                </div>
            <?php endif; ?>

            <h2>Заказ букета</h2>

            <form action="index.php" method="POST" data-api-url="api.php" data-login="<?php echo isset($_SESSION['user_login']) ? htmlspecialchars($_SESSION['user_login'], ENT_QUOTES, 'UTF-8') : ''; ?>">

                <?php if (!empty($_SESSION['user_id'])): ?>
                    <div class="form-group">
                        <label for="api_password">Пароль (для подтверждения):</label>
                        <input type="password" id="api_password" name="api_password" class="form-control" placeholder="Введите ваш пароль">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Ваше имя *</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="Как к вам обращаться?" required>
                </div>

                <div class="form-group">
                    <label for="phone">Телефон *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="+7 (___) ___-__-__" required>
                </div>

                <div class="form-group">
                    <label for="bouquet">Выберите букет</label>
                    <select id="bouquet" name="bouquet" class="form-control">
                        <option value="black-moon">Полуночный сад (4 290 ₽)</option>
                        <option value="blue-evening">Сияние ночи (5 490 ₽)</option>
                        <option value="moonlight">Лунные розы (6 850 ₽)</option>
                        <option value="custom">Индивидуальный ночной букет</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="message">Особые пожелания</label>
                    <textarea id="message" name="message" class="form-control" placeholder="Опишите атмосферу, которую хотите создать..."></textarea>
                </div>

                <button type="submit" name="submit" class="btn">Заказать ночную доставку</button>
            </form>
        </div>
    </div>

    <script src="form.js"></script>
</body>
</html>
