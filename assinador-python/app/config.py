# app/config.py
import os
from dotenv import load_dotenv

load_dotenv()

class Config:
    # Banco de Dados
    DB_HOST = os.getenv('DB_HOST', 'localhost')
    DB_PORT = int(os.getenv('DB_PORT', 3306))
    DB_NAME = os.getenv('DB_NAME', 'sisged_gea')
    DB_USER = os.getenv('DB_USER', 'admin')
    DB_PASSWORD = os.getenv('DB_PASSWORD', '')
    
    # Segurança
    SECRET_KEY = os.getenv('SECRET_KEY', 'dev-secret-key')
    TOKEN_EXPIRY = int(os.getenv('TOKEN_EXPIRY', 3600))
    
    # Paths
    UPLOAD_DIR = os.getenv('UPLOAD_DIR', '/var/www/vhosts/gea/assinatura/uploads/')
    CERT_DIR = os.getenv('CERT_DIR', '/var/www/vhosts/gea/assinatura/certs/')
    TEMP_DIR = os.getenv('TEMP_DIR', './temp/')
    
    # Servidor
    HOST = os.getenv('HOST', '0.0.0.0')
    PORT = int(os.getenv('PORT', 5000))
    DEBUG = os.getenv('DEBUG', 'false').lower() == 'true'
    
    # ICP-Brasil
    ICP_BRASIL_ALGORITHM = 'sha256WithRSAEncryption'
    ICP_BRASIL_HASH = 'sha256'