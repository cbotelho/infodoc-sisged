<?php

// Habilitar a exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pythonServicePublicUrl = getenv('PYTHON_SERVICE_PUBLIC_URL');
$gedUploadUrl = 'upload.php';
$presignUploadUrl = '';
$completeUploadUrl = '';
$gedDirectFinalizeUrl = '';

if ($pythonServicePublicUrl !== false && trim((string) $pythonServicePublicUrl) !== '') {
    $pythonBaseUrl = rtrim((string) $pythonServicePublicUrl, '/');
    $gedUploadUrl = $pythonBaseUrl . '/api/ged/upload';
    $presignUploadUrl = $pythonBaseUrl . '/api/uploads/presign';
    $completeUploadUrl = $pythonBaseUrl . '/api/uploads/complete';
    $gedDirectFinalizeUrl = $pythonBaseUrl . '/api/ged/upload/direct';
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
                <form id="uploadForm" action="<?php echo htmlspecialchars($gedUploadUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" id="id_registro" name="id_registro">
                    <input type="hidden" id="numero_hidden" name="numero">
                    <div class="form-row">
                        <div class="form-group col-md-3">
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
                        <div class="form-group col-md-3">
                            <label for="setor">* Setor</label>
                            <select class="form-control" id="setor" name="setor" required>
                                <option value="">Selecione o Setor</option>
                                <!-- Opções serão carregadas via AJAX -->
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="padrao_renomeio">* Padrão de Renomeio</label>
                            <select class="form-control" id="padrao_renomeio" name="padrao_renomeio" required>
                                <option value="">Selecione o padrão</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="tipodoc">* Tipo de Documentos</label>
                            <select class="form-control" id="tipodoc" name="tipodoc" required>
                                <option value="">Selecione Tipo Documento</option>
                                <option value="152">Público</option>
                                <option value="153">Privado</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="tipo">* Tipo de Arquivo</label>
                            <select class="form-control" id="tipo" name="tipo" required>
                                <option value="118">Caixa</option>
                                <option value="117">Pasta</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="numero_select">* Nº da Caixa/Pasta</label>
                            <select class="form-control" id="numero_select" required>
                                <option value="">Selecione o Nº da Caixa/Pasta</option>
                            </select>
                        </div>
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
        <div class="row mt-4">
            <div class="col-12">
                <h5 class="mb-4">Registros Salvos</h5>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Processo</th>
                                <th>Interessado</th>
                                <th>Assunto</th>
                                <th>Tipo</th>
                                <th>Documento</th>
                                <th>Páginas</th>
                            </tr>
                        </thead>
                        <tbody id="registros"></tbody>
                    </table>
                </div>
                <nav>
                    <ul class="pagination" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- jQuery e Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
    $(document).ready(function() {
        var gedUploadUrl = <?php echo json_encode($gedUploadUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var presignUploadUrl = <?php echo json_encode($presignUploadUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var completeUploadUrl = <?php echo json_encode($completeUploadUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var gedDirectFinalizeUrl = <?php echo json_encode($gedDirectFinalizeUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        var directUploadEnabled = !!(presignUploadUrl && completeUploadUrl && gedDirectFinalizeUrl);

        // Carregar registros ao abrir a página
        loadRegistros(1);

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

            if (!secretariaId || !setorId || !tipoId) {
                return;
            }

            $.ajax({
                url: 'get_numeros.php',
                type: 'GET',
                data: {
                    format: 'select',
                    secretaria_id: secretariaId,
                    setor_id: setorId,
                    tipo_id: tipoId
                }
            }).done(function(data) {
                $('#numero_select').html(data);
            }).fail(function() {
                resetNumeroSelect();
            });
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
                tratado_por: $('#tratado_por').val()
            };
        }

        function requestPresignedUploads(files) {
            return $.ajax({
                url: presignUploadUrl,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    source: 'ged',
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
                    source: 'ged',
                    files: uploadedFiles
                })
            });
        }

        function finalizeDirectUpload(payload) {
            return $.ajax({
                url: gedDirectFinalizeUrl,
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
                    size: item.content_length || null,
                    content_length: item.content_length || null
                };
            });

            setProgressState('Finalizando registros no sistema...', 95, true);
            return finalizeDirectUpload(payload);
        }

        function submitFallbackUpload() {
            var formData = new FormData($('#uploadForm')[0]);

            return $.ajax({
                url: gedUploadUrl,
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
                            $('#progressBar').css('width', percent + '%').attr('aria-valuenow', percent).text(percent + '%');
                        }
                    });
                    return xhr;
                },
                beforeSend: function() {
                    setProgressState('Enviando pelo assinador...', 0, true);
                    $('.progress').show();
                }
            });
        }

        $('#numero_select').on('change', function() {
            syncNumeroSelection();
        });

        // Carregar opções de setor quando a secretaria é selecionada
        $('#secretaria').change(function() {
            var secretariaId = $(this).val();
            resetNumeroSelect();

            if (secretariaId) {
                $.ajax({
                    url: 'get_setores.php',
                    type: 'GET',
                    data: { secretaria_id: secretariaId },
                    success: function(data) {
                        $('#setor').html(data);
                        resetNumeroSelect();
                    }
                });
            } else {
                $('#setor').html('<option value="">Selecione o Setor</option>');
                resetNumeroSelect();
            }
        });

        $('#setor, #tipo').change(function() {
            loadNumeroOptions();
        });

        // Atualizar a barra de progresso durante o upload
        $('#uploadForm').submit(function(event) {
            event.preventDefault();

            syncNumeroSelection();

            if (!$('#numero_hidden').val() || !$('#id_registro').val()) {
                var invalidRegistroMessage = 'Erro ao carregar arquivos. Detalhes: selecione uma Caixa/Pasta valida da lista antes de enviar.';
                $('#status').html(invalidRegistroMessage);
                $('#statusInline').html(invalidRegistroMessage);
                return;
            }

            $('#status').empty();
            $('#statusInline').empty();
            setProgressState('Preparando...', 0, true);
            $('.progress').show();

            if (directUploadEnabled) {
                $('#uploadForm button[type="submit"]').prop('disabled', true);

                handleDirectUpload().then(function(response) {
                    var successMessage = response && response.message ? response.message : 'Arquivos carregados com sucesso!';
                    $('#status').html(successMessage);
                    $('#statusInline').html(successMessage);
                    setProgressState('100%', 100, false);
                    setTimeout(function() {
                        $('.progress').hide();
                        $('#uploadForm')[0].reset();
                        resetNumeroSelect();
                        loadRegistros(1);
                    }, 1000);
                }).catch(function(error) {
                    var directErrorMessage = 'Falha no upload direto. Tentando envio pelo assinador...';
                    $('#status').html(directErrorMessage);
                    $('#statusInline').html(directErrorMessage);
                    setProgressState('Alternando para o assinador...', 5, true);

                    submitFallbackUpload().done(function(response) {
                        $('#status').html(response);
                        $('#statusInline').html(response);
                        setProgressState('100%', 100, false);
                        setTimeout(function() {
                            $('.progress').hide();
                            $('#uploadForm')[0].reset();
                            resetNumeroSelect();
                            loadRegistros(1);
                        }, 1000);
                    }).fail(function(xhr) {
                        var fallbackErrorMessage = 'Erro ao carregar arquivos. Detalhes: ' + (error && error.message ? error.message : 'falha no fluxo de upload direto.') + ' Fallback no assinador: ' + xhr.status + ': ' + xhr.responseText;
                        $('#status').html(fallbackErrorMessage);
                        $('#statusInline').html(fallbackErrorMessage);
                    });
                }).finally(function() {
                    $('#uploadForm button[type="submit"]').prop('disabled', false);
                    $('#files').val('');
                });

                return;
            }

            submitFallbackUpload().done(function(response) {
                $('#status').html(response);
                $('#statusInline').html(response);
                setProgressState('100%', 100, false);
                setTimeout(function() {
                    $('.progress').hide();
                    $('#uploadForm')[0].reset();
                    resetNumeroSelect();
                    loadRegistros(1);
                }, 1000);
            }).fail(function(xhr) {
                var errorMessage = 'Erro ao carregar arquivos. Detalhes: ' + xhr.status + ': ' + xhr.responseText;
                $('#status').html(errorMessage);
                $('#statusInline').html(errorMessage);
            }).always(function() {
                $('#files').val('');
            });
        });

        // Carregar registros com paginação
        function loadRegistros(page) {
            $.ajax({
                url: 'load_registros.php',
                type: 'GET',
                data: { page: page },
                dataType: 'json',
                success: function(data) {
                    $('#registros').html(data.records);
                    $('#pagination').html(data.pagination);
                }
            });
        }

        // Navegação na paginação
        $(document).on('click', '.page-link', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            loadRegistros(page);
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
