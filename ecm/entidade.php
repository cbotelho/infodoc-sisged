<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Entidades</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h1>Gestão de Entidades</h1>

    <?php
    include '../includes/db.php';

    $id = '';
    $sigla = '';
    $cnpj = '';
    $nome = '';
    $cadastroEm = date('Y-m-d'); // Gera a data do cadastro com a data atual do sistema

    if (isset($_GET['edit'])) {
        $id = $_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM Entidade WHERE id = ?");
        $stmt->execute([$id]);
        $entity = $stmt->fetch();

        if ($entity) {
            $sigla = $entity['Sigla'];
            $cnpj = $entity['cnpj'];
            $nome = $entity['Nome'];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sigla = $_POST['sigla'];
        $cnpj = $_POST['cnpj'];
        $nome = $_POST['nome'];
        $usuario = 1; // ID do usuário fictício, ajuste conforme necessário

        if (isset($_POST['id']) && $_POST['id'] != '') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE Entidade SET Sigla = ?, cnpj = ?, Nome = ?, criado_por = ? WHERE id = ?");
            $stmt->execute([$sigla, $cnpj, $nome, $usuario, $id]);
            echo "<div class='alert alert-success'>Entidade atualizada com sucesso!</div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO Entidade (Cadastrado_em, criado_por, Sigla, cnpj, Nome) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$cadastroEm, $usuario, $sigla, $cnpj, $nome]);
            echo "<div class='alert alert-success'>Entidade cadastrada com sucesso!</div>";
        }
    }
    ?>

    <form action="entidade.php" method="POST">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
        <div class="form-group">
            <label for="sigla">Sigla</label>
            <input type="text" class="form-control" id="sigla" name="sigla" value="<?php echo htmlspecialchars($sigla); ?>" required>
        </div>
        <div class="form-group">
            <label for="cnpj">CNPJ</label>
            <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?php echo htmlspecialchars($cnpj); ?>" required>
        </div>
        <div class="form-group">
            <label for="nome">Nome</label>
            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Salvar</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
