<?php
// test.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "PHP está funcionando!<br>";

// Testar conexão com banco de dados
include '../includes/db.php';
if ($pdo) {
    echo "Conexão com banco de dados OK!<br>";
} else {
    echo "Erro na conexão com banco de dados!<br>";
}

// Testar sessão
session_start();
$_SESSION['teste'] = 'funcionando';
echo "Sessão funcionando! ID: " . session_id() . "<br>";

phpinfo();
?>