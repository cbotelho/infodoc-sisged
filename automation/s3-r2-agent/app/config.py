import os
from dataclasses import dataclass


@dataclass(frozen=True)
class Settings:
    root_path: str = os.getenv("AGENT_ROOT_PATH", "/data")
    poll_interval_seconds: int = int(os.getenv("AGENT_POLL_INTERVAL_SECONDS", "30"))
    tenant_allowlist: str = os.getenv("AGENT_TENANT_ALLOWLIST", "")
    db_path: str = os.getenv("AGENT_DB_PATH", "/data/.agent/queue.db")
    batch_size: int = int(os.getenv("AGENT_BATCH_SIZE", "50"))
    max_attempts: int = int(os.getenv("AGENT_MAX_ATTEMPTS", "5"))
    backoff_base_seconds: int = int(os.getenv("AGENT_BACKOFF_BASE_SECONDS", "30"))
    backoff_max_seconds: int = int(os.getenv("AGENT_BACKOFF_MAX_SECONDS", "1800"))

    s3_endpoint: str = os.getenv("S3_ENDPOINT", "")
    s3_region: str = os.getenv("S3_REGION", "auto")
    s3_access_key_id: str = os.getenv("S3_ACCESS_KEY_ID", "")
    s3_secret_access_key: str = os.getenv("S3_SECRET_ACCESS_KEY", "")
    s3_bucket_prefix: str = os.getenv("S3_BUCKET_PREFIX", "")

    dry_run: bool = os.getenv("AGENT_DRY_RUN", "false").lower() == "true"


def get_settings() -> Settings:
    return Settings()
