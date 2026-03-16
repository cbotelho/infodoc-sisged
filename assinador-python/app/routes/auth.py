# -*- coding: utf-8 -*-
"""Rotas mínimas de autenticação."""

from flask import Blueprint, jsonify

bp = Blueprint('auth', __name__, url_prefix='/auth')


@bp.route('/health', methods=['GET'])
def health():
	"""Health check simples do blueprint de auth."""
	return jsonify({'success': True, 'service': 'auth'})
