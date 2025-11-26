<?php
include '../includes/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT id, field_433 AS secretaria, field_434 AS setor, field_436 AS tipo, field_437 AS numero FROM app_entity_41 WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($registro);
} else {
    echo json_encode([]);
}
?>
