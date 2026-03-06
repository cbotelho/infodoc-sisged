<?php
// Habilitar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurações
$UPLOAD_DIR = __DIR__ . '/uploads/';
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

// Se for uma requisição POST para processar o PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Verificar se o arquivo foi enviado
        if (empty($_FILES['pdf'])) {
            throw new Exception('Nenhum arquivo PDF enviado');
        }
        
        // Validar tipo do arquivo
        $file_type = $_FILES['pdf']['type'];
        if ($file_type !== 'application/pdf') {
            throw new Exception('Por favor, envie um arquivo PDF válido');
        }
        
        // Processar coordenadas
        $x = isset($_POST['x']) ? (int)$_POST['x'] : 100;
        $y = isset($_POST['y']) ? (int)$_POST['y'] : 100;
        
        // Gerar nome único para o arquivo
        $output_filename = 'assinado_' . uniqid() . '.pdf';
        $output_path = $UPLOAD_DIR . $output_filename;
        
        // Simular o processamento (apenas copia o arquivo)
        if (!copy($_FILES['pdf']['tmp_name'], $output_path)) {
            throw new Exception('Erro ao processar o documento');
        }
        
        // Retornar sucesso
        echo json_encode([
            'success' => true,
            'file' => 'uploads/' . $output_filename
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulador de Assinatura Digital</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .upload-area {
            border: 2px dashed #ccc;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #4CAF50;
            background-color: #f9f9f9;
        }
        .preview-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        .pdf-preview {
            flex: 2;
            border: 1px solid #ddd;
            min-height: 600px;
            position: relative;
            overflow: hidden;
        }
        .controls {
            flex: 1;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
            width: 100%;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="file"] {
            display: none;
        }
        .file-info {
            margin-top: 10px;
            color: #666;
        }
        #pdf-canvas {
            width: 100%;
            height: 100%;
            border: none;
        }
        .signature-marker {
            position: absolute;
            width: 150px;
            height: 50px;
            background-color: rgba(76, 175, 80, 0.3);
            border: 2px dashed #4CAF50;
            cursor: move;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simulador de Assinatura Digital</h1>
        
        <div class="upload-area" id="dropArea">
            <p>Arraste e solte seu PDF aqui ou clique para selecionar</p>
            <input type="file" id="fileInput" accept=".pdf">
        </div>
        
        <div class="preview-container" id="previewContainer" style="display: none;">
            <div class="pdf-preview" id="pdfContainer">
                <div class="signature-marker" id="signatureMarker">Sua Assinatura</div>
                <iframe id="pdfViewer" class="pdf-viewer" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
            
            <div class="controls">
                <h3>Configurações da Assinatura</h3>
                
                <div class="form-group">
                    <label>Certificado Digital:</label>
                    <select class="form-control" id="certificateSelect">
                        <option value="cert1">Certificado Digital A1</option>
                        <option value="cert2">Certificado Digital A3</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Posição da Assinatura:</label>
                    <div>
                        <label>X: <input type="number" id="posX" value="100" class="pos-input"> px</label>
                        <label>Y: <input type="number" id="posY" value="100" class="pos-input"> px</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Motivo da Assinatura:</label>
                    <select class="form-control" id="reasonSelect">
                        <option value="Aprovação">Aprovação</option>
                        <option value="Concordância">Concordância</option>
                        <option value="Ciência">Ciência</option>
                    </select>
                </div>
                
                <button id="signBtn" class="btn" disabled>Assinar Documento</button>
                
                <div id="result" style="margin-top: 20px; display: none;"></div>
            </div>
        </div>
    </div>

    <script>
        // Elementos da interface
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('fileInput');
        const previewContainer = document.getElementById('previewContainer');
        const pdfViewer = document.getElementById('pdfViewer');
        const signBtn = document.getElementById('signBtn');
        const signatureMarker = document.getElementById('signatureMarker');
        const posXInput = document.getElementById('posX');
        const posYInput = document.getElementById('posY');
        const resultDiv = document.getElementById('result');
        
        // Variáveis globais
        let isDragging = false;
        let currentFile = null;
        let offsetX, offsetY;
        
        // Função para exibir o PDF
        function displayPdf(file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const blob = new Blob([e.target.result], { type: 'application/pdf' });
                const url = URL.createObjectURL(blob);
                pdfViewer.src = url;
                previewContainer.style.display = 'flex';
                signBtn.disabled = false;
                currentFile = file;
            };
            
            reader.readAsArrayBuffer(file);
        }
        
        // Arrastar e soltar
        dropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropArea.style.borderColor = '#4CAF50';
            dropArea.style.backgroundColor = '#f0f9f0';
        });
        
        dropArea.addEventListener('dragleave', () => {
            dropArea.style.borderColor = '#ccc';
            dropArea.style.backgroundColor = '';
        });
        
        dropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            dropArea.style.borderColor = '#ccc';
            dropArea.style.backgroundColor = '';
            
            const file = e.dataTransfer.files[0];
            if (file && file.type === 'application/pdf') {
                displayPdf(file);
            } else {
                alert('Por favor, selecione um arquivo PDF válido.');
            }
        });
        
        // Clique para selecionar arquivo
        dropArea.addEventListener('click', () => {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && file.type === 'application/pdf') {
                displayPdf(file);
            }
        });
        
        // Arrastar o marcador de assinatura
        signatureMarker.addEventListener('mousedown', (e) => {
            isDragging = true;
            const rect = signatureMarker.getBoundingClientRect();
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;
            signatureMarker.style.cursor = 'grabbing';
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            
            const container = document.getElementById('pdfContainer');
            const containerRect = container.getBoundingClientRect();
            
            let x = e.clientX - containerRect.left - offsetX;
            let y = e.clientY - containerRect.top - offsetY;
            
            // Limitar ao contêiner
            x = Math.max(0, Math.min(x, containerRect.width - signatureMarker.offsetWidth));
            y = Math.max(0, Math.min(y, containerRect.height - signatureMarker.offsetHeight));
            
            signatureMarker.style.left = x + 'px';
            signatureMarker.style.top = y + 'px';
            
            // Atualizar os campos de posição
            posXInput.value = Math.round(x);
            posYInput.value = Math.round(y);
        });
        
        document.addEventListener('mouseup', () => {
            isDragging = false;
            signatureMarker.style.cursor = 'move';
        });
        
        // Atualizar posição do marcador quando os campos mudarem
        posXInput.addEventListener('input', updateMarkerPosition);
        posYInput.addEventListener('input', updateMarkerPosition);
        
        function updateMarkerPosition() {
            const x = parseInt(posXInput.value) || 0;
            const y = parseInt(posYInput.value) || 0;
            signatureMarker.style.left = x + 'px';
            signatureMarker.style.top = y + 'px';
        }
        
        // Assinar documento
        signBtn.addEventListener('click', async () => {
            if (!currentFile) return;
            
            const formData = new FormData();
            formData.append('pdf', currentFile);
            formData.append('x', posXInput.value);
            formData.append('y', posYInput.value);
            formData.append('reason', document.getElementById('reasonSelect').value);
            formData.append('certificate', document.getElementById('certificateSelect').value);
            
            signBtn.disabled = true;
            signBtn.textContent = 'Assinando...';
            resultDiv.style.display = 'none';
            
            try {
                const response = await fetch('simulador_assinatura.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.className = 'success';
                    resultDiv.innerHTML = `
                        <h3>✅ Documento assinado com sucesso!</h3>
                        <p><a href="${result.file}" target="_blank">Clique aqui para baixar o documento assinado</a></p>
                    `;
                } else {
                    throw new Error(result.message || 'Erro ao assinar o documento');
                }
            } catch (error) {
                resultDiv.className = 'error';
                resultDiv.innerHTML = `❌ ${error.message}`;
            } finally {
                signBtn.disabled = false;
                signBtn.textContent = 'Assinar Documento';
                resultDiv.style.display = 'block';
            }
        });
    </script>
</body>
</html>