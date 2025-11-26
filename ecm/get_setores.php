<?php
include '../includes/db.php'; // Inclua seu arquivo de conexão com o banco de dados

if (isset($_GET['secretaria_id'])) {
    $secretariaId = $_GET['secretaria_id'];

    // Consulta SQL ajustada para buscar setores da secretaria selecionada
    $stmt = $pdo->prepare("SELECT id, field_250 FROM app_entity_27 WHERE parent_item_id = ?");
    $stmt->execute([$secretariaId]);

    $options = '<option value="">Selecione o Setor</option>';
    while ($row = $stmt->fetch()) {
        $options .= '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['field_250']) . '</option>';
    }

    echo $options;
} else {
    echo '<option value="">Selecione o Setor</option>'; // Em caso de falha na requisição
}
?>
