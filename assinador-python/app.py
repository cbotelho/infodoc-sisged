# app.py - Assinador Digital ICP-Brasil
from flask import Flask, render_template, request, send_file, jsonify
import os
from datetime import datetime
from app.services.pdf_signer import PDFSigner

# ===== CONFIGURAÇÕES =====
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_FOLDER = os.path.join(BASE_DIR, 'uploads')
CERT_FOLDER = os.path.join(BASE_DIR, 'certs')
TEMP_FOLDER = os.path.join(BASE_DIR, 'temp')
# =========================

# Criar pastas se não existirem
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(CERT_FOLDER, exist_ok=True)
os.makedirs(TEMP_FOLDER, exist_ok=True)

# ===== VERIFICAÇÃO DO CRYPTOGRAPHY =====
CRYPTOGRAPHY_AVAILABLE = False
try:
    from cryptography.hazmat.primitives import hashes, serialization
    from cryptography.hazmat.primitives.serialization import pkcs12
    from cryptography.hazmat.primitives.asymmetric import padding
    from cryptography import x509
    CRYPTOGRAPHY_AVAILABLE = True
    print("✅ Cryptography disponível - Assinatura real habilitada")
except ImportError:
    CRYPTOGRAPHY_AVAILABLE = False
    print("⚠️ Cryptography NÃO disponível - Usando simulação")
    print("   Para assinatura real, instale: pip install cryptography")
# =======================================

app = Flask(__name__, template_folder=os.path.join(BASE_DIR, 'templates'))

@app.route('/')
def index():
    """Página principal do assinador"""
    certificados = []
    if os.path.exists(CERT_FOLDER):
        certificados = [f for f in os.listdir(CERT_FOLDER) 
                       if f.lower().endswith(('.pfx', '.p12', '.pem', '.cer', '.crt'))]
    return render_template('index.html', certificados=certificados)

@app.route('/upload_pdf', methods=['POST'])
def upload_pdf():
    """Upload de PDF"""
    if 'pdf' not in request.files:
        return jsonify({'error': 'Nenhum arquivo enviado'}), 400
    
    file = request.files['pdf']
    if file.filename == '':
        return jsonify({'error': 'Arquivo vazio'}), 400
    
    if not file.filename.lower().endswith('.pdf'):
        return jsonify({'error': 'Apenas arquivos PDF'}), 400
    
    filename = f"pdf_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
    filepath = os.path.join(UPLOAD_FOLDER, filename)
    file.save(filepath)
    
    return jsonify({
        'success': True,
        'filename': filename,
        'url': f'/uploads/{filename}'
    })

@app.route('/upload_cert', methods=['POST'])
def upload_cert():
    """Upload de certificado"""
    if 'cert' not in request.files:
        return jsonify({'error': 'Nenhum certificado enviado'}), 400
    
    file = request.files['cert']
    if file.filename == '':
        return jsonify({'error': 'Arquivo vazio'}), 400
    
    safe_name = os.path.basename(file.filename)
    filepath = os.path.join(CERT_FOLDER, safe_name)
    file.save(filepath)
    
    return jsonify({
        'success': True,
        'filename': safe_name,
        'message': 'Certificado enviado com sucesso'
    })

@app.route('/list_certs', methods=['GET'])
def list_certs():
    """Lista certificados disponíveis"""
    certificados = []
    if os.path.exists(CERT_FOLDER):
        certificados = [f for f in os.listdir(CERT_FOLDER) 
                       if f.lower().endswith(('.pfx', '.p12', '.pem', '.cer', '.crt'))]
    return jsonify({'certificados': certificados})

@app.route('/uploads/<filename>')
def uploaded_file(filename):
    """Servir arquivos de upload"""
    filepath = os.path.join(UPLOAD_FOLDER, os.path.basename(filename))
    
    if not os.path.exists(filepath):
        return jsonify({'error': 'Arquivo não encontrado'}), 404
    
    return send_file(filepath)

@app.route('/download/<filename>')
def download_file(filename):
    """Baixa o PDF assinado."""
    filepath = os.path.join(UPLOAD_FOLDER, os.path.basename(filename))

    if not os.path.exists(filepath):
        return jsonify({'error': 'Arquivo não encontrado'}), 404

    return send_file(filepath, as_attachment=True, download_name=os.path.basename(filepath))

@app.route('/health')
def health():
    """Health check simples para uso local."""
    return jsonify({'status': 'healthy'})

@app.route('/debug')
def debug():
    """Página de debug para listar arquivos"""
    base_dir = os.path.dirname(os.path.abspath(__file__))
    upload_dir = os.path.join(base_dir, UPLOAD_FOLDER)
    
    files = []
    if os.path.exists(upload_dir):
        files = os.listdir(upload_dir)
    
    html = "<h1>🔍 Debug - Arquivos disponíveis</h1>"
    html += f"<p><strong>Diretório:</strong> {upload_dir}</p>"
    html += "<ul>"
    
    for f in sorted(files):
        if f.lower().endswith('.pdf'):
            filepath = os.path.join(upload_dir, f)
            size = os.path.getsize(filepath)
            html += f'<li><a href="/uploads/{f}">{f}</a> ({size} bytes)</li>'
    
    html += "</ul>"
    return html

@app.route('/sign_pdf', methods=['POST'])
def sign_pdf():
    """Assina o PDF enviado pela interface standalone."""
    
    try:
        data = request.get_json(silent=True) or {}
        
        pdf_file = os.path.basename(data.get('pdf_file', ''))
        cert_file = os.path.basename(data.get('cert_file', ''))
        password = data.get('password', '')
        position = data.get('position') or {}
        normalized_position = {
            'x': float(position.get('x', 20)),
            'y': float(position.get('y', 50)),
            'page': int(position.get('page', position.get('pagina', 1) or 1)),
            'width': float(position.get('width', position.get('largura', 80))),
            'height': float(position.get('height', position.get('altura', 30)))
        }
        
        if not pdf_file:
            return jsonify({'error': 'PDF não informado'}), 400
        if not cert_file:
            return jsonify({'error': 'Certificado não informado'}), 400
        
        pdf_path = os.path.join(UPLOAD_FOLDER, pdf_file)
        cert_path = os.path.join(CERT_FOLDER, cert_file)
        
        if not os.path.exists(pdf_path):
            return jsonify({'error': 'PDF não encontrado'}), 400
        if not os.path.exists(cert_path):
            return jsonify({'error': 'Certificado não encontrado'}), 400
        if cert_file.lower().endswith(('.pfx', '.p12')) and not password:
            return jsonify({'error': 'Senha do certificado não informada'}), 400
        
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        signed_filename = f"assinado_{timestamp}.pdf"
        signed_path = os.path.join(UPLOAD_FOLDER, signed_filename)

        signer = PDFSigner(temp_dir=TEMP_FOLDER)
        success, message, _ = signer.sign_pdf(
            pdf_path=pdf_path,
            cert_path=cert_path,
            password=password,
            output_path=signed_path,
            position=normalized_position
        )

        if not success:
            return jsonify({'error': message}), 500
        
        return jsonify({
            'success': True,
            'signed_file': signed_filename,
            'message': message
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

# ===== INICIALIZAÇÃO DO SERVIDOR =====
if __name__ == '__main__':
    print("="*60)
    print("🚀 Assinador Digital ICP-Brasil")
    print("="*60)
    print(f"📁 Uploads: {os.path.abspath(UPLOAD_FOLDER)}")
    print(f"📁 Certificados: {os.path.abspath(CERT_FOLDER)}")
    print(f"🔐 Cryptography: {'✅ OK' if CRYPTOGRAPHY_AVAILABLE else '❌ Instale: pip install cryptography'}")
    print("="*60)
    print("🌐 Servidor rodando em:")
    print("   • Local: http://127.0.0.1:5000")
    print("   • Rede:  http://192.168.31.227:5000")
    print("="*60)
    print("Pressione CTRL+C para parar")
    print("="*60)
    
    app.run(host='0.0.0.0', port=5000, debug=True)