<?php
// Habilitar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Forçar o uso do TCPDI para melhor compatibilidade com PDFs
if (!defined('FPDI_VERSION')) {
    define('FPDI_VERSION', '2.3.7'); // Versão compatível
}

// Configurar o autoloader para usar TCPDI e PDF-Parser
use setasign\Fpdi\Tcpdf\Fpdi;
use setasign\FpdiPdfParser\PdfParser\PdfParser;
use setasign\FpdiPdfParser\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi as FpdiTcpdf;

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

$action = $_GET['action'] ?? '';

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
        $fileUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/assinatura/uploads/' . $filename;
        
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
                        $input['pdfFile'] = file_get_contents($file['tmp_name']);
                        file_put_contents('debug.log', "Arquivo PDF recebido. Tamanho: " . strlen($input['pdfFile']) . " bytes\n", FILE_APPEND);
                    }
                    // Se for o certificado
                    elseif ($key === 'cert' || $key === 'certFile') {
                        $input['certFile'] = file_get_contents($file['tmp_name']);
                        file_put_contents('debug.log', "Arquivo de certificado recebido. Tamanho: " . strlen($input['certFile']) . " bytes\n", FILE_APPEND);
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
        if (empty($input['pdfFile']) && empty($_FILES['file']['tmp_name'])) {
            $missing[] = 'pdfFile';
        }
        
        // Verificar Certificado
        if (empty($input['certFile']) && empty($_FILES['cert']['tmp_name'])) {
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
        
        // Verificar se é um arquivo temporário ou dados base64
        if (isset($_FILES['certFile'])) {
            $certContent = file_get_contents($_FILES['certFile']['tmp_name']);
        } elseif (strpos($certData, 'data:application/') === 0) {
            // Remover o cabeçalho do base64 (data:application/...;base64,)
            $certContent = base64_decode(preg_replace('#^data:application/.*;base64,#', '', $certData));
        } else {
            $certContent = base64_decode($certData);
        }
        
        // Processar PDF
        if (isset($_FILES['pdfFile'])) {
            $pdfContent = file_get_contents($_FILES['pdfFile']['tmp_name']);
        } elseif (isset($input['pdfFile'])) {
            // Se for uma URL, tenta baixar o arquivo
            if (filter_var($input['pdfFile'], FILTER_VALIDATE_URL)) {
                $pdfContent = file_get_contents($input['pdfFile']);
                if ($pdfContent === false) {
                    throw new Exception('Não foi possível carregar o PDF da URL fornecida');
                }
            } else {
                // Se for base64
                $pdfContent = base64_decode(preg_replace('#^data:application/.*;base64,#', '', $input['pdfFile']));
            }
        } else {
            throw new Exception('Nenhum arquivo PDF válido foi fornecido');
        }
        
        // Configurações da assinatura
        $info = [
            'Name' => $input['name'] ?? 'Assinatura Digital',
            'Location' => $input['location'] ?? 'Brasil',
            'Reason' => $input['reason'] ?? 'Aprovação do documento',
            'ContactInfo' => $input['contact'] ?? ''
        ];
        
        // Criar PDF assinado
        $pdf = new Fpdi();
        
        // Configurar metadados
        $pdf->SetCreator('Infodoc-SISGED');
        $pdf->SetAuthor('Infodoc-SISGED');
        $pdf->SetTitle('Documento Assinado Digitalmente');
        $pdf->SetSubject('Documento assinado digitalmente');
        $pdf->SetKeywords('assinatura, digital, documento');
        
        // Configurar assinatura
        $pdf->setSignature($certContent, $certContent, $certPass, '', 2, $info);
        
        // Importar páginas do PDF original
        $pageCount = $pdf->setSourceData($pdfContent);
        
        // Processar cada página
        for ($i = 1; $i <= $pageCount; $i++) {
            $tplIdx = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tplIdx);
            
            // Adicionar página com as mesmas dimensões do original
            $pdf->AddPage($size['orientation'], [$size['w'], $size['h']]);
            $pdf->useTemplate($tplIdx);
            
            // Adicionar assinatura apenas na última página
            if ($i == $pageCount) {
                $x = isset($input['x']) ? (float)$input['x'] : 20;
                $y = isset($input['y']) ? (float)$input['y'] : 20;
                $w = isset($input['width']) ? (float)$input['width'] : 100;
                $h = isset($input['height']) ? (float)$input['height'] : 50;
                
                // Configurar aparência da assinatura
                $pdf->setSignatureAppearance($x, $y, $w, $h, 'Name');
            }
        }
        
        // Gerar PDF assinado
        $signedPdf = $pdf->Output('documento_assinado.pdf', 'S');
        
        // Salvar arquivo assinado
        $signedFilename = 'signed_' . uniqid() . '.pdf';
        $signedFilepath = $UPLOAD_DIR . $signedFilename;
        
        if (file_put_contents($signedFilepath, $signedPdf) === false) {
            throw new Exception('Falha ao salvar o documento assinado.');
        }
        
        // Retornar sucesso com URL do arquivo assinado
        $signedFileUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/assinatura/uploads/' . $signedFilename;
        
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
