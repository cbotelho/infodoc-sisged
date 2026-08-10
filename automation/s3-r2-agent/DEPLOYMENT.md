# Deployment Guide - Agente S3/R2

## Requisitos

- Docker + Docker Compose
- Credenciais Cloudflare R2 (endpoint, access key, secret key)
- Estrutura de pastas Tenant/Setor/Caixa já criada no servidor
- PostgreSQL ou SQLite (SQLite embutido)

## Instalação Passo a Passo

### 1. Clone ou copie o agente

```bash
cd /opt/infodoc-agent
git clone <repo> s3-r2-agent
cd s3-r2-agent
```

### 2. Configure o arquivo .env

```bash
cp .env.example .env
# Editar .env com suas credenciais
nano .env
```

Valores críticos a preencher:

```env
SERVER_FILES_ROOT=/opt/infodoc-agent/data
S3_ENDPOINT=https://accountid.r2.cloudflarestorage.com
S3_REGION=auto
S3_ACCESS_KEY_ID=<sua-chave>
S3_SECRET_ACCESS_KEY=<seu-secret>
S3_BUCKET_PREFIX=infodoc
```

### 3. Crie a estrutura de pastas (opcional)

```bash
docker compose run --rm s3-r2-agent-worker python scripts/bootstrap_tree.py
```

Se quiser fazer manualmente:

```bash
mkdir -p /opt/infodoc-agent/data/{TENANT}/{SETOR}/{CAIXA}/{Arquivos_P_Envio,Arquivos_Enviados,Arquivos_Nao_Enviados,Logs}
```

### 4. Subir os containers

```bash
docker compose up -d --build
```

### 5. Validar saúde

```bash
# Esperar 15 segundos pelos healthchecks
sleep 15

# Verificar status
docker compose ps

# Testar endpoint
curl http://localhost:18090/health

# Consultar fila
curl http://localhost:18090/stats
```

### 6. Colocar arquivo de teste

```bash
# Criar arquivo de teste
echo "test content" > /opt/infodoc-agent/data/TENANT/SETOR/CAIXA/Arquivos_P_Envio/test.txt

# Aguardar descoberta (30s padrão) + processamento
sleep 35

# Verificar sucesso
ls /opt/infodoc-agent/data/TENANT/SETOR/CAIXA/Arquivos_Enviados/

# Verificar fila
curl http://localhost:18090/stats
```

---

## Configurações Avançadas

### Alterar intervalo de varredura

```env
AGENT_POLL_INTERVAL_SECONDS=60  # Varrer a cada 60 segundos
```

### Aumentar batch de processamento

```env
AGENT_BATCH_SIZE=200  # Processar até 200 jobs por lote
```

### Ajustar retry

```env
AGENT_MAX_ATTEMPTS=10                # Até 10 tentativas
AGENT_BACKOFF_BASE_SECONDS=60        # Aguardar 60s na 1ª retry
AGENT_BACKOFF_MAX_SECONDS=3600       # Máximo de 1 hora
```

Fórmula de espera:
```
wait = min(3600, 60 * 2^(tentativa-1))
```

### Modo dry-run (teste)

```env
AGENT_DRY_RUN=true  # Simula upload sem enviar ao S3
```

### Restringir tenants

```env
AGENT_TENANT_ALLOWLIST=GEA,CIPEMAC  # Processar apenas estes
```

---

## Monitoramento com Prometheus

### Instalar exportador customizado (opcional)

Crie `prometheus-exporter.py`:

```python
from prometheus_client import start_http_server, Gauge
import requests
import time

queue_total = Gauge('agent_queue_total', 'Total jobs in queue')
queue_uploaded = Gauge('agent_queue_uploaded', 'Uploaded jobs')
queue_failed = Gauge('agent_queue_failed', 'Failed jobs')

def update_metrics():
    while True:
        try:
            resp = requests.get('http://localhost:18090/stats').json()
            queue_total.set(resp['total'])
            queue_uploaded.set(resp['uploaded'])
            queue_failed.set(resp['failed'])
        except:
            pass
        time.sleep(60)

if __name__ == '__main__':
    start_http_server(8888)
    update_metrics()
```

---

## Troubleshooting de Deploy

### Containers não iniciam

```bash
docker compose logs s3-r2-agent-worker
docker compose logs s3-r2-agent-api
```

### Permissão negada em /data

```bash
sudo chown -R 1000:1000 /opt/infodoc-agent/data
```

### Banco de dados corrompido

```bash
# Remover banco
rm /opt/infodoc-agent/data/.agent/queue.db

# Reiniciar
docker compose restart s3-r2-agent-worker s3-r2-agent-api
```

---

## Rollback

```bash
# Se precisar voltar para versão anterior
docker compose down
git checkout v1.0.0
docker compose up -d --build
```

---

## Performance Tuning

| Configuração | Recomendação |
|-------------|-------------|
| AGENT_POLL_INTERVAL_SECONDS | 30-60s (mais baixo = mais CPU) |
| AGENT_BATCH_SIZE | 50-500 (depende da velocidade do S3) |
| Memória do container | 512MB mínimo, 1GB recomendado |
| CPU | 0.5 cores mínimo, 1 core recomendado |

---

## Logs Estruturados

Todos os logs são salvos em:
```
/opt/infodoc-agent/data/{TENANT}/{SETOR}/{CAIXA}/Logs/upload-AAAA-MM-DD.log
```

Formato:
```
2026-06-23T15:30:45.123456 file=documento.pdf attempt=1 doc_type=fiscal
2026-06-23T15:30:50.654321 SUCCESS file=documento.pdf bucket=infodoc-gea key=GEA/SEFAZ_RH/CAIXA_001/documento.pdf
```
