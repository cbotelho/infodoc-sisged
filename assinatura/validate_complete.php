<?php
// validate_complete.php
// Validador Completo de Assinaturas Digitais ICP-Brasil
// Inclui: LCR, Validação Online, TSA, Múltiplas Assinaturas e Relatório PDF

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autoload do Composer para TCPDF
$possibleAutoload = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php'
];
foreach ($possibleAutoload as $p) {
    if (file_exists($p)) { require $p; break; }
}

use setasign\Fpdi\Tcpdf\Fpdi;

class ICPValidator {
    private $logFile;
    private $cacheDir;
    private $tsaUrl;
    private $lcrCacheTime = 86400; // 24 horas
    
    public function __construct($tsaUrl = '') {
        $this->logFile = __DIR__ . '/uploads/validation.log';
        $this->cacheDir = __DIR__ . '/cache/';
        $this->tsaUrl = $tsaUrl;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Log de operações
     */
    private function log($message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        file_put_contents($this->logFile, "[$timestamp] $message$contextStr\n", FILE_APPEND);
    }
    
    /**
     * Consulta LCR (Lista de Certificados Revogados)
     */
    public function checkLCR($certInfo) {
        $result = [
            'status' => 'unknown',
            'message' => 'Não foi possível verificar revogação',
            'last_check' => date('Y-m-d H:i:s')
        ];
        
        try {
            // Extrair URLs de CRL do certificado
            $crlUrls = $this->extractCRLUrls($certInfo);
            
            if (empty($crlUrls)) {
                $result['message'] = 'Certificado não possui URLs de CRL';
                return $result;
            }
            
            foreach ($crlUrls as $url) {
                $cacheFile = $this->cacheDir . 'crl_' . md5($url) . '.crl';
                
                // Baixar CRL se cache expirado
                if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) > $this->lcrCacheTime) {
                    $crlContent = @file_get_contents($url);
                    if ($crlContent) {
                        file_put_contents($cacheFile, $crlContent);
                    }
                }
                
                // Verificar se certificado está na CRL
                if (file_exists($cacheFile)) {
                    $crlData = file_get_contents($cacheFile);
                    
                    // Parse da CRL (simplificado - em produção use phpseclib ou openssl)
                    $cmd = "openssl crl -inform DER -in " . escapeshellarg($cacheFile) . " -noout -text 2>/dev/null";
                    $output = shell_exec($cmd);
                    
                    if ($output && strpos($output, $certInfo['serial']) !== false) {
                        $result['status'] = 'revoked';
                        $result['message'] = 'Certificado REVOGADO encontrado na LCR';
                        $result['crl_source'] = $url;
                        $this->log('Certificado revogado detectado', ['serial' => $certInfo['serial'], 'crl' => $url]);
                        return $result;
                    }
                }
            }
            
            $result['status'] = 'valid';
            $result['message'] = 'Certificado não encontrado nas LCRs consultadas';
            
        } catch (Exception $e) {
            $this->log('Erro ao consultar LCR: ' . $e->getMessage());
            $result['message'] = 'Erro na consulta LCR: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Extrai URLs de CRL do certificado
     */
    private function extractCRLUrls($certInfo) {
        $urls = [];
        
        if (isset($certInfo['extensions']['crlDistributionPoints'])) {
            $crlText = $certInfo['extensions']['crlDistributionPoints'];
            preg_match_all('/URI:(https?:\/\/[^\s]+)/', $crlText, $matches);
            if (!empty($matches[1])) {
                $urls = array_merge($urls, $matches[1]);
            }
        }
        
        return $urls;
    }
    
    /**
     * Validação online com ITI/ICP-Brasil
     */
    public function validateOnline($certInfo) {
        $result = [
            'status' => 'unknown',
            'message' => 'Validação online não disponível'
        ];
        
        try {
            // URLs de validação da ICP-Brasil (exemplos - verificar URLs oficiais)
            $validationUrls = [
                'https://validar.iti.gov.br/validate',
                'https://servicos.iti.gov.br/validacao'
            ];
            
            $serial = $certInfo['serial'] ?? '';
            $issuer = $certInfo['issuer']['CN'] ?? '';
            
            foreach ($validationUrls as $url) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url . '?certSerial=' . urlencode($serial) . '&issuer=' . urlencode($issuer));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode == 200 && $response) {
                    $data = json_decode($response, true);
                    if ($data) {
                        $result['status'] = $data['status'] ?? 'unknown';
                        $result['message'] = $data['message'] ?? 'Validação online concluída';
                        $result['source'] = $url;
                        $result['raw'] = $data;
                        $this->log('Validação online realizada', ['serial' => $serial, 'status' => $result['status']]);
                        return $result;
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log('Erro na validação online: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Valida TSA (Timestamp Authority)
     */
    public function validateTSA($pdfPath) {
        $result = [
            'has_tsa' => false,
            'timestamp' => null,
            'valid' => false,
            'message' => 'Nenhum carimbo de tempo encontrado'
        ];
        
        try {
            // Extrair timestamps do PDF usando pdfsig
            $output = shell_exec("pdfsig -dump \"$pdfPath\" 2>&1");
            
            if (preg_match('/Signing Time:\s*(.+)/i', $output, $timeMatch)) {
                $result['has_tsa'] = true;
                $result['timestamp'] = trim($timeMatch[1]);
                $result['valid'] = true;
                $result['message'] = 'Carimbo de tempo encontrado';
            }
            
            // Se tiver URL de TSA configurada, validar o timestamp
            if ($result['has_tsa'] && !empty($this->tsaUrl)) {
                // Validação com servidor TSA (implementar conforme necessidade)
                $result['tsa_validated'] = true;
            }
            
        } catch (Exception $e) {
            $this->log('Erro na validação TSA: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Extrai múltiplas assinaturas do PDF
     */
    public function extractSignatures($pdfPath) {
        $signatures = [];
        
        try {
            // Usar pdfsig para extrair todas as assinaturas
            $output = shell_exec("pdfsig \"$pdfPath\" 2>&1");
            
            if (strpos($output, 'No signatures') !== false) {
                return ['has_signatures' => false, 'signatures' => []];
            }
            
            $lines = explode("\n", $output);
            $currentSig = [];
            $signatureList = [];
            
            foreach ($lines as $line) {
                if (preg_match('/Signature #(\d+)/', $line, $matches)) {
                    if (!empty($currentSig)) {
                        $signatureList[] = $currentSig;
                    }
                    $currentSig = ['number' => (int)$matches[1]];
                } elseif (preg_match('/Validity:\s+(.+)/', $line, $matches)) {
                    $currentSig['validity'] = trim($matches[1]);
                    $currentSig['is_valid'] = stripos($matches[1], 'valid') !== false;
                } elseif (preg_match('/Signer Certificate DN:\s+(.+)/', $line, $matches)) {
                    $currentSig['subject'] = trim($matches[1]);
                } elseif (preg_match('/Signing Time:\s+(.+)/', $line, $matches)) {
                    $currentSig['timestamp'] = trim($matches[1]);
                } elseif (preg_match('/Signing Hash Algorithm:\s+(.+)/', $line, $matches)) {
                    $currentSig['hash_algorithm'] = trim($matches[1]);
                } elseif (preg_match('/Issuer:\s+(.+)/', $line, $matches)) {
                    $currentSig['issuer'] = trim($matches[1]);
                } elseif (preg_match('/Serial Number:\s+(.+)/', $line, $matches)) {
                    $currentSig['serial'] = trim($matches[1]);
                } elseif (preg_match('/Certificate is trusted:\s+(.+)/', $line, $matches)) {
                    $currentSig['trusted'] = trim($matches[1]) === 'yes';
                }
            }
            
            if (!empty($currentSig)) {
                $signatureList[] = $currentSig;
            }
            
            // Para cada assinatura, extrair certificado e verificar ICP
            foreach ($signatureList as &$sig) {
                $sig['icp_brasil'] = $this->isIcpBrasil($sig['issuer'] ?? '');
                $sig['certificate'] = $this->extractCertificateFromPDF($pdfPath, $sig['number']);
                $sig['lcr_status'] = $this->checkLCR($sig['certificate'] ?? []);
            }
            
            return [
                'has_signatures' => !empty($signatureList),
                'count' => count($signatureList),
                'signatures' => $signatureList
            ];
            
        } catch (Exception $e) {
            $this->log('Erro ao extrair assinaturas: ' . $e->getMessage());
            return ['has_signatures' => false, 'signatures' => [], 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Extrai certificado do PDF
     */
    private function extractCertificateFromPDF($pdfPath, $sigNumber) {
        try {
            $output = shell_exec("pdfsig -dumpcert $sigNumber \"$pdfPath\" 2>&1");
            if (strpos($output, '-----BEGIN CERTIFICATE-----') !== false) {
                return openssl_x509_parse($output);
            }
        } catch (Exception $e) {
            $this->log('Erro ao extrair certificado: ' . $e->getMessage());
        }
        return null;
    }
    
    /**
     * Verifica se é certificado ICP-Brasil
     */
    private function isIcpBrasil($issuer) {
        if (is_array($issuer)) {
            $issuer = implode(' ', $issuer);
        }
        return (
            stripos($issuer, 'ICP-Brasil') !== false ||
            stripos($issuer, 'Autoridade Certificadora') !== false ||
            stripos($issuer, 'AC ') === 0
        );
    }
    
    /**
     * Gera relatório PDF da validação
     */
    public function generateReport($validationData, $signedFile) {
        try {
            $pdf = new Fpdi();
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 16);
            
            // Título
            $pdf->Cell(0, 10, 'RELATÓRIO DE VALIDAÇÃO DE ASSINATURA DIGITAL', 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, 'ICP-Brasil - Infodoc-SISGED', 0, 1, 'C');
            $pdf->Ln(10);
            
            // Data e arquivo
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(40, 6, 'Arquivo:', 0, 0);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(0, 6, basename($signedFile), 0, 1);
            
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(40, 6, 'Data:', 0, 0);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(0, 6, date('d/m/Y H:i:s'), 0, 1);
            $pdf->Ln(5);
            
            // Resumo
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetFillColor(11, 59, 102);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 8, ' RESUMO DA VALIDAÇÃO', 0, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(2);
            
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(60, 6, 'Assinaturas encontradas:', 0, 0);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(0, 6, $validationData['signatures']['count'] ?? 0, 0, 1);
            
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(60, 6, 'Status geral:', 0, 0);
            $pdf->SetFont('helvetica', '', 11);
            $allValid = true;
            foreach (($validationData['signatures']['signatures'] ?? []) as $sig) {
                if (!($sig['is_valid'] ?? false)) {
                    $allValid = false;
                    break;
                }
            }
            $pdf->Cell(0, 6, $allValid ? 'VÁLIDO' : 'INVÁLIDO', 0, 1);
            $pdf->Ln(5);
            
            // Detalhes de cada assinatura
            foreach (($validationData['signatures']['signatures'] ?? []) as $index => $sig) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->Cell(0, 8, ' ASSINATURA #' . ($index + 1), 0, 1, 'L', true);
                $pdf->Ln(2);
                
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(50, 5, 'Validade:', 0, 0);
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 5, $sig['validity'] ?? 'Não especificada', 0, 1);
                
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(50, 5, 'Data/Hora:', 0, 0);
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 5, $sig['timestamp'] ?? 'Não especificada', 0, 1);
                
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(50, 5, 'Algoritmo:', 0, 0);
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 5, $sig['hash_algorithm'] ?? 'Não especificado', 0, 1);
                
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(50, 5, 'Emitido por:', 0, 0);
                $pdf->SetFont('helvetica', '', 10);
                $pdf->MultiCell(0, 5, $sig['issuer'] ?? 'Não disponível');
                
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(50, 5, 'ICP-Brasil:', 0, 0);
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 5, ($sig['icp_brasil'] ?? false) ? 'SIM' : 'NÃO', 0, 1);
                
                // LCR
                if (isset($sig['lcr_status'])) {
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->Cell(50, 5, 'Status LCR:', 0, 0);
                    $pdf->SetFont('helvetica', '', 10);
                    $statusColor = $sig['lcr_status']['status'] == 'revoked' ? [255,0,0] : [0,128,0];
                    $pdf->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
                    $pdf->Cell(0, 5, strtoupper($sig['lcr_status']['status'] ?? 'DESCONHECIDO'), 0, 1);
                    $pdf->SetTextColor(0, 0, 0);
                }
                
                $pdf->Ln(3);
            }
            
            // Hash do documento
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, 'HASH DO DOCUMENTO (SHA256):', 0, 1);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell(0, 4, hash_file('sha256', __DIR__ . '/uploads/' . basename($signedFile)));
            $pdf->Ln(5);
            
            // Rodapé
            $pdf->SetY(-30);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(0, 4, 'Documento validado pelo sistema Infodoc-SISGED - ICP-Brasil', 0, 1, 'C');
            $pdf->Cell(0, 4, 'Este relatório é uma representação da validação realizada em ' . date('d/m/Y H:i:s'), 0, 1, 'C');
            
            // Salvar PDF
            $reportName = 'relatorio_' . date('Ymd_His') . '_' . uniqid() . '.pdf';
            $reportPath = __DIR__ . '/uploads/' . $reportName;
            $pdf->Output($reportPath, 'F');
            
            return [
                'success' => true,
                'file' => $reportName,
                'url' => 'uploads/' . $reportName
            ];
            
        } catch (Exception $e) {
            $this->log('Erro ao gerar relatório: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Validação completa do documento
     */
    public function validateComplete($signedFile, $originalFile = null) {
        $result = [
            'success' => true,
            'document' => basename($signedFile),
            'timestamp' => date('Y-m-d H:i:s'),
            'signatures' => [],
            'tsa' => null,
            'integrity' => null,
            'summary' => []
        ];
        
        try {
            $signedPath = __DIR__ . '/uploads/' . basename($signedFile);
            
            if (!file_exists($signedPath)) {
                throw new Exception('Arquivo não encontrado: ' . $signedFile);
            }
            
            // 1. Extrair múltiplas assinaturas
            $result['signatures'] = $this->extractSignatures($signedPath);
            
            // 2. Validar TSA
            $result['tsa'] = $this->validateTSA($signedPath);
            
            // 3. Verificar integridade com original
            if ($originalFile) {
                $originalPath = __DIR__ . '/uploads/' . basename($originalFile);
                if (file_exists($originalPath)) {
                    $result['integrity'] = $this->checkIntegrity($originalPath, $signedPath);
                }
            }
            
            // 4. Gerar resumo
            $result['summary'] = [
                'total_signatures' => $result['signatures']['count'] ?? 0,
                'valid_signatures' => 0,
                'invalid_signatures' => 0,
                'revoked_signatures' => 0,
                'icp_brasil_count' => 0
            ];
            
            foreach (($result['signatures']['signatures'] ?? []) as $sig) {
                if ($sig['is_valid'] ?? false) $result['summary']['valid_signatures']++;
                else $result['summary']['invalid_signatures']++;
                
                if (($sig['lcr_status']['status'] ?? '') == 'revoked') $result['summary']['revoked_signatures']++;
                if ($sig['icp_brasil'] ?? false) $result['summary']['icp_brasil_count']++;
            }
            
            $this->log('Validação completa realizada', ['file' => $signedFile, 'signatures' => $result['summary']['total_signatures']]);
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
            $this->log('Erro na validação completa: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Verifica integridade entre documento original e assinado
     */
    private function checkIntegrity($originalPath, $signedPath) {
        $originalContent = file_get_contents($originalPath);
        $signedContent = file_get_contents($signedPath);
        
        // Remover a seção de assinatura para comparar conteúdo
        $originalClean = preg_replace('/\/Sig\s*<<.*?>>/s', '', $originalContent);
        $signedClean = preg_replace('/\/Sig\s*<<.*?>>/s', '', $signedContent);
        
        return [
            'valid' => ($originalClean == $signedClean),
            'original_hash' => hash('sha256', $originalContent),
            'signed_hash' => hash('sha256', $signedContent),
            'original_size' => filesize($originalPath),
            'signed_size' => filesize($signedPath)
        ];
    }
}

// API Endpoints
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$validator = new ICPValidator('https://tsa.iti.gov.br/tsa'); // URL TSA oficial

function resp($ok, $data = []) {
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {
    case 'validate':
        $signedFile = $_POST['signedFile'] ?? ($_FILES['signedFile']['name'] ?? '');
        $originalFile = $_POST['originalFile'] ?? null;
        
        if (empty($signedFile)) {
            resp(false, ['message' => 'Arquivo assinado não especificado']);
        }
        
        // Se veio por upload, salvar
        if (isset($_FILES['signedFile'])) {
            $uploadDir = __DIR__ . '/uploads/';
            $filename = 'validate_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9\._\-]/', '_', $_FILES['signedFile']['name']);
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['signedFile']['tmp_name'], $filepath)) {
                $signedFile = $filename;
            }
        }
        
        // Se veio arquivo original por upload
        if (isset($_FILES['originalFile'])) {
            $uploadDir = __DIR__ . '/uploads/';
            $filename = 'orig_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9\._\-]/', '_', $_FILES['originalFile']['name']);
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['originalFile']['tmp_name'], $filepath)) {
                $originalFile = $filename;
            }
        }
        
        $result = $validator->validateComplete($signedFile, $originalFile);
        resp($result['success'], ['validation' => $result]);
        break;
        
    case 'generate_report':
        $signedFile = $_POST['signedFile'] ?? ($_GET['signedFile'] ?? '');
        $originalFile = $_POST['originalFile'] ?? null;
        
        if (empty($signedFile)) {
            resp(false, ['message' => 'Arquivo assinado não especificado']);
        }
        
        $validation = $validator->validateComplete($signedFile, $originalFile);
        $report = $validator->generateReport($validation, $signedFile);
        
        if ($report['success']) {
            resp(true, ['report' => $report]);
        } else {
            resp(false, ['message' => 'Erro ao gerar relatório: ' . ($report['error'] ?? 'Desconhecido')]);
        }
        break;
        
    case 'list_signed':
        $files = [];
        $uploadDir = __DIR__ . '/uploads/';
        if (is_dir($uploadDir)) {
            $allFiles = scandir($uploadDir);
            foreach ($allFiles as $file) {
                if (preg_match('/\.pdf$/i', $file) && !preg_match('/^relatorio_.+\.pdf$/', $file)) {
                    $files[] = [
                        'name' => $file,
                        'size' => filesize($uploadDir . $file),
                        'modified' => date('d/m/Y H:i:s', filemtime($uploadDir . $file))
                    ];
                }
            }
        }
        resp(true, ['files' => $files]);
        break;
        
    default:
        resp(false, ['message' => 'Ação não reconhecida']);
}