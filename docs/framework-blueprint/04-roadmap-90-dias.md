# 04 - Roadmap de 90 Dias

## Objetivo
Executar o framework no-code/low-code em 90 dias com entregas incrementais, priorizando núcleo estável, segurança e capacidade de gerar aplicações por entidades.

## Estratégia
1. Entregar um produto funcional por fases.
2. Reduzir risco com milestones validáveis.
3. Evitar big bang de arquitetura.

## Fase 1 (Dias 1-30): Núcleo Builder + Runtime básico

### Escopo
1. Multi-tenant básico (workspace)
2. Auth e RBAC inicial
3. Builder de entidades e campos
4. Runtime CRUD dinâmico
5. Auditoria básica de alterações

### Entregáveis
1. API v1 de apps, entidades e campos
2. API v1 de registros dinâmicos
3. UI Builder inicial (criar entidade/campo)
4. UI Runtime inicial (lista + formulário dinâmico)

### Critérios de aceite
1. Criar app de Contas a Pagar sem código de domínio.
2. Criar 3 entidades com relacionamento simples.
3. CRUD completo operando com permissão por papel.

### Riscos e mitigação
1. Complexidade de validação dinâmica.
- Mitigação: engine de validação com tipos limitados na v1.

2. Performance de listagem.
- Mitigação: índices padrão e paginação obrigatória.

## Fase 2 (Dias 31-60): Permissão fina + automações + arquivos

### Escopo
1. Permissão por campo (read/write)
2. Automações por trigger
3. Gestão de anexos S3
4. Publicação de versão de metadados

### Entregáveis
1. API de permissões por entidade e campo
2. API de automações (trigger + ações)
3. Worker assíncrono para ações
4. Upload/download de arquivos com metadados

### Critérios de aceite
1. Regra de aprovação bloqueando edição de campos financeiros.
2. Trigger de notificação em vencimento próximo.
3. Fluxo de anexo por registro funcionando ponta a ponta.

### Riscos e mitigação
1. Corridas de execução em automações.
- Mitigação: fila com idempotência e lock por registro.

2. Exposição de arquivos.
- Mitigação: URLs assinadas e controle por permissão.

## Fase 3 (Dias 61-90): Reporting + integração + hardening

### Escopo
1. Views analíticas e exportação
2. Webhooks e API pública
3. Observabilidade completa
4. Hardening de segurança e operação

### Entregáveis
1. Módulo de relatórios (lista, agregação, export CSV)
2. Webhooks de eventos de registro
3. Dashboard de operação (jobs, erros, latência)
4. Playbook de produção e rollout

### Critérios de aceite
1. Aplicação de Estoque mínima construída no builder.
2. Integração externa via webhook consumindo eventos.
3. SLO mínimo cumprido em ambiente de staging.

### Riscos e mitigação
1. Relatórios pesados degradando runtime.
- Mitigação: materialização assíncrona e limites de consulta.

2. Falhas silenciosas em integração.
- Mitigação: retry com dead letter queue e alertas.

## Backlog de produto pós-90 dias
1. Designer visual avançado de formulário.
2. Workflow BPM com estados e transições.
3. Marketplace de templates de apps.
4. Conectores prontos (ERP, fiscal, CRM, e-mail).
5. Versionamento com comparação visual de schema.

## Métricas de sucesso

### Produto
1. Tempo para criar um app funcional menor que 2 horas.
2. Tempo para criar nova entidade menor que 10 minutos.
3. Percentual de funcionalidades entregues sem código de domínio maior que 80%.

### Engenharia
1. Cobertura de testes mínima de 70% no core.
2. MTTR menor que 60 minutos.
3. Taxa de erro de API menor que 1%.

### Operação
1. Disponibilidade maior que 99,5% em produção.
2. Jobs críticos com sucesso maior que 99%.

## Plano de equipe sugerido
1. 1 arquiteto técnico
2. 2 backend engineers (Django)
3. 2 frontend engineers (React)
4. 1 QA automation
5. 1 DevOps/SRE part-time
6. 1 product owner

## Governança semanal
1. Review de arquitetura e dívida técnica.
2. Review de segurança e permissões.
3. Demo de incremento funcional no builder/runtime.
4. Revisão de métricas de entrega e operação.
