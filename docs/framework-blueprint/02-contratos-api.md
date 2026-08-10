# 02 - Contratos de API (v1)

## Objetivo
Definir contratos mínimos para operar um framework no-code/low-code orientado a metadados, com foco em:
- Builder de entidades e campos
- Runtime dinâmico de dados
- Permissões por papel e por campo
- Automações por evento

## Convenções
- Base path: /api/v1
- Formato: JSON
- Timezone padrão: UTC
- Ids: UUID
- Paginação: limit e offset
- Ordenação: sort (exemplo: created_at desc)

## Autenticação e autorização
- Login via token JWT ou sessão com CSRF
- Cada requisição carrega contexto de tenant/workspace
- Controle de acesso por:
  - entidade
  - ação (create, read, update, delete, export, approve)
  - campo (read/write)

## Recursos centrais

### Workspaces

POST /workspaces
- Cria um workspace

Body:
{
  "name": "Financeiro ACME",
  "slug": "financeiro-acme",
  "timezone": "America/Sao_Paulo"
}

Response 201:
{
  "id": "f1dfc2f1-5f8f-4d71-996f-11f71a3c2f9b",
  "name": "Financeiro ACME",
  "slug": "financeiro-acme",
  "timezone": "America/Sao_Paulo",
  "created_at": "2026-06-23T14:00:00Z"
}

GET /workspaces/{workspace_id}
- Retorna dados do workspace

### Apps

POST /workspaces/{workspace_id}/apps
- Cria uma aplicação dentro do workspace

Body:
{
  "name": "Contas a Pagar",
  "slug": "contas-a-pagar",
  "description": "Gestão de títulos, aprovações e pagamentos"
}

GET /workspaces/{workspace_id}/apps
- Lista apps

### Entidades (Builder)

POST /apps/{app_id}/entities
- Cria entidade

Body:
{
  "name": "titulo_pagar",
  "label": "Título a Pagar",
  "description": "Registro de obrigação financeira",
  "plural_label": "Títulos a Pagar"
}

Response 201:
{
  "id": "1b3e16fd-1f9a-4db9-89d9-4f4d0381fbc2",
  "app_id": "...",
  "name": "titulo_pagar",
  "label": "Título a Pagar",
  "status": "draft",
  "version": 1
}

GET /apps/{app_id}/entities
- Lista entidades

PATCH /entities/{entity_id}
- Atualiza metadados da entidade

DELETE /entities/{entity_id}
- Arquivamento lógico (não remoção física)

### Campos (Builder)

POST /entities/{entity_id}/fields
- Cria campo na entidade

Body:
{
  "name": "valor_total",
  "label": "Valor Total",
  "type": "decimal",
  "required": true,
  "options": {
    "precision": 14,
    "scale": 2,
    "default": 0
  }
}

Tipos iniciais recomendados:
- text
- long_text
- integer
- decimal
- boolean
- date
- datetime
- enum
- relation_one
- relation_many
- user
- attachment

PATCH /fields/{field_id}
- Atualiza campo e dispara versão de schema

DELETE /fields/{field_id}
- Remove campo com política de migração

### Views e layout

POST /entities/{entity_id}/views
- Cria view de lista

Body:
{
  "name": "lista_abertos",
  "label": "Títulos em Aberto",
  "type": "table",
  "filters": [
    {"field": "status", "op": "eq", "value": "aberto"}
  ],
  "columns": ["fornecedor", "vencimento", "valor_total", "status"]
}

PATCH /views/{view_id}
- Atualiza filtros, colunas e ordenação

### Dados em runtime

POST /runtime/entities/{entity_name}/records
- Insere registro dinâmico

Body:
{
  "fornecedor": "Fornecedor XYZ",
  "vencimento": "2026-07-10",
  "valor_total": 1290.55,
  "status": "aberto"
}

Response 201:
{
  "id": "2cb6b133-9f72-41fa-b0c1-c2f70f3925a9",
  "entity": "titulo_pagar",
  "data": {
    "fornecedor": "Fornecedor XYZ",
    "vencimento": "2026-07-10",
    "valor_total": 1290.55,
    "status": "aberto"
  },
  "created_at": "2026-06-23T14:15:00Z"
}

GET /runtime/entities/{entity_name}/records
- Lista registros com filtros dinâmicos

Query params sugeridos:
- filter
- limit
- offset
- sort
- include

GET /runtime/entities/{entity_name}/records/{record_id}
- Detalhe de registro

PATCH /runtime/entities/{entity_name}/records/{record_id}
- Atualiza registro com validações de schema e permissão por campo

DELETE /runtime/entities/{entity_name}/records/{record_id}
- Exclusão lógica (soft delete)

### Permissões

POST /apps/{app_id}/roles
- Cria papel

POST /roles/{role_id}/permissions
- Define regras por entidade/ação/campo

Body:
{
  "entity": "titulo_pagar",
  "actions": ["create", "read", "update"],
  "field_rules": {
    "valor_total": {"read": true, "write": false},
    "status": {"read": true, "write": true}
  }
}

### Automações

POST /entities/{entity_id}/automations
- Cria automação baseada em trigger

Body:
{
  "name": "notificar_vencimento",
  "trigger": {
    "event": "record.updated",
    "condition": "status == 'aberto' and dias_para_vencimento <= 3"
  },
  "actions": [
    {
      "type": "email.send",
      "params": {
        "to": "financeiro@acme.com",
        "subject": "Título próximo do vencimento"
      }
    }
  ],
  "active": true
}

### Publicação de schema

POST /apps/{app_id}/publish
- Publica versão de metadados para runtime

Body:
{
  "version_note": "Adição de campo centro_custo",
  "run_migrations": true
}

Response 202:
{
  "job_id": "fb7f998f-3d6e-495d-88cf-a658451d0b27",
  "status": "queued"
}

GET /jobs/{job_id}
- Consulta status de publicação/migração

## Códigos de erro padrão
- 400: payload inválido
- 401: não autenticado
- 403: sem permissão
- 404: recurso não encontrado
- 409: conflito de schema
- 422: erro de validação
- 500: erro interno

Formato padrão de erro:
{
  "error": {
    "code": "validation_error",
    "message": "Campo valor_total é obrigatório",
    "details": [
      {"field": "valor_total", "rule": "required"}
    ],
    "trace_id": "3fdb83a5f2bb4d2b"
  }
}

## Requisitos não funcionais de API
- Idempotência para operações críticas
- Rate limit por token e workspace
- Logs estruturados por trace_id
- Auditoria imutável de alterações de metadados
# Blueprint v1 — Contratos de API REST
# Framework No-Code/Low-Code

> Base URL: `/api/v1`
> Autenticação: Bearer JWT em todos os endpoints (exceto login e health)
> Formato: JSON (Content-Type: application/json)

---

## Convenções

| Padrão | Regra |
|--------|-------|
| URL | `/recurso/{id}/sub-recurso` — snake_case, plural |
| Resposta de lista | `{ "count": N, "next": url, "previous": url, "results": [...] }` |
| Resposta de item | `{ "id": "uuid", "created_at": "iso8601", ... }` |
| Erro de validação | `{ "errors": { "campo": ["msg"] } }` (HTTP 400) |
| Erro de acesso | `{ "detail": "Permission denied." }` (HTTP 403) |
| Não encontrado | `{ "detail": "Not found." }` (HTTP 404) |
| Erro interno | `{ "detail": "Internal error.", "trace_id": "..." }` (HTTP 500) |

---

## 1. Autenticação

### POST /auth/login
Autentica usuário e retorna tokens.

**Request:**
```json
{
  "email": "carlos@empresa.com",
  "password": "senha123"
}
```

**Response 200:**
```json
{
  "access": "eyJ...",
  "refresh": "eyJ...",
  "user": {
    "id": "uuid",
    "email": "carlos@empresa.com",
    "first_name": "Carlos",
    "last_name": "Botelho"
  }
}
```

### POST /auth/token/refresh
Renova access token.

### POST /auth/logout
Invalida refresh token.

### GET /auth/me
Retorna usuário logado com workspaces e papéis.

---

## 2. Workspaces

### GET /workspaces
Lista workspaces do usuário logado.

**Response 200:**
```json
{
  "count": 2,
  "results": [
    {
      "id": "uuid",
      "slug": "ged-prefeitura",
      "name": "GED Prefeitura",
      "role": "admin",
      "entities_count": 12,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ]
}
```

### POST /workspaces
Cria novo workspace.

**Request:**
```json
{
  "slug": "ged-prefeitura",
  "name": "GED Prefeitura",
  "description": "Gestão eletrônica de documentos"
}
```

### GET /workspaces/{workspace_slug}
Detalhes do workspace.

### PATCH /workspaces/{workspace_slug}
Atualiza configurações do workspace.

### DELETE /workspaces/{workspace_slug}
Remove workspace (requer confirmação e papel admin).

---

## 3. Builder de Entidades

### GET /workspaces/{ws}/entities
Lista entidades do workspace.

**Query params:** `?parent=null` (raízes), `?parent={id}` (filhas)

**Response 200:**
```json
{
  "count": 3,
  "results": [
    {
      "id": "uuid",
      "slug": "documento",
      "name": "Documento",
      "name_plural": "Documentos",
      "icon": "file-text",
      "color": "#4A90D9",
      "fields_count": 8,
      "records_count": 1247,
      "parent_entity": null,
      "sort_order": 1
    }
  ]
}
```

### POST /workspaces/{ws}/entities
Cria entidade.

**Request:**
```json
{
  "slug": "documento",
  "name": "Documento",
  "name_plural": "Documentos",
  "icon": "file-text",
  "color": "#4A90D9",
  "parent_entity_id": null
}
```

### GET /workspaces/{ws}/entities/{entity_slug}
Detalhes da entidade com campos e relacionamentos.

### PATCH /workspaces/{ws}/entities/{entity_slug}
Atualiza metadados da entidade.

### DELETE /workspaces/{ws}/entities/{entity_slug}
Remove entidade (somente se sem registros ou com flag `force=true`).

---

## 4. Builder de Campos

### GET /workspaces/{ws}/entities/{entity}/fields
Lista campos da entidade.

**Response 200:**
```json
{
  "count": 6,
  "results": [
    {
      "id": "uuid",
      "slug": "titulo",
      "name": "Título",
      "field_type": "text",
      "is_required": true,
      "is_unique": false,
      "sort_order": 1,
      "config": {}
    },
    {
      "id": "uuid",
      "slug": "tipo_documento",
      "name": "Tipo de Documento",
      "field_type": "select",
      "is_required": true,
      "config": {
        "choices": [
          { "value": "contrato", "label": "Contrato", "color": "#2ECC71" },
          { "value": "oficio", "label": "Ofício", "color": "#E74C3C" }
        ]
      }
    }
  ]
}
```

### POST /workspaces/{ws}/entities/{entity}/fields
Cria campo.

**Request (texto):**
```json
{
  "slug": "titulo",
  "name": "Título",
  "field_type": "text",
  "is_required": true,
  "config": { "max_length": 255 }
}
```

**Request (relação):**
```json
{
  "slug": "setor",
  "name": "Setor",
  "field_type": "relation_one",
  "config": {
    "target_entity_id": "uuid-da-entidade-setor",
    "display_field": "nome",
    "on_delete": "restrict"
  }
}
```

**Request (fórmula):**
```json
{
  "slug": "valor_com_desconto",
  "name": "Valor com Desconto",
  "field_type": "formula",
  "config": {
    "expression": "record.valor * (1 - record.desconto / 100)",
    "result_type": "decimal"
  }
}
```

### PATCH /workspaces/{ws}/entities/{entity}/fields/{field}
Atualiza campo (gera migration automática se type-safe).

### DELETE /workspaces/{ws}/entities/{entity}/fields/{field}
Remove campo (requer confirmação; dados do campo são apagados dos registros).

### POST /workspaces/{ws}/entities/{entity}/fields/reorder
Reordena campos.

**Request:**
```json
{ "order": ["uuid1", "uuid2", "uuid3"] }
```

---

## 5. Runtime — CRUD Dinâmico

### GET /workspaces/{ws}/data/{entity}
Lista registros da entidade. Motor de query dinâmico.

**Query params:**
```
?page=1&page_size=25
?sort=titulo&sort_dir=asc
?search=carlos                       (full-text nos campos searchable)
?filter[status]=ativo                (filtro exato)
?filter[valor__gte]=1000             (lookups: gte, lte, contains, in, isnull)
?filter[tipo_documento__in]=contrato,oficio
?fields=id,titulo,status             (projeção)
?expand=setor,criado_por             (eager load de relações)
?view=uuid-da-view                   (usar configuração de view salva)
```

**Response 200:**
```json
{
  "count": 1247,
  "next": "...",
  "previous": null,
  "results": [
    {
      "id": "uuid",
      "titulo": "Contrato de Fornecimento",
      "status": "ativo",
      "valor": 15000.00,
      "setor": {
        "id": "uuid",
        "nome": "Setor de TI"
      },
      "created_at": "2026-06-01T10:00:00Z",
      "created_by": { "id": "uuid", "name": "Carlos Botelho" }
    }
  ]
}
```

### POST /workspaces/{ws}/data/{entity}
Cria registro.

**Request:**
```json
{
  "titulo": "Contrato de Fornecimento",
  "tipo_documento": "contrato",
  "valor": 15000.00,
  "setor": "uuid-do-setor",
  "descricao": "Contrato anual de fornecimento de TI"
}
```

**Response 201:**
```json
{
  "id": "uuid",
  "titulo": "Contrato de Fornecimento",
  "tipo_documento": "contrato",
  "valor": 15000.00,
  "valor_com_desconto": 15000.00,
  "setor": { "id": "uuid", "nome": "Setor de TI" },
  "created_at": "2026-06-23T14:00:00Z",
  "created_by": { "id": "uuid", "name": "Carlos Botelho" }
}
```

### GET /workspaces/{ws}/data/{entity}/{record_id}
Retorna registro completo com campos computados e relações expandidas.

### PATCH /workspaces/{ws}/data/{entity}/{record_id}
Atualiza campos específicos.

### DELETE /workspaces/{ws}/data/{entity}/{record_id}
Soft-delete (move para deleted_at). Hard-delete com `?permanent=true` (apenas admin).

### POST /workspaces/{ws}/data/{entity}/bulk_create
Criação em lote (máx. 500 por chamada).

### PATCH /workspaces/{ws}/data/{entity}/bulk_update
Atualização em lote com filtro.

**Request:**
```json
{
  "filter": { "status": "rascunho" },
  "data": { "status": "publicado" }
}
```

### DELETE /workspaces/{ws}/data/{entity}/bulk_delete
Remoção em lote.

---

## 6. Relações de Registros (M-N)

### GET /workspaces/{ws}/data/{entity}/{record}/relations/{relation_slug}
Lista registros relacionados.

### POST /workspaces/{ws}/data/{entity}/{record}/relations/{relation_slug}
Adiciona relação.

**Request:**
```json
{ "target_id": "uuid-do-registro-alvo" }
```

### DELETE /workspaces/{ws}/data/{entity}/{record}/relations/{relation_slug}/{target_id}
Remove relação.

---

## 7. Uploads de Arquivo

### POST /workspaces/{ws}/data/{entity}/{record}/files
Upload de arquivo para campo do tipo `file` ou `files`.

**Content-Type:** `multipart/form-data`

**Response 201:**
```json
{
  "id": "uuid",
  "field_slug": "anexo",
  "filename": "contrato.pdf",
  "size_bytes": 204800,
  "mime_type": "application/pdf",
  "url": "https://bucket.r2.cloudflarestorage.com/...",
  "created_at": "2026-06-23T14:00:00Z"
}
```

### DELETE /workspaces/{ws}/data/{entity}/{record}/files/{file_id}
Remove arquivo.

---

## 8. Views (Layouts)

### GET /workspaces/{ws}/entities/{entity}/views
Lista views da entidade.

### POST /workspaces/{ws}/entities/{entity}/views
Cria view.

**Request (lista):**
```json
{
  "slug": "lista-principal",
  "name": "Lista Principal",
  "view_type": "list",
  "is_default": true,
  "config": {
    "columns": [
      { "field": "titulo", "width": 300, "sortable": true },
      { "field": "status", "width": 120 },
      { "field": "valor", "width": 150, "align": "right" }
    ],
    "default_sort": { "field": "created_at", "dir": "desc" },
    "filters": [
      { "field": "status", "operator": "eq", "value": "ativo" }
    ],
    "group_by": null,
    "page_size": 25
  }
}
```

**Request (formulário):**
```json
{
  "slug": "form-criacao",
  "name": "Formulário de Criação",
  "view_type": "form",
  "config": {
    "sections": [
      {
        "title": "Dados Gerais",
        "columns": 2,
        "fields": ["titulo", "tipo_documento", "data_emissao"]
      },
      {
        "title": "Valores",
        "columns": 2,
        "fields": ["valor", "desconto", "valor_com_desconto"]
      }
    ]
  }
}
```

**Request (kanban):**
```json
{
  "slug": "kanban-status",
  "name": "Kanban por Status",
  "view_type": "kanban",
  "config": {
    "group_by_field": "status",
    "card_fields": ["titulo", "valor", "responsavel"],
    "swimlanes": null
  }
}
```

### PATCH /workspaces/{ws}/entities/{entity}/views/{view}
Atualiza view.

### DELETE /workspaces/{ws}/entities/{entity}/views/{view}
Remove view.

---

## 9. Permissões

### GET /workspaces/{ws}/roles
Lista papéis.

### POST /workspaces/{ws}/roles
Cria papel.

### GET /workspaces/{ws}/roles/{role}/permissions
Lista permissões do papel.

### PUT /workspaces/{ws}/roles/{role}/permissions
Substitui todas as permissões do papel.

**Request:**
```json
{
  "permissions": [
    {
      "entity_id": "uuid",
      "can_view": true,
      "can_create": true,
      "can_update": true,
      "can_delete": false,
      "view_scope": "own"
    }
  ],
  "field_permissions": [
    {
      "field_id": "uuid",
      "can_read": true,
      "can_write": false
    }
  ]
}
```

---

## 10. Automação

### GET /workspaces/{ws}/automations
Lista regras.

### POST /workspaces/{ws}/automations
Cria regra.

**Request:**
```json
{
  "name": "Notificar responsável ao criar documento",
  "entity_id": "uuid-documento",
  "trigger_type": "on_create",
  "conditions": [
    { "field": "tipo_documento", "operator": "eq", "value": "contrato" }
  ],
  "actions": [
    {
      "type": "notify_user",
      "config": {
        "recipient_field": "responsavel",
        "template": "Novo contrato criado: {{titulo}}"
      }
    },
    {
      "type": "set_field",
      "config": {
        "field": "status",
        "value": "em_revisao"
      }
    }
  ]
}
```

### POST /workspaces/{ws}/automations/{rule_id}/test
Testa regra manualmente com registro existente.

---

## 11. Relatórios e Exportação

### POST /workspaces/{ws}/data/{entity}/export
Exporta registros com filtros.

**Request:**
```json
{
  "format": "xlsx",
  "fields": ["titulo", "status", "valor", "created_at"],
  "filter": { "status": "ativo" },
  "sort": { "field": "created_at", "dir": "desc" }
}
```

**Response 202 (assíncrono):**
```json
{
  "job_id": "uuid",
  "status": "queued",
  "status_url": "/api/v1/jobs/uuid"
}
```

### GET /workspaces/{ws}/data/{entity}/aggregate
Agregações para gráficos e dashboards.

**Request:**
```json
{
  "aggregations": [
    { "type": "count", "field": null, "alias": "total" },
    { "type": "sum", "field": "valor", "alias": "valor_total" },
    { "type": "avg", "field": "valor", "alias": "valor_medio" }
  ],
  "group_by": "tipo_documento",
  "filter": { "status": "ativo" }
}
```

---

## 12. Auditoria

### GET /workspaces/{ws}/audit
Lista eventos de auditoria.

**Query params:** `?entity={slug}&record_id={id}&user={id}&action={create|update|delete}`

### GET /workspaces/{ws}/data/{entity}/{record}/history
Histórico completo de um registro (diff de cada versão).

---

## 13. Health e Sistema

### GET /health
Health check público (sem autenticação).

```json
{
  "status": "ok",
  "version": "1.0.0",
  "db": "ok",
  "cache": "ok",
  "queue": "ok"
}
```

### GET /api/v1/schema/{ws}
Retorna o schema completo do workspace em formato JSON (entidades, campos, relações, views).
Usado pelo frontend para montar UI dinamicamente.

---

## Códigos HTTP de Referência

| Código | Significado |
|--------|-------------|
| 200 | OK — leitura com sucesso |
| 201 | Created — criação com sucesso |
| 202 | Accepted — processamento assíncrono iniciado |
| 204 | No Content — delete com sucesso |
| 400 | Bad Request — validação falhou |
| 401 | Unauthorized — não autenticado |
| 403 | Forbidden — autenticado mas sem permissão |
| 404 | Not Found — recurso não existe |
| 409 | Conflict — violação de unique |
| 422 | Unprocessable Entity — dados semanticamente inválidos |
| 429 | Too Many Requests — rate limit |
| 500 | Internal Server Error — erro inesperado |
