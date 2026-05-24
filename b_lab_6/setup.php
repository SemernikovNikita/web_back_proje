<?php

header("Content-Type: text/html; charset=UTF-8");

$host = 'localhost';
$dbname = 'u82190';
$user = 'u82190';
$pass = '8528410';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cols = $pdo->query("SHOW COLUMNS FROM application")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('login', $cols)) {
        $pdo->exec("ALTER TABLE application ADD COLUMN login VARCHAR(32) UNIQUE");
    }
    if (!in_array('password_hash', $cols)) {
        $pdo->exec("ALTER TABLE application ADD COLUMN password_hash VARCHAR(255)");
    }

    echo '<div style="color:green; padding:20px; font-family:sans-serif;">Готово. Поля login и password_hash добавлены (если отсутствовали).</div>';
} catch (PDOException $e) {
    echo '<div style="color:red; padding:20px; font-family:sans-serif;">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
