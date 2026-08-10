# Runbook Operacional - Agente S3/R2 24/7

## Endpoints de Operação

### Health Check
```bash
curl http://localhost:18090/health
```
Retorna status da saúde, configurações e fila.

### Estatísticas da Fila
```bash
curl http://localhost:18090/stats
```
Retorna contagem de jobs por status.

### Listar Jobs (com filtro e paginação)
```bash
# Todos os jobs
curl http://localhost:18090/jobs?limit=50&offset=0

# Jobs por status
curl http://localhost:18090/jobs?status=FAILED&limit=50

# Jobs por tenant
curl http://localhost:18090/jobs?tenant=GEA&limit=50

# Jobs por tenant e setor
curl http://localhost:18090/jobs?tenant=GEA&setor=SEFAZ_RH&limit=50
```

### Histórico de Tentativas por Job
```bash
curl http://localhost:18090/jobs/123/attempts
```
Mostra todas as tentativas realizadas no arquivo (job_id=123).

### Disparar Varredura Manual
```bash
curl -X POST http://localhost:18090/scan-now
```
Executa scan imediato de Arquivos_P_Envio.

### Reprocessar Falhas (Dead-letter)
```bash
# Reprocessar últimos 100 falhos
curl -X POST "http://localhost:18090/requeue-failed?limit=100"
```
Move jobs de status FAILED para QUEUED (máximo de tentativas é resetado).

---

## Troubleshooting

### 1. Arquivos não saem de Arquivos_P_Envio

**Diagnóstico:**
1. Verificar fila: `curl http://localhost:18090/stats`
2. Verificar logs: `CAIXA/Logs/upload-AAAA-MM-DD.log`
3. Verificar se worker está rodando: `docker ps | grep s3-r2-agent-worker`

**Ações:**
- Se status está UPLOADING > 10 min: reiniciar worker `docker restart infodoc-s3-r2-agent-worker`
- Se status está RETRYING: aguardar backoff exponencial
- Se status está FAILED: chamar `/requeue-failed`

### 2. Muitos jobs em FAILED

**Diagnóstico:**
1. Consultar último erro: `curl http://localhost:18090/jobs?status=FAILED&limit=1`
2. Verificar tentativas: `curl http://localhost:18090/jobs/XXX/attempts` (com id do job)

**Possíveis causas:**
- Credenciais R2 inválidas: verificar `.env`
- Bucket não existe: criar bucket em Cloudflare
- Bucket incorreto para tenant: verificar S3_BUCKET_PREFIX e tenant

**Ações:**
- Corrigir credenciais em `.env` e reiniciar containers
- Reprocessar com `/requeue-failed` após correção

### 3. Arquivo continua retornando erro

**Diagnóstico:**
1. Listar tentativas: `curl http://localhost:18090/jobs/XXX/attempts`
2. Verificar hash do arquivo: `sha256sum ARQUIVO`
3. Procurar arquivo no R2 manualmente

**Possíveis causas:**
- Arquivo corrompido ou deletado no meio do upload
- Tamanho do arquivo maior que limite do S3 (5GB para PUT simples)
- MIME type bloqueado

**Ações:**
- Deletar arquivo de Arquivos_P_Envio
- Recolocar arquivo novo em Arquivos_P_Envio
- Aguardar nova descoberta (~30s)

---

## Monitoramento em Produção

### Alertas Recomendados

1. **Fila crescendo**: Se QUEUED + RETRYING > 1000, investigar delays
2. **Taxa de falha alta**: Se FAILED/total > 5%, revisar último erro
3. **Worker não responde**: Se health check falhar 3x seguidas, reiniciar
4. **Backlog antigo**: Se existe RETRYING com next_attempt_at > 1h atrás

### Métricas Importantes

```bash
# Verificar a cada 5 minutos
while true; do
  echo "$(date): $(curl -s http://localhost:18090/stats)"
  sleep 300
done
```

### Logs Centralizados

Coletar logs de todos os CAIXA/Logs/upload-*.log para sistema de logs centralizados (ELK, Splunk, etc).

---

## Procedimentos de Manutenção

### Rotação de Credenciais S3/R2

1. Gerar novas credenciais em Cloudflare
2. Atualizar `.env` com novas chaves
3. Reiniciar ambos os serviços:
   ```bash
   docker compose restart s3-r2-agent-worker s3-r2-agent-api
   ```
4. Verificar health: `curl http://localhost:18090/health`

### Limpeza de Banco de Dados

Remover jobs antigos (mantendo auditoria):

```bash
# Dentro do container API
docker exec infodoc-s3-r2-agent-api sqlite3 /data/.agent/queue.db \
  "DELETE FROM upload_jobs WHERE status='UPLOADED' AND updated_at < datetime('now', '-30 days');"
```

### Backup da Fila

```bash
docker exec infodoc-s3-r2-agent-api cp /data/.agent/queue.db /data/.agent/queue.db.backup.$(date +%Y%m%d)
```

### Restore de Backup

```bash
docker exec infodoc-s3-r2-agent-api cp /data/.agent/queue.db.backup.YYYYMMDD /data/.agent/queue.db
docker restart infodoc-s3-r2-agent-worker
```

---

## Escalação

| Problema | Contato | SLA |
|----------|---------|-----|
| Erro persistente em dedução de tenant | Backend | 2h |
| Bucket S3/R2 inacessível | Infra | 1h |
| Worker não responde | DevOps | 30min |
| Taxa de falha > 10% | Backend + Infra | 1h |

---

## Exemplos de Scripts Úteis

### Encontrar arquivo por caminho
```bash
curl "http://localhost:18090/jobs?limit=1000" | grep -i "meu_arquivo.pdf"
```

### Resetar todos os failed de um tenant
```bash
curl "http://localhost:18090/jobs?status=FAILED&tenant=GEA&limit=10000" | \
  jq '.jobs[].id' | \
  xargs -I {} curl -X POST "http://localhost:18090/requeue-failed?limit=1000"
```

### Exportar estatísticas
```bash
curl http://localhost:18090/stats | jq '.' > stats-$(date +%Y%m%d-%H%M%S).json
```
