# -*- coding: utf-8 -*-
"""
pdf_signer.py - Módulo principal de assinatura digital de PDFs
Padrão ICP-Brasil (PKCS#7, SHA256, certificados A1/A3)
"""

import os
import io
import hashlib
import logging
import os
import re
import tempfile
from datetime import datetime
from typing import Optional, Tuple, Dict, Any, Union, List
from pathlib import Path

from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding, rsa, ec
from cryptography.hazmat.primitives.serialization import pkcs12, Encoding
from cryptography import x509
from cryptography.hazmat.backends import default_backend
from cryptography.exceptions import InvalidSignature
from pyhanko.sign import signers
from pyhanko.sign.fields import SigFieldSpec, InvisSigSettings
from pyhanko.sign.signers import PdfSigner, PdfSignatureMetadata
from pyhanko.pdf_utils.incremental_writer import IncrementalPdfFileWriter
from reportlab.lib.colors import Color, black, white
from reportlab.pdfbase.pdfmetrics import stringWidth
from reportlab.pdfgen import canvas as rl_canvas

import PyPDF2
from PyPDF2 import PdfReader, PdfWriter
from PyPDF2.generic import (
    NameObject, TextStringObject, NumberObject, 
    ArrayObject, DictionaryObject, ByteStringObject,
    FloatObject, DecodedStreamObject
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

    def _load_pyhanko_signer(self, cert_path: str, password: Optional[str] = None):
        """Carrega um signer compatível com pyHanko."""
        extension = Path(cert_path).suffix.lower()
        passphrase = password.encode('utf-8') if password else None

        if extension in ('.pfx', '.p12'):
            return signers.SimpleSigner.load_pkcs12(
                cert_path,
                passphrase=passphrase
            )

        if extension == '.pem':
            return signers.SimpleSigner.load(
                key_file=cert_path,
                cert_file=cert_path,
                key_passphrase=passphrase
            )

        raise ValueError(
            'Formato de certificado não suportado para assinatura profissional. '
            'Use um arquivo .pfx, .p12 ou .pem com chave privada.'
        )

    def _get_subject_value(self, cert_info: Dict, *keys: str) -> str:
        subject = cert_info.get('subject', {}) if cert_info else {}
        for key in keys:
            value = subject.get(key)
            if value:
                return str(value)
        return ''

    def _extract_document_identifier(self, cert_info: Dict) -> str:
        for raw_value in (
            self._get_subject_value(cert_info, 'organizationIdentifier', 'serialNumber'),
            self._get_subject_value(cert_info, 'commonName')
        ):
            digits = re.sub(r'\D', '', raw_value or '')
            if len(digits) >= 11:
                return digits
        return ''

    def _extract_certificate_holder_name(self, cert_info: Dict) -> str:
        subject = cert_info.get('subject', {}) if cert_info else {}

        candidates = [
            subject.get('organizationName'),
            subject.get('givenName'),
            subject.get('pseudonym'),
            subject.get('commonName')
        ]

        surname = subject.get('surname')
        if subject.get('givenName') and surname:
            candidates.insert(0, f"{subject.get('givenName')} {surname}")

        disallowed = {
            'ICP-BRASIL',
            'ICP BRASIL',
            'ICP-BRASIL A1',
            'ICP-BRASIL A3'
        }

        for value in candidates:
            if not value:
                continue
            cleaned = re.sub(r'\s+', ' ', str(value)).strip()
            if not cleaned:
                continue
            if cleaned.upper() in disallowed:
                continue
            return cleaned

        return 'TITULAR DO CERTIFICADO'

    def _fit_font_size(self,
                       text: str,
                       font_name: str,
                       max_width: float,
                       preferred: float,
                       minimum: float) -> float:
        size = preferred
        while size > minimum and stringWidth(text, font_name, size) > max_width:
            size -= 0.5
        return max(size, minimum)

    def _wrap_text(self, text: str, font_name: str, font_size: float, max_width: float) -> List[str]:
        words = (text or '').split()
        if not words:
            return []

        lines = []
        current = words[0]
        for word in words[1:]:
            candidate = f'{current} {word}'
            if stringWidth(candidate, font_name, font_size) <= max_width:
                current = candidate
            else:
                lines.append(current)
                current = word
        lines.append(current)
        return lines

    def _draw_wrapped_lines(self,
                            drawing: rl_canvas.Canvas,
                            lines: List[str],
                            x: float,
                            y_top: float,
                            font_name: str,
                            font_size: float,
                            leading: float) -> float:
        y = y_top
        drawing.setFont(font_name, font_size)
        for line in lines:
            drawing.drawString(x, y, line)
            y -= leading
        return y

    def _build_visual_stamp_overlay(self,
                                    page_width: float,
                                    page_height: float,
                                    rect: Tuple[float, float, float, float],
                                    cert_info: Dict) -> bytes:
        """Gera um overlay PDF com o carimbo visual institucional."""
        buffer = io.BytesIO()
        drawing = rl_canvas.Canvas(buffer, pagesize=(page_width, page_height))

        x1, y1, x2, y2 = rect
        width = x2 - x1
        height = y2 - y1

        holder_name = self._extract_certificate_holder_name(cert_info).upper()
        identifier = self._extract_document_identifier(cert_info)
        timestamp = datetime.now().astimezone()
        time_line = timestamp.strftime("%H:%M:%S %z")
        if len(time_line) >= 5:
            time_line = f"{time_line[:-2]}'{time_line[-2:]}'"
        date_time_line = f"Data: {timestamp.strftime('%Y.%m.%d')} {time_line}"

        drawing.saveState()
        drawing.setFillColor(white)
        drawing.rect(x1, y1, width, height, fill=1, stroke=0)
        drawing.setFillColor(black)

        padding_x = max(8, width * 0.03)
        padding_y = max(8, height * 0.08)
        left_x = x1 + padding_x
        separator_x = x1 + width * 0.445 + 2
        left_width = max(60, separator_x - left_x - 8)
        right_x = separator_x + 18
        right_width = max(80, x2 - right_x - padding_x)
        top_y = y2 - padding_y

        left_title_font = max(10, min(17, height * 0.15))
        left_title_lines = self._wrap_text(
            holder_name,
            'Helvetica',
            left_title_font,
            left_width
        )

        while len(left_title_lines) > 4 and left_title_font > 8:
            left_title_font -= 0.5
            left_title_lines = self._wrap_text(
                holder_name,
                'Helvetica',
                left_title_font,
                left_width
            )

        left_title_y = top_y - left_title_font
        left_cursor_y = self._draw_wrapped_lines(
            drawing,
            left_title_lines,
            left_x,
            left_title_y,
            'Helvetica',
            left_title_font,
            left_title_font * 1.1
        )

        if identifier:
            id_font = self._fit_font_size(
                identifier,
                'Helvetica',
                left_width,
                preferred=max(11, left_title_font * 0.92),
                minimum=9
            )
            drawing.setFont('Helvetica', id_font)
            drawing.drawString(left_x, left_cursor_y - id_font * 0.35, identifier)

        label_font = max(9, min(13, height * 0.12))
        value_font = max(9, min(13, height * 0.12))
        line_gap = max(10, height * 0.18)

        drawing.setFont('Helvetica', label_font)
        drawing.drawString(right_x, top_y - label_font, 'Assinado Digitalmente por:')

        right_name_lines = self._wrap_text(holder_name, 'Helvetica', value_font, right_width)
        right_name_y = top_y - label_font - line_gap
        right_name_y = self._draw_wrapped_lines(
            drawing,
            right_name_lines,
            right_x,
            right_name_y,
            'Helvetica',
            value_font,
            value_font * 1.08
        )

        if identifier:
            drawing.setFont('Helvetica', label_font)
            drawing.drawString(right_x, right_name_y - value_font * 0.35, 'CPF/CNPJ:')
            drawing.setFont('Helvetica', value_font)
            drawing.drawString(right_x, right_name_y - line_gap, identifier)
            date_y = right_name_y - line_gap * 1.9
        else:
            date_y = right_name_y - line_gap * 0.8

        drawing.setFont('Helvetica', label_font)
        drawing.drawString(right_x, date_y, date_time_line)

        # Traço central rosado simulando rubrica, como na referência.
        stroke = Color(0.96, 0.72, 0.74, alpha=0.9)
        drawing.setStrokeColor(stroke)
        drawing.setLineWidth(max(2.5, width * 0.012))
        center_x = separator_x
        drawing.bezier(
            center_x, y1 + height * 0.18,
            center_x - width * 0.03, y1 + height * 0.45,
            center_x - width * 0.01, y1 + height * 0.72,
            center_x + width * 0.01, y1 + height * 0.90
        )
        drawing.bezier(
            center_x + width * 0.01, y1 + height * 0.90,
            center_x + width * 0.04, y1 + height * 0.65,
            center_x + width * 0.00, y1 + height * 0.42,
            center_x - width * 0.04, y1 + height * 0.12
        )

        drawing.restoreState()
        drawing.showPage()
        drawing.save()
        return buffer.getvalue()

    def _apply_visual_stamp(self,
                            pdf_path: str,
                            page_index: int,
                            rect: Tuple[float, float, float, float],
                            cert_info: Dict) -> str:
        """Aplica o carimbo visual ao PDF antes da assinatura criptográfica."""
        with open(pdf_path, 'rb') as source_stream:
            source_reader = PdfReader(source_stream)
            page = source_reader.pages[page_index]
            page_width = float(page.mediabox.width)
            page_height = float(page.mediabox.height)
            overlay_bytes = self._build_visual_stamp_overlay(page_width, page_height, rect, cert_info)
            overlay_reader = PdfReader(io.BytesIO(overlay_bytes))

            writer = PdfWriter()
            for index, current_page in enumerate(source_reader.pages):
                if index == page_index:
                    current_page.merge_page(overlay_reader.pages[0])
                writer.add_page(current_page)

            temp_file = tempfile.NamedTemporaryFile(delete=False, suffix='.pdf', dir=self.temp_dir)
            temp_file.close()
            with open(temp_file.name, 'wb') as stamped_stream:
                writer.write(stamped_stream)

        return temp_file.name

    def _escape_pdf_text(self, text: str) -> str:
        """Escapa texto para uso em streams PDF."""
        return str(text).replace('\\', '\\\\').replace('(', '\\(').replace(')', '\\)')

    def _create_signature_appearance(self,
                                     writer: PdfWriter,
                                     width_pt: float,
                                     height_pt: float,
                                     certificate: x509.Certificate,
                                     cert_info: Dict) -> Any:
        """Cria uma aparência visível para o campo de assinatura."""
        subject = cert_info.get('subject', {}) if cert_info else {}
        signer_name = subject.get('commonName')

        if not signer_name:
            try:
                signer_name = certificate.subject.get_attributes_for_oid(
                    x509.NameOID.COMMON_NAME
                )[0].value
            except Exception:
                signer_name = 'Assinante não identificado'

        signed_at = datetime.now().strftime('%d/%m/%Y %H:%M:%S')
        lines = [
            'Assinado digitalmente por',
            signer_name[:72],
            f'Data: {signed_at}'
        ]

        font_size = 8 if height_pt < 90 else 9
        leading = font_size + 3
        start_y = max(leading + 4, height_pt - 14)

        text_ops = [
            'BT',
            f'/F1 {font_size} Tf',
            '0.15 0.15 0.15 rg',
            f'{leading} TL',
            f'10 {start_y:.2f} Td'
        ]

        for index, line in enumerate(lines):
            if index > 0:
                text_ops.append('T*')
            text_ops.append(f'({self._escape_pdf_text(line)}) Tj')

        text_ops.append('ET')

        content = '\n'.join([
            'q',
            '0.95 0.97 1 rg',
            f'0 0 {width_pt:.2f} {height_pt:.2f} re',
            'f',
            '0.72 0.13 0.13 RG',
            '1 w',
            f'0.5 0.5 {max(width_pt - 1, 1):.2f} {max(height_pt - 1, 1):.2f} re',
            'S',
            *text_ops,
            'Q'
        ])

        font = DictionaryObject({
            NameObject('/Type'): NameObject('/Font'),
            NameObject('/Subtype'): NameObject('/Type1'),
            NameObject('/BaseFont'): NameObject('/Helvetica'),
            NameObject('/Encoding'): NameObject('/WinAnsiEncoding')
        })
        font_ref = writer._add_object(font)

        stream = DecodedStreamObject()
        stream.set_data(content.encode('latin-1', errors='replace'))
        stream.update({
            NameObject('/Type'): NameObject('/XObject'),
            NameObject('/Subtype'): NameObject('/Form'),
            NameObject('/BBox'): ArrayObject([
                FloatObject(0),
                FloatObject(0),
                FloatObject(width_pt),
                FloatObject(height_pt)
            ]),
            NameObject('/Resources'): DictionaryObject({
                NameObject('/Font'): DictionaryObject({
                    NameObject('/F1'): font_ref
                })
            })
        })

        return writer._add_object(stream)
    
    def create_signature_field(self, 
                               writer: PdfWriter,
                               page_num: int,
                               field_name: str,
                               rect: Tuple[float, float, float, float],
                               certificate: x509.Certificate,
                               cert_info: Dict) -> Any:
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
        width_pt = rect[2] - rect[0]
        height_pt = rect[3] - rect[1]
        appearance_ref = self._create_signature_appearance(
            writer,
            width_pt,
            height_pt,
            certificate,
            cert_info
        )

        field.update({
            NameObject("/Type"): NameObject("/Annot"),
            NameObject("/Subtype"): NameObject("/Widget"),
            NameObject("/FT"): NameObject("/Sig"),
            NameObject("/T"): TextStringObject(field_name),
            NameObject("/Rect"): ArrayObject([FloatObject(x) for x in rect]),
            NameObject("/F"): NumberObject(4),  # Print flag
            NameObject("/P"): writer.pages[page_num].indirect_reference,
            NameObject('/AP'): DictionaryObject({
                NameObject('/N'): appearance_ref
            }),
            NameObject('/DA'): TextStringObject('/F1 9 Tf 0 g'),
            NameObject('/MK'): DictionaryObject({
                NameObject('/BC'): ArrayObject([
                    FloatObject(0.72), FloatObject(0.13), FloatObject(0.13)
                ]),
                NameObject('/BG'): ArrayObject([
                    FloatObject(0.95), FloatObject(0.97), FloatObject(1)
                ])
            })
        })

        field_ref = writer._add_object(field)
        
        # Adicionar à página
        if "/Annots" not in writer.pages[page_num]:
            writer.pages[page_num][NameObject("/Annots")] = ArrayObject()
        
        writer.pages[page_num]["/Annots"].append(field_ref)
        
        # Adicionar ao catálogo de formulários
        if "/AcroForm" not in writer._root_object:
            writer._root_object[NameObject("/AcroForm")] = DictionaryObject()
        
        acroform = writer._root_object["/AcroForm"]
        if hasattr(acroform, 'get_object'):
            acroform = acroform.get_object()
        
        if "/Fields" not in acroform:
            acroform[NameObject("/Fields")] = ArrayObject()
        
        acroform["/Fields"].append(field_ref)
        acroform[NameObject("/SigFlags")] = NumberObject(3)
        
        return field_ref
    
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
            _, certificate, cert_info = self.load_certificate(cert_path, password)
            pdf_signer_backend = self._load_pyhanko_signer(cert_path, password)
            
            # Verificar ICP-Brasil
            if not cert_info['is_icp_brasil']:
                logger.warning("Certificado não é ICP-Brasil")
            
            with open(pdf_path, 'rb') as pdf_file:
                reader = PdfReader(pdf_file)
                total_pages = len(reader.pages)

            if position:
                x = float(position.get('x', 20))
                y = float(position.get('y', 50))
                requested_page = position.get('page', position.get('pagina'))
                width = float(position.get('width', position.get('largura', 150)))
                height = float(position.get('height', position.get('altura', 50)))
                if requested_page in (None, '', 0):
                    page = total_pages - 1
                else:
                    page = max(0, min(total_pages - 1, int(requested_page) - 1))
            else:
                page = total_pages - 1
                x = 20
                y = 50
                width = 150
                height = 50

            x_pt = int(round(x * 72 / 25.4))
            y_pt = int(round(y * 72 / 25.4))
            width_pt = int(round(width * 72 / 25.4))
            height_pt = int(round(height * 72 / 25.4))
            field_name = f"Signature_{datetime.now().strftime('%Y%m%d%H%M%S')}"

            signer_name = None
            try:
                signer_name = certificate.subject.get_attributes_for_oid(
                    x509.NameOID.COMMON_NAME
                )[0].value
            except Exception:
                signer_name = cert_info.get('subject', {}).get('commonName', 'Assinante')

            contact_info = None
            try:
                contact_info = certificate.subject.get_attributes_for_oid(
                    x509.NameOID.EMAIL_ADDRESS
                )[0].value
            except Exception:
                contact_info = None

            signature_meta = PdfSignatureMetadata(
                field_name=field_name,
                md_algorithm='sha256',
                location='Brasil',
                reason='Assinatura Digital ICP-Brasil',
                contact_info=contact_info,
                name=signer_name
            )

            visual_rect = (x_pt, y_pt, x_pt + width_pt, y_pt + height_pt)
            stamped_pdf_path = self._apply_visual_stamp(pdf_path, page, visual_rect, cert_info)

            try:
                new_field_spec = SigFieldSpec(
                    sig_field_name=field_name,
                    on_page=page,
                    box=(0, 0, 0, 0),
                    invis_sig_settings=InvisSigSettings(
                        set_print_flag=False,
                        set_hidden_flag=True,
                        box_out_of_bounds=False
                    )
                )

                with open(stamped_pdf_path, 'rb') as input_stream:
                    writer = IncrementalPdfFileWriter(input_stream)
                    output = io.BytesIO()
                    pdf_signer = PdfSigner(
                        signature_meta,
                        signer=pdf_signer_backend,
                        new_field_spec=new_field_spec
                    )
                    pdf_signer.sign_pdf(writer, output=output)
                    signed_pdf = output.getvalue()
            finally:
                try:
                    os.unlink(stamped_pdf_path)
                except OSError:
                    pass

            if b'/ByteRange [ 0 0 0 0 ]' in signed_pdf:
                raise ValueError('Assinatura gerada com ByteRange inválido')
                
                # Salvar se caminho de saída fornecido
            if output_path:
                with open(output_path, 'wb') as f:
                    f.write(signed_pdf)
                logger.info(f"PDF assinado salvo em: {output_path}")

            return True, "Documento assinado digitalmente com sucesso", signed_pdf
                
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