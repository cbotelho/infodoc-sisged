<?php
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/object_storage_helper.php';

function fail_test($message, $statusCode = 1)
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
    }

    echo 'ERRO: ' . $message . PHP_EOL;
    exit($statusCode);
}

function cleanup_test_file($fileName, $uploadResult)
{
    try {
        if (($uploadResult['mode'] ?? '') === 'r2' && ged_r2_is_enabled() && ged_sync_r2_upload_enabled()) {
            $client = ged_get_r2_client();
            $config = ged_get_r2_config();

            if ($client) {
                $client->deleteObject([
                    'Bucket' => $config['bucket'],
                    'Key' => ged_build_object_key($fileName, 'upload'),
                ]);
            }

            return;
        }

        if (isset($uploadResult['path']) && is_file($uploadResult['path'])) {
            @unlink($uploadResult['path']);
        }
    } catch (Throwable $cleanupError) {
        echo 'AVISO: falha ao limpar arquivo de teste: ' . $cleanupError->getMessage() . PHP_EOL;
    }
}

try {
    $config = ged_get_r2_config();

    if (!ged_r2_is_enabled()) {
        fail_test('As variaveis FILE_STORAGE_R2_* nao estao configuradas para o helper PHP.');
    }

    $fileName = 'r2_php_smoke_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.txt';
    $content = 'Teste local PHP GED em ' . date('c');
    $tempSource = tempnam(sys_get_temp_dir(), 'ged_r2_src_');
    $tempTarget = tempnam(sys_get_temp_dir(), 'ged_r2_dst_');

    if ($tempSource === false || $tempTarget === false) {
        fail_test('Nao foi possivel criar arquivos temporarios para o teste.');
    }

    file_put_contents($tempSource, $content);

    $uploadResult = ged_upload_file($tempSource, $fileName, 'upload');
    $downloadResult = ged_download_file_to_path($fileName, $tempTarget, 'upload');
    $downloadedContent = file_get_contents($tempTarget);

    if ($downloadedContent !== $content) {
        cleanup_test_file($fileName, $uploadResult);
        fail_test('O conteudo baixado nao corresponde ao conteudo enviado.');
    }

    echo 'PHP_R2_OK' . PHP_EOL;
    echo 'MODE=' . ($uploadResult['mode'] ?? 'desconhecido') . PHP_EOL;
    echo 'BUCKET=' . $config['bucket'] . PHP_EOL;
    echo 'OBJECT_KEY=' . ged_build_object_key($fileName, 'upload') . PHP_EOL;
    echo 'DOWNLOAD_PATH=' . ($downloadResult['path'] ?? $tempTarget) . PHP_EOL;
    echo 'SYNC_ENABLED=' . (ged_sync_r2_upload_enabled() ? 'true' : 'false') . PHP_EOL;

    cleanup_test_file($fileName, $uploadResult);

    @unlink($tempSource);
    @unlink($tempTarget);
} catch (Throwable $error) {
    if (isset($tempSource) && is_file($tempSource)) {
        @unlink($tempSource);
    }

    if (isset($tempTarget) && is_file($tempTarget)) {
        @unlink($tempTarget);
    }

    fail_test($error->getMessage());
}