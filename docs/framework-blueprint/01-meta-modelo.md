# Blueprint v1 — Meta-Modelo do Banco de Dados
# Framework No-Code/Low-Code (Python + React)

> Versão: 0.1 — 2026-06-23
> Status: Referência arquitetural inicial

---

## Visão Geral

O meta-modelo é o **coração do framework**. Toda aplicação criada dentro da plataforma
é descrita como dados, não como código. Isso permite criar, alterar e versionar
aplicações sem deploy.

```
Meta-Modelo
│
├── Workspace       ← Aplicação (GED, Estoque, Financeiro...)
│   ├── Entity          ← Tabela (Documento, Produto, Fatura...)
│   │   ├── Field           ← Campo (título, data, valor...)
│   │   ├── Relation        ← FK / M-N para outra entidade
│   │   ├── View            ← Layout de lista ou formulário
│   │   ├── ValidationRule  ← Regra de validação por campo/entidade
│   │   ├── AutomationRule  ← Gatilho de automação (on_create etc.)
│   │   └── Permission      ← Acesso por papel/campo/ação
│   ├── Role            ← Papel de usuário (Admin, Operador...)
│   ├── User            ← Usuário da aplicação
│   └── SchemaVersion   ← Versionamento de publicação
└── Plugin          ← Extensão instalável
```

---

## Tabelas do Meta-Modelo (PostgreSQL)

### 1. workspace

```sql
CREATE TABLE workspace (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug        VARCHAR(64) UNIQUE NOT NULL,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    is_active   BOOLEAN NOT NULL DEFAULT TRUE,
    settings    JSONB NOT NULL DEFAULT '{}',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE workspace IS 
    'Unidade isolada de aplicação. Cada workspace é uma app completa.';
```

### 2. entity

```sql
CREATE TABLE entity (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id    UUID NOT NULL REFERENCES workspace(id) ON DELETE CASCADE,
    slug            VARCHAR(64) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    name_plural     VARCHAR(255) NOT NULL,
    description     TEXT,
    icon            VARCHAR(64),
    color           VARCHAR(16),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    display_in_menu BOOLEAN NOT NULL DEFAULT TRUE,
    parent_entity_id UUID REFERENCES entity(id),   -- Nested entity (1-N)
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    settings        JSONB NOT NULL DEFAULT '{}',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (workspace_id, slug)
);

CREATE INDEX idx_entity_workspace ON entity(workspace_id);

COMMENT ON COLUMN entity.parent_entity_id IS 
    'Quando preenchido, esta entidade é filha (nested) da entidade pai.';
```

### 3. field

```sql
CREATE TABLE field (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    entity_id       UUID NOT NULL REFERENCES entity(id) ON DELETE CASCADE,
    slug            VARCHAR(64) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    field_type      VARCHAR(64) NOT NULL,       -- Ver enum abaixo
    is_required     BOOLEAN NOT NULL DEFAULT FALSE,
    is_unique       BOOLEAN NOT NULL DEFAULT FALSE,
    is_searchable   BOOLEAN NOT NULL DEFAULT TRUE,
    is_system       BOOLEAN NOT NULL DEFAULT FALSE,  -- Campos internos (id, created_at...)
    default_value   TEXT,
    placeholder     TEXT,
    help_text       TEXT,
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    config          JSONB NOT NULL DEFAULT '{}',    -- Configurações específicas por tipo
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (entity_id, slug)
);

CREATE INDEX idx_field_entity ON field(entity_id);
CREATE INDEX idx_field_type ON field(field_type);

COMMENT ON COLUMN field.config IS 
    'Configurações específicas do tipo: ex: {choices:[...]} para dropdown, 
     {target_entity_id:"uuid"} para relation, {min:0, max:100} para number.';
```

### 4. field_type (enum de referência)

```
Simples:
  text            → Texto curto (VARCHAR)
  textarea        → Texto longo (TEXT)
  number          → Número inteiro ou decimal
  decimal         → Decimal com precisão controlada
  boolean         → Verdadeiro/Falso
  date            → Data
  datetime        → Data e hora
  time            → Hora

Seleção:
  select          → Dropdown (uma escolha)
  multiselect     → Dropdown (múltiplas escolhas)
  radio           → Radio buttons
  checkbox_group  → Grupos de checkboxes

Relacionamento:
  relation_one    → Muitos para um (FK)
  relation_many   → Muitos para muitos (M-N)
  lookup          → Valor lido de entidade relacionada (read-only)

Usuário:
  user            → Usuário único (FK para user)
  users           → Múltiplos usuários

Arquivo:
  file            → Upload de arquivo único
  files           → Upload de múltiplos arquivos
  image           → Imagem com preview

Calculado:
  formula         → Expressão calculada em runtime (Python safe eval)
  computed_count  → COUNT de registros relacionados
  computed_sum    → SUM de campo numérico em relacionados
  sequence        → Numeração automática (ex: DOC-0001)

Sistema (gerados automaticamente):
  system_id       → Identificador único
  system_created_at
  system_updated_at
  system_created_by
  system_updated_by
  system_status   → Status com máquina de estados
```

### 5. relation

```sql
CREATE TABLE relation (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_entity_id    UUID NOT NULL REFERENCES entity(id) ON DELETE CASCADE,
    target_entity_id    UUID NOT NULL REFERENCES entity(id) ON DELETE RESTRICT,
    slug                VARCHAR(64) NOT NULL,
    name                VARCHAR(255) NOT NULL,
    relation_type       VARCHAR(16) NOT NULL,     -- 'one_to_many' | 'many_to_many'
    on_delete           VARCHAR(16) NOT NULL DEFAULT 'restrict',  -- restrict|cascade|set_null
    is_required         BOOLEAN NOT NULL DEFAULT FALSE,
    reverse_name        VARCHAR(255),    -- Nome do relacionamento reverso
    sort_order          SMALLINT NOT NULL DEFAULT 0,
    config              JSONB NOT NULL DEFAULT '{}',
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (source_entity_id, slug)
);
```

### 6. role (papel de usuário por workspace)

```sql
CREATE TABLE role (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id    UUID NOT NULL REFERENCES workspace(id) ON DELETE CASCADE,
    slug            VARCHAR(64) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    is_admin        BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (workspace_id, slug)
);
```

### 7. permission (acesso por papel + entidade + ação)

```sql
CREATE TABLE permission (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role_id         UUID NOT NULL REFERENCES role(id) ON DELETE CASCADE,
    entity_id       UUID NOT NULL REFERENCES entity(id) ON DELETE CASCADE,
    can_view        BOOLEAN NOT NULL DEFAULT FALSE,
    can_create      BOOLEAN NOT NULL DEFAULT FALSE,
    can_update      BOOLEAN NOT NULL DEFAULT FALSE,
    can_delete      BOOLEAN NOT NULL DEFAULT FALSE,
    view_scope      VARCHAR(32) NOT NULL DEFAULT 'all',  -- 'all'|'own'|'group'
    row_filter      JSONB,   -- Filtro adicional de linha (ex: só registros com status='active')
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (role_id, entity_id)
);
```

### 8. field_permission (acesso por papel + campo)

```sql
CREATE TABLE field_permission (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role_id         UUID NOT NULL REFERENCES role(id) ON DELETE CASCADE,
    field_id        UUID NOT NULL REFERENCES field(id) ON DELETE CASCADE,
    can_read        BOOLEAN NOT NULL DEFAULT TRUE,
    can_write       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (role_id, field_id)
);
```

### 9. record (dados operacionais — particionado por workspace)

```sql
-- Tabela única para todos os registros (EAV otimizado com JSONB)
CREATE TABLE record (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id    UUID NOT NULL REFERENCES workspace(id),
    entity_id       UUID NOT NULL REFERENCES entity(id),
    data            JSONB NOT NULL DEFAULT '{}',     -- Todos os campos como JSONB
    status          VARCHAR(64) NOT NULL DEFAULT 'active',
    parent_id       UUID REFERENCES record(id),      -- Para nested entities
    created_by      UUID NOT NULL,
    updated_by      UUID,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ                      -- Soft delete
);

CREATE INDEX idx_record_workspace_entity ON record(workspace_id, entity_id);
CREATE INDEX idx_record_parent ON record(parent_id);
CREATE INDEX idx_record_status ON record(status);
CREATE INDEX idx_record_created_by ON record(created_by);
CREATE INDEX idx_record_data ON record USING GIN(data);   -- Full-text search e filtragem JSONB
CREATE INDEX idx_record_deleted ON record(deleted_at) WHERE deleted_at IS NULL;
```

### 10. relation_record (dados M-N)

```sql
CREATE TABLE relation_record (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    relation_id     UUID NOT NULL REFERENCES relation(id) ON DELETE CASCADE,
    source_record_id UUID NOT NULL REFERENCES record(id) ON DELETE CASCADE,
    target_record_id UUID NOT NULL REFERENCES record(id) ON DELETE CASCADE,
    sort_order      SMALLINT DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (relation_id, source_record_id, target_record_id)
);
```

### 11. view (layout de lista ou formulário)

```sql
CREATE TABLE view (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    entity_id       UUID NOT NULL REFERENCES entity(id) ON DELETE CASCADE,
    slug            VARCHAR(64) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    view_type       VARCHAR(32) NOT NULL,   -- 'list'|'form'|'kanban'|'calendar'|'chart'
    is_default      BOOLEAN NOT NULL DEFAULT FALSE,
    config          JSONB NOT NULL DEFAULT '{}',   -- Colunas, filtros, agrupamentos, layout
    roles           UUID[] DEFAULT '{}',    -- Papéis que enxergam esta view
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (entity_id, slug)
);
```

### 12. automation_rule (gatilho de automação)

```sql
CREATE TABLE automation_rule (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id    UUID NOT NULL REFERENCES workspace(id) ON DELETE CASCADE,
    entity_id       UUID REFERENCES entity(id),     -- NULL = global
    name            VARCHAR(255) NOT NULL,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    trigger_type    VARCHAR(64) NOT NULL,    -- 'on_create'|'on_update'|'on_delete'|'schedule'|'manual'
    trigger_config  JSONB NOT NULL DEFAULT '{}',   -- ex: cron schedule, field que mudou
    conditions      JSONB NOT NULL DEFAULT '[]',   -- Filtros para disparar
    actions         JSONB NOT NULL DEFAULT '[]',   -- Ações sequenciais
    sort_order      SMALLINT NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Tipos de trigger_type:
--   on_create, on_update, on_delete
--   schedule (cron expression)
--   manual (botão na UI)
--   webhook_inbound (chamada HTTP)

-- Tipos de actions (dentro do array JSONB):
--   set_field, send_email, send_webhook, create_record,
--   update_record, delete_record, run_script, notify_user
```

### 13. audit_log (auditoria imutável)

```sql
CREATE TABLE audit_log (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id    UUID NOT NULL,
    entity_id       UUID,
    record_id       UUID,
    user_id         UUID,
    action          VARCHAR(32) NOT NULL,   -- 'create'|'update'|'delete'|'view'|'export'
    old_data        JSONB,
    new_data        JSONB,
    ip_address      INET,
    user_agent      TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Partição mensal automática para desempenho
CREATE TABLE audit_log_2026_01 PARTITION OF audit_log
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');
-- (criadas automaticamente via rotina de manutenção)
```

### 14. schema_version (versionamento de metadados)

```sql
CREATE TABLE schema_version (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id    UUID NOT NULL REFERENCES workspace(id) ON DELETE CASCADE,
    version         VARCHAR(32) NOT NULL,
    description     TEXT,
    snapshot        JSONB NOT NULL,     -- Snapshot completo do meta-modelo neste momento
    published_by    UUID NOT NULL,
    published_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    is_current      BOOLEAN NOT NULL DEFAULT FALSE
);
```

### 15. user (usuário da plataforma)

```sql
CREATE TABLE platform_user (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email           VARCHAR(255) UNIQUE NOT NULL,
    username        VARCHAR(64) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    first_name      VARCHAR(128),
    last_name       VARCHAR(128),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    is_superuser    BOOLEAN NOT NULL DEFAULT FALSE,
    avatar_url      TEXT,
    preferences     JSONB NOT NULL DEFAULT '{}',
    last_login_at   TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE workspace_user (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id    UUID NOT NULL REFERENCES workspace(id) ON DELETE CASCADE,
    user_id         UUID NOT NULL REFERENCES platform_user(id) ON DELETE CASCADE,
    role_id         UUID NOT NULL REFERENCES role(id),
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    invited_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (workspace_id, user_id)
);
```

---

## Diagrama de Relacionamentos (texto)

```
platform_user ──< workspace_user >── workspace ──< entity ──< field
                                         │              │         └── field_permission
                                         │              ├── relation
                                         │              ├── view
                                         │              └── automation_rule
                                         ├── role ──< permission
                                         │              └── field_permission
                                         ├── schema_version
                                         └── audit_log

entity ──< record ──< relation_record
```

---

## Decisões Arquiteturais

| Decisão | Escolha | Justificativa |
|---------|---------|---------------|
| Dados dos registros | JSONB único por registro | Flexibilidade máxima sem migrations a cada campo novo |
| Indexação | GIN no data + índices virtuais | Consultas rápidas sem colunas fixas |
| Auditoria | Tabela separada particionada | Não polui tabela de dados, alta retenção |
| Relacionamentos M-N | Tabela relation_record | Suporta metadata no link (ordem, notas) |
| Versionamento | Snapshot JSONB | Rollback de configuração sem perda de dados |
| Permissões | Por role+entity+field | Granularidade enterprise desde o início |
| Identificadores | UUID v4 | Evita colisão em multi-tenant e exportações |
| Soft delete | deleted_at nullable | Recuperação de registros, auditoria completa |
