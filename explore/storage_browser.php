<?php

declare(strict_types=1);

use Aws\Exception\AwsException;

require_once dirname(__DIR__) . '/ecm/object_storage_helper.php';

function explore_start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (function_exists('app_session_name')) {
        app_session_name(SESSION_NAME);
        app_session_save_path(SESSION_WRITE_DIRECTORY);
        app_session_start();
        return;
    }

    session_name(SESSION_NAME);
    session_save_path(SESSION_WRITE_DIRECTORY);
    session_start();
}

function explore_is_logged_in(): bool
{
    if (function_exists('app_session_is_registered')) {
        return app_session_is_registered('app_logged_users_id');
    }

    return isset($_SESSION['app_logged_users_id']);
}

function explore_detect_bucket(): string
{
    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
    if (strpos($host, ',') !== false) {
        $parts = explode(',', $host);
        $host = trim($parts[0]);
    }

    if (stripos($host, 'cipemac') !== false) {
        return 'cipemac';
    }

    $cfg = ged_get_r2_config();
    return (string) ($cfg['bucket'] ?? '');
}

function explore_sanitize_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    $path = ltrim($path, '/');

    if ($path === '') {
        return '';
    }

    $segments = array_filter(explode('/', $path), 'strlen');
    $safe = [];

    foreach ($segments as $segment) {
        if ($segment === '.' || $segment === '..') {
            continue;
        }

        $safe[] = preg_replace('/[^A-Za-z0-9._\- ]+/', '_', $segment);
    }

    return implode('/', $safe);
}

function explore_build_prefix(string $path = ''): string
{
    $cfg = ged_get_r2_config();
    $parts = [];

    if (!empty($cfg['object_prefix'])) {
        $parts[] = trim((string) $cfg['object_prefix'], '/');
    }

    $parts[] = 'upload';

    $safePath = explore_sanitize_path($path);
    if ($safePath !== '') {
        $parts[] = $safePath;
    }

    return implode('/', $parts) . '/';
}

function explore_human_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = (float) max($bytes, 0);
    $idx = 0;

    while ($size >= 1024 && $idx < count($units) - 1) {
        $size /= 1024;
        $idx++;
    }

    return sprintf('%.2f %s', $size, $units[$idx]);
}

function explore_get_public_url(string $relativePath): string
{
    $safe = explore_sanitize_path($relativePath);
    if ($safe === '') {
        return '/upload/';
    }

    $parts = array_filter(explode('/', $safe), 'strlen');
    $encoded = array_map('rawurlencode', $parts);
    return '/upload/' . implode('/', $encoded);
}

function explore_list_local(string $path = '', int $limit = 100, string $cursor = ''): array
{
    $base = rtrim(ged_get_local_upload_dir(), DIRECTORY_SEPARATOR);
    $safePath = explore_sanitize_path($path);
    $target = $base . ($safePath !== '' ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safePath) : '');
    $limit = max(1, min(500, $limit));
    $offset = ctype_digit($cursor) ? (int) $cursor : 0;

    if (!is_dir($target)) {
        return ['mode' => 'local', 'path' => $safePath, 'folders' => [], 'files' => [], 'has_more' => false, 'next_token' => ''];
    }

    $folders = [];
    $files = [];

    $items = scandir($target);
    if (!is_array($items)) {
        return ['mode' => 'local', 'path' => $safePath, 'folders' => [], 'files' => []];
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $target . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            $folders[] = ['name' => $item, 'path' => trim($safePath . '/' . $item, '/')];
            continue;
        }

        if (!is_file($fullPath)) {
            continue;
        }

        $mtime = filemtime($fullPath);
        $files[] = [
            'name' => $item,
            'relative_path' => trim($safePath . '/' . $item, '/'),
            'size' => filesize($fullPath) ?: 0,
            'size_human' => explore_human_size((int) (filesize($fullPath) ?: 0)),
            'last_modified' => $mtime ? gmdate('c', $mtime) : null,
            'url' => explore_get_public_url(trim($safePath . '/' . $item, '/')),
        ];
    }

    usort($folders, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
    usort($files, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    $filesPage = array_slice($files, $offset, $limit);
    $nextOffset = $offset + count($filesPage);
    $hasMore = $nextOffset < count($files);

    foreach ($filesPage as &$entry) {
        $entry['download_url'] = $entry['url'];
        $entry['meta'] = [
            'name' => $entry['name'],
            'relative_path' => $entry['relative_path'] ?? '',
            'size' => $entry['size'],
            'size_human' => $entry['size_human'],
            'last_modified' => $entry['last_modified'],
            'source' => 'local',
        ];
    }
    unset($entry);

    return [
        'mode' => 'local',
        'path' => $safePath,
        'folders' => $folders,
        'files' => $filesPage,
        'bucket' => '',
        'prefix' => $safePath,
        'is_truncated' => $hasMore,
        'key_count' => count($filesPage),
        'has_more' => $hasMore,
        'next_token' => $hasMore ? (string) $nextOffset : '',
    ];
}

function explore_list_r2(string $path = '', int $maxKeys = 100, string $continuationToken = ''): array
{
    $client = ged_get_r2_client();
    if ($client === null) {
        return explore_list_local($path, $maxKeys, $continuationToken);
    }

    $bucket = explore_detect_bucket();
    if ($bucket === '') {
        return explore_list_local($path, $maxKeys, $continuationToken);
    }

    $safePath = explore_sanitize_path($path);
    $prefix = explore_build_prefix($safePath);
    $maxKeys = max(1, min(500, $maxKeys));

    $params = [
        'Bucket' => $bucket,
        'Prefix' => $prefix,
        'Delimiter' => '/',
        'MaxKeys' => $maxKeys,
    ];

    if ($continuationToken !== '') {
        $params['ContinuationToken'] = $continuationToken;
    }

    try {
        $result = $client->listObjectsV2($params);
    } catch (AwsException $e) {
        return [
            'mode' => 'r2',
            'path' => $safePath,
            'folders' => [],
            'files' => [],
            'has_more' => false,
            'next_token' => '',
            'error' => 'Falha ao listar objetos no R2.',
        ];
    }

    $folders = [];
    foreach ((array) ($result['CommonPrefixes'] ?? []) as $entry) {
        $entryPrefix = (string) ($entry['Prefix'] ?? '');
        if ($entryPrefix === '') {
            continue;
        }

        $trimmed = trim(substr($entryPrefix, strlen($prefix)), '/');
        if ($trimmed === '') {
            continue;
        }

        $folders[] = [
            'name' => $trimmed,
            'path' => trim($safePath . '/' . $trimmed, '/'),
        ];
    }

    $files = [];
    foreach ((array) ($result['Contents'] ?? []) as $entry) {
        $key = (string) ($entry['Key'] ?? '');
        if ($key === '' || $key === $prefix) {
            continue;
        }

        $name = basename($key);
        if ($name === '' || $name === '.') {
            continue;
        }

        $size = (int) ($entry['Size'] ?? 0);
        $lastModified = $entry['LastModified'] ?? null;
        $lastModifiedIso = null;

        if ($lastModified instanceof DateTimeInterface) {
            $lastModifiedIso = $lastModified->format(DATE_ATOM);
        } elseif (is_string($lastModified) && $lastModified !== '') {
            $lastModifiedIso = $lastModified;
        }

        $files[] = [
            'name' => $name,
            'relative_path' => trim($safePath . '/' . $name, '/'),
            'storage_key' => $key,
            'size' => $size,
            'size_human' => explore_human_size($size),
            'last_modified' => $lastModifiedIso,
            'url' => explore_get_public_url(trim($safePath . '/' . $name, '/')),
        ];
    }

    usort($folders, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
    usort($files, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    foreach ($files as &$entry) {
        $entry['download_url'] = $entry['url'];
        $entry['meta'] = [
            'name' => $entry['name'],
            'relative_path' => $entry['relative_path'] ?? '',
            'storage_key' => $entry['storage_key'] ?? '',
            'size' => $entry['size'],
            'size_human' => $entry['size_human'],
            'last_modified' => $entry['last_modified'],
            'source' => 'r2',
        ];
    }
    unset($entry);

    $nextToken = (string) ($result['NextContinuationToken'] ?? '');
    $hasMore = (bool) ($result['IsTruncated'] ?? false);

    return [
        'mode' => 'r2',
        'path' => $safePath,
        'folders' => $folders,
        'files' => $files,
        'bucket' => $bucket,
        'prefix' => $prefix,
        'is_truncated' => $hasMore,
        'key_count' => (int) ($result['KeyCount'] ?? 0),
        'has_more' => $hasMore,
        'next_token' => $nextToken,
    ];
}
