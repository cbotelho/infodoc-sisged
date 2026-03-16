# -*- coding: utf-8 -*-
"""Conexão com banco MySQL."""

import pymysql
from pymysql.cursors import DictCursor

from app.config import Config


def get_db():
	"""Cria uma nova conexão com o banco de dados."""
	return pymysql.connect(
		host=Config.DB_HOST,
		port=Config.DB_PORT,
		user=Config.DB_USER,
		password=Config.DB_PASSWORD,
		database=Config.DB_NAME,
		charset='utf8mb4',
		cursorclass=DictCursor,
		autocommit=False
	)
