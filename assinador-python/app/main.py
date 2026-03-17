# app/main.py
from flask import Flask, jsonify, redirect, url_for
from flask_cors import CORS
import os
import logging
from datetime import datetime

from app.config import Config
from app.routes import auth, sign, certificates, standalone

BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
LOG_DIR = os.path.join(BASE_DIR, 'logs')
os.makedirs(LOG_DIR, exist_ok=True)

# Configurar logging
logging.basicConfig(
    filename=os.path.join(LOG_DIR, 'app.log'),
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)

# Criar aplicação Flask
app = Flask(__name__,
            template_folder='templates',
            static_folder='static')

app.config.from_object(Config)
CORS(app)

# Registrar blueprints
app.register_blueprint(auth.bp)
app.register_blueprint(sign.bp)
app.register_blueprint(certificates.bp)
app.register_blueprint(standalone.bp)

@app.route('/')
def index():
    """Rota raiz publica o modo standalone por padrao."""
    return redirect(url_for('standalone.index'))


@app.route('/info')
def info():
    """Rota tecnica com informações básicas do serviço."""
    return jsonify({
        'service': 'assinador-python',
        'status': 'online',
        'version': '1.0.0',
        'routes': {
            'info': '/info',
            'health': '/health',
            'assinador': '/assinador[?token=<token>&doc=<caminho>]',
            'api_sign': '/api/sign',
            'standalone': '/standalone/'
        }
    })

@app.route('/health')
def health():
    """Health check para o supervisor"""
    return jsonify({
        'status': 'healthy',
        'timestamp': datetime.now().isoformat(),
        'version': '1.0.0'
    })

@app.errorhandler(404)
def not_found(error):
    return jsonify({'error': 'Rota não encontrada'}), 404

@app.errorhandler(500)
def internal_error(error):
    logging.error(f"Erro interno: {error}")
    return jsonify({'error': 'Erro interno do servidor'}), 500

if __name__ != '__main__':
    # Configurar logging para produção
    gunicorn_logger = logging.getLogger('gunicorn.error')
    app.logger.handlers = gunicorn_logger.handlers
    app.logger.setLevel(gunicorn_logger.level)