/**
 * Assinador PDF Frontend - Versão Corrigida
 */

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

document.addEventListener('DOMContentLoaded', () => {
    state.canvas = document.getElementById('pdf-canvas');
    state.ctx = state.canvas.getContext('2d');
    setupEventListeners();
    loadCertsList();
});

async function loadCertsList() {
    try {
        const res = await fetch('sign.php?action=certs_list');
        const data = await res.json();
        if (data.success && data.certs) {
            const select = document.getElementById('certificado');
            select.innerHTML = '<option value="">-- Selecione um certificado --</option>';
            data.certs.forEach(cert => {
                const opt = document.createElement('option');
                opt.value = cert;
                opt.textContent = cert;
                select.appendChild(opt);
            });
        }
    } catch (err) {
        console.error('Erro ao carregar certificados:', err);
    }
}

function setupEventListeners() {
    document.getElementById('uploadForm').addEventListener('submit', handlePdfUpload);
    state.canvas.addEventListener('click', handleCanvasClick);
    
    document.getElementById('btnAssinar').addEventListener('click', () => {
        const cert = document.getElementById('certificado').value;
        if (!cert) {
            Swal.fire('Aviso', 'Selecione um certificado.', 'warning');
            return;
        }
        
        if (cert.toLowerCase().endsWith('.pfx') || cert.toLowerCase().endsWith('.p12')) {
            new bootstrap.Modal(document.getElementById('senhaModal')).show();
        } else {
            handleSignConfirm('');
        }
    });
    
    document.getElementById('confirmarAssinatura').addEventListener('click', () => {
        const senha = document.getElementById('senhaCertificado').value || '';
        handleSignConfirm(senha);
    });
    
    document.getElementById('btnBaixar').addEventListener('click', handleDownload);
    
    document.getElementById('certUploadForm').addEventListener('submit', handleCertUpload);
}

async function handlePdfUpload(e) {
    e.preventDefault();
    const fileInput = document.getElementById('pdfFile');
    if (!fileInput.files.length) {
        Swal.fire('Erro', 'Selecione um PDF.', 'error');
        return;
    }
    
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
            await loadCertsList();
        } else {
            statusDiv.innerText = data.message || 'Falha no upload do certificado.';
            Swal.fire('Erro', data.message || 'Falha no upload do certificado.', 'error');
        }
    } catch (err) {
        statusDiv.innerText = 'Erro ao enviar certificado.';
        Swal.fire('Erro', 'Erro ao enviar certificado.', 'error');
    }
}

async function loadPDF(url) {
    try {
        state.pdfDoc = await pdfjsLib.getDocument(url).promise;
        renderPage(1);
    } catch (err) {
        console.error('PDF load error:', err);
        Swal.fire('Erro', 'Falha ao carregar o PDF', 'error');
    }
}

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
    }
}

async function handleCanvasClick(ev) {
    if (!state.lastRenderedViewport) return;
    
    const rect = state.canvas.getBoundingClientRect();
    const clickX = ev.clientX - rect.left;
    const clickY = ev.clientY - rect.top;
    
    const viewport = state.lastRenderedViewport.viewport;
    const scale = viewport.scale || state.currentScale;
    
    // Converter pixels para mm
    const x_mm = (clickX / scale) * 25.4 / 72;
    const y_mm = (clickY / scale) * 25.4 / 72;
    
    const pageNum = state.lastRenderedViewport.page.pageNumber;
    const w_mm = parseFloat(document.getElementById('sigWidth').value) || 60;
    const h_mm = parseFloat(document.getElementById('sigHeight').value) || 30;
    
    // Mostrar marcador
    const mm_to_px = (mm) => (mm * 72 / 25.4) * scale;
    const marker = document.getElementById('marker');
    marker.style.width = mm_to_px(w_mm) + 'px';
    marker.style.height = mm_to_px(h_mm) + 'px';
    marker.style.left = (clickX - mm_to_px(w_mm)/2) + 'px';
    marker.style.top = (clickY - mm_to_px(h_mm)/2) + 'px';
    marker.style.display = 'block';
    
    state.selectedPosition = {
        page: pageNum,
        x_mm: (x_mm - w_mm/2).toFixed(2),
        y_mm: (y_mm - h_mm/2).toFixed(2),
        width_mm: w_mm,
        height_mm: h_mm
    };
    
    Swal.fire({
        icon: 'info',
        title: 'Posição definida',
        text: `Página ${pageNum} — x=${state.selectedPosition.x_mm}mm y=${state.selectedPosition.y_mm}mm`
    });
}

async function handleSignConfirm(senha) {
    if (!state.selectedFile) {
        Swal.fire('Erro', 'Nenhum PDF carregado.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'sign');
    formData.append('file', state.selectedFile);
    formData.append('cert', document.getElementById('certificado').value);
    formData.append('password', senha);
    
    if (state.selectedPosition) {
        formData.append('page', state.selectedPosition.page);
        formData.append('x_mm', state.selectedPosition.x_mm);
        formData.append('y_mm', state.selectedPosition.y_mm);
        formData.append('width_mm', state.selectedPosition.width_mm);
        formData.append('height_mm', state.selectedPosition.height_mm);
    }
    
    // Se tiver key file
    const keyFileInput = document.getElementById('keyFile');
    if (keyFileInput.files.length) {
        formData.append('keyFile', keyFileInput.files[0]);
    }
    
    try {
        Swal.fire({ title: 'Assinando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        const res = await fetch('sign.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        Swal.close();
        
        if (data.success) {
            state.signedFilename = data.signedFile;
            document.getElementById('btnBaixar').disabled = false;
            document.getElementById('btnBaixar').dataset.signed = state.signedFilename;
            
            Swal.fire('Sucesso', 'Documento assinado com sucesso!', 'success');
            
            // Recarregar visualização com documento assinado
            loadPDF('uploads/' + state.signedFilename);
            state.selectedFile = state.signedFilename;
            document.getElementById('currentFile').innerText = state.signedFilename;
            
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('senhaModal'));
            if (modal) modal.hide();
        } else {
            Swal.fire('Erro', data.message || 'Falha ao assinar', 'error');
        }
    } catch (err) {
        Swal.fire('Erro', 'Falha ao assinar o documento', 'error');
        console.error(err);
    }
}

function handleDownload() {
    const signedFile = document.getElementById('btnBaixar').dataset.signed;
    if (!signedFile) {
        Swal.fire('Erro', 'Nenhum arquivo assinado disponível', 'error');
        return;
    }
    window.location = 'download.php?file=' + encodeURIComponent(signedFile);
}