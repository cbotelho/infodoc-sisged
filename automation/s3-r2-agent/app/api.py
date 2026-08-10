from typing import Optional

from fastapi import FastAPI

from app.config import get_settings
from app.queue_db import QueueDB
from app.worker import process_once

app = FastAPI(title="S3/R2 Agent", version="1.0.0")


@app.get("/health")
def health() -> dict:
    settings = get_settings()
    queue_db = QueueDB(settings.db_path)
    queue_db.init()
    return {
        "status": "ok",
        "root_path": settings.root_path,
        "interval_seconds": settings.poll_interval_seconds,
        "dry_run": settings.dry_run,
        "db_path": settings.db_path,
        "queue": queue_db.stats(),
    }


@app.post("/scan-now")
def scan_now() -> dict:
    return process_once()


@app.get("/stats")
def stats() -> dict:
    settings = get_settings()
    queue_db = QueueDB(settings.db_path)
    queue_db.init()
    return queue_db.stats()


@app.post("/requeue-failed")
def requeue_failed(limit: int = 100) -> dict:
    settings = get_settings()
    queue_db = QueueDB(settings.db_path)
    queue_db.init()
    total = queue_db.requeue_failed(limit=limit)
    return {"requeued": total, "limit": limit}


@app.get("/jobs")
def list_jobs(
    status: Optional[str] = None,
    tenant: Optional[str] = None,
    setor: Optional[str] = None,
    limit: int = 100,
    offset: int = 0,
) -> dict:
    settings = get_settings()
    queue_db = QueueDB(settings.db_path)
    queue_db.init()
    jobs, total = queue_db.find_jobs(status=status, tenant=tenant, setor=setor, limit=limit, offset=offset)
    return {
        "total": total,
        "limit": limit,
        "offset": offset,
        "jobs": jobs,
    }


@app.get("/jobs/{job_id}/attempts")
def get_job_attempts(job_id: int) -> dict:
    settings = get_settings()
    queue_db = QueueDB(settings.db_path)
    queue_db.init()
    attempts = queue_db.get_job_attempts(job_id)
    return {"job_id": job_id, "attempts": attempts}
