# -*- coding: utf-8 -*-
"""Endpoint de upload SEFAZ RH usando o mesmo fluxo de storage do assinador."""

import logging
import os
import time

from flask import Blueprint, Response, jsonify, request
from PyPDF2 import PdfReader

from app.config import Config
from app.services.database import get_db
from app.services.object_storage import ObjectStorage, ObjectStorageError

logger = logging.getLogger(__name__)

storage = ObjectStorage(Config)
storage.ensure_local_dirs()

bp = Blueprint('sefaz_rh', __name__, url_prefix='')


def text_response(message, status=200):
    return Response(message, status=status, mimetype='text/plain; charset=utf-8')


def normalize_cpf(value):
    return ''.join(character for character in str(value or '') if character.isdigit())


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


def get_file_name_parts(file_name):
    name_without_extension = os.path.splitext(str(file_name or ''))[0]

    if name_without_extension == '':
        return []

    return name_without_extension.split('#')


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


def count_pdf_pages_safe(upload):
    try:
        upload.stream.seek(0)
        reader = PdfReader(upload.stream)
        return len(reader.pages)
    except Exception as exc:
        logger.warning('SEFAZ RH: falha ao contar paginas do PDF %s: %s', upload.filename, exc)
        return 0
    finally:
        upload.stream.seek(0)


def count_pdf_pages_safe_from_storage(stored_name):
    try:
        stream = storage.open_stream(stored_name)
        reader = PdfReader(stream)
        return len(reader.pages)
    except Exception as exc:
        logger.warning('SEFAZ RH: falha ao contar paginas do PDF remoto %s: %s', stored_name, exc)
        return 0


def resolve_document_fields(entry):
    matricula = str(entry.get('coluna1') or '').strip()
    interessado = str(entry.get('coluna2') or '').strip()
    cpf = normalize_cpf(entry.get('coluna3') or '')

    return matricula, interessado, cpf


def table_has_column(connection, table_name, column_name):
    query = (
        'SELECT COUNT(*) AS total '
        'FROM information_schema.COLUMNS '
        'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s'
    )

    with connection.cursor() as cursor:
        cursor.execute(query, (table_name, column_name))
        row = cursor.fetchone()

    return int(row['total']) > 0


def get_registro_by_id(connection, registro_id):
    query = (
        'SELECT id, field_524, field_525, field_526, field_527 '
        'FROM app_entity_48 WHERE id = %s LIMIT 1'
    )

    with connection.cursor() as cursor:
        cursor.execute(query, (registro_id,))
        return cursor.fetchone()


def resolve_registro_id_by_numero(connection, numero, secretaria=None, setor=None, tipo=None):
    numero = str(numero or '').strip()

    if numero == '':
        raise ValueError('Informe o numero da Caixa/Pasta.')

    base_conditions = ['TRIM(field_527) = %s']
    base_params = [numero]

    if secretaria:
        base_conditions.append('field_524 = %s')
        base_params.append(str(secretaria).strip())

    if setor:
        base_conditions.append('field_525 = %s')
        base_params.append(str(setor).strip())

    def fetch_registro(use_tipo_filter):
        conditions = list(base_conditions)
        params = list(base_params)

        if use_tipo_filter and tipo:
            conditions.append('field_526 = %s')
            params.append(str(tipo).strip())

        query = (
            'SELECT id FROM app_entity_48 WHERE ' + ' AND '.join(conditions) + ' ORDER BY id DESC LIMIT 1'
        )

        with connection.cursor() as cursor:
            cursor.execute(query, params)
            return cursor.fetchone()

    registro = fetch_registro(True)

    if not registro and tipo:
        registro = fetch_registro(False)

    if not registro:
        raise ValueError('Nenhuma Caixa/Pasta foi encontrada na entidade 48 com os filtros informados.')

    return int(registro['id'])


def validate_selected_registro(connection, registro_id, numero, secretaria=None, setor=None, tipo=None):
    registro = get_registro_by_id(connection, registro_id)

    if not registro:
        raise ValueError('O registro selecionado para a Caixa/Pasta e invalido ou nao existe.')

    if numero and str(registro.get('field_527') or '').strip() != str(numero).strip():
        raise ValueError('O numero informado nao corresponde ao registro pai selecionado na entidade 48.')

    if secretaria and str(registro.get('field_524') or '').strip() != str(secretaria).strip():
        raise ValueError('A secretaria informada nao corresponde ao registro pai selecionado na entidade 48.')

    if setor and str(registro.get('field_525') or '').strip() != str(setor).strip():
        raise ValueError('O setor informado nao corresponde ao registro pai selecionado na entidade 48.')

    return registro


def prepare_entries(uploads, padrao_renomeio, tratado_por_id):
    valid_entries = []
    invalid_entries = []

    for upload in uploads:
        original_name = os.path.basename(str(upload.filename or '').strip())

        if original_name == '':
            continue

        parts = get_file_name_parts(original_name)

        if not validate_file_name_pattern(len(parts), padrao_renomeio):
            invalid_entries.append(original_name)
            continue

        valid_entries.append({
            'upload': upload,
            'nome': original_name,
            'stored_name': original_name.replace('#', '_'),
            'coluna1': parts[0] if len(parts) > 0 else None,
            'coluna2': parts[1] if len(parts) > 1 else None,
            'coluna3': parts[2] if len(parts) > 2 else None,
            'coluna4': parts[3] if len(parts) > 3 else None,
            'coluna5': tratado_por_id,
            'content_type': upload.mimetype or 'application/octet-stream',
            'total_paginas': count_pdf_pages_safe(upload) if original_name.lower().endswith('.pdf') else 0,
        })

    return valid_entries, invalid_entries


def prepare_entries_from_direct_upload(files, padrao_renomeio, tratado_por_id):
    valid_entries = []
    invalid_entries = []

    for item in files:
        original_name = os.path.basename(str(item.get('original_name') or '').strip())
        stored_name = os.path.basename(str(item.get('stored_name') or '').strip())
        content_type = str(item.get('content_type') or 'application/octet-stream').strip() or 'application/octet-stream'

        if original_name == '' or stored_name == '':
            invalid_entries.append(original_name or stored_name or 'arquivo_sem_nome')
            continue

        parts = get_file_name_parts(original_name)

        if not validate_file_name_pattern(len(parts), padrao_renomeio):
            invalid_entries.append(original_name)
            continue

        total_paginas = 0
        if original_name.lower().endswith('.pdf'):
            total_paginas = count_pdf_pages_safe_from_storage(stored_name)

        valid_entries.append({
            'nome': original_name,
            'stored_name': stored_name,
            'coluna1': parts[0] if len(parts) > 0 else None,
            'coluna2': parts[1] if len(parts) > 1 else None,
            'coluna3': parts[2] if len(parts) > 2 else None,
            'coluna4': parts[3] if len(parts) > 3 else None,
            'coluna5': tratado_por_id,
            'content_type': content_type,
            'total_paginas': total_paginas,
        })

    return valid_entries, invalid_entries


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
                logger.warning('SEFAZ RH: falha ao remover objeto apos erro de upload %s: %s', uploaded_name, cleanup_exc)
        raise

    return uploaded_names


def insert_entries(connection, parent_item_id, entries, tipodoc, doctipo, numero, assunto):
    has_field563 = table_has_column(connection, 'app_entity_49', 'field_563')

    if has_field563:
        query = (
            'INSERT INTO app_entity_49 '
            '(parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, '
            'field_542, field_543, field_544, field_545, field_546, field_548, field_552, field_553, field_563) '
            'VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)'
        )
    else:
        query = (
            'INSERT INTO app_entity_49 '
            '(parent_id, parent_item_id, linked_id, date_added, date_updated, created_by, sort_order, '
            'field_542, field_543, field_544, field_545, field_546, field_548, field_552, field_553) '
            'VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)'
        )

    with connection.cursor() as cursor:
        for entry in entries:
            matricula, interessado, cpf = resolve_document_fields(entry)
            params = [
                0,
                parent_item_id,
                0,
                int(time.time()),
                None,
                entry['coluna5'],
                0,
                entry['stored_name'],
                matricula,
                interessado,
                cpf,
                tipodoc,
                assunto,
                entry['total_paginas'],
                numero,
            ]

            if has_field563:
                params.append(doctipo)

            cursor.execute(query, params)


@bp.route('/api/sefaz-rh/upload', methods=['POST'])
def upload_sefaz_rh():
    connection = None
    uploaded_names = []

    try:
        fields = require_form_fields(['numero', 'tratado_por', 'padrao_renomeio', 'tipodoc', 'doctipo', 'assunto'])

        numero = fields['numero']
        tratado_por_id = fields['tratado_por']
        padrao_renomeio = int(fields['padrao_renomeio'])
        tipodoc = int(fields['tipodoc'])
        doctipo = int(fields['doctipo'])
        assunto = fields['assunto']
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
            message_lines = [
                'Os seguintes arquivos possuem formato inválido para o Padrao de Renomeio selecionado. Use nomes com partes separadas por # conforme o padrao informado:'
            ]
            for invalid_name in invalid_entries:
                message_lines.append('- ' + invalid_name)
            return text_response('\n'.join(message_lines), 400)

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
            insert_entries(connection, registro_id, valid_entries, tipodoc, doctipo, numero, assunto)
            connection.commit()
        except Exception:
            connection.rollback()
            raise
        finally:
            connection.close()
            connection = None

        return text_response(
            'Arquivos carregados com sucesso! Total de arquivos importados: ' + str(len(valid_entries))
        )
    except ObjectStorageError as exc:
        for uploaded_name in uploaded_names:
            try:
                storage.delete(uploaded_name)
            except Exception as cleanup_exc:
                logger.warning('SEFAZ RH: falha ao remover objeto apos erro no storage %s: %s', uploaded_name, cleanup_exc)
        return text_response('Erro ao carregar arquivos. Detalhes: ' + str(exc), 400)
    except Exception as exc:
        for uploaded_name in uploaded_names:
            try:
                storage.delete(uploaded_name)
            except Exception as cleanup_exc:
                logger.warning('SEFAZ RH: falha ao remover objeto apos erro no fluxo %s: %s', uploaded_name, cleanup_exc)
        if connection is not None:
            connection.close()
        return text_response('Erro ao carregar arquivos. Detalhes: ' + str(exc), 400)


@bp.route('/api/sefaz-rh/upload/direct', methods=['POST'])
def upload_sefaz_rh_direct():
    connection = None
    validated_entries = []

    try:
        payload = require_json_payload()
        fields = require_json_fields(payload, ['numero', 'tratado_por', 'padrao_renomeio', 'tipodoc', 'doctipo', 'assunto'])

        numero = fields['numero']
        tratado_por_id = fields['tratado_por']
        padrao_renomeio = int(fields['padrao_renomeio'])
        tipodoc = int(fields['tipodoc'])
        doctipo = int(fields['doctipo'])
        assunto = fields['assunto']
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
            message_lines = [
                'Os seguintes arquivos possuem formato inválido para o Padrao de Renomeio selecionado. Use nomes com partes separadas por # conforme o padrao informado:'
            ]
            for invalid_name in invalid_entries:
                message_lines.append('- ' + invalid_name)
            return text_response('\n'.join(message_lines), 400)

        missing_files = []
        for entry in validated_entries:
            if not storage.exists(entry['stored_name']):
                missing_files.append(entry['nome'])

        if missing_files:
            message_lines = ['Os seguintes arquivos ainda nao foram encontrados no storage:']
            for file_name in missing_files:
                message_lines.append('- ' + file_name)
            return text_response('\n'.join(message_lines), 409)

        connection = get_db()

        try:
            if registro_id <= 0:
                registro_id = resolve_registro_id_by_numero(connection, numero, secretaria, setor, tipo)

            validate_selected_registro(connection, registro_id, numero, secretaria, setor, tipo)
            insert_entries(connection, registro_id, validated_entries, tipodoc, doctipo, numero, assunto)
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
            except Exception as cleanup_exc:
                logger.warning('SEFAZ RH: falha ao remover objeto apos erro na finalizacao %s: %s', entry['stored_name'], cleanup_exc)

        return text_response('Erro ao carregar arquivos. Detalhes: ' + str(exc), 400)