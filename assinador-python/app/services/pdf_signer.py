# -*- coding: utf-8 -*-
"""
pdf_signer.py - Módulo principal de assinatura digital de PDFs
Padrão ICP-Brasil (PKCS#7, SHA256, certificados A1/A3)
"""

import os
import io
import hashlib
import logging
from datetime import datetime
from typing import Optional, Tuple, Dict, Any, Union, List
from pathlib import Path

from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding, rsa, ec
from cryptography.hazmat.primitives.serialization import pkcs12, Encoding
from cryptography import x509
from cryptography.hazmat.backends import default_backend
from cryptography.exceptions import InvalidSignature

import PyPDF2
from PyPDF2 import PdfReader, PdfWriter
from PyPDF2.generic import (
    NameObject, TextStringObject, NumberObject, 
    ArrayObject, DictionaryObject, ByteStringObject
)

# Configurar logging
logger = logging.getLogger(__name__)

class PDFSigner:
    """
    Classe principal para assinatura digital de PDFs no padrão ICP-Brasil
    Suporta certificados A1 (arquivo) e A3 (token)
    """
    
    # Constantes PDF para assinatura
    SIG_FILTER = NameObject("/Adobe.PPKLite")
    SIG_SUBFILTER = NameObject("/adbe.pkcs7.detached")
    SIG_TYPE = NameObject("/Sig")
    
    def __init__(self, temp_dir: str = "./temp"):
        """
        Inicializa o assinador
        
        Args:
            temp_dir: Diretório para arquivos temporários
        """
        self.temp_dir = temp_dir
        self._ensure_temp_dir()
        
    def _ensure_temp_dir(self):
        """Garante que o diretório temporário existe"""
        os.makedirs(self.temp_dir, exist_ok=True)
        
    def load_certificate(self, cert_path: str, password: Optional[str] = None) -> Tuple[Any, Any, Dict]:
        """
        Carrega certificado digital (PKCS#12 ou PEM)
        
        Args:
            cert_path: Caminho do arquivo de certificado
            password: Senha do certificado (obrigatória para PKCS#12)
            
        Returns:
            Tuple (private_key, certificate, cert_info)
            
        Raises:
            ValueError: Se o certificado for inválido
        """
        logger.info(f"Carregando certificado: {cert_path}")
        
        with open(cert_path, 'rb') as f:
            cert_data = f.read()
        
        cert_info = {}
        
        try:
            # Tentar PKCS#12 (PFX/P12)
            if b'-----BEGIN' not in cert_data:
                private_key, certificate, additional_certs = pkcs12.load_key_and_certificates(
                    cert_data,
                    password.encode() if password else None,
                    default_backend()
                )
                cert_info['format'] = 'PKCS12'
            else:
                # Tentar PEM
                try:
                    # Separar chave privada e certificado
                    lines = cert_data.decode('utf-8').split('\n')
                    cert_lines = []
                    key_lines = []
                    current = None
                    
                    for line in lines:
                        if 'BEGIN CERTIFICATE' in line:
                            current = 'cert'
                            cert_lines = [line]
                        elif 'END CERTIFICATE' in line:
                            cert_lines.append(line)
                            current = None
                        elif 'BEGIN PRIVATE KEY' in line or 'BEGIN RSA PRIVATE KEY' in line:
                            current = 'key'
                            key_lines = [line]
                        elif 'END PRIVATE KEY' in line or 'END RSA PRIVATE KEY' in line:
                            key_lines.append(line)
                            current = None
                        elif current == 'cert':
                            cert_lines.append(line)
                        elif current == 'key':
                            key_lines.append(line)
                    
                    cert_pem = '\n'.join(cert_lines).encode()
                    key_pem = '\n'.join(key_lines).encode()
                    
                    if cert_pem and key_pem:
                        certificate = x509.load_pem_x509_certificate(cert_pem, default_backend())
                        
                        if 'RSA' in key_pem.decode():
                            private_key = serialization.load_pem_private_key(
                                key_pem, password.encode() if password else None, default_backend()
                            )
                        else:
                            private_key = serialization.load_pem_private_key(
                                key_pem, password.encode() if password else None, default_backend()
                            )
                        cert_info['format'] = 'PEM'
                    else:
                        raise ValueError("PEM inválido: não contém certificado e chave privada")
                        
                except Exception as e:
                    raise ValueError(f"Erro ao ler PEM: {str(e)}")
            
            # Extrair informações do certificado
            cert_info.update(self._extract_cert_info(certificate))
            
            return private_key, certificate, cert_info
            
        except Exception as e:
            logger.error(f"Erro ao carregar certificado: {e}")
            raise ValueError(f"Certificado inválido ou senha incorreta: {str(e)}")
    
    def _extract_cert_info(self, certificate: x509.Certificate) -> Dict:
        """
        Extrai informações do certificado
        
        Args:
            certificate: Objeto Certificate do cryptography
            
        Returns:
            Dict com informações
        """
        info = {
            'subject': {},
            'issuer': {},
            'serial': str(certificate.serial_number),
            'not_valid_before': certificate.not_valid_before_utc.isoformat(),
            'not_valid_after': certificate.not_valid_after_utc.isoformat(),
            'fingerprint': certificate.fingerprint(hashes.SHA256()).hex()
        }
        
        # Extrair subject
        for attr in certificate.subject:
            info['subject'][attr.oid._name] = attr.value
        
        # Extrair issuer
        for attr in certificate.issuer:
            info['issuer'][attr.oid._name] = attr.value
        
        # Verificar ICP-Brasil
        info['is_icp_brasil'] = self._is_icp_brasil(certificate)
        
        return info
    
    def _is_icp_brasil(self, certificate: x509.Certificate) -> bool:
        """
        Verifica se o certificado é ICP-Brasil
        
        Args:
            certificate: Certificado X.509
            
        Returns:
            bool
        """
        issuer_str = str(certificate.issuer)
        patterns = ['ICP-Brasil', 'AC ', 'Autoridade Certificadora', 'ITI']
        
        for pattern in patterns:
            if pattern.lower() in issuer_str.lower():
                return True
        
        # Verificar políticas
        try:
            for ext in certificate.extensions:
                if ext.oid._name == 'certificatePolicies':
                    if '2.16.76' in str(ext.value):  # OID ICP-Brasil
                        return True
        except:
            pass
        
        return False
    
    def create_signature_field(self, 
                               writer: PdfWriter,
                               page_num: int,
                               field_name: str,
                               rect: Tuple[float, float, float, float]) -> None:
        """
        Cria campo de assinatura no PDF
        
        Args:
            writer: PdfWriter
            page_num: Número da página (0-based)
            field_name: Nome do campo
            rect: Retângulo (x1, y1, x2, y2) em pontos
        """
        # Criar dicionário do campo de assinatura
        field = DictionaryObject()
        
        # Tipo do campo
        field.update({
            NameObject("/Type"): NameObject("/Annot"),
            NameObject("/Subtype"): NameObject("/Widget"),
            NameObject("/FT"): NameObject("/Sig"),
            NameObject("/T"): TextStringObject(field_name),
            NameObject("/Rect"): ArrayObject([NumberObject(x) for x in rect]),
            NameObject("/F"): NumberObject(4),  # Print flag
            NameObject("/P"): writer.pages[page_num].indirect_reference
        })
        
        # Adicionar à página
        if "/Annots" not in writer.pages[page_num]:
            writer.pages[page_num][NameObject("/Annots")] = ArrayObject()
        
        writer.pages[page_num]["/Annots"].append(field)
        
        # Adicionar ao catálogo de formulários
        if "/AcroForm" not in writer._root_object:
            writer._root_object[NameObject("/AcroForm")] = DictionaryObject()
        
        acroform = writer._root_object["/AcroForm"]
        
        if "/Fields" not in acroform:
            acroform[NameObject("/Fields")] = ArrayObject()
        
        acroform["/Fields"].append(field)
        acroform[NameObject("/SigFlags")] = NumberObject(3)
    
    def create_signature_dictionary(self,
                                     private_key: Any,
                                     certificate: x509.Certificate,
                                     password: Optional[str] = None) -> Tuple[bytes, Dict]:
        """
        Cria dicionário PKCS#7 para assinatura
        
        Args:
            private_key: Chave privada
            certificate: Certificado
            password: Senha (opcional)
            
        Returns:
            Tuple (pkcs7_data, signature_dict)
        """
        # Calcular hash do documento (será preenchido depois)
        data_to_sign = b"TO_BE_SIGNED"
        
        # Criar assinatura PKCS#7
        from cryptography.hazmat.primitives.serialization import pkcs7
        
        # Determinar algoritmo de hash (ICP-Brasil usa SHA256)
        hash_algorithm = hashes.SHA256()
        
        # Opções do PKCS#7
        options = [pkcs7.PKCS7Options.DetachedSignature]
        if password:
            options.append(pkcs7.PKCS7Options.NoCerts)
        
        # Construir PKCS#7
        builder = pkcs7.PKCS7SignatureBuilder().set_data(
            data_to_sign
        ).add_signer(
            certificate, private_key, hash_algorithm
        )
        
        # Adicionar certificados da cadeia (opcional)
        # builder.add_certificate(additional_cert)
        
        pkcs7_data = builder.sign(Encoding.DER, options)
        
        # Criar dicionário de assinatura PDF
        sig_dict = DictionaryObject()
        sig_dict.update({
            NameObject("/Type"): self.SIG_TYPE,
            NameObject("/Filter"): self.SIG_FILTER,
            NameObject("/SubFilter"): self.SIG_SUBFILTER,
            NameObject("/Contents"): ByteStringObject(pkcs7_data),
            NameObject("/ByteRange"): ArrayObject([
                NumberObject(0), NumberObject(0), 
                NumberObject(0), NumberObject(0)
            ]),
            NameObject("/M"): TextStringObject(f"D:{datetime.now().strftime('%Y%m%d%H%M%S%z')}")
        })
        
        # Adicionar informações do certificado
        sig_dict[NameObject("/Name")] = TextStringObject(
            certificate.subject.get_attributes_for_oid(x509.NameOID.COMMON_NAME)[0].value
        )
        
        # Adicionar localização e motivo (padrão ICP-Brasil)
        sig_dict[NameObject("/Location")] = TextStringObject("Brasil")
        sig_dict[NameObject("/Reason")] = TextStringObject("Assinatura Digital ICP-Brasil")
        
        # Adicionar informações de contato
        try:
            email = certificate.subject.get_attributes_for_oid(x509.NameOID.EMAIL_ADDRESS)[0].value
            sig_dict[NameObject("/ContactInfo")] = TextStringObject(email)
        except:
            pass
        
        return pkcs7_data, sig_dict
    
    def sign_pdf(self,
                 pdf_path: str,
                 cert_path: str,
                 password: str,
                 output_path: Optional[str] = None,
                 position: Optional[Dict] = None) -> Tuple[bool, str, bytes]:
        """
        Assina um documento PDF
        
        Args:
            pdf_path: Caminho do PDF original
            cert_path: Caminho do certificado
            password: Senha do certificado
            output_path: Caminho de saída (opcional)
            position: Posição da assinatura {x, y, page, width, height}
            
        Returns:
            Tuple (success, message, signed_pdf_bytes)
        """
        try:
            logger.info(f"Iniciando assinatura do PDF: {pdf_path}")
            
            # Validar PDF
            if not os.path.exists(pdf_path):
                return False, f"PDF não encontrado: {pdf_path}", b""
            
            # Carregar certificado
            private_key, certificate, cert_info = self.load_certificate(cert_path, password)
            
            # Verificar ICP-Brasil
            if not cert_info['is_icp_brasil']:
                logger.warning("Certificado não é ICP-Brasil")
            
            # Abrir PDF
            with open(pdf_path, 'rb') as f:
                pdf_reader = PdfReader(f)
                pdf_writer = PdfWriter()
                
                # Copiar todas as páginas
                for page in pdf_reader.pages:
                    pdf_writer.add_page(page)
                
                # Determinar posição da assinatura
                if position:
                    x = position.get('x', 20)
                    y = position.get('y', 50)
                    page = position.get('page', len(pdf_reader.pages)) - 1
                    width = position.get('width', 150)
                    height = position.get('height', 50)
                else:
                    # Posição padrão: canto inferior esquerdo da última página
                    page = len(pdf_reader.pages) - 1
                    x = 20
                    y = 50
                    width = 150
                    height = 50
                
                # Converter mm para pontos (1 mm = 72/25.4 pontos)
                x_pt = x * 72 / 25.4
                y_pt = y * 72 / 25.4
                width_pt = width * 72 / 25.4
                height_pt = height * 72 / 25.4
                
                # Criar campo de assinatura
                field_name = f"Signature_{datetime.now().strftime('%Y%m%d%H%M%S')}"
                rect = (x_pt, y_pt, x_pt + width_pt, y_pt + height_pt)
                self.create_signature_field(pdf_writer, page, field_name, rect)
                
                # Calcular hash do documento
                temp_pdf = io.BytesIO()
                pdf_writer.write(temp_pdf)
                pdf_bytes = temp_pdf.getvalue()
                
                # Calcular hash para assinatura
                doc_hash = hashlib.sha256(pdf_bytes).digest()
                
                # Criar assinatura PKCS#7
                from cryptography.hazmat.primitives import hashes
                from cryptography.hazmat.primitives.asymmetric import padding
                
                # Assinar o hash
                if isinstance(private_key, rsa.RSAPrivateKey):
                    signature = private_key.sign(
                        doc_hash,
                        padding.PKCS1v15(),
                        hashes.SHA256()
                    )
                else:
                    # ECDSA
                    signature = private_key.sign(
                        doc_hash,
                        ec.ECDSA(hashes.SHA256())
                    )
                
                # Criar dicionário de assinatura
                sig_dict = DictionaryObject()
                
                # Formatar data manualmente para evitar backslash em f-string
                data_str = datetime.now().strftime('D:%Y%m%d%H%M%S-03\'00\'')
                
                sig_dict.update({
                    NameObject("/Type"): self.SIG_TYPE,
                    NameObject("/Filter"): self.SIG_FILTER,
                    NameObject("/SubFilter"): self.SIG_SUBFILTER,
                    NameObject("/Contents"): ByteStringObject(signature),
                    NameObject("/ByteRange"): ArrayObject([
                        NumberObject(0), 
                        NumberObject(0), 
                        NumberObject(0), 
                        NumberObject(0)
                    ]),
                    NameObject("/M"): TextStringObject(data_str),
                    NameObject("/Name"): TextStringObject(
                        certificate.subject.get_attributes_for_oid(
                            x509.NameOID.COMMON_NAME
                        )[0].value
                    ),
                    NameObject("/Location"): TextStringObject("Brasil"),
                    NameObject("/Reason"): TextStringObject("Assinatura Digital ICP-Brasil")
                })
                
                # Adicionar ao campo de assinatura
                for annot in pdf_writer.pages[page]["/Annots"]:
                    if annot.get_object()["/T"] == field_name:
                        annot.get_object()[NameObject("/V")] = sig_dict
                        break
                
                # Gerar PDF final
                output = io.BytesIO()
                pdf_writer.write(output)
                signed_pdf = output.getvalue()
                
                # Salvar se caminho de saída fornecido
                if output_path:
                    with open(output_path, 'wb') as f:
                        f.write(signed_pdf)
                    logger.info(f"PDF assinado salvo em: {output_path}")
                
                return True, "Documento assinado com sucesso", signed_pdf
                
        except Exception as e:
            logger.error(f"Erro na assinatura: {e}", exc_info=True)
            return False, f"Erro na assinatura: {str(e)}", b""
    
    def sign_with_position(self,
                           pdf_path: str,
                           cert_path: str,
                           password: str,
                           x_mm: float,
                           y_mm: float,
                           page: int = -1,
                           width_mm: float = 80,
                           height_mm: float = 30) -> Tuple[bool, str, bytes]:
        """
        Assina PDF com posição específica
        
        Args:
            pdf_path: Caminho do PDF
            cert_path: Caminho do certificado
            password: Senha
            x_mm: Posição X em mm
            y_mm: Posição Y em mm
            page: Número da página (1-based, -1 para última)
            width_mm: Largura em mm
            height_mm: Altura em mm
            
        Returns:
            Tuple (success, message, signed_pdf)
        """
        position = {
            'x': x_mm,
            'y': y_mm,
            'page': page if page > 0 else None,
            'width': width_mm,
            'height': height_mm
        }
        
        return self.sign_pdf(pdf_path, cert_path, password, None, position)
    
    def sign_multiple(self,
                      pdf_path: str,
                      signatures: List[Dict]) -> Tuple[bool, str, bytes]:
        """
        Adiciona múltiplas assinaturas ao mesmo PDF
        
        Args:
            pdf_path: Caminho do PDF
            signatures: Lista de dicionários com cert_path, password, position
            
        Returns:
            Tuple (success, message, signed_pdf)
        """
        current_pdf = pdf_path
        temp_files = []
        
        try:
            for i, sig in enumerate(signatures):
                output_temp = os.path.join(self.temp_dir, f"temp_sig_{i}.pdf")
                temp_files.append(output_temp)
                
                success, msg, signed = self.sign_pdf(
                    current_pdf,
                    sig['cert_path'],
                    sig['password'],
                    output_temp if i < len(signatures)-1 else None,
                    sig.get('position')
                )
                
                if not success:
                    return False, f"Erro na assinatura {i+1}: {msg}", b""
                
                current_pdf = output_temp
            
            # Ler PDF final
            with open(current_pdf, 'rb') as f:
                final_pdf = f.read()
            
            return True, f"{len(signatures)} assinaturas adicionadas", final_pdf
            
        finally:
            # Limpar arquivos temporários
            for f in temp_files:
                try:
                    if os.path.exists(f):
                        os.unlink(f)
                except:
                    pass