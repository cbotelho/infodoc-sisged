"""Abstrai o uso opcional de R2/S3 para os arquivos de /uploads do assinador."""

from __future__ import annotations

import io
import os
import re
import time
import unicodedata
import uuid
from typing import BinaryIO

import boto3
from botocore.config import Config as BotoConfig
from botocore.exceptions import BotoCoreError, ClientError


class ObjectStorageError(RuntimeError):
    """Erro de acesso ao storage de objetos."""


class ObjectStorage:
    LEGACY_SIGNER_FOLDER = 'assinador-python/uploads'
    GED_FOLDER = 'upload'
    GED_SOURCE_PREFIXES = ('ged_', 'ged_fallback_', 'sefaz_rh_', 'generic_')
    STANDALONE_SOURCE_PREFIXES = ('pdf_', 'assinado_', 'standalone_')

    def __init__(self, app_config):
        self.upload_dir = app_config.UPLOAD_DIR
        self.temp_dir = app_config.TEMP_DIR
        self.endpoint = app_config.FILE_STORAGE_R2_ENDPOINT
        self.region = app_config.FILE_STORAGE_R2_REGION
        
        # Multi-Tenant Bucket Router
        from flask import request, has_request_context
        bucket = app_config.FILE_STORAGE_R2_BUCKET
        if has_request_context():
            host = request.headers.get('Host', '')
            forwarded_host = request.headers.get('X-Forwarded-Host', '')
            tenant = request.headers.get('X-Tenant-DB', '')
            if 'cipemac' in host or 'cipemac' in forwarded_host or 'cipemac' in tenant:
                bucket = 'cipemac'
                
        self.bucket = bucket
        self.access_key_id = app_config.FILE_STORAGE_R2_ACCESS_KEY_ID
        self.secret_access_key = app_config.FILE_STORAGE_R2_SECRET_ACCESS_KEY
        self.prefix = (app_config.FILE_STORAGE_R2_OBJECT_PREFIX or '').strip('/')
        self.presigned_upload_expiry = int(getattr(app_config, 'PRESIGNED_UPLOAD_EXPIRY', 900) or 900)

        self.enabled = all([
            self.endpoint,
            self.bucket,
            self.access_key_id,
            self.secret_access_key,
        ])

        self._client = None

    def ensure_local_dirs(self) -> None:
        os.makedirs(self.upload_dir, exist_ok=True)
        os.makedirs(self.temp_dir, exist_ok=True)

    def _get_client(self):
        if not self.enabled:
            return None

        if self._client is None:
            self._client = boto3.client(
                's3',
                endpoint_url=self.endpoint,
                region_name=self.region,
                aws_access_key_id=self.access_key_id,
                aws_secret_access_key=self.secret_access_key,
                config=BotoConfig(signature_version='s3v4'),
            )

        return self._client

    def _normalize_folder(self, folder: str) -> str:
        return '/'.join([segment for segment in str(folder or '').strip('/').split('/') if segment])

    def resolve_storage_folder(self, source: str | None = None, filename: str | None = None) -> str:
        normalized_source = self.sanitize_filename(source).lower() if source else ''
        safe_name = self.sanitize_filename(filename) if filename else ''

        if normalized_source in {'ged', 'ged_fallback', 'sefaz_rh', 'generic', 'upload'}:
            return self.GED_FOLDER

        if normalized_source in {'standalone', 'signer'}:
            return self.LEGACY_SIGNER_FOLDER

        if safe_name.startswith(self.GED_SOURCE_PREFIXES):
            return self.GED_FOLDER

        if safe_name.startswith(self.STANDALONE_SOURCE_PREFIXES):
            return self.LEGACY_SIGNER_FOLDER

        return self.GED_FOLDER

    def build_key(self, filename: str, folder: str | None = None) -> str:
        resolved_folder = self._normalize_folder(folder or self.resolve_storage_folder(filename=filename))
        parts = [self.prefix, resolved_folder, os.path.basename(filename)]
        return '/'.join([part for part in parts if part])

    def build_candidate_keys(self, filename: str, source: str | None = None) -> list[str]:
        safe_name = self.sanitize_filename(filename)
        primary_folder = self.resolve_storage_folder(source=source, filename=safe_name)
        folders = [
            primary_folder,
            self.GED_FOLDER,
            self.LEGACY_SIGNER_FOLDER,
        ]

        keys = []
        seen = set()

        for folder in folders:
            key = self.build_key(safe_name, folder)
            if key in seen:
                continue
            seen.add(key)
            keys.append(key)

        return keys

    def sanitize_filename(self, filename: str) -> str:
        safe_name = os.path.basename(str(filename or '')).strip()

        if safe_name == '':
            raise ObjectStorageError('Nome de arquivo invalido para o storage.')

        normalized_ascii = unicodedata.normalize('NFKD', safe_name)
        normalized_ascii = normalized_ascii.encode('ascii', 'ignore').decode('ascii')
        normalized_ascii = re.sub(r'[^A-Za-z0-9._-]+', '_', normalized_ascii)
        normalized_ascii = re.sub(r'_+', '_', normalized_ascii)

        collapsed = normalized_ascii.strip('._')

        if collapsed == '':
            raise ObjectStorageError('Nome de arquivo invalido para o storage.')

        return collapsed

    def build_upload_name(self, original_name: str, source: str = 'upload') -> str:
        safe_name = self.sanitize_filename(original_name)
        source_name = self.sanitize_filename(source).lower()
        name_root, extension = os.path.splitext(safe_name)

        if name_root == '':
            name_root = 'arquivo'

        return f'{source_name}_{uuid.uuid4().hex}_{name_root}{extension}'

    def _run_with_retry(self, operation, stream=None):
        last_exception = None

        for attempt in range(1, 4):
            try:
                if stream is not None:
                    stream.seek(0)
                return operation()
            except (OSError, BotoCoreError, ClientError) as exc:
                last_exception = exc

                if attempt >= 3:
                    break

                time.sleep(attempt)

        raise last_exception

    def save_upload(self, upload, filename: str, content_type: str = 'application/pdf', source: str | None = None) -> None:
        safe_name = self.sanitize_filename(filename)

        if not self.enabled:
            upload.save(os.path.join(self.upload_dir, safe_name))
            return

        try:
            upload.stream.seek(0)
            extra_args = {'ContentType': content_type}
            object_key = self.build_key(safe_name, self.resolve_storage_folder(source=source, filename=safe_name))
            self._run_with_retry(
                lambda: self._get_client().upload_fileobj(
                    upload.stream,
                    self.bucket,
                    object_key,
                    ExtraArgs=extra_args,
                ),
                stream=upload.stream,
            )
        except (OSError, BotoCoreError, ClientError) as exc:
            raise ObjectStorageError(f'Falha ao enviar arquivo para o R2: {exc}') from exc

    def upload_local_file(self, local_path: str, filename: str, content_type: str = 'application/pdf', source: str | None = None) -> None:
        safe_name = self.sanitize_filename(filename)

        if not self.enabled:
            destination = os.path.join(self.upload_dir, safe_name)
            if os.path.abspath(local_path) != os.path.abspath(destination):
                with open(local_path, 'rb') as src, open(destination, 'wb') as dst:
                    dst.write(src.read())
            return

        try:
            object_key = self.build_key(safe_name, self.resolve_storage_folder(source=source, filename=safe_name))
            self._run_with_retry(
                lambda: self._get_client().upload_file(
                    local_path,
                    self.bucket,
                    object_key,
                    ExtraArgs={'ContentType': content_type},
                )
            )
        except (OSError, BotoCoreError, ClientError) as exc:
            raise ObjectStorageError(f'Falha ao enviar arquivo assinado para o R2: {exc}') from exc

    def generate_presigned_upload(self, filename: str, content_type: str = 'application/octet-stream', expires_in: int | None = None, source: str | None = None) -> dict:
        if not self.enabled:
            raise ObjectStorageError('Upload direto com URL assinada requer storage R2/S3 configurado.')

        safe_name = self.sanitize_filename(filename)
        ttl = int(expires_in or self.presigned_upload_expiry or 900)

        if ttl <= 0:
            ttl = 900

        object_key = self.build_key(safe_name, self.resolve_storage_folder(source=source, filename=safe_name))

        try:
            url = self._get_client().generate_presigned_url(
                'put_object',
                Params={
                    'Bucket': self.bucket,
                    'Key': object_key,
                    'ContentType': content_type,
                }, 
                ExpiresIn=ttl,
                HttpMethod='PUT',
            )
        except (BotoCoreError, ClientError) as exc:
            raise ObjectStorageError(f'Falha ao gerar URL assinada para upload: {exc}') from exc

        return {
            'url': url,
            'method': 'PUT',
            'headers': {
                'Content-Type': content_type,
            },
            'object_key': object_key,
            'filename': safe_name,
            'expires_in': ttl,
        }

    def get_object_info(self, filename: str) -> dict:
        safe_name = self.sanitize_filename(filename)

        if not self.enabled:
            local_path = os.path.join(self.upload_dir, safe_name)

            if not os.path.exists(local_path):
                raise FileNotFoundError(safe_name)

            return {
                'filename': safe_name,
                'object_key': local_path,
                'content_length': os.path.getsize(local_path),
                'content_type': 'application/octet-stream',
                'etag': None,
                'last_modified': int(os.path.getmtime(local_path)),
                'storage_mode': 'local',
            }

        response = None
        object_key = None

        for candidate_key in self.build_candidate_keys(safe_name):
            try:
                response = self._get_client().head_object(Bucket=self.bucket, Key=candidate_key)
                object_key = candidate_key
                break
            except ClientError as exc:
                error_code = exc.response.get('Error', {}).get('Code', '')
                if error_code in {'404', 'NoSuchKey', 'NotFound'}:
                    continue
                raise ObjectStorageError(f'Falha ao consultar objeto no R2: {exc}') from exc
            except BotoCoreError as exc:
                raise ObjectStorageError(f'Falha ao consultar objeto no R2: {exc}') from exc

        if response is None or object_key is None:
            raise FileNotFoundError(safe_name)

        last_modified = response.get('LastModified')

        return {
            'filename': safe_name,
            'object_key': object_key,
            'content_length': int(response.get('ContentLength') or 0),
            'content_type': response.get('ContentType') or 'application/octet-stream',
            'etag': str(response.get('ETag') or '').strip('"') or None,
            'last_modified': last_modified.isoformat() if hasattr(last_modified, 'isoformat') else None,
            'storage_mode': 'r2',
        }

    def delete(self, filename: str) -> None:
        safe_name = os.path.basename(filename)

        if not self.enabled:
            local_path = os.path.join(self.upload_dir, safe_name)
            if os.path.exists(local_path):
                os.unlink(local_path)
            return

        last_exception = None

        for candidate_key in self.build_candidate_keys(safe_name):
            try:
                self._get_client().delete_object(Bucket=self.bucket, Key=candidate_key)
            except ClientError as exc:
                error_code = exc.response.get('Error', {}).get('Code', '')
                if error_code in {'404', 'NoSuchKey', 'NotFound'}:
                    continue
                last_exception = exc
            except (OSError, BotoCoreError) as exc:
                last_exception = exc

        if last_exception is not None:
            raise ObjectStorageError(f'Falha ao remover arquivo do R2: {last_exception}') from last_exception

    def exists(self, filename: str) -> bool:
        safe_name = os.path.basename(filename)

        if not self.enabled:
            return os.path.exists(os.path.join(self.upload_dir, safe_name))

        for candidate_key in self.build_candidate_keys(safe_name):
            try:
                self._get_client().head_object(Bucket=self.bucket, Key=candidate_key)
                return True
            except ClientError as exc:
                error_code = exc.response.get('Error', {}).get('Code', '')
                if error_code in {'404', 'NoSuchKey', 'NotFound'}:
                    continue
                raise ObjectStorageError(f'Falha ao consultar arquivo no R2: {exc}') from exc
            except BotoCoreError as exc:
                raise ObjectStorageError(f'Falha ao consultar arquivo no R2: {exc}') from exc

        return False

    def download_to_path(self, filename: str, destination_path: str) -> str:
        safe_name = os.path.basename(filename)

        if not self.enabled:
            source_path = os.path.join(self.upload_dir, safe_name)
            if not os.path.exists(source_path):
                raise FileNotFoundError(source_path)
            with open(source_path, 'rb') as src, open(destination_path, 'wb') as dst:
                dst.write(src.read())
            return destination_path

        for candidate_key in self.build_candidate_keys(safe_name):
            try:
                self._get_client().download_file(self.bucket, candidate_key, destination_path)
                return destination_path
            except ClientError as exc:
                error_code = exc.response.get('Error', {}).get('Code', '')
                if error_code in {'404', 'NoSuchKey', 'NotFound'}:
                    continue
                raise ObjectStorageError(f'Falha ao baixar arquivo do R2: {exc}') from exc
            except (OSError, BotoCoreError) as exc:
                raise ObjectStorageError(f'Falha ao baixar arquivo do R2: {exc}') from exc

        raise FileNotFoundError(safe_name)

    def read_bytes(self, filename: str) -> bytes:
        safe_name = os.path.basename(filename)

        if not self.enabled:
            with open(os.path.join(self.upload_dir, safe_name), 'rb') as file_handle:
                return file_handle.read()

        for candidate_key in self.build_candidate_keys(safe_name):
            try:
                response = self._get_client().get_object(Bucket=self.bucket, Key=candidate_key)
                return response['Body'].read()
            except ClientError as exc:
                error_code = exc.response.get('Error', {}).get('Code', '')
                if error_code in {'404', 'NoSuchKey', 'NotFound'}:
                    continue
                raise ObjectStorageError(f'Falha ao ler arquivo do R2: {exc}') from exc
            except BotoCoreError as exc:
                raise ObjectStorageError(f'Falha ao ler arquivo do R2: {exc}') from exc

        raise FileNotFoundError(safe_name)

    def open_stream(self, filename: str) -> BinaryIO:
        return io.BytesIO(self.read_bytes(filename))