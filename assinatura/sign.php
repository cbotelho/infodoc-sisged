<?php
// sign.php - Versão Final Corrigida
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Carregar autoload
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Classe personalizada que combina TCPDF com FPDI
 */
class FpdiIcpBrasil extends TCPDF {
    use \setasign\Fpdi\FpdiTrait;
    
    private $sigX, $sigY, $sigW, $sigH;
    private $sigCertInfo = [];
    
    /**
     * Configurar assinatura digital - sem usar setSignatureAlgorithm
     */
    public function setSignature(
        $signing_cert = '',
        $private_key = '',
        $private_key_password = '',
        $extracerts = '',
        $cert_type = 2,
        $info = array(),
        $approval = ''
    ) {
        // Processar PKCS#12 (PFX/P12)
        if (strpos($signing_cert, '-----BEGIN') === false) {
            $certs = [];
            if (openssl_pkcs12_read($signing_cert, $certs, $private_key_password)) {
                $signing_cert = $certs['cert'];
                $private_key = $certs['pkey'];
            } else {
                throw new Exception('Erro ao ler certificado PKCS#12. Senha incorreta ou arquivo inválido.');
            }
        }
        
        $certInfo = openssl_x509_parse($signing_cert);
        $this->sigCertInfo = $certInfo;
        
        $defaultInfo = [
            'Name' => $certInfo['subject']['CN'] ?? 'Assinatura Digital',
            'Location' => $certInfo['subject']['L'] ?? 'Brasil',
            'Reason' => 'Assinatura Digital ICP-Brasil',
            'ContactInfo' => $certInfo['subject']['emailAddress'] ?? '',
            'SigningTime' => time()
        ];
        
        $info = array_merge($defaultInfo, $info);
        
        // Chamar método pai sem o setSignatureAlgorithm
        parent::setSignature(
            $signing_cert,
            $private_key ?: $signing_cert,
            $private_key_password,
            $extracerts,
            $cert_type,
            $info,
            $approval
        );
        
        // O algoritmo padrão já é sha256WithRSAEncryption em versões recentes
    }
    
    /**
     * Sobrescrever método da classe pai
     */
    public function setSignatureAppearance($x = 0, $y = 0, $w = 0, $h = 0, $page = -1, $name = '') {
        parent::setSignatureAppearance($x, $y, $w, $h, $page, $name);
        
        $this->sigX = $x;
        $this->sigY = $y;
        $this->sigW = $w;
        $this->sigH = $h;
    }
    
    /**
     * Adicionar aparência visual
     */
    public function addVisualSignature($x, $y, $w, $h, $certInfo = []) {
        // Desenhar retângulo
        $this->SetDrawColor(0, 51, 102);
        $this->SetLineWidth(0.5);
        $this->Rect($x, $y, $w, $h, 'D');
        
        // Texto da assinatura
        $this->SetFont('helvetica', 'B', 8);
        $this->SetTextColor(0, 51, 102);
        $this->SetXY($x + 2, $y + 2);
        $this->Cell($w - 4, 4, 'DOCUMENTO ASSINADO DIGITALMENTE', 0, 2, 'L');
        
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x + 2, $y + 8);
        $this->Cell($w - 4, 4, 'Certificado: ' . ($certInfo['subject']['CN'] ?? 'ICP-Brasil'), 0, 2, 'L');
        
        $this->SetXY($x + 2, $y + 13);
        $this->Cell($w - 4, 4, 'Data: ' . date('d/m/Y H:i:s'), 0, 2, 'L');
    }
}

// Configurações
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('CERT_DIR', __DIR__ . '/certs/');

// Criar diretórios
foreach ([UPLOAD_DIR, CERT_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Função de resposta
function resp($ok, $data = []) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// Roteamento
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch($action) {
        case 'ping':
            resp(true, ['message' => 'pong', 'time' => date('Y-m-d H:i:s')]);
            break;
            
        case 'certs_list':
            $certs = [];
            if (is_dir(CERT_DIR)) {
                $files = scandir(CERT_DIR);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $certs[] = $file;
                    }
                }
            }
            resp(true, ['certs' => $certs]);
            break;
            
        case 'upload':
            if (!isset($_FILES['pdfFile']) || $_FILES['pdfFile']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Nenhum arquivo enviado ou erro no upload');
            }
            
            $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $_FILES['pdfFile']['name']);
            $filepath = UPLOAD_DIR . $filename;
            
            if (move_uploaded_file($_FILES['pdfFile']['tmp_name'], $filepath)) {
                resp(true, [
                    'filename' => $filename,
                    'filesize' => filesize($filepath)
                ]);
            } else {
                throw new Exception('Falha no upload');
            }
            break;
            
        case 'upload_cert':
            if (!isset($_FILES['certFile']) || $_FILES['certFile']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Nenhum certificado enviado ou erro no upload');
            }
            
            $filename = preg_replace('/[^a-zA-Z0-9\._-]/', '', $_FILES['certFile']['name']);
            $filepath = CERT_DIR . $filename;
            
            if (move_uploaded_file($_FILES['certFile']['tmp_name'], $filepath)) {
                resp(true, [
                    'filename' => $filename,
                    'filesize' => filesize($filepath)
                ]);
            } else {
                throw new Exception('Falha no upload do certificado');
            }
            break;
            
        case 'sign':
            // Validar parâmetros
            $pdfFile = $_POST['file'] ?? '';
            $certName = $_POST['cert'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($pdfFile) || empty($certName)) {
                throw new Exception('PDF e certificado são obrigatórios');
            }
            
            // Verificar arquivos
            $pdfPath = UPLOAD_DIR . $pdfFile;
            $certPath = CERT_DIR . $certName;
            
            if (!file_exists($pdfPath)) {
                throw new Exception('Arquivo PDF não encontrado: ' . $pdfFile);
            }
            if (!file_exists($certPath)) {
                throw new Exception('Certificado não encontrado: ' . $certName);
            }
            
            // Carregar certificado
            $certContent = file_get_contents($certPath);
            if (!$certContent) {
                throw new Exception('Erro ao ler certificado');
            }
            
            // Processar certificado
            $certs = [];
            $isPkcs12 = openssl_pkcs12_read($certContent, $certs, $password);
            
            if ($isPkcs12) {
                $certData = $certs['cert'];
                $privateKey = $certs['pkey'];
            } else {
                $certData = $certContent;
                $privateKey = $certContent;
            }
            
            // Validar certificado
            $certInfo = openssl_x509_parse($certData);
            if (!$certInfo) {
                throw new Exception('Certificado inválido ou senha incorreta');
            }
            
            // Verificar validade
            $now = time();
            if (isset($certInfo['validFrom_time_t']) && $now < $certInfo['validFrom_time_t']) {
                throw new Exception('Certificado ainda não é válido');
            }
            if (isset($certInfo['validTo_time_t']) && $now > $certInfo['validTo_time_t']) {
                throw new Exception('Certificado expirado');
            }
            
            // Criar PDF assinado
            $pdf = new FpdiIcpBrasil();
            
            // Configurar metadados
            $pdf->SetCreator('Infodoc-SISGED');
            $pdf->SetAuthor($certInfo['subject']['CN'] ?? 'Usuário');
            $pdf->SetTitle('Documento Assinado Digitalmente');
            
            // Configurar assinatura
            $pdf->setSignature(
                $certData,
                $privateKey,
                $password,
                '',
                2,
                [
                    'Name' => $certInfo['subject']['CN'] ?? 'Assinante',
                    'Location' => $certInfo['subject']['L'] ?? 'Brasil',
                    'Reason' => 'Assinatura Digital',
                    'ContactInfo' => $certInfo['subject']['emailAddress'] ?? ''
                ]
            );
            
            // Importar páginas
            $pageCount = $pdf->setSourceFile($pdfPath);
            
            // Determinar página da assinatura
            $targetPage = isset($_POST['page']) ? (int)$_POST['page'] : $pageCount;
            $targetPage = min($targetPage, $pageCount);
            
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplIdx = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplIdx);
                
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplIdx);
                
                if ($i == $targetPage) {
                    $x = isset($_POST['x_mm']) ? (float)$_POST['x_mm'] : 20;
                    $y = isset($_POST['y_mm']) ? (float)$_POST['y_mm'] : ($size['height'] - 50);
                    $w = isset($_POST['width_mm']) ? (float)$_POST['width_mm'] : 80;
                    $h = 30;
                    
                    // Configurar aparência da assinatura
                    $pdf->setSignatureAppearance($x, $y, $w, $h, $i, $certInfo['subject']['CN'] ?? '');
                    
                    // Adicionar aparência visual
                    $pdf->addVisualSignature($x, $y, $w, $h, $certInfo);
                }
            }
            
            // Gerar PDF assinado
            $signedFilename = 'signed_' . uniqid() . '.pdf';
            $signedPath = UPLOAD_DIR . $signedFilename;
            
            $pdf->Output($signedPath, 'F');
            
            // Verificar se gerou
            if (!file_exists($signedPath) || filesize($signedPath) === 0) {
                throw new Exception('Falha ao gerar PDF assinado');
            }
            
            resp(true, [
                'signedFile' => $signedFilename,
                'signedUrl' => 'uploads/' . $signedFilename,
                'filesize' => filesize($signedPath),
                'certInfo' => [
                    'subject' => $certInfo['subject']['CN'] ?? '',
                    'issuer' => $certInfo['issuer']['CN'] ?? '',
                    'validFrom' => date('d/m/Y', $certInfo['validFrom_time_t']),
                    'validTo' => date('d/m/Y', $certInfo['validTo_time_t'])
                ]
            ]);
            break;
            
        default:
            throw new Exception('Ação não reconhecida: ' . $action);
    }
} catch (Exception $e) {
    resp(false, ['message' => $e->getMessage()]);
}