<?php

// Habilitar a exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
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
                <form id="uploadForm" action="upload.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" id="id_registro" name="id_registro">
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
                            <label for="numero">* Nº da Caixa/Pasta</label>
                            <input type="text" class="form-control" id="numero" name="numero" list="numeroSugestoes" placeholder="Pesquise e selecione um número existente" autocomplete="off" required>
                            <datalist id="numeroSugestoes"></datalist>
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
        // Carregar registros ao abrir a página
        loadRegistros(1);

        var numeroRequest = null;

        function clearNumeroSuggestions() {
            $('#numeroSugestoes').empty();
        }

        function loadNumeroSuggestions() {
            var term = $('#numero').val().trim();
            var secretariaId = $('#secretaria').val();
            var setorId = $('#setor').val();
            var tipoId = $('#tipo').val();

            if (!secretariaId || !setorId || !tipoId || term.length < 2) {
                clearNumeroSuggestions();
                return;
            }

            if (numeroRequest) {
                numeroRequest.abort();
            }

            numeroRequest = $.ajax({
                url: 'get_numeros.php',
                type: 'GET',
                data: {
                    term: term,
                    format: 'datalist',
                    secretaria_id: secretariaId,
                    setor_id: setorId,
                    tipo_id: tipoId
                }
            }).done(function(data) {
                $('#numeroSugestoes').html(data);
            }).fail(function() {
                clearNumeroSuggestions();
            }).always(function() {
                numeroRequest = null;
            });
        }

        $('#numero').on('input', function() {
            $('#id_registro').val('');
            loadNumeroSuggestions();
        }).on('focus', function() {
            loadNumeroSuggestions();
        });

        // Carregar opções de setor quando a secretaria é selecionada
        $('#secretaria').change(function() {
            var secretariaId = $(this).val();
            $('#numero').val('');
            $('#id_registro').val('');

            if (secretariaId) {
                $.ajax({
                    url: 'get_setores.php',
                    type: 'GET',
                    data: { secretaria_id: secretariaId },
                    success: function(data) {
                        $('#setor').html(data);
                        clearNumeroSuggestions();
                    }
                });
            } else {
                $('#setor').html('<option value="">Selecione o Setor</option>');
                clearNumeroSuggestions();
            }
        });

        $('#setor, #tipo').change(function() {
            $('#numero').val('');
            $('#id_registro').val('');
            clearNumeroSuggestions();
        });

        // Atualizar a barra de progresso durante o upload
        $('#uploadForm').submit(function(event) {
            event.preventDefault();

            var formData = new FormData($(this)[0]);

            $.ajax({
                url: 'upload.php',
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
                    $('#status').empty();
                    $('#statusInline').empty();
                    $('#progressBar').css('width', '0%').attr('aria-valuenow', '0').text('0%');
                    $('.progress').show();
                },
                success: function(response) {
                    $('#status').html(response);
                    $('#statusInline').html(response);
                    $('#progressBar').css('width', '100%').attr('aria-valuenow', '100').text('100%');
                    setTimeout(function() {
                        $('.progress').hide();
                        $('#uploadForm')[0].reset();
                        loadRegistros(1);
                    }, 1000);
                },
                error: function(xhr) {
                    var errorMessage = 'Erro ao carregar arquivos. Detalhes: ' + xhr.status + ': ' + xhr.responseText;
                    $('#status').html(errorMessage);
                    $('#statusInline').html(errorMessage);
                },
                complete: function() {
                    $('#files').val('');
                }
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
