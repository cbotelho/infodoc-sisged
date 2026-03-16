# -*- coding: utf-8 -*-
"""Rotas standalone do assinador digital."""

import os
from datetime import datetime

from flask import Blueprint, jsonify, render_template, request, send_file

from app.config import Config
from app.services.pdf_signer import PDFSigner

UPLOAD_DIR = Config.UPLOAD_DIR
CERT_DIR = Config.CERT_DIR
TEMP_DIR = Config.TEMP_DIR

for path in (UPLOAD_DIR, CERT_DIR, TEMP_DIR):
    os.makedirs(path, exist_ok=True)

bp = Blueprint('standalone', __name__, url_prefix='/standalone')


@bp.route('/', methods=['GET'])
def index():
    """Interface standalone para upload manual e assinatura."""
    certificates = []
    if os.path.exists(CERT_DIR):
        certificates = [
            filename for filename in os.listdir(CERT_DIR)
            if filename.lower().endswith(('.pfx', '.p12', '.pem', '.cer', '.crt'))
        ]

    return render_template(
        'standalone.html',
        certificates=sorted(certificates),
        base_path='/standalone'
    )


@bp.route('/upload_pdf', methods=['POST'])
def upload_pdf():
    if 'pdf' not in request.files:
        return jsonify({'error': 'Nenhum arquivo enviado'}), 400

    upload = request.files['pdf']
    if upload.filename == '':
        return jsonify({'error': 'Arquivo vazio'}), 400

    if not upload.filename.lower().endswith('.pdf'):
        return jsonify({'error': 'Apenas arquivos PDF'}), 400

    filename = f"pdf_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
    output_path = os.path.join(UPLOAD_DIR, filename)
    upload.save(output_path)

    return jsonify({
        'success': True,
        'filename': filename,
        'url': f'/standalone/uploads/{filename}'
    })


@bp.route('/upload_cert', methods=['POST'])
def upload_cert():
    if 'cert' not in request.files:
        return jsonify({'error': 'Nenhum certificado enviado'}), 400

    upload = request.files['cert']
    if upload.filename == '':
        return jsonify({'error': 'Arquivo vazio'}), 400

    filename = os.path.basename(upload.filename)
    output_path = os.path.join(CERT_DIR, filename)
    upload.save(output_path)

    return jsonify({
        'success': True,
        'filename': filename,
        'message': 'Certificado enviado com sucesso'
    })


@bp.route('/list_certs', methods=['GET'])
def list_certs():
    certificates = []
    if os.path.exists(CERT_DIR):
        certificates = [
            filename for filename in os.listdir(CERT_DIR)
            if filename.lower().endswith(('.pfx', '.p12', '.pem', '.cer', '.crt'))
        ]

    return jsonify({'certificados': sorted(certificates)})


@bp.route('/uploads/<filename>', methods=['GET'])
def uploaded_file(filename):
    file_path = os.path.join(UPLOAD_DIR, os.path.basename(filename))
    if not os.path.exists(file_path):
        return jsonify({'error': 'Arquivo não encontrado'}), 404

    return send_file(file_path)


@bp.route('/download/<filename>', methods=['GET'])
def download_file(filename):
    file_path = os.path.join(UPLOAD_DIR, os.path.basename(filename))
    if not os.path.exists(file_path):
        return jsonify({'error': 'Arquivo não encontrado'}), 404

    return send_file(file_path, as_attachment=True, download_name=os.path.basename(file_path))


@bp.route('/sign_pdf', methods=['POST'])
def sign_pdf():
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

        pdf_path = os.path.join(UPLOAD_DIR, pdf_file)
        cert_path = os.path.join(CERT_DIR, cert_file)

        if not os.path.exists(pdf_path):
            return jsonify({'error': 'PDF não encontrado'}), 400
        if not os.path.exists(cert_path):
            return jsonify({'error': 'Certificado não encontrado'}), 400
        if cert_file.lower().endswith(('.pfx', '.p12')) and not password:
            return jsonify({'error': 'Senha do certificado não informada'}), 400

        signed_filename = f"assinado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
        signed_path = os.path.join(UPLOAD_DIR, signed_filename)

        signer = PDFSigner(temp_dir=TEMP_DIR)
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
    except Exception as exc:
        return jsonify({'error': str(exc)}), 500