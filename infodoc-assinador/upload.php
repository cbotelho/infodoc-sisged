<?php
// upload.php - salva arquivo em uploads/
$targetDir = __DIR__ . '/uploads/';
if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
if (!isset($_FILES['pdfFile'])) {
    header('HTTP/1.1 400 Bad Request'); echo 'Nenhum arquivo enviado.'; exit;
}
$fn = basename($_FILES['pdfFile']['name']);
$ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
if ($ext !== 'pdf') { header('HTTP/1.1 400 Bad Request'); echo 'Apenas PDF.'; exit; }
$new = time().'_'.preg_replace('/[^A-Za-z0-9_.-]/','_', $fn);
move_uploaded_file($_FILES['pdfFile']['tmp_name'], $targetDir . $new);
echo json_encode(['success'=>true,'file'=>$new]);
