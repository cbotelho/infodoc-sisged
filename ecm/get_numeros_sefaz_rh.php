<?php
include '../includes/db.php';

$format = $_GET['format'] ?? 'select';
$term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
$limit = ($format === 'json' || $term !== '') ? 50 : null;

function fetchNumeroRows(PDO $pdo, array $baseConditions, array $baseParams, string $term, ?int $limit, bool $useTipoFilter): array
{
    $conditions = $baseConditions;
    $params = $baseParams;

    if ($useTipoFilter && isset($_GET['tipo_id']) && $_GET['tipo_id'] !== '') {
        $conditions[] = 'field_526 = ?';
        $params[] = trim((string) $_GET['tipo_id']);
    }

    if ($term !== '') {
        $conditions[] = 'field_527 LIKE ?';
        $params[] = '%' . $term . '%';
    }

    $sql = 'SELECT MAX(id) AS id, field_527 FROM app_entity_48 WHERE field_527 IS NOT NULL AND TRIM(field_527) <> ""';

    if (!empty($conditions)) {
        $sql .= ' AND ' . implode(' AND ', $conditions);
    }

    $sql .= ' GROUP BY field_527 ORDER BY field_527';

    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

$baseConditions = [];
$baseParams = [];

if (isset($_GET['secretaria_id']) && $_GET['secretaria_id'] !== '') {
    $baseConditions[] = 'field_524 = ?';
    $baseParams[] = trim((string) $_GET['secretaria_id']);
}

if (isset($_GET['setor_id']) && $_GET['setor_id'] !== '') {
    $baseConditions[] = 'field_525 = ?';
    $baseParams[] = trim((string) $_GET['setor_id']);
}

$rows = fetchNumeroRows($pdo, $baseConditions, $baseParams, $term, $limit, true);

if (empty($rows) && isset($_GET['tipo_id']) && $_GET['tipo_id'] !== '') {
    $rows = fetchNumeroRows($pdo, $baseConditions, $baseParams, $term, $limit, false);
}

$results = [];
$options = '';

if ($format !== 'datalist') {
    $options = '<option value="">Selecione o Nº da Caixa/Pasta</option>';
}

foreach ($rows as $row) {
    $registroId = (int) ($row['id'] ?? 0);
    $numero = trim((string) ($row['field_527'] ?? ''));

    if ($registroId <= 0 || $numero === '') {
        continue;
    }

    $escapedNumero = htmlspecialchars($numero, ENT_QUOTES, 'UTF-8');

    if ($format === 'json') {
        $results[] = [
            'label' => $numero,
            'value' => $numero,
            'id' => $registroId,
        ];
        continue;
    }

    if ($format === 'datalist') {
        $options .= '<option value="' . $escapedNumero . '"></option>';
        continue;
    }

    $options .= '<option value="' . $escapedNumero . '" data-id="' . $registroId . '">' . $escapedNumero . '</option>';
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

echo $options;
?>