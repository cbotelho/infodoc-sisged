#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
run.py - Entry point da aplicação Flask
Executa o servidor de desenvolvimento ou importa para produção (gunicorn)
"""

import os
import sys
from dotenv import load_dotenv

# Carregar variáveis de ambiente do arquivo .env
load_dotenv()

# Adicionar o diretório atual ao path para imports
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

# Importar a aplicação
from app.main import app

if __name__ == '__main__':
    """
    Execução em modo desenvolvimento
    Para produção, use: gunicorn -w 4 -b 127.0.0.1:5000 run:app
    """
    import logging
    from logging.handlers import RotatingFileHandler
    
    # Configurar logging
    log_dir = os.path.join(os.path.dirname(__file__), 'logs')
    if not os.path.exists(log_dir):
        os.makedirs(log_dir)
    
    log_file = os.path.join(log_dir, 'app.log')
    handler = RotatingFileHandler(log_file, maxBytes=10*1024*1024, backupCount=5)
    handler.setFormatter(logging.Formatter(
        '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    ))
    
    app.logger.addHandler(handler)
    app.logger.setLevel(logging.INFO)
    
    # Obter configurações do ambiente
    host = os.getenv('HOST', '127.0.0.1')
    port = int(os.getenv('PORT', 5000))
    debug = os.getenv('DEBUG', 'False').lower() == 'true'
    
    app.logger.info(f"Iniciando servidor em {host}:{port} (debug={debug})")
    
    # Executar aplicação
    app.run(
        host=host,
        port=port,
        debug=debug,
        threaded=True
    )