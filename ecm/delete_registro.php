<?php
include '../includes/db.php';

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $pdo->prepare("DELETE FROM app_entity_43 WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    if ($stmt->execute()) {
        echo 'Registro excluído com sucesso.';
    } else {
        echo 'Erro ao excluir o registro.';
    }
} else {
    echo 'ID do registro não fornecido.';
}
?>
