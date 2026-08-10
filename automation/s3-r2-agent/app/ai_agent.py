from pathlib import Path


def classify_document(file_path: Path) -> str:
    """Heuristica inicial. Pode ser substituida por modelo de IA/API externa."""
    name = file_path.name.lower()
    if "sefaz" in name:
        return "fiscal"
    if "contrato" in name:
        return "juridico"
    if "folha" in name or "rh" in name:
        return "rh"
    return "geral"
