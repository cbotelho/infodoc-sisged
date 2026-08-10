# Checklist Pré-Produção - Agente S3/R2

Utilize este checklist antes de ativar o agente em produção.

## ✅ Pré-requisitos Técnicos

- [ ] Docker + Docker Compose instalados no servidor
- [ ] Estrutura Tenant/Setor/Caixa criada em `/opt/infodoc-agent/data`
- [ ] Permissões de leitura/escrita confirmadas no diretório
- [ ] Credenciais Cloudflare R2 obtidas
- [ ] Buckets S3/R2 criados para cada tenant

## ✅ Configuração

- [ ] Arquivo `.env` criado (cp .env.example .env)
- [ ] `SERVER_FILES_ROOT` aponta para caminho correto
- [ ] `S3_ENDPOINT` preenchido
- [ ] `S3_ACCESS_KEY_ID` preenchido
- [ ] `S3_SECRET_ACCESS_KEY` preenchido
- [ ] `S3_BUCKET_PREFIX` preenchido (recomendado: `infodoc`)
- [ ] `AGENT_ROOT_PATH` = `/data`
- [ ] `AGENT_MAX_ATTEMPTS` = 5 (ajustar se necessário)
- [ ] `AGENT_POLL_INTERVAL_SECONDS` = 30 (ajustar para carga)
- [ ] `AGENT_TENANT_ALLOWLIST` preenchido com tenants em produção

## ✅ Build e Deploy

- [ ] `docker compose build` executado sem erros
- [ ] `docker compose up -d` executado
- [ ] `docker compose ps` mostra 2 serviços (worker + api) em estado `Up`
- [ ] Ambos os serviços têm healthcheck `healthy`

## ✅ Validação Funcional

- [ ] `curl http://localhost:18090/health` retorna status ok
- [ ] `curl http://localhost:18090/stats` retorna total=0 (fila vazia inicial)
- [ ] Arquivo de teste criado em `{TENANT}/{SETOR}/{CAIXA}/Arquivos_P_Envio/`
- [ ] Aguardado 35 segundos (discovery 30s + processing time)
- [ ] Arquivo movido para `Arquivos_Enviados`
- [ ] `curl http://localhost:18090/stats` retorna uploaded=1
- [ ] Log gerado em `{TENANT}/{SETOR}/{CAIXA}/Logs/upload-AAAA-MM-DD.log`

## ✅ Teste de Falha

- [ ] Credencial S3 desabilitada intencionalmente
- [ ] Arquivo colocado em `Arquivos_P_Envio`
- [ ] Aguardado 35 segundos
- [ ] Arquivo permanece em `Arquivos_P_Envio` (retry agendado)
- [ ] `curl http://localhost:18090/stats` mostra retrying > 0
- [ ] Credencial restaurada
- [ ] Aguardado intervalo de retry (60-120s segundo backoff)
- [ ] Arquivo finalmente moved para `Arquivos_Enviados`

## ✅ Operação

- [ ] API endpoints documentados em equipe de operação
- [ ] Runbook (RUNBOOK.md) distribuído para on-call
- [ ] Alertas configurados em sistema de monitoramento (opcional)
- [ ] Escalonamento documentado em caso de falha

## ✅ Backup e Recuperação

- [ ] Backup manual do banco realizado: `docker exec s3-r2-agent-api cp /data/.agent/queue.db /data/.agent/queue.db.bak`
- [ ] Procedimento de restore testado
- [ ] Arquivo de backup armazenado em local seguro

## ✅ Monitoramento

- [ ] Health check configurado em sistema externo (opcional)
- [ ] Logs sendo coletados em sistema centralizado (opcional)
- [ ] Métricas sendo exportadas (opcional)
- [ ] Escalação documentada

## ✅ Documentação

- [ ] README.md lido e compreendido
- [ ] DEPLOYMENT.md consultado para tuning necessário
- [ ] RUNBOOK.md disponível para equipe de operação
- [ ] Endpoints da API documentados em wiki interna

## ✅ Performance

- [ ] Teste de carga: 1000 arquivos colocados em `Arquivos_P_Envio`
- [ ] Observado: processamento, memória, CPU
- [ ] Ajustados se necessário:
  - `AGENT_POLL_INTERVAL_SECONDS` (menor = mais CPU)
  - `AGENT_BATCH_SIZE` (maior = mais memória)
- [ ] Performance aceitável para SLA

## ✅ Segurança

- [ ] Credenciais não commitadas no git (usar .env)
- [ ] Arquivo .env adicionado ao .gitignore
- [ ] Backup de credenciais armazenado em local seguro (Vault, 1Password, etc)
- [ ] Acesso ao volume de dados restrito (permissões de arquivo)
- [ ] Logs não contêm segredos (verificado RUNBOOK.md)

## ✅ Escalação

- [ ] Documentação de contatos de escalação preenchida
- [ ] SLA definido para tempo de reprocessamento
- [ ] Procedimento de requeue-failed documentado
- [ ] Runbook de troubleshooting acessível 24/7

---

## Checklist de Go-Live

**Data:** ___________________

**Responsável:** ___________________

**Assinatura:** ___________________

- [ ] Todos os itens acima foram verificados ✓
- [ ] Nenhum erro em testes funcionais
- [ ] Equipe de operação foi treinada
- [ ] Runbook está disponível
- [ ] Escalação está configurada

**Status:** 🟢 APROVADO PARA PRODUÇÃO

---

## Notas Adicionais

```
_______________________________________________________________

_______________________________________________________________

_______________________________________________________________
```
