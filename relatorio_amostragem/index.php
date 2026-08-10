<?php

declare(strict_types=1);

use RelatorioAmostragem\TenantResolver;

require __DIR__ . '/bootstrap.php';

$tenantResolver = new TenantResolver();
$tenant = $tenantResolver->resolve();

$errors = [];
$rows = [];
$setores = [];
$totalRegistros = 0;
$totalPaginas = 0;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$itensPorPagina = 50;
$offset = ($paginaAtual - 1) * $itensPorPagina;

$filtroSetorSelecionado = trim((string) ($_GET['setor'] ?? 'GERAL'));
$filtroBusca = trim((string) ($_GET['q'] ?? ''));
$filtroDataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$filtroDataFim = trim((string) ($_GET['data_fim'] ?? ''));
$setoresPermitidos = ['SEFAZ - RECEITA', 'SEFAZ - TESOURO'];

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

try {
    $pdo = create_report_pdo($tenant);

    $setoresStmt = $pdo->query('SELECT DISTINCT setor FROM app_zproducao WHERE TRIM(COALESCE(setor, "")) <> "" ORDER BY setor ASC');
    $setoresBanco = array_values(array_filter(array_map(static fn(array $row): string => trim((string) ($row['setor'] ?? '')), $setoresStmt->fetchAll(PDO::FETCH_ASSOC))));
    $setores = array_values(array_intersect($setoresPermitidos, $setoresBanco));

    if ($filtroSetorSelecionado !== 'GERAL' && !in_array($filtroSetorSelecionado, $setoresPermitidos, true)) {
        $filtroSetorSelecionado = 'GERAL';
    }

    $filtroSetor = $filtroSetorSelecionado === 'GERAL' ? '' : $filtroSetorSelecionado;

    $where = [];
    $params = [];

    if ($filtroSetor !== '') {
        $where[] = 'setor = :setor';
        $params[':setor'] = $filtroSetor;
    }

    if ($filtroBusca !== '') {
        $where[] = '(interessado LIKE :busca OR arquivo LIKE :busca)';
        $params[':busca'] = '%' . $filtroBusca . '%';
    }

    if ($filtroDataInicio !== '') {
        $where[] = 'data_registro >= :data_inicio';
        $params[':data_inicio'] = $filtroDataInicio;
    }

    if ($filtroDataFim !== '') {
        $where[] = 'data_registro <= :data_fim';
        $params[':data_fim'] = $filtroDataFim;
    }

    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

    $countSql = 'SELECT COUNT(*) FROM app_zproducao ' . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRegistros = (int) $countStmt->fetchColumn();

    $totalPaginas = max(1, (int) ceil($totalRegistros / $itensPorPagina));
    $paginaAtual = min($paginaAtual, $totalPaginas);
    $offset = ($paginaAtual - 1) * $itensPorPagina;

    $dataSql = <<<SQL
        SELECT id, setor, interessado, paginas, data_registro, arquivo
        FROM app_zproducao
        {$whereSql}
        ORDER BY data_registro DESC, id DESC
        LIMIT :limit OFFSET :offset
    SQL;

    $dataStmt = $pdo->prepare($dataSql);
    foreach ($params as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }
    $dataStmt->bindValue(':limit', $itensPorPagina, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    $statsStmt = $pdo->query('SELECT COUNT(*) AS total, COALESCE(SUM(paginas), 0) AS paginas_total, COUNT(DISTINCT setor) AS setores_total FROM app_zproducao');
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $errors[] = 'Nao foi possivel carregar a tabela app_zproducao: ' . $e->getMessage();
    $stats = [];
}

$totalGeral = (int) ($stats['total'] ?? 0);
$paginasTotalGeral = (int) ($stats['paginas_total'] ?? 0);
$setoresTotalGeral = (int) ($stats['setores_total'] ?? 0);

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
    <title>Amostragem - app_zproducao</title>
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
                <h1>Registros da tabela app_zproducao</h1>
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
            <div class="col-12 col-md-4 col-lg-3">
                <label for="setor" class="form-label fw-semibold">Setor</label>
                <select class="form-select" id="setor" name="setor">
                    <option value="GERAL" <?= $filtroSetorSelecionado === 'GERAL' ? 'selected' : '' ?>>GERAL</option>
                    <?php foreach ($setores as $setor): ?>
                        <option value="<?= $h($setor) ?>" <?= $filtroSetorSelecionado === $setor ? 'selected' : '' ?>><?= $h($setor) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label fw-semibold">Busca</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= $h($filtroBusca) ?>" placeholder="Interessado ou arquivo">
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <label for="data_inicio" class="form-label fw-semibold">Data inicial</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= $h($filtroDataInicio) ?>">
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <label for="data_fim" class="form-label fw-semibold">Data final</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= $h($filtroDataFim) ?>">
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100" style="background: var(--brand); border-color: var(--brand);">Filtrar</button>
                <a class="btn btn-outline-secondary" href="?">Limpar</a>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-card p-3">
                <div class="stat-label">Registros visíveis</div>
                <div class="stat-value mt-2"><?= $h((string) $totalRegistros) ?></div>
                <div class="muted mt-2">Mostrando <?= $h((string) $inicioAtual) ?> a <?= $h((string) $fimAtual) ?> de <?= $h((string) $totalRegistros) ?>.</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card p-3">
                <div class="stat-label">Total de páginas</div>
                <div class="stat-value mt-2"><?= $h((string) $paginasTotalGeral) ?></div>
                <div class="muted mt-2">Soma acumulada do campo paginas em app_zproducao.</div>
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
                <h2 class="h5 mb-1">Últimos registros</h2>
                <div class="muted">Ordenados por data_registro e id em ordem decrescente.</div>
            </div>
            <div class="muted">Página <?= $h((string) $paginaAtual) ?> de <?= $h((string) $totalPaginas) ?></div>
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
                                <?= $arquivo !== '' ? $h($arquivo) : '<span class="text-muted">-</span>' ?>
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
</div>
</body>
</html>