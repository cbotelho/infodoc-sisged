from __future__ import annotations

import logging
import shutil
import time
from datetime import datetime
from pathlib import Path

from app.ai_agent import classify_document
from app.checksum import calculate_file_sha256
from app.config import get_settings
from app.queue_db import QueueDB, STATUS_FAILED, UploadJob
from app.storage import StorageClient
from app.tree import FAILED_DIR, LOGS_DIR, PENDING_DIR, SENT_DIR, extract_tenant_setor_caixa

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
logger = logging.getLogger("s3-r2-agent")


def write_box_log(log_dir: Path, message: str) -> None:
    log_dir.mkdir(parents=True, exist_ok=True)
    file_name = f"upload-{datetime.now().strftime('%Y-%m-%d')}.log"
    with (log_dir / file_name).open("a", encoding="utf-8") as f:
        f.write(f"{datetime.now().isoformat()} {message}\n")


def discover_pending_files(root: Path, queue_db: QueueDB, tenant_allowlist: set[str]) -> int:
    discovered = 0
    candidates = root.glob(f"*/*/*/{PENDING_DIR}/*")

    for file_path in candidates:
        if not file_path.is_file():
            continue

        try:
            tenant, setor, caixa = extract_tenant_setor_caixa(root, file_path)
            if tenant_allowlist and tenant not in tenant_allowlist:
                continue

            doc_type = classify_document(file_path)
            file_sha256 = calculate_file_sha256(file_path)
            was_enqueued = queue_db.enqueue_file(
                tenant=tenant,
                setor=setor,
                caixa=caixa,
                file_path=str(file_path),
                doc_type=doc_type,
                file_sha256=file_sha256,
            )
            if was_enqueued:
                discovered += 1
        except Exception as exc:
            logger.error("Erro descobrindo arquivo %s: %s", file_path, exc)

    return discovered


def _move_to_failed(root: Path, job: UploadJob) -> None:
    file_path = Path(job.file_path)
    fail_dir = root / job.tenant / job.setor / job.caixa / FAILED_DIR
    fail_dir.mkdir(parents=True, exist_ok=True)
    if file_path.exists():
        shutil.move(str(file_path), str(fail_dir / file_path.name))


def process_once() -> dict:
    settings = get_settings()
    root = Path(settings.root_path)
    storage = StorageClient(settings)
    queue_db = QueueDB(settings.db_path)
    queue_db.init()

    processed = 0
    failed_permanent = 0
    retry_scheduled = 0

    allowlist = {x.strip() for x in settings.tenant_allowlist.split(",") if x.strip()}
    discovered = discover_pending_files(root, queue_db, allowlist)
    jobs = queue_db.claim_ready_jobs(settings.batch_size)

    for job in jobs:
        file_path = Path(job.file_path)
        try:
            box_root = root / job.tenant / job.setor / job.caixa
            sent_dir = box_root / SENT_DIR
            log_dir = box_root / LOGS_DIR

            sent_dir.mkdir(parents=True, exist_ok=True)
            write_box_log(log_dir, f"START file={file_path.name} attempt={job.attempt_count + 1}")

            if not file_path.exists():
                status, _ = queue_db.mark_retry_or_failed(
                    job=job,
                    error_message="Arquivo nao encontrado na pasta de origem",
                    max_attempts=settings.max_attempts,
                    backoff_base_seconds=settings.backoff_base_seconds,
                    backoff_max_seconds=settings.backoff_max_seconds,
                )
                if status == STATUS_FAILED:
                    failed_permanent += 1
                else:
                    retry_scheduled += 1
                continue

            if settings.dry_run:
                queue_db.mark_uploaded(job.id, bucket="dry-run", object_key=f"dry/{file_path.name}")
                shutil.move(str(file_path), str(sent_dir / file_path.name))
                write_box_log(log_dir, f"DRY_RUN_SUCCESS file={file_path.name}")
                processed += 1
                continue

            bucket, key = storage.upload_file(file_path, job.tenant, job.setor, job.caixa)
            queue_db.mark_uploaded(job.id, bucket=bucket, object_key=key, attempt_number=job.attempt_count + 1)
            shutil.move(str(file_path), str(sent_dir / file_path.name))
            write_box_log(log_dir, f"SUCCESS file={file_path.name} bucket={bucket} key={key} sha256={job.file_sha256}")
            processed += 1
        except Exception as exc:
            status, wait_seconds = queue_db.mark_retry_or_failed(
                job=job,
                error_message=str(exc),
                max_attempts=settings.max_attempts,
                backoff_base_seconds=settings.backoff_base_seconds,
                backoff_max_seconds=settings.backoff_max_seconds,
            )
            try:
                log_dir = root / job.tenant / job.setor / job.caixa / LOGS_DIR
                if status == STATUS_FAILED:
                    _move_to_failed(root, job)
                    write_box_log(log_dir, f"FAILED_PERMANENT file={file_path.name} reason={exc}")
                    failed_permanent += 1
                else:
                    write_box_log(log_dir, f"RETRY_SCHEDULED file={file_path.name} wait_seconds={wait_seconds} reason={exc}")
                    retry_scheduled += 1
            except Exception as internal_exc:
                logger.error("Falha no fallback de erro: %s", internal_exc)
            logger.error("Erro processando %s: %s", file_path, exc)

    return {
        "discovered": discovered,
        "claimed": len(jobs),
        "processed": processed,
        "retry_scheduled": retry_scheduled,
        "failed_permanent": failed_permanent,
    }


def run_forever() -> None:
    settings = get_settings()
    queue_db = QueueDB(settings.db_path)
    queue_db.init()

    logger.info("Agente iniciado. root=%s interval=%ss", settings.root_path, settings.poll_interval_seconds)
    while True:
        result = process_once()
        if result["claimed"] > 0 or result["discovered"] > 0:
            logger.info(
                "Lote concluido discovered=%s claimed=%s processed=%s retry=%s failed=%s",
                result["discovered"],
                result["claimed"],
                result["processed"],
                result["retry_scheduled"],
                result["failed_permanent"],
            )
        time.sleep(settings.poll_interval_seconds)


if __name__ == "__main__":
    run_forever()
