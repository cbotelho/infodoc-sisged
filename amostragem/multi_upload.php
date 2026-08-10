<?php

declare(strict_types=1);

require_once __DIR__ . '/../ecm/object_storage_helper.php';
require_once __DIR__ . '/relatorio_amostragem/bootstrap.php';

$messages = [];
$errors = [];
$results = [];
$setoresPermitidos = [];

$r2Config = ged_get_r2_config();
$r2Enabled = ged_r2_is_enabled();
$syncUploadEnabled = ged_sync_r2_upload_enabled();

function is_ajax_request(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function get_current_tenant_slug(): string
{
    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');

    if (strpos($host, ',') !== false) {
        $parts = explode(',', $host);
        $host = trim($parts[0]);
    }

    $host = trim($host);

    if (strpos($host, ':') !== false) {
        $host = explode(':', $host, 2)[0];
    }

    $host = strtolower($host);

    if ($host === '') {
        return 'gea';
    }

    if (strpos($host, 'cipemac') !== false) {
        return 'cipemac';
    }

    if (preg_match('/^([a-z0-9-]+)\.infodocsisged\.com\.br$/i', $host, $m)) {
        return strtolower($m[1]);
    }

    return 'gea';
}

function normalize_upload_files_array(array $input): array
{
    $normalized = [];

    if (!isset($input['name']) || !is_array($input['name'])) {
        return $normalized;
    }

    foreach ($input['name'] as $i => $name) {
        $normalized[] = [
            'name' => (string) ($name ?? ''),
            'type' => (string) ($input['type'][$i] ?? ''),
            'tmp_name' => (string) ($input['tmp_name'][$i] ?? ''),
            'error' => (int) ($input['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($input['size'][$i] ?? 0),
        ];
    }

    return $normalized;
}

function upload_error_message(int $code): string
{
    $map = [
        UPLOAD_ERR_INI_SIZE => 'Arquivo excede upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE => 'Arquivo excede MAX_FILE_SIZE do formulario.',
        UPLOAD_ERR_PARTIAL => 'Upload parcial do arquivo.',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretorio temporario ausente no servidor.',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco.',
        UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensao do PHP.',
    ];

    return $map[$code] ?? ('Erro de upload (codigo ' . $code . ').');
}

function normalize_storage_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    return str_replace('#', '_', $name);
}

function count_pdf_pages(string $filePath): int
{
    $content = @file_get_contents($filePath);
    if ($content === false || $content === '') {
        return 0;
    }

    $count = preg_match_all('/\/Type\s*\/Page([^s]|$)/', $content, $matches);
    return max(0, (int) $count);
}

function build_public_arquivo_value(string $interessado): string
{
    $tenant = get_current_tenant_slug();

    return 'https://' . $tenant . '.infodocsisged.com.br/upload/' . rawurlencode($interessado) . '.pdf';
}

function normalize_decimal_9_3(float $value): string
{
    $rounded = round($value, 3);
    if ($rounded < 0) {
        $rounded = 0.0;
    }

    return number_format($rounded, 3, '.', '');
}

function normalize_movement_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

    if (!$date instanceof DateTimeImmutable || $hasErrors || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date->format('Y-m-d');
}

function upsert_app_zproducao(PDO $pdo, string $setor, string $interessado, int $paginas, string $dataRegistro, string $arquivo): int
{
    $updateSql = 'UPDATE app_zproducao SET paginas = :paginas, data_registro = :data_registro, arquivo = :arquivo WHERE setor = :setor AND interessado = :interessado';
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->bindValue(':paginas', $paginas, PDO::PARAM_INT);
    $updateStmt->bindValue(':data_registro', $dataRegistro);
    $updateStmt->bindValue(':arquivo', $arquivo);
    $updateStmt->bindValue(':setor', $setor);
    $updateStmt->bindValue(':interessado', $interessado);
    $updateStmt->execute();

    if ($updateStmt->rowCount() > 0) {
        return (int) $updateStmt->rowCount();
    }

    $insertSql = 'INSERT INTO app_zproducao (setor, interessado, paginas, data_registro, arquivo) VALUES (:setor, :interessado, :paginas, :data_registro, :arquivo)';
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->bindValue(':setor', $setor);
    $insertStmt->bindValue(':interessado', $interessado);
    $insertStmt->bindValue(':paginas', $paginas, PDO::PARAM_INT);
    $insertStmt->bindValue(':data_registro', $dataRegistro);
    $insertStmt->bindValue(':arquivo', $arquivo);
    $insertStmt->execute();

    return (int) $insertStmt->rowCount();
}

function add_pages_to_app_ztotal(PDO $pdo, string $setor, int $paginas): void
{
    $selectSql = 'SELECT id, tproducao FROM app_ztotal WHERE setor = :setor AND data_inicial IS NULL AND data_final IS NULL ORDER BY id DESC LIMIT 1';
    $selectStmt = $pdo->prepare($selectSql);
    $selectStmt->bindValue(':setor', $setor);
    $selectStmt->execute();
    $row = $selectStmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $atual = is_numeric((string) ($row['tproducao'] ?? '0')) ? (float) $row['tproducao'] : 0.0;
        $novoTotal = normalize_decimal_9_3($atual + $paginas);

        $updateSql = 'UPDATE app_ztotal SET tproducao = :tproducao WHERE id = :id';
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->bindValue(':tproducao', $novoTotal);
        $updateStmt->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
        $updateStmt->execute();
        return;
    }

    $insertSql = 'INSERT INTO app_ztotal (setor, data_inicial, data_final, tproducao) VALUES (:setor, NULL, NULL, :tproducao)';
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->bindValue(':setor', $setor);
    $insertStmt->bindValue(':tproducao', normalize_decimal_9_3((float) $paginas));
    $insertStmt->execute();
}

function fetch_setores_from_app_ztotal(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT DISTINCT setor FROM app_ztotal WHERE TRIM(COALESCE(setor, "")) <> "" ORDER BY setor ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $setores = [];
    foreach ($rows as $row) {
        $setor = trim((string) ($row['setor'] ?? ''));
        if ($setor === '') {
            continue;
        }
        $setores[$setor] = true;
    }

    return array_keys($setores);
}

function r2_object_exists(string $objectKey): bool
{
    if (!ged_r2_is_enabled()) {
        return false;
    }

    $client = ged_get_r2_client();
    if ($client === null) {
        return false;
    }

    $config = ged_get_r2_config();

    try {
        $client->headObject([
            'Bucket' => (string) $config['bucket'],
            'Key' => $objectKey,
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$tenant = get_current_tenant_slug();
$baseUrl = 'https://' . $tenant . '.infodocsisged.com.br/upload/';

$pdo = null;
try {
    $pdo = create_report_pdo($tenant);
    $setoresPermitidos = fetch_setores_from_app_ztotal($pdo);
    if ($setoresPermitidos === []) {
        $errors[] = 'Nenhum setor encontrado na tabela app_ztotal.';
    }
} catch (Throwable $e) {
    $errors[] = 'Falha ao carregar setores da tabela app_ztotal: ' . $e->getMessage();
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $setorSelecionado = trim((string) ($_POST['setor'] ?? ''));
    $dataMovimento = normalize_movement_date($_POST['data_movimento'] ?? null);

    if ($setorSelecionado === '' || !in_array($setorSelecionado, $setoresPermitidos, true)) {
        $errors[] = 'Selecione um setor válido da tabela app_ztotal.';
    }

    if ($dataMovimento === null) {
        $errors[] = 'Informe uma data de movimento valida no formato AAAA-MM-DD.';
    }

    if (!$pdo instanceof PDO) {
        try {
            $pdo = create_report_pdo($tenant);
        } catch (Throwable $e) {
            $errors[] = 'Falha ao conectar no banco para atualizar app_zproducao: ' . $e->getMessage();
        }
    }

    if (!isset($_FILES['files'])) {
        $errors[] = 'Nenhum arquivo foi recebido na requisicao.';
    } else {
        $files = normalize_upload_files_array($_FILES['files']);

        if ($files === []) {
            $errors[] = 'Selecione ao menos um arquivo para envio.';
        } else {
            foreach ($files as $file) {
                $originalName = basename(trim($file['name']));
                $storedName = normalize_storage_name($originalName);

                if ($originalName === '') {
                    $results[] = [
                        'ok' => false,
                        'name' => '(sem nome)',
                        'message' => 'Nome de arquivo invalido.',
                    ];
                    continue;
                }

                if ($storedName === '') {
                    $results[] = [
                        'ok' => false,
                        'name' => $originalName,
                        'message' => 'Nome de arquivo invalido apos normalizacao.',
                    ];
                    continue;
                }

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $results[] = [
                        'ok' => false,
                        'name' => $originalName,
                        'message' => upload_error_message($file['error']),
                    ];
                    continue;
                }

                if (!is_uploaded_file($file['tmp_name'])) {
                    $results[] = [
                        'ok' => false,
                        'name' => $originalName,
                        'message' => 'Arquivo temporario invalido para upload.',
                    ];
                    continue;
                }

                try {
                    // Regra de compatibilidade: normaliza # para _ no storage.
                    $uploadResult = ged_upload_file($file['tmp_name'], $storedName, 'upload');
                    $storageMode = (string) ($uploadResult['mode'] ?? 'unknown');
                    $storagePath = (string) ($uploadResult['path'] ?? '');

                    if ($storageMode !== 'r2') {
                        $results[] = [
                            'ok' => false,
                            'name' => $originalName,
                            'url' => '',
                            'stored_name' => $storedName,
                            'mode' => $storageMode,
                            'path' => $storagePath,
                            'message' => 'Arquivo enviado apenas para fallback local (' . $storageMode . '). R2 indisponivel/desativado neste ambiente.',
                        ];
                        continue;
                    }

                    if ($storagePath === '' || !r2_object_exists($storagePath)) {
                        $results[] = [
                            'ok' => false,
                            'name' => $originalName,
                            'url' => '',
                            'stored_name' => $storedName,
                            'mode' => 'r2',
                            'path' => $storagePath,
                            'message' => 'Upload retornou sucesso, mas o arquivo nao foi localizado no bucket R2 apos confirmacao (headObject).',
                        ];
                        continue;
                    }

                    if (!$pdo instanceof PDO) {
                        throw new RuntimeException('Upload no R2 concluido, mas a conexao com banco nao esta disponivel para atualizar app_zproducao.');
                    }

                    $interessado = pathinfo($storedName, PATHINFO_FILENAME);
                    if ($interessado === '') {
                        throw new RuntimeException('Nao foi possivel derivar o interessado a partir do nome do arquivo.');
                    }

                    $paginas = count_pdf_pages($file['tmp_name']);
                    if ($paginas < 0) {
                        $paginas = 0;
                    }

                    if ($dataMovimento === null) {
                        throw new RuntimeException('Data de movimento invalida.');
                    }

                    $dataRegistro = $dataMovimento;
                    $arquivoValue = build_public_arquivo_value($interessado);

                    $updatedRows = upsert_app_zproducao(
                        $pdo,
                        $setorSelecionado,
                        $interessado,
                        $paginas,
                        $dataRegistro,
                        $arquivoValue
                    );
                    add_pages_to_app_ztotal($pdo, $setorSelecionado, $paginas);

                    $publicUrl = $baseUrl . rawurlencode($storedName);

                    $results[] = [
                        'ok' => true,
                        'name' => $originalName,
                        'stored_name' => $storedName,
                        'url' => $publicUrl,
                        'mode' => $storageMode,
                        'path' => $storagePath,
                        'message' => 'Enviado com sucesso. app_zproducao atualizado: ' . $updatedRows . ' registro(s); app_ztotal somado com ' . $paginas . ' pagina(s).' . ($paginas === 0 ? ' Contagem de paginas indisponivel para este PDF, gravado como 0.' : ''),
                    ];
                } catch (Throwable $e) {
                    $results[] = [
                        'ok' => false,
                        'name' => $originalName,
                        'stored_name' => $storedName,
                        'mode' => 'error',
                        'message' => 'Falha no envio para storage: ' . $e->getMessage(),
                    ];
                }
            }

            $successCount = count(array_filter($results, static fn(array $r): bool => $r['ok'] === true));
            $failCount = count($results) - $successCount;
            $summary = [
                'processed' => count($results),
                'successCount' => $successCount,
                'failCount' => $failCount,
                'message' => 'Processados: ' . count($results) . ' arquivo(s) | Sucesso: ' . $successCount . ' | Falha: ' . $failCount,
            ];

            if (is_ajax_request()) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'ok' => $failCount === 0,
                    'summary' => $summary,
                    'results' => $results,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                exit;
            }

            $messages[] = $summary['message'];
        }
    }

    if (is_ajax_request() && $errors !== []) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'errors' => $errors,
            'results' => $results,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Multi Upload - Amostragem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h1 class="h4 mb-3">Multi Upload - Amostragem</h1>
            <p class="text-muted mb-1">Destino base:</p>
            <p class="mb-3"><code><?= h($baseUrl) ?></code></p>
            <p class="small text-muted">Regra aplicada: o nome do arquivo e mantido exatamente como enviado.</p>

            <?php if (!$r2Enabled || !$syncUploadEnabled): ?>
                <div class="alert alert-warning">
                    <div><strong>Atencao:</strong> upload sincrono para R2 esta desabilitado/incompleto neste ambiente.</div>
                    <div>r2_enabled: <code><?= $r2Enabled ? '1' : '0' ?></code> | sync_enabled: <code><?= $syncUploadEnabled ? '1' : '0' ?></code> | bucket: <code><?= h((string) ($r2Config['bucket'] ?? '')) ?></code> | prefixo: <code><?= h((string) ($r2Config['object_prefix'] ?? '')) ?></code></div>
                </div>
            <?php endif; ?>

            <div class="mb-3 p-2 border rounded bg-white">
                <div class="fw-bold text-success">Quantidade de arquivos selecionados: <span id="selected-count">0</span></div>
            </div>

            <div id="upload-alerts">
                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><?= h($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($messages !== []): ?>
                    <div class="alert alert-info">
                        <?php foreach ($messages as $message): ?>
                            <div><?= h($message) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <form id="upload-form" method="post" enctype="multipart/form-data" class="mb-3">
                <div class="mb-3">
                    <label for="setor" class="form-label">Setor</label>
                    <select id="setor" name="setor" class="form-select">
                        <?php if ($setoresPermitidos !== []): ?>
                            <?php foreach ($setoresPermitidos as $setor): ?>
                                <option value="<?= h($setor) ?>" <?= (($_POST['setor'] ?? '') === $setor) ? 'selected' : '' ?>><?= h($setor) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>(Sem setores disponíveis em app_ztotal)</option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">Setor carregado da tabela app_ztotal. O upload atualiza app_zproducao e soma páginas em app_ztotal.</div>
                </div>

                <div class="mb-3">
                    <label for="data_movimento" class="form-label">Data do movimento</label>
                    <input
                        id="data_movimento"
                        name="data_movimento"
                        type="date"
                        class="form-control"
                        value="<?= h((string) ($_POST['data_movimento'] ?? '')) ?>"
                        required
                    >
                    <div class="form-text">Informe a data do movimento que sera gravada em app_zproducao.data_registro.</div>
                </div>

                <div class="mb-3">
                    <label for="files" class="form-label">Arquivos</label>
                    <input id="files" name="files[]" type="file" class="form-control" multiple required>
                    <div class="form-text">Selecione os arquivos ja nomeados conforme o campo arquivo (ex.: NOME.pdf). O envio e feito um por um para evitar erro 413.</div>
                </div>
                <div id="upload-progress-wrapper" class="d-none mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted" id="upload-progress-label">Aguardando envio...</span>
                        <span class="small text-muted" id="upload-progress-percent">0%</span>
                    </div>
                    <div class="progress" style="height: 22px;">
                        <div id="upload-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </div>
                <button id="upload-submit" type="submit" class="btn btn-primary">Enviar arquivos</button>
            </form>

            <div class="mb-3" id="selected-files-wrapper" style="display:none;">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 48%;">Nome do Arquivo</th>
                            <th style="width: 32%;">Progresso</th>
                            <th style="width: 20%;">Sucesso ou Insucesso</th>
                        </tr>
                        </thead>
                        <tbody id="selected-files-body"></tbody>
                    </table>
                </div>
            </div>

            <div id="upload-result"></div>

            <?php if ($results !== []): ?>
                <div class="table-responsive" id="server-results-table">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Status</th>
                            <th>Arquivo</th>
                            <th>Storage</th>
                            <th>Mensagem</th>
                            <th>URL destino</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td>
                                    <?php if ($result['ok']): ?>
                                        <span class="badge text-bg-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger">ERRO</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string) $result['name']) ?></td>
                                <td>
                                    <?php $mode = (string) ($result['mode'] ?? '-'); ?>
                                    <?php if ($mode === 'r2'): ?>
                                        <span class="badge text-bg-success">r2</span>
                                    <?php elseif ($mode === 'local' || $mode === 'local-buffer'): ?>
                                        <span class="badge text-bg-warning"><?= h($mode) ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary"><?= h($mode) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string) $result['message']) ?></td>
                                <td>
                                    <?php if (!empty($result['url'])): ?>
                                        <code><?= h((string) $result['url']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
    const form = document.getElementById('upload-form');
    const resultContainer = document.getElementById('upload-result');
    const alertsContainer = document.getElementById('upload-alerts');
    const progressWrapper = document.getElementById('upload-progress-wrapper');
    const progressBar = document.getElementById('upload-progress-bar');
    const progressLabel = document.getElementById('upload-progress-label');
    const progressPercent = document.getElementById('upload-progress-percent');
    const submitButton = document.getElementById('upload-submit');
    const fileInput = document.getElementById('files');
    const selectedCount = document.getElementById('selected-count');
    const selectedFilesWrapper = document.getElementById('selected-files-wrapper');
    const selectedFilesBody = document.getElementById('selected-files-body');
    const setorInput = document.getElementById('setor');
    const movementDateInput = document.getElementById('data_movimento');

    if (!form || !window.XMLHttpRequest) {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setProgress(percent, label) {
        const safePercent = Math.max(0, Math.min(100, Math.round(percent)));
        progressWrapper.classList.remove('d-none');
        progressBar.style.width = safePercent + '%';
        progressBar.setAttribute('aria-valuenow', String(safePercent));
        progressBar.textContent = safePercent + '%';
        progressPercent.textContent = safePercent + '%';
        if (label) {
            progressLabel.textContent = label;
        }
        if (safePercent >= 100) {
            progressBar.classList.remove('progress-bar-animated');
        } else {
            progressBar.classList.add('progress-bar-animated');
        }
    }

    function renderAlert(type, lines) {
        const items = Array.isArray(lines) ? lines : [lines];
        return '<div class="alert alert-' + type + '">' + items.map(function (line) {
            return '<div>' + escapeHtml(line) + '</div>';
        }).join('') + '</div>';
    }

    function renderResults(results) {
        if (!Array.isArray(results) || results.length === 0) {
            resultContainer.innerHTML = '';
            return;
        }

        const rows = results.map(function (result) {
            const status = result.ok ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">ERRO</span>';
            let storage = '<span class="badge text-bg-secondary">-</span>';
            if (result.mode === 'r2') {
                storage = '<span class="badge text-bg-success">r2</span>';
            } else if (result.mode === 'local' || result.mode === 'local-buffer') {
                storage = '<span class="badge text-bg-warning">' + escapeHtml(result.mode) + '</span>';
            } else if (result.mode) {
                storage = '<span class="badge text-bg-secondary">' + escapeHtml(result.mode) + '</span>';
            }
            const url = result.url ? '<code>' + escapeHtml(result.url) + '</code>' : '<span class="text-muted">-</span>';
            return '<tr>' +
                '<td>' + status + '</td>' +
                '<td>' + escapeHtml(result.name || '') + '</td>' +
                '<td>' + storage + '</td>' +
                '<td>' + escapeHtml(result.message || '') + '</td>' +
                '<td>' + url + '</td>' +
            '</tr>';
        }).join('');

        resultContainer.innerHTML = [
            '<div class="table-responsive mt-3">',
            '<table class="table table-sm table-striped align-middle">',
            '<thead><tr><th>Status</th><th>Arquivo</th><th>Storage</th><th>Mensagem</th><th>URL destino</th></tr></thead>',
            '<tbody>',
            rows,
            '</tbody></table></div>'
        ].join('');
    }

    function fileRowsTemplate(files) {
        return files.map(function (file, index) {
            const safeName = escapeHtml(file.name || '');
            return '' +
                '<tr id="file-row-' + index + '">' +
                    '<td style="word-break: break-word; white-space: normal;">' + safeName + '</td>' +
                    '<td>' +
                        '<div class="progress" style="height: 20px;">' +
                            '<div id="file-progress-' + index + '" class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>' +
                        '</div>' +
                    '</td>' +
                    '<td id="file-status-' + index + '"><span class="badge bg-secondary">Aguardando</span></td>' +
                '</tr>';
        }).join('');
    }

    function setRowProgress(index, percent, label, isFinal) {
        const progressBar = document.getElementById('file-progress-' + index);
        if (!progressBar) {
            return;
        }

        const safePercent = Math.max(0, Math.min(100, Math.round(percent)));
        progressBar.style.width = safePercent + '%';
        progressBar.setAttribute('aria-valuenow', String(safePercent));
        progressBar.textContent = label || (safePercent + '%');

        if (isFinal) {
            progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
        }
    }

    function setRowStatus(index, ok, message) {
        const statusCell = document.getElementById('file-status-' + index);
        const progressBar = document.getElementById('file-progress-' + index);

        if (!statusCell) {
            return;
        }

        if (ok) {
            statusCell.innerHTML = '<div class="w-100 text-center py-1 px-2 rounded" style="background:#198754;color:#fff;font-weight:700;">' + escapeHtml(message || 'Carregado com sucesso!') + '</div>';
            if (progressBar) {
                progressBar.classList.remove('bg-info', 'bg-danger');
                progressBar.classList.add('bg-success');
                progressBar.textContent = '100%';
            }
            return;
        }

        statusCell.innerHTML = '<div class="w-100 text-center py-1 px-2 rounded" style="background:#dc3545;color:#000;font-weight:700;">' + escapeHtml(message || 'Erro ao Enviar o Arquivo!') + '</div>';
        if (progressBar) {
            progressBar.classList.remove('bg-info', 'bg-success');
            progressBar.classList.add('bg-danger');
        }
    }

    function getSelectedFiles() {
        return Array.prototype.slice.call(fileInput.files || []);
    }

    function updateSelectedFilesView(files) {
        selectedCount.textContent = String(files.length);
        if (!files.length) {
            selectedFilesWrapper.style.display = 'none';
            selectedFilesBody.innerHTML = '';
            return;
        }

        selectedFilesBody.innerHTML = fileRowsTemplate(files);
        selectedFilesWrapper.style.display = 'block';
    }

    function createFormDataForFile(file) {
        const formData = new FormData();
        formData.append('files[]', file, file.name);
        if (setorInput && setorInput.value) {
            formData.append('setor', setorInput.value);
        }
        if (movementDateInput && movementDateInput.value) {
            formData.append('data_movimento', movementDateInput.value);
        }
        return formData;
    }

    function uploadSingleFile(file, index, totalCount) {
        return new Promise(function (resolve) {
            const xhr = new XMLHttpRequest();
            const formData = createFormDataForFile(file);

            xhr.open('POST', form.action || window.location.href, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.responseType = 'json';

            xhr.upload.onprogress = function (event) {
                if (!event.lengthComputable || event.total <= 0) {
                    setRowProgress(index, 0, '0%');
                    setProgress(0, 'Enviando arquivo ' + (index + 1) + ' de ' + totalCount + '...');
                    return;
                }

                const percent = (event.loaded / event.total) * 100;
                setRowProgress(index, percent, Math.round(percent) + '%');
                setProgress(((index + percent / 100) / totalCount) * 100, 'Enviando arquivo ' + (index + 1) + ' de ' + totalCount + '...');
            };

            xhr.onload = function () {
                const response = xhr.response;

                if (!(xhr.status >= 200 && xhr.status < 300)) {
                    const message = 'Erro ao Enviar o Arquivo! HTTP ' + xhr.status;
                    setRowProgress(index, 100, 'Erro', true);
                    setRowStatus(index, false, message);
                    resolve({ ok: false, name: file.name, message: message, mode: 'http-error' });
                    return;
                }

                if (!response || typeof response !== 'object' || !Array.isArray(response.results) || response.results.length === 0) {
                    const message = 'Resposta invalida do servidor (JSON ausente ou incompleto).';
                    setRowProgress(index, 100, 'Erro', true);
                    setRowStatus(index, false, message);
                    resolve({ ok: false, name: file.name, message: message, mode: 'invalid-json' });
                    return;
                }

                const apiResult = response.results[0] || {};
                const ok = apiResult.ok === true;

                if (ok) {
                    setRowProgress(index, 100, '100%', true);
                    setRowStatus(index, true, 'Carregado com sucesso!');
                    resolve({
                        ok: true,
                        name: apiResult.name || file.name,
                        stored_name: apiResult.stored_name || '',
                        mode: apiResult.mode || '',
                        path: apiResult.path || '',
                        url: apiResult.url || '',
                        message: apiResult.message || 'Carregado com sucesso!'
                    });
                    return;
                }

                const message = (response && response.errors && response.errors[0]) || apiResult.message || 'Erro ao Enviar o Arquivo!';
                setRowProgress(index, 100, 'Erro', true);
                setRowStatus(index, false, message);
                resolve({
                    ok: false,
                    name: apiResult.name || file.name,
                    stored_name: apiResult.stored_name || '',
                    mode: apiResult.mode || '',
                    path: apiResult.path || '',
                    url: apiResult.url || '',
                    message: message
                });
            };

            xhr.onerror = function () {
                const message = 'Erro ao Enviar o Arquivo!';
                setRowProgress(index, 100, 'Erro', true);
                setRowStatus(index, false, message);
                resolve({ ok: false, name: file.name, message: message });
            };

            xhr.send(formData);
        });
    }

    async function uploadAllFiles(files) {
        const results = [];

        for (let index = 0; index < files.length; index += 1) {
            const file = files[index];
            setRowStatus(index, false, 'Enviando...');
            setRowProgress(index, 1, '1%');
            const result = await uploadSingleFile(file, index, files.length);
            results.push(result);
        }

        return results;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const files = getSelectedFiles();

        updateSelectedFilesView(files);

        if (!files.length) {
            alertsContainer.innerHTML = renderAlert('danger', 'Selecione ao menos um arquivo para envio.');
            return;
        }

        if (!movementDateInput || !movementDateInput.value) {
            alertsContainer.innerHTML = renderAlert('danger', 'Informe a data do movimento antes de enviar os arquivos.');
            movementDateInput.focus();
            return;
        }

        alertsContainer.innerHTML = '';
        resultContainer.innerHTML = '';
        submitButton.disabled = true;
        fileInput.disabled = true;
        progressWrapper.classList.remove('d-none');
        setProgress(0, 'Preparando envio...');
        setRowProgress(0, 0, '0%');

        uploadAllFiles(files).then(function (results) {
            const successCount = results.filter(function (item) {
                return item.ok;
            }).length;
            const failCount = results.length - successCount;
            const failedFiles = results.filter(function (item) {
                return !item.ok;
            });

            setProgress(100, 'Upload concluido.');

            if (failCount === 0) {
                alertsContainer.innerHTML = renderAlert('success', [
                    'Quantidade de arquivos enviados sem erro: ' + successCount,
                    'Carregado com sucesso!'
                ]);
            } else {
                const lines = [
                    'Quantidade de arquivos enviados sem erro: ' + successCount,
                    'Quantidade de arquivos com erro: ' + failCount
                ];

                failedFiles.forEach(function (item) {
                    lines.push((item.name || '(sem nome)') + ': ' + (item.message || 'Erro ao Enviar o Arquivo!'));
                });

                alertsContainer.innerHTML = renderAlert('danger', lines);
            }

            renderResults(results);
            submitButton.disabled = false;
            fileInput.disabled = false;
        }).catch(function () {
            submitButton.disabled = false;
            fileInput.disabled = false;
            setProgress(0, 'Falha na comunicacao com o servidor.');
            alertsContainer.innerHTML = renderAlert('danger', 'Falha na comunicacao com o servidor.');
        });
    });

    fileInput.addEventListener('change', function () {
        updateSelectedFilesView(getSelectedFiles());
    });
})();
</script>
</body>
</html>
