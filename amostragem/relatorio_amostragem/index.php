<?php

declare(strict_types=1);

use RelatorioAmostragem\Export\ExcelExporter;
use RelatorioAmostragem\TenantResolver;

require __DIR__ . '/bootstrap.php';

$tenantResolver = new TenantResolver();
$tenant = $tenantResolver->resolve();

$errors = [];
$rows = [];
$amostraRows = [];
$setores = [];
$totalRegistros = 0;
$totalPaginas = 0;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$itensPorPagina = 50;
$offset = ($paginaAtual - 1) * $itensPorPagina;

$filtroSetorSelecionado = trim((string) ($_GET['setor'] ?? ''));
$filtroPercentual = trim((string) ($_GET['percentual'] ?? '10'));
$filtroDataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$filtroDataFim = trim((string) ($_GET['data_fim'] ?? ''));
$filtroSeed = trim((string) ($_GET['seed'] ?? ''));
$isExportXlsx = strtolower(trim((string) ($_GET['export'] ?? ''))) === 'xlsx';
$setoresPermitidos = [];

if (!ctype_digit($filtroSeed)) {
    $filtroSeed = (string) random_int(1, 2147483647);
}

$seedAmostragem = (int) $filtroSeed;

if (!is_numeric(str_replace(',', '.', $filtroPercentual))) {
    $filtroPercentual = '10';
}

$percentualAmostragem = (float) str_replace(',', '.', $filtroPercentual);
if ($percentualAmostragem < 0.5 || $percentualAmostragem > 100) {
    $percentualAmostragem = 10.0;
}

$filtroPercentual = number_format($percentualAmostragem, 1, '.', '');

$periodoValido = true;
$stats = [];

function normalizeSectorToken(string $value): string
{
    $upper = strtoupper(trim($value));
    $upper = str_replace([' ', '-', '_'], '', $upper);
    return $upper;
}

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

try {
    $pdo = create_report_pdo($tenant);

    $setoresStmt = $pdo->query('SELECT DISTINCT setor FROM app_ztotal WHERE TRIM(COALESCE(setor, "")) <> "" ORDER BY setor ASC');
    $setoresPermitidos = array_values(array_filter(array_map(static fn(array $row): string => trim((string) ($row['setor'] ?? '')), $setoresStmt->fetchAll(PDO::FETCH_ASSOC))));
    $setores = array_values(array_unique(array_merge(['GERAL'], $setoresPermitidos)));

    if ($filtroSetorSelecionado === '' || !in_array($filtroSetorSelecionado, $setores, true)) {
        $filtroSetorSelecionado = 'GERAL';
    }

    $periodoDefaultSql = 'SELECT data_inicial, data_final, tproducao FROM app_ztotal WHERE setor = :setor_total AND data_inicial IS NOT NULL AND data_final IS NOT NULL ORDER BY data_final DESC, data_inicial DESC, id DESC LIMIT 1';
    $periodoDefaultStmt = $pdo->prepare($periodoDefaultSql);
    $periodoDefaultStmt->bindValue(':setor_total', $filtroSetorSelecionado);
    $periodoDefaultStmt->execute();
    $periodoDefault = $periodoDefaultStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $defaultDataInicio = trim((string) ($periodoDefault['data_inicial'] ?? ''));
    $defaultDataFim = trim((string) ($periodoDefault['data_final'] ?? ''));

    if (($defaultDataInicio === '' || $defaultDataFim === '') && $filtroSetorSelecionado !== '') {
        if ($filtroSetorSelecionado === 'GERAL') {
            $periodoBaseStmt = $pdo->prepare('SELECT MIN(data_registro) AS min_data, MAX(data_registro) AS max_data FROM app_zproducao');
        } else {
            $periodoBaseStmt = $pdo->prepare('SELECT MIN(data_registro) AS min_data, MAX(data_registro) AS max_data FROM app_zproducao WHERE REPLACE(REPLACE(REPLACE(UPPER(TRIM(setor)), " ", ""), "-", ""), "_", "") = :setor_norm');
            $periodoBaseStmt->bindValue(':setor_norm', normalizeSectorToken($filtroSetorSelecionado));
        }
        $periodoBaseStmt->execute();
        $periodoBase = $periodoBaseStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if ($defaultDataInicio === '') {
            $defaultDataInicio = trim((string) ($periodoBase['min_data'] ?? ''));
        }
        if ($defaultDataFim === '') {
            $defaultDataFim = trim((string) ($periodoBase['max_data'] ?? ''));
        }
    }

    if ($filtroDataInicio === '' && $defaultDataInicio !== '') {
        $filtroDataInicio = $defaultDataInicio;
    }

    if ($filtroDataFim === '' && $defaultDataFim !== '') {
        $filtroDataFim = $defaultDataFim;
    }

    if ($filtroDataInicio === '' || $filtroDataFim === '') {
        $errors[] = 'Informe obrigatoriamente o período (data inicial e data final) para gerar a amostragem.';
        $periodoValido = false;
    } elseif (strtotime($filtroDataInicio) === false || strtotime($filtroDataFim) === false) {
        $errors[] = 'Período inválido. Verifique as datas informadas.';
        $periodoValido = false;
    } elseif ($filtroDataInicio > $filtroDataFim) {
        $errors[] = 'A data inicial não pode ser maior que a data final.';
        $periodoValido = false;
    }

    $filtroSetor = $filtroSetorSelecionado;

    if ($periodoValido) {
        $where = [];
        $params = [];

        if ($filtroSetor !== '' && $filtroSetor !== 'GERAL') {
            $where[] = 'REPLACE(REPLACE(REPLACE(UPPER(TRIM(setor)), " ", ""), "-", ""), "_", "") = :setor_norm';
            $params[':setor_norm'] = normalizeSectorToken($filtroSetor);
        }

        $where[] = 'data_registro >= :data_inicio';
        $params[':data_inicio'] = $filtroDataInicio;

        $where[] = 'data_registro <= :data_fim';
        $params[':data_fim'] = $filtroDataFim;

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countSql = 'SELECT COUNT(*) FROM app_zproducao ' . $whereSql;
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalRegistrosBase = (int) $countStmt->fetchColumn();

        $totalRegistros = $totalRegistrosBase === 0
            ? 0
            : max(1, (int) ceil($totalRegistrosBase * ($percentualAmostragem / 100)));

        if ($filtroSetor === 'GERAL' && $totalRegistros > 0) {
            $setoresPeriodoSql = <<<SQL
                SELECT setor, COUNT(*) AS qtd
                FROM app_zproducao
                WHERE data_registro >= :data_inicio AND data_registro <= :data_fim
                GROUP BY setor
                ORDER BY setor ASC
            SQL;

            $setoresPeriodoStmt = $pdo->prepare($setoresPeriodoSql);
            $setoresPeriodoStmt->bindValue(':data_inicio', $filtroDataInicio);
            $setoresPeriodoStmt->bindValue(':data_fim', $filtroDataFim);
            $setoresPeriodoStmt->execute();
            $setoresPeriodo = $setoresPeriodoStmt->fetchAll(PDO::FETCH_ASSOC);

            $amostraRows = [];

            if ($setoresPeriodo !== []) {
                $setorCount = count($setoresPeriodo);
                $totalRegistros = max($totalRegistros, $setorCount);

                $distribuicao = [];
                $capacidadeExtraTotal = 0;

                foreach ($setoresPeriodo as $setorInfo) {
                    $setorNome = trim((string) ($setorInfo['setor'] ?? ''));
                    $qtdSetor = (int) ($setorInfo['qtd'] ?? 0);
                    if ($setorNome === '' || $qtdSetor <= 0) {
                        continue;
                    }

                    $distribuicao[$setorNome] = [
                        'qtd' => $qtdSetor,
                        'take' => 1,
                    ];

                    $capacidadeExtraTotal += max(0, $qtdSetor - 1);
                }

                $remaining = $totalRegistros - count($distribuicao);

                if ($remaining > 0 && $capacidadeExtraTotal > 0) {
                    foreach ($distribuicao as $setorNome => $info) {
                        $capacidade = max(0, $info['qtd'] - 1);
                        if ($capacidade === 0) {
                            continue;
                        }

                        $extra = (int) floor(($remaining * $capacidade) / $capacidadeExtraTotal);
                        $extra = min($extra, $capacidade);
                        $distribuicao[$setorNome]['take'] += $extra;
                    }

                    $alocado = 0;
                    foreach ($distribuicao as $info) {
                        $alocado += (int) $info['take'];
                    }

                    $faltante = $totalRegistros - $alocado;

                    if ($faltante > 0) {
                        uasort($distribuicao, static fn(array $a, array $b): int => $b['qtd'] <=> $a['qtd']);
                        while ($faltante > 0) {
                            $adicionou = false;
                            foreach ($distribuicao as $setorNome => $info) {
                                if ($distribuicao[$setorNome]['take'] < $info['qtd']) {
                                    $distribuicao[$setorNome]['take']++;
                                    $faltante--;
                                    $adicionou = true;
                                    if ($faltante <= 0) {
                                        break;
                                    }
                                }
                            }
                            if (!$adicionou) {
                                break;
                            }
                        }
                    }
                }

                $setorSql = <<<SQL
                    SELECT id, setor, interessado, paginas, data_registro, arquivo
                    FROM app_zproducao
                    WHERE setor = :setor
                        AND data_registro >= :data_inicio
                        AND data_registro <= :data_fim
                    ORDER BY RAND(:rand_seed), id DESC
                    LIMIT :limit
                SQL;

                $setorStmt = $pdo->prepare($setorSql);
                $representativeRows = [];
                $remainingRows = [];

                foreach ($distribuicao as $setorNome => $info) {
                    $take = (int) $info['take'];
                    if ($take <= 0) {
                        continue;
                    }

                    $seedSetor = (int) abs(crc32($setorNome . ':' . (string) $seedAmostragem));
                    $setorStmt->bindValue(':setor', $setorNome);
                    $setorStmt->bindValue(':data_inicio', $filtroDataInicio);
                    $setorStmt->bindValue(':data_fim', $filtroDataFim);
                    $setorStmt->bindValue(':rand_seed', $seedSetor, PDO::PARAM_INT);
                    $setorStmt->bindValue(':limit', $take, PDO::PARAM_INT);
                    $setorStmt->execute();

                    $setorRows = $setorStmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($setorRows !== []) {
                        $representativeRows[] = array_shift($setorRows);
                        if ($setorRows !== []) {
                            $remainingRows = array_merge($remainingRows, $setorRows);
                        }
                    }
                }

                if ($remainingRows !== []) {
                    usort(
                        $remainingRows,
                        static function (array $a, array $b) use ($seedAmostragem): int {
                            $keyA = sprintf('%u', crc32(((string) ($a['id'] ?? '')) . '|' . ((string) ($a['setor'] ?? '')) . '|' . (string) $seedAmostragem));
                            $keyB = sprintf('%u', crc32(((string) ($b['id'] ?? '')) . '|' . ((string) ($b['setor'] ?? '')) . '|' . (string) $seedAmostragem));
                            return $keyA <=> $keyB;
                        }
                    );
                }

                $amostraRows = array_merge($representativeRows, $remainingRows);
            }

            $totalRegistros = count($amostraRows);
            $totalPaginas = max(1, (int) ceil(max(1, $totalRegistros) / $itensPorPagina));
            $paginaAtual = min($paginaAtual, $totalPaginas);
            $offset = ($paginaAtual - 1) * $itensPorPagina;

            if ($isExportXlsx) {
                $rows = $amostraRows;
            } else {
                $rows = $totalRegistros === 0
                    ? []
                    : array_slice($amostraRows, $offset, $itensPorPagina);
            }
        } else {
            $totalPaginas = max(1, (int) ceil($totalRegistros / $itensPorPagina));
            $paginaAtual = min($paginaAtual, $totalPaginas);
            $offset = ($paginaAtual - 1) * $itensPorPagina;

            if ($totalRegistros > 0) {
                $dataSql = <<<SQL
                    SELECT id, setor, interessado, paginas, data_registro, arquivo
                    FROM app_zproducao
                    {$whereSql}
                    ORDER BY RAND({$seedAmostragem}), id DESC
                    LIMIT :limit
                SQL;

                $dataStmt = $pdo->prepare($dataSql);
                foreach ($params as $key => $value) {
                    $dataStmt->bindValue($key, $value);
                }
                $dataStmt->bindValue(':limit', $totalRegistros, PDO::PARAM_INT);
                $dataStmt->execute();
                $amostraRows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                if ($isExportXlsx) {
                    $rows = $amostraRows;
                } else {
                    $rows = $totalRegistros === 0
                        ? []
                        : array_slice($amostraRows, $offset, $itensPorPagina);
                }
            }
        }

        if ($isExportXlsx) {
            $filter = new \RelatorioAmostragem\ReportFilter(
                $filtroDataInicio,
                $filtroDataFim,
                $filtroPercentual,
                $filtroSetorSelecionado,
                'xlsx'
            );

            (new ExcelExporter())->export($rows, $filter, $tenant);
            exit;
        }
    }

    $statsStmt = $pdo->query('SELECT COUNT(*) AS total, COUNT(DISTINCT setor) AS setores_total FROM app_zproducao');
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $tproducaoSql = 'SELECT tproducao FROM app_ztotal WHERE setor = :setor_total AND data_inicial = :data_inicio AND data_final = :data_fim ORDER BY data_final DESC, data_inicial DESC, id DESC LIMIT 1';
    $tproducaoStmt = $pdo->prepare($tproducaoSql);
    $tproducaoTotal = null;

    if ($periodoValido) {
        $tproducaoStmt->bindValue(':setor_total', $filtroSetorSelecionado);
        $tproducaoStmt->bindValue(':data_inicio', $filtroDataInicio);
        $tproducaoStmt->bindValue(':data_fim', $filtroDataFim);
        $tproducaoStmt->execute();
        $tproducaoTotal = $tproducaoStmt->fetchColumn();
    }

    if ($tproducaoTotal === false || $tproducaoTotal === null) {
        $tproducaoTotal = $periodoDefault['tproducao'] ?? 0;
    }

    $stats['tproducao_total'] = $tproducaoTotal;
} catch (Throwable $e) {
    $errors[] = 'Nao foi possivel carregar os dados do relatorio (app_zproducao/app_ztotal): ' . $e->getMessage();
    $stats = [];
}

$totalGeral = (int) ($stats['total'] ?? 0);
$paginasTotalRaw = $stats['tproducao_total'] ?? 0;
$paginasTotalGeral = is_numeric((string) $paginasTotalRaw)
    ? rtrim(rtrim(number_format((float) $paginasTotalRaw, 3, '.', ''), '0'), '.')
    : '0';
if ($paginasTotalGeral === '') {
    $paginasTotalGeral = '0';
}
$setoresTotalGeral = (int) ($stats['setores_total'] ?? 0);
$chartCounts = [];
$chartPages = [];
if ($amostraRows !== []) {
    foreach ($amostraRows as $row) {
        $setorNome = trim((string) ($row['setor'] ?? ''));
        if ($setorNome === '') {
            continue;
        }

        if (!isset($chartCounts[$setorNome])) {
            $chartCounts[$setorNome] = 0;
        }

        if (!isset($chartPages[$setorNome])) {
            $chartPages[$setorNome] = 0.0;
        }

        $chartCounts[$setorNome]++;

        $paginasValue = str_replace(',', '.', trim((string) ($row['paginas'] ?? '0')));
        $chartPages[$setorNome] += is_numeric($paginasValue) ? (float) $paginasValue : 0.0;
    }
}

$chartLabels = array_keys($chartCounts);
$chartValues = array_values($chartCounts);
$chartPageValues = [];
foreach ($chartLabels as $label) {
    $chartPageValues[] = $chartPages[$label] ?? 0;
}

$chartPalette = [
    '#0b5cad', '#0d7cc2', '#2bb3ff', '#14b8a6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#64748b',
];
$chartSectorColorMap = [
    'SEFAZ - TESOURO RH' => '#166534',
    'SEFAZ - RECEITA' => '#2563eb',
    'SEFAZ-RECEITA JUPAF' => '#8b5cf6',
    'SEFAZ-RECEITA CERF' => '#f97316',
];

$chartColors = [];
foreach ($chartLabels as $index => $label) {
    $normalizedLabel = strtoupper(normalizeSectorToken($label));
    $matchedColor = null;

    foreach ($chartSectorColorMap as $sectorName => $color) {
        if ($normalizedLabel === strtoupper(normalizeSectorToken($sectorName))) {
            $matchedColor = $color;
            break;
        }
    }

    $chartColors[] = $matchedColor ?? $chartPalette[$index % count($chartPalette)];
}

function buildQuery(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }

    return http_build_query($query);
}

function buildArchiveUrl(string $tenant, string $arquivo): string
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

function formatDate(string $value): string
{
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d/m/Y', $timestamp);
}

function renderPageLink(int $page, string $label, bool $disabled = false, bool $active = false): string
{
    $class = 'page-item';
    if ($disabled) {
        $class .= ' disabled';
    }
    if ($active) {
        $class .= ' active';
    }

    $href = $disabled ? '#' : '?' . buildQuery(['pagina' => $page]);

    return '<li class="' . $class . '"><a class="page-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
}

$inicioAtual = $totalRegistros === 0 ? 0 : (($paginaAtual - 1) * $itensPorPagina + 1);
$fimAtual = min($totalRegistros, $paginaAtual * $itensPorPagina);
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório de Amostragem - app_zproducao</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #0b5cad;
            --brand-dark: #083d73;
            --bg: #eef4fb;
            --ink: #1f2a37;
        }

        body {
            min-height: 100vh;
            background: radial-gradient(circle at top left, #ffffff 0%, #f3f8fe 38%, #e6eff9 100%);
            color: var(--ink);
        }

        .hero {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, #0d7cc2 100%);
            color: #fff;
            border-radius: 20px;
            padding: 1.4rem 1.5rem;
            box-shadow: 0 18px 44px rgba(11, 92, 173, .2);
        }

        .hero h1 {
            font-size: clamp(1.25rem, 2.5vw, 2rem);
            margin: 0;
            font-weight: 800;
        }

        .panel {
            border: 1px solid #dbe6f3;
            background: rgba(255, 255, 255, .92);
            border-radius: 20px;
            box-shadow: 0 12px 38px rgba(27, 50, 81, .08);
            backdrop-filter: blur(8px);
        }

        .stat-card {
            border: 1px solid #dce6f3;
            border-radius: 16px;
            background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
            height: 100%;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-label {
            color: #5f6f82;
            font-size: .88rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .table thead th {
            background: #f2f6fb;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .table-wrap {
            border: 1px solid #d9e4f0;
            border-radius: 16px;
            overflow: auto;
            background: #fff;
            max-height: 68vh;
        }

        .badge-soft {
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .24);
            color: #fff;
        }

        .muted {
            color: #637489;
        }
    </style>
</head>
<body>
<div class="container py-4 py-lg-5">
    <div class="hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
            <div>
                <h1>Relatório de Amostragem</h1>
                <div class="opacity-75">Página vinculada aos arquivos que foram importados na base.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge rounded-pill badge-soft px-3 py-2">Tenant: <?= $h($tenant) ?></span>
                <span class="badge rounded-pill badge-soft px-3 py-2">Total: <?= $h((string) $totalGeral) ?></span>
            </div>
        </div>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <strong>Falha ao carregar os registros.</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= $h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="panel p-3 p-md-4 mb-4">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="seed" value="<?= $h($filtroSeed) ?>">
            <div class="col-12 col-md-4 col-lg-3">
                <label for="setor" class="form-label fw-semibold">Setor</label>
                <select class="form-select" id="setor" name="setor">
                    <?php foreach ($setores as $setor): ?>
                        <option value="<?= $h($setor) ?>" <?= $filtroSetorSelecionado === $setor ? 'selected' : '' ?>><?= $h($setor) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="percentual" class="form-label fw-semibold">Percentual</label>
                <div class="input-group">
                    <input
                        type="number"
                        class="form-control"
                        id="percentual"
                        name="percentual"
                        min="0.5"
                        max="100"
                        step="0.5"
                        value="<?= $h($filtroPercentual) ?>"
                    >
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <label for="data_inicio" class="form-label fw-semibold">Data inicial</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= $h($filtroDataInicio) ?>" required>
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <label for="data_fim" class="form-label fw-semibold">Data final</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= $h($filtroDataFim) ?>" required>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100" style="background: var(--brand); border-color: var(--brand);">Filtrar</button>
                <a class="btn btn-outline-secondary" href="?">Limpar</a>
            </div>
            <div class="col-12">
                <div class="small text-muted">Período obrigatório: informe Data inicial e Data final para gerar a amostragem.</div>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-card p-3">
                <div class="stat-label">Arquivos Enviados</div>
                <div class="stat-value mt-2"><?= $h((string) $totalRegistros) ?></div>
                <div class="muted mt-2">Mostrando <?= $h((string) $inicioAtual) ?> a <?= $h((string) $fimAtual) ?> de <?= $h((string) $totalRegistros) ?>.</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card p-3">
                <div class="stat-label">Total de páginas</div>
                <div class="stat-value mt-2"><?= $h((string) $paginasTotalGeral) ?></div>
                <div class="muted mt-2">Total de Imagens do Período.</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card p-3">
                <div class="stat-label">Setores cadastrados</div>
                <div class="stat-value mt-2"><?= $h((string) $setoresTotalGeral) ?></div>
                <div class="muted mt-2">Agrupamento por setor presente na tabela.</div>
            </div>
        </div>
    </div>

    <div class="panel p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">Relatório de Amostragem Selecionada</h2>
                <div class="muted">Registros Aleatórios da Amostragem</div>
            </div>
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#biModal">Abrir BI</button>
                <a class="btn btn-outline-dark btn-sm" href="download_massa.php?<?= $h(buildQuery(['export' => null, 'pagina' => 1])) ?>">Download em Massa</a>
                <a class="btn btn-success btn-sm" href="?<?= $h(buildQuery(['export' => 'xlsx', 'pagina' => 1])) ?>">Exportar para Excel</a>
                <div class="muted">Página <?= $h((string) $paginaAtual) ?> de <?= $h((string) $totalPaginas) ?></div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Setor</th>
                    <th>Interessado</th>
                    <th style="width: 110px;">Páginas</th>
                    <th style="width: 140px;">Data</th>
                    <th>Arquivo</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Nenhum registro encontrado para os filtros informados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= $h((string) ($row['id'] ?? '')) ?></td>
                            <td><?= $h((string) ($row['setor'] ?? '')) ?></td>
                            <td><?= $h((string) ($row['interessado'] ?? '')) ?></td>
                            <td><?= $h((string) ($row['paginas'] ?? '')) ?></td>
                            <td><?= $h(formatDate((string) ($row['data_registro'] ?? ''))) ?></td>
                            <td>
                                <?php $arquivo = trim((string) ($row['arquivo'] ?? '')); ?>
                                <?php if ($arquivo !== ''): ?>
                                    <?php $arquivoUrl = buildArchiveUrl($tenant, $arquivo); ?>
                                    <?php if ($arquivoUrl !== ''): ?>
                                        <a href="<?= $h($arquivoUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $h($arquivo) ?></a>
                                    <?php else: ?>
                                        <?= $h($arquivo) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <nav class="mt-3" aria-label="Paginação">
            <ul class="pagination mb-0 flex-wrap">
                <?= renderPageLink(max(1, $paginaAtual - 1), 'Anterior', $paginaAtual <= 1) ?>
                <?php
                $inicioBloco = max(1, $paginaAtual - 2);
                $fimBloco = min($totalPaginas, $paginaAtual + 2);
                for ($pagina = $inicioBloco; $pagina <= $fimBloco; $pagina++):
                ?>
                    <?= renderPageLink($pagina, (string) $pagina, false, $pagina === $paginaAtual) ?>
                <?php endfor; ?>
                <?= renderPageLink(min($totalPaginas, $paginaAtual + 1), 'Próxima', $paginaAtual >= $totalPaginas) ?>
            </ul>
        </nav>
    </div>

    <div class="modal fade" id="biModal" tabindex="-1" aria-labelledby="biModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title" id="biModalLabel">BI da Amostragem</h5>
                        <div class="text-muted small">Visualização em tempo real da amostra filtrada por setor.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-12 col-md-4">
                            <label for="chartType" class="form-label fw-semibold">Tipo de gráfico</label>
                            <select class="form-select" id="chartType">
                                <option value="pie">Pizza</option>
                                <option value="bar">Barras</option>
                                <option value="line">Linhas</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="chartMetric" class="form-label fw-semibold">Base do gráfico</label>
                            <select class="form-select" id="chartMetric">
                                <option value="arquivos">Arquivos</option>
                                <option value="paginas">Páginas</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4 text-md-end">
                            <div class="small text-muted mb-1">Legenda: quantidade de registros por setor na amostra atual.</div>
                            <div class="fw-semibold">Total da amostra: <?= $h((string) count($amostraRows)) ?></div>
                        </div>
                    </div>
                    <div style="min-height: 420px;">
                        <canvas id="biChart" height="420"></canvas>
                    </div>
                    <div class="row g-3 mt-3">
                        <?php foreach ($chartLabels as $index => $label): ?>
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="d-inline-block rounded-circle" style="width:12px;height:12px;background:<?= $h($chartColors[$index]) ?>"></span>
                                        <strong><?= $h($label) ?></strong>
                                    </div>
                                    <div class="text-muted small">Quantidade na amostra: <?= $h((string) $chartValues[$index]) ?></div>
                                    <div class="text-muted small">Total de páginas: <?= $h(rtrim(rtrim(number_format((float) ($chartPageValues[$index] ?? 0), 3, '.', ''), '0'), '.')) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($chartLabels === []): ?>
                            <div class="col-12">
                                <div class="alert alert-light border mb-0">Nenhum dado disponível para montar o BI com os filtros informados.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    (() => {
        const chartData = {
            labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            arquivos: <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            paginas: <?= json_encode($chartPageValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            colors: <?= json_encode($chartColors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        };

        const modalElement = document.getElementById('biModal');
        const chartTypeElement = document.getElementById('chartType');
        const chartMetricElement = document.getElementById('chartMetric');
        const chartCanvas = document.getElementById('biChart');
        if (!modalElement || !chartTypeElement || !chartMetricElement || !chartCanvas || chartData.labels.length === 0) {
            return;
        }

        let chartInstance = null;

        const getMetricPayload = () => {
            if (chartMetricElement.value === 'paginas') {
                return {
                    label: 'Páginas na amostra',
                    values: chartData.paginas,
                };
            }

            return {
                label: 'Arquivos na amostra',
                values: chartData.arquivos,
            };
        };

        const buildConfig = (type) => {
            const metric = getMetricPayload();
            const dataset = {
                label: metric.label,
                data: metric.values,
                borderColor: chartData.colors[0] || '#0b5cad',
                backgroundColor: chartData.colors,
                tension: 0.35,
                fill: type === 'line',
            };

            if (type === 'line') {
                dataset.backgroundColor = chartData.colors[0] || '#0b5cad';
                dataset.borderWidth = 3;
                dataset.pointRadius = 4;
                dataset.pointHoverRadius = 6;
            }

            return {
                type,
                data: {
                    labels: chartData.labels,
                    datasets: [dataset],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: type !== 'line',
                            position: 'bottom',
                        },
                        tooltip: {
                            enabled: true,
                            callbacks: {
                                label: (context) => {
                                    const value = context.parsed?.y ?? context.parsed ?? 0;
                                    const numericValue = typeof value === 'number' ? value : Number(value);
                                    const suffix = chartMetricElement.value === 'paginas' ? 'páginas' : 'arquivos';
                                    const formatted = Number.isInteger(numericValue)
                                        ? String(numericValue)
                                        : numericValue.toLocaleString('pt-BR', { maximumFractionDigits: 3 });
                                    return `${context.label}: ${formatted} ${suffix}`;
                                },
                            },
                        },
                    },
                    scales: type === 'pie' ? {} : {
                        x: {
                            ticks: {
                                autoSkip: false,
                                maxRotation: 35,
                                minRotation: 0,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            precision: 0,
                        },
                    },
                },
            };
        };

        const renderChart = () => {
            if (chartInstance) {
                chartInstance.destroy();
            }
            chartInstance = new Chart(chartCanvas, buildConfig(chartTypeElement.value));
        };

        modalElement.addEventListener('shown.bs.modal', renderChart);
        chartTypeElement.addEventListener('change', renderChart);
        chartMetricElement.addEventListener('change', renderChart);
        modalElement.addEventListener('hidden.bs.modal', () => {
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }
        });
    })();
</script>
</body>
</html>