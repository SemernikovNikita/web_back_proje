<?php

header("Content-Type: text/html; charset=UTF-8");
session_start();

require_once __DIR__ . '/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login']) && isset($_POST['password'])) {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login) || empty($password)) {
        $error = 'Введите логин и пароль.';
    } else {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, full_name, password_hash FROM application WHERE login = :login");
        $stmt->execute([':login' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_login'] = $login;
            header('Location: form.php');
            exit();
        } else {
            $error = 'Неверный логин или пароль.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <link rel="stylesheet" href="../frontend/style.css">
    <style>
        body {
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Cascadia code", monospace;
        }
        .login-box {
            background: var(--bg-dark);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 35px 30px;
            width: 100%;
            max-width: 380px;
            box-shadow: var(--shadow);
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.8rem;
        }
        .login-box .form-group {
            margin-bottom: 20px;
        }
        .login-box .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .login-box .form-control {
            width: 100%;
            padding: 14px;
            background: var(--bg-dark);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            color: var(--text-primary);
            box-sizing: border-box;
        }
        .login-box .form-control:focus {
            border-color: var(--bg-light);
            outline: none;
        }
        .login-box .btn {
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
        .login-box .btn:hover {
            background: var(--primary-color);
            color: var(--bg-light);
        }
        .error {
            color: #f8d7da;
            background: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        .link {
            text-align: center;
            margin-top: 20px;
        }
        .link a {
            color: var(--bg-light);
            text-decoration: none;
            border: 1px solid var(--bg-light);
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            display: inline-block;
            margin: 5px;
            transition: background 0.3s ease;
        }
        .link a:hover {
            background: var(--bg-light);
            color: #13071d;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Вход</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="login">Логин:</label>
                <input type="text" id="login" name="login" class="form-control" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" class="form-control" autocomplete="off">
            </div>
            <button type="submit" class="btn">Войти</button>
        </form>
        <div class="link">
            <a href="form.php">Назад</a>
            <a href="admin.php">Админ-панель</a>
        </div>
    </div>
</body>
</html>
