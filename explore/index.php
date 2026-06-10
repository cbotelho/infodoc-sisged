<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require_once 'includes/application_core.php';
require_once 'explore/storage_browser.php';

explore_start_app_session();

if (!explore_is_logged_in()) {
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>Acesso negado</h2><p>Faca login no sistema para acessar o explorer.</p>';
    exit;
}
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Explorer de Arquivos</title>
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="../fonts/icomoon/icomoon.css">
  <link rel="stylesheet" href="../css/main.min.css">
  <style>
    :root {
      --bg: #f4f7fb;
      --panel: #ffffff;
      --line: #d9e2ef;
      --text: #1f2d3d;
      --muted: #6b7b8c;
      --accent: #0a66c2;
      --accent-soft: #e8f1fb;
      --danger: #b42318;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Segoe UI, Tahoma, sans-serif;
      color: var(--text);
      background: linear-gradient(180deg, #eef4fc 0%, var(--bg) 320px);
      min-height: 100vh;
    }

    .wrap {
      max-width: 1400px;
      margin: 24px auto;
      padding: 0 16px 24px;
    }

    .page-header {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 10px 14px;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .brand img {
      width: 92px;
      height: auto;
    }

    .header {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 14px 16px;
      margin-bottom: 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .title { font-size: 18px; font-weight: 600; }
    .meta { color: var(--muted); font-size: 12px; }

    .toolbar {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 10px;
      display: flex;
      gap: 8px;
      align-items: center;
      margin-bottom: 12px;
      flex-wrap: wrap;
    }

    .btn {
      border: 1px solid var(--line);
      background: #fff;
      color: var(--text);
      border-radius: 8px;
      padding: 7px 10px;
      cursor: pointer;
      font-size: 13px;
    }

    .btn:hover { background: #f9fbff; }

    .crumbs {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 7px 10px;
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      font-size: 13px;
      min-height: 36px;
      align-items: center;
      flex: 1;
    }

    .crumb {
      color: var(--accent);
      text-decoration: none;
      cursor: pointer;
    }

    .crumb.current {
      color: var(--text);
      font-weight: 600;
      cursor: default;
    }

    .search {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 13px;
      min-width: 220px;
      background: #fff;
    }

    .sort {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 13px;
      background: #fff;
      min-width: 210px;
    }

    .explorer-grid {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 12px;
      align-items: start;
    }

    .panel-title {
      font-size: 13px;
      font-weight: 600;
      color: #28425f;
      margin-bottom: 8px;
    }

    .panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 12px;
      overflow: hidden;
      min-height: 520px;
    }

    .panel-head {
      padding: 10px;
      border-bottom: 1px solid var(--line);
      background: #f8fbff;
    }

    .panel-body-scroll {
      max-height: 520px;
      overflow: auto;
    }

    .folder-list {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .folder-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 12px;
      border-bottom: 1px solid #edf2f8;
      cursor: pointer;
      font-size: 13px;
    }

    .folder-item:hover { background: #fbfdff; }
    .folder-item.active {
      background: #eaf3ff;
      color: #0a4f9a;
      font-weight: 600;
    }

    .folder-empty {
      padding: 14px 12px;
      color: var(--muted);
      font-size: 13px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead th {
      text-align: left;
      padding: 11px 12px;
      background: #f7faff;
      border-bottom: 1px solid var(--line);
      font-size: 12px;
      color: #355173;
      letter-spacing: .03em;
    }

    th.sortable {
      cursor: pointer;
      user-select: none;
      white-space: nowrap;
    }

    .sort-ind {
      color: #6f86a0;
      margin-left: 5px;
      font-size: 11px;
    }

    .check-col {
      width: 34px;
      text-align: center;
    }

    .row-check,
    #selectAllFiles {
      width: 14px;
      height: 14px;
      cursor: pointer;
    }

    .row-check {
      accent-color: #0a66c2;
    }

    tr.file.selected td {
      background: #eaf3ff;
      border-bottom-color: #cfe0f6;
    }

    tr.file.selected td:first-child {
      box-shadow: inset 3px 0 0 #0a66c2;
    }

    tr.file.selected:hover td {
      background: #dfeeff;
    }

    tbody td {
      padding: 11px 12px;
      border-bottom: 1px solid #edf2f8;
      font-size: 13px;
      vertical-align: middle;
    }

    tbody tr:hover { background: #fbfdff; }

    .name {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 280px;
    }

    .icon {
      width: 22px;
      height: 22px;
      border-radius: 6px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      background: var(--accent-soft);
      color: var(--accent);
      flex: 0 0 22px;
    }

    .file .icon {
      background: #eef2f6;
      color: #4d6177;
    }

    .link {
      color: var(--accent);
      text-decoration: none;
      cursor: pointer;
    }

    .muted { color: var(--muted); }

    .status {
      padding: 8px 10px;
      margin: 10px;
      border-radius: 8px;
      font-size: 13px;
      display: none;
    }

    .status.show { display: block; }
    .status.ok { background: #ecfdf3; color: #066a3a; }
    .status.err { background: #fef3f2; color: var(--danger); }

    .page-footer {
      margin-top: 12px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 12px;
      color: var(--muted);
      font-size: 12px;
      padding: 10px 14px;
      text-align: right;
    }

    .btn-row {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .btn.tiny {
      padding: 5px 8px;
      font-size: 12px;
    }

    .panel-foot {
      padding: 10px;
      border-top: 1px solid var(--line);
      background: #f8fbff;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .counter {
      color: var(--muted);
      font-size: 12px;
    }

    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.45);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
      z-index: 9999;
    }

    .modal-backdrop.show { display: flex; }

    .modal-card {
      width: min(700px, 100%);
      background: #fff;
      border-radius: 12px;
      border: 1px solid var(--line);
      overflow: hidden;
      box-shadow: 0 20px 45px rgba(15, 23, 42, 0.2);
    }

    .modal-head {
      padding: 12px 14px;
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      background: #f8fbff;
    }

    .modal-body {
      padding: 14px;
      max-height: 60vh;
      overflow: auto;
    }

    pre.meta-pre {
      margin: 0;
      font-size: 12px;
      background: #f8fafc;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px;
      white-space: pre-wrap;
      word-break: break-word;
    }

    @media (max-width: 900px) {
      .explorer-grid { grid-template-columns: 1fr; }
      .search { width: 100%; min-width: 0; }
      .crumbs { width: 100%; }
      thead th:nth-child(3),
      tbody td:nth-child(3) {
        display: none;
      }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="page-header">
      <div class="brand">
        <img src="../images/logo_ecm.png" alt="Logo ECM">
        <div>
          <div class="title">SISGED Explorer</div>
          <div class="meta">Navegador de arquivos no storage</div>
        </div>
      </div>
      <button class="btn" id="reloadBtnTop">Atualizar</button>
    </div>

    <div class="header">
      <div>
        <div class="title">Explorer de Arquivos (R2/S3)</div>
        <div class="meta" id="metaInfo">Carregando...</div>
      </div>
      <button class="btn" id="reloadBtn">Atualizar</button>
    </div>

    <div class="toolbar">
      <button class="btn" id="upBtn">Subir Nivel</button>
      <div class="crumbs" id="breadcrumbs"></div>
    </div>

    <div class="status" id="status"></div>

    <div class="explorer-grid">
      <div class="panel">
        <div class="panel-head">
          <div class="panel-title">Pastas</div>
          <input class="search" id="searchFolders" type="search" placeholder="Buscar pasta...">
        </div>
        <div class="panel-body-scroll">
          <ul class="folder-list" id="folderList"></ul>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div class="panel-title">Arquivos da pasta atual</div>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <input class="search" id="searchFiles" type="search" placeholder="Buscar arquivo...">
            <select class="sort" id="sortFiles">
              <option value="name_asc">Nome A-Z</option>
              <option value="name_desc">Nome Z-A</option>
              <option value="date_desc">Data mais recente</option>
              <option value="date_asc">Data mais antiga</option>
              <option value="size_desc">Maior tamanho</option>
              <option value="size_asc">Menor tamanho</option>
            </select>
          </div>
        </div>
        <div class="panel-body-scroll" id="filesScroll">
          <table>
            <thead>
              <tr>
                <th class="check-col"><input type="checkbox" id="selectAllFiles" title="Selecionar todos"></th>
                <th class="sortable" id="thName">Nome <span class="sort-ind" id="indName">-</span></th>
                <th class="sortable" id="thSize">Tamanho <span class="sort-ind" id="indSize">-</span></th>
                <th class="sortable" id="thDate">Atualizado em <span class="sort-ind" id="indDate">-</span></th>
                <th>Acoes</th>
              </tr>
            </thead>
            <tbody id="tbody"></tbody>
          </table>
        </div>
        <div class="panel-foot">
          <div class="counter" id="fileCounter">0 arquivo(s)</div>
          <div class="btn-row">
            <button class="btn" id="exportJsonBtn">Exportar JSON</button>
            <button class="btn" id="exportCsvBtn">Exportar CSV</button>
            <button class="btn" id="loadMoreBtn" style="display:none;">Carregar mais</button>
          </div>
        </div>
      </div>
    </div>

    <div class="page-footer">Explorer MVP 1.1 | INFODOC-SISGED</div>
  </div>

  <div class="modal-backdrop" id="metaModal">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="metaTitle">
      <div class="modal-head">
        <strong id="metaTitle">Metadados do arquivo</strong>
        <button class="btn" id="closeMetaBtn">Fechar</button>
      </div>
      <div class="modal-body">
        <pre class="meta-pre" id="metaContent"></pre>
      </div>
    </div>
  </div>

<script>
(function () {
  const PAGE_SIZE = 100;

  const state = {
    path: '',
    folders: [],
    files: [],
    mode: '',
    bucket: '',
    prefix: '',
    hasMore: false,
    nextToken: '',
    selected: new Set()
  };

  const tbody = document.getElementById('tbody');
  const folderList = document.getElementById('folderList');
  const breadcrumbs = document.getElementById('breadcrumbs');
  const metaInfo = document.getElementById('metaInfo');
  const status = document.getElementById('status');
  const searchFolders = document.getElementById('searchFolders');
  const searchFiles = document.getElementById('searchFiles');
  const sortFiles = document.getElementById('sortFiles');
  const filesScroll = document.getElementById('filesScroll');
  const selectAllFiles = document.getElementById('selectAllFiles');
  const thName = document.getElementById('thName');
  const thSize = document.getElementById('thSize');
  const thDate = document.getElementById('thDate');
  const indName = document.getElementById('indName');
  const indSize = document.getElementById('indSize');
  const indDate = document.getElementById('indDate');
  const loadMoreBtn = document.getElementById('loadMoreBtn');
  const fileCounter = document.getElementById('fileCounter');
  const exportJsonBtn = document.getElementById('exportJsonBtn');
  const exportCsvBtn = document.getElementById('exportCsvBtn');
  const metaModal = document.getElementById('metaModal');
  const metaContent = document.getElementById('metaContent');
  const closeMetaBtn = document.getElementById('closeMetaBtn');

  let loadingMore = false;

  function esc(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;');
  }

  function showStatus(kind, message) {
    status.className = 'status show ' + (kind || 'ok');
    status.textContent = message;
  }

  function hideStatus() {
    status.className = 'status';
    status.textContent = '';
  }

  function formatDate(iso) {
    if (!iso) return '-';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleString('pt-BR');
  }

  function absoluteUrl(url) {
    try {
      return new URL(url, window.location.origin).toString();
    } catch (_) {
      return String(url || '');
    }
  }

  function mergeByKey(existing, incoming, keyFn) {
    const map = new Map();
    existing.forEach(item => map.set(keyFn(item), item));
    incoming.forEach(item => map.set(keyFn(item), item));
    return Array.from(map.values());
  }

  function fileKey(file) {
    return String((file && (file.relative_path || file.name)) || '');
  }

  function selectedFilteredFiles() {
    const current = filteredFiles();
    const selected = current.filter(x => state.selected.has(fileKey(x)));
    return selected.length ? selected : current;
  }

  function applyStateToUrl(replaceEntry) {
    const params = new URLSearchParams(window.location.search);

    if (state.path) params.set('path', state.path); else params.delete('path');

    const qFolders = searchFolders.value.trim();
    const qFiles = searchFiles.value.trim();
    const sort = sortFiles.value || 'name_asc';

    if (qFolders) params.set('qf', qFolders); else params.delete('qf');
    if (qFiles) params.set('q', qFiles); else params.delete('q');
    if (sort && sort !== 'name_asc') params.set('sort', sort); else params.delete('sort');

    const next = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
    if (replaceEntry) {
      history.replaceState(null, '', next);
    } else {
      history.pushState(null, '', next);
    }
  }

  function hydrateStateFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const path = params.get('path') || '';
    const qFolders = params.get('qf') || '';
    const qFiles = params.get('q') || '';
    const sort = params.get('sort') || 'name_asc';

    searchFolders.value = qFolders;
    searchFiles.value = qFiles;

    const allowedSort = new Set(['name_asc', 'name_desc', 'date_desc', 'date_asc', 'size_desc', 'size_asc']);
    sortFiles.value = allowedSort.has(sort) ? sort : 'name_asc';

    return path;
  }

  function updateSortIndicators() {
    const mode = sortFiles.value || 'name_asc';

    indName.textContent = '-';
    indSize.textContent = '-';
    indDate.textContent = '-';

    if (mode.startsWith('name_')) {
      indName.textContent = mode.endsWith('_asc') ? 'ASC' : 'DESC';
    }
    if (mode.startsWith('size_')) {
      indSize.textContent = mode.endsWith('_asc') ? 'ASC' : 'DESC';
    }
    if (mode.startsWith('date_')) {
      indDate.textContent = mode.endsWith('_asc') ? 'ASC' : 'DESC';
    }
  }

  function toggleSortBy(base) {
    const current = sortFiles.value || 'name_asc';
    const defaultMode = (base === 'name') ? 'name_asc' : (base + '_desc');

    if (!current.startsWith(base + '_')) {
      sortFiles.value = defaultMode;
    } else {
      sortFiles.value = current.endsWith('_asc') ? (base + '_desc') : (base + '_asc');
    }

    updateSortIndicators();
    renderTable();
    applyStateToUrl(true);
  }

  function getParentPath(path) {
    const p = (path || '').trim();
    if (!p) return '';
    const parts = p.split('/').filter(Boolean);
    parts.pop();
    return parts.join('/');
  }

  function renderBreadcrumbs(path) {
    const parts = (path || '').split('/').filter(Boolean);
    const crumbs = [{ label: 'raiz', path: '' }];

    let acc = '';
    for (const part of parts) {
      acc = acc ? acc + '/' + part : part;
      crumbs.push({ label: part, path: acc });
    }

    breadcrumbs.innerHTML = crumbs.map((c, i) => {
      const current = i === crumbs.length - 1;
      return '<a class="crumb ' + (current ? 'current' : '') + '" data-path="' + esc(c.path) + '">' + esc(c.label) + '</a>';
    }).join(' <span class="muted">/</span> ');

    breadcrumbs.querySelectorAll('a.crumb').forEach(a => {
      if (a.classList.contains('current')) return;
      a.addEventListener('click', () => loadPath(a.getAttribute('data-path') || ''));
    });
  }

  function filteredFolders() {
    const q = searchFolders.value.trim().toLowerCase();
    if (!q) return state.folders;
    return state.folders.filter(f => (f.name || '').toLowerCase().includes(q));
  }

  function filteredFiles() {
    const q = searchFiles.value.trim().toLowerCase();
    const filtered = !q
      ? state.files.slice()
      : state.files.filter(f => (f.name || '').toLowerCase().includes(q));

    const mode = sortFiles.value || 'name_asc';
    const readDate = (x) => {
      const t = Date.parse(x && x.last_modified ? x.last_modified : '');
      return Number.isNaN(t) ? 0 : t;
    };
    const readSize = (x) => Number(x && x.size ? x.size : 0);
    const readName = (x) => String(x && x.name ? x.name : '').toLowerCase();

    filtered.sort((a, b) => {
      if (mode === 'name_desc') return readName(b).localeCompare(readName(a), 'pt-BR');
      if (mode === 'date_desc') return readDate(b) - readDate(a);
      if (mode === 'date_asc') return readDate(a) - readDate(b);
      if (mode === 'size_desc') return readSize(b) - readSize(a);
      if (mode === 'size_asc') return readSize(a) - readSize(b);
      return readName(a).localeCompare(readName(b), 'pt-BR');
    });

    return filtered;
  }

  function toCsvValue(value) {
    const raw = String(value == null ? '' : value);
    const escaped = raw.replaceAll('"', '""');
    return '"' + escaped + '"';
  }

  function downloadTextFile(filename, content, mimeType) {
    const blob = new Blob([content], { type: mimeType + ';charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  function exportMetadataJson() {
    const data = selectedFilteredFiles().map(x => x.meta || {});
    const payload = JSON.stringify(data, null, 2);
    const stamp = new Date().toISOString().replaceAll(':', '-');
    downloadTextFile('explorer-metadata-' + stamp + '.json', payload, 'application/json');
    showStatus('ok', 'Exportacao JSON concluida.');
  }

  function exportMetadataCsv() {
    const data = selectedFilteredFiles();
    const headers = ['name', 'relative_path', 'storage_key', 'size', 'size_human', 'last_modified', 'source', 'url'];
    const lines = [headers.join(',')];

    data.forEach(item => {
      const m = item.meta || {};
      const row = [
        m.name || item.name || '',
        m.relative_path || item.relative_path || '',
        m.storage_key || '',
        m.size || item.size || 0,
        m.size_human || item.size_human || '',
        m.last_modified || item.last_modified || '',
        m.source || '',
        absoluteUrl(item.url || '')
      ].map(toCsvValue);
      lines.push(row.join(','));
    });

    const payload = lines.join('\n');
    const stamp = new Date().toISOString().replaceAll(':', '-');
    downloadTextFile('explorer-metadata-' + stamp + '.csv', payload, 'text/csv');
    showStatus('ok', 'Exportacao CSV concluida.');
  }

  function renderFolders() {
    const data = filteredFolders();
    if (!data.length) {
      folderList.innerHTML = '<li class="folder-empty">Nenhuma pasta encontrada.</li>';
      return;
    }

    folderList.innerHTML = data.map(f => {
      const isActive = (f.path || '') === (state.path || '');
      return '<li class="folder-item ' + (isActive ? 'active' : '') + '" data-path="' + esc(f.path || '') + '">' +
        '<span class="icon">D</span>' +
        '<span>' + esc(f.name || '') + '</span>' +
      '</li>';
    }).join('');

    folderList.querySelectorAll('.folder-item').forEach(el => {
      el.addEventListener('click', () => {
        const p = el.getAttribute('data-path') || '';
        loadPath(p);
      });
    });
  }

  function renderTable() {
    const data = filteredFiles();

    const fileRows = data.map(f => {
      const key = fileKey(f);
      const checked = state.selected.has(key) ? 'checked' : '';
      const rowClass = checked ? 'file selected' : 'file';
      const payload = encodeURIComponent(JSON.stringify(f.meta || {}));
      return '<tr class="' + rowClass + '">' +
        '<td class="check-col"><input class="row-check" type="checkbox" data-key="' + esc(key) + '" ' + checked + '></td>' +
        '<td><div class="name"><span class="icon">F</span><span>' + esc(f.name || '') + '</span></div></td>' +
        '<td>' + esc(f.size_human || '-') + '</td>' +
        '<td>' + esc(formatDate(f.last_modified)) + '</td>' +
        '<td><div class="btn-row">' +
          '<a class="btn tiny" href="' + esc(f.url || '#') + '" target="_blank" rel="noopener">Visualizar</a>' +
          '<a class="btn tiny" href="' + esc(f.download_url || f.url || '#') + '" download>Download</a>' +
          '<button class="btn tiny copy-link" data-url="' + esc(f.url || '') + '">Copiar link</button>' +
          '<button class="btn tiny meta-btn" data-meta="' + payload + '">Metadados</button>' +
        '</div></td>' +
      '</tr>';
    }).join('');

    tbody.innerHTML = fileRows || '<tr><td colspan="5" class="muted">Nenhum arquivo encontrado.</td></tr>';

    tbody.querySelectorAll('.row-check').forEach(chk => {
      chk.addEventListener('change', () => {
        const key = chk.getAttribute('data-key') || '';
        if (!key) return;
        if (chk.checked) state.selected.add(key);
        else state.selected.delete(key);
        renderTable();
      });
    });

    tbody.querySelectorAll('.copy-link').forEach(btn => {
      btn.addEventListener('click', async () => {
        const url = absoluteUrl(btn.getAttribute('data-url') || '');
        try {
          if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
          } else {
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            document.execCommand('copy');
            ta.remove();
          }
          showStatus('ok', 'Link copiado: ' + url);
        } catch (_) {
          showStatus('err', 'Nao foi possivel copiar o link.');
        }
      });
    });

    tbody.querySelectorAll('.meta-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const raw = btn.getAttribute('data-meta') || '';
        let meta = {};
        try {
          meta = JSON.parse(decodeURIComponent(raw));
        } catch (_) {
          meta = { erro: 'Falha ao ler metadados.' };
        }

        metaContent.textContent = JSON.stringify(meta, null, 2);
        metaModal.classList.add('show');
      });
    });

    const selectedCount = data.filter(x => state.selected.has(fileKey(x))).length;
    fileCounter.textContent = data.length + ' arquivo(s) exibido(s) | ' + selectedCount + ' selecionado(s)';

    const allChecked = data.length > 0 && selectedCount === data.length;
    selectAllFiles.checked = allChecked;
    selectAllFiles.indeterminate = selectedCount > 0 && selectedCount < data.length;

    loadMoreBtn.style.display = state.hasMore ? 'inline-block' : 'none';
    updateSortIndicators();
  }

  function buildApiUrl(path, token) {
    const params = new URLSearchParams();
    params.set('path', path || '');
    params.set('limit', String(PAGE_SIZE));
    if (token) {
      params.set('token', token);
    }
    return 'api.php?' + params.toString();
  }

  async function fetchPage(path, token) {
    const res = await fetch(buildApiUrl(path, token), {
      credentials: 'same-origin'
    });

    const payload = await res.json();
    if (!res.ok || !payload.ok) {
      throw new Error(payload.message || 'Falha ao carregar lista.');
    }

    return payload.data || {};
  }

  async function loadMore() {
    if (!state.hasMore || loadingMore) {
      return;
    }

    loadingMore = true;
    loadMoreBtn.disabled = true;
    loadMoreBtn.textContent = 'Carregando...';

    try {
      const data = await fetchPage(state.path, state.nextToken || '');
      const nextFolders = Array.isArray(data.folders) ? data.folders : [];
      const nextFiles = Array.isArray(data.files) ? data.files : [];

      state.folders = mergeByKey(state.folders, nextFolders, x => (x.path || ''));
      state.files = mergeByKey(state.files, nextFiles, x => (x.relative_path || x.name || ''));
      state.hasMore = Boolean(data.has_more);
      state.nextToken = String(data.next_token || '');

      renderFolders();
      renderTable();

      if (state.hasMore) {
        showStatus('ok', 'Mais itens carregados.');
      } else {
        showStatus('ok', 'Todos os itens foram carregados.');
      }
    } catch (err) {
      showStatus('err', err.message || 'Erro ao carregar mais itens.');
    } finally {
      loadingMore = false;
      loadMoreBtn.disabled = false;
      loadMoreBtn.textContent = 'Carregar mais';
    }
  }

  function maybeLoadMoreOnScroll() {
    const remaining = filesScroll.scrollHeight - filesScroll.scrollTop - filesScroll.clientHeight;
    if (remaining <= 60) {
      loadMore();
    }
  }

  async function loadPath(path) {
    hideStatus();
    metaInfo.textContent = 'Carregando...';

    try {
      const data = await fetchPage(path || '', '');
      state.path = data.path || '';
      state.folders = Array.isArray(data.folders) ? data.folders : [];
      state.files = Array.isArray(data.files) ? data.files : [];
      state.mode = data.mode || '';
      state.bucket = data.bucket || '';
      state.prefix = data.prefix || '';
      state.hasMore = Boolean(data.has_more);
      state.nextToken = String(data.next_token || '');
      state.selected = new Set();

      renderBreadcrumbs(state.path);
      renderFolders();
      renderTable();
      applyStateToUrl(false);

      const total = state.folders.length + state.files.length;
      const bucketLabel = state.bucket ? (' | bucket: ' + state.bucket) : '';
      metaInfo.textContent = 'Modo: ' + (state.mode || '-') + bucketLabel + ' | Itens carregados: ' + total;

      if (state.hasMore) {
        showStatus('ok', 'Listagem parcial carregada. Use "Carregar mais" para continuar.');
      }
    } catch (err) {
      showStatus('err', err.message || 'Erro ao carregar dados.');
      metaInfo.textContent = 'Falha na listagem';
      tbody.innerHTML = '';
    }
  }

  document.getElementById('reloadBtn').addEventListener('click', () => loadPath(state.path));
  document.getElementById('reloadBtnTop').addEventListener('click', () => loadPath(state.path));
  document.getElementById('upBtn').addEventListener('click', () => loadPath(getParentPath(state.path)));
  searchFolders.addEventListener('input', renderFolders);
  searchFolders.addEventListener('input', () => {
    renderFolders();
    applyStateToUrl(true);
  });
  searchFiles.addEventListener('input', () => {
    renderTable();
    applyStateToUrl(true);
  });
  sortFiles.addEventListener('change', () => {
    updateSortIndicators();
    renderTable();
    applyStateToUrl(true);
  });

  thName.addEventListener('click', () => toggleSortBy('name'));
  thSize.addEventListener('click', () => toggleSortBy('size'));
  thDate.addEventListener('click', () => toggleSortBy('date'));

  selectAllFiles.addEventListener('change', () => {
    const data = filteredFiles();
    if (selectAllFiles.checked) {
      data.forEach(item => state.selected.add(fileKey(item)));
    } else {
      data.forEach(item => state.selected.delete(fileKey(item)));
    }
    renderTable();
  });
  loadMoreBtn.addEventListener('click', loadMore);
  exportJsonBtn.addEventListener('click', exportMetadataJson);
  exportCsvBtn.addEventListener('click', exportMetadataCsv);
  filesScroll.addEventListener('scroll', maybeLoadMoreOnScroll);
  closeMetaBtn.addEventListener('click', () => metaModal.classList.remove('show'));
  metaModal.addEventListener('click', (e) => {
    if (e.target === metaModal) {
      metaModal.classList.remove('show');
    }
  });

  window.addEventListener('popstate', () => {
    const pathFromUrl = hydrateStateFromUrl();
    loadPath(pathFromUrl || '');
  });

  const initialPath = hydrateStateFromUrl();
  updateSortIndicators();
  loadPath(initialPath || '');
})();
</script>
</body>
</html>
