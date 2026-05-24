<?php
header("Content-Type: text/html; charset=UTF-8");

$host = 'localhost';
$dbname = 'u82190';
$user = 'u82190';
$pass = '8528410';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create admin table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        login VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check if admin user already exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Create default admin login: admin, password: admin
        $password_hash = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin (login, password_hash) VALUES (:login, :hash)");
        $stmt->execute(['login' => 'admin', 'hash' => $password_hash]);
        echo '<div style="color:green; padding:20px; font-family:sans-serif;">Admin table created and default admin user added (login: admin, password: admin).</div>';
    } else {
        echo '<div style="color:green; padding:20px; font-family:sans-serif;">Admin table already exists. Admin user(s) present.</div>';
    }
} catch (PDOException $e) {
    echo '<div style="color:red; padding:20px; font-family:sans-serif;">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>