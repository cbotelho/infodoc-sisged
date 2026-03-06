<?php
// Habilitar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Forçar o uso do TCPDI para melhor compatibilidade com PDFs
if (!defined('FPDI_VERSION')) {
    define('FPDI_VERSION', '2.3.7'); // Versão compatível
}

function decodeFileData($data) {
    if ($data === null || $data === '') {
        return '';
    }

    if (strpos($data, 'data:') === 0) {
        return base64_decode(preg_replace('#^data:[^;]+;base64,#', '', $data));
    }

    $decoded = base64_decode($data, true);
    return $decoded !== false ? $decoded : $data;
}

function buildFileUrl($relativePath) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return sprintf('%s://%s/assinatura/%s', $scheme, $host, ltrim($relativePath, '/'));
}

function getUploadedTmp(array $names) {
    foreach ($names as $name) {
        if (!empty($_FILES[$name]['tmp_name']) && is_uploaded_file($_FILES[$name]['tmp_name'])) {
            return $_FILES[$name]['tmp_name'];
        }
    }
    return null;
}

// localizar autoload do Composer em múltiplos locais (assinatura/, projeto raiz, infodoc-assinador/)
$possibleAutoload = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../infodoc-assinador/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];
$autoloadFound = null;
foreach ($possibleAutoload as $p) {
    if (file_exists($p)) { $autoloadFound = $p; break; }
}
if ($autoloadFound) {
    require $autoloadFound;
} else {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Composer autoload not found. Execute 'composer install' in the project or copy vendor/autoload.php to assinatura/vendor/.\n");
        exit(1);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Composer autoload not found. Execute composer install.']);
        exit;
    }
}

// Configurar o autoloader para usar TCPDI e PDF-Parser
use setasign\Fpdi\Tcpdf\Fpdi;
use setasign\FpdiPdfParser\PdfParser\PdfParser;
use setasign\FpdiPdfParser\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi as FpdiTcpdf;

// Extensão personalizada do FPDI para suporte a assinatura ICP-Brasil
class FpdiIcpBrasil extends Fpdi {
    public function setSignature(
        $signing_cert = '',
        $private_key = '',
        $private_key_password = '',
        $extracerts = '',
        $cert_type = 2,
        $info = array(),
        $approval = ''
    ) {
        // Se nenhuma chave privada for informada, assume o mesmo arquivo do certificado
        if (empty($private_key)) {
            $private_key = $signing_cert;
        }

        $icpDefaults = array(
            'Name' => 'Assinatura Digital',
            'Location' => 'Brasil',
            'Reason' => 'Assinatura Digital ICP-Brasil',
            'ContactInfo' => '',
            'CertificationLevel' => 3,
            'URL' => 'https://www.iti.gov.br/icp-brasil'
        );

        $info = array_merge($icpDefaults, $info ?? []);

        parent::setSignature(
            $signing_cert,
            $private_key,
            $private_key_password,
            $extracerts,
            $cert_type,
            $info,
            $approval
        );

        if (method_exists($this, 'setSignatureAlgorithm')) {
            $this->setSignatureAlgorithm('sha256WithRSAEncryption');
        }
        if (method_exists($this, 'setCertificationLevel')) {
            $this->setCertificationLevel(3);
        }
    }
    
    public function _putsignature() {
        // Sobrescreve o método para incluir informações específicas do ICP-Brasil
        parent::_putsignature();
        
        // Adiciona informações adicionais para ICP-Brasil
        $this->_out('/Prop_Build << /App << /Name "TCPDF" >> >>');
        $this->_out('/Prop_AuthTime ' . time() . ' 00:00:00 -03:00');
        $this->_out('/Prop_AuthType /PKCS7');
    }
}

// Aumentar limite de memória se necessário
ini_set('memory_limit', '256M');

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// sign.php
// Backend para upload e assinatura real de PDFs usando TCPDF + FPDI
// Suporta .pfx/.p12 (PKCS#12) e .pem (+ chave separada enviada via keyFile)
// Aceita coords de posição em mm: page, x_mm, y_mm, width_mm, height_mm

// Função para log de erros
function logError($message, $context = []) {
    $logFile = __DIR__ . '/uploads/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    error_log("[$timestamp] $message$contextStr\n", 3, $logFile);
}

// localizar autoload do Composer em múltiplos locais (assinatura/, projeto raiz, infodoc-assinador/)
$possibleAutoload = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../infodoc-assinador/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];
$autoloadFound = null;
foreach ($possibleAutoload as $p) {
    if (file_exists($p)) { $autoloadFound = $p; break; }
}
if ($autoloadFound) {
    require $autoloadFound;
} else {
    // se executando via CLI, escrever no STDERR e sair; caso contrário retornar JSON de erro
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Composer autoload not found. Execute 'composer install' in the project or copy vendor/autoload.php to assinatura/vendor/.\n");
        exit(1);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Composer autoload not found. Execute composer install.']);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

function resp($ok, $data = []) {
    // Garantir que headers corretos sejam enviados
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        // Permitir CORS se necessário
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
    
    // Garantir que $data seja array
    if (!is_array($data)) {
        $data = ['message' => strval($data)];
    }
    
    // Preparar resposta
    $response = array_merge(['success' => $ok], $data);
    
    // Garantir que a resposta seja JSON válido
    $json = json_encode($response);
    if ($json === false) {
        // Se falhar, logar erro e retornar erro genérico
        logError('JSON encode failed: ' . json_last_error_msg(), ['data' => $data]);
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno do servidor',
            'error_code' => 'JSON_ENCODE_FAILED'
        ]);
    } else {
        echo $json;
    }
    exit;
}

// --- Configurações TSA (opcional) ---
// Se quiser usar TSA configure a URL e credenciais aqui:
// Exemplo: $TSA_URL = 'https://tsa.exemplo.tst/rfc3161'; // URL do servidor TSA que aceita RFC3161
$TSA_URL = ''; // deixar vazio para não usar TSA

// --- Configurações de Upload ---
$UPLOAD_DIR = __DIR__ . '/uploads/';
$ALLOWED_TYPES = ['application/pdf'];
$MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

$TSA_USERNAME = '';
$TSA_PASSWORD = '';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Se nenhuma ação for informada, assumir assinatura ao receber POST (ex.: formulário simples)
if ($action === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = 'sign';
}

// --- Tratamento de Upload ---
if ($action === 'upload') {
    header('Content-Type: application/json');
    
    try {
        // Verificar se o arquivo foi enviado
        if (!isset($_FILES['pdfFile']) || $_FILES['pdfFile']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Nenhum arquivo enviado ou erro no upload.');
        }
        
        $file = $_FILES['pdfFile'];
        
        // Validar tipo do arquivo
        if (!in_array($file['type'], $ALLOWED_TYPES)) {
            throw new Exception('Tipo de arquivo não suportado. Apenas PDF é permitido.');
        }
        
        // Validar tamanho do arquivo
        if ($file['size'] > $MAX_FILE_SIZE) {
            throw new Exception('Arquivo muito grande. Tamanho máximo permitido: 10MB');
        }
        
        // Criar diretório de uploads se não existir
        if (!is_dir($UPLOAD_DIR)) {
            if (!mkdir($UPLOAD_DIR, 0755, true)) {
                throw new Exception('Falha ao criar diretório de uploads.');
            }
        }
        
        // Gerar nome único para o arquivo
        $filename = uniqid('pdf_') . '_' . preg_replace('/[^A-Za-z0-9\._\-]/', '_', $file['name']);
        $filepath = $UPLOAD_DIR . $filename;
        
        // Mover arquivo para o diretório de uploads
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Falha ao salvar o arquivo.');
        }
        
        // Retornar sucesso com URL completa
        $fileUrl = buildFileUrl('uploads/' . $filename);
        
        resp(true, [
            'filename' => $filename,
            'filepath' => $filepath,
            'fileUrl' => $fileUrl,
            'message' => 'Arquivo enviado com sucesso.'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        resp(false, ['message' => $e->getMessage()]);
    }
}

// --- Processar Assinatura ---
else if ($action === 'sign') {
    try {
        // Log de debug para verificar o método da requisição e cabeçalhos
        file_put_contents('debug.log', "\n=== NOVA REQUISIÇÃO ===\n", FILE_APPEND);
        file_put_contents('debug.log', "Método: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
        file_put_contents('debug.log', "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'não especificado') . "\n", FILE_APPEND);
        file_put_contents('debug.log', "Dados brutos: " . file_get_contents('php://input') . "\n\n", FILE_APPEND);
        // Verificar se os dados foram enviados como JSON
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Se não conseguiu ler do input JSON, tenta do POST normal
        if (json_last_error() !== JSON_ERROR_NONE) {
            $input = $_POST;
        }
        
        // Mapear nomes de parâmetros alternativos para os nomes esperados
        $paramMap = [
            'file' => 'pdfFile',
            'cert' => 'certFile',
            'password' => 'certPass',
            'page' => 'page',
            'x_mm' => 'x',
            'y_mm' => 'y',
            'width_mm' => 'width',
            'height_mm' => 'height'
        ];
        
        // Aplicar mapeamento de parâmetros
        foreach ($paramMap as $oldName => $newName) {
            if (isset($input[$oldName]) && !isset($input[$newName])) {
                $input[$newName] = $input[$oldName];
                file_put_contents('debug.log', "Mapeado: $oldName => $newName\n", FILE_APPEND);
            }
        }
        
        // Log dos parâmetros recebidos
        $debugInfo = [
            'input_params' => array_keys($input),
            'files_received' => !empty($_FILES) ? array_keys($_FILES) : 'nenhum',
            'server_params' => [
                'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'não especificado',
                'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD']
            ]
        ];
        
        file_put_contents('debug.log', "Dados processados: " . print_r($debugInfo, true) . "\n", FILE_APPEND);
        
        // Processar arquivos enviados via multipart/form-data
        if (!empty($_FILES)) {
            foreach ($_FILES as $key => $file) {
                if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
                    // Se for o arquivo PDF
                    if ($key === 'file' || $key === 'pdfFile') {
                        $rawPdf = file_get_contents($file['tmp_name']);
                        if ($rawPdf !== false) {
                            $input['pdfFile'] = base64_encode($rawPdf);
                            file_put_contents('debug.log', "Arquivo PDF recebido. Tamanho: " . strlen($rawPdf) . " bytes\n", FILE_APPEND);
                        }
                    }
                    // Se for o certificado
                    elseif ($key === 'cert' || $key === 'certFile') {
                        $rawCert = file_get_contents($file['tmp_name']);
                        if ($rawCert !== false) {
                            $input['certFile'] = base64_encode($rawCert);
                            file_put_contents('debug.log', "Arquivo de certificado recebido. Tamanho: " . strlen($rawCert) . " bytes\n", FILE_APPEND);
                        }
                    }
                }
            }
        }
        
        // Se os arquivos não vierem como upload, tenta pegar do input
        if (empty($input['pdfFile']) && !empty($input['file'])) {
            $input['pdfFile'] = $input['file'];
            file_put_contents('debug.log', "Usando PDF do input. Tamanho: " . strlen($input['pdfFile']) . " bytes\n", FILE_APPEND);
        }
        
        if (empty($input['certFile']) && !empty($input['cert'])) {
            $input['certFile'] = $input['cert'];
            file_put_contents('debug.log', "Usando certificado do input. Tamanho: " . strlen($input['certFile']) . " bytes\n", FILE_APPEND);
        }
        
        // Log dos arquivos processados
        file_put_contents('debug.log', "Parâmetros finais: " . print_r([
            'tem_pdf' => !empty($input['pdfFile']) ? 'sim' : 'não',
            'tem_cert' => !empty($input['certFile']) ? 'sim' : 'não',
            'tem_senha' => !empty($input['certPass']) ? 'sim' : 'não'
        ], true) . "\n", FILE_APPEND);
        
        // Validar parâmetros obrigatórios
        $missing = [];
        
        // Verificar PDF
        $pdfTmp = $_FILES['file']['tmp_name'] ?? $_FILES['pdfFile']['tmp_name'] ?? null;
        if (empty($input['pdfFile']) && empty($pdfTmp)) {
            $missing[] = 'pdfFile';
        }
        
        // Verificar Certificado
        $certTmp = $_FILES['cert']['tmp_name'] ?? $_FILES['certFile']['tmp_name'] ?? null;
        if (empty($input['certFile']) && empty($certTmp)) {
            $missing[] = 'certFile';
        }
        
        // Verificar Senha
        if (empty($input['certPass'])) {
            $missing[] = 'certPass';
        }
        
        if (!empty($missing)) {
            throw new Exception('Parâmetros obrigatórios ausentes: ' . implode(', ', $missing) . 
                             ' | Dados recebidos: ' . json_encode(array_keys($input)));
        }
        
        // Processar certificado
        $certData = $input['certFile'] ?? '';
        $certPass = $input['certPass'] ?? '';

        $certContent = '';
        $certPathUsed = '';
        $certDirs = [
            __DIR__ . '/certs/' . $certData,
            $certData
        ];
        
        // Se foi enviado via upload (form multipart)
        if (isset($_FILES['certFile']) && is_uploaded_file($_FILES['certFile']['tmp_name'])) {
            $certContent = file_get_contents($_FILES['certFile']['tmp_name']);
            $certPathUsed = $_FILES['certFile']['tmp_name'];
        } elseif (isset($_FILES['cert']) && is_uploaded_file($_FILES['cert']['tmp_name'])) {
            $certContent = file_get_contents($_FILES['cert']['tmp_name']);
            $certPathUsed = $_FILES['cert']['tmp_name'];
        } else {
            foreach ($certDirs as $possible) {
                if (!empty($possible) && file_exists($possible) && is_file($possible)) {
                    $certContent = file_get_contents($possible);
                    $certPathUsed = $possible;
                    break;
                }
            }
            if ($certContent === '' && !empty($certData)) {
                $certContent = decodeFileData($certData);
            }
        }

        file_put_contents('debug.log', "Cert path usado: " . ($certPathUsed ?: 'inline/base64') . "\n", FILE_APPEND);
        if ($certContent === '' || $certContent === false) {
            throw new Exception('Certificado não encontrado ou vazio.');
        }
        
        // Processar PDF
        $pdfContent = '';
        $pdfPathUsed = '';
        $pdfDirs = [];
        if (!empty($input['pdfFile'])) {
            $pdfDirs[] = __DIR__ . '/uploads/' . $input['pdfFile'];
            $pdfDirs[] = $input['pdfFile'];
        }

        if (isset($_FILES['pdfFile']) && is_uploaded_file($_FILES['pdfFile']['tmp_name'])) {
            $pdfContent = file_get_contents($_FILES['pdfFile']['tmp_name']);
            $pdfPathUsed = $_FILES['pdfFile']['tmp_name'];
        } elseif (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $pdfContent = file_get_contents($_FILES['file']['tmp_name']);
            $pdfPathUsed = $_FILES['file']['tmp_name'];
        } else {
            foreach ($pdfDirs as $candidate) {
                if (!empty($candidate) && file_exists($candidate) && is_file($candidate)) {
                    $pdfContent = file_get_contents($candidate);
                    $pdfPathUsed = $candidate;
                    break;
                }
            }

            if ($pdfContent === '' && !empty($input['pdfFile'])) {
                if (filter_var($input['pdfFile'], FILTER_VALIDATE_URL)) {
                    $pdfContent = file_get_contents($input['pdfFile']);
                    if ($pdfContent === false) {
                        throw new Exception('Não foi possível carregar o PDF da URL fornecida');
                    }
                    $pdfPathUsed = $input['pdfFile'];
                } else {
                    $pdfContent = decodeFileData($input['pdfFile']);
                }
            }
        }

        file_put_contents('debug.log', "PDF path usado: " . ($pdfPathUsed ?: 'inline/base64') . "\n", FILE_APPEND);
        if ($pdfContent === '' || $pdfContent === false) {
            throw new Exception('Nenhum arquivo PDF válido foi fornecido');
        }
        
        // Configurações da assinatura
        $info = [
            'Name' => $input['name'] ?? 'Assinatura Digital',
            'Location' => $input['location'] ?? 'Brasil',
            'Reason' => $input['reason'] ?? 'Aprovação do documento',
            'ContactInfo' => $input['contact'] ?? ''
        ];
        
        // Verificar se o certificado é válido
        $certInfo = @openssl_x509_parse($certContent);
        if (!$certInfo) {
            // Tentar ler como PKCS#12 (PFX/P12)
            $certs = [];
            if (openssl_pkcs12_read($certContent, $certs, $certPass)) {
                $certContent = $certs['cert'];
                $certInfo = openssl_x509_parse($certContent);
                
                // Adicionar a chave privada ao conteúdo do certificado
                if (!empty($certs['pkey'])) {
                    $certContent = $certs['cert'] . "\n" . $certs['pkey'];
                }
            } else {
                throw new Exception('Certificado digital inválido, corrompido ou senha incorreta.');
            }
        }
        
        // Verificar se o certificado é da ICP-Brasil
        $isIcpBrasil = false;
        $issuerO = $certInfo['issuer']['O'] ?? '';
        $issuerCN = $certInfo['issuer']['CN'] ?? '';
        
        if (is_array($issuerO)) {
            $issuerO = implode(', ', $issuerO);
        }
        
        if (stripos($issuerO, 'ICP-Brasil') !== false || 
            stripos($issuerCN, 'ICP-Brasil') !== false ||
            stripos($issuerO, 'Autoridade Certificadora') !== false ||
            stripos($issuerO, 'AC ') === 0) {
            $isIcpBrasil = true;
        }
        
        // Verificar se o certificado está dentro do período de validade
        $now = time();
        if (isset($certInfo['validFrom_time_t']) && $now < $certInfo['validFrom_time_t']) {
            throw new Exception('O certificado ainda não está válido. Data de início: ' . 
                             date('d/m/Y H:i:s', $certInfo['validFrom_time_t']));
        }
        
        if (isset($certInfo['validTo_time_t']) && $now > $certInfo['validTo_time_t']) {
            throw new Exception('O certificado expirou em ' . 
                             date('d/m/Y H:i:s', $certInfo['validTo_time_t']));
        }
        
        // Verificar se o certificado tem chave privada
        $hasPrivateKey = false;
        $privateKey = openssl_pkey_get_private($certContent, $certPass);
        if ($privateKey !== false) {
            $hasPrivateKey = true;
        }
        
        if (!$hasPrivateKey) {
            throw new Exception('O certificado não contém uma chave privada ou a senha está incorreta.');
        }
        
        // Criar PDF assinado com a classe personalizada
        $pdf = new FpdiIcpBrasil();
        
        // Configurar metadados
        $pdf->SetCreator('Infodoc-SISGED');
        $pdf->SetAuthor('Infodoc-SISGED');
        $pdf->SetTitle('Documento Assinado Digitalmente');
        $pdf->SetSubject('Documento assinado digitalmente');
        $pdf->SetKeywords('assinatura, digital, documento, ICP-Brasil');
        
        // Configurar informações da assinatura
        $info = [
            'Name' => $certInfo['subject']['CN'] ?? 'Assinatura Digital',
            'Location' => $certInfo['subject']['L'] ?? 'Brasil',
            'Reason' => 'Assinatura Digital ICP-Brasil',
            'ContactInfo' => $certInfo['subject']['emailAddress'] ?? '',
            'CertificationLevel' => 3
        ];
        
        // Configurar assinatura
        $pdf->setSignature($certContent, $certContent, $certPass, '', 2, $info);
        
        // Importar páginas do PDF original
        $sourcePdfPath = null;
        $cleanupTempPdf = false;

        if (!empty($pdfPathUsed) && file_exists($pdfPathUsed)) {
            $sourcePdfPath = $pdfPathUsed;
        } else {
            $tempPdf = tempnam($UPLOAD_DIR, 'pdf_src_');
            if ($tempPdf === false || file_put_contents($tempPdf, $pdfContent) === false) {
                throw new Exception('Não foi possível preparar o PDF para importação.');
            }
            $sourcePdfPath = $tempPdf;
            $cleanupTempPdf = true;
        }

        if (!method_exists($pdf, 'setSourceData')) {
            $pageCount = $pdf->setSourceFile($sourcePdfPath);
        } else {
            $pageCount = $pdf->setSourceData($pdfContent);
        }
        
        // Processar cada página
        for ($i = 1; $i <= $pageCount; $i++) {
            $tplIdx = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tplIdx);
            $tplWidth = $size['width'] ?? $size['w'] ?? ($size[0] ?? 0);
            $tplHeight = $size['height'] ?? $size['h'] ?? ($size[1] ?? 0);
            
            // Adicionar página com as mesmas dimensões do original
            $pdf->AddPage($size['orientation'], [$tplWidth, $tplHeight]);
            $pdf->useTemplate($tplIdx);
            
            // Adicionar assinatura apenas na página especificada (padrão: última página)
            $targetPage = isset($input['page']) ? (int)$input['page'] : $pageCount;
            if ($i == $targetPage) {
                $x = isset($input['x_mm']) ? (float)$input['x_mm'] : 20;
                $y = isset($input['y_mm']) ? (float)$input['y_mm'] : 20;
                $w = isset($input['width_mm']) ? (float)$input['width_mm'] : 100;
                $h = 30; // Altura fixa para a assinatura
                
                // Configurar aparência da assinatura
                $pdf->setSignatureAppearance($x, $y, $w, $h, 'Name');
                
                // Adicionar texto de assinatura
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($x, $y);
                $pdf->Cell($w, 5, 'Assinado digitalmente por:', 0, 1, 'L');
                $pdf->SetX($x);
                $pdf->Cell($w, 5, $info['Name'], 0, 1, 'L');
                $pdf->SetX($x);
                $pdf->Cell($w, 5, 'CPF: ' . ($certInfo['subject']['serialNumber'] ?? ''), 0, 1, 'L');
                $pdf->SetX($x);
                $pdf->Cell($w, 5, 'Emissor: ' . ($certInfo['issuer']['O'] ?? ''), 0, 1, 'L');
                $pdf->SetX($x);
                $pdf->Cell($w, 5, 'Data: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
                
                if ($isIcpBrasil) {
                    $pdf->SetX($x);
                    $pdf->Cell($w, 5, 'Assinatura ICP-Brasil válida', 0, 1, 'L');
                }
            }
        }
        
        // Gerar PDF assinado
        $signedPdf = $pdf->Output('documento_assinado.pdf', 'S');

        if ($cleanupTempPdf && file_exists($sourcePdfPath)) {
            @unlink($sourcePdfPath);
        }
        
        // Salvar arquivo assinado
        $signedFilename = 'signed_' . uniqid() . '.pdf';
        $signedFilepath = $UPLOAD_DIR . $signedFilename;
        
        if (file_put_contents($signedFilepath, $signedPdf) === false) {
            throw new Exception('Falha ao salvar o documento assinado.');
        }
        
        // Retornar sucesso com URL do arquivo assinado
        $signedFileUrl = buildFileUrl('uploads/' . $signedFilename);
        
        resp(true, [
            'signedFile' => $signedFilename,
            'signedFileUrl' => $signedFileUrl,
            'message' => 'Documento assinado com sucesso!'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        resp(false, [
            'message' => 'Erro ao assinar o documento: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

// Ação não reconhecida
else {
    http_response_code(400);
    resp(false, ['message' => 'Ação não reconhecida']);
}
