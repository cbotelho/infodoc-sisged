#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Testes do assinador digital."""

import io
import os
import sys
import tempfile
import unittest

from PyPDF2 import PdfReader, PdfWriter

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from app.services.pdf_signer import PDFSigner
from app.services.signature_validator import SignatureValidator


class TestPDFSigner(unittest.TestCase):
	"""Testes para a classe PDFSigner."""

	@classmethod
	def setUpClass(cls):
		cls.test_dir = tempfile.mkdtemp()
		cls.test_pdf = os.path.join(cls.test_dir, 'test.pdf')
		cls.test_cert = os.path.join(cls.test_dir, 'test.pfx')

		cls._create_test_pdf(cls.test_pdf)
		cls._create_test_certificate(cls.test_cert, password='123456')
		cls.signer = PDFSigner(temp_dir=cls.test_dir)

	@staticmethod
	def _create_test_pdf(path):
		writer = PdfWriter()
		writer.add_blank_page(width=595, height=842)

		with open(path, 'wb') as pdf_file:
			writer.write(pdf_file)

	@staticmethod
	def _create_test_certificate(path, password='123456'):
		import datetime

		from cryptography import x509
		from cryptography.hazmat.primitives import hashes, serialization
		from cryptography.hazmat.primitives.asymmetric import rsa
		from cryptography.hazmat.primitives.serialization import pkcs12
		from cryptography.x509.oid import NameOID

		private_key = rsa.generate_private_key(public_exponent=65537, key_size=2048)

		subject = issuer = x509.Name([
			x509.NameAttribute(NameOID.COMMON_NAME, 'Teste ICP-Brasil'),
			x509.NameAttribute(NameOID.ORGANIZATION_NAME, 'Infodoc Testes'),
			x509.NameAttribute(NameOID.COUNTRY_NAME, 'BR'),
		])

		certificate = x509.CertificateBuilder().subject_name(
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

		pkcs12_data = pkcs12.serialize_key_and_certificates(
			name=b'Test Certificate',
			key=private_key,
			cert=certificate,
			cas=None,
			encryption_algorithm=serialization.BestAvailableEncryption(password.encode())
		)

		with open(path, 'wb') as cert_file:
			cert_file.write(pkcs12_data)

	def test_load_certificate(self):
		private_key, certificate, info = self.signer.load_certificate(self.test_cert, '123456')

		self.assertIsNotNone(private_key)
		self.assertIsNotNone(certificate)
		self.assertEqual(info['format'], 'PKCS12')
		self.assertIn('subject', info)

	def test_sign_pdf(self):
		output_path = os.path.join(self.test_dir, 'signed.pdf')

		success, message, signed_data = self.signer.sign_pdf(
			pdf_path=self.test_pdf,
			cert_path=self.test_cert,
			password='123456',
			output_path=output_path
		)

		self.assertTrue(success, message)
		self.assertTrue(os.path.exists(output_path))
		self.assertGreater(len(signed_data), 0)
		self.assertIn(b'/ByteRange [', signed_data)
		self.assertNotIn(b'/ByteRange [ 0 0 0 0 ]', signed_data)

	def test_sign_with_position_creates_visible_annotation(self):
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

		self.assertTrue(success, message)
		self.assertGreater(len(signed_data), 0)

		signed_reader = PdfReader(io.BytesIO(signed_data))
		page_text = signed_reader.pages[0].extract_text() or ''
		self.assertIn('Assinado Digitalmente por:', page_text)
		self.assertIn('Data:', page_text)
		self.assertIn('INFODOC', page_text.upper())
		self.assertIn('TESTES', page_text.upper())

	def test_invalid_password(self):
		with self.assertRaises(ValueError):
			self.signer.load_certificate(self.test_cert, 'senha_errada')

	def test_nonexistent_pdf(self):
		success, message, signed_data = self.signer.sign_pdf(
			pdf_path='/arquivo/inexistente.pdf',
			cert_path=self.test_cert,
			password='123456'
		)

		self.assertFalse(success)
		self.assertEqual(signed_data, b'')
		self.assertIn('não encontrado', message)


class TestSignatureValidator(unittest.TestCase):
	"""Testes básicos para o validador de assinaturas."""

	@classmethod
	def setUpClass(cls):
		cls.test_dir = tempfile.mkdtemp()
		cls.signer = PDFSigner(temp_dir=cls.test_dir)
		cls.validator = SignatureValidator()

		cls.test_pdf = os.path.join(cls.test_dir, 'test.pdf')
		cls.test_cert = os.path.join(cls.test_dir, 'test.pfx')
		cls.signed_pdf = os.path.join(cls.test_dir, 'signed.pdf')

		TestPDFSigner._create_test_pdf(cls.test_pdf)
		TestPDFSigner._create_test_certificate(cls.test_cert, '123456')
		cls.signer.sign_pdf(
			pdf_path=cls.test_pdf,
			cert_path=cls.test_cert,
			password='123456',
			output_path=cls.signed_pdf
		)

	def test_extract_signatures(self):
		signatures = self.validator.extract_signatures(self.signed_pdf)
		self.assertGreaterEqual(len(signatures), 1)

	def test_validate_signature(self):
		result = self.validator.validate_signature(self.signed_pdf)

		self.assertIn('valid', result)
		self.assertIn('signature', result)
		self.assertIn('message', result)


if __name__ == '__main__':
	unittest.main()
