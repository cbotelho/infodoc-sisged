from __future__ import annotations

import sqlite3
from contextlib import contextmanager
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Iterator


STATUS_QUEUED = "QUEUED"
STATUS_UPLOADING = "UPLOADING"
STATUS_UPLOADED = "UPLOADED"
STATUS_RETRYING = "RETRYING"
STATUS_FAILED = "FAILED"


@dataclass
class UploadJob:
    id: int
    tenant: str
    setor: str
    caixa: str
    file_path: str
    file_name: str
    status: str
    attempt_count: int
    next_attempt_at: str
    last_error: str
    file_sha256: str | None = None


class QueueDB:
    def __init__(self, db_path: str) -> None:
        self.db_path = Path(db_path)
        self.db_path.parent.mkdir(parents=True, exist_ok=True)

    @contextmanager
    def connect(self) -> Iterator[sqlite3.Connection]:
        conn = sqlite3.connect(self.db_path)
        try:
            conn.row_factory = sqlite3.Row
            yield conn
            conn.commit()
        finally:
            conn.close()

    def init(self) -> None:
        with self.connect() as conn:
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS upload_jobs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tenant TEXT NOT NULL,
                    setor TEXT NOT NULL,
                    caixa TEXT NOT NULL,
                    file_path TEXT NOT NULL UNIQUE,
                    file_name TEXT NOT NULL,
                    file_sha256 TEXT,
                    status TEXT NOT NULL,
                    attempt_count INTEGER NOT NULL DEFAULT 0,
                    next_attempt_at TEXT NOT NULL,
                    doc_type TEXT,
                    bucket TEXT,
                    object_key TEXT,
                    last_error TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )
                """
            )
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS upload_attempts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    job_id INTEGER NOT NULL,
                    attempt_number INTEGER NOT NULL,
                    status TEXT NOT NULL,
                    error_message TEXT,
                    bucket TEXT,
                    object_key TEXT,
                    started_at TEXT NOT NULL,
                    completed_at TEXT,
                    FOREIGN KEY(job_id) REFERENCES upload_jobs(id)
                )
                """
            )
            conn.execute(
                """
                CREATE INDEX IF NOT EXISTS idx_upload_jobs_status_next_attempt
                ON upload_jobs(status, next_attempt_at)
                """
            )
            conn.execute(
                """
                CREATE INDEX IF NOT EXISTS idx_upload_jobs_tenant_setor
                ON upload_jobs(tenant, setor)
                """
            )
            conn.execute(
                """
                CREATE INDEX IF NOT EXISTS idx_upload_jobs_sha256
                ON upload_jobs(file_sha256)
                """
            )
            conn.execute(
                """
                CREATE INDEX IF NOT EXISTS idx_upload_attempts_job_id
                ON upload_attempts(job_id, attempt_number)
                """
            )

    def enqueue_file(self, tenant: str, setor: str, caixa: str, file_path: str, doc_type: str, file_sha256: str | None = None) -> bool:
        now = _utc_now_iso()
        file_name = Path(file_path).name

        with self.connect() as conn:
            row = conn.execute(
                "SELECT id, status FROM upload_jobs WHERE file_path = ?",
                (file_path,),
            ).fetchone()
            if row is not None:
                # Reativa apenas se era falha permanente e o arquivo reapareceu no pendente.
                if row["status"] == STATUS_FAILED:
                    conn.execute(
                        """
                        UPDATE upload_jobs
                        SET status = ?, next_attempt_at = ?, last_error = '', updated_at = ?
                        WHERE id = ?
                        """,
                        (STATUS_QUEUED, now, now, row["id"]),
                    )
                    return True
                return False

            conn.execute(
                """
                INSERT INTO upload_jobs (
                    tenant, setor, caixa, file_path, file_name, file_sha256, status,
                    attempt_count, next_attempt_at, doc_type, bucket,
                    object_key, last_error, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, '', '', '', ?, ?)
                """,
                (tenant, setor, caixa, file_path, file_name, file_sha256, STATUS_QUEUED, now, doc_type, now, now),
            )
            return True

    def claim_ready_jobs(self, limit: int) -> list[UploadJob]:
        now = _utc_now_iso()
        jobs: list[UploadJob] = []

        with self.connect() as conn:
            rows = conn.execute(
                """
                SELECT *
                FROM upload_jobs
                WHERE status IN (?, ?) AND next_attempt_at <= ?
                ORDER BY created_at ASC
                LIMIT ?
                """,
                (STATUS_QUEUED, STATUS_RETRYING, now, limit),
            ).fetchall()

            for row in rows:
                conn.execute(
                    "UPDATE upload_jobs SET status = ?, updated_at = ? WHERE id = ?",
                    (STATUS_UPLOADING, now, row["id"]),
                )
                jobs.append(
                    UploadJob(
                        id=row["id"],
                        tenant=row["tenant"],
                        setor=row["setor"],
                        caixa=row["caixa"],
                        file_path=row["file_path"],
                        file_name=row["file_name"],
                        status=STATUS_UPLOADING,
                        attempt_count=row["attempt_count"],
                        next_attempt_at=row["next_attempt_at"],
                        last_error=row["last_error"],
                        file_sha256=row["file_sha256"],
                    )
                )

        return jobs

    def mark_uploaded(self, job_id: int, bucket: str, object_key: str, attempt_number: int) -> None:
        now = _utc_now_iso()
        with self.connect() as conn:
            conn.execute(
                """
                UPDATE upload_jobs
                SET status = ?, bucket = ?, object_key = ?, last_error = '', updated_at = ?
                WHERE id = ?
                """,
                (STATUS_UPLOADED, bucket, object_key, now, job_id),
            )
            conn.execute(
                """
                INSERT INTO upload_attempts (job_id, attempt_number, status, error_message, bucket, object_key, started_at, completed_at)
                VALUES (?, ?, ?, NULL, ?, ?, ?, ?)
                """,
                (job_id, attempt_number, STATUS_UPLOADED, bucket, object_key, now, now),
            )

    def mark_retry_or_failed(self, job: UploadJob, error_message: str, max_attempts: int, backoff_base_seconds: int, backoff_max_seconds: int) -> tuple[str, int]:
        next_attempt_count = job.attempt_count + 1
        now = _utc_now_iso()

        if next_attempt_count >= max_attempts:
            with self.connect() as conn:
                conn.execute(
                    """
                    UPDATE upload_jobs
                    SET status = ?, attempt_count = ?, last_error = ?, updated_at = ?
                    WHERE id = ?
                    """,
                    (STATUS_FAILED, next_attempt_count, error_message[:1200], now, job.id),
                )
                conn.execute(
                    """
                    INSERT INTO upload_attempts (job_id, attempt_number, status, error_message, started_at, completed_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                    """,
                    (job.id, next_attempt_count, STATUS_FAILED, error_message[:1200], now, now),
                )
            return STATUS_FAILED, 0

        wait_seconds = min(backoff_max_seconds, backoff_base_seconds * (2 ** max(0, next_attempt_count - 1)))
        next_try = (datetime.now(timezone.utc) + timedelta(seconds=wait_seconds)).isoformat()

        with self.connect() as conn:
            conn.execute(
                """
                UPDATE upload_jobs
                SET status = ?, attempt_count = ?, next_attempt_at = ?, last_error = ?, updated_at = ?
                WHERE id = ?
                """,
                (STATUS_RETRYING, next_attempt_count, next_try, error_message[:1200], now, job.id),
            )
            conn.execute(
                """
                INSERT INTO upload_attempts (job_id, attempt_number, status, error_message, started_at, completed_at)
                VALUES (?, ?, ?, ?, ?, ?)
                """,
                (job.id, next_attempt_count, STATUS_RETRYING, error_message[:1200], now, now),
            )

        return STATUS_RETRYING, wait_seconds

    def stats(self) -> dict:
        with self.connect() as conn:
            rows = conn.execute(
                "SELECT status, COUNT(*) AS total FROM upload_jobs GROUP BY status"
            ).fetchall()
            by_status = {row["status"]: row["total"] for row in rows}
            total = sum(by_status.values())

        return {
            "total": total,
            "queued": by_status.get(STATUS_QUEUED, 0),
            "uploading": by_status.get(STATUS_UPLOADING, 0),
            "retrying": by_status.get(STATUS_RETRYING, 0),
            "uploaded": by_status.get(STATUS_UPLOADED, 0),
            "failed": by_status.get(STATUS_FAILED, 0),
        }

    def requeue_failed(self, limit: int) -> int:
        now = _utc_now_iso()
        with self.connect() as conn:
            rows = conn.execute(
                "SELECT id FROM upload_jobs WHERE status = ? ORDER BY updated_at DESC LIMIT ?",
                (STATUS_FAILED, limit),
            ).fetchall()
            ids = [row["id"] for row in rows]
            for job_id in ids:
                conn.execute(
                    """
                    UPDATE upload_jobs
                    SET status = ?, next_attempt_at = ?, last_error = '', updated_at = ?
                    WHERE id = ?
                    """,
                    (STATUS_QUEUED, now, now, job_id),
                )
        return len(ids)

    def find_jobs(
        self,
        status: str | None = None,
        tenant: str | None = None,
        setor: str | None = None,
        limit: int = 100,
        offset: int = 0,
    ) -> tuple[list[dict], int]:
        with self.connect() as conn:
            query = "SELECT * FROM upload_jobs WHERE 1=1"
            params: list = []

            if status:
                query += " AND status = ?"
                params.append(status)
            if tenant:
                query += " AND tenant = ?"
                params.append(tenant)
            if setor:
                query += " AND setor = ?"
                params.append(setor)

            count_query = query.replace("SELECT *", "SELECT COUNT(*) as total")
            count_row = conn.execute(count_query, params).fetchone()
            total = count_row["total"] if count_row else 0

            query += " ORDER BY updated_at DESC LIMIT ? OFFSET ?"
            params.extend([limit, offset])

            rows = conn.execute(query, params).fetchall()
            jobs = [dict(row) for row in rows]

        return jobs, total

    def get_job_attempts(self, job_id: int) -> list[dict]:
        with self.connect() as conn:
            rows = conn.execute(
                "SELECT * FROM upload_attempts WHERE job_id = ? ORDER BY attempt_number DESC",
                (job_id,),
            ).fetchall()
            return [dict(row) for row in rows]


def _utc_now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()
