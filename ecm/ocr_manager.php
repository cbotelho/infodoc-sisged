<?php
include '../includes/db.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciador OCR</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="fonts/icomoon/icomoon.css" />
    <link rel="stylesheet" href="css/main.min.css" />
    <style>
        .container { max-width: 100%; }
        .table-wrap { max-height: 520px; overflow-y: auto; }
        .badge-status { min-width: 80px; }
        .mono { font-family: Consolas, monospace; font-size: 12px; }
        .progress { height: 22px; }
        .row-processing { background: #fff7df; }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-12 d-flex align-items-center justify-content-between">
            <div>
                <h3 class="mb-1">OCR sob demanda</h3>
                <small class="text-muted">Selecione registros sem OCR, enfileire e processe em lote.</small>
            </div>
            <div>
                <a class="btn btn-outline-primary btn-sm" href="index.php">Voltar GED</a>
                <a class="btn btn-outline-primary btn-sm" href="Envio_sefaz_rh.php">Voltar RH</a>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="col-md-3">
                    <label for="source">Origem</label>
                    <select id="source" class="form-control">
                        <option value="ged">GED</option>
                        <option value="sefaz_rh">SEFAZ RH</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="limit">Limite</label>
                    <input id="limit" type="number" min="1" max="1000" value="200" class="form-control" />
                </div>
                <div class="col-md-2">
                    <label for="created_by">Criado por (fila)</label>
                    <select class="form-control" id="created_by">
                        <option value="">Nao informar</option>
                        <?php
                        $stmtUsers = $pdo->query("SELECT id, field_12 FROM app_entity_1 ORDER BY field_12");
                        while ($u = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($u['id']) . '">' . htmlspecialchars($u['field_12']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button id="btn-load" class="btn btn-primary btn-block">Carregar pendentes</button>
                </div>
                <div class="col-md-3 text-right">
                    <button id="btn-refresh-stats" class="btn btn-outline-secondary">Atualizar status fila</button>
                </div>
            </div>
            <div class="mt-3" id="status"></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Pendentes sem OCR</span>
            <div>
                <button id="btn-select-all" class="btn btn-outline-secondary btn-sm">Marcar todos</button>
                <button id="btn-unselect-all" class="btn btn-outline-secondary btn-sm">Desmarcar</button>
                <button id="btn-enqueue" class="btn btn-success btn-sm">Enfileirar selecionados</button>
            </div>
        </div>
        <div class="card-body table-wrap p-0">
            <table class="table table-sm table-striped mb-0" id="pending-table">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 36px;"><input type="checkbox" id="toggle-all" /></th>
                        <th style="width: 70px;">ID</th>
                        <th>Arquivo</th>
                        <th>Campo 1</th>
                        <th>Campo 2</th>
                        <th>Campo 3</th>
                        <th style="width: 90px;">Paginas</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Fila OCR</span>
            <div>
                <input id="batch" type="number" min="1" max="500" value="20" class="form-control d-inline-block" style="width:100px;" />
                <button id="btn-process-now" class="btn btn-warning btn-sm">Processar lote agora</button>
                <button id="btn-cancel-batch" class="btn btn-outline-danger btn-sm">Cancelar lote atual</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row" id="queue-stats-row">
                <div class="col-md-3">Queued: <span class="badge badge-secondary badge-status" id="stat-queued">0</span></div>
                <div class="col-md-3">Processing: <span class="badge badge-info badge-status" id="stat-processing">0</span></div>
                <div class="col-md-3">Done: <span class="badge badge-success badge-status" id="stat-done">0</span></div>
                <div class="col-md-3">Failed: <span class="badge badge-danger badge-status" id="stat-failed">0</span></div>
            </div>
            <div class="mt-3">
                <label class="mb-1">Progresso do lote atual</label>
                <div class="progress">
                    <div id="batch-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar" style="width: 0%;">0%</div>
                </div>
                <small class="text-muted d-block mt-1" id="batch-progress-text">Nenhum lote em acompanhamento.</small>
                <small class="text-muted d-block" id="batch-processing-text"></small>
            </div>
            <hr />
            <div class="table-wrap" style="max-height: 300px;">
                <table class="table table-sm table-bordered" id="jobs-table">
                    <thead>
                        <tr>
                            <th>ID Job</th>
                            <th>Origem</th>
                            <th>Registro</th>
                            <th>Arquivo</th>
                            <th>Status</th>
                            <th>Tentativas</th>
                            <th>OCR chars</th>
                            <th>Erro</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    let activeBatchToken = null;
    let autoRefreshTimer = null;
    let autoRefreshTick = 0;
    const apiBaseUrl = new URL('ocr_queue_api.php', window.location.href).toString();
    const AUTO_REFRESH_ACTIVE_MS = 10000;
    const AUTO_REFRESH_IDLE_MS = 20000;

    function currentSource() {
        return $('#source').val() || 'ged';
    }

    function batchStorageKey(source) {
        return 'ocr_active_batch_token_' + (source || 'ged');
    }

    function getSavedBatchToken(source) {
        return localStorage.getItem(batchStorageKey(source));
    }

    function saveBatchToken(source, batchToken) {
        if (!batchToken) {
            return;
        }
        localStorage.setItem(batchStorageKey(source), batchToken);
    }

    function clearBatchToken(source) {
        localStorage.removeItem(batchStorageKey(source));
    }

    function apiUrl(action) {
        return action ? (apiBaseUrl + '?action=' + encodeURIComponent(action)) : apiBaseUrl;
    }

    function networkErrorMessage(prefix, xhr) {
        const status = xhr && typeof xhr.status !== 'undefined' ? xhr.status : 0;
        if (status === 0) {
            return prefix + ': falha de rede ou DNS ao resolver a URL da API.';
        }
        return prefix + ': HTTP ' + status;
    }

    function setStatus(type, message) {
        const cls = type === 'ok' ? 'alert-success' : (type === 'warn' ? 'alert-warning' : 'alert-danger');
        $('#status').html('<div class="alert ' + cls + ' py-2 mb-0">' + message + '</div>');
    }

    function escHtml(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getSelectedIds() {
        const ids = [];
        $('#pending-table tbody input.record-check:checked').each(function() {
            ids.push($(this).val());
        });
        return ids;
    }

    function renderPending(items) {
        const $tbody = $('#pending-table tbody');
        $tbody.empty();

        if (!items || !items.length) {
            $tbody.append('<tr><td colspan="7" class="text-center text-muted">Nenhum pendente encontrado.</td></tr>');
            return;
        }

        items.forEach(function(row) {
            const tr = [
                '<tr>',
                '<td><input type="checkbox" class="record-check" value="' + row.id + '" /></td>',
                '<td>' + (row.id || '') + '</td>',
                '<td class="mono">' + (row.file_name || '') + '</td>',
                '<td>' + (row.c1 || '') + '</td>',
                '<td>' + (row.c2 || '') + '</td>',
                '<td>' + (row.c3 || '') + '</td>',
                '<td>' + (row.pages || 0) + '</td>',
                '</tr>'
            ].join('');
            $tbody.append(tr);
        });
    }

    function refreshStats() {
        $.getJSON(apiUrl('queue_stats'))
            .done(function(resp) {
                if (!resp.success) {
                    setStatus('warn', resp.error || 'Falha ao atualizar status da fila.');
                    return;
                }
                const s = resp.stats || {};
                $('#stat-queued').text(s.queued || 0);
                $('#stat-processing').text(s.processing || 0);
                $('#stat-done').text(s.done || 0);
                $('#stat-failed').text(s.failed || 0);
            })
            .fail(function(xhr) {
                setStatus('warn', networkErrorMessage('Erro ao consultar status da fila', xhr));
            });
    }

    function refreshRecentJobs() {
        $.getJSON(apiUrl('recent_jobs'), { limit: 50 })
            .done(function(resp) {
                const $tbody = $('#jobs-table tbody');
                $tbody.empty();
                if (!resp.success || !resp.items || !resp.items.length) {
                    $tbody.append('<tr><td colspan="8" class="text-center text-muted">Sem jobs recentes.</td></tr>');
                    return;
                }

                resp.items.forEach(function(j) {
                    const isProcessing = (j.status || '') === 'processing';
                    const rowClass = isProcessing ? ' class="row-processing"' : '';
                    $tbody.append(
                        '<tr' + rowClass + '>' +
                        '<td>' + j.id + '</td>' +
                        '<td>' + (j.source || '') + '</td>' +
                        '<td>' + (j.record_id || '') + '</td>' +
                        '<td class="mono">' + escHtml(j.file_name || '') + '</td>' +
                        '<td>' + (j.status || '') + '</td>' +
                        '<td>' + (j.attempts || 0) + '</td>' +
                        '<td>' + (j.ocr_chars || 0) + '</td>' +
                        '<td class="mono">' + escHtml(j.error_message || '') + '</td>' +
                        '</tr>'
                    );
                });
            });
    }

    function renderBatchProgress(data) {
        const pct = Math.max(0, Math.min(100, parseInt(data.percent || 0, 10)));
        const total = parseInt(data.total || 0, 10);
        const finished = parseInt(data.finished || 0, 10);

        $('#batch-progress-bar').css('width', pct + '%').text(pct + '%');
        $('#batch-progress-text').text('Lote ' + data.batch_token + ' - ' + finished + '/' + total + ' concluídos.');

        const proc = data.processing_items || [];
        if (proc.length > 0) {
            const current = proc.map(function(item) {
                return '#' + item.record_id + ' (' + (item.source || '') + ')';
            }).join(', ');
            $('#batch-processing-text').text('Processando agora: ' + current);
        } else if (total > 0 && finished < total) {
            $('#batch-processing-text').text('Aguardando worker assumir o próximo item...');
        } else {
            $('#batch-processing-text').text('Nenhum item em processamento neste lote.');
        }
    }

    function clearBatchProgressUi(message) {
        $('#batch-progress-bar').css('width', '0%').text('0%');
        $('#batch-progress-text').text(message || 'Nenhum lote em acompanhamento.');
        $('#batch-processing-text').text('');
    }

    function stopAutoRefresh() {
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }
    }

    function resetBatchTracking(message) {
        clearBatchToken(currentSource());
        activeBatchToken = null;
        clearBatchProgressUi(message);
        startAutoRefresh();
    }

    function beginTrackingBatch(batchToken) {
        activeBatchToken = batchToken || null;
        if (activeBatchToken) {
            saveBatchToken(currentSource(), activeBatchToken);
        }
        refreshBatchProgress();
        startAutoRefresh();
    }

    function refreshBatchProgress() {
        if (!activeBatchToken) {
            clearBatchProgressUi();
            return;
        }

        $.getJSON(apiUrl('batch_progress'), { batch_token: activeBatchToken, source: currentSource() })
            .done(function(resp) {
                if (!resp.success) {
                    return;
                }

                if ((resp.total || 0) === 0 && resp.active_batch_token && resp.active_batch_token !== activeBatchToken) {
                    beginTrackingBatch(resp.active_batch_token);
                    return;
                }

                if ((resp.total || 0) === 0 && !resp.active_batch_token) {
                    resetBatchTracking('Nenhum lote ativo para esta origem.');
                    return;
                }

                renderBatchProgress(resp);

                if ((resp.finished || 0) >= (resp.total || 0) && (resp.total || 0) > 0) {
                    resetBatchTracking('Lote concluido.');
                }
            })
            .fail(function(xhr) {
                setStatus('warn', networkErrorMessage('Falha ao consultar progresso do lote', xhr) + '.');
            });
    }

    function startAutoRefresh() {
        stopAutoRefresh();

        autoRefreshTick = 0;
        const intervalMs = activeBatchToken ? AUTO_REFRESH_ACTIVE_MS : AUTO_REFRESH_IDLE_MS;

        autoRefreshTimer = setInterval(function() {
            autoRefreshTick++;
            refreshStats();
            if (activeBatchToken) {
                refreshBatchProgress();
            }
            if (autoRefreshTick === 1 || (autoRefreshTick % 3) === 0) {
                refreshRecentJobs();
            }
        }, intervalMs);
    }

    $('#btn-load').on('click', function() {
        const source = $('#source').val();
        const limit = $('#limit').val();

        $.getJSON(apiUrl('list_pending'), { source: source, limit: limit })
            .done(function(resp) {
                if (!resp.success) {
                    setStatus('err', resp.error || 'Falha ao listar pendentes.');
                    return;
                }
                renderPending(resp.items || []);
                setStatus('ok', 'Pendentes carregados: ' + (resp.total || 0));
            })
            .fail(function(xhr) {
                setStatus('err', networkErrorMessage('Erro ao carregar pendentes', xhr));
            });
    });

    $('#btn-enqueue').on('click', function() {
        const ids = getSelectedIds();
        if (!ids.length) {
            setStatus('warn', 'Selecione ao menos um registro para enfileirar.');
            return;
        }

        $.post(apiUrl('enqueue'), {
            source: $('#source').val(),
            created_by: $('#created_by').val(),
            record_ids: ids,
            force: 0,
            reset_active_batch: 1
        }, null, 'json')
        .done(function(resp) {
            if (!resp.success) {
                setStatus('err', resp.error || 'Falha ao enfileirar.');
                return;
            }

            const r = resp.result || {};
            if ((r.enqueued || 0) > 0) {
                beginTrackingBatch(r.batch_token || null);
            }
            let msg = 'Enfileirados: ' + (r.enqueued || 0) + '. Ignorados: ' + (r.skipped || 0) + '.';
            if (r.reset && r.reset.had_active_batch) {
                msg += ' Lote anterior limpo automaticamente: ' + (r.reset.canceled_batch_token || '') + ' (itens cancelados: ' + (r.reset.canceled_rows || 0) + ').';
            }
            if (r.errors && r.errors.length) {
                msg += ' Erros: ' + r.errors.join(' | ');
            }
            if ((r.enqueued || 0) === 0 && activeBatchToken) {
                msg += ' Mantido lote ativo atual: ' + activeBatchToken + '.';
            }
            setStatus('ok', msg);
            refreshStats();
            refreshRecentJobs();
        })
        .fail(function(xhr) {
            setStatus('err', networkErrorMessage('Erro ao enfileirar', xhr));
        });
    });

    $('#btn-process-now').on('click', function() {
        const batch = $('#batch').val() || 20;
        const batchToken = activeBatchToken || getSavedBatchToken(currentSource()) || '';
        if (!batchToken) {
            setStatus('warn', 'Enfileire um lote primeiro para iniciar o processamento.');
            return;
        }

        $.post(apiUrl('process_now'), { batch: batch, batch_token: batchToken, source: currentSource() }, null, 'json')
            .done(function(resp) {
                if (!resp.success) {
                    setStatus('err', resp.error || 'Falha ao processar lote.');
                    return;
                }

                beginTrackingBatch(resp.batch_token || batchToken);
                if (resp.already_running) {
                    setStatus('warn', 'Este lote ja esta em processamento. Acompanhe pela barra de progresso.');
                } else if (resp.message) {
                    setStatus('warn', resp.message);
                } else {
                    setStatus('ok', 'Processamento disparado em segundo plano. O lote continua sendo acompanhado pela barra de progresso.');
                }
                refreshStats();
                refreshRecentJobs();
            })
            .fail(function(xhr) {
                setStatus('err', networkErrorMessage('Erro ao processar lote', xhr));
            });
    });

        $('#btn-cancel-batch').on('click', function() {
            const batchToken = activeBatchToken || getSavedBatchToken(currentSource()) || '';
            $.post(apiUrl('cancel_batch'), { batch_token: batchToken, source: currentSource() }, null, 'json')
                .done(function(resp) {
                    if (!resp.success) {
                        setStatus('err', resp.error || 'Falha ao cancelar lote.');
                        return;
                    }

                    resetBatchTracking('Lote cancelado.');
                    refreshStats();
                    refreshRecentJobs();
                    setStatus('ok', 'Lote cancelado: ' + (resp.batch_token || '') + '. Itens afetados: ' + (resp.canceled_rows || 0) + '.');
                })
                .fail(function(xhr) {
                    setStatus('err', networkErrorMessage('Erro ao cancelar lote', xhr));
                });
        });

    $('#btn-select-all').on('click', function() {
        $('#pending-table tbody input.record-check').prop('checked', true);
    });

    $('#btn-unselect-all').on('click', function() {
        $('#pending-table tbody input.record-check').prop('checked', false);
        $('#toggle-all').prop('checked', false);
    });

    $('#toggle-all').on('change', function() {
        $('#pending-table tbody input.record-check').prop('checked', $(this).is(':checked'));
    });

    $('#btn-refresh-stats').on('click', function() {
        refreshStats();
        refreshRecentJobs();
        refreshBatchProgress();
        setStatus('ok', 'Status atualizado.');
    });

    const savedBatch = getSavedBatchToken(currentSource());
    if (savedBatch) {
        activeBatchToken = savedBatch;
    } else {
        clearBatchProgressUi();
    }

    $(document).on('change', '#source', function() {
        activeBatchToken = getSavedBatchToken(currentSource()) || null;
        if (!activeBatchToken) {
            clearBatchProgressUi('Nenhum lote ativo para esta origem.');
        }
        refreshRecentJobs();
        refreshBatchProgress();
        startAutoRefresh();
    });

    refreshStats();
    refreshRecentJobs();
    refreshBatchProgress();
    startAutoRefresh();
</script>
</body>
</html>
