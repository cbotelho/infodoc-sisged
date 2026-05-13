<?php
include '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$source = isset($_GET['source']) ? trim((string) $_GET['source']) : 'sefaz_rh';
$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';

$sources = [
    'ged' => [
        'parent_table' => 'app_entity_41',
        'document_table' => 'app_entity_43',
        'numero_field' => 'field_437',
        'secretaria_field' => 'field_433',
        'setor_field' => 'field_434',
        'tipo_field' => 'field_436',
        'file_field' => 'field_445',
        'fixed_types' => [
            ['id' => '118', 'name' => 'Caixa'],
            ['id' => '117', 'name' => 'Pasta'],
        ],
    ],
    'sefaz_rh' => [
        'parent_table' => 'app_entity_48',
        'document_table' => 'app_entity_49',
        'numero_field' => 'field_527',
        'secretaria_field' => 'field_524',
        'setor_field' => 'field_525',
        'tipo_field' => 'field_526',
        'file_field' => 'field_542',
        'choices_field_id' => 526,
    ],
];

if (!isset($sources[$source])) {
    http_response_code(400);
    echo json_encode(['error' => 'Origem invalida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = $sources[$source];

function json_fail($message, $statusCode = 400)
{
    http_response_code($statusCode);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {
    case 'secretarias':
        $stmt = $pdo->query("SELECT id, field_232 AS name FROM app_entity_26 ORDER BY field_232");
        echo json_encode(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;

    case 'setores':
        $secretariaId = isset($_GET['secretaria_id']) ? trim((string) $_GET['secretaria_id']) : '';
        if ($secretariaId === '') {
            json_fail('Secretaria obrigatoria.');
        }
        $stmt = $pdo->prepare("SELECT id, field_249 AS name FROM app_entity_27 WHERE parent_item_id = ? ORDER BY field_249");
        $stmt->execute([$secretariaId]);
        echo json_encode(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;

    case 'tipos':
        if (isset($config['fixed_types'])) {
            echo json_encode(['items' => $config['fixed_types']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, name FROM app_fields_choices WHERE fields_id = ? AND name IN ('Caixa', 'Pasta') ORDER BY FIELD(name, 'Caixa', 'Pasta')");
        $stmt->execute([$config['choices_field_id']]);
        echo json_encode(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;

    case 'caixas':
        $secretariaId = isset($_GET['secretaria_id']) ? trim((string) $_GET['secretaria_id']) : '';
        $setorId = isset($_GET['setor_id']) ? trim((string) $_GET['setor_id']) : '';
        $tipoId = isset($_GET['tipo_id']) ? trim((string) $_GET['tipo_id']) : '';

        if ($secretariaId === '' || $setorId === '') {
            json_fail('Secretaria e setor sao obrigatorios.');
        }

        $baseConditions = [
            $config['secretaria_field'] . ' = ?',
            $config['setor_field'] . ' = ?',
        ];
        $baseParams = [$secretariaId, $setorId];

        $fetchRows = function(array $conditions, array $params) use ($pdo, $config) {
            $sql = 'SELECT MAX(id) AS id, TRIM(' . $config['numero_field'] . ') AS numero FROM ' . $config['parent_table'] . ' WHERE TRIM(' . $config['numero_field'] . ') <> ""';
            if (!empty($conditions)) {
                $sql .= ' AND ' . implode(' AND ', $conditions);
            }
            $sql .= ' GROUP BY ' . $config['numero_field'] . ' ORDER BY ' . $config['numero_field'];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        $rows = [];
        if ($tipoId !== '') {
            $rows = $fetchRows(array_merge($baseConditions, [$config['tipo_field'] . ' = ?']), array_merge($baseParams, [$tipoId]));
        }

        if (empty($rows)) {
            $rows = $fetchRows($baseConditions, $baseParams);
        }

        echo json_encode(['items' => $rows], JSON_UNESCAPED_UNICODE);
        exit;

    case 'documentos':
        $caixaId = isset($_GET['caixa_id']) ? trim((string) $_GET['caixa_id']) : '';
        if ($caixaId === '') {
            json_fail('Caixa/Pasta obrigatoria.');
        }

        if ($source === 'ged') {
            $sql = "SELECT e.id, e.parent_item_id, e.field_445 AS arquivo, e.field_446 AS campo1, e.field_447 AS campo2, e.field_448 AS campo3, e.field_450 AS extra, e.field_554 AS paginas, fc.name AS tipo_nome FROM app_entity_43 e LEFT JOIN app_fields_choices fc ON fc.id = e.field_449 WHERE e.parent_item_id = ? ORDER BY e.id";
        } else {
            $sql = "SELECT e.id, e.parent_item_id, e.field_542 AS arquivo, e.field_543 AS campo1, e.field_544 AS campo2, e.field_545 AS campo3, e.field_548 AS extra, e.field_552 AS paginas, fc.name AS tipo_nome FROM app_entity_49 e LEFT JOIN app_fields_choices fc ON fc.id = e.field_546 WHERE e.parent_item_id = ? ORDER BY e.id";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$caixaId]);
        echo json_encode(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;
}

json_fail('Acao invalida.');
