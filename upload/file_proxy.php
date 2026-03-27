<?php

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

function fail_with_status($statusCode, $message)
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function get_requested_relative_path()
{
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/upload/file_proxy.php'));
    $scriptDirectory = rtrim($scriptDirectory, '/');

    if (!is_string($requestPath) || $requestPath === '' || $scriptDirectory === '') {
        fail_with_status(404, 'Arquivo nao encontrado.');
    }

    $prefix = $scriptDirectory . '/';

    if (strpos($requestPath, $prefix) !== 0) {
        fail_with_status(404, 'Arquivo nao encontrado.');
    }

    $relativePath = rawurldecode(substr($requestPath, strlen($prefix)));
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

    if ($relativePath === '' || $relativePath === 'file_proxy.php' || preg_match('#(^|/)\.\.(?:/|$)#', $relativePath)) {
        fail_with_status(404, 'Arquivo nao encontrado.');
    }

    return $relativePath;
}

function send_common_headers($contentType, $contentLength, $downloadName)
{
    header('Content-Type: ' . ($contentType ?: 'application/octet-stream'));

    if ($contentLength !== null) {
        header('Content-Length: ' . (int) $contentLength);
    }

    header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($downloadName)) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=300');
}

function stream_local_file($filePath, $downloadName)
{
    $contentType = mime_content_type($filePath) ?: 'application/octet-stream';
    $contentLength = filesize($filePath);

    send_common_headers($contentType, $contentLength, $downloadName);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        readfile($filePath);
    }

    exit;
}

function load_r2_sdk()
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $autoload = dirname(__DIR__) . '/plugins/ext/file_storage_modules/r2/vendor/autoload.php';

    if (!is_file($autoload)) {
        fail_with_status(500, 'AWS SDK nao encontrada para leitura de arquivos no R2.');
    }

    require_once $autoload;
    $loaded = true;
}

function build_r2_client()
{
    load_r2_sdk();

    $endpoint = trim((string) (getenv('FILE_STORAGE_R2_ENDPOINT') ?: ''));
    $region = trim((string) (getenv('FILE_STORAGE_R2_REGION') ?: 'auto'));
    $accessKeyId = trim((string) (getenv('FILE_STORAGE_R2_ACCESS_KEY_ID') ?: ''));
    $secretAccessKey = trim((string) (getenv('FILE_STORAGE_R2_SECRET_ACCESS_KEY') ?: ''));
    $bucket = trim((string) (getenv('FILE_STORAGE_R2_BUCKET') ?: ''));

    if ($endpoint === '' || $accessKeyId === '' || $secretAccessKey === '' || $bucket === '') {
        fail_with_status(500, 'Configuracao R2 incompleta no ambiente.');
    }

    return new S3Client([
        'version' => 'latest',
        'region' => $region,
        'endpoint' => $endpoint,
        'credentials' => [
            'key' => $accessKeyId,
            'secret' => $secretAccessKey,
        ],
        'signature_version' => 'v4',
    ]);
}

function build_r2_object_key($relativePath)
{
    $prefix = trim((string) (getenv('FILE_STORAGE_R2_OBJECT_PREFIX') ?: 'ged'), '/');
    $objectParts = array_filter([$prefix, 'upload', basename($relativePath)], 'strlen');

    return implode('/', $objectParts);
}

function stream_r2_object($relativePath)
{
    $bucket = trim((string) (getenv('FILE_STORAGE_R2_BUCKET') ?: ''));
    $objectKey = build_r2_object_key($relativePath);
    $client = build_r2_client();

    try {
        $result = $client->getObject([
            'Bucket' => $bucket,
            'Key' => $objectKey,
        ]);
    } catch (AwsException $exception) {
        $statusCode = (int) ($exception->getStatusCode() ?: 0);

        if ($statusCode === 404 || $exception->getAwsErrorCode() === 'NoSuchKey') {
            fail_with_status(404, 'Arquivo nao encontrado.');
        }

        fail_with_status(502, 'Falha ao recuperar arquivo no R2.');
    } catch (Throwable $exception) {
        fail_with_status(502, 'Falha ao recuperar arquivo no R2.');
    }

    send_common_headers(
        isset($result['ContentType']) ? (string) $result['ContentType'] : 'application/octet-stream',
        isset($result['ContentLength']) ? (int) $result['ContentLength'] : null,
        basename($relativePath)
    );

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        $body = $result['Body'];

        if (is_object($body) && method_exists($body, 'rewind')) {
            $body->rewind();
        }

        if (is_object($body) && method_exists($body, 'eof') && method_exists($body, 'read')) {
            while (!$body->eof()) {
                echo $body->read(8192);
            }
        } else {
            echo (string) $body;
        }
    }

    exit;
}

$relativePath = get_requested_relative_path();
$localPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

if (is_file($localPath)) {
    stream_local_file($localPath, $relativePath);
}

stream_r2_object($relativePath);