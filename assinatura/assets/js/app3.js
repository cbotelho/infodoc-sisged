/**
 * Assinador PDF Frontend
 */

// Estado global da aplicação
const state = {
    pdfDoc: null,
    currentScale: 1.5,
    canvas: null,
    ctx: null,
    selectedFile: null,
    signedFilename: null,
    selectedPosition: null,
    lastRenderedViewport: null
};

// Inicializar quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar canvas
    state.canvas = document.getElementById('pdf-canvas');
    state.ctx = state.canvas.getContext('2d');

    // Event listeners
    setupEventListeners();
    // Event listener para upload de certificado
    const certForm = document.getElementById('certUploadForm');
    if (certForm) {
        certForm.addEventListener('submit', handleCertUpload);
    }
});

// Setup de todos os event listeners
function setupEventListeners() {
    // Form de upload do PDF
    document.getElementById('uploadForm').addEventListener('submit', handlePdfUpload);

    // Click no canvas para posicionar assinatura
    state.canvas.addEventListener('click', handleCanvasClick);

    // Botão assinar
    document.getElementById('btnAssinar').addEventListener('click', () => {
        const cert = document.getElementById('certificado').value;
        if (!cert) return Swal.fire('Aviso', 'Selecione um certificado.', 'warning');

        // Se for .pfx ou .p12, abrir modal de senha. Se não, assinar direto
        if (cert.toLowerCase().endsWith('.pfx') || cert.toLowerCase().endsWith('.p12')) {
            new bootstrap.Modal(document.getElementById('senhaModal')).show();
        } else {
            // Para .pem não precisa de senha
            handleSignConfirm('');
        }
    });

    // Confirmar assinatura no modal de senha
    document.getElementById('confirmarAssinatura').addEventListener('click', () => {
        const senha = document.getElementById('senhaCertificado').value || '';
        handleSignConfirm(senha);
    });

    // Botão baixar
    document.getElementById('btnBaixar').addEventListener('click', handleDownload);
    // O upload de certificado é tratado no DOMContentLoaded
}
/**
 * Upload de Certificado
 */
async function handleCertUpload(e) {
    e.preventDefault();
    const certInput = document.getElementById('certFile');
    const statusDiv = document.getElementById('certUploadStatus');
    if (!certInput.files.length) {
        statusDiv.innerText = 'Selecione um arquivo de certificado.';
        return;
    }
    const formData = new FormData();
    formData.append('certFile', certInput.files[0]);
    try {
        const res = await fetch('sign.php?action=upload_cert', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            statusDiv.innerText = 'Certificado enviado com sucesso: ' + data.filename;
            Swal.fire('Sucesso', 'Certificado enviado!', 'success');
            await updateCertList();
        } else {
            statusDiv.innerText = data.message || 'Falha no upload do certificado.';
            Swal.fire('Erro', data.message || 'Falha no upload do certificado.', 'error');
        }
    /**
     * Atualiza a lista de certificados disponíveis
     */
    async function updateCertList() {
        try {
            const res = await fetch('certs_list.php');
            const certs = await res.json();
            const select = document.getElementById('certificado');
            if (!select) return;
            // Salva o valor atual
            const prevValue = select.value;
            select.innerHTML = '<option value="">-- Selecione um certificado --</option>';
            certs.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f;
                opt.textContent = f;
                select.appendChild(opt);
            });
            // Restaura seleção anterior se possível
            if (prevValue && certs.includes(prevValue)) {
                select.value = prevValue;
            }
        } catch (err) {
            console.error('Erro ao atualizar lista de certificados:', err);
        }
    }
    } catch (err) {
        statusDiv.innerText = 'Erro ao enviar certificado.';
        Swal.fire('Erro', 'Erro ao enviar certificado.', 'error');
    }
}

/**
 * Upload do PDF
 */
async function handlePdfUpload(e) {
    e.preventDefault();
    const fileInput = document.getElementById('pdfFile');
    if (!fileInput.files.length) return Swal.fire('Erro', 'Selecione um PDF.', 'error');

    const formData = new FormData();
    formData.append('pdfFile', fileInput.files[0]);

    try {
        const res = await fetch('sign.php?action=upload', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            state.selectedFile = data.filename;
            document.getElementById('currentFile').innerText = state.selectedFile;
            document.getElementById('btnAssinar').disabled = false;
            loadPDF('uploads/' + state.selectedFile);
            Swal.fire('Sucesso', 'PDF carregado.', 'success');
        } else {
            Swal.fire('Erro', data.message || 'Falha no upload', 'error');
        }
    } catch (err) {
        console.error('Upload error:', err);
        Swal.fire('Erro', 'Falha no upload do arquivo', 'error');
    }
}

/**
 * Carrega e renderiza o PDF
 */
async function loadPDF(url) {
    try {
        state.pdfDoc = await pdfjsLib.getDocument(url).promise;
        renderPage(1);
    } catch (err) {
        console.error('PDF load error:', err);
        Swal.fire('Erro', 'Falha ao carregar o PDF', 'error');
    }
}

/**
 * Renderiza uma página do PDF
 */
async function renderPage(pageNum) {
    try {
        const page = await state.pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale: state.currentScale });
        state.lastRenderedViewport = { viewport, page };

        state.canvas.width = viewport.width;
        state.canvas.height = viewport.height;
        state.canvas.style.width = viewport.width + 'px';
        state.canvas.style.height = viewport.height + 'px';

        const renderContext = { canvasContext: state.ctx, viewport: viewport };
        await page.render(renderContext).promise;
    } catch (err) {
        console.error('Page render error:', err);
        Swal.fire('Erro', 'Falha ao renderizar a página', 'error');
    }
}

/**
 * Processa clique no canvas para posicionar assinatura
 */
async function handleCanvasClick(ev) {
    if (!state.lastRenderedViewport) return;

    const rect = state.canvas.getBoundingClientRect();
    const clickX = ev.clientX - rect.left;
    const clickY = ev.clientY - rect.top;

    // Viewport e escala
    const viewport = state.lastRenderedViewport.viewport;
    const scale = viewport.scale || state.currentScale;

    // Converter coordenadas de pixels para milímetros (1 pt = 1/72 inch, 1 inch = 25.4mm)
    const x_mm = (clickX / scale) * 25.4 / 72;
    const y_mm_from_top = (clickY / scale) * 25.4 / 72;

    // Página atual e dimensões
    const pageNum = state.lastRenderedViewport.page.pageNumber;
    const w_mm = parseFloat(document.getElementById('sigWidth').value) || 60;
    const h_mm = parseFloat(document.getElementById('sigHeight').value) || 30;

    // Função auxiliar para converter mm para pixels na escala atual
    const mm_to_px = (mm) => (mm * 72 / 25.4) * scale;

    // Centralizar a tarja no clique
    const marker = document.getElementById('marker');
    marker.style.width = mm_to_px(w_mm) + 'px';
    marker.style.height = mm_to_px(h_mm) + 'px';
    marker.style.left = clickX + 'px';
    marker.style.top = clickY + 'px';
    marker.style.display = 'block';

    // Ajustar para centralizar a tarja: clique vira centro
    const x_mm_central = x_mm - (w_mm / 2);
    const y_mm_central = y_mm_from_top - (h_mm / 2);
    state.selectedPosition = {
        page: pageNum,
        x_mm: x_mm_central.toFixed(2),
        y_mm: y_mm_central.toFixed(2),
        width_mm: w_mm,
        height_mm: h_mm
    };

    Swal.fire({
        icon: 'info',
        title: 'Posição definida',
        text: `Página ${pageNum} — x=${state.selectedPosition.x_mm}mm y=${state.selectedPosition.y_mm}mm`
    });
}

/**
 * Assinar o documento
 */
async function handleSignConfirm(senha) {
    const cert = document.getElementById('certificado').value;
    const keyFileInput = document.getElementById('keyFile');

    if (!state.selectedFile) {
        return Swal.fire('Erro', 'Nenhum PDF carregado.', 'error');
    }

    // Preparar dados para envio
    const body = new FormData();
    body.append('file', state.selectedFile);
    body.append('cert', cert);
    body.append('password', senha);

    // Se tiver key file, anexar
    if (keyFileInput.files.length) {
        body.append('keyFile', keyFileInput.files[0]);
    }

    // Coordenadas e página
    if (state.selectedPosition) {
        body.append('page', state.selectedPosition.page);
        body.append('x_mm', state.selectedPosition.x_mm);
        body.append('y_mm', state.selectedPosition.y_mm);
        body.append('width_mm', state.selectedPosition.width_mm);
        body.append('height_mm', state.selectedPosition.height_mm);
    } else {
        const pageInput = document.getElementById('signaturePage').value;
        if (pageInput) body.append('page', pageInput);
        body.append('width_mm', document.getElementById('sigWidth').value || 60);
        body.append('height_mm', document.getElementById('sigHeight').value || 30);
    }

    try {
        const res = await fetch('sign.php?action=sign', { method: 'POST', body: body });
        const data = await res.json();

        if (data.success) {
            state.signedFilename = data.signedFile;
            document.getElementById('btnBaixar').disabled = false;
            document.getElementById('btnBaixar').dataset.signed = state.signedFilename;
            Swal.fire('Sucesso', 'Documento assinado: ' + state.signedFilename, 'success');

            const signedUrl = 'uploads/' + state.signedFilename;
            if (signedUrl) {
                loadPDF(signedUrl);
                state.selectedFile = state.signedFilename;
                document.getElementById('currentFile').innerText = state.signedFilename;
            }

            // Fechar modal de senha se estiver aberto
            const bs = bootstrap.Modal.getInstance(document.getElementById('senhaModal'));
            if (bs) bs.hide();
        } else {
            Swal.fire('Erro', data.message || 'Falha ao assinar', 'error');
        }
    } catch (err) {
        console.error('Sign error:', err);
        Swal.fire('Erro', 'Falha ao assinar o documento', 'error');
    }
}

/**
 * Download do documento assinado
 */
function handleDownload() {
    const signedFile = document.getElementById('btnBaixar').dataset.signed;
    if (!signedFile) {
        return Swal.fire('Erro', 'Nenhum arquivo assinado disponível', 'error');
    }
    window.location = 'download.php?file=' + encodeURIComponent(signedFile);
}