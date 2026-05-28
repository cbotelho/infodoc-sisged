<?php
$pythonServicePublicUrl = getenv('PYTHON_SERVICE_PUBLIC_URL');
$defaultBaseUrl = '';

if ($pythonServicePublicUrl !== false && trim((string) $pythonServicePublicUrl) !== '') {
    $defaultBaseUrl = rtrim((string) $pythonServicePublicUrl, '/');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Direto R2/S3</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            background: #f4f6f8;
            color: #1f2933;
        }

        .card {
            max-width: 900px;
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin-top: 0;
            font-size: 28px;
        }

        p {
            line-height: 1.5;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
        }

        input,
        select,
        button,
        textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #cbd2d9;
            border-radius: 8px;
            font-size: 14px;
        }

        button {
            background: #0f766e;
            color: #ffffff;
            border: none;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .progress {
            margin: 16px 0;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
            height: 22px;
            display: none;
        }

        .progress-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #0f766e, #14b8a6);
            color: #ffffff;
            text-align: center;
            font-size: 12px;
            line-height: 22px;
            transition: width 0.2s ease;
        }

        .status {
            margin: 12px 0;
            font-weight: 600;
        }

        textarea {
            min-height: 260px;
            font-family: Consolas, monospace;
            resize: vertical;
        }

        .hint {
            background: #eefbf8;
            border-left: 4px solid #14b8a6;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Teste de Upload Direto do Navegador para R2/S3</h1>
        <p>Esta página testa o fluxo real do front-end: solicitar URL assinada, enviar o arquivo direto ao storage com JavaScript e confirmar o objeto no backend.</p>

        <div class="hint">
            Use esta tela para validar se o navegador local consegue fazer o PUT direto no bucket. Se houver problema de CORS, o erro aparece aqui no passo do upload direto.
        </div>

        <div class="grid">
            <div>
                <label for="baseUrl">Base URL do assinador</label>
                <input id="baseUrl" type="text" value="<?php echo htmlspecialchars($defaultBaseUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://assinador.seu-dominio.com.br">
            </div>
            <div>
                <label for="source">Origem</label>
                <select id="source">
                    <option value="ged">GED</option>
                    <option value="sefaz_rh">SEFAZ RH</option>
                    <option value="generic">Generic</option>
                </select>
            </div>
        </div>

        <div class="grid">
            <div>
                <label for="fileInput">Arquivo de teste</label>
                <input id="fileInput" type="file" required>
            </div>
            <div>
                <label for="expiresIn">Expiração da URL assinada (segundos)</label>
                <input id="expiresIn" type="number" value="300" min="60" max="3600">
            </div>
        </div>

        <button id="runTestButton" type="button">Executar teste no navegador</button>

        <div class="progress" id="progressWrapper">
            <div class="progress-bar" id="progressBar">0%</div>
        </div>

        <div class="status" id="statusText">Aguardando teste.</div>
        <textarea id="logOutput" readonly></textarea>
    </div>

    <script>
        (function() {
            var runButton = document.getElementById('runTestButton');
            var fileInput = document.getElementById('fileInput');
            var baseUrlInput = document.getElementById('baseUrl');
            var sourceInput = document.getElementById('source');
            var expiresInput = document.getElementById('expiresIn');
            var statusText = document.getElementById('statusText');
            var progressWrapper = document.getElementById('progressWrapper');
            var progressBar = document.getElementById('progressBar');
            var logOutput = document.getElementById('logOutput');

            function log(message, data) {
                var line = '[' + new Date().toLocaleTimeString('pt-BR') + '] ' + message;

                if (typeof data !== 'undefined') {
                    line += '\n' + JSON.stringify(data, null, 2);
                }

                logOutput.value += line + '\n\n';
                logOutput.scrollTop = logOutput.scrollHeight;
            }

            function setStatus(message) {
                statusText.textContent = message;
                log(message);
            }

            function setProgress(percent, label) {
                var safePercent = Math.max(0, Math.min(100, percent));
                progressWrapper.style.display = 'block';
                progressBar.style.width = safePercent + '%';
                progressBar.textContent = label || (safePercent + '%');
            }

            function buildJsonResponse(response) {
                return response.text().then(function(text) {
                    try {
                        return { raw: text, json: JSON.parse(text) };
                    } catch (error) {
                        return { raw: text, json: null };
                    }
                });
            }

            function uploadFileDirect(uploadInfo, file) {
                return new Promise(function(resolve, reject) {
                    var xhr = new XMLHttpRequest();

                    xhr.open(uploadInfo.method || 'PUT', uploadInfo.upload_url, true);
                    xhr.timeout = 20000;

                    Object.keys(uploadInfo.headers || {}).forEach(function(headerName) {
                        xhr.setRequestHeader(headerName, uploadInfo.headers[headerName]);
                    });

                    xhr.upload.addEventListener('progress', function(event) {
                        if (event.lengthComputable) {
                            var percent = Math.round((event.loaded / event.total) * 70) + 20;
                            setProgress(percent, 'PUT direto ' + percent + '%');
                        }
                    });

                    xhr.onload = function() {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve({ status: xhr.status, responseText: xhr.responseText });
                            return;
                        }

                        reject(new Error('PUT direto retornou HTTP ' + xhr.status + '.'));
                    };

                    xhr.onerror = function() {
                        reject(new Error('Falha de rede ou CORS no PUT direto para o storage.'));
                    };

                    xhr.ontimeout = function() {
                        reject(new Error('Timeout no PUT direto para o storage.'));
                    };

                    xhr.onabort = function() {
                        reject(new Error('Upload direto abortado.'));
                    };

                    xhr.send(file);
                });
            }

            async function runTest() {
                logOutput.value = '';
                progressWrapper.style.display = 'none';

                var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                var baseUrl = (baseUrlInput.value || '').trim().replace(/\/$/, '');
                var source = sourceInput.value;
                var expiresIn = parseInt(expiresInput.value, 10) || 300;

                if (!baseUrl) {
                    throw new Error('Informe a base URL do assinador.');
                }

                if (!file) {
                    throw new Error('Selecione um arquivo para o teste.');
                }

                runButton.disabled = true;

                try {
                    setProgress(5, 'Presign 5%');
                    setStatus('Solicitando URL assinada...');

                    var presignResponse = await fetch(baseUrl + '/api/uploads/presign', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            source: source,
                            expires_in: expiresIn,
                            files: [{
                                filename: file.name,
                                content_type: file.type || 'application/octet-stream',
                                size: file.size || 0
                            }]
                        })
                    });

                    var presignPayload = await buildJsonResponse(presignResponse);
                    log('Resposta do presign recebida.', presignPayload.json || presignPayload.raw);

                    if (!presignResponse.ok || !presignPayload.json || !presignPayload.json.success) {
                        throw new Error('Falha ao solicitar URL assinada.');
                    }

                    var uploadInfo = presignPayload.json.uploads[0];

                    setProgress(20, 'PUT 20%');
                    setStatus('Enviando arquivo direto ao R2/S3...');
                    var putResult = await uploadFileDirect(uploadInfo, file);
                    log('PUT direto concluido.', putResult);

                    setProgress(92, 'Complete 92%');
                    setStatus('Confirmando objeto no backend...');

                    var completeResponse = await fetch(baseUrl + '/api/uploads/complete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            source: source,
                            files: [{
                                original_name: file.name,
                                stored_name: uploadInfo.stored_name,
                                content_type: file.type || 'application/octet-stream',
                                size: file.size || 0
                            }]
                        })
                    });

                    var completePayload = await buildJsonResponse(completeResponse);
                    log('Resposta do complete recebida.', completePayload.json || completePayload.raw);

                    if (!completeResponse.ok || !completePayload.json || !completePayload.json.success) {
                        throw new Error('Falha ao confirmar o upload no backend.');
                    }

                    setProgress(100, '100%');
                    setStatus('SUCESSO: o navegador conseguiu enviar direto ao R2/S3.');
                } finally {
                    runButton.disabled = false;
                }
            }

            runButton.addEventListener('click', function() {
                runTest().catch(function(error) {
                    setStatus('ERRO: ' + error.message);
                    log('Falha no teste.', { message: error.message, stack: error.stack || null });
                });
            });
        })();
    </script>
</body>
</html>