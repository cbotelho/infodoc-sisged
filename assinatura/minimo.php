<?php
// minimo.php - Script MÍNIMO para teste
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== TESTE MÍNIMO ===\n";
echo "PHP Versão: " . phpversion() . "\n";
echo "Arquivo: " . __FILE__ . "\n";
echo "Diretório: " . __DIR__ . "\n";
echo "Server API: " . php_sapi_name() . "\n";
echo "Memória: " . ini_get('memory_limit') . "\n";
echo "POST Max: " . ini_get('post_max_size') . "\n";
echo "Upload Max: " . ini_get('upload_max_filesize') . "\n";
echo "=== FIM ===\n";
?>