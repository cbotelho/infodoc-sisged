<?php
$host = 'localhost';
$db   = 'u578749560_sisged'; // Substitua pelo nome do seu banco de dados
$user = 'u578749560_botelho_sisged'; // Substitua pelo seu usuário do banco de dados
$pass = '@#Botelho751953#@'; // Substitua pela sua senha do banco de dados
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
