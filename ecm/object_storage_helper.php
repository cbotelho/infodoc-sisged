<?php

function ged_get_root_dir() {
    return dirname(__DIR__);
}

function ged_load_env_file_values() {
    static $values = null;

    if ($values !== null) {
        return $values;
    }

    $values = [];
    $files = [
        ged_get_root_dir() . DIRECTORY_SEPARATOR . '.env.docker.example',
        ged_get_root_dir() . DIRECTORY_SEPARATOR . '.env.production.portainer.example',
        ged_get_root_dir() . DIRECTORY_SEPARATOR . 'assinador-python' . DIRECTORY_SEPARATOR . '.env',
        ged_get_root_dir() . DIRECTORY_SEPARATOR . '.env',
    ];

    foreach ($files as $filePath) {
        if (!is_file($filePath)) {
            continue;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));

            if ($key === '') {
                continue;
            }

            if (($commentPos = strpos($value, ' #')) !== false) {
                $value = substr($value, 0, $commentPos);
            }

            $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    return $values;
}

function ged_get_runtime_setting($key, $default = '') {
    $value = getenv($key);

    if ($value !== false && trim((string) $value) !== '') {
        return trim((string) $value);
    }

    if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
        return trim((string) $_ENV[$key]);
    }

    if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
        return trim((string) $_SERVER[$key]);
    }

    $envValues = ged_load_env_file_values();

    if (isset($envValues[$key]) && trim((string) $envValues[$key]) !== '') {
        return trim((string) $envValues[$key]);
    }

    return $default;
}

function ged_get_r2_config() {
    return [
        'endpoint' => ged_get_runtime_setting('FILE_STORAGE_R2_ENDPOINT', ''),
        'region' => ged_get_runtime_setting('FILE_STORAGE_R2_REGION', 'auto'),
        'bucket' => ged_get_runtime_setting('FILE_STORAGE_R2_BUCKET', ''),
        'access_key_id' => ged_get_runtime_setting('FILE_STORAGE_R2_ACCESS_KEY_ID', ''),
        'secret_access_key' => ged_get_runtime_setting('FILE_STORAGE_R2_SECRET_ACCESS_KEY', ''),
        'object_prefix' => trim(ged_get_runtime_setting('FILE_STORAGE_R2_OBJECT_PREFIX', 'ged'), '/'),
    ];
}

function ged_sync_r2_upload_enabled() {
    static $enabled = null;

    if ($enabled !== null) {
        return $enabled;
    }

    $value = ged_get_runtime_setting('GED_ENABLE_SYNC_R2_UPLOAD', '1');
    $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);

    return $enabled;
}

function ged_r2_is_enabled() {
    $config = ged_get_r2_config();

    return $config['endpoint'] !== ''
        && $config['bucket'] !== ''
        && $config['access_key_id'] !== ''
        && $config['secret_access_key'] !== '';
}

function ged_require_r2_sdk() {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $autoload = ged_get_root_dir() . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'file_storage_modules' . DIRECTORY_SEPARATOR . 'r2' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

    if (!is_file($autoload)) {
        throw new RuntimeException('AWS SDK nao encontrada para envio ao R2.');
    }

    require_once $autoload;
    $loaded = true;
}

function ged_get_r2_client() {
    static $client = null;

    if ($client !== null) {
        return $client;
    }

    if (!ged_r2_is_enabled()) {
        return null;
    }

    $config = ged_get_r2_config();
    ged_require_r2_sdk();

    $client = new Aws\S3\S3Client([
        'version' => 'latest',
        'region' => $config['region'],
        'endpoint' => $config['endpoint'],
        'credentials' => [
            'key' => $config['access_key_id'],
            'secret' => $config['secret_access_key'],
        ],
        'signature_version' => 'v4',
    ]);

    return $client;
}

function ged_build_object_key($fileName, $folder = 'upload') {
    $config = ged_get_r2_config();
    $parts = [];

    if ($config['object_prefix'] !== '') {
        $parts[] = $config['object_prefix'];
    }

    $parts[] = trim($folder, '/');
    $parts[] = basename($fileName);

    return implode('/', array_filter($parts, 'strlen'));
}

function ged_get_local_upload_dir() {
    return ged_get_root_dir() . DIRECTORY_SEPARATOR . 'upload';
}

function ged_ensure_local_upload_dir() {
    $uploadDir = ged_get_local_upload_dir();

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Nao foi possivel criar o diretorio local de upload do GED.');
    }

    return $uploadDir;
}

function ged_detect_content_type($localPath) {
    $contentType = @mime_content_type($localPath);

    return $contentType ?: 'application/octet-stream';
}

function ged_upload_file($localPath, $fileName, $folder = 'upload') {
    if (!is_file($localPath)) {
        throw new RuntimeException('Arquivo temporario nao encontrado para envio.');
    }

    $safeName = basename($fileName);

    if (!ged_r2_is_enabled() || !ged_sync_r2_upload_enabled()) {
        $uploadDir = ged_ensure_local_upload_dir();
        $destination = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (realpath($localPath) !== realpath($destination) && !@copy($localPath, $destination)) {
            throw new RuntimeException('Falha ao gravar o arquivo no armazenamento local de fallback.');
        }

        return [
            'mode' => ged_r2_is_enabled() ? 'local-buffer' : 'local',
            'path' => $destination,
        ];
    }

    $client = ged_get_r2_client();
    $config = ged_get_r2_config();
    $objectKey = ged_build_object_key($safeName, $folder);

    $stream = fopen($localPath, 'rb');

    if ($stream === false) {
        throw new RuntimeException('Falha ao abrir o arquivo temporario para envio ao R2.');
    }

    try {
        $client->upload(
            $config['bucket'],
            $objectKey,
            $stream,
            'private',
            [
                'params' => [
                    'ContentType' => ged_detect_content_type($localPath),
                ],
            ]
        );
    } catch (Throwable $e) {
        throw new RuntimeException('Falha ao enviar arquivo para o R2: ' . $e->getMessage(), 0, $e);
    } finally {
        fclose($stream);
    }

    return [
        'mode' => 'r2',
        'path' => $objectKey,
    ];
}

function ged_download_file_to_path($fileName, $destinationPath, $folder = 'upload') {
    $safeName = basename($fileName);

    if (!ged_r2_is_enabled() || !ged_sync_r2_upload_enabled()) {
        $sourcePath = ged_get_local_upload_dir() . DIRECTORY_SEPARATOR . $safeName;

        if (!is_file($sourcePath)) {
            throw new RuntimeException('Arquivo nao encontrado no armazenamento local de fallback.');
        }

        if (!@copy($sourcePath, $destinationPath)) {
            throw new RuntimeException('Falha ao copiar arquivo do armazenamento local.');
        }

        return [
            'mode' => ged_r2_is_enabled() ? 'local-buffer' : 'local',
            'path' => $destinationPath,
        ];
    }

    $client = ged_get_r2_client();
    $config = ged_get_r2_config();
    $objectKey = ged_build_object_key($safeName, $folder);

    try {
        $client->getObject([
            'Bucket' => $config['bucket'],
            'Key' => $objectKey,
            'SaveAs' => $destinationPath,
        ]);
    } catch (Throwable $e) {
        throw new RuntimeException('Falha ao baixar arquivo do R2: ' . $e->getMessage(), 0, $e);
    }

    return [
        'mode' => 'r2',
        'path' => $destinationPath,
    ];
}