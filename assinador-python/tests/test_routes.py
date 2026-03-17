#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Testes básicos das rotas HTTP do assinador."""

import os
import sys
import unittest
import subprocess

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from app.main import app


class TestRoutes(unittest.TestCase):
    """Valida as rotas públicas principais."""

    @classmethod
    def setUpClass(cls):
        cls.client = app.test_client()

    def test_root_redirects_to_standalone(self):
        response = self.client.get('/')

        self.assertEqual(response.status_code, 302)
        self.assertEqual(response.headers.get('Location'), '/standalone/')

    def test_info_reports_optional_signer_params(self):
        response = self.client.get('/info')

        self.assertEqual(response.status_code, 200)
        payload = response.get_json()
        self.assertEqual(payload['service'], 'assinador-python')
        self.assertEqual(payload['routes']['assinador'], '/assinador[?token=<token>&doc=<caminho>]')

    def test_assinador_without_params_redirects_to_standalone(self):
        response = self.client.get('/assinador')

        self.assertEqual(response.status_code, 302)
        self.assertEqual(response.headers.get('Location'), '/standalone/')

    def test_assinador_with_partial_params_returns_bad_request(self):
        response = self.client.get('/assinador?token=abc')
        body = response.get_data(as_text=True)

        self.assertEqual(response.status_code, 400)
        self.assertIn('Informe token e documento', body)

    def test_php_public_base_url_points_to_public_domain(self):
        php_code = (
            "require 'functions_python.php'; "
            "echo getPythonServiceBaseUrl(true);"
        )

        result = subprocess.run(
            ['php', '-r', php_code],
            cwd=os.path.abspath(os.path.join(os.path.dirname(__file__), '..')),
            capture_output=True,
            text=True,
            check=True,
        )

        self.assertEqual(result.stdout.strip(), 'https://assinador.infodocsisged.com.br')


if __name__ == '__main__':
    unittest.main()