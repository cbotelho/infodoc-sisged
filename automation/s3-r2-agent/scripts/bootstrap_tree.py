from pathlib import Path

from app.tree import ensure_box_structure

ROOT = Path("/data")
TENANTS = {
    "GEA": {
        "SEFAZ_RH": ["CAIXA_001", "CAIXA_002"],
        "JURIDICO": ["CAIXA_001"],
    },
    "CIPEMAC": {
        "SEFAZ_RH": ["CAIXA_001"],
    },
}


def main() -> None:
    for tenant, setores in TENANTS.items():
        for setor, caixas in setores.items():
            for caixa in caixas:
                box_dir = ROOT / tenant / setor / caixa
                ensure_box_structure(box_dir)
                print(f"Estrutura pronta: {box_dir}")


if __name__ == "__main__":
    main()
