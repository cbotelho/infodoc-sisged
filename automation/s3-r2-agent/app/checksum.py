import hashlib
from pathlib import Path


def calculate_file_sha256(file_path: Path) -> str:
    """Calcula SHA256 do arquivo para deduplicacao."""
    sha256_hash = hashlib.sha256()
    with open(file_path, "rb") as f:
        for byte_block in iter(lambda: f.read(4096), b""):
            sha256_hash.update(byte_block)
    return sha256_hash.hexdigest()
