# -*- coding: utf-8 -*-
"""Endpoints para upload direto ao R2/S3 via Presigned URL."""

import os

from flask import Blueprint, jsonify, request

from app.config import Config
from app.services.object_storage import ObjectStorage, ObjectStorageError

storage = ObjectStorage(Config)
storage.ensure_local_dirs()

bp = Blueprint('direct_upload', __name__, url_prefix='/api/uploads')

ALLOWED_SOURCES = {'ged', 'sefaz_rh', 'standalone', 'generic'}


def json_error(message, status_code=400, **extra):
    payload = {'success': False, 'error': message}
    payload.update(extra)
    return jsonify(payload), status_code


def get_json_payload():
    data = request.get_json(silent=True)

    if not isinstance(data, dict):
        raise ValueError('Payload JSON invalido.')

    return data


def normalize_expiry(value):
    if value in (None, ''):
        return Config.PRESIGNED_UPLOAD_EXPIRY

    expiry = int(value)

    if expiry <= 0:
        raise ValueError('expires_in deve ser maior que zero.')

    return min(expiry, 3600)


def normalize_source(value):
    source = str(value or 'generic').strip().lower()

    if source not in ALLOWED_SOURCES:
        raise ValueError('Origem de upload invalida.')

    return source


def normalize_files(payload, require_client_filename=True):
    files = payload.get('files')

    if not isinstance(files, list) or not files:
        raise ValueError('Informe a lista de arquivos em files.')

    normalized = []

    for index, item in enumerate(files, start=1):
        if not isinstance(item, dict):
            raise ValueError(f'Arquivo {index} invalido.')

        raw_original_name = item.get('filename') or item.get('original_name') or item.get('stored_name') or ''
        original_name = os.path.basename(str(raw_original_name).strip())

        if require_client_filename and original_name == '':
            raise ValueError(f'Arquivo {index} sem filename valido.')

        content_type = str(item.get('content_type') or 'application/octet-stream').strip() or 'application/octet-stream'
        size = item.get('size')
        stored_name = os.path.basename(str(item.get('stored_name') or '').strip())

        if size in (None, ''):
            normalized_size = None
        else:
            normalized_size = int(size)
            if normalized_size < 0:
                raise ValueError(f'Arquivo {original_name} com size invalido.')

        normalized.append({
            'original_name': original_name,
            'stored_name': stored_name,
            'content_type': content_type,
            'size': normalized_size,
        })

    return normalized


@bp.route('/presign', methods=['POST'])
def presign_uploads():
    try:
        if not storage.enabled:
            return json_error('Storage R2/S3 nao configurado para upload direto.', 503)

        payload = get_json_payload()
        source = normalize_source(payload.get('source'))
        expires_in = normalize_expiry(payload.get('expires_in'))
        files = normalize_files(payload, require_client_filename=True)

        uploads = []
        for file_info in files:
            stored_name = storage.build_upload_name(file_info['original_name'], source)
            signed_upload = storage.generate_presigned_upload(
                stored_name,
                file_info['content_type'],
                expires_in,
                source,
            )

            uploads.append({
                'original_name': file_info['original_name'],
                'stored_name': stored_name,
                'object_key': signed_upload['object_key'],
                'upload_url': signed_upload['url'],
                'method': signed_upload['method'],
                'headers': signed_upload['headers'],
                'expires_in': signed_upload['expires_in'],
                'size': file_info['size'],
                'content_type': file_info['content_type'],
            })

        return jsonify({
            'success': True,
            'source': source,
            'storage_mode': 'r2',
            'uploads': uploads,
        })
    except ValueError as exc:
        return json_error(str(exc), 400)
    except ObjectStorageError as exc:
        return json_error(str(exc), 500)
    except Exception as exc:
        return json_error(str(exc), 500)


@bp.route('/complete', methods=['POST'])
def complete_uploads(): 
    try:
        payload = get_json_payload()
        source = normalize_source(payload.get('source'))
        files = normalize_files(payload, require_client_filename=False)

        confirmed = []
        missing = []

        for file_info in files:
            stored_name = os.path.basename(str(file_info.get('stored_name') or file_info.get('original_name') or '').strip())

            if stored_name == '':
                raise ValueError('Cada arquivo precisa informar stored_name ou original_name para confirmacao.')

            try:
                object_info = storage.get_object_info(stored_name)
            except FileNotFoundError:
                missing.append({
                    'stored_name': stored_name,
                    'original_name': file_info['original_name'],
                })
                continue

            confirmed.append({
                'original_name': file_info['original_name'],
                'stored_name': stored_name,
                'object_key': object_info['object_key'],
                'content_type': object_info['content_type'],
                'content_length': object_info['content_length'],
                'etag': object_info['etag'],
                'last_modified': object_info['last_modified'],
                'storage_mode': object_info['storage_mode'],
            })

        if missing:
            return json_error(
                'Nem todos os arquivos foram encontrados no storage.',
                409,
                source=source,
                confirmed=confirmed,
                missing=missing,
            )

        return jsonify({
            'success': True,
            'source': source,
            'confirmed': confirmed,
        })
    except ValueError as exc:
        return json_error(str(exc), 400)
    except ObjectStorageError as exc:
        return json_error(str(exc), 500)
    except Exception as exc:
        return json_error(str(exc), 500)