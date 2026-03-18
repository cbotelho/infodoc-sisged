"""Abstrai o uso opcional de R2/S3 para os arquivos de /uploads do assinador."""

from __future__ import annotations

import io
import os
from typing import BinaryIO

import boto3
from botocore.config import Config as BotoConfig
from botocore.exceptions import BotoCoreError, ClientError


class ObjectStorageError(RuntimeError):
    """Erro de acesso ao storage de objetos."""


class ObjectStorage:
    def __init__(self, app_config):
        self.upload_dir = app_config.UPLOAD_DIR
        self.temp_dir = app_config.TEMP_DIR
        self.endpoint = app_config.FILE_STORAGE_R2_ENDPOINT
        self.region = app_config.FILE_STORAGE_R2_REGION
        self.bucket = app_config.FILE_STORAGE_R2_BUCKET
        self.access_key_id = app_config.FILE_STORAGE_R2_ACCESS_KEY_ID
        self.secret_access_key = app_config.FILE_STORAGE_R2_SECRET_ACCESS_KEY
        self.prefix = (app_config.FILE_STORAGE_R2_OBJECT_PREFIX or '').strip('/')

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

    def build_key(self, filename: str) -> str:
        parts = [self.prefix, 'assinador-python', 'uploads', os.path.basename(filename)]
        return '/'.join([part for part in parts if part])

    def save_upload(self, upload, filename: str, content_type: str = 'application/pdf') -> None:
        safe_name = os.path.basename(filename)

        if not self.enabled:
            upload.save(os.path.join(self.upload_dir, safe_name))
            return

        try:
            upload.stream.seek(0)
            extra_args = {'ContentType': content_type}
            self._get_client().upload_fileobj(upload.stream, self.bucket, self.build_key(safe_name), ExtraArgs=extra_args)
        except (OSError, BotoCoreError, ClientError) as exc:
            raise ObjectStorageError(f'Falha ao enviar arquivo para o R2: {exc}') from exc

    def upload_local_file(self, local_path: str, filename: str, content_type: str = 'application/pdf') -> None:
        safe_name = os.path.basename(filename)

        if not self.enabled:
            destination = os.path.join(self.upload_dir, safe_name)
            if os.path.abspath(local_path) != os.path.abspath(destination):
                with open(local_path, 'rb') as src, open(destination, 'wb') as dst:
                    dst.write(src.read())
            return

        try:
            self._get_client().upload_file(
                local_path,
                self.bucket,
                self.build_key(safe_name),
                ExtraArgs={'ContentType': content_type},
            )
        except (OSError, BotoCoreError, ClientError) as exc:
            raise ObjectStorageError(f'Falha ao enviar arquivo assinado para o R2: {exc}') from exc

    def exists(self, filename: str) -> bool:
        safe_name = os.path.basename(filename)

        if not self.enabled:
            return os.path.exists(os.path.join(self.upload_dir, safe_name))

        try:
            self._get_client().head_object(Bucket=self.bucket, Key=self.build_key(safe_name))
            return True
        except ClientError as exc:
            error_code = exc.response.get('Error', {}).get('Code', '')
            if error_code in {'404', 'NoSuchKey', 'NotFound'}:
                return False
            raise ObjectStorageError(f'Falha ao consultar arquivo no R2: {exc}') from exc
        except BotoCoreError as exc:
            raise ObjectStorageError(f'Falha ao consultar arquivo no R2: {exc}') from exc

    def download_to_path(self, filename: str, destination_path: str) -> str:
        safe_name = os.path.basename(filename)

        if not self.enabled:
            source_path = os.path.join(self.upload_dir, safe_name)
            if not os.path.exists(source_path):
                raise FileNotFoundError(source_path)
            with open(source_path, 'rb') as src, open(destination_path, 'wb') as dst:
                dst.write(src.read())
            return destination_path

        try:
            self._get_client().download_file(self.bucket, self.build_key(safe_name), destination_path)
            return destination_path
        except ClientError as exc:
            error_code = exc.response.get('Error', {}).get('Code', '')
            if error_code in {'404', 'NoSuchKey', 'NotFound'}:
                raise FileNotFoundError(safe_name) from exc
            raise ObjectStorageError(f'Falha ao baixar arquivo do R2: {exc}') from exc
        except (OSError, BotoCoreError) as exc:
            raise ObjectStorageError(f'Falha ao baixar arquivo do R2: {exc}') from exc

    def read_bytes(self, filename: str) -> bytes:
        safe_name = os.path.basename(filename)

        if not self.enabled:
            with open(os.path.join(self.upload_dir, safe_name), 'rb') as file_handle:
                return file_handle.read()

        try:
            response = self._get_client().get_object(Bucket=self.bucket, Key=self.build_key(safe_name))
            return response['Body'].read()
        except ClientError as exc:
            error_code = exc.response.get('Error', {}).get('Code', '')
            if error_code in {'404', 'NoSuchKey', 'NotFound'}:
                raise FileNotFoundError(safe_name) from exc
            raise ObjectStorageError(f'Falha ao ler arquivo do R2: {exc}') from exc
        except BotoCoreError as exc:
            raise ObjectStorageError(f'Falha ao ler arquivo do R2: {exc}') from exc

    def open_stream(self, filename: str) -> BinaryIO:
        return io.BytesIO(self.read_bytes(filename))