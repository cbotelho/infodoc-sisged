# Agente Python 24/7 para envio S3/R2

Este agente monitora a arvore:

`PastaPai/Tenant/Setor/Caixa/Arquivos_P_Envio`

E movimenta automaticamente para:
- `Arquivos_Enviados` quando sucesso
- `Arquivos_Nao_Enviados` quando falha
- `Logs` para trilha de auditoria por caixa

Fila persistente em SQLite garante retomada apos reinicio do container.

## Estrutura esperada

```text
PastaPai/
  TENANT/
    SETOR/
      CAIXA/
        Arquivos_P_Envio/
        Arquivos_Enviados/
        Arquivos_Nao_Enviados/
        Logs/
```

## Componentes

- `app/worker.py`: loop continuo 24/7
- `app/api.py`: healthcheck e disparo manual de scan
- `app/queue_db.py`: fila persistente, estados, auditoria e retry
- `app/storage.py`: upload S3/R2 via boto3
- `app/tree.py`: regras da estrutura de pastas
- `app/ai_agent.py`: heuristica inicial de classificacao
- `app/checksum.py`: calcular SHA256 para deduplicacao

## Passo a passo de implantacao

1. Copie `.env.example` para `.env` e preencha credenciais.
2. Ajuste `SERVER_FILES_ROOT` para o caminho real da PastaPai no servidor.
3. Suba os containers:

```bash
docker compose up -d --build
```

4. Verifique saude:

```bash
curl http://localhost:18090/health
```

5. Forcar varredura manual:

```bash
curl -X POST http://localhost:18090/scan-now
```

6. Consultar estatisticas da fila:

```bash
curl http://localhost:18090/stats
```

7. Reprocessar falhas definitivas:

```bash
curl -X POST "http://localhost:18090/requeue-failed?limit=200"
```

8. Listar jobs com filtro e paginação:

```bash
# Todos os jobs
curl http://localhost:18090/jobs?limit=50&offset=0

# Jobs por status (QUEUED, UPLOADING, RETRYING, UPLOADED, FAILED)
curl http://localhost:18090/jobs?status=FAILED&limit=50

# Jobs por tenant
curl http://localhost:18090/jobs?tenant=GEA&limit=50

# Jobs por tenant e setor
curl http://localhost:18090/jobs?tenant=GEA&setor=SEFAZ_RH&limit=50
```

9. Ver histórico de tentativas de um arquivo:

```bash
curl http://localhost:18090/jobs/123/attempts
```

## Bootstrap da arvore (opcional)

O script abaixo cria exemplos de Tenant/Setor/Caixa no volume montado:

```bash
docker compose run --rm s3-r2-agent-worker python scripts/bootstrap_tree.py
```

## Fluxo operacional

1. Worker escaneia `Arquivos_P_Envio`.
2. Resolve `tenant/setor/caixa` pelo caminho.
3. Executa classificacao inicial (agente IA heuristico).
4. Calcula SHA256 para deduplicacao e auditoria.
5. Envia para S3/R2 (bucket por tenant).
6. Move arquivo para `Arquivos_Enviados` ou `Arquivos_Nao_Enviados`.
7. Escreve log local em `Logs/upload-AAAA-MM-DD.log`.
8. Registra histórico completo de tentativas no banco (auditoria).

## Estados da fila

- `QUEUED`: aguardando primeira tentativa
- `UPLOADING`: em processamento
- `RETRYING`: falhou e aguardando nova tentativa
- `UPLOADED`: sucesso definitivo
- `FAILED`: falha definitiva apos limite de tentativas

Backoff exponencial:

- espera = min(`AGENT_BACKOFF_MAX_SECONDS`, `AGENT_BACKOFF_BASE_SECONDS * 2^(tentativa-1)`)
- limite por arquivo: `AGENT_MAX_ATTEMPTS`
- histórico completo de tentativas em tabela `upload_attempts` para rastreabilidade

## Observacoes importantes

- Para manter padrao, use `Arquivos_Nao_Enviados` (sem acento).
- O bucket e resolvido por tenant para evitar mistura de dados.
- Em `AGENT_DRY_RUN=true`, simula upload e move para Enviados.
- Em producao, mantenha `restart: unless-stopped` e monitoramento externo.
- SHA256 por arquivo evita uploads duplicados em caso de concorrência.
- Auditoria completa: cada tentativa é registrada em `upload_attempts`.
- DB SQLite em volume compartilhado persiste entre reinícios.

**Documentação complementar:**
- [DEPLOYMENT.md](DEPLOYMENT.md) - Instalação, configuração e tuning
- [RUNBOOK.md](RUNBOOK.md) - Operação 24/7, troubleshooting e procedimentos
