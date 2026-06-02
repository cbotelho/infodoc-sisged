# -*- coding: utf-8 -*-
"""Conexão com banco MySQL."""

import pymysql
from pymysql.cursors import DictCursor
from flask import request, has_request_context

from app.config import Config

def get_tenant_db_name():
    """Identifica qual banco de dados usar baseado na origem da requisicao."""
    db_name = Config.DB_NAME
    if has_request_context():
        tenant_header = request.headers.get('X-Tenant-DB')
        host = request.headers.get('Host', '')
        
        if tenant_header:
            db_name = tenant_header
        elif 'cipemac' in host:
            db_name = 'sisged_cipemac'
    return db_name

def get_db():
	"""Cria uma nova conexão com o banco de dados dinamicamente resolvido."""
	return pymysql.connect(
		host=Config.DB_HOST,
		port=Config.DB_PORT,
		user=Config.DB_USER,
		password=Config.DB_PASSWORD,
		database=get_tenant_db_name(),
		charset='utf8mb4',
		cursorclass=DictCursor,
		autocommit=False
	)
