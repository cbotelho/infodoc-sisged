from __future__ import annotations

import os
import sys
import tempfile
from pathlib import Path

from dotenv import load_dotenv


REPO_ROOT = Path(__file__).resolve().parents[2]
SIGNER_ROOT = REPO_ROOT / 'assinador-python'

for env_file in (
    REPO_ROOT / '.env',
    REPO_ROOT / '.env.production.portainer.example',
    REPO_ROOT / '.env.docker.example',
    SIGNER_ROOT / '.env',
):
    if env_file.is_file():
        load_dotenv(env_file, override=False)

sys.path.insert(0, str(SIGNER_ROOT))

from app.config import Config  # noqa: E402
from app.services.object_storage import ObjectStorage  # noqa: E402


def main() -> int:
    storage = ObjectStorage(Config)
    storage.ensure_local_dirs()

    if not storage.enabled:
        print('ERRO: As variaveis FILE_STORAGE_R2_* nao estao configuradas para o assinador Python.')
        return 1

    source_fd, source_path = tempfile.mkstemp(prefix='signer_r2_src_', suffix='.txt')
    os.close(source_fd)

    target_fd, target_path = tempfile.mkstemp(prefix='signer_r2_dst_', suffix='.txt')
    os.close(target_fd)

    filename = f"ged_python_smoke_{os.getpid()}_{next(tempfile._get_candidate_names())}.txt"
    content = f'Teste local Python signer em {Path(source_path).name}'

    try:
        Path(source_path).write_text(content, encoding='utf-8')

        storage.upload_local_file(source_path, filename, content_type='text/plain', source='ged')
        object_info = storage.get_object_info(filename)
        storage.download_to_path(filename, target_path)
        downloaded_content = Path(target_path).read_text(encoding='utf-8')
        presigned = storage.generate_presigned_upload(filename, content_type='text/plain', expires_in=300, source='ged')

        if downloaded_content != content:
            print('ERRO: O conteudo baixado nao corresponde ao conteudo enviado.')
            return 1

        print('PYTHON_R2_OK')
        print(f"BUCKET={storage.bucket}")
        print(f"OBJECT_KEY={object_info['object_key']}")
        print(f"CONTENT_LENGTH={object_info['content_length']}")
        print(f"PRESIGNED_OBJECT_KEY={presigned['object_key']}")
        return 0
    finally:
        try:
            storage.delete(filename)
        except Exception as cleanup_error:  # pragma: no cover - diagnostico apenas
            print(f'AVISO: falha ao limpar arquivo de teste: {cleanup_error}')

        for temp_path in (source_path, target_path):
            try:
                if os.path.exists(temp_path):
                    os.unlink(temp_path)
            except OSError:
                pass


if __name__ == '__main__':
    raise SystemExit(main())