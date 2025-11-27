<?php
// test_upload.php
header('Content-Type: text/plain; charset=utf-8');

$uploadDir = __DIR__ . '/uploads/';
$testFile = $uploadDir . 'test_permission_' . time() . '.txt';
$testContent = 'Teste de permissão em ' . date('Y-m-d H:i:s');

// Verificar se o diretório existe
if (!is_dir($uploadDir)) {
    die("ERRO: O diretório de uploads não existe: $uploadDir");
}

// Verificar se é possível gravar no diretório
if (!is_writable($uploadDir)) {
    die("ERRO: O diretório de uploads não tem permissão de escrita: $uploadDir");
}

// Tentar criar um arquivo de teste
if (file_put_contents($testFile, $testContent) === false) {
    die("ERRO: Não foi possível criar o arquivo de teste em: $uploadDir");
}

// Verificar se o arquivo foi criado
if (!file_exists($testFile)) {
    die("ERRO: O arquivo de teste não foi criado corretamente em: $testFile");
}

// Verificar se é possível ler o arquivo
$readContent = file_get_contents($testFile);
if ($readContent === false) {
    unlink($testFile);
    die("ERRO: Não foi possível ler o arquivo de teste: $testFile");
}

// Verificar se o conteúdo está correto
if ($readContent !== $testContent) {
    unlink($testFile);
    die("ERRO: O conteúdo do arquivo de teste não corresponde ao esperado");
}

// Limpar - remover o arquivo de teste
if (!unlink($testFile)) {
    die("AVISO: Não foi possível remover o arquivo de teste: $testFile");
}

echo "SUCESSO: O diretório de uploads está configurado corretamente.\n";
echo "Caminho do diretório: " . realpath($uploadDir) . "\n";
echo "Proprietário: " . (function_exists('posix_getpwuid') ? @posix_getpwuid(fileowner($uploadDir))['name'] : 'N/A') . "\n";
echo "Permissões: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "\n";