<?php
define("DB_HOST", "localhost");
define("DB_NAME", "u82190");
define("DB_USER", "u82190");
define("DB_PASS", "8528410");
define("ADMIN_LOGIN", "admin");
define("ADMIN_PASS", "admin123");

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );
    }
    return $pdo;
}
