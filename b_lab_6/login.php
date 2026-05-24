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
    <title>Вход</title>
    <style>
        body { font-family: "Century Gothic", sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 300px; }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        input[type="submit"] { width: 100%; background: #4CAF50; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #45a049; }
        .error { color: red; margin-bottom: 10px; text-align: center; }
        .link { text-align: center; margin-top: 15px; }
        .link a { color: #6495ed; text-decoration: none; }
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
                <input type="text" id="login" name="login" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" autocomplete="off">
            </div>
            <input type="submit" value="Войти">
        </form>
        <div class="link">
            <a href="form.php">Назад к форме</a>
            <br><br>
            <a href="admin.php">Админ-панель</a>
        </div>
    </div>
</body>
</html>
