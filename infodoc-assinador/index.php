<?php
// index.php - Infodoc-Sisgep Assinador (entrega de exemplo)
// Redireciona para a interface completa de assinatura (usa /assinatura/sign.php)
// Isso escolhe a implementação mais completa (seleção de área, TSA, suporte a .pfx/.pem)
// Se preferir a interface simples, remova o header redirect abaixo.
// Redirecionamento robusto: monta o caminho a partir de SCRIPT_NAME para funcionar em setups
// com o projeto em subdiretórios. Ex.: /meu-projeto/infodoc-assinador -> /meu-projeto/assinatura
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$redirectPath = $scriptDir . '/../assinatura/';
header('Location: ' . $redirectPath);
exit;

$certDir = __DIR__ . '/certs/';
$certFiles = is_dir($certDir) ? array_values(array_diff(scandir($certDir), ['.','..'])) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Infodoc-Sisgep - Assinador</title>
<link href="assets/css/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<header class="header">
  <img src="assets/img/logo.png" alt="Logo" />
  <h2>Infodoc-Sisgep - Assinador Digital (Exemplo)</h2>
</header>
<div class="container mt-3">
  <div class="row">
    <div class="col-md-4">
      <h5>Upload do PDF</h5>
      <form id="uploadForm" enctype="multipart/form-data" method="post" action="upload.php">
        <input type="file" name="pdfFile" accept="application/pdf" class="form-control mb-2" required>
        <button class="btn btn-success w-100" type="submit">Enviar PDF</button>
      </form>
      <hr>
      <h5>Certificados em /certs</h5>
      <select id="certSelect" class="form-select">
        <option value="">-- selecione --</option>
        <?php foreach ($certFiles as $c) {
            $ext = pathinfo($c, PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['pfx','p12','pem'])) {
                echo "<option value=\"".htmlspecialchars($c)."\">".htmlspecialchars($c)."</option>";
            }
        } ?>
      </select>
      <div class="mt-2">
        <label>Senha do certificado (se aplicável)</label>
        <input id="certPassword" class="form-control" type="password">
      </div>
      <div class="mt-2">
        <label>Rubrica (imagem opcional)</label>
        <input id="sigImage" class="form-control" type="file" accept="image/*">
      </div>
      <div class="mt-2">
        <label>Largura (mm)</label>
        <input id="sigWidth" class="form-control" type="number" value="40">
        <label class="mt-1">Altura (mm)</label>
        <input id="sigHeight" class="form-control" type="number" value="20">
      </div>
    </div>
    <div class="col-md-8">
      <h5>Visualizador Teste</h5>
      <iframe id="pdfFrame" src="" style="width:100%; height:600px; border:1px solid #ccc;"></iframe>
      <div class="mt-2 d-flex gap-2">
        <button id="btnSign" class="btn btn-primary">Assinar (exemplo)</button>
        <a id="btnDownload" class="btn btn-success disabled" href="#" download>Baixar Assinado</a>
      </div>
      <div class="mt-3" id="status"></div>
    </div>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
