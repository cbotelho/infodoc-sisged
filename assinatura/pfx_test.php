<?php
// pfx_test.php — coloque em /assinatura/ e abra via navegador para testar leitura de .pfx/.p12
header('Content-Type: text/plain; charset=utf-8');

// Ajuste estes valores antes de abrir o script no navegador
$filename = __DIR__ . '/certs/test.pfx'; // coloque seu .pfx aqui
$password = 'SENHA_DO_CERTIFICADO';

echo "Arquivo testado: $filename\n";
if (!file_exists($filename)) {
    echo $filename . " - Arquivo não encontrado. Coloque seu .pfx em /assinatura/certs/ e renomeie para test.pfx ou edite este arquivo.\n";
    exit;
}

$data = file_get_contents($filename);
if ($data === false) {
    echo "Falha ao ler o arquivo\n";
    exit;
}

// Limpar erros anteriores
while (openssl_error_string());

$ok = openssl_pkcs12_read($data, $certs, $password);
if (!$ok) {
    echo "openssl_pkcs12_read falhou. OpenSSL errors:\n";
    while ($err = openssl_error_string()) {
        echo $err . "\n";
    }
    echo "\nSe o erro indicar 'mac verify failure' ou senha inválida, verifique a senha do .pfx.\n";
    exit;
}

echo "PKCS#12 lido com sucesso. Itens encontrados: " . implode(', ', array_keys($certs)) . "\n";
if (!empty($certs['cert'])) {
    $info = openssl_x509_parse($certs['cert']);
    echo "Subject CN: " . ($info['subject']['CN'] ?? '(não encontrado)') . "\n";
    echo "Validade: " . date('c', $info['validFrom_time_t']) . " até " . date('c', $info['validTo_time_t']) . "\n";
}

if (!empty($certs['pkey'])) {
    echo "A chave privada foi extraída com sucesso (não será exibida).\n";
}

// Para diagnóstico adicional, mostrar algumas infos PHP
echo "\nAmbiente:\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "OpenSSL: " . OPENSSL_VERSION_TEXT . "\n";

echo "\nFim do teste.\n";
?>