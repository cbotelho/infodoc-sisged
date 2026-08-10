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

function get_normalized_request_host()
{
    $httpHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';

    if (strpos($httpHost, ',') !== false) {
        $hosts = explode(',', $httpHost);
        $httpHost = trim($hosts[0]);
    }

    $httpHost = trim((string) $httpHost);

    // Remove porta para evitar variações como "host:443".
    if (strpos($httpHost, ':') !== false) {
        $httpHost = explode(':', $httpHost, 2)[0];
    }

    return strtolower($httpHost);
}

function resolve_r2_bucket_name()
{
    $defaultBucket = trim((string) (getenv('FILE_STORAGE_R2_BUCKET') ?: ''));

    // Regra unificada: mesmo bucket padrao para todos os tenants.
    return $defaultBucket;
}

function resolve_r2_bucket_candidates()
{
    $primary = resolve_r2_bucket_name();
    $defaultBucket = trim((string) (getenv('FILE_STORAGE_R2_BUCKET') ?: ''));
    $candidates = [];

    foreach ([$primary, $defaultBucket] as $bucket) {
        if ($bucket !== '' && !in_array($bucket, $candidates, true)) {
            $candidates[] = $bucket;
        }
    }

    return $candidates;
}

function sanitize_storage_path($path)
{
    $path = trim(str_replace('\\', '/', (string) $path), '/');

    if ($path === '') {
        return '';
    }

    $segments = array_filter(explode('/', $path), 'strlen');
    $safeSegments = [];

    foreach ($segments as $segment) {
        $safeSegments[] = sanitize_storage_filename($segment);
    }

    return implode('/', array_filter($safeSegments, 'strlen'));
}

function build_r2_client()
{
    load_r2_sdk();

    $endpoint = trim((string) (getenv('FILE_STORAGE_R2_ENDPOINT') ?: ''));
    $region = trim((string) (getenv('FILE_STORAGE_R2_REGION') ?: 'auto'));
    $accessKeyId = trim((string) (getenv('FILE_STORAGE_R2_ACCESS_KEY_ID') ?: ''));
    $secretAccessKey = trim((string) (getenv('FILE_STORAGE_R2_SECRET_ACCESS_KEY') ?: ''));
    $bucket = resolve_r2_bucket_name();

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
    $normalizedRelative = trim(str_replace('\\', '/', (string) $relativePath), '/');
    $objectParts = array_filter([$prefix, 'upload', $normalizedRelative], 'strlen');

    return implode('/', $objectParts);
}

function sanitize_storage_filename($filename)
{
    $name = basename((string) $filename);

    if ($name === '') {
        return '';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if ($ascii === false || $ascii === '') {
        $ascii = $name;
    }

    $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $ascii);
    $ascii = preg_replace('/_+/', '_', $ascii);
    $ascii = trim((string) $ascii, '._');

    return $ascii;
}

function build_r2_candidate_keys($relativePath)
{
    $prefix = trim((string) (getenv('FILE_STORAGE_R2_OBJECT_PREFIX') ?: 'ged'), '/');
    $rawRelative = trim(str_replace('\\', '/', (string) $relativePath), '/');

    // Aceita variacoes comuns de encoding em path (espaco/+ e dupla codificacao).
    $relativeVariants = array_values(array_unique(array_filter([
        $rawRelative,
        str_replace('_', '#', $rawRelative),
        rawurldecode($rawRelative),
        str_replace('_', '#', rawurldecode($rawRelative)),
        rawurldecode(rawurldecode($rawRelative)),
        str_replace('_', '#', rawurldecode(rawurldecode($rawRelative))),
        str_replace('+', ' ', $rawRelative),
        str_replace('+', ' ', rawurldecode($rawRelative)),
        str_replace('+', ' ', rawurldecode(rawurldecode($rawRelative))),
    ], 'strlen')));

    $folders = ['upload', 'assinador-python/uploads'];

    $keys = [];
    $seen = [];

    $addKey = static function ($key) use (&$seen, &$keys) {
        if (!is_string($key) || $key === '' || isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $keys[] = $key;
    };

    foreach ($relativeVariants as $variant) {
        $safeRelative = sanitize_storage_path($variant);
        $rawName = basename($variant);
        $safeName = sanitize_storage_filename($rawName);

        foreach ($folders as $folder) {
            foreach ([$variant, $safeRelative, $rawName, $safeName] as $name) {
                if (!is_string($name) || $name === '') {
                    continue;
                }

                $key = implode('/', array_filter([$prefix, $folder, $name], 'strlen'));
                $addKey($key);
            }
        }

        foreach ([$variant, $safeRelative, $rawName, $safeName] as $name) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $key = implode('/', array_filter([$prefix, $name], 'strlen'));
            $addKey($key);
        }
    }

    return $keys;
}

function stream_r2_object($relativePath)
{
    $objectKey = build_r2_object_key($relativePath);
    $client = build_r2_client();
    $bucketCandidates = resolve_r2_bucket_candidates();

    $candidateKeys = build_r2_candidate_keys($relativePath);
    if (!in_array($objectKey, $candidateKeys, true)) {
        array_unshift($candidateKeys, $objectKey);
    }

    $result = null;
    $lastAwsException = null;

    foreach ($bucketCandidates as $bucket) {
        foreach ($candidateKeys as $candidateKey) {
            try {
                $result = $client->getObject([
                    'Bucket' => $bucket,
                    'Key' => $candidateKey,
                ]);
                $objectKey = $candidateKey;
                break 2;
            } catch (AwsException $exception) {
                $statusCode = (int) ($exception->getStatusCode() ?: 0);
                $awsCode = (string) ($exception->getAwsErrorCode() ?: '');
                if ($statusCode === 404 || $awsCode === 'NoSuchKey') {
                    $lastAwsException = $exception;
                    continue;
                }

                if ($statusCode === 403 || $awsCode === 'AccessDenied') {
                    $lastAwsException = $exception;
                    break;
                }

                fail_with_status(502, 'Falha ao recuperar arquivo no R2.');
            } catch (Throwable $exception) {
                fail_with_status(502, 'Falha ao recuperar arquivo no R2.');
            }
        }
    }

    if ($result === null) {
        if ($lastAwsException instanceof AwsException) {
            fail_with_status(404, 'Arquivo nao encontrado.');
        }

        fail_with_status(404, 'Arquivo nao encontrado.');
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

$legacyRelativePath = str_replace('_', '#', $relativePath);
if ($legacyRelativePath !== $relativePath) {
    $legacyLocalPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $legacyRelativePath);
    if (is_file($legacyLocalPath)) {
        stream_local_file($legacyLocalPath, $legacyRelativePath);
    }
}

$safeLocalName = sanitize_storage_filename($relativePath);
if ($safeLocalName !== '' && $safeLocalName !== basename($relativePath)) {
    $safeLocalPath = __DIR__ . DIRECTORY_SEPARATOR . $safeLocalName;
    if (is_file($safeLocalPath)) {
        stream_local_file($safeLocalPath, $relativePath);
    }
}

stream_r2_object($relativePath);