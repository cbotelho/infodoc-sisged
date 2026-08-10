#!/usr/bin/env python3
"""Migra arquivos referenciados no banco para o bucket correto no R2/S3.

Padrao seguro:
- Dry-run (nao altera nada) quando --apply nao e informado.
- Copia somente objetos listados no banco.
- Mantem fallback de busca por chave para cenarios legados.

Exemplo dry-run:
python ecm/migrate_r2_objects_from_db.py \
  --endpoint "https://<accountid>.r2.cloudflarestorage.com" \
  --access-key "<ACCESS_KEY>" \
  --secret-key "<SECRET_KEY>" \
  --from-bucket "gea" \
  --to-bucket "cipemac" \
  --prefix "ged" \
  --db-host "127.0.0.1" \
  --db-port 3306 \
  --db-user "user" \
  --db-pass "pass" \
  --db-name "database" \
  --source all

Aplicar migracao:
python ecm/migrate_r2_objects_from_db.py <mesmos argumentos> --apply
"""

from __future__ import annotations

import argparse
import os
import re
import sys
import unicodedata
from typing import Iterable, List, Sequence, Set, Tuple

import boto3
import pymysql
from botocore.config import Config as BotoConfig
from botocore.exceptions import BotoCoreError, ClientError

SOURCE_ALL = "all"
SOURCE_GED = "ged"
SOURCE_SEFAZ_RH = "sefaz_rh"
VALID_SOURCES = (SOURCE_ALL, SOURCE_GED, SOURCE_SEFAZ_RH)


def sanitize_filename(filename: str) -> str:
    name = os.path.basename(str(filename or "").strip())
    if not name:
        return ""

    normalized = unicodedata.normalize("NFKD", name).encode("ascii", "ignore").decode("ascii")
    normalized = re.sub(r"[^A-Za-z0-9._-]+", "_", normalized)
    normalized = re.sub(r"_+", "_", normalized).strip("._")
    return normalized


def normalize_relative_path(path: str) -> str:
    path = str(path or "").replace("\\", "/").strip("/")
    if not path:
        return ""

    segments = [seg for seg in path.split("/") if seg and seg not in (".", "..")]
    return "/".join(segments)


def build_candidate_keys(prefix: str, stored_value: str) -> List[str]:
    normalized = normalize_relative_path(stored_value)
    raw_name = os.path.basename(normalized)
    safe_name = sanitize_filename(raw_name)

    keys: List[str] = []
    seen: Set[str] = set()

    def add_key(parts: Sequence[str]) -> None:
        key = "/".join([p.strip("/") for p in parts if p and p.strip("/")])
        if key and key not in seen:
            seen.add(key)
            keys.append(key)

    # Prioriza caminho completo quando existir.
    if normalized:
        add_key((prefix, "upload", normalized))

    if safe_name and safe_name != raw_name:
        add_key((prefix, "upload", safe_name))

    # Fallbacks historicos.
    add_key((prefix, "upload", raw_name))
    add_key((prefix, "assinador-python/uploads", raw_name))

    if safe_name:
        add_key((prefix, "assinador-python/uploads", safe_name))

    # Fallback sem pasta fixa.
    if normalized:
        add_key((prefix, normalized))
    add_key((prefix, raw_name))
    if safe_name:
        add_key((prefix, safe_name))

    return keys


def build_target_key(prefix: str, stored_value: str) -> str:
    normalized = normalize_relative_path(stored_value)
    raw_name = os.path.basename(normalized)
    if not raw_name:
        raise ValueError("Nome de arquivo invalido no banco.")
    return "/".join([p for p in (prefix.strip("/"), "upload", raw_name) if p])


def object_exists(s3_client, bucket: str, key: str) -> bool:
    try:
        s3_client.head_object(Bucket=bucket, Key=key)
        return True
    except ClientError as exc:
        code = str(exc.response.get("Error", {}).get("Code", ""))
        if code in {"404", "NoSuchKey", "NotFound"}:
            return False
        raise


def fetch_db_filenames(connection, source: str) -> List[Tuple[str, str]]:
    queries = {
        SOURCE_GED: """
            SELECT 'ged' AS origem, CONVERT(TRIM(field_445) USING utf8mb4) COLLATE utf8mb4_general_ci AS arquivo
            FROM app_entity_43
            WHERE field_445 IS NOT NULL AND TRIM(field_445) <> ''
        """,
        SOURCE_SEFAZ_RH: """
            SELECT 'sefaz_rh' AS origem, CONVERT(TRIM(field_542) USING utf8mb4) COLLATE utf8mb4_general_ci AS arquivo
            FROM app_entity_49
            WHERE field_542 IS NOT NULL AND TRIM(field_542) <> ''
        """,
    }

    if source == SOURCE_ALL:
        sql = f"""
            SELECT origem, arquivo FROM (
                {queries[SOURCE_GED]}
                UNION ALL
                {queries[SOURCE_SEFAZ_RH]}
            ) src
            WHERE arquivo IS NOT NULL AND arquivo <> ''
            GROUP BY origem, arquivo
            ORDER BY origem, arquivo
        """
    else:
        sql = f"""
            SELECT origem, arquivo FROM (
                {queries[source]}
            ) src
            GROUP BY origem, arquivo
            ORDER BY origem, arquivo
        """

    with connection.cursor() as cur:
        cur.execute(sql)
        rows = cur.fetchall()

    result: List[Tuple[str, str]] = []
    for row in rows:
        origem, arquivo = row
        if arquivo:
            result.append((str(origem), str(arquivo)))
    return result


def parse_args(argv: Sequence[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Migra objetos R2/S3 com base nos registros do banco.")

    parser.add_argument("--endpoint", required=True, help="Endpoint R2/S3")
    parser.add_argument("--region", default="auto", help="Regiao R2/S3")
    parser.add_argument("--access-key", required=True, help="Access key R2/S3")
    parser.add_argument("--secret-key", required=True, help="Secret key R2/S3")
    parser.add_argument(
        "--from-access-key",
        default="",
        help="Access key da origem (opcional, usa --access-key quando vazio)",
    )
    parser.add_argument(
        "--from-secret-key",
        default="",
        help="Secret key da origem (opcional, usa --secret-key quando vazio)",
    )
    parser.add_argument(
        "--to-access-key",
        default="",
        help="Access key do destino (opcional, usa --access-key quando vazio)",
    )
    parser.add_argument(
        "--to-secret-key",
        default="",
        help="Secret key do destino (opcional, usa --secret-key quando vazio)",
    )
    parser.add_argument("--from-bucket", required=True, help="Bucket de origem")
    parser.add_argument("--to-bucket", required=True, help="Bucket de destino")
    parser.add_argument("--prefix", default="ged", help="Prefixo de objetos (ex.: ged)")

    parser.add_argument("--db-host", required=True)
    parser.add_argument("--db-port", type=int, default=3306)
    parser.add_argument("--db-user", required=True)
    parser.add_argument("--db-pass", required=True)
    parser.add_argument("--db-name", required=True)

    parser.add_argument("--source", choices=VALID_SOURCES, default=SOURCE_ALL, help="Origem no banco")
    parser.add_argument("--apply", action="store_true", help="Aplica copia real")
    parser.add_argument(
        "--delete-source",
        action="store_true",
        help="Apaga objeto da origem apos copiar (somente com --apply)",
    )

    return parser.parse_args(argv)


def main(argv: Sequence[str]) -> int:
    args = parse_args(argv)

    if args.delete_source and not args.apply:
        print("ERRO: --delete-source exige --apply.", file=sys.stderr)
        return 2

    from_access_key = args.from_access_key or args.access_key
    from_secret_key = args.from_secret_key or args.secret_key
    to_access_key = args.to_access_key or args.access_key
    to_secret_key = args.to_secret_key or args.secret_key

    source_s3_client = boto3.client(
        "s3",
        endpoint_url=args.endpoint,
        region_name=args.region,
        aws_access_key_id=from_access_key,
        aws_secret_access_key=from_secret_key,
        config=BotoConfig(signature_version="s3v4"),
    )

    dest_s3_client = boto3.client(
        "s3",
        endpoint_url=args.endpoint,
        region_name=args.region,
        aws_access_key_id=to_access_key,
        aws_secret_access_key=to_secret_key,
        config=BotoConfig(signature_version="s3v4"),
    )

    try:
        connection = pymysql.connect(
            host=args.db_host,
            port=args.db_port,
            user=args.db_user,
            password=args.db_pass,
            database=args.db_name,
            charset="utf8mb4",
            autocommit=True,
            cursorclass=pymysql.cursors.Cursor,
        )
    except Exception as exc:
        print(f"ERRO ao conectar no banco: {exc}", file=sys.stderr)
        return 2

    try:
        db_items = fetch_db_filenames(connection, args.source)
    finally:
        connection.close()

    total = len(db_items)
    copied = 0
    already_in_dest = 0
    missing_in_source = 0
    errors = 0

    print("=" * 90)
    print(f"Itens no banco: {total}")
    print(f"Origem: {args.from_bucket} | Destino: {args.to_bucket} | Prefixo: {args.prefix}")
    print(f"Fonte: {args.source} | Modo: {'APPLY' if args.apply else 'DRY-RUN'}")
    print("=" * 90)

    for idx, (origin, stored_value) in enumerate(db_items, start=1):
        try:
            target_key = build_target_key(args.prefix, stored_value)
            candidate_keys = build_candidate_keys(args.prefix, stored_value)

            if object_exists(dest_s3_client, args.to_bucket, target_key):
                already_in_dest += 1
                print(f"[{idx}/{total}] {origin} | ja existe no destino -> {stored_value}")
                continue

            source_key = None
            for key in candidate_keys:
                if object_exists(source_s3_client, args.from_bucket, key):
                    source_key = key
                    break

            if not source_key:
                missing_in_source += 1
                print(f"[{idx}/{total}] {origin} | NAO encontrado na origem -> {stored_value}")
                continue

            if not args.apply:
                copied += 1
                print(
                    f"[{idx}/{total}] {origin} | DRY-RUN copia: "
                    f"{args.from_bucket}/{source_key} -> {args.to_bucket}/{target_key}"
                )
                continue

            response = source_s3_client.get_object(Bucket=args.from_bucket, Key=source_key)
            body = response["Body"]
            try:
                dest_s3_client.put_object(Bucket=args.to_bucket, Key=target_key, Body=body)
            finally:
                body.close()

            if args.delete_source:
                source_s3_client.delete_object(Bucket=args.from_bucket, Key=source_key)

            copied += 1
            print(
                f"[{idx}/{total}] {origin} | COPIADO: "
                f"{args.from_bucket}/{source_key} -> {args.to_bucket}/{target_key}"
            )

        except (ClientError, BotoCoreError, ValueError, OSError) as exc:
            errors += 1
            print(f"[{idx}/{total}] {origin} | ERRO em {stored_value}: {exc}")

    print("-" * 90)
    print("Resumo")
    print(f"  total banco: {total}")
    print(f"  copiados (ou previstos no dry-run): {copied}")
    print(f"  ja existiam no destino: {already_in_dest}")
    print(f"  nao encontrados na origem: {missing_in_source}")
    print(f"  erros: {errors}")

    # Falha de execucao quando houve erro real em modo apply.
    if args.apply and errors > 0:
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
