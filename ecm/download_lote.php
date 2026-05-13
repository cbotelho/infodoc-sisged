<?php
?><!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Download em Massa</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="fonts/icomoon/icomoon.css" />
    <link rel="stylesheet" href="css/main.min.css" />
    <style>
        .container { max-width: 100%; }
        .table-container { max-height: 520px; overflow-y: auto; }
        .clickable-row { cursor: pointer; }
        .progress { height: 28px; }
        .muted-hint { color: #6c757d; font-size: 12px; }
        .sticky-actions { position: sticky; top: 0; background: #fff; z-index: 5; padding: 10px 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-12">
            <span><img src="../images/logo_ecm.png" width="100" height="64" alt="ECM"></span>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">Download em Massa de Documentos</h4>
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label for="source">Fluxo</label>
                            <select class="form-control" id="source">
                                <option value="sefaz_rh">SEFAZ RH</option>
                                <option value="ged">GED principal</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="secretaria">Secretaria</label>
                            <select class="form-control" id="secretaria">
                                <option value="">Selecione</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="setor">Setor</label>
                            <select class="form-control" id="setor">
                                <option value="">Selecione</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="tipo">Tipo</label>
                            <select class="form-control" id="tipo">
                                <option value="">Selecione</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="caixa">Caixa/Pasta</label>
                            <select class="form-control" id="caixa">
                                <option value="">Selecione</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row align-items-end">
                        <div class="form-group col-md-8">
                            <label for="destinoNome">Nome do arquivo ZIP</label>
                            <input type="text" class="form-control" id="destinoNome" placeholder="Ex.: documentos_sefaz_rh.zip">
                            <div class="muted-hint">O navegador vai perguntar onde salvar. Este nome será sugerido no download.</div>
                        </div>
                        <div class="form-group col-md-4">
                            <button type="button" class="btn btn-primary" id="carregarArquivos">Carregar arquivos</button>
                        </div>
                    </div>

                    <div class="sticky-actions">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="selecionarTodos">Selecionar todos</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="limparSelecao">Limpar seleção</button>
                        <button type="button" class="btn btn-success btn-sm" id="baixarSelecionados">Baixar selecionados</button>
                    </div>

                    <div class="table-container mt-3">
                        <table class="table table-striped table-sm" id="documentosTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="marcarTodosTopo"></th>
                                    <th>Arquivo</th>
                                    <th>Campo 1</th>
                                    <th>Campo 2</th>
                                    <th>Campo 3</th>
                                    <th>Tipo</th>
                                    <th>Páginas</th>
                                    <th>Extra</th>
                                </tr>
                            </thead>
                            <tbody id="documentosBody">
                                <tr><td colspan="8" class="text-center text-muted">Nenhum arquivo carregado.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <div class="progress" style="display:none;" id="downloadProgressWrapper">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="downloadProgressBar" role="progressbar" style="width:0%">0%</div>
                        </div>
                        <div id="status" class="mt-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
$(function() {
    let currentDocuments = [];

    function getApiParams(extra) {
        return Object.assign({
            source: $('#source').val(),
            secretaria_id: $('#secretaria').val(),
            setor_id: $('#setor').val(),
            tipo_id: $('#tipo').val(),
            caixa_id: $('#caixa').val()
        }, extra || {});
    }

    function setStatus(message, isError) {
        $('#status').html('<div class="alert ' + (isError ? 'alert-danger' : 'alert-info') + ' mb-0">' + message + '</div>');
    }

    function clearStatus() {
        $('#status').empty();
    }

    function showProgress(message) {
        $('#downloadProgressWrapper').show();
        $('#downloadProgressBar')
            .addClass('progress-bar-animated progress-bar-striped')
            .css('width', '100%')
            .attr('aria-valuenow', '100')
            .text(message || 'Preparando ZIP...');
    }

    function updateProgress(percent) {
        const safePercent = Math.max(0, Math.min(100, percent));
        $('#downloadProgressBar')
            .removeClass('progress-bar-animated')
            .addClass('progress-bar-striped')
            .css('width', safePercent + '%')
            .attr('aria-valuenow', safePercent)
            .text(safePercent + '%');
    }

    function hideProgress() {
        $('#downloadProgressWrapper').hide();
        $('#downloadProgressBar')
            .addClass('progress-bar-animated progress-bar-striped')
            .css('width', '0%')
            .attr('aria-valuenow', '0')
            .text('0%');
    }

    function resetSelect($element, placeholder) {
        $element.html('<option value="">' + placeholder + '</option>');
    }

    function loadSecretarias() {
        $.getJSON('download_lote_api.php', { action: 'secretarias', source: $('#source').val() })
            .done(function(response) {
                const $secretaria = $('#secretaria');
                resetSelect($secretaria, 'Selecione');
                response.items.forEach(function(item) {
                    $secretaria.append('<option value="' + item.id + '">' + item.name + '</option>');
                });
            })
            .fail(function() {
                setStatus('Falha ao carregar secretarias.', true);
            });
    }

    function loadTipos() {
        $.getJSON('download_lote_api.php', { action: 'tipos', source: $('#source').val() })
            .done(function(response) {
                const $tipo = $('#tipo');
                resetSelect($tipo, 'Selecione');
                response.items.forEach(function(item) {
                    $tipo.append('<option value="' + item.id + '">' + item.name + '</option>');
                });
            })
            .fail(function() {
                setStatus('Falha ao carregar tipos.', true);
            });
    }

    function loadSetores() {
        resetSelect($('#setor'), 'Selecione');
        resetSelect($('#caixa'), 'Selecione');
        clearDocuments();
        const secretariaId = $('#secretaria').val();
        if (!secretariaId) {
            return;
        }
        $.getJSON('download_lote_api.php', { action: 'setores', source: $('#source').val(), secretaria_id: secretariaId })
            .done(function(response) {
                const $setor = $('#setor');
                response.items.forEach(function(item) {
                    $setor.append('<option value="' + item.id + '">' + item.name + '</option>');
                });
            })
            .fail(function(xhr) {
                setStatus(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Falha ao carregar setores.', true);
            });
    }

    function loadCaixas() {
        resetSelect($('#caixa'), 'Selecione');
        clearDocuments();
        const params = getApiParams({ action: 'caixas' });
        if (!params.secretaria_id || !params.setor_id || !params.tipo_id) {
            return;
        }

        $.getJSON('download_lote_api.php', params)
            .done(function(response) {
                const $caixa = $('#caixa');
                response.items.forEach(function(item) {
                    $caixa.append('<option value="' + item.id + '">' + item.id + ' - ' + item.numero + '</option>');
                });
            })
            .fail(function(xhr) {
                setStatus(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Falha ao carregar caixas/pastas.', true);
            });
    }

    function clearDocuments() {
        currentDocuments = [];
        $('#marcarTodosTopo').prop('checked', false);
        $('#documentosBody').html('<tr><td colspan="8" class="text-center text-muted">Nenhum arquivo carregado.</td></tr>');
    }

    function renderDocuments(items) {
        currentDocuments = items;
        if (!items.length) {
            clearDocuments();
            return;
        }

        const rows = items.map(function(item) {
            return '<tr>' +
                '<td><input type="checkbox" class="document-check" value="' + item.id + '"></td>' +
                '<td>' + escapeHtml(item.arquivo || '') + '</td>' +
                '<td>' + escapeHtml(item.campo1 || '') + '</td>' +
                '<td>' + escapeHtml(item.campo2 || '') + '</td>' +
                '<td>' + escapeHtml(item.campo3 || '') + '</td>' +
                '<td>' + escapeHtml(item.tipo_nome || '') + '</td>' +
                '<td>' + escapeHtml(String(item.paginas || '')) + '</td>' +
                '<td>' + escapeHtml(item.extra || '') + '</td>' +
                '</tr>';
        }).join('');

        $('#documentosBody').html(rows);
    }

    function loadDocuments() {
        clearStatus();
        const params = getApiParams({ action: 'documentos' });
        if (!params.caixa_id) {
            setStatus('Selecione uma Caixa/Pasta.', true);
            return;
        }

        $.getJSON('download_lote_api.php', params)
            .done(function(response) {
                renderDocuments(response.items || []);
                setStatus((response.items || []).length + ' arquivo(s) carregado(s).', false);
            })
            .fail(function(xhr) {
                setStatus(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Falha ao carregar documentos.', true);
            });
    }

    function getSelectedDocumentIds() {
        return $('.document-check:checked').map(function() { return $(this).val(); }).get();
    }

    function buildZipFileName() {
        const raw = ($('#destinoNome').val() || '').trim();
        if (raw) {
            return raw.toLowerCase().endsWith('.zip') ? raw : raw + '.zip';
        }
        const source = $('#source').val();
        const caixaText = ($('#caixa option:selected').text() || 'documentos').replace(/[^A-Za-z0-9_-]+/g, '_');
        return 'download_' + source + '_' + caixaText + '.zip';
    }

    function downloadSelected() {
        clearStatus();
        const selectedIds = getSelectedDocumentIds();
        if (!selectedIds.length) {
            setStatus('Selecione ao menos um PDF na grade.', true);
            return;
        }

        const formData = new FormData();
        formData.append('source', $('#source').val());
        formData.append('caixa_id', $('#caixa').val());
        selectedIds.forEach(function(id) {
            formData.append('document_ids[]', id);
        });

        showProgress('Preparando ZIP...');
        $('#baixarSelecionados').prop('disabled', true);

        $.ajax({
            url: 'download_lote_zip.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhrFields: {
                responseType: 'blob'
            },
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.addEventListener('loadstart', function() {
                    showProgress('Iniciando download...');
                });
                xhr.addEventListener('progress', function(event) {
                    if (event.lengthComputable) {
                        const percent = Math.round((event.loaded / event.total) * 100);
                        updateProgress(percent);
                    } else {
                        showProgress('Recebendo arquivo...');
                    }
                });
                return xhr;
            },
            success: function(blob, _status, xhr) {
                updateProgress(100);
                const fileName = buildZipFileName();
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);
                setStatus('Download preparado com sucesso.', false);
            },
            error: function(xhr) {
                hideProgress();
                if (xhr.response instanceof Blob) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        setStatus(reader.result || 'Falha ao baixar os documentos.', true);
                    };
                    reader.readAsText(xhr.response);
                    return;
                }
                setStatus('Falha ao baixar os documentos.', true);
            },
            complete: function() {
                setTimeout(function() {
                    hideProgress();
                    $('#baixarSelecionados').prop('disabled', false);
                }, 2500);
            }
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $('#source').on('change', function() {
        clearStatus();
        resetSelect($('#secretaria'), 'Selecione');
        resetSelect($('#setor'), 'Selecione');
        resetSelect($('#tipo'), 'Selecione');
        resetSelect($('#caixa'), 'Selecione');
        clearDocuments();
        loadSecretarias();
        loadTipos();
    });

    $('#secretaria').on('change', loadSetores);
    $('#setor, #tipo').on('change', loadCaixas);
    $('#caixa').on('change', clearDocuments);
    $('#carregarArquivos').on('click', loadDocuments);
    $('#selecionarTodos').on('click', function() {
        $('.document-check').prop('checked', true);
        $('#marcarTodosTopo').prop('checked', true);
    });
    $('#limparSelecao').on('click', function() {
        $('.document-check').prop('checked', false);
        $('#marcarTodosTopo').prop('checked', false);
    });
    $('#marcarTodosTopo').on('change', function() {
        $('.document-check').prop('checked', $(this).is(':checked'));
    });
    $('#baixarSelecionados').on('click', downloadSelected);

    loadSecretarias();
    loadTipos();
});
</script>
</body>
</html>
