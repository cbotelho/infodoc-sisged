<?php

declare(strict_types=1);

use RelatorioAmostragem\TenantResolver;

require __DIR__ . '/bootstrap.php';

$tenantResolver = new TenantResolver();
$tenant = $tenantResolver->resolve();

$errors = [];
$notices = [];
$rows = [];
$setores = [];
$periodos = [];

$filtroSetor = trim((string) ($_REQUEST['setor'] ?? ''));
$filtroPeriodo = trim((string) ($_REQUEST['periodo'] ?? ''));
$action = trim((string) ($_POST['action'] ?? ''));
$requestAction = trim((string) ($_REQUEST['action'] ?? ''));
$jobToken = trim((string) ($_REQUEST['job'] ?? ''));
$runJob = ((string) ($_REQUEST['run'] ?? '')) === '1';
$filtroPagina = (int) ($_REQUEST['pagina'] ?? 1);

$jobProgress = null;

const DOWNLOAD_JOB_BATCH_SIZE = 25;
const DOWNLOAD_JOB_MAX_SECONDS = 12;
const DOWNLOAD_GRID_PER_PAGE = 50;

$totalRows = 0;
$totalPages = 1;
$currentPage = max(1, $filtroPagina);
$currentOffset = 0;

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

function normalizeSectorTokenDownload(string $value): string
{
    $upper = strtoupper(trim($value));
    $upper = str_replace([' ', '-', '_'], '', $upper);
    return $upper;
}

function buildArchiveUrlDownload(string $tenant, string $arquivo): string
{
    $arquivo = trim($arquivo);

    if ($arquivo === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $arquivo)) {
        return $arquivo;
    }

    $baseUrl = strtolower(trim($tenant)) === 'cipemac'
        ? 'https://cipemac.infodocsisged.com.br'
        : 'https://gea.infodocsisged.com.br';

    $normalized = ltrim(str_replace('\\', '/', $arquivo), '/');

    if ($normalized === '') {
        return '';
    }

    if (str_starts_with($normalized, 'upload/')) {
        return rtrim($baseUrl, '/') . '/' . $normalized;
    }

    return rtrim($baseUrl, '/') . '/upload/' . $normalized;
}

function fetchRemoteBinary(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'infodoc-download-massa/1.0',
            ]);

            $content = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($content !== false && $code >= 200 && $code < 300) {
                return $content;
            }
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'follow_location' => 1,
            'header' => "User-Agent: infodoc-download-massa/1.0\r\n",
        ],
    ]);

    $content = @file_get_contents($url, false, $context);
    return $content === false ? null : $content;
}

function sanitizeZipName(string $rawName, int $fallbackId): string
{
    $name = trim($rawName);
    if ($name === '') {
        $name = 'arquivo_' . $fallbackId;
    }

    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: ('arquivo_' . $fallbackId);

    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'arquivo_' . $fallbackId;
    }

    return $name;
}

function buildQueryDownload(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }

    return http_build_query($query);
}

function getJobsBaseDir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'download_massa_jobs';

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function generateJobToken(): string
{
    return bin2hex(random_bytes(16));
}

function getJobStatePath(string $token): string
{
    return getJobsBaseDir() . DIRECTORY_SEPARATOR . 'job_' . $token . '.json';
}

function loadJobState(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }

    $path = getJobStatePath($token);
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $state = json_decode($raw, true);

    return is_array($state) ? $state : null;
}

function saveJobState(string $token, array $state): void
{
    $path = getJobStatePath($token);
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function deleteJobState(string $token): void
{
    $path = getJobStatePath($token);
    if (is_file($path)) {
        @unlink($path);
    }
}

function resolveLocalArchivePath(string $arquivo): ?string
{
    $arquivo = trim($arquivo);
    if ($arquivo === '' || preg_match('#^https?://#i', $arquivo)) {
        return null;
    }

    $normalized = ltrim(str_replace('\\', '/', $arquivo), '/');
    if (str_starts_with($normalized, 'upload/')) {
        $normalized = substr($normalized, 7);
    }

    $baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'upload');
    if ($baseDir === false) {
        return null;
    }

    $candidate = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $real = realpath($candidate);

    if ($real === false || !is_file($real)) {
        return null;
    }

    if (strpos($real, $baseDir) !== 0) {
        return null;
    }

    return $real;
}

function fetchArchiveBinary(string $tenant, string $arquivo): ?string
{
    $localPath = resolveLocalArchivePath($arquivo);
    if ($localPath !== null) {
        $content = @file_get_contents($localPath);
        if ($content !== false) {
            return $content;
        }
    }

    $url = buildArchiveUrlDownload($tenant, $arquivo);
    if ($url === '') {
        return null;
    }

    return fetchRemoteBinary($url);
}

try {
    $pdo = create_report_pdo($tenant);

    $setoresStmt = $pdo->query('SELECT DISTINCT setor FROM app_ztotal WHERE TRIM(COALESCE(setor, "")) <> "" ORDER BY setor ASC');
    $setores = array_values(array_filter(array_map(static fn(array $row): string => trim((string) ($row['setor'] ?? '')), $setoresStmt->fetchAll(PDO::FETCH_ASSOC))));

    if ($setores === []) {
        $errors[] = 'Nenhum setor encontrado na tabela app_ztotal.';
    }

    if ($filtroSetor === '' || !in_array($filtroSetor, $setores, true)) {
        $filtroSetor = $setores[0] ?? '';
    }

    if ($filtroSetor !== '') {
        $periodosStmt = $pdo->prepare(
            'SELECT DISTINCT data_inicial, data_final
             FROM app_ztotal
             WHERE setor = :setor
                 AND data_inicial IS NOT NULL
                 AND data_final IS NOT NULL
             ORDER BY data_final DESC, data_inicial DESC'
        );
        $periodosStmt->bindValue(':setor', $filtroSetor);
        $periodosStmt->execute();
        $periodos = $periodosStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($periodos === []) {
        $errors[] = 'Nao ha periodos cadastrados em app_ztotal para o setor selecionado.';
    }

    $periodoKeys = [];
    foreach ($periodos as $periodo) {
        $di = trim((string) ($periodo['data_inicial'] ?? ''));
        $df = trim((string) ($periodo['data_final'] ?? ''));
        if ($di !== '' && $df !== '') {
            $periodoKeys[] = $di . '|' . $df;
        }
    }

    if ($filtroPeriodo === '' || !in_array($filtroPeriodo, $periodoKeys, true)) {
        $filtroPeriodo = $periodoKeys[0] ?? '';
    }

    $dataInicio = '';
    $dataFim = '';

    if ($filtroPeriodo !== '') {
        [$dataInicio, $dataFim] = array_pad(explode('|', $filtroPeriodo, 2), 2, '');
    }

    if ($filtroSetor !== '' && $dataInicio !== '' && $dataFim !== '') {
        $countSql = <<<SQL
            SELECT COUNT(*)
            FROM app_zproducao
            WHERE REPLACE(REPLACE(REPLACE(UPPER(TRIM(setor)), ' ', ''), '-', ''), '_', '') = :setor_norm
                AND data_registro >= :data_inicio
                AND data_registro <= :data_fim
                AND TRIM(COALESCE(arquivo, '')) <> ''
        SQL;

        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindValue(':setor_norm', normalizeSectorTokenDownload($filtroSetor));
        $countStmt->bindValue(':data_inicio', $dataInicio);
        $countStmt->bindValue(':data_fim', $dataFim);
        $countStmt->execute();
        $totalRows = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / DOWNLOAD_GRID_PER_PAGE));
        $currentPage = min(max(1, $currentPage), $totalPages);
        $currentOffset = ($currentPage - 1) * DOWNLOAD_GRID_PER_PAGE;

        $limit = DOWNLOAD_GRID_PER_PAGE;

        $sql = <<<SQL
            SELECT id, setor, interessado, paginas, data_registro, arquivo
            FROM app_zproducao
            WHERE REPLACE(REPLACE(REPLACE(UPPER(TRIM(setor)), ' ', ''), '-', ''), '_', '') = :setor_norm
                AND data_registro >= :data_inicio
                AND data_registro <= :data_fim
                AND TRIM(COALESCE(arquivo, '')) <> ''
            ORDER BY data_registro DESC, id DESC
            LIMIT {$currentOffset}, {$limit}
        SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':setor_norm', normalizeSectorTokenDownload($filtroSetor));
        $stmt->bindValue(':data_inicio', $dataInicio);
        $stmt->bindValue(':data_fim', $dataFim);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($action === 'download_selected') {
        $selectedIds = array_values(array_unique(array_map('intval', (array) ($_POST['selected'] ?? []))));

        if ($selectedIds === []) {
            $errors[] = 'Selecione ao menos um arquivo para baixar.';
        } else {
            $jobToken = generateJobToken();
            $zipPath = getJobsBaseDir() . DIRECTORY_SEPARATOR . 'zip_' . $jobToken . '.zip';

            $state = [
                'token' => $jobToken,
                'tenant' => $tenant,
                'setor' => $filtroSetor,
                'periodo' => $filtroPeriodo,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'selected_ids' => $selectedIds,
                'cursor' => 0,
                'added' => 0,
                'failed' => 0,
                'name_count' => [],
                'zip_path' => $zipPath,
                'status' => 'running',
                'created_at' => date(DATE_ATOM),
                'finished_at' => null,
            ];

            saveJobState($jobToken, $state);

            header('Location: download_massa.php?' . buildQueryDownload([
                'setor' => $filtroSetor,
                'periodo' => $filtroPeriodo,
                'job' => $jobToken,
                'run' => 1,
            ]));
            exit;
        }
    }

    if ($requestAction === 'download_single') {
        $singleId = (int) ($_GET['id'] ?? 0);

        if ($singleId <= 0) {
            throw new RuntimeException('ID invalido para download individual.');
        }

        $singleRow = null;
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $singleId) {
                $singleRow = $row;
                break;
            }
        }

        if ($singleRow === null) {
            throw new RuntimeException('Arquivo nao encontrado no filtro atual para download individual.');
        }

        $arquivoOriginal = trim((string) ($singleRow['arquivo'] ?? ''));
        $downloadUrl = buildArchiveUrlDownload($tenant, $arquivoOriginal);

        if ($downloadUrl === '') {
            throw new RuntimeException('Link de arquivo invalido para download individual.');
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Location: ' . $downloadUrl);
        exit;
    }

    if ($action === '' && $requestAction === 'download_ready' && $jobToken !== '') {
        $state = loadJobState($jobToken);

        if ($state === null || ($state['status'] ?? '') !== 'done') {
            throw new RuntimeException('Arquivo ZIP ainda nao esta pronto ou expirou.');
        }

        $zipPath = (string) ($state['zip_path'] ?? '');
        if ($zipPath === '' || !is_file($zipPath)) {
            deleteJobState($jobToken);
            throw new RuntimeException('Arquivo ZIP nao encontrado para download.');
        }

        $downloadName = sprintf(
            'download_massa_%s_%s_%s.zip',
            preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($state['setor'] ?? 'setor')),
            str_replace('-', '', (string) ($state['data_inicio'] ?? '')),
            str_replace('-', '', (string) ($state['data_fim'] ?? ''))
        );

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string) filesize($zipPath));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        readfile($zipPath);
        @unlink($zipPath);
        deleteJobState($jobToken);
        exit;
    }

    if ($jobToken !== '') {
        $state = loadJobState($jobToken);
        if ($state !== null) {
            $jobProgress = $state;
        }
    }

    if ($runJob && $jobProgress !== null && ($jobProgress['status'] ?? '') === 'running') {
        $rowById = [];
        foreach ($rows as $row) {
            $rowById[(int) ($row['id'] ?? 0)] = $row;
        }

        $zipPath = (string) ($jobProgress['zip_path'] ?? '');
        if ($zipPath === '') {
            throw new RuntimeException('Caminho do ZIP invalido no job.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Nao foi possivel abrir o ZIP para processamento em lote.');
        }

        $selectedIds = array_values(array_map('intval', (array) ($jobProgress['selected_ids'] ?? [])));
        $cursor = (int) ($jobProgress['cursor'] ?? 0);
        $added = (int) ($jobProgress['added'] ?? 0);
        $failed = (int) ($jobProgress['failed'] ?? 0);
        $nameCount = is_array($jobProgress['name_count'] ?? null) ? $jobProgress['name_count'] : [];

        $processedInThisBatch = 0;
        $startedAt = microtime(true);

        while ($cursor < count($selectedIds) && $processedInThisBatch < DOWNLOAD_JOB_BATCH_SIZE) {
            if ((microtime(true) - $startedAt) >= DOWNLOAD_JOB_MAX_SECONDS) {
                break;
            }

            $id = $selectedIds[$cursor];

            if (!isset($rowById[$id])) {
                $failed++;
                $cursor++;
                $processedInThisBatch++;
                continue;
            }

            $row = $rowById[$id];
            $arquivoOriginal = trim((string) ($row['arquivo'] ?? ''));

            $binary = fetchArchiveBinary($tenant, $arquivoOriginal);
            if ($binary === null) {
                $failed++;
                $cursor++;
                $processedInThisBatch++;
                continue;
            }

            $baseName = basename($arquivoOriginal);
            if ($baseName === '' || $baseName === '/' || $baseName === '.') {
                $arquivoUrl = buildArchiveUrlDownload($tenant, $arquivoOriginal);
                $baseName = basename((string) parse_url($arquivoUrl, PHP_URL_PATH));
            }

            $safeName = sanitizeZipName($baseName, $id);
            $count = ((int) ($nameCount[$safeName] ?? 0)) + 1;
            $nameCount[$safeName] = $count;

            if ($count > 1) {
                $dotPos = strrpos($safeName, '.');
                if ($dotPos !== false) {
                    $safeName = substr($safeName, 0, $dotPos) . '_' . $count . substr($safeName, $dotPos);
                } else {
                    $safeName .= '_' . $count;
                }
            }

            $zip->addFromString($safeName, $binary);
            $added++;
            $cursor++;
            $processedInThisBatch++;
        }

        $zip->close();

        $jobProgress['cursor'] = $cursor;
        $jobProgress['added'] = $added;
        $jobProgress['failed'] = $failed;
        $jobProgress['name_count'] = $nameCount;

        if ($cursor >= count($selectedIds)) {
            $jobProgress['status'] = 'done';
            $jobProgress['finished_at'] = date(DATE_ATOM);
            if ($added === 0) {
                $jobProgress['status'] = 'failed';
                $errors[] = 'Nenhum arquivo pôde ser adicionado ao ZIP. Verifique os links em app_zproducao.';
            }
        }

        saveJobState($jobToken, $jobProgress);

        if (($jobProgress['status'] ?? '') === 'running') {
            header('Location: download_massa.php?' . buildQueryDownload([
                'setor' => $filtroSetor,
                'periodo' => $filtroPeriodo,
                'job' => $jobToken,
                'run' => 1,
            ]));
            exit;
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Falha ao processar download em massa: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download em Massa - app_zproducao</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Download em Massa de Arquivos</h1>
            <div class="text-muted small">Fonte: app_zproducao | Períodos e setores: app_ztotal</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="index.php?<?= $h(buildQueryDownload(['job' => null, 'run' => null, 'action' => null])) ?>">Voltar ao Relatório</a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= $h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($notices !== []): ?>
        <div class="alert alert-info">
            <ul class="mb-0">
                <?php foreach ($notices as $notice): ?>
                    <li><?= $h($notice) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($jobProgress !== null): ?>
        <?php
            $jobStatus = (string) ($jobProgress['status'] ?? 'running');
            $jobTotal = count((array) ($jobProgress['selected_ids'] ?? []));
            $jobCursor = (int) ($jobProgress['cursor'] ?? 0);
            $jobAdded = (int) ($jobProgress['added'] ?? 0);
            $jobFailed = (int) ($jobProgress['failed'] ?? 0);
            $jobPercent = $jobTotal > 0 ? (int) floor(($jobCursor / $jobTotal) * 100) : 0;
        ?>

        <?php if ($jobStatus === 'running'): ?>
            <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold">Gerando ZIP em lotes para evitar timeout de gateway...</div>
                    <div class="small">Processados <?= $h((string) $jobCursor) ?> de <?= $h((string) $jobTotal) ?> | Adicionados: <?= $h((string) $jobAdded) ?> | Falhas: <?= $h((string) $jobFailed) ?> | <?= $h((string) $jobPercent) ?>%</div>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="download_massa.php?<?= $h(http_build_query(['setor' => $filtroSetor, 'periodo' => $filtroPeriodo, 'job' => $jobToken, 'run' => 1])) ?>">Continuar processamento</a>
            </div>
        <?php elseif ($jobStatus === 'done'): ?>
            <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold">ZIP concluído com sucesso.</div>
                    <div class="small">Total selecionado: <?= $h((string) $jobTotal) ?> | Adicionados: <?= $h((string) $jobAdded) ?> | Falhas: <?= $h((string) $jobFailed) ?></div>
                </div>
                <a class="btn btn-success btn-sm" href="download_massa.php?<?= $h(http_build_query(['setor' => $filtroSetor, 'periodo' => $filtroPeriodo, 'job' => $jobToken, 'action' => 'download_ready'])) ?>">Baixar ZIP pronto</a>
            </div>
        <?php elseif ($jobStatus === 'failed'): ?>
            <div class="alert alert-danger">
                <div class="fw-semibold">Falha na geração do ZIP.</div>
                <div class="small">Nenhum arquivo válido pôde ser adicionado. Revise os registros do período selecionado.</div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="setor" class="form-label fw-semibold">Setor</label>
                    <select id="setor" name="setor" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($setores as $setor): ?>
                            <option value="<?= $h($setor) ?>" <?= $setor === $filtroSetor ? 'selected' : '' ?>><?= $h($setor) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label for="periodo" class="form-label fw-semibold">Período (app_ztotal)</label>
                    <select id="periodo" name="periodo" class="form-select">
                        <?php foreach ($periodos as $periodo): ?>
                            <?php $di = trim((string) ($periodo['data_inicial'] ?? '')); ?>
                            <?php $df = trim((string) ($periodo['data_final'] ?? '')); ?>
                            <?php $key = $di . '|' . $df; ?>
                            <option value="<?= $h($key) ?>" <?= $key === $filtroPeriodo ? 'selected' : '' ?>>
                                <?= $h($di) ?> até <?= $h($df) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Listar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fw-semibold">Arquivos encontrados: <?= $h((string) $totalRows) ?></div>
                <div class="small text-muted">Página <?= $h((string) $currentPage) ?> de <?= $h((string) $totalPages) ?> | Exibindo <?= $h((string) ($totalRows > 0 ? ($currentOffset + 1) : 0)) ?>-<?= $h((string) min($totalRows, $currentOffset + count($rows))) ?></div>
            </div>

            <form method="post">
                <input type="hidden" name="action" value="download_selected">
                <input type="hidden" name="setor" value="<?= $h($filtroSetor) ?>">
                <input type="hidden" name="periodo" value="<?= $h($filtroPeriodo) ?>">
                <input type="hidden" name="pagina" value="<?= $h((string) $currentPage) ?>">

                <div class="mb-2 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="select-all">Selecionar todos</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-all">Limpar seleção</button>
                    <button type="submit" class="btn btn-success btn-sm">Baixar selecionados (.zip)</button>
                    <button type="button" class="btn btn-primary btn-sm" id="download-individual">Baixar selecionados (individual)</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th style="width: 38px;"></th>
                            <th style="width: 90px;">ID</th>
                            <th>Setor</th>
                            <th>Interessado</th>
                            <th style="width: 100px;">Páginas</th>
                            <th style="width: 130px;">Data</th>
                            <th>Arquivo</th>
                            <th style="width: 170px;">Status download</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows === []): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Nenhum arquivo encontrado para os parâmetros selecionados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php $id = (int) ($row['id'] ?? 0); ?>
                                <?php $arquivo = trim((string) ($row['arquivo'] ?? '')); ?>
                                <?php $arquivoUrl = buildArchiveUrlDownload($tenant, $arquivo); ?>
                                <tr>
                                    <td><input class="form-check-input item-check" type="checkbox" name="selected[]" value="<?= $h((string) $id) ?>"></td>
                                    <td><?= $h((string) $id) ?></td>
                                    <td><?= $h((string) ($row['setor'] ?? '')) ?></td>
                                    <td><?= $h((string) ($row['interessado'] ?? '')) ?></td>
                                    <td><?= $h((string) ($row['paginas'] ?? '')) ?></td>
                                    <td><?= $h((string) ($row['data_registro'] ?? '')) ?></td>
                                    <td>
                                        <?php if ($arquivoUrl !== ''): ?>
                                            <a href="<?= $h($arquivoUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $h($arquivo) ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-secondary dl-status" data-id="<?= $h((string) $id) ?>">Pendente</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Paginacao de arquivos" class="mt-3">
                        <ul class="pagination pagination-sm mb-0 flex-wrap">
                            <?php $prevPage = max(1, $currentPage - 1); ?>
                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="download_massa.php?<?= $h(buildQueryDownload(['setor' => $filtroSetor, 'periodo' => $filtroPeriodo, 'pagina' => $prevPage, 'job' => null, 'run' => null, 'action' => null])) ?>">Anterior</a>
                            </li>

                            <?php
                                $startPage = max(1, $currentPage - 3);
                                $endPage = min($totalPages, $currentPage + 3);
                                if (($endPage - $startPage) < 6) {
                                    $startPage = max(1, $endPage - 6);
                                    $endPage = min($totalPages, $startPage + 6);
                                }
                            ?>

                            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="download_massa.php?<?= $h(buildQueryDownload(['setor' => $filtroSetor, 'periodo' => $filtroPeriodo, 'pagina' => $p, 'job' => null, 'run' => null, 'action' => null])) ?>"><?= $h((string) $p) ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php $nextPage = min($totalPages, $currentPage + 1); ?>
                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="download_massa.php?<?= $h(buildQueryDownload(['setor' => $filtroSetor, 'periodo' => $filtroPeriodo, 'pagina' => $nextPage, 'job' => null, 'run' => null, 'action' => null])) ?>">Próxima</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
    (() => {
        const selectAllBtn = document.getElementById('select-all');
        const clearAllBtn = document.getElementById('clear-all');
        const downloadIndividualBtn = document.getElementById('download-individual');
        const checks = Array.from(document.querySelectorAll('.item-check'));
        const statusMap = new Map(
            Array.from(document.querySelectorAll('.dl-status')).map((el) => [String(el.dataset.id || ''), el])
        );

        const queryParams = new URLSearchParams(window.location.search);
        const setor = queryParams.get('setor') || '';
        const periodo = queryParams.get('periodo') || '';

        const setStatus = (id, type, text) => {
            const badge = statusMap.get(String(id));
            if (!badge) {
                return;
            }

            badge.className = 'badge dl-status';
            if (type === 'success') {
                badge.classList.add('text-bg-success');
            } else if (type === 'warning') {
                badge.classList.add('text-bg-warning');
            } else if (type === 'danger') {
                badge.classList.add('text-bg-danger');
            } else {
                badge.classList.add('text-bg-secondary');
            }

            badge.textContent = text;
        };

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', () => {
                checks.forEach((item) => {
                    item.checked = true;
                });
            });
        }

        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', () => {
                checks.forEach((item) => {
                    item.checked = false;
                });
            });
        }

        if (downloadIndividualBtn) {
            downloadIndividualBtn.addEventListener('click', async () => {
                const selected = checks.filter((item) => item.checked).map((item) => String(item.value || '').trim()).filter(Boolean);

                if (selected.length === 0) {
                    alert('Selecione ao menos um arquivo para download individual.');
                    return;
                }

                downloadIndividualBtn.disabled = true;
                downloadIndividualBtn.textContent = 'Baixando...';

                let frame = document.getElementById('download-individual-frame');
                if (!frame) {
                    frame = document.createElement('iframe');
                    frame.id = 'download-individual-frame';
                    frame.style.display = 'none';
                    document.body.appendChild(frame);
                }

                for (let i = 0; i < selected.length; i++) {
                    const id = selected[i];
                    setStatus(id, 'warning', 'Solicitando...');

                    const params = new URLSearchParams({
                        setor,
                        periodo,
                        action: 'download_single',
                        id,
                        t: String(Date.now()),
                    });

                    frame.src = `download_massa.php?${params.toString()}`;

                    await new Promise((resolve) => {
                        setTimeout(resolve, 450);
                    });

                    setStatus(id, 'success', 'Solicitado');
                }

                downloadIndividualBtn.disabled = false;
                downloadIndividualBtn.textContent = 'Baixar selecionados (individual)';
            });
        }
    })();
</script>
</body>
</html>
