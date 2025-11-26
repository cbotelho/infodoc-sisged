document.getElementById('uploadForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const fd = new FormData(this);
  const res = await fetch('upload.php', {method:'POST', body: fd});
  const data = await res.json();
  if (data.success) {
    document.getElementById('pdfFrame').src = 'uploads/' + data.file;
    document.getElementById('status').innerText = 'Arquivo carregado: ' + data.file;
  } else { alert('Erro no upload'); }
});
document.getElementById('btnSign').addEventListener('click', async function(){
  const cert = document.getElementById('certSelect').value;
  if (!cert) return alert('Selecione um certificado em /certs');
  
  // Verificar extensão do certificado
  const ext = cert.toLowerCase().split('.').pop();
  let password = '';
  
  // Só pedir senha se for .pfx ou .p12
  if (ext === 'pfx' || ext === 'p12') {
    password = document.getElementById('certPassword').value || '';
    if (!password) {
      return alert('Informe a senha do certificado');
    }
  }
  
  const pdfUrl = document.getElementById('pdfFrame').src;
  if (!pdfUrl) return alert('Envie um PDF primeiro');
  const fd = new FormData();
  fd.append('cert', cert);
  fd.append('password', password);
  // extract filename
  const parts = pdfUrl.split('/'); const fn = parts[parts.length-1];
  fd.append('file', fn);
  const img = document.getElementById('sigImage').files[0];
  if (img) fd.append('sigImage', img);
  const res = await fetch('assinar.php', {method:'POST', body: fd});
  const data = await res.json();
  if (data.success) {
    document.getElementById('btnDownload').href = 'download.php?file=' + encodeURIComponent(data.signed);
    document.getElementById('btnDownload').classList.remove('disabled');
    document.getElementById('status').innerText = 'Assinatura concluída: ' + data.signed;
  } else {
    alert('Erro: ' + (data.message || data.error || 'unknown'));
  }
});