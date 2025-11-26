<?php
include '../includes/db.php';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage = isset($_GET['records_per_page']) ? intval($_GET['records_per_page']) : 10;
$offset = ($page - 1) * $recordsPerPage;

$stmt = $pdo->query("SELECT COUNT(*) FROM app_entity_43");
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);

$stmt = $pdo->prepare("SELECT id, field_430, field_431, field_429, field_436 FROM app_entity_43 ORDER BY id DESC LIMIT :offset, :records_per_page");
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':records_per_page', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$output = '';
foreach ($registros as $registro) {
    $output .= '<tr>';
    $output .= '<td>' . htmlspecialchars($registro['id']) . '</td>';
    $output .= '<td>' . htmlspecialchars($registro['field_430']) . '</td>';
    $output .= '<td>' . htmlspecialchars($registro['field_431']) . '</td>';
    $output .= '<td>' . htmlspecialchars($registro['field_429']) . '</td>';
    $output .= '<td>' . htmlspecialchars($registro['field_436']) . '</td>';
    $output .= '<td>';
    $output .= '<button class="btn btn-primary btn-sm editar" data-id="' . $registro['id'] . '">Editar</button>';
    $output .= ' ';
    $output .= '<button class="btn btn-danger btn-sm excluir" data-id="' . $registro['id'] . '">Excluir</button>';
    $output .= '</td>';
    $output .= '</tr>';
}

$pagination = '';
for ($i = 1; $i <= $totalPages; $i++) {
    $pagination .= '<li class="page-item' . ($i == $page ? ' active' : '') . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
}

echo json_encode(['records' => $output, 'pagination' => $pagination]);
?>
