<?php
include '../includes/db.php';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

$stmt = $pdo->query("SELECT COUNT(*) FROM app_entity_49");
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);
$totalPages = min($totalPages, 18);

$stmt = $pdo->prepare("SELECT * FROM app_entity_49 ORDER BY id DESC LIMIT :offset, :recordsPerPage");
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':recordsPerPage', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$output = '';
foreach ($records as $record) {
    $output .= '
    <tr>
        <td>' . htmlspecialchars($record['id']) . '</td>
        <td>' . htmlspecialchars($record['field_543']) . '</td>
        <td>' . htmlspecialchars($record['field_544']) . '</td>
        <td>' . htmlspecialchars($record['field_545']) . '</td>
        <td>' . htmlspecialchars($record['field_546']) . '</td>
        <td>' . htmlspecialchars($record['field_552']) . '</td>
    </tr>';
}

$pagination = '';
if ($page > 1) {
    $pagination .= '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page - 1) . '"><<</a></li>';
}

for ($i = 1; $i <= $totalPages; $i++) {
    $pagination .= '<li class="page-item' . ($i == $page ? ' active' : '') . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
}

if ($page < $totalPages) {
    $pagination .= '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page + 1) . '">>></a></li>';
}

echo json_encode([
    'records' => $output,
    'pagination' => $pagination,
]);
?>