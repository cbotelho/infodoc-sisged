# -*- coding: utf-8 -*-
"""
validators.py - Funções de validação para certificados, PDFs e tokens
"""

import re
import os
from datetime import datetime
from typing import Tuple, Optional, Dict, Any
import logging
from cryptography import x509
from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives import hashes
from cryptography.exceptions import InvalidSignature

logger = logging.getLogger(__name__)

class PDFValidator:
    """Validações específicas para arquivos PDF"""
    
    @staticmethod
    def is_valid_pdf(file_path: str) -> Tuple[bool, str]:
        """
        Verifica se o arquivo é um PDF válido
        
        Args:
            file_path: Caminho do arquivo
            
        Returns:
            (bool, str): (é válido?, mensagem de erro)
        """
        if not os.path.exists(file_path):
            return False, "Arquivo não encontrado"
        
        # Verificar extensão
        if not file_path.lower().endswith('.pdf'):
            return False, "Extensão não é .pdf"
        
        try:
            # Verificar magic number do PDF (%PDF)
            with open(file_path, 'rb') as f:
                header = f.read(5)
                if header != b'%PDF-':
                    return False, "Arquivo não é um PDF válido (header incorreto)"
            
            # Verificar tamanho
            size = os.path.getsize(file_path)
            if size == 0:
                return False, "Arquivo PDF vazio"
            
            if size > 100 * 1024 * 1024:  # 100MB
                return False, "Arquivo PDF muito grande (máx 100MB)"
            
            return True, "PDF válido"
            
        except Exception as e:
            logger.error(f"Erro ao validar PDF: {e}")
            return False, f"Erro ao validar PDF: {str(e)}"
    
    @staticmethod
    def get_pdf_info(file_path: str) -> Dict[str, Any]:
        """
        Extrai informações básicas do PDF
        
        Args:
            file_path: Caminho do arquivo
            
        Returns:
            Dict com informações do PDF
        """
        info = {
            'filename': os.path.basename(file_path),
            'size': os.path.getsize(file_path),
            'modified': datetime.fromtimestamp(os.path.getmtime(file_path)).isoformat(),
            'pages': 0,
            'has_signatures': False
        }
        
        try:
            # Tentar ler número de páginas (simplificado)
            with open(file_path, 'rb') as f:
                content = f.read(4096)  # Ler apenas início do arquivo
                # Procurar por /Count ou /Pages
                match = re.search(rb'/Count\s+(\d+)', content)
                if match:
                    info['pages'] = int(match.group(1))
                
                # Verificar se já tem assinaturas
                if b'/Sig' in content or b'/Signature' in content:
                    info['has_signatures'] = True
        
        except Exception as e:
            logger.warning(f"Erro ao ler info do PDF: {e}")
        
        return info


class CertificateValidator:
    """Validações para certificados digitais ICP-Brasil"""
    
    # Padrões ICP-Brasil para identificação
    ICP_BRASIL_PATTERNS = [
        r'ICP-Brasil',
        r'AC\s+(Certificadora|Raiz)',
        r'Autoridade Certificadora',
        r'Secretaria da Receita Federal',
        r'Serpro',
        r'Certisign',
        r'Serasa',
        r'Valid'
    ]
    
    @classmethod
    def is_icp_brasil(cls, certificate_data: bytes) -> bool:
        """
        Verifica se o certificado é do padrão ICP-Brasil
        
        Args:
            certificate_data: Dados do certificado
            
        Returns:
            bool: True se for ICP-Brasil
        """
        try:
            cert = x509.load_pem_x509_certificate(certificate_data, default_backend())
            
            # Verificar issuer
            issuer_str = str(cert.issuer)
            
            for pattern in cls.ICP_BRASIL_PATTERNS:
                if re.search(pattern, issuer_str, re.IGNORECASE):
                    return True
            
            # Verificar políticas da ICP-Brasil
            try:
                for ext in cert.extensions:
                    if ext.oid._name == 'certificatePolicies':
                        policies_str = str(ext.value)
                        if '2.16.76' in policies_str:  # OID ICP-Brasil
                            return True
            except:
                pass
            
            return False
            
        except Exception as e:
            logger.error(f"Erro ao verificar ICP-Brasil: {e}")
            return False
    
    @staticmethod
    def validate_certificate(cert_data: bytes, password: Optional[str] = None) -> Tuple[bool, str, Dict]:
        """
        Valida certificado digital
        
        Args:
            cert_data: Dados do certificado (PEM ou PKCS12)
            password: Senha (obrigatória para PKCS12)
            
        Returns:
            (is_valid, message, info)
        """
        info = {}
        
        try:
            # Tentar ler como PKCS12
            from cryptography.hazmat.primitives.serialization import pkcs12
            
            if b'-----BEGIN' not in cert_data:
                # PKCS12
                try:
                    private_key, certificate, additional_certs = pkcs12.load_key_and_certificates(
                        cert_data,
                        password.encode() if password else None,
                        default_backend()
                    )
                    info['type'] = 'PKCS12'
                    info['has_private_key'] = True
                except Exception as e:
                    return False, f"Erro ao ler PKCS12: {str(e)}", info
            else:
                # PEM
                try:
                    certificate = x509.load_pem_x509_certificate(cert_data, default_backend())
                    info['type'] = 'PEM'
                    info['has_private_key'] = 'PRIVATE KEY' in cert_data.decode('utf-8', errors='ignore')
                except Exception as e:
                    return False, f"Erro ao ler PEM: {str(e)}", info
            
            # Extrair informações
            info['subject'] = str(certificate.subject)
            info['issuer'] = str(certificate.issuer)
            info['serial'] = str(certificate.serial_number)
            info['not_valid_before'] = certificate.not_valid_before_utc.isoformat()
            info['not_valid_after'] = certificate.not_valid_after_utc.isoformat()
            info['is_icp_brasil'] = cls.is_icp_brasil(cert_data)
            
            # Verificar validade temporal
            now = datetime.utcnow()
            if now < certificate.not_valid_before_utc:
                return False, "Certificado ainda não é válido", info
            if now > certificate.not_valid_after_utc:
                return False, "Certificado expirado", info
            
            # Verificar se tem chave privada (para PKCS12 já verificamos)
            if not info['has_private_key']:
                return False, "Certificado não contém chave privada", info
            
            return True, "Certificado válido", info
            
        except Exception as e:
            logger.error(f"Erro na validação do certificado: {e}")
            return False, f"Erro na validação: {str(e)}", info


class TokenValidator:
    """Validação de tokens de sessão"""
    
    def __init__(self, db_connection):
        self.db = db_connection
    
    def validate_token(self, token: str) -> Tuple[bool, Dict]:
        """
        Valida token de sessão
        
        Args:
            token: Token a ser validado
            
        Returns:
            (is_valid, session_data)
        """
        if not token or len(token) < 32:
            return False, {}
        
        try:
            with self.db.cursor() as cursor:
                cursor.execute("""
                    SELECT s.*, d.caminho as doc_path, d.nome_original
                    FROM sessoes_assinatura s
                    JOIN documentos d ON s.documento_id = d.id
                    WHERE s.token = %s 
                    AND s.data_fim IS NULL
                    AND s.data_inicio > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                """, (token,))
                
                session = cursor.fetchone()
                
                if not session:
                    return False, {}
                
                return True, session
                
        except Exception as e:
            logger.error(f"Erro ao validar token: {e}")
            return False, {}


class InputValidator:
    """Validações de entrada de dados"""
    
    @staticmethod
    def validate_position(position: Dict) -> Tuple[bool, str]:
        """
        Valida posição da assinatura
        
        Args:
            position: Dict com x, y, page, width, height
            
        Returns:
            (is_valid, error_message)
        """
        page_key = 'pagina' if 'pagina' in position else 'page'
        required = ['x', 'y', page_key]
        
        for field in required:
            if field not in position:
                return False, f"Campo obrigatório: {field}"
        
        try:
            x = float(position.get('x', 0))
            y = float(position.get('y', 0))
            page = int(position.get('pagina', position.get('page', 1)))
            width = float(position.get('width', position.get('largura', 80)))
            height = float(position.get('height', position.get('altura', 30)))
            
            if x < 0 or y < 0:
                return False, "Coordenadas devem ser positivas"
            
            if page < 1:
                return False, "Número de página inválido"
            
            if width < 20 or width > 500:
                return False, "Largura deve estar entre 20 e 500mm"
            
            if height < 10 or height > 200:
                return False, "Altura deve estar entre 10 e 200mm"
            
            return True, "Posição válida"
            
        except ValueError:
            return False, "Valores numéricos inválidos"
    
    @staticmethod
    def sanitize_filename(filename: str) -> str:
        """
        Sanitiza nome de arquivo
        
        Args:
            filename: Nome original
            
        Returns:
            Nome sanitizado
        """
        # Remover caracteres especiais
        filename = re.sub(r'[^a-zA-Z0-9\._-]', '', filename)
        
        # Evitar path traversal
        filename = os.path.basename(filename)
        
        # Limitar tamanho
        if len(filename) > 255:
            name, ext = os.path.splitext(filename)
            filename = name[:250] + ext
        
        return filename or 'documento.pdf'# Validações diversas
