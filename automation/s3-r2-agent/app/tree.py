from pathlib import Path

PENDING_DIR = "Arquivos_P_Envio"
SENT_DIR = "Arquivos_Enviados"
FAILED_DIR = "Arquivos_Nao_Enviados"
LOGS_DIR = "Logs"


class TreePathError(ValueError):
    pass


def ensure_box_structure(box_dir: Path) -> None:
    for name in (PENDING_DIR, SENT_DIR, FAILED_DIR, LOGS_DIR):
        (box_dir / name).mkdir(parents=True, exist_ok=True)


def extract_tenant_setor_caixa(root_path: Path, file_path: Path) -> tuple[str, str, str]:
    rel = file_path.relative_to(root_path)
    if len(rel.parts) < 4:
        raise TreePathError(f"Caminho invalido para arquivo de entrada: {file_path}")

    tenant, setor, caixa = rel.parts[0], rel.parts[1], rel.parts[2]
    return tenant, setor, caixa
