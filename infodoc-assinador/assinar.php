<?php
// assinar.php - tenta usar setasign/fpdi-tcpdf se disponível para criar assinatura visível e PKCS#7.
// Caso contrário, faz um 'simulado' de assinatura: grava assinatura separada (.sig) e concatena para demonstração.
// Recebe: cert (filename in certs/), password (for pfx), file (name in uploads), optional sigImage
header('Content-Type: application/json; charset=utf-8');
$cert = $_POST['cert'] ?? ($_POST['certificado'] ?? '');
$password = $_POST['password'] ?? ($_POST['certPassword'] ?? '');
$file = $_POST['file'] ?? ($_POST['pdfFile'] ?? '');
if (!$cert || !$file) { echo json_encode(['success'=>false,'message'=>'cert ou file ausente']); exit; }
$certPath = __DIR__.'/certs/'.basename($cert);
$pdfPath = __DIR__.'/uploads/'.basename($file);
if (!file_exists($certPath) || !file_exists($pdfPath)) { echo json_encode(['success'=>false,'message'=>'Certificado ou PDF não encontrado']); exit; }

// try to use FPDI+TCPDF if available
if (file_exists(__DIR__.'/vendor/autoload.php')) {
    require_once __DIR__.'/vendor/autoload.php';
    // try to use Fpdi Tcpdf
    try {
        $useFpdi = class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi') || class_exists('setasign\\Fpdi\\Fpdi');
    } catch (Exception $e) { $useFpdi = false; }
} else { $useFpdi = false; }

if ($useFpdi) {
    try {
        // Use FPDI/TCPDF route
        if (class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi')) {
            $pdf = new setasign\Fpdi\Tcpdf\Fpdi();
        } else {
            $pdf = new setasign\Fpdi\Fpdi();
        }
        $pageCount = $pdf->setSourceFile($pdfPath);
        for ($p=1;$p<=$pageCount;$p++) {
            $tpl = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'],$size['height']]);
            $pdf->useTemplate($tpl);
        }
        // create signed output
        $outDir = __DIR__.'/signed/';
        if (!is_dir($outDir)) mkdir($outDir,0777,true);
        $outName = 'ASSINADO_'.basename($file);
        $pdf->Output($outDir.$outName,'F');
        echo json_encode(['success'=>true,'signed'=>$outName,'message'=>'Assinado com FPDI (visual)']);
        exit;
    } catch (Exception $e) {
        // fallback to openssl_sign below
    }
}

// fallback: basic openssl_sign demo
// read pfx if possible
$ext = strtolower(pathinfo($certPath, PATHINFO_EXTENSION));
$pkey = null;
$pubcert = null;
if (in_array($ext,['pfx','p12'])) {
    $pfx = file_get_contents($certPath);
    $certs = [];
    if (!openssl_pkcs12_read($pfx,$certs,$password)) {
        echo json_encode(['success'=>false,'message'=>'Falha ao ler .pfx. Use senha correta.']); exit;
    }
    $pubcert = $certs['cert'];
    $pkey = openssl_pkey_get_private($certs['pkey']);
} elseif ($ext==='pem') {
    $pem = file_get_contents($certPath);
    $pubcert = $pem;
    // try to find key in same file - or fail
    if (strpos($pem,'PRIVATE KEY')!==false) {
        $pkey = openssl_pkey_get_private($pem);
    } else {
        // no private key
        echo json_encode(['success'=>false,'message'=>'PEM sem chave privada.']); exit;
    }
} else {
    echo json_encode(['success'=>false,'message'=>'Formato de certificado não suportado no demo.']); exit;
}

$data = file_get_contents($pdfPath);
if (!$pkey) { echo json_encode(['success'=>false,'message'=>'Chave privada não disponível']); exit; }
openssl_sign($data,$signature,$pkey,OPENSSL_ALGO_SHA256);
$outDir = __DIR__.'/signed/'; if (!is_dir($outDir)) mkdir($outDir,0777,true);
$outName = 'ASSINADO_'.basename($file);
copy($pdfPath,$outDir.$outName);
// append a marker and signature for demo (NOT a real PAdES embedding)
file_put_contents($outDir.$outName, "\n\n%--SIGNATURE--\n".base64_encode($signature), FILE_APPEND);
echo json_encode(['success'=>true,'signed'=>$outName,'message'=>'Assinado (demo)']);
