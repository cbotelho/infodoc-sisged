# -*- coding: utf-8 -*-
"""Endpoint de upload GED usando Presigned URL + finalizacao no backend."""

import json
import logging
import os
import time

from flask import Blueprint, Response, jsonify, request
from PyPDF2 import PdfReader

from app.config import Config
from app.services.database import get_db
from app.services.object_storage import ObjectStorage, ObjectStorageError

storage = ObjectStorage(Config)
storage.ensure_local_dirs()
logger = logging.getLogger(__name__)

bp = Blueprint('ged', __name__, url_prefix='')


def text_response(message, status=200):
    return Response(message, status=status, mimetype='text/plain; charset=utf-8')


def require_form_fields(field_names):
    values = {}
    missing = []

    for field_name in field_names:
        value = request.form.get(field_name)

        if value is None or str(value).strip() == '':
            missing.append(field_name)
            continue

        values[field_name] = str(value).strip()

    if missing:
        raise ValueError('Campos obrigatorios ausentes: ' + ', '.join(missing))

    return values


def get_uploaded_files():
    uploads = request.files.getlist('files[]')

    if not uploads:
        uploads = request.files.getlist('files')

    return [upload for upload in uploads if upload and str(upload.filename or '').strip() != '']


def require_json_payload():
    payload = request.get_json(silent=True)

    if not isinstance(payload, dict):
        raise ValueError('Payload JSON invalido.')

    return payload


def require_json_fields(payload, field_names):
    values = {}
    missing = []

    for field_name in field_names:
        value = payload.get(field_name)

        if value is None or str(value).strip() == '':
            missing.append(field_name)
            continue

        values[field_name] = str(value).strip()

    if missing:
        raise ValueError('Campos obrigatorios ausentes: ' + ', '.join(missing))

    return values


def get_registro_by_id(connection, registro_id):
    query = (
        'SELECT id, field_433, field_434, field_436, field_437 '
        'FROM app_entity_41 WHERE id = %s LIMIT 1'
    )

    with connection.cursor() as cursor:
        cursor.execute(query, (registro_id,))
        return cursor.fetchone()


def resolve_registro_id_by_numero(connection, numero, secretaria=None, setor=None, tipo=None):
    conditions = ['field_437 = %s']
    params = [str(numero or '').strip()]

    if secretaria:
        conditions.append('field_433 = %s')
        params.append(str(secretaria).strip())

    if setor:
        conditions.append('field_434 = %s')
        params.append(str(setor).strip())

    if tipo:
        conditions.append('field_436 = %s')
        params.append(str(tipo).strip())

    query = 'SELECT id FROM app_entity_41 WHERE ' + ' AND '.join(conditions) + ' ORDER BY id DESC LIMIT 2'

    with connection.cursor() as cursor:
        cursor.execute(query, params)
        registros = cursor.fetchall()

    if len(registros) == 0:
        raise ValueError('Nenhum registro pai foi localizado para o numero informado com os filtros atuais.')

    if len(registros) > 1:
        raise ValueError('Mais de um registro pai foi localizado para o numero informado. Selecione o item desejado no autocomplete.')

    return int(registros[0]['id'])


def validate_selected_registro(connection, registro_id, numero, secretaria=None, setor=None, tipo=None):
    registro = get_registro_by_id(connection, registro_id)

    if not registro:
        raise ValueError('O registro selecionado para a Caixa/Pasta e invalido ou nao existe.')

    if str(registro.get('field_437') or '').strip() != str(numero or '').strip():
        raise ValueError('O numero informado nao corresponde ao registro selecionado.')

    if secretaria and str(registro.get('field_433') or '').strip() != str(secretaria).strip():
        raise ValueError('A secretaria informada nao corresponde ao registro selecionado.')

    if setor and str(registro.get('field_434') or '').strip() != str(setor).strip():
        raise ValueError('O setor informado nao corresponde ao registro selecionado.')

    if tipo and str(registro.get('field_436') or '').strip() != str(tipo).strip():
        raise ValueError('O tipo informado nao corresponde ao registro selecionado.')

    return registro


def validate_file_name_pattern(parts_count, padrao_renomeio):
    if parts_count <= 0 or parts_count > 4:
        return False

    if padrao_renomeio == 1:
        return parts_count >= 1
    if padrao_renomeio == 2:
        return parts_count >= 2
    if padrao_renomeio == 3:
        return parts_count >= 3
    if padrao_renomeio == 4:
        return parts_count >= 4

    return False


def resolve_document_fields(entry, padrao_renomeio):
    field446 = entry.get('coluna1')
    field447 = entry.get('coluna2') if padrao_renomeio >= 2 else None
    field448 = entry.get('coluna3') if padrao_renomeio >= 3 else None
    field458 = entry.get('coluna4') if padrao_renomeio == 4 else None

    return field446, field447, field448, field458


def count_pdf_pages_safe_from_storage(stored_name):
    try:
        stream = storage.open_stream(stored_name)
        reader = PdfReader(stream)
        return len(reader.pages)
    except Exception:
        return 0


def count_pdf_pages_safe(upload):
    try:
        upload.stream.seek(0)
        reader = PdfReader(upload.stream)
        return len(reader.pages)
    except Exception as exc:
        logger.warning('GED: falha ao contar paginas do PDF %s: %s', upload.filename, exc)
        return 0
    finally:
        upload.stream.seek(0)


def build_metadata(original_name, content_length, content_type):
    return {
        'nome_original': original_name,
        'tamanho_bytes': int(content_length or 0),
        'mime_type': content_type or 'application/octet-stream',
        'extensao': str(os.path.splitext(original_name)[1] or '').lower().lstrip('.'),
        'data_upload': time.strftime('%Y-%m-%d %H:%M:%S'),
    }


def prepare_entries_from_direct_upload(files, padrao_renomeio, tratado_por_id):
    valid_entries = []
    invalid_entries = []

    for item in files:
        original_name = os.path.basename(str(item.get('original_name') or '').strip())
        stored_name = os.path.basename(str(item.get('stored_name') or '').strip())

        if original_name == '' or stored_name == '':
            invalid_entries.append(original_name or stored_name or 'arquivo_sem_nome')
            continue

        parts = os.path.splitext(original_name)[0].split('#') if os.path.splitext(original_name)[0] else []

        if not validate_file_name_pattern(len(parts), padrao_renomeio):
            invalid_entries.append(original_name)
            continue

        content_length = item.get('content_length') or item.get('size') or 0
        content_type = str(item.get('content_type') or 'application/octet-stream').strip() or 'application/octet-stream'
        total_paginas = count_pdf_pages_safe_from_storage(stored_name) if original_name.lower().endswith('.pdf') else 0

        valid_entries.append({
            'nome': original_name,
            'stored_name': stored_name,
            'coluna1': parts[0] if len(parts) > 0 else None,
            'coluna2': parts[1] if len(parts) > 1 else None,
            'coluna3': parts[2] if len(parts) > 2 else None,
            'coluna4': parts[3] if len(parts) > 3 else None,
            'coluna5': tratado_por_id,
            'content_type': content_type,
            'content_length': int(content_length or 0),
            'total_paginas': total_paginas,
        })

    return valid_entries, invalid_entries


def prepare_entries(uploads, padrao_renomeio, tratado_por_id):
    valid_entries = []
    invalid_entries = []

    for upload in uploads:
        original_name = os.path.basename(str(upload.filename or '').strip())

        if original_name == '':
            continue

        parts = os.path.splitext(original_name)[0].split('#') if os.path.splitext(original_name)[0] else []

        if not validate_file_name_pattern(len(parts), padrao_renomeio):
            invalid_entries.append(original_name)
            continue

        valid_entries.append({
            'upload': upload,
            'nome': original_name,
            'stored_name': storage.build_upload_name(original_name, 'ged_fallback'),
            'coluna1': parts[0] if len(parts) > 0 else None,
            'coluna2': parts[1] if len(parts) > 1 else None,
            'coluna3': parts[2] if len(parts) > 2 else None,
            'coluna4': parts[3] if len(parts) > 3 else None,
            'coluna5': tratado_por_id,
            'content_type': upload.mimetype or 'application/octet-stream',
            'content_length': int(upload.content_length or 0),
            'total_paginas': count_pdf_pages_safe(upload) if original_name.lower().endswith('.pdf') else 0,
        })

    return valid_entries, invalid_entries


def upload_entries_to_storage(entries):
    uploaded_names = []

    try:
        for entry in entries:
            storage.save_upload(entry['upload'], entry['stored_name'], entry['content_type'])
            uploaded_names.append(entry['stored_name'])
    except Exception:
        for uploaded_name in uploaded_names:
            try:
                storage.delete(uploaded_name)
            except Exception as cleanup_exc:
                logger.warning('GED: falha ao remover objeto apos erro de upload %s: %s', uploaded_name, cleanup_exc)
        raise

    return uploaded_names


def insert_entries(connection, parent_item_id, entries, tipodoc, numero, padrao_renomeio):
    query = (
        'INSERT INTO app_entity_43 '
        '(parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, '
        'field_445, field_446, field_447, field_448, field_449, field_450, field_458, field_474, field_475, field_554) '
        'VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)'
    )

    with connection.cursor() as cursor:
        for entry in entries:
            field446, field447, field448, field458 = resolve_document_fields(entry, padrao_renomeio)
            metadata = build_metadata(entry['nome'], entry['content_length'], entry['content_type'])
            cursor.execute(query, (
                0,
                parent_item_id,
                0,
                int(time.time()),
                None,
                entry['coluna5'],
                0,
                entry['stored_name'],
                field446,
                field447,
                field448,
                tipodoc,
                numero,
                field458,
                json.dumps(metadata, ensure_ascii=False),
                '',
                entry['total_paginas'],
            ))


@bp.route('/api/ged/upload', methods=['POST'])
def upload_ged():
    connection = None
    uploaded_names = []

    try:
        fields = require_form_fields(['numero', 'tratado_por', 'padrao_renomeio', 'tipodoc'])

        numero = fields['numero']
        tratado_por_id = fields['tratado_por']
        padrao_renomeio = int(fields['padrao_renomeio'])
        tipodoc = int(fields['tipodoc'])
        secretaria = str(request.form.get('secretaria') or '').strip() or None
        setor = str(request.form.get('setor') or '').strip() or None
        tipo = str(request.form.get('tipo') or '').strip() or None
        registro_id = int(str(request.form.get('id_registro') or '0').strip() or 0)

        if padrao_renomeio < 1 or padrao_renomeio > 4:
            raise ValueError('O campo Padrao de Renomeio deve estar entre 1 e 4.')

        uploads = get_uploaded_files()

        if not uploads:
            raise ValueError('Nenhum arquivo foi recebido na requisicao.')

        valid_entries, invalid_entries = prepare_entries(uploads, padrao_renomeio, tratado_por_id)

        if invalid_entries:
            lines = ['Os seguintes arquivos possuem formato inválido para o Padrao de Renomeio selecionado. Use nomes com partes separadas por # conforme o padrao informado:']
            for invalid_name in invalid_entries:
                lines.append('- ' + invalid_name)
            return text_response('\n'.join(lines), 400)

        connection = get_db()

        try:
            if registro_id <= 0:
                registro_id = resolve_registro_id_by_numero(connection, numero, secretaria, setor, tipo)

            validate_selected_registro(connection, registro_id, numero, secretaria, setor, tipo)
        finally:
            connection.close()
            connection = None

        uploaded_names = upload_entries_to_storage(valid_entries)

        connection = get_db()
        try:
            insert_entries(connection, registro_id, valid_entries, tipodoc, numero, padrao_renomeio)
            connection.commit()
        except Exception:
            connection.rollback()
            raise
        finally:
            connection.close()
            connection = None

        return text_response('Arquivos carregados com sucesso! Total de arquivos importados: ' + str(len(valid_entries)))
    except ObjectStorageError as exc:
        for uploaded_name in uploaded_names:
            try:
                storage.delete(uploaded_name)
            except Exception as cleanup_exc:
                logger.warning('GED: falha ao remover objeto apos erro no storage %s: %s', uploaded_name, cleanup_exc)
        return text_response('Erro ao carregar arquivos. Detalhes: ' + str(exc), 400)
    except Exception as exc:
        for uploaded_name in uploaded_names:
            try:
                storage.delete(uploaded_name)
            except Exception as cleanup_exc:
                logger.warning('GED: falha ao remover objeto apos erro no fluxo %s: %s', uploaded_name, cleanup_exc)
        if connection is not None:
            connection.close()
        return text_response('Erro ao carregar arquivos. Detalhes: ' + str(exc), 400)


@bp.route('/api/ged/upload/direct', methods=['POST'])
def upload_ged_direct():
    connection = None
    validated_entries = []

    try:
        payload = require_json_payload()
        fields = require_json_fields(payload, ['numero', 'tratado_por', 'padrao_renomeio', 'tipodoc'])

        numero = fields['numero']
        tratado_por_id = fields['tratado_por']
        padrao_renomeio = int(fields['padrao_renomeio'])
        tipodoc = int(fields['tipodoc'])
        secretaria = str(payload.get('secretaria') or '').strip() or None
        setor = str(payload.get('setor') or '').strip() or None
        tipo = str(payload.get('tipo') or '').strip() or None
        registro_id = int(str(payload.get('id_registro') or '0').strip() or 0)
        files = payload.get('files')

        if padrao_renomeio < 1 or padrao_renomeio > 4:
            raise ValueError('O campo Padrao de Renomeio deve estar entre 1 e 4.')

        if not isinstance(files, list) or not files:
            raise ValueError('Nenhum arquivo confirmado foi informado para finalizacao.')

        validated_entries, invalid_entries = prepare_entries_from_direct_upload(files, padrao_renomeio, tratado_por_id)

        if invalid_entries:
            lines = ['Os seguintes arquivos possuem formato inválido para o Padrao de Renomeio selecionado. Use nomes com partes separadas por # conforme o padrao informado:']
            for invalid_name in invalid_entries:
                lines.append('- ' + invalid_name)
            return text_response('\n'.join(lines), 400)

        missing_files = []
        for entry in validated_entries:
            if not storage.exists(entry['stored_name']):
                missing_files.append(entry['nome'])

        if missing_files:
            lines = ['Os seguintes arquivos ainda nao foram encontrados no storage:']
            for file_name in missing_files:
                lines.append('- ' + file_name)
            return text_response('\n'.join(lines), 409)

        connection = get_db()
        try:
            if registro_id <= 0:
                registro_id = resolve_registro_id_by_numero(connection, numero, secretaria, setor, tipo)

            validate_selected_registro(connection, registro_id, numero, secretaria, setor, tipo)
            insert_entries(connection, registro_id, validated_entries, tipodoc, numero, padrao_renomeio)
            connection.commit()
        except Exception:
            connection.rollback()
            raise
        finally:
            connection.close()
            connection = None

        return jsonify({
            'success': True,
            'message': 'Arquivos carregados com sucesso! Total de arquivos importados: ' + str(len(validated_entries)),
            'total_importados': len(validated_entries),
        })
    except Exception as exc:
        if connection is not None:
            connection.close()

        for entry in validated_entries:
            try:
                storage.delete(entry['stored_name'])
            except Exception:
                pass

        return text_response('Erro ao carregar arquivos. Detalhes: ' + str(exc), 400)