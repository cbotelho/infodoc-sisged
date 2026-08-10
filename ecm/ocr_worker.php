<?php

require_once __DIR__ . '/ocr_queue_common.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Somente CLI\n";
    exit(1);
}

$opts = getopt('', ['batch::', 'loop::', 'sleep::', 'batch-token::', 'drain::']);
$batch = isset($opts['batch']) ? (int) $opts['batch'] : 20;
$loop = isset($opts['loop']) ? (int) $opts['loop'] : 1;
$sleepSeconds = isset($opts['sleep']) ? (int) $opts['sleep'] : 10;
$batchToken = isset($opts['batch-token']) ? trim((string) $opts['batch-token']) : '';
$drain = isset($opts['drain']) ? filter_var($opts['drain'], FILTER_VALIDATE_BOOLEAN) : false;

$batch = max(1, min(500, $batch));
$loop = max(1, $loop);
$sleepSeconds = max(0, $sleepSeconds);

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Conexao com banco indisponivel no worker OCR.');
    }

    ocr_ensure_queue_table($pdo);

    $cycle = 0;
    while (true) {
        $cycle++;
        $summary = ocr_process_batch($pdo, $batch, $batchToken !== '' ? $batchToken : null);
        $stats = ocr_queue_stats($pdo);

        echo '[OCR] ciclo=' . $cycle
            . ' claimed=' . (int) $summary['claimed']
            . ' done=' . (int) $summary['done']
            . ' failed=' . (int) $summary['failed']
            . ' queued=' . (int) $stats['queued']
            . ' processing=' . (int) $stats['processing']
            . ' total_done=' . (int) $stats['done']
            . ' total_failed=' . (int) $stats['failed']
            . PHP_EOL;

        if (!empty($summary['errors'])) {
            foreach ($summary['errors'] as $error) {
                echo '[OCR][ERR] ' . $error . PHP_EOL;
            }
        }

        if ($drain) {
            $pending = $batchToken !== '' ? ocr_batch_pending_count($pdo, $batchToken) : ((int) $stats['queued'] + (int) $stats['processing']);
            if ($pending <= 0) {
                break;
            }
            if ($sleepSeconds > 0) {
                sleep($sleepSeconds);
            }
            continue;
        }

        if ($cycle >= $loop) {
            break;
        }

        if ($sleepSeconds > 0) {
            sleep($sleepSeconds);
        }
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[OCR][FATAL] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
