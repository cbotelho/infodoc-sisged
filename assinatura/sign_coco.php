<?php
require_once('tcpdf/tcpdf.php');
require_once('fpdi/src/autoload.php');

use setasign\Fpdi\Tcpdf\Fpdi;

// Configurações
$UPLOAD_DIR = __DIR__ . '/uploads/';
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

// Função para retornar resposta em JSON
function json_response($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Método não permitido');
}

// Verificar se o arquivo PDF foi enviado
if (empty($_FILES['file'])) {
    json_response(false, 'Por favor, envie o PDF');
}

// Processar parâmetros
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$x = isset($_POST['x_mm']) ? (float)$_POST['x_mm'] : 20;
$y = isset($_POST['y_mm']) ? (float)$_POST['y_mm'] : 20;
$width = isset($_POST['width_mm']) ? (float)$_POST['width_mm'] : 50;
$height = isset($_POST['height_mm']) ? (float)$_POST['height_mm'] : 20;

// Caminhos dos arquivos
$pdf_path = $_FILES['file']['tmp_name'];
$output_filename = 'signed_' . uniqid() . '.pdf';
$output_path = $UPLOAD_DIR . $output_filename;

try {
    // Carregar o PDF existente
    $pdf = new Fpdi();
    
    // Contar o número de páginas
    $pageCount = $pdf->setSourceFile($pdf_path);
    if ($page < 1 || $page > $pageCount) {
        $page = 1; // Página padrão se for inválida
    }
    
    // Adicionar a página ao PDF
    $pageId = $pdf->importPage($page);
    $size = $pdf->getTemplateSize($pageId);
    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $pdf->useTemplate($pageId);
    
    // Adicionar a imagem da assinatura
    $signature_img = __DIR__ . '/assinatura.png'; // Imagem da assinatura
    
    // Se não existir a imagem da assinatura, criar uma
    if (!file_exists($signature_img)) {
        // Criar uma imagem de assinatura de exemplo
        $img = imagecreatetruecolor(400, 150);
        $bg = imagecolorallocate($img, 255, 255, 255);
        $text_color = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, 399, 149, $bg);
        imagettftext($img, 20, 0, 10, 50, $text_color, 'arial.ttf', 'Assinatura Digital');
        imagettftext($img, 12, 0, 10, 80, $text_color, 'arial.ttf', 'Nome do Signatário');
        imagettftext($img, 10, 0, 10, 110, $text_color, 'arial.ttf', 'CPF: 000.000.000-00');
        imagepng($img, $signature_img);
        imagedestroy($img);
    }
    
    // Inserir a assinatura no PDF
    $pdf->Image($signature_img, $x, $y, $width, 0, '', '', '', false, 300, '', false, false, 0);
    
    // Salvar o PDF
    $pdf->Output($output_path, 'F');
    
    // Retornar sucesso
    json_response(true, 'Assinatura adicionada ao documento com sucesso!', [
        'download_url' => '/assinatura/uploads/' . $output_filename,
        'file_name' => $output_filename
    ]);
    
} catch (Exception $e) {
    json_response(false, 'Erro ao processar o documento: ' . $e->getMessage());
}