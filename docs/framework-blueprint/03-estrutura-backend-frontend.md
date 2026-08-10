# 03 - Estrutura Backend e Frontend

## Objetivo
Definir uma estrutura de código escalável para um framework no-code/low-code com backend Python (Django) e frontend React.

## Princípios de arquitetura
1. Modular monolith no início, com fronteiras de domínio claras.
2. Separação Builder x Runtime.
3. Metadados versionados e runtime estável.
4. Multi-tenant desde o núcleo.
5. Eventos de domínio para automações e integrações.

## Backend (Django + DRF)

Estrutura sugerida:

backend/
  src/
    core/
      settings/
        base.py
        dev.py
        prod.py
      urls.py
      wsgi.py
      asgi.py
      security/
      observability/

    apps/
      identity/
        models/
        services/
        api/
        permissions/

      tenancy/
        models/
        middleware/
        routing/

      builder/
        models/
          app.py
          entity.py
          field.py
          relation.py
          view.py
          schema_version.py
        services/
          schema_compiler.py
          migration_planner.py
        api/
          entities.py
          fields.py
          publish.py

      runtime/
        models/
          record.py
          record_audit.py
        services/
          query_engine.py
          validation_engine.py
          permission_engine.py
        api/
          records.py

      automation/
        models/
          automation_rule.py
          trigger_log.py
        workers/
        services/
          trigger_engine.py
          action_dispatcher.py
        api/

      files/
        models/
        services/
          storage_service.py
          scan_service.py
        api/

      reporting/
        models/
        services/
          report_query_service.py
          materialization_service.py
        api/

      integration/
        models/
        services/
          webhook_service.py
          connector_service.py
        api/

    shared/
      db/
      events/
      errors/
      types/
      utils/

  tests/
    unit/
    integration/
    contract/
    e2e/

## Fronteiras de domínio

1. Identity
- Usuários, grupos, papéis, autenticação.

2. Tenancy
- Workspace, isolamento de dados, políticas por tenant.

3. Builder
- Definição de apps, entidades, campos, layouts e versões de schema.

4. Runtime
- Operações de registros dinâmicos com validação e permissão.

5. Automation
- Triggers, condições e ações assíncronas.

6. Files
- Upload, antivírus, metadados e storage S3.

7. Reporting
- Views analíticas, exportações e consultas materializadas.

8. Integration
- API pública, webhooks e conectores externos.

## Modelo de dados recomendado

Camadas:
1. Metadata tables
- apps, entities, fields, field_options, relations, views, schema_versions.

2. Runtime tables
- records (jsonb), record_events, record_audit.

3. Segurança e governança
- roles, permissions, field_permissions, api_tokens.

4. Operação
- jobs, job_logs, webhook_deliveries.

## Estratégia de runtime

1. Gravação em JSONB para flexibilidade inicial.
2. Índices dinâmicos para campos críticos (GIN e btree conforme tipo).
3. Evolução para materializações quando necessário.
4. Event sourcing parcial para auditoria e replay de automações.

## Frontend (React + TypeScript)

Estrutura sugerida:

frontend/
  src/
    app/
      router/
      providers/
      store/
      theme/

    modules/
      auth/
      workspace/
      builder/
        entities/
        fields/
        views/
        publish/
      runtime/
        record-list/
        record-form/
        record-detail/
      automation/
      reporting/
      admin/

    components/
      ui/
      forms/
      grids/
      charts/

    services/
      api/
      auth/
      schema/
      permissions/

    hooks/
    utils/
    types/

  tests/
    unit/
    integration/
    e2e/

## Padrões de frontend

1. Form renderer dinâmico
- Componente de formulário que interpreta metadados de campo.

2. Grid renderer dinâmico
- Lista com colunas, filtros e ordenação definidos por metadados de view.

3. Permission-aware UI
- Botões e campos habilitados por regras de permissão recebidas da API.

4. Feature flags
- Liberar módulos por tenant e por plano.

## Infraestrutura mínima

1. PostgreSQL
2. Redis
3. Celery workers
4. Object storage S3 compatível
5. Nginx
6. Observabilidade com OpenTelemetry

## Segurança

1. Segredos fora de código (vault ou secret manager).
2. Criptografia de dados sensíveis em repouso.
3. Rotação de tokens.
4. Trilha de auditoria imutável para metadados e permissões.
5. Políticas anti-CSRF e anti-XSS.

## Checklist de prontidão técnica

1. CI com testes unitários e integração.
2. Testes de contrato de API.
3. Migrações reversíveis de schema.
4. Rollback por versão de metadados.
5. Documentação OpenAPI atualizada.
