<?php
// download.php
$uploads = __DIR__ . '/uploads/';
$file = $_GET['file'] ?? '';
if (!$file) { http_response_code(400); echo "Arquivo não informado."; exit; }

$filename = basename($file);
$path = realpath($uploads . $filename);
if (!$path || strpos($path, realpath($uploads)) !== 0 || !file_exists($path)) {
    http_response_code(404); echo "Arquivo não encontrado."; exit;
}

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
