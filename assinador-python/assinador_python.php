<?php
// assinador_python.php - Página de integração com o Assinador Python
// NÃO redireciona para login, apenas valida a sessão existente

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir funções de integração
require_once __DIR__ . '/functions_python.php';

// Verificar se usuário está logado pela sessão existente
// Se não estiver logado, mostra mensagem de erro em vez de redirecionar
/*
if (!isset($_SESSION['usuario_id'])) {
    die('<div style="color: red; padding: 20px; text-align: center; font-family: Arial;">
         <h2>Erro de Sessão</h2>
         <p>Usuário não está logado. Por favor, faça login no sistema principal.</p>
         <p><a href="javascript:window.close()">Fechar esta aba</a> e tente novamente.</p>
         </div>');
}
*/
// Buscar documento que será assinado
$documento_id = $_GET['doc_id'] ?? 0;
$token = $_GET['token'] ?? '';

if (!$documento_id || !$token) {
    header('Location: ' . getPythonServiceBaseUrl(true) . '/standalone/');
    exit;
}

// Conectar ao banco de dados
try {
    $pdo = getAssinadorPdo();
} catch (PDOException $e) {
    die('<div style="color: red; padding: 20px;">
         <h2>Erro de Banco de Dados</h2>
         <p>' . htmlspecialchars($e->getMessage()) . '</p>
         </div>');
}

// Validar token e sessão
$stmt = $pdo->prepare("
    SELECT s.*, d.caminho as doc_path, d.nome_original
    FROM sessoes_assinatura s
    JOIN documentos d ON s.documento_id = d.id
    WHERE s.token = ? AND s.documento_id = ? 
    AND s.data_fim IS NULL
    AND s.usuario_id = ?
");
$stmt->execute([$token, $documento_id, $_SESSION['usuario_id']]);
$sessao = $stmt->fetch();

if (!$sessao) {
    die('<div style="color: red; padding: 20px;">
         <h2>Sessão Inválida</h2>
         <p>Token de sessão inválido ou expirado. Gere uma nova solicitação de assinatura.</p>
         <p><a href="javascript:window.close()">Fechar esta aba</a></p>
         </div>');
}

// Verificar se o arquivo PDF existe
if (!file_exists($sessao['doc_path'])) {
    die('<div style="color: red; padding: 20px;">
         <h2>Arquivo não encontrado</h2>
         <p>O documento não foi encontrado no servidor.</p>
         <p>Caminho: ' . htmlspecialchars($sessao['doc_path']) . '</p>
         </div>');
}

// Buscar certificados do usuário
$stmt = $pdo->prepare("
    SELECT id, nome, emissor, DATE_FORMAT(validade, '%d/%m/%Y') as validade_fmt
    FROM certificados 
    WHERE usuario_id = ? AND ativo = 1
    ORDER BY nome
");
$stmt->execute([$_SESSION['usuario_id']]);
$certificados = $stmt->fetchAll();

// Status do serviço Python
$pythonStatus = function_exists('pythonServiceStatus') ? pythonServiceStatus() : false;
$usarCLI = !$pythonStatus;

// URL base para assets (ajuste conforme necessário)
$baseUrl = '/infodoc-sisged-remote-clone/';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Assinador Digital Python - Infodoc SISGED</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            background: #f4f6f9; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .header { 
            background: #0b3b66; 
            color: white; 
            padding: 15px 20px; 
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        .header i {
            font-size: 1.8rem;
        }
        .container {
            max-width: 100%;
            padding: 20px;
        }
        .python-status { 
            padding: 12px; 
            border-radius: 6px; 
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .python-online { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        .python-offline { 
            background: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeeba; 
        }
        #pdf-canvas { 
            border: 1px solid #ddd; 
            cursor: crosshair;
            max-width: 100%;
            height: auto;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .marker { 
            position: absolute; 
            border: 2px dashed #dc3545; 
            background: rgba(220,53,69,0.1);
            pointer-events: none;
            display: none;
            z-index: 1000;
        }
        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        .btn-assinar {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-assinar:hover:not(:disabled) {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-assinar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .info-documento {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            word-break: break-all;
        }
        #progresso {
            margin-top: 15px;
        }
        .certificado-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .certificado-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <i class="fas fa-pencil-alt"></i>
        <h2>Assinador Digital Python - ICP-Brasil</h2>
        <div style="flex:1"></div>
        <span class="badge bg-light text-dark">
            <i class="fas fa-user"></i> 
            Usuário: <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'ID: ' . $_SESSION['usuario_id']) ?>
        </span>
    </div>
    
    <div class="container">
        <!-- Informações do documento -->
        <div class="info-documento">
            <i class="fas fa-file-pdf text-danger"></i>
            <strong>Documento:</strong> <?= htmlspecialchars(basename($sessao['doc_path'])) ?>
        </div>
        
        <!-- Status do serviço Python -->
        <div class="python-status <?= $pythonStatus ? 'python-online' : 'python-offline' ?>">
            <?php if ($pythonStatus): ?>
                <i class="fas fa-check-circle fa-lg"></i>
                <span><strong>Serviço Python online</strong> - usando API REST (recomendado)</span>
            <?php else: ?>
                <i class="fas fa-exclamation-triangle fa-lg"></i>
                <span><strong>Serviço Python offline</strong> - usando fallback CLI (pode ser mais lento)</span>
            <?php endif; ?>
        </div>
        
        <div class="row">
            <!-- Coluna esquerda: Configurações -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-certificate"></i> Certificado Digital
                    </div>
                    <div class="card-body">
                        <?php if (empty($certificados)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation"></i>
                                Nenhum certificado encontrado. 
                                <a href="#" class="alert-link">Upload de certificado</a>
                            </div>
                        <?php else: ?>
                            <select id="certificado" class="form-select mb-3" required>
                                <option value="">-- Selecione o certificado --</option>
                                <?php foreach ($certificados as $cert): ?>
                                <option value="<?= $cert['id'] ?>">
                                    <?= htmlspecialchars($cert['nome']) ?> 
                                    (Val: <?= $cert['validade_fmt'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        
                        <label class="form-label">Senha do Certificado:</label>
                        <input type="password" id="senha" class="form-control mb-3" 
                               placeholder="••••••••" required>
                        
                        <hr>
                        
                        <label class="form-label">Posição da Assinatura:</label>
                        <p class="small text-muted">
                            <i class="fas fa-mouse-pointer"></i> Clique no PDF para definir
                        </p>
                        
                        <div class="row">
                            <div class="col">
                                <label class="form-label">Largura (mm)</label>
                                <input type="number" id="largura" class="form-control" value="80" min="20" max="300">
                            </div>
                            <div class="col">
                                <label class="form-label">Altura (mm)</label>
                                <input type="number" id="altura" class="form-control" value="30" min="10" max="200">
                            </div>
                        </div>
                        
                        <hr>
                        
                        <button id="btnAssinar" class="btn btn-assinar w-100" disabled>
                            <i class="fas fa-pencil-alt"></i> Assinar Documento
                        </button>
                        
                        <div id="progresso" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                     style="width: 100%">
                                    <i class="fas fa-spinner fa-spin"></i> Processando assinatura...
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-outline-secondary btn-sm w-100" onclick="window.close()">
                                <i class="fas fa-times"></i> Cancelar e Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Coluna direita: Visualizador PDF -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-file-pdf"></i> Visualizador do Documento
                        <span class="float-end">
                            <span class="badge bg-light text-dark" id="pageInfo">Página 1</span>
                        </span>
                    </div>
                    <div class="card-body text-center" style="position: relative; overflow: auto;">
                        <canvas id="pdf-canvas"></canvas>
                        <div id="marker" class="marker"></div>
                    </div>
                    <div class="card-footer text-muted small">
                        <i class="fas fa-info-circle"></i>
                        Clique no documento para posicionar a assinatura. O retângulo vermelho mostra a área.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script>
        // Configurar PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
        
        // Estado da aplicação
        let pdfDoc = null;
        let posicao = null;
        let scale = 1.5;
        let currentPage = 1;
        const canvas = document.getElementById('pdf-canvas');
        const ctx = canvas.getContext('2d');
        const documentoId = <?= json_encode($documento_id) ?>;
        const token = <?= json_encode($token) ?>;
        const usarCLI = <?= json_encode($usarCLI) ?>;
        const docPath = <?= json_encode($sessao['doc_path']) ?>;
        
        // Função para carregar o PDF
        function loadPDF() {
            pdfjsLib.getDocument(docPath).promise.then(pdf => {
                pdfDoc = pdf;
                renderPage(currentPage);
                document.getElementById('pageInfo').textContent = `Página 1 de ${pdf.numPages}`;
            }).catch(err => {
                console.error('Erro ao carregar PDF:', err);
                Swal.fire('Erro', 'Não foi possível carregar o PDF', 'error');
            });
        }
        
        // Função para renderizar página
        function renderPage(num) {
            pdfDoc.getPage(num).then(page => {
                const viewport = page.getViewport({ scale: scale });
                
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                
                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                
                return page.render(renderContext).promise;
            }).catch(err => {
                console.error('Erro ao renderizar página:', err);
            });
        }
        
        // Clique para posicionar assinatura
        canvas.addEventListener('click', (e) => {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            
            // Coordenadas no canvas
            const canvasX = (e.clientX - rect.left) * scaleX;
            const canvasY = (e.clientY - rect.top) * scaleY;
            
            // Converter pixels para mm (72 pontos = 1 polegada = 25.4 mm)
            const mmPorPixel = 25.4 / 72 / scale;
            const largura = parseFloat(document.getElementById('largura').value) || 80;
            const altura = parseFloat(document.getElementById('altura').value) || 30;
            const pixelsPorMm = 1 / mmPorPixel;
            const larguraPx = largura * pixelsPorMm;
            const alturaPx = altura * pixelsPorMm;
            const leftPx = Math.max(0, Math.min(canvas.width - larguraPx, canvasX - larguraPx / 2));
            const topPx = Math.max(0, Math.min(canvas.height - alturaPx, canvasY - alturaPx / 2));
            const x_mm = leftPx * mmPorPixel;
            const y_mm = (canvas.height - (topPx + alturaPx)) * mmPorPixel;
            
            posicao = {
                x: Math.round(x_mm * 100) / 100,
                y: Math.round(y_mm * 100) / 100,
                pagina: currentPage,
                largura: largura,
                altura: altura
            };
            
            // Mostrar marcador
            const marker = document.getElementById('marker');
            marker.style.left = leftPx + 'px';
            marker.style.top = topPx + 'px';
            marker.style.width = larguraPx + 'px';
            marker.style.height = alturaPx + 'px';
            marker.style.display = 'block';
            
            document.getElementById('btnAssinar').disabled = false;
            
            Swal.fire({
                icon: 'success',
                title: 'Posição definida',
                text: `X: ${posicao.x}mm, Y: ${posicao.y}mm (Página ${currentPage})`,
                timer: 2000,
                showConfirmButton: false
            });
        });
        
        // Assinar documento
        document.getElementById('btnAssinar').addEventListener('click', async () => {
            const certId = document.getElementById('certificado').value;
            const senha = document.getElementById('senha').value;
            
            if (!certId) {
                Swal.fire('Erro', 'Selecione um certificado', 'error');
                return;
            }
            
            if (!senha) {
                Swal.fire('Erro', 'Informe a senha do certificado', 'error');
                return;
            }
            
            if (!posicao) {
                Swal.fire('Erro', 'Posicione a assinatura no documento', 'error');
                return;
            }
            
            // Confirmar com o usuário
            const confirm = await Swal.fire({
                title: 'Confirmar assinatura',
                html: `
                    <p>Documento: ${docPath.split('/').pop()}</p>
                    <p>Posição: X=${posicao.x}mm, Y=${posicao.y}mm</p>
                    <p>Página: ${posicao.pagina}</p>
                    <p>${usarCLI ? '⚠️ Usando modo fallback (pode ser mais lento)' : '✅ Serviço Python online'}</p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, assinar',
                cancelButtonText: 'Cancelar'
            });
            
            if (!confirm.isConfirmed) return;
            
            // Mostrar progresso
            document.getElementById('progresso').style.display = 'block';
            document.getElementById('btnAssinar').disabled = true;
            
            try {
                const response = await fetch('processar_assinatura.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        documento_id: documentoId,
                        token: token,
                        certificado_id: certId,
                        senha: senha,
                        posicao: posicao,
                        usar_cli: usarCLI
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: result.message || 'Documento assinado com sucesso',
                        timer: 3000,
                        showConfirmButton: true
                    });
                    
                    // Fechar a aba após sucesso
                    window.close();
                } else {
                    Swal.fire('Erro', result.message || 'Falha na assinatura', 'error');
                }
            } catch (err) {
                console.error('Erro:', err);
                Swal.fire('Erro', 'Falha na comunicação com o servidor: ' + err.message, 'error');
            } finally {
                document.getElementById('progresso').style.display = 'none';
                document.getElementById('btnAssinar').disabled = false;
            }
        });
        
        // Teclas de navegação
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft' && currentPage > 1) {
                currentPage--;
                renderPage(currentPage);
                document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${pdfDoc.numPages}`;
            } else if (e.key === 'ArrowRight' && currentPage < pdfDoc.numPages) {
                currentPage++;
                renderPage(currentPage);
                document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${pdfDoc.numPages}`;
            }
        });
        
        // Iniciar
        loadPDF();
    </script>
</body>
</html>