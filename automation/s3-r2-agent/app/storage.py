from __future__ import annotations

import mimetypes
from pathlib import Path

import boto3

from app.config import Settings


class StorageClient:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.client = boto3.client(
            "s3",
            endpoint_url=settings.s3_endpoint or None,
            region_name=settings.s3_region,
            aws_access_key_id=settings.s3_access_key_id,
            aws_secret_access_key=settings.s3_secret_access_key,
        )

    def resolve_bucket(self, tenant: str) -> str:
        prefix = self.settings.s3_bucket_prefix.strip()
        if prefix:
            return f"{prefix}-{tenant}".lower()
        return tenant.lower()

    def build_key(self, tenant: str, setor: str, caixa: str, filename: str) -> str:
        return f"{tenant}/{setor}/{caixa}/{filename}".replace(" ", "_")

    def upload_file(self, file_path: Path, tenant: str, setor: str, caixa: str) -> tuple[str, str]:
        bucket = self.resolve_bucket(tenant)
        key = self.build_key(tenant, setor, caixa, file_path.name)

        mime, _ = mimetypes.guess_type(str(file_path))
        extra = {"ContentType": mime} if mime else {}

        self.client.upload_file(str(file_path), bucket, key, ExtraArgs=extra)
        return bucket, key
