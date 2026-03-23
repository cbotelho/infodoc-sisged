<?php
include '../includes/db.php';

$format = $_GET['format'] ?? 'select';
$term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
$limit = 20;

$conditions = [];
$params = [];

if (isset($_GET['secretaria_id']) && $_GET['secretaria_id'] !== '') {
    $conditions[] = 'field_433 = ?';
    $params[] = $_GET['secretaria_id'];
}

if (isset($_GET['setor_id']) && $_GET['setor_id'] !== '') {
    $conditions[] = 'field_434 = ?';
    $params[] = $_GET['setor_id'];
}

if (isset($_GET['tipo_id']) && $_GET['tipo_id'] !== '') {
    $conditions[] = 'field_436 = ?';
    $params[] = $_GET['tipo_id'];
}

if ($term !== '') {
    $conditions[] = 'field_437 LIKE ?';
    $params[] = '%' . $term . '%';
}

$sql = 'SELECT MAX(id) AS id, field_437 FROM app_entity_41 WHERE field_437 IS NOT NULL AND TRIM(field_437) <> ""';

if (!empty($conditions)) {
    $sql .= ' AND ' . implode(' AND ', $conditions);
}

$sql .= ' GROUP BY field_437 ORDER BY field_437 LIMIT ' . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$results = [];
$options = '';

if ($format !== 'datalist') {
    $options = '<option value="">Selecione o Nº da Caixa/Pasta</option>';
}

while ($row = $stmt->fetch()) {
    $registroId = (int) ($row['id'] ?? 0);
    $numero = trim((string) $row['field_437']);

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
