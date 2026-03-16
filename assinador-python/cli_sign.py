#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Execução CLI para assinatura de PDFs."""

import argparse
import json
import os
import sys

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, BASE_DIR)

from app.services.pdf_signer import PDFSigner


def main() -> int:
    parser = argparse.ArgumentParser(description='Assina um PDF via linha de comando.')
    parser.add_argument('--pdf', required=True, help='Caminho do PDF de entrada')
    parser.add_argument('--cert', required=True, help='Caminho do certificado')
    parser.add_argument('--password', default='', help='Senha do certificado')
    parser.add_argument('--output', required=True, help='Caminho do PDF assinado')
    parser.add_argument('--position', default='{}', help='JSON com x, y, pagina, width, height')
    args = parser.parse_args()

    try:
        position = json.loads(args.position or '{}')
    except json.JSONDecodeError as exc:
        print(json.dumps({'success': False, 'message': f'JSON de posição inválido: {exc}'}))
        return 1

    signer = PDFSigner(temp_dir=os.path.join(BASE_DIR, 'temp'))
    success, message, _ = signer.sign_pdf(
        pdf_path=args.pdf,
        cert_path=args.cert,
        password=args.password,
        output_path=args.output,
        position=position
    )

    result = {
        'success': success,
        'message': message,
        'arquivo': os.path.basename(args.output),
        'output_path': args.output
    }
    print(json.dumps(result, ensure_ascii=False))
    return 0 if success else 1


if __name__ == '__main__':
    raise SystemExit(main())