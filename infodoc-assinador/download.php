<?php
// download.php?file=filename.pdf
$file = $_GET['file'] ?? '';
$path = __DIR__ . '/signed/' . basename($file);
if (!$file || !file_exists($path)) { http_response_code(404); echo 'Arquivo não encontrado.'; exit; }
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.basename($path).'"');
readfile($path);
