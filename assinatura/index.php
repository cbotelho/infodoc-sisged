<?php
// index.php
// Interface de Assinatura Digital - Infodoc-Sisged
// Lista certificados na pasta /certs e permite upload de PDF + seleção de posição da assinatura

$certDir = __DIR__ . '/certs/';
$certFiles = [];
if (is_dir($certDir)) {
    $certFiles = array_values(array_diff(scandir($certDir), ['.', '..']));
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Infodoc-SisGed - Assinatura Digital ICP-Brasil</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    body { background:#f4f6f9; }
    header { background:#0b3b66; color:#fff; padding:12px 20px; display:flex; align-items:center; gap:15px; }
    header img { height:48px; }
    .pdf-viewer { border:1px solid #ddd; height:700px; background:#fff; overflow:auto; position:relative; }
    #pdf-canvas { display:block; margin:0 auto; cursor:crosshair; }
    .marker {
        position:absolute;
        border:2px dashed #e74c3c;
        pointer-events:none;
        transform: translate(-50%, -50%);
        background: rgba(231,76,60,0.08);
    }
</style>
</head>
<body>
<header>
    <?php if (file_exists(__DIR__ . '/assets/logo.png')): ?>
    <img src="assets/logo.png" alt="Logo Infodoc">
    <?php endif; ?>
    <h4>Infodoc-SisGed — Assinatura Digital (ICP-Brasil)</h4>
</header>

<div class="container mt-4">
    <div class="row">
        <!-- Coluna esquerda: upload + certificados -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">Upload do Documento PDF</div>
                <div class="card-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="file" name="pdfFile" id="pdfFile" accept="application/pdf" class="form-control mb-2" required>
                        <button class="btn btn-success w-100" type="submit">Enviar PDF</button>
                    </form>
                    <hr>
                    <label class="form-label">Arquivo PDF carregado:</label>
                    <div id="currentFile" class="small text-muted">Nenhum</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">Certificados Disponíveis (/certs)</div>
                <div class="card-body">
                    <select id="certificado" class="form-select mb-2">
                        <option value="">-- Selecione um certificado --</option>
                        <?php foreach ($certFiles as $f): ?>
                            <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-text mb-2">Se usar PEM sem chave privada embutida, envie o arquivo de chave abaixo:</div>
                    <input type="file" id="keyFile" accept=".key,.pem" class="form-control mb-2">
                    <div class="form-text">Obs: Se usar .pfx/.p12 não envie chave separada.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-info text-white">Aparência da Assinatura</div>
                <div class="card-body">
                    <label class="form-label">Página (padrão: última página)</label>
                    <input type="number" id="signaturePage" class="form-control mb-2" min="1" placeholder="Deixe vazio para última página">
                    <div class="row">
                        <div class="col">
                            <label class="form-label">Largura (mm)</label>
                            <input type="number" id="sigWidth" class="form-control" value="60" min="10">
                        </div>
                        <div class="col">
                            <label class="form-label">Altura (mm)</label>
                            <input type="number" id="sigHeight" class="form-control" value="30" min="10">
                        </div>
                    </div>
                    <small class="text-muted">Clique no visualizador para posicionar a assinatura. Ou deixe em branco para usar posição padrão (inferior esquerdo).</small>
                </div>
            </div>
        </div>

        <!-- Coluna direita: visualizador -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-dark text-white">Visualizador do PDF (clique para definir posição)</div>
                <div class="card-body pdf-viewer" id="viewerContainer">
                    <canvas id="pdf-canvas"></canvas>
                    <div id="marker" class="marker" style="display:none;"></div>
                </div>
                <div class="card-footer text-center">
                    <button id="btnAssinar" class="btn btn-primary me-2" disabled>Assinar Documento</button>
                    <button id="btnBaixar" class="btn btn-success" disabled>Baixar Documento Assinado</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal senha - somente para .pfx/.p12 -->
<div class="modal fade" id="senhaModal" tabindex="-1" role="dialog" aria-labelledby="senhaModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="senhaModalLabel">Senha do Certificado</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <input type="password" id="senhaCertificado" class="form-control" placeholder="Senha do certificado (.pfx/.p12)">
        <small class="text-muted">Nota: Certificados .pem não precisam de senha</small>
      </div>
      <div class="modal-footer">
        <button id="confirmarAssinatura" class="btn btn-primary">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<!-- Bibliotecas -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- PDF.js worker -->
<script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';</script>

<!-- App JS -->
<script src="assets/js/app.js"></script>
</body>
</html>