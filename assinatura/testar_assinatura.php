<?php
// teste_ok.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Teste Final do Sistema de Assinatura</h1>";

// Carregar autoload
require_once __DIR__ . '/vendor/autoload.php';
echo "✅ Autoload carregado<br>";

// 1. Verificar TCPDF
echo "<h2>1. Verificando TCPDF</h2>";
if (class_exists('TCPDF')) {
    echo "✅ TCPDF disponível<br>";
    
    // Tentar instanciar
    try {
        $tcpdf = new TCPDF();
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ Instância TCPDF criada<br>";
        
        // Verificar método
        if (method_exists($tcpdf, 'AddPage')) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ Método AddPage disponível<br>";
        }
    } catch (Exception $e) {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;❌ Erro: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ TCPDF NÃO disponível<br>";
}

// 2. Verificar FPDI
echo "<h2>2. Verificando FPDI</h2>";
if (class_exists('setasign\Fpdi\Fpdi')) {
    echo "✅ FPDI disponível<br>";
    
    try {
        $fpdi = new \setasign\Fpdi\Fpdi();
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ Instância FPDI criada<br>";
    } catch (Exception $e) {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;❌ Erro: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ FPDI NÃO disponível<br>";
}

// 3. Verificar FPDI-TCPDF (o mais importante)
echo "<h2>3. Verificando FPDI-TCPDF</h2>";
if (class_exists('setasign\Fpdi\Tcpdf\Fpdi')) {
    echo "✅ FPDI-TCPDF disponível<br>";
    
    // Tentar instanciar (classe que vamos usar)
    try {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ Instância FPDI-TCPDF criada com sucesso!<br>";
        
        // Testar métodos básicos
        $pdf->AddPage();
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ AddPage funcionou<br>";
        
        $pdf->SetFont('helvetica', '', 12);
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ SetFont funcionou<br>";
        
        $pdf->Cell(0, 10, 'Teste de PDF - OK', 0, 1);
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ Cell funcionou<br>";
        
        // Salvar PDF de teste
        $testFile = __DIR__ . '/uploads/teste_final_' . uniqid() . '.pdf';
        $pdf->Output($testFile, 'F');
        
        if (file_exists($testFile)) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ PDF gerado com sucesso: " . basename($testFile) . "<br>";
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Tamanho: " . round(filesize($testFile)/1024, 2) . " KB<br>";
        } else {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;❌ Falha ao gerar PDF<br>";
        }
        
    } catch (Exception $e) {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;❌ Erro ao instanciar: " . $e->getMessage() . "<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;Arquivo: " . $e->getFile() . " linha " . $e->getLine() . "<br>";
    }
} else {
    echo "❌ FPDI-TCPDF NÃO disponível<br>";
    
    // Listar todas as classes disponíveis para debug
    echo "<h3>Classes disponíveis:</h3>";
    $classes = get_declared_classes();
    $fpdiClasses = array_filter($classes, function($c) {
        return strpos($c, 'Fpdi') !== false || strpos($c, 'TCPDF') !== false;
    });
    echo "<pre>" . print_r(array_values($fpdiClasses), true) . "</pre>";
}

// 4. Verificar OpenSSL
echo "<h2>4. Verificando OpenSSL</h2>";
if (extension_loaded('openssl')) {
    echo "✅ OpenSSL carregado<br>";
    echo "&nbsp;&nbsp;&nbsp;&nbsp;Versão: " . OPENSSL_VERSION_TEXT . "<br>";
    
    // Testar funções de certificado
    if (function_exists('openssl_pkcs12_read')) {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ openssl_pkcs12_read disponível<br>";
    } else {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;❌ openssl_pkcs12_read NÃO disponível<br>";
    }
    
    if (function_exists('openssl_x509_parse')) {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ openssl_x509_parse disponível<br>";
    } else {
        echo "&nbsp;&nbsp;&nbsp;&nbsp;❌ openssl_x509_parse NÃO disponível<br>";
    }
} else {
    echo "❌ OpenSSL NÃO carregado<br>";
}

// 5. Verificar certificados
echo "<h2>5. Certificados Disponíveis</h2>";
$certDir = __DIR__ . '/certs/';
if (is_dir($certDir)) {
    $certs = scandir($certDir);
    $certs = array_diff($certs, ['.', '..']);
    
    if (count($certs) > 0) {
        echo "<ul>";
        foreach ($certs as $cert) {
            $ext = strtolower(pathinfo($cert, PATHINFO_EXTENSION));
            $icon = in_array($ext, ['pfx', 'p12']) ? '🔐' : '📄';
            $size = filesize($certDir . $cert);
            echo "<li>$icon $cert (" . round($size/1024, 2) . " KB) - " . strtoupper($ext) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "Nenhum certificado encontrado. Faça upload pela interface.<br>";
    }
}

// 6. Versões das bibliotecas
echo "<h2>6. Versões</h2>";
if (class_exists('TCPDF')) {
    echo "TCPDF: " . (defined('TCPDF::VERSION') ? TCPDF::VERSION : 'versão desconhecida, mas funcionando') . "<br>";
}
if (class_exists('setasign\Fpdi\Fpdi')) {
    echo "FPDI: " . (defined('setasign\Fpdi\Fpdi::VERSION') ? \setasign\Fpdi\Fpdi::VERSION : 'versão instalada') . "<br>";
}

// 7. Resumo
echo "<h2>✅ Resumo Final</h2>";
echo "<ul>";
echo "<li>" . (class_exists('TCPDF') ? '✅' : '❌') . " TCPDF</li>";
echo "<li>" . (class_exists('setasign\Fpdi\Fpdi') ? '✅' : '❌') . " FPDI</li>";
echo "<li>" . (class_exists('setasign\Fpdi\Tcpdf\Fpdi') ? '✅' : '❌') . " FPDI-TCPDF</li>";
echo "<li>" . (extension_loaded('openssl') ? '✅' : '❌') . " OpenSSL</li>";
echo "</ul>";

echo "<p>📝 <strong>Status:</strong> ";
if (class_exists('setasign\Fpdi\Tcpdf\Fpdi') && extension_loaded('openssl')) {
    echo "✅ <span style='color:green; font-weight:bold'>SISTEMA PRONTO para assinatura digital!</span>";
} else {
    echo "❌ Sistema com problemas. Verifique os itens marcados em vermelho acima.";
}
echo "</p>";
?>