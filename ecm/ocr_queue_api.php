<?php

require_once __DIR__ . '/ocr_queue_common.php';

header('Content-Type: application/json; charset=utf-8');

function ocr_api_fail($message, $status = 400)
{
    http_response_code((int) $status);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Conexao com banco indisponivel.');
    }

    ocr_ensure_queue_table($pdo);
    ocr_requeue_stale_processing_jobs($pdo);
    $action = isset($_REQUEST['action']) ? trim((string) $_REQUEST['action']) : '';

    if ($action === 'list_pending') {
        $source = isset($_GET['source']) ? trim((string) $_GET['source']) : 'ged';
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
        $rows = ocr_fetch_pending($pdo, $source, $limit);

        echo json_encode([
            'success' => true,
            'source' => $source,
            'total' => count($rows),
            'items' => $rows,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'enqueue') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            ocr_api_fail('Metodo invalido para enfileirar.', 405);
        }

        $source = isset($_POST['source']) ? trim((string) $_POST['source']) : '';
        $ids = isset($_POST['record_ids']) && is_array($_POST['record_ids']) ? $_POST['record_ids'] : [];
        $createdBy = isset($_POST['created_by']) && trim((string) $_POST['created_by']) !== '' ? (int) $_POST['created_by'] : null;
        $force = filter_var($_POST['force'] ?? '0', FILTER_VALIDATE_BOOLEAN);

        if ($source === '') {
            ocr_api_fail('Informe a origem (ged ou sefaz_rh).');
        }

        if (count($ids) === 0) {
            ocr_api_fail('Nenhum registro selecionado.');
        }

        $resetActiveBatch = !isset($_POST['reset_active_batch'])
            ? true
            : filter_var($_POST['reset_active_batch'], FILTER_VALIDATE_BOOLEAN);

        $resetInfo = [
            'had_active_batch' => false,
            'canceled_rows' => 0,
            'canceled_batch_token' => '',
        ];

        if ($resetActiveBatch) {
            try {
                $resetReason = 'Lote anterior interrompido automaticamente para iniciar novo processamento.';
                $resetInfo = ocr_reset_active_batch_for_source($pdo, $source, $resetReason);
            } catch (Throwable $resetError) {
                error_log('[OCR] Erro ao resetar lote ativo: ' . $resetError->getMessage());
                // Continuar mesmo se falhar, nao bloquear enqueue
                $resetInfo['error'] = $resetError->getMessage();
            }
        }

        $batchToken = isset($_POST['batch_token']) ? trim((string) $_POST['batch_token']) : '';
        if ($batchToken === '') {
            $batchToken = 'ocr_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 12);
        }

        $result = ocr_enqueue_records($pdo, $source, $ids, $createdBy, $force, $batchToken);
        $result['reset'] = $resetInfo;
        $stats = ocr_queue_stats($pdo);

        echo json_encode([
            'success' => true,
            'result' => $result,
            'stats' => $stats,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'batch_progress') {
        $batchToken = isset($_GET['batch_token']) ? trim((string) $_GET['batch_token']) : '';
        $source = isset($_GET['source']) ? trim((string) $_GET['source']) : '';
        if ($batchToken === '') {
            ocr_api_fail('batch_token obrigatorio.');
        }

        ocr_requeue_stale_processing_jobs($pdo, $batchToken, $source);

        $stmt = $pdo->prepare('SELECT status, COUNT(*) AS total FROM app_ocr_queue WHERE batch_token = ? GROUP BY status');
        $stmt->execute([$batchToken]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = [
            'queued' => 0,
            'processing' => 0,
            'done' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            if (isset($stats[$status])) {
                $stats[$status] = (int) $row['total'];
            }
        }

        $total = $stats['queued'] + $stats['processing'] + $stats['done'] + $stats['failed'];
        $finished = $stats['done'] + $stats['failed'];
        $percent = $total > 0 ? (int) floor(($finished * 100) / $total) : 0;

        $processingStmt = $pdo->prepare('SELECT id, source, record_id, file_name, started_at, attempts FROM app_ocr_queue WHERE batch_token = ? AND status = "processing" ORDER BY started_at ASC, id ASC LIMIT 5');
        $processingStmt->execute([$batchToken]);
        $processingItems = $processingStmt->fetchAll(PDO::FETCH_ASSOC);

        $activeBatchToken = '';
        if ($total === 0) {
            $activeBatchToken = $source !== ''
                ? ocr_find_active_batch_token_by_source($pdo, $source)
                : ocr_find_active_batch_token($pdo);
        }

        echo json_encode([
            'success' => true,
            'batch_token' => $batchToken,
            'total' => $total,
            'finished' => $finished,
            'percent' => $percent,
            'stats' => $stats,
            'processing_items' => $processingItems,
            'active_batch_token' => $activeBatchToken,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'queue_stats') {
        $stats = ocr_queue_stats($pdo);

        echo json_encode([
            'success' => true,
            'stats' => $stats,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'recent_jobs') {
        $limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 50;
        $stmt = $pdo->prepare('SELECT id, source, record_id, file_name, status, attempts, error_message, ocr_chars, created_at, updated_at, finished_at FROM app_ocr_queue ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'process_now') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            ocr_api_fail('Metodo invalido para processar.', 405);
        }

        $batch = isset($_POST['batch']) ? (int) $_POST['batch'] : 20;
        $source = isset($_POST['source']) ? trim((string) $_POST['source']) : '';
        $requestedBatchToken = isset($_POST['batch_token']) ? trim((string) $_POST['batch_token']) : '';
        $batchToken = $requestedBatchToken;

        if ($batchToken === '') {
            $batchToken = $source !== ''
                ? ocr_find_active_batch_token_by_source($pdo, $source)
                : ocr_find_active_batch_token($pdo);
        }

        if ($batchToken === '') {
            ocr_api_fail('Nenhum lote ativo encontrado para processar.');
        }

        $pendingForRequested = ocr_batch_pending_count($pdo, $batchToken);
        if ($pendingForRequested <= 0) {
            $autoBatch = $source !== ''
                ? ocr_find_active_batch_token_by_source($pdo, $source)
                : ocr_find_active_batch_token($pdo);
            if ($autoBatch !== '') {
                $batchToken = $autoBatch;
            }
        }

        $pendingForEffective = ocr_batch_pending_count($pdo, $batchToken);
        if ($pendingForEffective <= 0) {
            http_response_code(202);
            echo json_encode([
                'success' => true,
                'queued' => false,
                'already_running' => false,
                'batch_token' => $batchToken,
                'message' => 'Nao ha itens pendentes para este lote.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $runningStmt = $pdo->prepare('SELECT COUNT(*) FROM app_ocr_queue WHERE batch_token = ? AND status = "processing"');
        $runningStmt->execute([$batchToken]);
        $running = (int) $runningStmt->fetchColumn();

        if ($running > 0) {
            http_response_code(202);
            echo json_encode([
                'success' => true,
                'queued' => false,
                'already_running' => true,
                'batch_token' => $batchToken,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $maxWorkers = ocr_get_env_int('OCR_MAX_WORKERS', 2, 1, 6);
        $requestedWorkers = isset($_POST['worker_count']) ? (int) $_POST['worker_count'] : 0;
        if ($requestedWorkers <= 0) {
            $requestedWorkers = 1;
        }
        $workerCount = max(1, min($maxWorkers, $requestedWorkers));

        try {
            $logFiles = [];
            for ($i = 0; $i < $workerCount; $i++) {
                $logFiles[] = ocr_spawn_worker_async($batchToken, $batch, 1);
            }
        } catch (Throwable $spawnError) {
            // Fallback para nao devolver 500 quando houver falha ao iniciar processo em background.
            $summary = ocr_process_batch($pdo, min($batch, 50), $batchToken);
            http_response_code(202);
            echo json_encode([
                'success' => true,
                'queued' => false,
                'already_running' => false,
                'batch_token' => $batchToken,
                'message' => 'Worker em background indisponivel, processamento executado em modo de contingencia.',
                'fallback_summary' => $summary,
                'spawn_error' => $spawnError->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(202);

        echo json_encode([
            'success' => true,
            'queued' => true,
            'requested_batch_token' => $requestedBatchToken,
            'batch_token' => $batchToken,
            'workers_started' => $workerCount,
            'log_file' => $logFiles[0],
            'log_files' => $logFiles,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'cancel_batch') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            ocr_api_fail('Metodo invalido para cancelar lote.', 405);
        }

        $source = isset($_POST['source']) ? trim((string) $_POST['source']) : '';
        $batchToken = isset($_POST['batch_token']) ? trim((string) $_POST['batch_token']) : '';

        if ($batchToken === '' && $source !== '') {
            $batchToken = ocr_find_active_batch_token_by_source($pdo, $source);
        }
        if ($batchToken === '') {
            $batchToken = ocr_find_active_batch_token($pdo);
        }
        if ($batchToken === '') {
            ocr_api_fail('Nenhum lote ativo encontrado para cancelar.', 404);
        }

        $reason = 'Lote cancelado manualmente pelo gerenciador OCR.';
        $canceledRows = ocr_cancel_batch($pdo, $batchToken, $reason, $source !== '' ? $source : null);
        $stats = ocr_queue_stats($pdo);

        echo json_encode([
            'success' => true,
            'batch_token' => $batchToken,
            'canceled_rows' => $canceledRows,
            'stats' => $stats,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    ocr_api_fail('Acao OCR invalida.', 400);
} catch (Throwable $e) {
    ocr_api_fail('Falha na API OCR: ' . $e->getMessage(), 500);
}
