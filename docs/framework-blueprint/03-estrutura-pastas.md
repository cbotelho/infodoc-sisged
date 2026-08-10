# Blueprint v1 — Estrutura de Pastas
# Framework No-Code/Low-Code (Python + React)

---

## Backend Django

```
framework-backend/
│
├── manage.py
├── pyproject.toml                    ← Dependências (uv ou Poetry)
├── Dockerfile
├── docker-compose.yml
├── docker-compose.production.yml
├── .env.example
│
├── config/                           ← Configurações Django (separado por ambiente)
│   ├── __init__.py
│   ├── settings/
│   │   ├── base.py                   ← Configurações comuns
│   │   ├── development.py
│   │   ├── production.py
│   │   └── test.py
│   ├── urls.py                       ← Roteamento raiz
│   ├── wsgi.py
│   └── asgi.py                       ← Para WebSocket (automações em tempo real)
│
├── apps/                             ← Django apps por domínio
│   │
│   ├── authentication/               ← Usuários, JWT, OAuth2, 2FA
│   │   ├── models.py
│   │   ├── serializers.py
│   │   ├── views.py
│   │   ├── urls.py
│   │   ├── services.py               ← Lógica de negócio (login, refresh, logout)
│   │   └── tests/
│   │       ├── test_models.py
│   │       └── test_views.py
│   │
│   ├── workspaces/                   ← Gestão de workspaces e usuários por workspace
│   │   ├── models.py
│   │   ├── serializers.py
│   │   ├── views.py
│   │   ├── urls.py
│   │   ├── services.py
│   │   └── tests/
│   │
│   ├── builder/                      ← CORAÇÃO: Entity, Field, Relation, View
│   │   ├── models/
│   │   │   ├── __init__.py
│   │   │   ├── entity.py
│   │   │   ├── field.py
│   │   │   ├── relation.py
│   │   │   ├── view.py
│   │   │   └── schema_version.py
│   │   ├── serializers/
│   │   │   ├── __init__.py
│   │   │   ├── entity.py
│   │   │   ├── field.py
│   │   │   └── view.py
│   │   ├── views/
│   │   │   ├── __init__.py
│   │   │   ├── entity_views.py
│   │   │   ├── field_views.py
│   │   │   ├── relation_views.py
│   │   │   └── view_views.py
│   │   ├── urls.py
│   │   ├── services/
│   │   │   ├── __init__.py
│   │   │   ├── entity_service.py     ← Criar/alterar entidade + validação
│   │   │   ├── field_service.py      ← Criar/alterar campo + tipo handling
│   │   │   ├── schema_service.py     ← Snapshots e versionamento
│   │   │   └── migration_planner.py  ← Planejar mudanças seguras de schema
│   │   ├── field_types/              ← Registro de tipos de campo
│   │   │   ├── __init__.py
│   │   │   ├── registry.py           ← Dict: tipo → handler
│   │   │   ├── base.py               ← Classe base FieldType
│   │   │   ├── text.py
│   │   │   ├── number.py
│   │   │   ├── select.py
│   │   │   ├── relation.py
│   │   │   ├── file.py
│   │   │   ├── formula.py
│   │   │   ├── sequence.py
│   │   │   └── system_fields.py
│   │   └── tests/
│   │
│   ├── runtime/                      ← Motor dinâmico de CRUD
│   │   ├── models.py                 ← Record, RelationRecord (tabelas de dados)
│   │   ├── serializers/
│   │   │   ├── __init__.py
│   │   │   └── record_serializer.py  ← Serializer dinâmico por entidade/campo
│   │   ├── views/
│   │   │   ├── __init__.py
│   │   │   ├── record_views.py
│   │   │   ├── relation_record_views.py
│   │   │   └── file_upload_views.py
│   │   ├── urls.py
│   │   ├── services/
│   │   │   ├── __init__.py
│   │   │   ├── record_service.py     ← Criar, ler, atualizar, deletar com validação
│   │   │   ├── query_engine.py       ← Parser de filtros, sort, projeção, lookups
│   │   │   ├── formula_engine.py     ← Avaliação segura de fórmulas
│   │   │   ├── computed_service.py   ← Campos computed (count, sum, lookup)
│   │   │   └── export_service.py     ← Exportação xlsx, csv, pdf
│   │   └── tests/
│   │
│   ├── permissions/                  ← Role, Permission, FieldPermission
│   │   ├── models.py
│   │   ├── serializers.py
│   │   ├── views.py
│   │   ├── urls.py
│   │   ├── services.py
│   │   ├── backends.py               ← Django permission backend customizado
│   │   ├── decorators.py             ← @require_entity_permission
│   │   └── tests/
│   │
│   ├── automations/                  ← AutomationRule, executor de ações
│   │   ├── models.py
│   │   ├── serializers.py
│   │   ├── views.py
│   │   ├── urls.py
│   │   ├── services/
│   │   │   ├── __init__.py
│   │   │   ├── rule_evaluator.py     ← Avalia condições de gatilho
│   │   │   └── action_executor.py    ← Executa ações (email, set_field, webhook)
│   │   ├── tasks.py                  ← Celery tasks
│   │   ├── actions/                  ← Handlers por tipo de ação
│   │   │   ├── __init__.py
│   │   │   ├── base.py
│   │   │   ├── set_field.py
│   │   │   ├── send_email.py
│   │   │   ├── send_webhook.py
│   │   │   ├── create_record.py
│   │   │   └── notify_user.py
│   │   └── tests/
│   │
│   ├── audit/                        ← AuditLog
│   │   ├── models.py
│   │   ├── serializers.py
│   │   ├── views.py
│   │   ├── urls.py
│   │   ├── middleware.py             ← Intercepta requests e grava audit
│   │   └── tests/
│   │
│   ├── storage/                      ← Upload de arquivos (S3/R2)
│   │   ├── models.py
│   │   ├── serializers.py
│   │   ├── views.py
│   │   ├── urls.py
│   │   ├── backends/
│   │   │   ├── __init__.py
│   │   │   ├── base.py
│   │   │   ├── local.py
│   │   │   └── s3.py
│   │   └── tests/
│   │
│   └── api/                          ← Roteamento global da API
│       ├── __init__.py
│       ├── urls.py                   ← Agrega todas as apps
│       ├── schema.py                 ← OpenAPI (drf-spectacular)
│       └── pagination.py
│
├── core/                             ← Código utilitário compartilhado
│   ├── __init__.py
│   ├── exceptions.py                 ← Exceções customizadas de domínio
│   ├── validators.py                 ← Validadores reutilizáveis
│   ├── mixins.py                     ← ViewSet mixins
│   ├── pagination.py
│   ├── utils/
│   │   ├── __init__.py
│   │   ├── slugify.py
│   │   ├── json_diff.py              ← Diff entre versões JSONB
│   │   └── safe_eval.py              ← Sandbox para fórmulas
│   └── signals.py                    ← Hooks globais Django
│
└── tests/                            ← Testes de integração
    ├── conftest.py
    ├── fixtures/
    └── integration/
```

---

## Frontend React

```
framework-frontend/
│
├── package.json
├── vite.config.ts
├── tsconfig.json
├── Dockerfile
├── .env.example
│
├── public/
│   └── favicon.ico
│
├── src/
│   │
│   ├── main.tsx                      ← Entry point
│   ├── App.tsx                       ← Roteador raiz
│   │
│   ├── api/                          ← Camada de API (axios + react-query)
│   │   ├── client.ts                 ← Axios instance com interceptors JWT
│   │   ├── auth.ts
│   │   ├── workspaces.ts
│   │   ├── builder.ts
│   │   ├── records.ts
│   │   ├── permissions.ts
│   │   ├── automations.ts
│   │   └── types/                    ← TypeScript interfaces geradas do OpenAPI
│   │       ├── entity.ts
│   │       ├── field.ts
│   │       ├── record.ts
│   │       └── ...
│   │
│   ├── store/                        ← Estado global (Zustand)
│   │   ├── auth.store.ts
│   │   ├── workspace.store.ts
│   │   └── schema.store.ts           ← Schema do workspace (entidades/campos) em cache
│   │
│   ├── hooks/                        ← React Query hooks
│   │   ├── useAuth.ts
│   │   ├── useWorkspace.ts
│   │   ├── useEntity.ts
│   │   ├── useRecords.ts
│   │   ├── usePermissions.ts
│   │   └── useSchema.ts              ← Hook que carrega schema completo do workspace
│   │
│   ├── router/                       ← React Router v6
│   │   ├── index.tsx
│   │   ├── ProtectedRoute.tsx
│   │   └── routes.ts
│   │
│   ├── pages/                        ← Páginas (1 pasta por domínio)
│   │   ├── auth/
│   │   │   ├── LoginPage.tsx
│   │   │   └── ForgotPasswordPage.tsx
│   │   │
│   │   ├── workspaces/
│   │   │   ├── WorkspaceListPage.tsx
│   │   │   └── WorkspaceCreatePage.tsx
│   │   │
│   │   ├── builder/                  ← Designer de entidades/campos
│   │   │   ├── BuilderPage.tsx       ← Layout do builder
│   │   │   ├── EntityListPage.tsx
│   │   │   ├── EntityEditPage.tsx
│   │   │   ├── FieldListPage.tsx
│   │   │   ├── FieldEditPage.tsx
│   │   │   └── ViewEditPage.tsx
│   │   │
│   │   ├── runtime/                  ← Páginas dinâmicas geradas por entidade
│   │   │   ├── RecordListPage.tsx    ← Lista de registros (any entity)
│   │   │   ├── RecordDetailPage.tsx  ← Detalhe (any entity)
│   │   │   └── RecordCreatePage.tsx  ← Formulário (any entity)
│   │   │
│   │   ├── permissions/
│   │   │   ├── RoleListPage.tsx
│   │   │   └── RoleEditPage.tsx
│   │   │
│   │   ├── automations/
│   │   │   ├── AutomationListPage.tsx
│   │   │   └── AutomationEditPage.tsx
│   │   │
│   │   └── settings/
│   │       └── WorkspaceSettingsPage.tsx
│   │
│   ├── components/                   ← Componentes reutilizáveis
│   │   ├── ui/                       ← Design System base (shadcn/ui)
│   │   │   ├── Button.tsx
│   │   │   ├── Input.tsx
│   │   │   ├── Modal.tsx
│   │   │   ├── Table.tsx
│   │   │   ├── Badge.tsx
│   │   │   └── ...
│   │   │
│   │   ├── field-renderers/          ← Renderer dinâmico por tipo de campo
│   │   │   ├── FieldRenderer.tsx     ← Dispatcher: type → component
│   │   │   ├── TextField.tsx
│   │   │   ├── NumberField.tsx
│   │   │   ├── SelectField.tsx
│   │   │   ├── RelationField.tsx     ← Autocomplete + lazy load
│   │   │   ├── FileField.tsx
│   │   │   ├── DateField.tsx
│   │   │   ├── FormulaField.tsx      ← Read-only, computed
│   │   │   └── ...
│   │   │
│   │   ├── views/                    ← View renderers
│   │   │   ├── ListView.tsx          ← Tabela configurável
│   │   │   ├── KanbanView.tsx
│   │   │   ├── CalendarView.tsx
│   │   │   └── ChartView.tsx
│   │   │
│   │   ├── forms/                    ← Form dinâmico gerado por schema
│   │   │   ├── DynamicForm.tsx       ← Gera form por schema de entidade
│   │   │   ├── FormSection.tsx
│   │   │   ├── FormField.tsx
│   │   │   └── ValidationMessages.tsx
│   │   │
│   │   ├── builder/                  ← Componentes do designer
│   │   │   ├── EntityCard.tsx
│   │   │   ├── FieldEditor.tsx
│   │   │   ├── FieldTypeSelector.tsx
│   │   │   ├── ViewBuilder.tsx
│   │   │   └── RelationBuilder.tsx
│   │   │
│   │   └── layout/
│   │       ├── AppShell.tsx          ← Layout com sidebar dinâmica
│   │       ├── Sidebar.tsx           ← Menu gerado pelo schema
│   │       ├── Header.tsx
│   │       └── Breadcrumb.tsx
│   │
│   ├── lib/                          ← Utilitários
│   │   ├── schema-cache.ts           ← Cache local de schema do workspace
│   │   ├── filter-parser.ts          ← Montar query strings de filtro
│   │   ├── permissions.ts            ← Verificações de permissão no frontend
│   │   └── utils.ts
│   │
│   └── styles/
│       ├── globals.css
│       └── theme.css
│
└── tests/
    ├── unit/
    └── e2e/                          ← Playwright
```

---

## Infraestrutura (Docker Compose)

```yaml
# docker-compose.yml (desenvolvimento)
services:
  db:         PostgreSQL 16
  cache:      Redis 7
  backend:    Django + Gunicorn (porta 8000)
  celery:     Celery worker
  celery-beat: Celery scheduler (tarefas agendadas)
  frontend:   Vite dev server (porta 5173)
  storage:    MinIO (S3 local) (porta 9000 + 9001)
  mailhog:    Captura de email local (porta 8025)
```

```yaml
# docker-compose.production.yml
services:
  db:         PostgreSQL 16 (volume persistente)
  cache:      Redis 7 (volume persistente)
  backend:    Django + Gunicorn (escalonável)
  celery:     Celery worker (N réplicas)
  celery-beat: Celery scheduler (1 réplica)
  frontend:   Nginx servindo build React
  storage:    Cloudflare R2 ou AWS S3 (externo)
```

---

## Principais Dependências

### Backend (Python)
| Pacote | Versão | Propósito |
|--------|--------|-----------|
| Django | 5.x | Framework |
| djangorestframework | 3.x | API REST |
| djangorestframework-simplejwt | latest | JWT |
| django-cors-headers | latest | CORS |
| drf-spectacular | latest | OpenAPI |
| psycopg[binary] | 3.x | PostgreSQL driver |
| celery | 5.x | Tasks assíncronas |
| redis | latest | Celery broker + cache |
| boto3 | latest | S3/R2 |
| Pillow | latest | Manipulação de imagens |
| openpyxl | latest | Exportação Excel |
| reportlab | latest | Exportação PDF |
| pydantic | 2.x | Validação de config JSONB |
| django-filter | latest | Filtros dinâmicos |
| pytest-django | latest | Testes |

### Frontend (TypeScript)
| Pacote | Versão | Propósito |
|--------|--------|-----------|
| React | 18.x | Framework UI |
| TypeScript | 5.x | Tipagem |
| Vite | 5.x | Build |
| React Router | 6.x | Navegação |
| TanStack Query | 5.x | Server state + cache |
| Zustand | 4.x | Client state |
| Axios | latest | HTTP |
| shadcn/ui | latest | Design System |
| Tailwind CSS | 3.x | Estilização |
| React Hook Form | 7.x | Formulários |
| Zod | 3.x | Validação no cliente |
| @dnd-kit | latest | Drag-and-drop (kanban, reorder) |
| Recharts | latest | Gráficos |
| Playwright | latest | Testes E2E |
