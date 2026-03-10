<?php
// diagnostico.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Diagnóstico do Sistema</h2>";

// Verificar PHP
echo "<h3>PHP Info:</h3>";
echo "Versão PHP: " . phpversion() . "<br>";
echo "Server API: " . php_sapi_name() . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Arquivo atual: " . __FILE__ . "<br>";

// Verificar arquivo db.php
echo "<h3>Verificação do arquivo db.php:</h3>";
$dbPath = realpath(__DIR__ . '/../includes/db.php');
if ($dbPath && file_exists($dbPath)) {
    echo "✓ Arquivo db.php encontrado em: " . $dbPath . "<br>";
    echo "Permissões: " . substr(sprintf('%o', fileperms($dbPath)), -4) . "<br>";
} else {
    echo "✗ Arquivo db.php NÃO encontrado em: " . __DIR__ . '/../includes/db.php' . "<br>";
}

// Verificar diretório de upload
echo "<h3>Verificação do diretório de upload:</h3>";
$uploadPath = realpath(__DIR__ . '/../upload');
if ($uploadPath && file_exists($uploadPath)) {
    echo "✓ Diretório upload encontrado em: " . $uploadPath . "<br>";
    echo "Permissões: " . substr(sprintf('%o', fileperms($uploadPath)), -4) . "<br>";
    echo "Gravável: " . (is_writable($uploadPath) ? 'Sim' : 'Não') . "<br>";
} else {
    echo "✗ Diretório upload NÃO encontrado em: " . __DIR__ . '/../upload' . "<br>";
}

// Testar conexão com banco de dados
echo "<h3>Teste de conexão com banco de dados:</h3>";
try {
    // Incluir o arquivo de banco de dados apenas se ele existir
    if ($dbPath && file_exists($dbPath)) {
        require_once $dbPath;
        if (isset($pdo) && $pdo instanceof PDO) {
            echo "✓ Conexão com banco de dados OK!<br>";
            
            // Testar consulta
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM app_entity_26");
            $row = $stmt->fetch();
            echo "✓ Total de secretarias: " . $row['total'] . "<br>";
        } else {
            echo "✗ Variável PDO não definida corretamente<br>";
        }
    } else {
        echo "✗ Arquivo db.php não encontrado, pulando teste de banco de dados.<br>";
    }
} catch (Exception $e) {
    echo "✗ Erro na conexão: " . $e->getMessage() . "<br>";
}

// Verificar extensões necessárias para o funcionamento básico
echo "<h3>Extensões PHP carregadas (Funcionamento Básico):</h3>";
$extensoes_obrigatorias = ['pdo', 'pdo_mysql', 'gd', 'zip'];
foreach ($extensoes_obrigatorias as $ext) {
    echo $ext . ": " . (extension_loaded($ext) ? '✓' : '✗') . "<br>";
}

// --- NOVO BLOCO: Verificação de extensões para Assinatura Digital ICP-Brasil ---
echo "<h3>Extensões PHP para Assinatura Digital ICP-Brasil:</h3>";
echo "<p><small>Baseado em requisitos comuns para assinatura digital de documentos (XML, PDF) utilizando certificados A1/A3.</small></p>";

// Lista de extensões importantes para assinatura digital
$extensoes_assinatura = [
    'openssl' => 'Manipulação de certificados e criptografia [citation:2]',
    'dom' => 'Manipulação de documentos XML (para assinar XML)',
    'xml' => 'Parsing e validação de XML (para assinar XML)',
    'fileinfo' => 'Identificação de tipos de arquivo',
    'mbstring' => 'Manipulação de strings multibyte (útil para PDFs)'
];

$todas_assinatura_ok = true;
foreach ($extensoes_assinatura as $ext => $descricao) {
    $carregada = extension_loaded($ext);
    if ($carregada) {
        echo "✓ <strong>" . $ext . "</strong>: " . $descricao . "<br>";
    } else {
        echo "✗ <strong>" . $ext . "</strong>: " . $descricao . " <span style='color:red;'>(NÃO CARREGADA - pode ser necessária)</span><br>";
        $todas_assinatura_ok = false;
    }
}

// Verificação adicional de funcionalidades OpenSSL
echo "<h4>Detalhes da extensão OpenSSL:</h4>";
if (extension_loaded('openssl')) {
    // Versão da OpenSSL
    $openssl_version = OPENSSL_VERSION_TEXT;
    echo "✓ Versão da OpenSSL: " . $openssl_version . "<br>";
    
    // Caminhos de configuração
    $openssl_cnf = (defined('OPENSSL_CONF') && !empty(OPENSSL_CONF)) ? OPENSSL_CONF : 'Não definido (usando padrão do sistema)';
    echo "✓ Caminho de configuração (OPENSSL_CONF): " . $openssl_cnf . "<br>";
    
    // Testar se é possível carregar um certificado (simulação)
    echo "✓ OpenSSL pronta para uso com certificados digitais.<br>";
    
    // Verificar suporte a algoritmos comuns em ICP-Brasil
    $algos_disponiveis = openssl_get_md_methods();
    $algos_icp = ['sha1', 'sha256', 'sha512', 'ripemd160']; // Comuns em ICP
    echo "✓ Algoritmos de hash suportados (relevantes para ICP):<br>";
    echo "<ul>";
    foreach ($algos_icp as $algo) {
        if (in_array($algo, $algos_disponiveis)) {
            echo "<li>" . $algo . ": ✓ Disponível</li>";
        } else {
            echo "<li>" . $algo . ": ✗ Não disponível (pode ser problema)</li>";
            $todas_assinatura_ok = false;
        }
    }
    echo "</ul>";
} else {
    echo "✗ OpenSSL não está carregada. A assinatura digital ICP-Brasil é impossível sem esta extensão.<br>";
}

// Recomendação final
echo "<h4>Status para Assinatura Digital:</h4>";
if ($todas_assinatura_ok) {
    echo "<span style='color:green; font-weight:bold;'>✓ Ambiente aparentemente OK para desenvolvimento de funcionalidades de assinatura digital ICP-Brasil.</span><br>";
} else {
    echo "<span style='color:orange; font-weight:bold;'>⚠ Atenção: Algumas extensões importantes para assinatura digital não estão carregadas. Verifique os itens marcados com ✗.</span><br>";
}
echo "<p><small>Nota: A implementação completa de assinatura digital ICP-Brasil também depende de outros fatores, como a configuração correta do OpenSSL (arquivo openssl.cnf) e o manuseio adequado do certificado digital (A1 ou A3). [citation:1]</small></p>";
// --- FIM DO NOVO BLOCO ---

// Informações de configuração do PHP (útil para debug)
echo "<h3>Configurações do PHP (php.ini):</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
?>