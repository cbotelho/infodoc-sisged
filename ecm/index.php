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
            <div class="col-md-6">
                <span><img src="../images/logo_ecm.png" width="100px" height="64px"></span>
                <!-- Formulário de Upload -->
                <form id="uploadForm" action="upload.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" id="id_registro" name="id_registro">
                    <div class="form-group">
                        <label for="secretaria">* Secretaria</label>
                        <select class="form-control" id="secretaria" name="secretaria" required>
                            <option value="">Selecione a Secretaria</option>
                            <?php
                            include '../includes/db.php';
                            $stmt = $pdo->query("SELECT id, field_233 FROM app_entity_26");
                            while ($row = $stmt->fetch()) {
                                echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['field_233']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="setor">* Setor</label>
                        <select class="form-control" id="setor" name="setor" required>
                            <option value="">Selecione o Setor</option>
                            <!-- Opções serão carregadas via AJAX -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tipo">* Tipo</label>
                        <select class="form-control" id="tipo" name="tipo" required>
                            <option value="118">Caixa</option>
                            <option value="117">Pasta</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="numero">* Nº da Caixa/Pasta</label>
                        <input type="text" class="form-control" id="numero" name="numero" required>
                    </div>

                    <div class="form-group">
                        <label for="tratado_por">* Tratado Por:</label>
                        <select class="form-control" id="tratado_por" name="tratado_por" required>
                            <option value="">Selecione quem Tratou</option>
                            <?php
                            include '../includes/db.php';
                            $stmt = $pdo->query("SELECT id, field_12 FROM app_entity_1");
                            while ($row = $stmt->fetch()) {
                                echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['field_12']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="custom-file mb-3">
                        <input type="file" class="custom-file-input" id="files" name="files[]" multiple required>
                        <label class="custom-file-label" for="files">* Escolha os arquivos...</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar Arquivos</button>
                </form>

                <!-- Barra de Progresso -->
                <div class="progress mt-4" style="display: none;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <!-- Mensagens de Status -->
                <div id="status" class="mt-3"></div>
            </div>
            <div class="col-md-6">
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

        // Carregar opções de setor quando a secretaria é selecionada
        $('#secretaria').change(function() {
            var secretariaId = $(this).val();
            if (secretariaId) {
                $.ajax({
                    url: 'get_setores.php',
                    type: 'GET',
                    data: { secretaria_id: secretariaId },
                    success: function(data) {
                        $('#setor').html(data);
                    }
                });
            } else {
                $('#setor').html('<option value="">Selecione o Setor</option>');
            }
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
                    $('#progressBar').css('width', '0%').attr('aria-valuenow', '0').text('0%');
                    $('.progress').show();
                },
                success: function(response) {
                    $('#status').html(response);
                    $('#progressBar').css('width', '100%').attr('aria-valuenow', '100').text('100%');
                    setTimeout(function() {
                        $('.progress').hide();
                        $('#uploadForm')[0].reset();
                        loadRegistros(1);
                    }, 1000);
                },
                error: function(xhr) {
                    $('#status').html('Erro ao carregar arquivos. Detalhes: ' + xhr.status + ': ' + xhr.responseText);
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
            © ECM Tecnologia e Soluções 2024
        </div>
    </footer>
    
</body>
</html>
