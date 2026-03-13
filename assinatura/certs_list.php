<?php
// Retorna lista de certificados disponíveis em /certs
$certDir = __DIR__ . '/certs/';
$certFiles = [];
if (is_dir($certDir)) {
    $certFiles = array_values(array_diff(scandir($certDir), ['.', '..']));
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($certFiles);
