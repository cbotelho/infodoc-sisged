<?php
 
// Habilitar a exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pythonServicePublicUrl = getenv('PYTHON_SERVICE_PUBLIC_URL');
$sefazRhUploadUrl = 'upload_sefaz_rh.php';
$presignUploadUrl = '';
$completeUploadUrl = '';
$sefazRhDirectFinalizeUrl = '';

if ($pythonServicePublicUrl !== false && trim((string) $pythonServicePublicUrl) !== '') {
    $pythonBaseUrl = rtrim((string) $pythonServicePublicUrl, '/');
    $sefazRhUploadUrl = $pythonBaseUrl . '/api/sefaz-rh/upload';
    $presignUploadUrl = $pythonBaseUrl . '/api/uploads/presign';
    $completeUploadUrl = $pythonBaseUrl . '/api/uploads/complete';
    $sefazRhDirectFinalizeUrl = $pythonBaseUrl . '/api/sefaz-rh/upload/direct';
}
 
// require_once 'fpdi260/autoload.php';
// use setasign\Fpdi\Fpdi;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SISGED-INFODOC</title>
    <!-- Bootstrap CSS -->
    <!--<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">-->
    <!-- Common CSS -->
		<link rel="stylesheet" href="css/bootstrap.min.css" />
		<link rel="stylesheet" href="fonts/icomoon/icomoon.css" />
        <link rel="stylesheet" href="css/main.min.css" />

		<!-- Other CSS includes plugins - Cleanedup unnecessary CSS -->
		<!-- Chartist css -->
    <style>
        .table-container {
            max-height: 480px;
            overflow-y: auto;
        }
        .container{
            max-width:100%;
        }
    </style>
</head>
<body width="100%">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <span><img src="../images/logo_ecm.png" width="100px" height="64px"></span>
            </div>
        </div>
        <div class="row align-items-start">
            <div class="col-12">
                <!-- Formulário de Upload -->
                <form id="uploadForm" action="<?php echo htmlspecialchars($sefazRhUploadUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" id="id_registro" name="id_registro">
                    <input type="hidden" id="numero_hidden" name="numero">
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label for="secretaria">* Secretaria</label>
                            <select class="form-control" id="secretaria" name="secretaria" required>
                                <option value="">Selecione a Secretaria</option>
                                <?php
                                include '../includes/db.php';
                                $stmt = $pdo->query("SELECT id, field_232 FROM app_entity_26");
                                while ($row = $stmt->fetch()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['field_232']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="setor">* Setor</label>
                            <select class="form-control" id="setor" name="setor" required>
                                <option value="">Selecione o Setor</option>
                                <!-- Opções serão carregadas via AJAX -->
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="padrao_renomeio">* Padrão de Renomeio</label>
                            <select class="form-control" id="padrao_renomeio" name="padrao_renomeio" required>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3" selected>3</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="tipodoc">* Tipo de Acesso</label>
                            <select class="form-control" id="tipodoc" name="tipodoc" required>
                                <option value="">Selecione Tipo de Acesso</option>
                                <option value="173">Público</option>
                                <option value="174">Privado</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="doctipo">* Tipo de Documento</label>
                            <select class="form-control" id="doctipo" name="doctipo" required>
                                <option value="">Selecione o Tipo de Documento</option>
                                <option value="2">Matrícula</option>
                                <option value="3">Folha de Ponto</option>
                                <option value="4">Pasta Funcional</option>
                                <option value="5">Diário Oficial</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="tipo">* Tipo de Arquivo</label>
                            <select class="form-control" id="tipo" name="tipo" required>
                                <option value="">Selecione o Tipo</option>
                                <?php
                                include '../includes/db.php';
                                $stmt = $pdo->query("SELECT id, name FROM app_fields_choices WHERE fields_id = 526 AND name IN ('Caixa', 'Pasta') ORDER BY FIELD(name, 'Caixa', 'Pasta')");
                                while ($row = $stmt->fetch()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="numero_select">* Nº da Caixa/Pasta</label>
                            <select class="form-control" id="numero_select" required>
                                <option value="">Selecione o Nº da Caixa/Pasta</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="assunto">* Assunto</label>
                            <input type="text" class="form-control" id="assunto" name="assunto" placeholder="Informe o assunto do documento" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="tratado_por">* Enviado Por:</label>
                            <select class="form-control" id="tratado_por" name="tratado_por" required>
                                <option value="">Selecione quem Enviou</option>
                                <?php
                                include '../includes/db.php';
                                $stmt = $pdo->query("SELECT id, field_12 FROM app_entity_1");
                                while ($row = $stmt->fetch()) {
                                    echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['field_12']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row align-items-center">
                        <div class="form-group col-md-4">
                            <div class="custom-file mb-3">
                                <input type="file" class="custom-file-input" id="files" name="files[]" multiple required>
                                <label class="custom-file-label" for="files">* Escolha os arquivos...</label>
                            </div>
                            <div id="arquivos_selecionados_info" style="color: red; font-weight: bold; display: none; margin-top: 5px;"></div>
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-primary mt-2">Enviar Arquivos</button>
                        </div>
                        <div class="form-group col-md-3">
                            <!-- Barra de Progresso -->
                            <div class="progress" style="height: 38px; display: none;">
                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <div class="form-group col-md-3">
                            <!-- Mensagens de Status ao lado -->
                            <div id="statusInline" class="ml-2"></div>
                        </div>
                    </div>
                    <!-- Mensagens abaixo -->
                    <div class="form-row">
                        <div class="form-group col-12">
                            <div id="status" class="mt-2"></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="row mt-4" id="upload_progress_container" style="display: none;">
            <div class="col-12">
                <h5 class="mb-4">Arquivos Selecionados e Progresso de Envio</h5>
                <div class="list-group" id="upload_progress_list">
                    <!-- Barras de progresso individuais aparecerão aqui -->
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery e Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
    $(document).ready(function() {
        var sefazRhUploadUrl = <?php echo json_encode($sefazRhUploadUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var presignUploadUrl = <?php echo json_encode($presignUploadUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var completeUploadUrl = <?php echo json_encode($completeUploadUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var sefazRhDirectFinalizeUrl = <?php echo json_encode($sefazRhDirectFinalizeUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var directUploadEnabled = false; // Isolando temporariamente no S3 pelo CORS da rede

        function resetNumeroSelect() {
            $('#numero_select').html('<option value="">Selecione o Nº da Caixa/Pasta</option>');
            $('#numero_hidden').val('');
            $('#id_registro').val('');
        }

        function syncNumeroSelection() {
            var selectedOption = $('#numero_select option:selected');
            var numero = $('#numero_select').val();
            var registroId = selectedOption.data('id') || '';

            $('#numero_hidden').val(numero);
            $('#id_registro').val(registroId);
        }

        function loadNumeroOptions() {
            var secretariaId = $('#secretaria').val();
            var setorId = $('#setor').val();
            var tipoId = $('#tipo').val();

            resetNumeroSelect();
            atualizarStatusArquivos();

            if (!secretariaId || !setorId || !tipoId) {
                return;
            }

            $.ajax({
                url: 'get_numeros_sefaz_rh.php',
                type: 'GET',
                data: {
                    format: 'select',
                    secretaria_id: secretariaId,
                    setor_id: setorId,
                    tipo_id: tipoId
                }
            }).done(function(data) {
                $('#numero_select').html(data);
                syncNumeroSelection();
                atualizarStatusArquivos();
            }).fail(function() {
                resetNumeroSelect();
                atualizarStatusArquivos();
            });
        }

        function extractNameWithoutExtension(filename) {
            var lastDot = filename.lastIndexOf('.');
            return lastDot !== -1 ? filename.substring(0, lastDot) : filename;
        }

        function atualizarStatusArquivos() {
            var files = getSelectedFiles();
            var padrao = parseInt($('#padrao_renomeio').val(), 10);
            var numero = $('#numero_hidden').val() || $('#numero_select').val();
            var container = $('#arquivos_selecionados_info');

            if (!files.length) {
                container.hide().empty();
                return;
            }

            container.show();

            var html = '<div style="color: red; font-weight: bold; font-size: 16px;">Arquivos Selecionados: ' + files.length + '</div>';

            if (!numero || isNaN(padrao)) {
                html += '<div style="font-size: 13px; color: #dc3545; font-weight: normal; margin-top: 5px;">* Selecione o Padrão de Renomeio e o Nº da Caixa/Pasta para validar as regras.</div>';
                container.html(html);
                return;
            }

            var arquivosInvalidos = [];

            files.forEach(function(file) {
                var filename = file.name;
                var nameWithoutExt = extractNameWithoutExtension(filename);
                var partes = nameWithoutExt.split('#');
                var partsCount = partes.length;

                // 1. Validar quantidade de partes de acordo com o padrão
                var validoPorPadrao = false;
                if (partsCount > 0 && partsCount <= 4) {
                    if (padrao === 1 && partsCount >= 1) validoPorPadrao = true;
                    else if (padrao === 2 && partsCount >= 2) validoPorPadrao = true;
                    else if (padrao === 3 && partsCount >= 3) validoPorPadrao = true;
                    else if (padrao === 4 && partsCount >= 4) validoPorPadrao = true;
                }

                if (!validoPorPadrao) {
                    var motivo = 'Formato inválido para o Padrão ' + padrao + ' (esperava pelo menos ' + padrao + ' partes separadas por #)';
                    if (partsCount > 4) {
                        motivo = 'Formato inválido (máximo de 4 partes separadas por #, encontrado ' + partsCount + ')';
                    }
                    arquivosInvalidos.push({
                        nome: filename,
                        razao: motivo
                    });
                }
            });

            if (arquivosInvalidos.length > 0) {
                html += '<div style="margin-top: 10px; font-size: 13px; color: red;">' +
                        '<b>Os seguintes arquivos possuem renomeio incompatível:</b>' +
                        '<ul style="margin-top: 5px; margin-bottom: 0; padding-left: 15px; list-style-type: square;">';
                arquivosInvalidos.forEach(function(arq) {
                    html += '<li style="color: red; font-weight: bold; margin-bottom: 3px;">' + arq.nome + ' <span style="font-weight: normal; font-size: 12px; color: #555;">(' + arq.razao + ')</span></li>';
                });
                html += '</ul></div>';
                $('#upload_progress_container').hide();
            } else {
                html += '<div style="margin-top: 5px; font-size: 13px; color: #28a745;"><b>✓ Todos os arquivos possuem formato de nome de acordo com o padrão selecionado!</b></div>';
                
                // Exibir a listagem para cada arquivo com uma barra de progresso individual zerada
                var listHtml = '';
                files.forEach(function(file, idx) {
                    listHtml += '<div class="list-group-item" id="file_item_' + idx + '">' +
                                    '<div class="d-flex justify-content-between align-items-center mb-1">' +
                                        '<span class="file-name" style="font-weight: 500;">' + file.name + '</span>' +
                                        '<span class="file-status text-muted" style="font-size: 12px;">Aguardando envio...</span>' +
                                    '</div>' +
                                    '<div class="progress" style="height: 15px;">' +
                                        '<div class="progress-bar progress-bar-striped" id="file_prog_' + idx + '" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>' +
                                    '</div>' +
                                '</div>';
                });
                $('#upload_progress_list').html(listHtml);
                $('#upload_progress_container').show();
            }

            container.html(html);
        }

        function setProgressState(label, percent, animated) {
            var safePercent = Math.max(0, Math.min(100, percent));
            $('#progressBar')
                .toggleClass('progress-bar-animated', animated !== false)
                .css('width', safePercent + '%')
                .attr('aria-valuenow', safePercent)
                .text(label || (safePercent + '%'));
        }

        function getSelectedFiles() {
            return Array.prototype.slice.call($('#files')[0].files || []);
        }

        function buildDirectUploadPayload() {
            return {
                numero: $('#numero_hidden').val(),
                id_registro: $('#id_registro').val(),
                secretaria: $('#secretaria').val(),
                setor: $('#setor').val(),
                tipo: $('#tipo').val(),
                padrao_renomeio: $('#padrao_renomeio').val(),
                tipodoc: $('#tipodoc').val(),
                doctipo: $('#doctipo').val(),
                assunto: $('#assunto').val(),
                tratado_por: $('#tratado_por').val()
            };
        }

        function requestPresignedUploads(files) {
            return $.ajax({
                url: presignUploadUrl,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    source: 'sefaz_rh',
                    files: files.map(function(file) {
                        return {
                            filename: file.name,
                            content_type: file.type || 'application/octet-stream',
                            size: file.size || 0
                        };
                    })
                })
            });
        }

        function uploadFileToStorage(uploadInfo, file, updateOverallProgress, offsetBytes, totalBytes) {
            return new Promise(function(resolve, reject) {
                var xhr = new window.XMLHttpRequest();
                var settled = false;

                function finishWithError(message) {
                    if (settled) {
                        return;
                    }

                    settled = true;
                    reject(new Error(message));
                }

                function finishWithSuccess(payload) {
                    if (settled) {
                        return;
                    }

                    settled = true;
                    resolve(payload);
                }

                xhr.open(uploadInfo.method || 'PUT', uploadInfo.upload_url, true);
                xhr.timeout = 15000;

                Object.keys(uploadInfo.headers || {}).forEach(function(headerName) {
                    xhr.setRequestHeader(headerName, uploadInfo.headers[headerName]);
                });

                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        updateOverallProgress(offsetBytes + e.loaded, totalBytes);
                    }
                });

                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        updateOverallProgress(offsetBytes + file.size, totalBytes);
                        finishWithSuccess({
                            original_name: uploadInfo.original_name,
                            stored_name: uploadInfo.stored_name,
                            content_type: uploadInfo.content_type,
                            size: file.size
                        });
                        return;
                    }

                    finishWithError('Falha ao enviar o arquivo ' + uploadInfo.original_name + ' para o storage. HTTP ' + xhr.status + '.');
                };

                xhr.onerror = function() {
                    finishWithError('Falha de rede ao enviar o arquivo ' + uploadInfo.original_name + ' para o storage.');
                };

                xhr.onabort = function() {
                    finishWithError('Upload cancelado ao enviar o arquivo ' + uploadInfo.original_name + ' para o storage.');
                };

                xhr.ontimeout = function() {
                    finishWithError('Tempo esgotado ao enviar o arquivo ' + uploadInfo.original_name + ' para o storage.');
                };

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 0) {
                        finishWithError('Falha de rede ao enviar o arquivo ' + uploadInfo.original_name + ' para o storage.');
                    }
                };

                xhr.send(file);
            });
        }

        function confirmDirectUploads(uploadedFiles) {
            return $.ajax({
                url: completeUploadUrl,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    source: 'sefaz_rh',
                    files: uploadedFiles
                })
            });
        }

        function finalizeDirectUpload(payload) {
            return $.ajax({
                url: sefazRhDirectFinalizeUrl,
                type: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify(payload)
            });
        }

        async function handleDirectUpload() {
            var files = getSelectedFiles();

            if (!files.length) {
                throw new Error('Selecione ao menos um arquivo para upload.');
            }

            var payload = buildDirectUploadPayload();
            var totalBytes = files.reduce(function(sum, file) {
                return sum + (file.size || 0);
            }, 0);
            var uploadedBytes = 0;

            function updateOverallProgress(loadedBytes, bytesTotal) {
                if (bytesTotal <= 0) {
                    setProgressState('Enviando ao storage...', 10, true);
                    return;
                }

                var percent = Math.round((loadedBytes / bytesTotal) * 85);
                setProgressState(percent + '%', percent, false);
            }

            setProgressState('Solicitando URLs assinadas...', 5, true);
            var presignResponse = await requestPresignedUploads(files);

            if (!presignResponse || !presignResponse.success || !Array.isArray(presignResponse.uploads)) {
                throw new Error('Resposta invalida ao solicitar URLs assinadas.');
            }

            if (presignResponse.uploads.length !== files.length) {
                throw new Error('Quantidade de URLs assinadas diferente da quantidade de arquivos selecionados.');
            }

            var uploadedFiles = [];
            for (var index = 0; index < presignResponse.uploads.length; index += 1) {
                var currentFile = files[index];
                var currentUpload = presignResponse.uploads[index];
                setProgressState('Enviando ' + (index + 1) + '/' + files.length + '...', Math.round((uploadedBytes / Math.max(totalBytes, 1)) * 85), true);
                var result = await uploadFileToStorage(currentUpload, currentFile, updateOverallProgress, uploadedBytes, Math.max(totalBytes, 1));
                uploadedBytes += currentFile.size || 0;
                uploadedFiles.push(result);
            }

            setProgressState('Confirmando uploads no storage...', 90, true);
            var completeResponse = await confirmDirectUploads(uploadedFiles);

            if (!completeResponse || !completeResponse.success || !Array.isArray(completeResponse.confirmed)) {
                throw new Error((completeResponse && completeResponse.error) || 'Falha ao confirmar os uploads no storage.');
            }

            payload.files = completeResponse.confirmed.map(function(item, fileIndex) {
                return {
                    original_name: uploadedFiles[fileIndex] ? uploadedFiles[fileIndex].original_name : item.filename,
                    stored_name: item.stored_name || item.filename,
                    content_type: item.content_type || 'application/octet-stream',
                    size: item.content_length || null
                };
            });

            setProgressState('Finalizando registros no sistema...', 95, true);
            return finalizeDirectUpload(payload);
        }

        function createFallbackFormData(filesChunk) {
            var formElement = $('#uploadForm')[0];
            var formData = new FormData(formElement);
            formData.delete('files[]');
            formData.delete('files');
            filesChunk.forEach(function(file) {
                formData.append('files[]', file);
            });
            return formData;
        }

        function submitFallbackUploadFile(file, idx) {
            var formData = createFallbackFormData([file]);

            return $.ajax({
                url: sefazRhUploadUrl,
                type: 'POST',
                data: formData,
                async: true,
                cache: false,
                contentType: false,
                processData: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = Math.round((e.loaded / e.total) * 100);
                            $('#file_prog_' + idx).css('width', percent + '%').attr('aria-valuenow', percent).text(percent + '%');
                        }
                    });
                    return xhr;
                },
                beforeSend: function() {
                    $('#file_prog_' + idx).css('width', '5%').text('Iniciando...');
                    $('#file_item_' + idx + ' .file-status').text('Enviando pelo backend...');
                    $('#file_item_' + idx).removeClass('list-group-item-danger list-group-item-success');
                    $('#file_item_' + idx + ' .file-name').css('color', '');
                }
            });
        }

        async function submitFallbackUpload(files) {
            var selectedFiles = Array.isArray(files) ? files : getSelectedFiles();

            if (!selectedFiles.length) {
                throw new Error('Selecione ao menos um arquivo para upload.');
            }

            var importedTotal = 0;
            $('#uploadForm button[type="submit"]').prop('disabled', true);

            for (var idx = 0; idx < selectedFiles.length; idx += 1) {
                var file = selectedFiles[idx];
                var overallPercent = Math.round((idx / selectedFiles.length) * 100);
                setProgressState('Enviando arquivos: ' + (idx + 1) + '/' + selectedFiles.length, overallPercent, true);

                try {
                    var response = await submitFallbackUploadFile(file, idx);
                    var importCount = parseImportedCount(response);
                    
                    if (importCount > 0) {
                        importedTotal += importCount;
                        $('#file_item_' + idx).addClass('list-group-item-success');
                        $('#file_item_' + idx + ' .file-status').html('<b style="color: green;">Enviado Com Sucesso</b>');
                        $('#file_prog_' + idx).css('width', '100%').text('100%').removeClass('progress-bar-striped progress-bar-animated');
                    } else {
                        throw new Error('Arquivo não aceito ou formato inválido pelo servidor.');
                    }
                } catch (jqXHR) {
                    var xhrMsg = jqXHR && jqXHR.responseText ? jqXHR.responseText : (jqXHR && jqXHR.message ? jqXHR.message : 'Falha na comunicação com o servidor/assinador.');
                    $('#file_item_' + idx).addClass('list-group-item-danger');
                    $('#file_item_' + idx + ' .file-name').css('color', 'red');
                    $('#file_item_' + idx + ' .file-status').html('<b style="color: red;">Não Foi possível enviar o arquivo</b> (' + xhrMsg + ')');
                    $('#file_prog_' + idx).css('width', '100%').addClass('bg-danger').text('Erro');
                }
            }

            setProgressState('Upload Concluído.', 100, false);
            return importedTotal;
        }

        $('#numero_select').on('change', function() {
            syncNumeroSelection();
            atualizarStatusArquivos();
        });

        $('#padrao_renomeio').on('change', function() {
            atualizarStatusArquivos();
        });

        $('#files').on('change', function() {
            var files = getSelectedFiles();
            var label = $(this).next('.custom-file-label');
            if (files.length > 0) {
                label.text(files.length + ' arquivos selecionados');
            } else {
                label.text('Escolha os arquivos...');
            }
            atualizarStatusArquivos();
        });

        // Carregar opções de setor quando a secretaria é selecionada
        $('#secretaria').change(function() {
            var secretariaId = $(this).val();
            resetNumeroSelect();
            atualizarStatusArquivos();

            if (secretariaId) {
                $.ajax({
                    url: 'get_setores.php',
                    type: 'GET',
                    data: { secretaria_id: secretariaId },
                    success: function(data) {
                        $('#setor').html(data);
                        resetNumeroSelect();
                        atualizarStatusArquivos();
                    }
                });
            } else {
                $('#setor').html('<option value="">Selecione o Setor</option>');
                resetNumeroSelect();
                atualizarStatusArquivos();
            }
        });

        $('#setor, #tipo').change(function() {
            loadNumeroOptions();
        });

        function parseImportedCount(response) {
            if (!response) return 0;
            if (typeof response === 'object' && response.total_importados !== undefined) {
                return parseInt(response.total_importados, 10);
            }
            var text = typeof response === 'string' ? response : (response.message || JSON.stringify(response));
            var match = text.match(/Total de arquivos importados:\s*(\d+)/i);
            if (match && match[1]) {
                return parseInt(match[1], 10);
            }
            return 0;
        }

        // Atualizar a barra de progresso durante o upload
        $('#uploadForm').submit(function(event) {
            event.preventDefault();

            syncNumeroSelection();
            atualizarStatusArquivos();

            if (!$('#numero_hidden').val() || !$('#id_registro').val()) {
                var errorMessage = '<span style="color: red; font-weight: bold;">Erro ao carregar arquivos. Detalhes: selecione uma Caixa/Pasta válida da lista antes de enviar.</span>';
                $('#status').html(errorMessage);
                $('#statusInline').html(errorMessage);
                return;
            }

            // Impedir envio se houver erros de compatibilidade de nomes na listagem
            if ($('#arquivos_selecionados_info .text-danger, #arquivos_selecionados_info li').length > 0 || $('#arquivos_selecionados_info').text().indexOf('incompatível') !== -1) {
                var fixErrorsMessage = '<span style="color: red; font-weight: bold;">Erro: Existem nomes de arquivos que não estão de acordo com o padrão de renomeio selecionado. Corrija-os e tente novamente.</span>';
                $('#status').html(fixErrorsMessage);
                $('#statusInline').html(fixErrorsMessage);
                return;
            }

            $('#status').empty();
            $('#statusInline').empty();
            setProgressState('Preparando...', 0, true);
            $('.progress').show();

            if (directUploadEnabled) {
                $('#uploadForm button[type="submit"]').prop('disabled', true);
                var selectedFiles = getSelectedFiles();

                handleDirectUpload().then(function(response) {
                    var totalSelecionados = selectedFiles.length;
                    var importedTotal = parseImportedCount(response) || totalSelecionados;
                    var finalMessage = '';
                    if (importedTotal === totalSelecionados) {
                        finalMessage = '<span style="color: green; font-weight: bold;">Sucesso! ' + importedTotal + ' de ' + totalSelecionados + ' arquivos foram salvos com sucesso.</span>';
                    } else {
                        finalMessage = '<span style="color: red; font-weight: bold;">Erro: Apenas ' + importedTotal + ' de ' + totalSelecionados + ' arquivos foram salvos.</span>';
                    }
                    $('#status').html(finalMessage);
                    $('#statusInline').html(finalMessage);
                    setProgressState('100%', 100, false);
                    setTimeout(function() {
                        $('.progress').hide();
                        $('#uploadForm')[0].reset();
                        resetNumeroSelect();
                        $('#arquivos_selecionados_info').hide().empty();
                        
                    }, 1500);
                }).catch(function(error) {
                    var directErrorMessage = 'Falha no upload direto. Tentando envio pelo assinador...';
                    $('#status').html(directErrorMessage);
                    $('#statusInline').html(directErrorMessage);
                    setProgressState('Alternando para o assinador...', 5, true);

                    submitFallbackUpload(selectedFiles).then(function(importedTotal) {
                        var totalSelecionados = selectedFiles.length;
                        var finalMessage = '';
                        if (importedTotal === totalSelecionados) {
                            finalMessage = '<span style="color: green; font-weight: bold;">Sucesso! Todos os ' + importedTotal + ' arquivos foram salvos com sucesso.</span>';
                        } else {
                            finalMessage = '<span style="color: red; font-weight: bold;">Concluído com falhas: Apenas ' + importedTotal + ' de ' + totalSelecionados + ' arquivos foram salvos. Feche esta janela, corrija os arquivos em vermelho e tente novamente.</span>';
                        }
                        $('#status').html(finalMessage);
                        $('#statusInline').html(finalMessage);
                        setProgressState('100%', 100, false);
                        setTimeout(function() {
                            $('.progress').hide();
                            if (importedTotal === totalSelecionados) {
                                $('#uploadForm')[0].reset();
                                resetNumeroSelect();
                                $('#upload_progress_container').hide();
                                $('#arquivos_selecionados_info').hide().empty();
                            }
                        }, 3000);
                    }).catch(function(err) {
                        var msg = err && err.message ? err.message : 'Falha crítica na alternância para o assinador.';
                        var fallbackErrorMessage = '<span style="color: red; font-weight: bold;">' + msg + '</span>';
                        $('#status').html(fallbackErrorMessage);
                        $('#statusInline').html(fallbackErrorMessage);
                    });
                }).finally(function() {
                    $('#uploadForm button[type="submit"]').prop('disabled', false);
                    $('#files').val('');
                });

                return;
            }

            var selectedFiles = getSelectedFiles();
            submitFallbackUpload(selectedFiles).then(function(importedTotal) {
                var totalSelecionados = selectedFiles.length;
                var finalMessage = '';
                if (importedTotal === totalSelecionados) {
                    finalMessage = '<span style="color: green; font-weight: bold;">Sucesso! Todos os ' + importedTotal + ' arquivos foram salvos com sucesso.</span>';
                } else {
                    finalMessage = '<span style="color: red; font-weight: bold;">Concluído com falhas: Apenas ' + importedTotal + ' de ' + totalSelecionados + ' arquivos foram salvos. Feche esta janela, corrija os arquivos em vermelho e tente novamente.</span>';
                }
                $('#status').html(finalMessage);
                $('#statusInline').html(finalMessage);
                setProgressState('100%', 100, false);
                setTimeout(function() {
                    $('.progress').hide();
                    if (importedTotal === totalSelecionados) {
                        $('#uploadForm')[0].reset();
                        resetNumeroSelect();
                        $('#upload_progress_container').hide();
                        $('#arquivos_selecionados_info').hide().empty();
                    }
                }, 3000);
            }).catch(function(err) {
                var msg = err && err.message ? err.message : 'Falha crítica ao tentar enviar um ou mais arquivos.';
                var errorMessage = '<span style="color: red; font-weight: bold;">' + msg + '</span>';
                $('#status').html(errorMessage);
                $('#statusInline').html(errorMessage);
            }).finally(function() {
                $('#uploadForm button[type="submit"]').prop('disabled', false);
                $('#files').val('');
            });
        });
    });
    </script>

    <footer class="main-footer no-bdr fixed-btm">
        <div class="container">
            © ECM Tecnologia e Soluções 2025
        </div>
    </footer>
    
</body>
</html>