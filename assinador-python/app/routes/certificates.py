# -*- coding: utf-8 -*-
"""Rotas mínimas de certificados."""

from flask import Blueprint, jsonify

bp = Blueprint('certificates', __name__, url_prefix='/certificates')


@bp.route('/health', methods=['GET'])
def health():
	"""Health check simples do blueprint de certificados."""
	return jsonify({'success': True, 'service': 'certificates'})
