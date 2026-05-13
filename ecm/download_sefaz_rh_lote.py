import argparse
import getpass
import os
import queue
import re
import sys
import threading
from pathlib import Path

import boto3
import pymysql
from botocore.config import Config as BotoConfig
from botocore.exceptions import BotoCoreError, ClientError
from dotenv import load_dotenv

try:
    import tkinter as tk
    from tkinter import filedialog, messagebox, ttk
except ImportError:
    tk = None
    filedialog = None
    messagebox = None
    ttk = None


ROOT_DIR = Path(__file__).resolve().parents[1]
CONFIG_DATABASE_PATH = ROOT_DIR / 'config' / 'database.php'
INCLUDES_DB_PATH = ROOT_DIR / 'includes' / 'db.php'
CHECKED = '☑'
UNCHECKED = '☐'

SOURCE_CONFIGS = {
    'ged': {
        'label': 'GED principal',
        'parent_table': 'app_entity_41',
        'document_table': 'app_entity_43',
        'numero_field': 'field_437',
        'file_field': 'field_445',
        'secretaria_field': 'field_433',
        'setor_field': 'field_434',
        'tipo_field': 'field_436',
        'folder': 'upload',
        'fixed_types': [('118', 'Caixa'), ('117', 'Pasta')],
        'document_columns': [
            ('arquivo', 'Arquivo', 260),
            ('campo1', 'Processo', 120),
            ('campo2', 'Interessado', 180),
            ('campo3', 'Assunto', 180),
            ('tipo_nome', 'Tipo Documento', 140),
            ('paginas', 'Páginas', 70),
        ],
    },
    'sefaz_rh': {
        'label': 'SEFAZ RH',
        'parent_table': 'app_entity_48',
        'document_table': 'app_entity_49',
        'numero_field': 'field_527',
        'file_field': 'field_542',
        'secretaria_field': 'field_524',
        'setor_field': 'field_525',
        'tipo_field': 'field_526',
        'folder': 'upload',
        'choices_field_id': 526,
        'document_columns': [
            ('arquivo', 'Arquivo', 260),
            ('campo1', 'Matrícula', 120),
            ('campo2', 'Interessado', 180),
            ('campo3', 'CPF', 120),
            ('tipo_nome', 'Tipo Acesso', 140),
            ('paginas', 'Páginas', 70),
            ('extra', 'Assunto/Número', 180),
        ],
    },
}


def parse_php_fallbacks(file_path):
    values = {}

    if not file_path.exists():
        return values

    content = file_path.read_text(encoding='utf-8', errors='ignore')

    define_map = {
        'DB_SERVER': 'DB_HOST',
        'DB_SERVER_PORT': 'DB_PORT',
        'DB_SERVER_USERNAME': 'DB_USER',
        'DB_SERVER_PASSWORD': 'DB_PASSWORD',
        'DB_DATABASE': 'DB_NAME',
    }

    for php_name, env_name in define_map.items():
        match = re.search(r"define\('%s',\s*\$?[A-Za-z_][A-Za-z0-9_]*\);" % re.escape(php_name), content)
        if match:
            continue

        match = re.search(r"define\('%s',\s*'([^']*)'\);" % re.escape(php_name), content)
        if match:
            values[env_name] = match.group(1)

    simple_vars = {
        r"\$host\s*=\s*'([^']*)'": 'DB_HOST',
        r"\$db\s*=\s*'([^']*)'": 'DB_NAME',
        r"\$user\s*=\s*'([^']*)'": 'DB_USER',
        r"\$pass\s*=\s*'([^']*)'": 'DB_PASSWORD',
        r"\$port\s*=\s*'([^']*)'": 'DB_PORT',
    }

    for pattern, env_name in simple_vars.items():
        match = re.search(pattern, content)
        if match:
            values[env_name] = match.group(1)

    return values


def get_setting(name, cli_value=None, default=''):
    if cli_value not in (None, ''):
        return str(cli_value).strip()

    env_value = os.getenv(name, '').strip()
    if env_value:
        return env_value

    fallback = PHP_FALLBACKS.get(name, '')
    return str(fallback).strip() if fallback else default


def prompt_value(label, current_value='', secret=False, required=True):
    while True:
        suffix = f' [{current_value}]' if current_value else ''
        if secret:
            entered = getpass.getpass(f'{label}{suffix}: ')
        else:
            entered = input(f'{label}{suffix}: ').strip()

        if entered:
            return entered

        if current_value:
            return current_value

        if not required:
            return ''

        print('Valor obrigatorio.')


def ensure_directory(path_value):
    destination = Path(path_value).expanduser().resolve()
    destination.mkdir(parents=True, exist_ok=True)
    return destination


def create_s3_client(endpoint, region, access_key_id, secret_access_key):
    return boto3.client(
        's3',
        endpoint_url=endpoint,
        region_name=region,
        aws_access_key_id=access_key_id,
        aws_secret_access_key=secret_access_key,
        config=BotoConfig(signature_version='s3v4'),
    )


def build_object_key(prefix, folder, filename):
    parts = [prefix.strip('/'), folder.strip('/'), os.path.basename(filename)]
    return '/'.join(part for part in parts if part)


def connect_db(host, port, user, password, database):
    return pymysql.connect(
        host=host,
        port=int(port or 3306),
        user=user,
        password=password,
        database=database,
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
    )


def fetch_secretarias(connection):
    query = 'SELECT id, field_232 AS nome FROM app_entity_26 ORDER BY field_232'
    with connection.cursor() as cursor:
        cursor.execute(query)
        return cursor.fetchall()


def fetch_setores(connection, secretaria_id):
    query = 'SELECT id, field_249 AS nome FROM app_entity_27 WHERE parent_item_id = %s ORDER BY field_249'
    with connection.cursor() as cursor:
        cursor.execute(query, (secretaria_id,))
        return cursor.fetchall()


def fetch_tipos(connection, source_key):
    source = SOURCE_CONFIGS[source_key]

    if 'fixed_types' in source:
        return [{'id': item_id, 'nome': item_name} for item_id, item_name in source['fixed_types']]

    query = (
        'SELECT id, name AS nome FROM app_fields_choices '
        'WHERE fields_id = %s AND name IN (\'Caixa\', \'Pasta\') '
        'ORDER BY FIELD(name, \'Caixa\', \'Pasta\')'
    )
    with connection.cursor() as cursor:
        cursor.execute(query, (source['choices_field_id'],))
        return cursor.fetchall()


def fetch_caixas_by_filters(connection, source_key, secretaria_id, setor_id, tipo_id):
    source = SOURCE_CONFIGS[source_key]
    conditions = [
        f"{source['secretaria_field']} = %s",
        f"{source['setor_field']} = %s",
    ]
    params = [secretaria_id, setor_id]

    if source_key == 'sefaz_rh' and tipo_id:
        query = (
            f"SELECT MAX(id) AS id, TRIM({source['numero_field']}) AS numero "
            f"FROM {source['parent_table']} "
            f"WHERE TRIM({source['numero_field']}) <> '' AND {' AND '.join(conditions)} AND {source['tipo_field']} = %s "
            f"GROUP BY {source['numero_field']} ORDER BY {source['numero_field']}"
        )
        with connection.cursor() as cursor:
            cursor.execute(query, params + [tipo_id])
            rows = cursor.fetchall()

        if rows:
            return rows

    if tipo_id:
        conditions.append(f"{source['tipo_field']} = %s")
        params.append(tipo_id)

    query = (
        f"SELECT MAX(id) AS id, TRIM({source['numero_field']}) AS numero "
        f"FROM {source['parent_table']} "
        f"WHERE TRIM({source['numero_field']}) <> '' AND {' AND '.join(conditions)} "
        f"GROUP BY {source['numero_field']} ORDER BY {source['numero_field']}"
    )
    with connection.cursor() as cursor:
        cursor.execute(query, params)
        return cursor.fetchall()


def fetch_documents_for_caixa(connection, source_key, caixa_id):
    source = SOURCE_CONFIGS[source_key]

    if source_key == 'ged':
        query = (
            'SELECT e.id, e.parent_item_id, e.field_445 AS arquivo, e.field_446 AS campo1, '
            'e.field_447 AS campo2, e.field_448 AS campo3, e.field_554 AS paginas, e.field_449 AS tipo_id, '
            'fc.name AS tipo_nome, e.field_450 AS extra '
            'FROM app_entity_43 e '
            'LEFT JOIN app_fields_choices fc ON fc.id = e.field_449 '
            'WHERE e.parent_item_id = %s '
            'ORDER BY e.id'
        )
    else:
        query = (
            'SELECT e.id, e.parent_item_id, e.field_542 AS arquivo, e.field_543 AS campo1, '
            'e.field_544 AS campo2, e.field_545 AS campo3, e.field_552 AS paginas, e.field_546 AS tipo_id, '
            'fc.name AS tipo_nome, e.field_548 AS extra '
            'FROM app_entity_49 e '
            'LEFT JOIN app_fields_choices fc ON fc.id = e.field_546 '
            'WHERE e.parent_item_id = %s '
            'ORDER BY e.id'
        )

    with connection.cursor() as cursor:
        cursor.execute(query, (caixa_id,))
        return cursor.fetchall()


def sanitize_folder_name(name):
    cleaned = re.sub(r'[<>:"/\\|?*]+', '_', str(name or '').strip())
    cleaned = re.sub(r'\s+', ' ', cleaned).strip(' .')
    return cleaned or 'caixa_sem_nome'


def unique_destination_path(base_path):
    if not base_path.exists():
        return base_path

    stem = base_path.stem
    suffix = base_path.suffix
    counter = 1

    while True:
        candidate = base_path.parent / f'{stem}__{counter}{suffix}'
        if not candidate.exists():
            return candidate
        counter += 1


def default_logger(message):
    print(message)


def download_selected_documents(settings, caixa, documents, progress_callback=None, logger=default_logger):
    source = SOURCE_CONFIGS[settings['source_key']]
    destination_root = ensure_directory(settings['destino'])
    caixa_numero = str(caixa.get('numero') or caixa['id'])
    caixa_dir = destination_root / sanitize_folder_name(f"caixa_{caixa_numero}_id_{caixa['id']}")
    caixa_dir.mkdir(parents=True, exist_ok=True)

    s3_client = create_s3_client(
        settings['r2_endpoint'],
        settings['r2_region'],
        settings['r2_access_key_id'],
        settings['r2_secret_access_key'],
    )

    total = len(documents)
    baixados = 0
    faltantes = []
    falhas = []

    for index, document in enumerate(documents, start=1):
        filename = os.path.basename(str(document.get('arquivo') or '').strip())
        if not filename:
            continue

        target_path = unique_destination_path(caixa_dir / filename)
        object_key = build_object_key(settings['r2_prefix'], source['folder'], filename)

        try:
            s3_client.download_file(settings['r2_bucket'], object_key, str(target_path))
            baixados += 1
            logger(f"[OK] {filename}")
        except ClientError as exc:
            error_code = exc.response.get('Error', {}).get('Code', '')
            if error_code in {'404', 'NoSuchKey', 'NotFound'}:
                faltantes.append({'arquivo': filename, 'key': object_key})
                logger(f"[FALTANTE] {filename}")
            else:
                falhas.append({'arquivo': filename, 'erro': str(exc)})
                logger(f"[ERRO] {filename}: {exc}")
        except (OSError, BotoCoreError) as exc:
            falhas.append({'arquivo': filename, 'erro': str(exc)})
            logger(f"[ERRO] {filename}: {exc}")

        if progress_callback:
            progress_callback(index, total, filename)

    return {
        'baixados': baixados,
        'faltantes': faltantes,
        'falhas': falhas,
        'destino': str(caixa_dir),
    }


def build_parser():
    parser = argparse.ArgumentParser(description='Ferramenta de download em massa GED / SEFAZ RH com GUI.')
    parser.add_argument('--mode', choices=['gui', 'cli'], default='gui', help='Modo de execução.')
    parser.add_argument('--source', choices=sorted(SOURCE_CONFIGS.keys()), default='sefaz_rh')
    parser.add_argument('--secretaria-id')
    parser.add_argument('--setor-id')
    parser.add_argument('--tipo-id')
    parser.add_argument('--caixa-id')
    parser.add_argument('--destino')
    parser.add_argument('--db-host')
    parser.add_argument('--db-port')
    parser.add_argument('--db-user')
    parser.add_argument('--db-password')
    parser.add_argument('--db-name')
    parser.add_argument('--r2-endpoint')
    parser.add_argument('--r2-region', default=None)
    parser.add_argument('--r2-bucket')
    parser.add_argument('--r2-access-key-id')
    parser.add_argument('--r2-secret-access-key')
    parser.add_argument('--r2-prefix', default=None)
    parser.add_argument('--listar', action='store_true')
    return parser


def collect_cli_settings(args):
    return {
        'source_key': args.source,
        'secretaria_id': prompt_value('Secretaria ID', args.secretaria_id or ''),
        'setor_id': prompt_value('Setor ID', args.setor_id or ''),
        'tipo_id': prompt_value('Tipo ID', args.tipo_id or '', required=False),
        'caixa_id': prompt_value('Caixa/Pasta ID', args.caixa_id or ''),
        'destino': prompt_value('Pasta de destino', args.destino or ''),
        'db_host': prompt_value('DB host', get_setting('DB_HOST', args.db_host)),
        'db_port': prompt_value('DB port', get_setting('DB_PORT', args.db_port, '3306')),
        'db_user': prompt_value('DB user', get_setting('DB_USER', args.db_user)),
        'db_password': prompt_value('DB password', get_setting('DB_PASSWORD', args.db_password), secret=True),
        'db_name': prompt_value('DB name', get_setting('DB_NAME', args.db_name)),
        'r2_endpoint': prompt_value('R2 endpoint', get_setting('FILE_STORAGE_R2_ENDPOINT', args.r2_endpoint)),
        'r2_region': prompt_value('R2 region', get_setting('FILE_STORAGE_R2_REGION', args.r2_region, 'auto')),
        'r2_bucket': prompt_value('R2 bucket', get_setting('FILE_STORAGE_R2_BUCKET', args.r2_bucket)),
        'r2_access_key_id': prompt_value('R2 access key id', get_setting('FILE_STORAGE_R2_ACCESS_KEY_ID', args.r2_access_key_id)),
        'r2_secret_access_key': prompt_value('R2 secret access key', get_setting('FILE_STORAGE_R2_SECRET_ACCESS_KEY', args.r2_secret_access_key), secret=True),
        'r2_prefix': prompt_value('R2 prefix', get_setting('FILE_STORAGE_R2_OBJECT_PREFIX', args.r2_prefix, 'ged')),
        'listar': args.listar,
    }


def run_cli(settings):
    connection = connect_db(settings['db_host'], settings['db_port'], settings['db_user'], settings['db_password'], settings['db_name'])
    try:
        caixas = fetch_caixas_by_filters(connection, settings['source_key'], settings['secretaria_id'], settings['setor_id'], settings['tipo_id'])
        caixa = next((item for item in caixas if str(item['id']) == str(settings['caixa_id'])), None)
        if not caixa:
            raise ValueError('Caixa/Pasta não encontrada para os filtros informados.')
        documentos = fetch_documents_for_caixa(connection, settings['source_key'], caixa['id'])
    finally:
        connection.close()

    if settings['listar']:
        for documento in documentos:
            print(documento['arquivo'])
        return 0

    resultado = download_selected_documents(settings, caixa, documentos)
    print(resultado)
    return 0 if not resultado['falhas'] else 2


class DownloadApp:
    def __init__(self, root):
        self.root = root
        self.root.title('Download em massa GED / SEFAZ RH')
        self.root.geometry('1220x840')
        self.message_queue = queue.Queue()
        self.worker = None
        self.current_caixas = []
        self.current_documents = []
        self.selected_document_ids = set()
        self.current_caixa = None

        self.source_var = tk.StringVar(value='sefaz_rh')
        self.secretaria_var = tk.StringVar()
        self.setor_var = tk.StringVar()
        self.tipo_var = tk.StringVar()
        self.caixa_var = tk.StringVar()
        self.destino_var = tk.StringVar()
        self.db_host_var = tk.StringVar(value=get_setting('DB_HOST'))
        self.db_port_var = tk.StringVar(value=get_setting('DB_PORT', default='3306'))
        self.db_user_var = tk.StringVar(value=get_setting('DB_USER'))
        self.db_password_var = tk.StringVar(value=get_setting('DB_PASSWORD'))
        self.db_name_var = tk.StringVar(value=get_setting('DB_NAME'))
        self.r2_endpoint_var = tk.StringVar(value=get_setting('FILE_STORAGE_R2_ENDPOINT'))
        self.r2_region_var = tk.StringVar(value=get_setting('FILE_STORAGE_R2_REGION', default='auto'))
        self.r2_bucket_var = tk.StringVar(value=get_setting('FILE_STORAGE_R2_BUCKET'))
        self.r2_access_key_id_var = tk.StringVar(value=get_setting('FILE_STORAGE_R2_ACCESS_KEY_ID'))
        self.r2_secret_access_key_var = tk.StringVar(value=get_setting('FILE_STORAGE_R2_SECRET_ACCESS_KEY'))
        self.r2_prefix_var = tk.StringVar(value=get_setting('FILE_STORAGE_R2_OBJECT_PREFIX', default='ged'))
        self.progress_var = tk.DoubleVar(value=0)
        self.progress_text_var = tk.StringVar(value='Pronto.')

        self.secretarias_map = {}
        self.setores_map = {}
        self.tipos_map = {}
        self.caixas_map = {}

        self._build_ui()
        self._bind_events()
        self.root.after(120, self._poll_messages)
        self._load_initial_data()

    def _build_ui(self):
        container = ttk.Frame(self.root, padding=12)
        container.pack(fill='both', expand=True)
        container.columnconfigure(1, weight=1)
        container.rowconfigure(7, weight=1)

        filters = ttk.LabelFrame(container, text='Filtros')
        filters.grid(row=0, column=0, columnspan=3, sticky='ew')
        for col in range(6):
            filters.columnconfigure(col, weight=1)

        ttk.Label(filters, text='Fluxo').grid(row=0, column=0, sticky='w', padx=6, pady=6)
        self.source_combo = ttk.Combobox(filters, textvariable=self.source_var, state='readonly', values=list(SOURCE_CONFIGS.keys()))
        self.source_combo.grid(row=1, column=0, sticky='ew', padx=6, pady=6)

        ttk.Label(filters, text='Secretaria').grid(row=0, column=1, sticky='w', padx=6, pady=6)
        self.secretaria_combo = ttk.Combobox(filters, textvariable=self.secretaria_var, state='readonly')
        self.secretaria_combo.grid(row=1, column=1, sticky='ew', padx=6, pady=6)

        ttk.Label(filters, text='Setor').grid(row=0, column=2, sticky='w', padx=6, pady=6)
        self.setor_combo = ttk.Combobox(filters, textvariable=self.setor_var, state='readonly')
        self.setor_combo.grid(row=1, column=2, sticky='ew', padx=6, pady=6)

        ttk.Label(filters, text='Tipo').grid(row=0, column=3, sticky='w', padx=6, pady=6)
        self.tipo_combo = ttk.Combobox(filters, textvariable=self.tipo_var, state='readonly')
        self.tipo_combo.grid(row=1, column=3, sticky='ew', padx=6, pady=6)

        ttk.Label(filters, text='Caixa/Pasta').grid(row=0, column=4, sticky='w', padx=6, pady=6)
        self.caixa_combo = ttk.Combobox(filters, textvariable=self.caixa_var, state='readonly')
        self.caixa_combo.grid(row=1, column=4, sticky='ew', padx=6, pady=6)

        ttk.Button(filters, text='Carregar arquivos', command=self._load_documents).grid(row=1, column=5, sticky='ew', padx=6, pady=6)

        destination_frame = ttk.LabelFrame(container, text='Destino do download')
        destination_frame.grid(row=1, column=0, columnspan=3, sticky='ew', pady=(10, 0))
        destination_frame.columnconfigure(0, weight=1)
        ttk.Entry(destination_frame, textvariable=self.destino_var).grid(row=0, column=0, sticky='ew', padx=6, pady=6)
        ttk.Button(destination_frame, text='Escolher...', command=self._choose_destination).grid(row=0, column=1, padx=6, pady=6)

        credentials = ttk.LabelFrame(container, text='Conexões')
        credentials.grid(row=2, column=0, columnspan=3, sticky='ew', pady=(10, 0))
        for col in range(4):
            credentials.columnconfigure(col, weight=1)
        self._credential_entry(credentials, 'DB host', self.db_host_var, 0, 0)
        self._credential_entry(credentials, 'DB port', self.db_port_var, 0, 1)
        self._credential_entry(credentials, 'DB user', self.db_user_var, 0, 2)
        self._credential_entry(credentials, 'DB password', self.db_password_var, 0, 3, show='*')
        self._credential_entry(credentials, 'DB name', self.db_name_var, 2, 0)
        self._credential_entry(credentials, 'R2 endpoint', self.r2_endpoint_var, 2, 1)
        self._credential_entry(credentials, 'R2 region', self.r2_region_var, 2, 2)
        self._credential_entry(credentials, 'R2 bucket', self.r2_bucket_var, 2, 3)
        self._credential_entry(credentials, 'R2 access key id', self.r2_access_key_id_var, 4, 0)
        self._credential_entry(credentials, 'R2 secret access key', self.r2_secret_access_key_var, 4, 1, show='*')
        self._credential_entry(credentials, 'R2 prefix', self.r2_prefix_var, 4, 2)

        actions = ttk.Frame(container)
        actions.grid(row=3, column=0, columnspan=3, sticky='ew', pady=(10, 0))
        self.select_all_button = ttk.Button(actions, text='Selecionar todos', command=self._select_all_documents)
        self.select_all_button.pack(side='left')
        self.clear_selection_button = ttk.Button(actions, text='Limpar seleção', command=self._clear_selection)
        self.clear_selection_button.pack(side='left', padx=(8, 0))
        self.download_button = ttk.Button(actions, text='Baixar selecionados', command=self._start_download)
        self.download_button.pack(side='left', padx=(8, 0))

        progress_frame = ttk.Frame(container)
        progress_frame.grid(row=4, column=0, columnspan=3, sticky='ew', pady=(10, 0))
        progress_frame.columnconfigure(0, weight=1)
        ttk.Progressbar(progress_frame, variable=self.progress_var, maximum=100).grid(row=0, column=0, sticky='ew')
        ttk.Label(progress_frame, textvariable=self.progress_text_var).grid(row=1, column=0, sticky='w', pady=(4, 0))

        grid_frame = ttk.LabelFrame(container, text='Arquivos encontrados')
        grid_frame.grid(row=7, column=0, columnspan=3, sticky='nsew', pady=(10, 0))
        grid_frame.rowconfigure(0, weight=1)
        grid_frame.columnconfigure(0, weight=1)

        columns = ('selecionado', 'arquivo', 'campo1', 'campo2', 'campo3', 'tipo_nome', 'paginas', 'extra')
        self.tree = ttk.Treeview(grid_frame, columns=columns, show='headings', selectmode='browse')
        self.tree.grid(row=0, column=0, sticky='nsew')
        scrollbar = ttk.Scrollbar(grid_frame, orient='vertical', command=self.tree.yview)
        scrollbar.grid(row=0, column=1, sticky='ns')
        self.tree.configure(yscrollcommand=scrollbar.set)

        headings = {
            'selecionado': ('Sel.', 50),
            'arquivo': ('Arquivo', 280),
            'campo1': ('Campo 1', 130),
            'campo2': ('Campo 2', 180),
            'campo3': ('Campo 3', 150),
            'tipo_nome': ('Tipo', 140),
            'paginas': ('Páginas', 80),
            'extra': ('Extra', 180),
        }
        for key, (title, width) in headings.items():
            self.tree.heading(key, text=title)
            self.tree.column(key, width=width, anchor='w')

        log_frame = ttk.LabelFrame(container, text='Log')
        log_frame.grid(row=8, column=0, columnspan=3, sticky='nsew', pady=(10, 0))
        log_frame.rowconfigure(0, weight=1)
        log_frame.columnconfigure(0, weight=1)
        self.log_widget = tk.Text(log_frame, height=10, wrap='word')
        self.log_widget.grid(row=0, column=0, sticky='nsew')
        log_scroll = ttk.Scrollbar(log_frame, orient='vertical', command=self.log_widget.yview)
        log_scroll.grid(row=0, column=1, sticky='ns')
        self.log_widget.configure(yscrollcommand=log_scroll.set)

    def _credential_entry(self, parent, label, variable, row, column, show=None):
        ttk.Label(parent, text=label).grid(row=row, column=column, sticky='w', padx=6, pady=(6, 0))
        ttk.Entry(parent, textvariable=variable, show=show).grid(row=row + 1, column=column, sticky='ew', padx=6, pady=(0, 6))

    def _bind_events(self):
        self.source_combo.bind('<<ComboboxSelected>>', lambda _event: self._load_initial_data())
        self.secretaria_combo.bind('<<ComboboxSelected>>', lambda _event: self._on_secretaria_change())
        self.setor_combo.bind('<<ComboboxSelected>>', lambda _event: self._on_setor_or_tipo_change())
        self.tipo_combo.bind('<<ComboboxSelected>>', lambda _event: self._on_setor_or_tipo_change())
        self.caixa_combo.bind('<<ComboboxSelected>>', lambda _event: self._reset_documents())
        self.tree.bind('<Button-1>', self._handle_tree_click)
        self.tree.bind('<Double-1>', self._handle_tree_click)

    def _choose_destination(self):
        selected = filedialog.askdirectory() if filedialog else ''
        if selected:
            self.destino_var.set(selected)

    def _append_log(self, message):
        self.log_widget.insert('end', message + '\n')
        self.log_widget.see('end')

    def _poll_messages(self):
        try:
            while True:
                event_type, payload = self.message_queue.get_nowait()
                if event_type == 'log':
                    self._append_log(payload)
                elif event_type == 'progress':
                    current, total, filename = payload
                    percent = (current / total) * 100 if total else 0
                    self.progress_var.set(percent)
                    self.progress_text_var.set(f'Baixando {current}/{total}: {filename}')
                elif event_type == 'done':
                    self.download_button.configure(state='normal')
                    self.progress_var.set(100)
                    self.progress_text_var.set(f"Concluído. Baixados: {payload['baixados']}")
                    self._append_log('Download concluído.')
                    messagebox.showinfo('Concluído', f"Baixados: {payload['baixados']}\nDestino: {payload['destino']}")
                elif event_type == 'error':
                    self.download_button.configure(state='normal')
                    self.progress_text_var.set('Erro durante o download.')
                    self._append_log('[ERRO] ' + payload)
                    messagebox.showerror('Erro', payload)
        except queue.Empty:
            pass

        self.root.after(120, self._poll_messages)

    def _build_connection(self):
        return connect_db(
            self.db_host_var.get().strip(),
            self.db_port_var.get().strip(),
            self.db_user_var.get().strip(),
            self.db_password_var.get(),
            self.db_name_var.get().strip(),
        )

    def _load_initial_data(self):
        self._reset_filters(clear_secretaria=False)
        self._reset_documents()
        self.current_caixas = []
        self.current_documents = []
        self.current_caixa = None

        connection = self._build_connection()
        try:
            secretarias = fetch_secretarias(connection)
            tipos = fetch_tipos(connection, self.source_var.get())
        finally:
            connection.close()

        self.secretarias_map = {str(item['id']): item for item in secretarias}
        self.tipos_map = {str(item['id']): item for item in tipos}

        self.secretaria_combo['values'] = [f"{item['id']} - {item['nome']}" for item in secretarias]
        self.tipo_combo['values'] = [f"{item['id']} - {item['nome']}" for item in tipos]
        self.secretaria_var.set('')
        self.tipo_var.set('')
        self.progress_text_var.set('Pronto para carregar os filtros.')

    def _on_secretaria_change(self):
        secretaria_id = self._extract_selected_id(self.secretaria_var.get())
        self._reset_filters(clear_secretaria=True)
        self._reset_documents()
        if not secretaria_id:
            return

        connection = self._build_connection()
        try:
            setores = fetch_setores(connection, secretaria_id)
        finally:
            connection.close()

        self.setores_map = {str(item['id']): item for item in setores}
        self.setor_combo['values'] = [f"{item['id']} - {item['nome']}" for item in setores]

    def _on_setor_or_tipo_change(self):
        self._load_caixas()
        self._reset_documents()

    def _load_caixas(self):
        secretaria_id = self._extract_selected_id(self.secretaria_var.get())
        setor_id = self._extract_selected_id(self.setor_var.get())
        tipo_id = self._extract_selected_id(self.tipo_var.get())

        self.caixa_combo['values'] = []
        self.caixa_var.set('')
        self.current_caixas = []
        self.caixas_map = {}

        if not secretaria_id or not setor_id:
            return

        if self.source_var.get() == 'ged' and not tipo_id:
            return
        if self.source_var.get() == 'sefaz_rh' and not tipo_id:
            return

        connection = self._build_connection()
        try:
            caixas = fetch_caixas_by_filters(connection, self.source_var.get(), secretaria_id, setor_id, tipo_id)
        finally:
            connection.close()

        self.current_caixas = caixas
        self.caixas_map = {str(item['id']): item for item in caixas}
        self.caixa_combo['values'] = [f"{item['id']} - {item['numero']}" for item in caixas]

    def _load_documents(self):
        caixa_id = self._extract_selected_id(self.caixa_var.get())
        if not caixa_id:
            messagebox.showerror('Validação', 'Selecione uma Caixa/Pasta.')
            return

        connection = self._build_connection()
        try:
            documentos = fetch_documents_for_caixa(connection, self.source_var.get(), caixa_id)
        finally:
            connection.close()

        self.current_caixa = self.caixas_map.get(str(caixa_id))
        self.current_documents = documentos
        self.selected_document_ids = set()
        self.tree.delete(*self.tree.get_children())

        for documento in documentos:
            values = self._document_values(documento)
            self.tree.insert('', 'end', iid=str(documento['id']), values=values)

        self.progress_text_var.set(f'{len(documentos)} arquivo(s) carregado(s).')
        self._append_log(f"Arquivos carregados para a caixa {self.current_caixa.get('numero') if self.current_caixa else caixa_id}.")

    def _document_values(self, documento):
        source = SOURCE_CONFIGS[self.source_var.get()]
        if source['label'] == 'GED principal':
            return (
                UNCHECKED,
                documento.get('arquivo') or '',
                documento.get('campo1') or '',
                documento.get('campo2') or '',
                documento.get('campo3') or '',
                documento.get('tipo_nome') or str(documento.get('tipo_id') or ''),
                documento.get('paginas') or 0,
                documento.get('extra') or '',
            )

        return (
            UNCHECKED,
            documento.get('arquivo') or '',
            documento.get('campo1') or '',
            documento.get('campo2') or '',
            documento.get('campo3') or '',
            documento.get('tipo_nome') or str(documento.get('tipo_id') or ''),
            documento.get('paginas') or 0,
            documento.get('extra') or '',
        )

    def _handle_tree_click(self, event):
        item_id = self.tree.identify_row(event.y)
        column = self.tree.identify_column(event.x)
        if not item_id or column != '#1':
            return
        self._toggle_document(item_id)
        return 'break'

    def _toggle_document(self, item_id):
        current_values = list(self.tree.item(item_id, 'values'))
        if item_id in self.selected_document_ids:
            self.selected_document_ids.remove(item_id)
            current_values[0] = UNCHECKED
        else:
            self.selected_document_ids.add(item_id)
            current_values[0] = CHECKED
        self.tree.item(item_id, values=current_values)
        self.progress_text_var.set(f'{len(self.selected_document_ids)} arquivo(s) selecionado(s).')

    def _select_all_documents(self):
        self.selected_document_ids = {str(doc['id']) for doc in self.current_documents}
        for documento in self.current_documents:
            item_id = str(documento['id'])
            values = list(self.tree.item(item_id, 'values'))
            values[0] = CHECKED
            self.tree.item(item_id, values=values)
        self.progress_text_var.set(f'{len(self.selected_document_ids)} arquivo(s) selecionado(s).')

    def _clear_selection(self):
        self.selected_document_ids.clear()
        for documento in self.current_documents:
            item_id = str(documento['id'])
            values = list(self.tree.item(item_id, 'values'))
            values[0] = UNCHECKED
            self.tree.item(item_id, values=values)
        self.progress_text_var.set('Seleção limpa.')

    def _start_download(self):
        if self.worker and self.worker.is_alive():
            return
        if not self.current_caixa:
            messagebox.showerror('Validação', 'Carregue os arquivos de uma Caixa/Pasta antes de baixar.')
            return
        if not self.selected_document_ids:
            messagebox.showerror('Validação', 'Selecione ao menos um arquivo na grade.')
            return

        settings = self._build_download_settings()
        selected_documents = [doc for doc in self.current_documents if str(doc['id']) in self.selected_document_ids]

        self.download_button.configure(state='disabled')
        self.progress_var.set(0)
        self.progress_text_var.set('Iniciando download...')
        self.worker = threading.Thread(target=self._run_download_worker, args=(settings, self.current_caixa, selected_documents), daemon=True)
        self.worker.start()

    def _build_download_settings(self):
        return {
            'source_key': self.source_var.get().strip(),
            'destino': self.destino_var.get().strip(),
            'r2_endpoint': self.r2_endpoint_var.get().strip(),
            'r2_region': self.r2_region_var.get().strip(),
            'r2_bucket': self.r2_bucket_var.get().strip(),
            'r2_access_key_id': self.r2_access_key_id_var.get().strip(),
            'r2_secret_access_key': self.r2_secret_access_key_var.get(),
            'r2_prefix': self.r2_prefix_var.get().strip(),
        }

    def _run_download_worker(self, settings, caixa, documents):
        def logger(message):
            self.message_queue.put(('log', message))

        def progress_callback(current, total, filename):
            self.message_queue.put(('progress', (current, total, filename)))

        try:
            resultado = download_selected_documents(settings, caixa, documents, progress_callback=progress_callback, logger=logger)
            self.message_queue.put(('done', resultado))
        except Exception as exc:
            self.message_queue.put(('error', str(exc)))

    def _extract_selected_id(self, value):
        value = str(value or '').strip()
        if not value:
            return ''
        return value.split(' - ', 1)[0].strip()

    def _reset_filters(self, clear_secretaria):
        self.setor_combo['values'] = []
        self.caixa_combo['values'] = []
        self.setor_var.set('')
        self.caixa_var.set('')
        self.setores_map = {}
        self.caixas_map = {}
        if clear_secretaria:
            self.secretaria_var.set(self.secretaria_var.get())

    def _reset_documents(self):
        self.current_documents = []
        self.selected_document_ids.clear()
        self.current_caixa = None
        self.tree.delete(*self.tree.get_children())


def run_gui():
    if tk is None:
        raise RuntimeError('Tkinter não está disponível neste Python. Use --mode cli.')
    root = tk.Tk()
    DownloadApp(root)
    root.mainloop()


def main():
    parser = build_parser()
    args = parser.parse_args()
    if args.mode == 'gui':
        run_gui()
        return 0
    settings = collect_cli_settings(args)
    return run_cli(settings)


load_dotenv(ROOT_DIR / '.env')
load_dotenv(ROOT_DIR / '.env.production.portainer.example')
PHP_FALLBACKS = parse_php_fallbacks(CONFIG_DATABASE_PATH)
PHP_FALLBACKS.update(parse_php_fallbacks(INCLUDES_DB_PATH))


if __name__ == '__main__':
    sys.exit(main())
