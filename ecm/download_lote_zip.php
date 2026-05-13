<?php
require_once __DIR__ . '/object_storage_helper.php';
include '../includes/db.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

function fail_download($message, $statusCode = 400)
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$source = isset($_POST['source']) ? trim((string) $_POST['source']) : 'sefaz_rh';
$caixaId = isset($_POST['caixa_id']) ? trim((string) $_POST['caixa_id']) : '';
$documentIds = isset($_POST['document_ids']) && is_array($_POST['document_ids']) ? $_POST['document_ids'] : [];

if ($caixaId === '' || empty($documentIds)) {
    fail_download('Selecione a Caixa/Pasta e ao menos um documento.');
}

$configs = [
    'ged' => [
        'document_table' => 'app_entity_43',
        'file_field' => 'field_445',
        'numero_field' => 'field_437',
        'parent_table' => 'app_entity_41',
        'folder' => 'upload',
        'label' => 'ged',
    ],
    'sefaz_rh' => [
        'document_table' => 'app_entity_49',
        'file_field' => 'field_542',
        'numero_field' => 'field_527',
        'parent_table' => 'app_entity_48',
        'folder' => 'upload',
        'label' => 'sefaz_rh',
    ],
];

if (!isset($configs[$source])) {
    fail_download('Origem invalida.');
}

$config = $configs[$source];
$cleanDocumentIds = array_values(array_filter(array_map('intval', $documentIds), function ($value) {
    return $value > 0;
}));

if (empty($cleanDocumentIds)) {
    fail_download('Nenhum documento valido foi informado.');
}

$placeholders = implode(',', array_fill(0, count($cleanDocumentIds), '?'));
$params = $cleanDocumentIds;
array_unshift($params, (int) $caixaId);

$parentSql = 'SELECT id, TRIM(' . $config['numero_field'] . ') AS numero FROM ' . $config['parent_table'] . ' WHERE id = ? LIMIT 1';
$parentStmt = $pdo->prepare($parentSql);
$parentStmt->execute([(int) $caixaId]);
$caixa = $parentStmt->fetch(PDO::FETCH_ASSOC);

if (!$caixa) {
    fail_download('Caixa/Pasta nao encontrada.');
}

$sql = 'SELECT id, ' . $config['file_field'] . ' AS arquivo FROM ' . $config['document_table'] . ' WHERE parent_item_id = ? AND id IN (' . $placeholders . ') ORDER BY id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($documents)) {
    fail_download('Nenhum documento foi encontrado para download.');
}

$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'download_lote_' . uniqid('', true);
if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
    fail_download('Nao foi possivel criar a pasta temporaria.', 500);
}

$zipPath = $tempDir . DIRECTORY_SEPARATOR . 'download_' . $config['label'] . '_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $caixa['numero']) . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail_download('Nao foi possivel criar o arquivo ZIP.', 500);
}

$downloadedFiles = [];

try {
    foreach ($documents as $document) {
        $fileName = basename((string) $document['arquivo']);
        if ($fileName === '') {
            continue;
        }

        $tempFilePath = $tempDir . DIRECTORY_SEPARATOR . uniqid('file_', true) . '_' . $fileName;
        ged_download_file_to_path($fileName, $tempFilePath, $config['folder']);
        $zip->addFile($tempFilePath, $fileName);
        $downloadedFiles[] = $tempFilePath;
    }

    $zip->close();

    if (!is_file($zipPath)) {
        fail_download('Falha ao gerar o ZIP.', 500);
    }

    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($zipPath));
    header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($zipPath);
} catch (Throwable $e) {
    $zip->close();
    fail_download('Falha ao preparar o download: ' . $e->getMessage(), 500);
} finally {
    foreach ($downloadedFiles as $tempFilePath) {
        if (is_file($tempFilePath)) {
            @unlink($tempFilePath);
        }
    }

    if (is_file($zipPath)) {
        @unlink($zipPath);
    }

    if (is_dir($tempDir)) {
        @rmdir($tempDir);
    }
}
