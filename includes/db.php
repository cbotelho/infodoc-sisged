<?php
// Tenta carregar as configurações caso não estejam importadas
if (!defined('DB_SERVER')) {
    $configFile = __DIR__ . '/../config/database.php';
    if (file_exists($configFile)) {
        require_once $configFile;
    }
}

$host = defined('DB_SERVER') ? DB_SERVER : (getenv('DB_SERVER') ?: '195.200.4.41');
$db   = defined('DB_DATABASE') ? DB_DATABASE : (getenv('DB_DATABASE') ?: 'sisged_gea');
$user = defined('DB_SERVER_USERNAME') ? DB_SERVER_USERNAME : (getenv('DB_SERVER_USERNAME') ?: 'admin');
$pass = defined('DB_SERVER_PASSWORD') ? DB_SERVER_PASSWORD : (getenv('DB_SERVER_PASSWORD') ?: '');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
