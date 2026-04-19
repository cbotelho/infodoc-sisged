<?php
include '../includes/db.php';

$format = $_GET['format'] ?? 'select';
$term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
$limit = 20;

$conditions = [];
$params = [];

if (isset($_GET['secretaria_id']) && $_GET['secretaria_id'] !== '') {
    $conditions[] = 'field_524 = ?';
    $params[] = trim((string) $_GET['secretaria_id']);
}

if (isset($_GET['setor_id']) && $_GET['setor_id'] !== '') {
    $conditions[] = 'field_525 = ?';
    $params[] = trim((string) $_GET['setor_id']);
}

if (isset($_GET['tipo_id']) && $_GET['tipo_id'] !== '') {
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

$sql .= ' GROUP BY field_527 ORDER BY field_527 LIMIT ' . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$results = [];
$options = '';

if ($format !== 'datalist') {
    $options = '<option value="">Selecione o Nº da Caixa/Pasta</option>';
}

while ($row = $stmt->fetch()) {
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

    $options .= '<option value="' . $escapedNumero . '">' . $escapedNumero . '</option>';
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}

echo $options;
?>