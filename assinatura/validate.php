<?php
// validate.php
// Validador de Assinaturas Digitais ICP-Brasil

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função para log de erros
function logError($message, $context = []) {
    $logFile = __DIR__ . '/uploads/validate_error.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    error_log("[$timestamp] $message$contextStr\n", 3, $logFile);
}

function resp($ok, $data = []) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    
    $response = array_merge(['success' => $ok], $data);
    echo json_encode($response);
    exit;
}

// Configurações
$UPLOAD_DIR = __DIR__ . '/uploads/';

// Ação a ser executada
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/**
 * Extrai informações de assinatura de um PDF usando binário do sistema
 */
function extractSignatureInfo($pdfPath) {
    $info = [
        'has_signature' => false,
        'signatures' => [],
        'error' => null
    ];
    
    // Método 1: Usar pdfsig (do poppler-utils) se disponível
    $pdfsig = trim(shell_exec('which pdfsig 2>/dev/null'));
    if (!empty($pdfsig) && file_exists($pdfPath)) {
        $output = shell_exec("pdfsig \"$pdfPath\" 2>&1");
        
        if (strpos($output, 'No signatures') === false && !empty($output)) {
            $info['has_signature'] = true;
            $info['raw_output'] = $output;
            
            // Parsear saída do pdfsig
            $lines = explode("\n", $output);
            $currentSig = [];
            
            foreach ($lines as $line) {
                if (preg_match('/Signature #(\d+)/', $line, $matches)) {
                    if (!empty($currentSig)) {
                        $info['signatures'][] = $currentSig;
                    }
                    $currentSig = ['number' => $matches[1]];
                } elseif (preg_match('/Validity:\s+(.+)성/', $line, $matches)) {
                    $currentSig['validity'] = trim($matches[1]);
                } elseif (preg_match('/Signer Certificate DN:\s+(.+)/', $line, $matches)) {
                    $currentSig['subject_dn'] = trim($matches[1]);
                } elseif (preg_match('/Signing Time:\s+(.+)/', $line, $matches)) {
                    $currentSig['signing_time'] = trim($matches[1]);
                } elseif (preg_match('/Signing Hash Algorithm:\s+(.+)/', $line, $matches)) {
                    $currentSig['hash_algo'] = trim($matches[1]);
                } elseif (preg_match('/Issuer:\s+(.+)/', $line, $matches)) {
                    $currentSig['issuer'] = trim($matches[1]);
                } elseif (preg_match('/Serial Number:\s+(.+)/', $line, $matches)) {
                    $currentSig['serial'] = trim($matches[1]);
                }
            }
            if (!empty($currentSig)) {
                $info['signatures'][] = $currentSig;
            }
        }
    }
    
    // Método 2: Verificar com TCPDI se disponível
    if (empty($info['signatures']) && class_exists('\\setasign\\Fpdi\\Tcpdf\\Fpdi')) {
        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            $pageCount = $pdf->setSourceFile($pdfPath);
            
            // Verificar se há campos de assinatura no PDF
            // Esta é uma verificação básica
            $content = file_get_contents($pdfPath);
            if (preg_match('/\/Type\s*\/Sig/i', $content)) {
                $info['has_signature'] = true;
                $info['signatures'][] = [
                    'type' => 'PDF Signature Field',
                    'method' => 'detected_by_content'
                ];
            }
        } catch (Exception $e) {
            $info['error'] = 'Erro ao analisar PDF: ' . $e->getMessage();
        }
    }
    
    return $info;
}

/**
 * Valida a integridade do documento
 */
function validateDocumentIntegrity($originalPath, $signedPath) {
    // Comparar hashes para ver se o documento foi alterado após assinatura
    if (!file_exists($originalPath) || !file_exists($signedPath)) {
        return ['valid' => false, 'message' => 'Arquivo não encontrado'];
    }
    
    $originalHash = md5_file($originalPath);
    $signedHash = md5_file($signedPath);
    
    // O documento assinado geralmente tem hash diferente devido à assinatura
    // Mas podemos verificar se o conteúdo textual é similar
    $originalContent = file_get_contents($originalPath);
    $signedContent = file_get_contents($signedPath);
    
    // Remover a seção de assinatura para comparar o resto do documento
    // Esta é uma simplificação - uma validação real é mais complexa
    $originalClean = preg_replace('/\/Sig\s*<<.*?>>/s', '', $originalContent);
    $signedClean = preg_replace('/\/Sig\s*<<.*?>>/s', '', $signedContent);
    
    return [
        'valid' => ($originalClean == $signedClean),
        'original_hash' => $originalHash,
        'signed_hash' => $signedHash
    ];
}

/**
 * Verifica se o certificado é ICP-Brasil
 */
function checkIcpBrasil($certContent) {
    if (empty($certContent)) return false;
    
    $certInfo = @openssl_x509_parse($certContent);
    if (!$certInfo) return false;
    
    $issuerO = $certInfo['issuer']['O'] ?? '';
    $issuerCN = $certInfo['issuer']['CN'] ?? '';
    
    if (is_array($issuerO)) $issuerO = implode(', ', $issuerO);
    if (is_array($issuerCN)) $issuerCN = implode(', ', $issuerCN);
    
    return (
        stripos($issuerO, 'ICP-Brasil') !== false ||
        stripos($issuerCN, 'ICP-Brasil') !== false ||
        stripos($issuerO, 'Autoridade Certificadora') !== false ||
        stripos($issuerO, 'AC ') === 0
    );
}

/**
 * Validação completa
 */
function validateSignature($signedFile, $originalFile = null) {
    $result = [
        'valid' => false,
        'has_signature' => false,
        'signatures' => [],
        'certificate' => [],
        'integrity' => [],
        'icp_brasil' => false
    ];
    
    $signedPath = __DIR__ . '/uploads/' . basename($signedFile);
    
    if (!file_exists($signedPath)) {
        $result['error'] = 'Arquivo não encontrado: ' . $signedFile;
        return $result;
    }
    
    // Extrair informações da assinatura
    $sigInfo = extractSignatureInfo($signedPath);
    $result['has_signature'] = $sigInfo['has_signature'];
    $result['signatures'] = $sigInfo['signatures'];
    
    // Se não encontrou assinatura, retorna
    if (!$result['has_signature']) {
        return $result;
    }
    
    // Verificar integridade com original (se fornecido)
    if ($originalFile) {
        $originalPath = __DIR__ . '/uploads/' . basename($originalFile);
        if (file_exists($originalPath)) {
            $result['integrity'] = validateDocumentIntegrity($originalPath, $signedPath);
        }
    }
    
    // Tentar extrair e validar certificado
    try {
        // Tentar extrair certificado do PDF
        $pdfContent = file_get_contents($signedPath);
        
        // Procurar por certificados no PDF (simplificado)
        if (preg_match_all('/\/Cert\s*<<.*?>>/s', $pdfContent, $matches)) {
            foreach ($matches[0] as $match) {
                if (preg_match('/\/CERT\s*\(([^)]+)\)/i', $match, $certMatch)) {
                    $certBase64 = $certMatch[1];
                    $certDer = base64_decode($certBase64);
                    if ($certDer) {
                        $certPem = "-----BEGIN CERTIFICATE-----\n" . 
                                   chunk_split(base64_encode($certDer), 64, "\n") . 
                                   "-----END CERTIFICATE-----";
                        
                        $certInfo = @openssl_x509_parse($certPem);
                        if ($certInfo) {
                            $result['certificate'] = [
                                'subject' => $certInfo['subject']['CN'] ?? ($certInfo['subject']['O'] ?? 'Desconhecido'),
                                'issuer' => $certInfo['issuer']['CN'] ?? ($certInfo['issuer']['O'] ?? 'Desconhecido'),
                                'valid_from' => date('d/m/Y H:i:s', $certInfo['validFrom_time_t']),
                                'valid_to' => date('d/m/Y H:i:s', $certInfo['validTo_time_t']),
                                'is_expired' => (time() > $certInfo['validTo_time_t']),
                                'serial' => $certInfo['serialNumber'] ?? '',
                                'raw' => $certInfo
                            ];
                            
                            $result['icp_brasil'] = checkIcpBrasil($certPem);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $result['cert_error'] = $e->getMessage();
    }
    
    // Determinar validade geral
    $result['valid'] = $result['has_signature'] && 
                       empty($result['integrity']['error']) &&
                       (!isset($result['certificate']['is_expired']) || !$result['certificate']['is_expired']);
    
    return $result;
}

// Roteamento das ações
switch ($action) {
    case 'validate':
        $signedFile = $_POST['signedFile'] ?? ($_GET['signedFile'] ?? '');
        $originalFile = $_POST['originalFile'] ?? ($_GET['originalFile'] ?? null);
        
        if (empty($signedFile)) {
            resp(false, ['message' => 'Arquivo assinado não especificado']);
        }
        
        $result = validateSignature($signedFile, $originalFile);
        resp(true, ['validation' => $result]);
        break;
        
    case 'check_icp':
        // Verificar se um certificado específico é ICP-Brasil
        $certFile = $_POST['certFile'] ?? ($_GET['certFile'] ?? '');
        if (empty($certFile)) {
            resp(false, ['message' => 'Arquivo de certificado não especificado']);
        }
        
        $certPath = __DIR__ . '/certs/' . basename($certFile);
        if (!file_exists($certPath)) {
            resp(false, ['message' => 'Certificado não encontrado']);
        }
        
        $certContent = file_get_contents($certPath);
        $icp = checkIcpBrasil($certContent);
        
        $certInfo = @openssl_x509_parse($certContent);
        
        resp(true, [
            'icp_brasil' => $icp,
            'cert_info' => $certInfo ? [
                'subject' => $certInfo['subject']['CN'] ?? ($certInfo['subject']['O'] ?? ''),
                'issuer' => $certInfo['issuer']['CN'] ?? ($certInfo['issuer']['O'] ?? ''),
                'valid_from' => date('d/m/Y H:i:s', $certInfo['validFrom_time_t']),
                'valid_to' => date('d/m/Y H:i:s', $certInfo['validTo_time_t'])
            ] : null
        ]);
        break;
        
    case 'list_signed':
        // Listar documentos assinados disponíveis
        $files = [];
        if (is_dir($UPLOAD_DIR)) {
            $allFiles = scandir($UPLOAD_DIR);
            foreach ($allFiles as $file) {
                if (preg_match('/^signed_.+\.pdf$/', $file)) {
                    $files[] = $file;
                }
            }
        }
        resp(true, ['files' => $files]);
        break;
        
    default:
        resp(false, ['message' => 'Ação não reconhecida']);
}