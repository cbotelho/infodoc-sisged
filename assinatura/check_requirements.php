<?php
echo "Verificando requisitos para assinatura digital ICP-Brasil:\n\n";

// Verificar extensão OpenSSL
if (!extension_loaded('openssl')) {
    echo "❌ A extensão OpenSSL não está habilitada no PHP.\n";
    echo "   - Habilite a extensão no php.ini: extension=openssl\n";
} else {
    echo "✅ Extensão OpenSSL está habilitada.\n";
    
    // Verificar versão do OpenSSL
    $sslVersion = explode(' ', OPENSSL_VERSION_TEXT);
    $sslVersion = $sslVersion[1] ?? 'desconhecida';
    echo "   - Versão do OpenSSL: $sslVersion\n";
    
    if (version_compare($sslVersion, '1.0.1', '<')) {
        echo "⚠️  Aviso: A versão do OpenSSL é antiga. Recomenda-se atualizar para uma versão mais recente.\n";
    }
}

// Verificar se o TCPDF está instalado
$tcpdfInstalled = file_exists(__DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php');
$fpdiInstalled = file_exists(__DIR__ . '/vendor/setasign/fpdi/src/autoload.php');

if (!$tcpdfInstalled || !$fpdiInstalled) {
    echo "\n❌ Bibliotecas necessárias não encontradas.\n";
    echo "   - TCPDF: " . ($tcpdfInstalled ? '✅' : '❌') . "\n";
    echo "   - FPDI: " . ($fpdiInstalled ? '✅' : '❌') . "\n";
    echo "\nPara instalar as dependências, execute no diretório do projeto:\n";
    echo "composer require tecnickcom/tcpdf setasign/fpdi\n";
} else {
    echo "\n✅ Bibliotecas necessárias estão instaladas.\n";
}

// Verificar permissões de escrita
$writableDirs = [
    'uploads' => is_writable(__DIR__ . '/uploads'),
    'vendor' => is_writable(__DIR__ . '/vendor')
];

$allWritable = true;
echo "\nVerificando permissões de escrita:\n";
foreach ($writableDirs as $dir => $isWritable) {
    echo "- $dir: " . ($isWritable ? '✅' : '❌') . "\n";
    if (!$isWritable) $allWritable = false;
}

if (!$allWritable) {
    echo "\n⚠️  Corrija as permissões dos diretórios listados acima para garantir o funcionamento correto.\n";
}

echo "\nVerificação concluída. " . ($allWritable && extension_loaded('openssl') && $tcpdfInstalled && $fpdiInstalled 
    ? "✅ Todos os requisitos estão atendidos!" 
    : "❌ Alguns requisitos não foram atendidos.") . "\n";
?>
