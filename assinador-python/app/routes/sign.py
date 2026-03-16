# Rota de assinatura
# -*- coding: utf-8 -*-
"""
sign.py - Rotas para assinatura de documentos
"""

import os
import logging
from datetime import datetime
from flask import Blueprint, request, jsonify, render_template, redirect, url_for

from app.services.pdf_signer import PDFSigner
from app.services.database import get_db
from app.utils.validators import TokenValidator, InputValidator
from app.config import Config

logger = logging.getLogger(__name__)
bp = Blueprint('sign', __name__, url_prefix='')

@bp.route('/assinador')
def assinador():
    """
    Página do assinador digital
    Aceita token/doc no fluxo autenticado ou abre o modo standalone sem parâmetros.
    """
    token = request.args.get('token')
    doc_path = request.args.get('doc')

    if not token and not doc_path:
        return redirect(url_for('standalone.index'))

    if not token or not doc_path:
        return render_template(
            'error.html',
            error='Informe token e documento, ou acesse sem parâmetros para usar o modo standalone.'
        ), 400
    
    # Validar token com banco de dados
    db = get_db()
    validator = TokenValidator(db)
    is_valid, session_data = validator.validate_token(token)
    
    if not is_valid:
        return render_template('error.html',
                             error="Sessão inválida ou expirada"), 403
    
    # Buscar certificados do usuário
    with db.cursor() as cursor:
        cursor.execute("""
            SELECT id, nome, caminho, emissor, validade
            FROM certificados 
            WHERE usuario_id = %s AND ativo = 1
        """, (session_data['usuario_id'],))
        certificados = cursor.fetchall()
    
    return render_template('assinador.html',
                         documento=doc_path,
                         certificados=certificados,
                         token=token,
                         doc_nome=os.path.basename(doc_path))

@bp.route('/api/sign', methods=['POST'])
def api_sign():
    """
    API para assinar documento
    Recebe token, certificado_id, senha e posição
    """
    try:
        data = request.json
        token = data.get('token')
        cert_id = data.get('certificado_id')
        password = data.get('senha')
        raw_position = data.get('posicao', {}) or {}
        position = {
            'x': raw_position.get('x', 20),
            'y': raw_position.get('y', 50),
            'pagina': raw_position.get('pagina', raw_position.get('page', 1)),
            'width': raw_position.get('width', raw_position.get('largura', 80)),
            'height': raw_position.get('height', raw_position.get('altura', 30))
        }
        
        # Validar entrada
        if not all([token, cert_id, password]):
            return jsonify({'success': False, 'error': 'Parâmetros obrigatórios'}), 400
        
        # Validar token
        db = get_db()
        validator = TokenValidator(db)
        is_valid, session_data = validator.validate_token(token)
        
        if not is_valid:
            return jsonify({'success': False, 'error': 'Sessão inválida'}), 403
        
        # Validar posição
        is_valid_pos, pos_error = InputValidator.validate_position(position)
        if not is_valid_pos:
            return jsonify({'success': False, 'error': pos_error}), 400
        
        # Buscar certificado
        with db.cursor() as cursor:
            cursor.execute("""
                SELECT caminho FROM certificados 
                WHERE id = %s AND usuario_id = %s
            """, (cert_id, session_data['usuario_id']))
            cert = cursor.fetchone()
            
            if not cert:
                return jsonify({'success': False, 'error': 'Certificado não encontrado'}), 404
        
        # Assinar documento
        signer = PDFSigner(temp_dir=Config.TEMP_DIR)
        
        x = position.get('x', 20)
        y = position.get('y', 50)
        page = position.get('pagina', 1)
        width = position.get('width', 80)
        height = position.get('height', 30)
        
        # Caminho de saída
        output_dir = os.path.dirname(session_data['doc_path'])
        output_filename = f"assinado_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
        output_path = os.path.join(output_dir, output_filename)
        
        success, message, signed_pdf = signer.sign_with_position(
            pdf_path=session_data['doc_path'],
            cert_path=cert['caminho'],
            password=password,
            x_mm=x,
            y_mm=y,
            page=page,
            width_mm=width,
            height_mm=height
        )
        
        if not success:
            return jsonify({'success': False, 'error': message}), 500
        
        # Salvar PDF assinado
        with open(output_path, 'wb') as f:
            f.write(signed_pdf)
        
        # Registrar no banco de dados
        with db.cursor() as cursor:
            # Inserir documento assinado
            cursor.execute("""
                INSERT INTO documentos 
                (nome_original, caminho, data_upload, documento_original_id, usuario_id)
                VALUES (%s, %s, NOW(), %s, %s)
            """, (output_filename, output_path, session_data['documento_id'], 
                  session_data['usuario_id']))
            
            novo_doc_id = cursor.lastrowid
            
            # Criar tramitação
            cursor.execute("""
                INSERT INTO tramitacoes 
                (documento_id, de_usuario_id, para_usuario_id, data_tramitacao, status)
                VALUES (%s, %s, %s, NOW(), 'ASSINADO')
            """, (novo_doc_id, session_data['usuario_id'], session_data.get('remetente_id')))
            
            # Atualizar sessão
            cursor.execute("""
                UPDATE sessoes_assinatura 
                SET data_fim = NOW(), documento_assinado_id = %s
                WHERE token = %s
            """, (novo_doc_id, token))
            
            db.commit()
        
        # Log da operação
        logger.info(f"Documento assinado: {output_filename} (ID: {novo_doc_id})")
        
        return jsonify({
            'success': True,
            'message': 'Documento assinado com sucesso',
            'documento_id': novo_doc_id,
            'arquivo': output_filename
        })
        
    except Exception as e:
        logger.error(f"Erro na assinatura: {e}", exc_info=True)
        return jsonify({'success': False, 'error': str(e)}), 500