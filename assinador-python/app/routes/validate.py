#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
test_signer.py - Testes do assinador digital
"""

import os
import sys
import unittest
import tempfile
from datetime import datetime

# Adicionar diretório pai ao path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from app.services.pdf_signer import PDFSigner
from app.services.signature_validator import SignatureValidator

class TestPDFSigner(unittest.TestCase):
    """Testes para a classe PDFSigner"""
    
    @classmethod
    def setUpClass(cls):
        """Configuração inicial"""
        # Criar diretório temporário para testes
        cls.test_dir = tempfile.mkdtemp()
        
        # Criar um PDF simples para teste
        cls.test_pdf = os.path.join(cls.test_dir, 'test.pdf')
        cls._create_test_pdf(cls.test_pdf)
        
        # Criar certificado de teste (autoassinado)
        cls.test_cert = os.path.join(cls.test_dir, 'test.pfx')
        cls._create_test_certificate(cls.test_cert, password='123456')
        
        # Inicializar assinador
        cls.signer = PDFSigner(temp_dir=cls.test_dir)
    
    @staticmethod
    def _create_test_pdf(path):
        """Cria um PDF simples para teste"""
        from reportlab.pdfgen import canvas
        from reportlab.lib.pagesizes import A4
        
        c = canvas.Canvas(path, pagesize=A4)
        c.drawString(100, 750, "Documento de Teste para Assinatura")
        c.drawString(100, 700, f"Gerado em: {datetime.now()}")
        c.save()
    
    @staticmethod
    def _create_test_certificate(path, password='123456'):
        """Cria certificado autoassinado para teste"""
        from cryptography import x509
        from cryptography.hazmat.primitives import hashes, serialization
        from cryptography.hazmat.primitives.asymmetric import rsa
        from cryptography.x509.oid import NameOID
        import datetime
        
        # Gerar chave privada
        private_key = rsa.generate_private_key(
            public_exponent=65537,
            key_size=2048
        )
        
        # Criar certificado
        subject = issuer = x509.Name([
            x509.NameAttribute(NameOID.COMMON_NAME, u"Teste ICP-Brasil"),
            x509.NameAttribute(NameOID.ORGANIZATION_NAME, u"Infodoc Testes"),
            x509.NameAttribute(NameOID.COUNTRY_NAME, u"BR"),
        ])
        
        cert = x509.CertificateBuilder().subject_name(
            subject
        ).issuer_name(
            issuer
        ).public_key(
            private_key.public_key()
        ).serial_number(
            x509.random_serial_number()
        ).not_valid_before(
            datetime.datetime.utcnow()
        ).not_valid_after(
            datetime.datetime.utcnow() + datetime.timedelta(days=365)
        ).add_extension(
            x509.BasicConstraints(ca=True, path_length=None), critical=True
        ).sign(private_key, hashes.SHA256())
        
        # Exportar PKCS#12
        from cryptography.hazmat.primitives.serialization import pkcs12
        
        pkcs12_data = pkcs12.serialize_key_and_certificates(
            name=b"Test Certificate",
            key=private_key,
            cert=cert,
            cas=None,
            encryption_algorithm=serialization.BestAvailableEncryption(password.encode())
        )
        
        with open(path, 'wb') as f:
            f.write(pkcs12_data)
    
    def test_load_certificate(self):
        """Testa carregamento de certificado"""
        private_key, certificate, info = self.signer.load_certificate(
            self.test_cert, '123456'
        )
        
        self.assertIsNotNone(private_key)
        self.assertIsNotNone(certificate)
        self.assertIsNotNone(info)
        self.assertEqual(info['format'], 'PKCS12')
        self.assertIn('subject', info)
    
    def test_sign_pdf(self):
        """Testa assinatura de PDF"""
        output_path = os.path.join(self.test_dir, 'signed.pdf')
        
        success, message, signed_data = self.signer.sign_pdf(
            pdf_path=self.test_pdf,
            cert_path=self.test_cert,
            password='123456',
            output_path=output_path
        )
        
        self.assertTrue(success)
        self.assertTrue(os.path.exists(output_path))
        self.assertGreater(len(signed_data), 0)
    
    def test_sign_with_position(self):
        """Testa assinatura com posição específica"""
        success, message, signed_data = self.signer.sign_with_position(
            pdf_path=self.test_pdf,
            cert_path=self.test_cert,
            password='123456',
            x_mm=50,
            y_mm=100,
            page=1,
            width_mm=100,
            height_mm=40
        )
        
        self.assertTrue(success)
        self.assertGreater(len(signed_data), 0)
    
    def test_invalid_password(self):
        """Testa senha incorreta"""
        with self.assertRaises(ValueError):
            self.signer.load_certificate(self.test_cert, 'senha_errada')
    
    def test_nonexistent_pdf(self):
        """Testa PDF inexistente"""
        success, message, signed_data = self.signer.sign_pdf(
            pdf_path='/arquivo/inexistente.pdf',
            cert_path=self.test_cert,
            password='123456'
        )
        
        self.assertFalse(success)
        self.assertIn('não encontrado', message)

class TestSignatureValidator(unittest.TestCase):
    """Testes para o validador de assinaturas"""
    
    @classmethod
    def setUpClass(cls):
        """Configuração inicial"""
        cls.test_dir = tempfile.mkdtemp()
        cls.signer = PDFSigner(temp_dir=cls.test_dir)
        cls.validator = SignatureValidator()
        
        # Criar PDF de teste assinado
        cls.test_pdf = os.path.join(cls.test_dir, 'test.pdf')
        cls._create_test_pdf(cls.test_pdf)
        
        # Criar certificado
        cls.test_cert = os.path.join(cls.test_dir, 'test.pfx')
        TestPDFSigner._create_test_certificate(cls.test_cert, '123456')
        
        # Assinar PDF
        cls.signed_pdf = os.path.join(cls.test_dir, 'signed.pdf')
        cls.signer.sign_pdf(
            pdf_path=cls.test_pdf,
            cert_path=cls.test_cert,
            password='123456',
            output_path=cls.signed_pdf
        )
    
    @staticmethod
    def _create_test_pdf(path):
        """Cria PDF de teste"""
        from reportlab.pdfgen import canvas
        c = canvas.Canvas(path)
        c.drawString(100, 500, "Documento para teste de validação")
        c.save()
    
    def test_extract_signatures(self):
        """Testa extração de assinaturas"""
        signatures = self.validator.extract_signatures(self.signed_pdf)
        self.assertGreaterEqual(len(signatures), 0)
    
    def test_validate_signature(self):
        """Testa validação de assinatura"""
        result = self.validator.validate_signature(self.signed_pdf)
        
        self.assertIn('valid', result)
        self.assertIn('signature', result)
        self.assertIn('certificate', result)
    
    def test_generate_report(self):
        """Testa geração de relatório"""
        report = self.validator.generate_validation_report(self.signed_pdf)
        
        self.assertIn('documento', report)
        self.assertIn('total_assinaturas', report)
        self.assertIn('detalhes', report)

if __name__ == '__main__':
    unittest.main()