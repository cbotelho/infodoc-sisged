<?php

/* CRM - INFODOC-SISGED | 2026 https://ecmsolucoes.com */

chdir(substr(__DIR__, 0, -5));

define('IS_CRON', true);

require('config/server.php');

$lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'infodoc_runtime_cleanup.lock';
$lockHandle = fopen($lockFile, 'c');

if ($lockHandle === false)
{
    fwrite(STDERR, "Unable to create cleanup lock file\n");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB))
{
    fwrite(STDOUT, "Cleanup already running\n");
    fclose($lockHandle);
    exit(0);
}

function cleanup_env_bool($name, $default)
{
    $value = getenv($name);

    if ($value === false || $value === '')
    {
        return $default;
    }

    return in_array(strtolower((string) $value), array('1', 'true', 'yes', 'on'), true);
}

function cleanup_env_int($name, $default, $minimum = 0)
{
    $value = getenv($name);

    if ($value === false || $value === '')
    {
        return $default;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_INT);

    if ($parsed === false)
    {
        return $default;
    }

    return max($minimum, (int) $parsed);
}

function cleanup_should_keep_name($path)
{
    $name = basename($path);

    return in_array($name, array('.', '..', '.gitkeep', 'index.html', 'index.php', '.htaccess'), true);
}

function cleanup_is_older_than($path, $cutoffTimestamp)
{
    $mtime = @filemtime($path);

    if ($mtime === false)
    {
        return false;
    }

    return $mtime < $cutoffTimestamp;
}

function cleanup_remove_tree($path, &$stats, $dryRun)
{
    if (is_link($path) || is_file($path))
    {
        if ($dryRun)
        {
            $stats['files_removed']++;
            return true;
        }

        if (@unlink($path))
        {
            $stats['files_removed']++;
            return true;
        }

        return false;
    }

    if (!is_dir($path))
    {
        return false;
    }

    $items = @scandir($path);

    if ($items === false)
    {
        return false;
    }

    foreach ($items as $item)
    {
        if ($item === '.' || $item === '..')
        {
            continue;
        }

        $childPath = $path . DIRECTORY_SEPARATOR . $item;

        if (!cleanup_remove_tree($childPath, $stats, $dryRun))
        {
            return false;
        }
    }

    if ($dryRun)
    {
        $stats['directories_removed']++;
        return true;
    }

    if (@rmdir($path))
    {
        $stats['directories_removed']++;
        return true;
    }

    return false;
}

function cleanup_purge_directory($label, $baseDir, $retentionDays, $mode, $dryRun)
{
    $stats = array(
        'label' => $label,
        'base_dir' => $baseDir,
        'retention_days' => $retentionDays,
        'files_removed' => 0,
        'directories_removed' => 0,
        'files_truncated' => 0,
        'skipped' => 0,
        'errors' => 0,
    );

    if (!is_dir($baseDir))
    {
        $stats['skipped']++;
        return $stats;
    }

    $cutoffTimestamp = time() - ($retentionDays * 86400);
    $items = @scandir($baseDir);

    if ($items === false)
    {
        $stats['errors']++;
        return $stats;
    }

    foreach ($items as $item)
    {
        if ($item === '.' || $item === '..')
        {
            continue;
        }

        $path = $baseDir . DIRECTORY_SEPARATOR . $item;

        if (cleanup_should_keep_name($path))
        {
            $stats['skipped']++;
            continue;
        }

        if (!cleanup_is_older_than($path, $cutoffTimestamp))
        {
            $stats['skipped']++;
            continue;
        }

        if ($mode === 'truncate')
        {
            if (!is_file($path))
            {
                $stats['skipped']++;
                continue;
            }

            if ($dryRun)
            {
                $stats['files_truncated']++;
                continue;
            }

            if (@file_put_contents($path, '') !== false)
            {
                $stats['files_truncated']++;
            }
            else
            {
                $stats['errors']++;
            }

            continue;
        }

        if (!cleanup_remove_tree($path, $stats, $dryRun))
        {
            $stats['errors']++;
        }
    }

    return $stats;
}

$enabled = cleanup_env_bool('RUNTIME_CLEANUP_ENABLED', true);

if (!$enabled)
{
    fwrite(STDOUT, "Runtime cleanup disabled\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(0);
}

$dryRun = cleanup_env_bool('RUNTIME_CLEANUP_DRY_RUN', false);
$logRetentionDays = cleanup_env_int('RUNTIME_CLEANUP_LOG_RETENTION_DAYS', 7, 1);
$tmpRetentionDays = cleanup_env_int('RUNTIME_CLEANUP_TMP_RETENTION_DAYS', 7, 1);
$cacheRetentionDays = cleanup_env_int('RUNTIME_CLEANUP_CACHE_RETENTION_DAYS', 7, 1);

$results = array(
    cleanup_purge_directory('log', DIR_FS_CATALOG . 'log', $logRetentionDays, 'truncate', $dryRun),
    cleanup_purge_directory('tmp', DIR_FS_TMP, $tmpRetentionDays, 'delete', $dryRun),
    cleanup_purge_directory('cache', DIR_FS_CACHE, $cacheRetentionDays, 'delete', $dryRun),
);

foreach ($results as $result)
{
    fwrite(
        STDOUT,
        sprintf(
            "%s: retention=%sd truncated=%d files_removed=%d directories_removed=%d skipped=%d errors=%d dry_run=%s\n",
            $result['label'],
            $result['retention_days'],
            $result['files_truncated'],
            $result['files_removed'],
            $result['directories_removed'],
            $result['skipped'],
            $result['errors'],
            $dryRun ? 'true' : 'false'
        )
    );
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
