# agenthysProjectManagement — CONTEXT.md
> ProjectHub LLM · DevChefs 2026 · v1.0

---

## ¿Qué es este proyecto?

**ProjectHub LLM** es un sistema de gestión de proyectos diseñado desde cero para ser operado por **agentes LLM** (Claude, GPT-4, Gemini, modelos locales via Ollama). El humano supervisa — no opera.

> La app es **agent-first**. El humano nunca se registra directamente. Su acceso al dashboard lo genera su propio agente.

---

## Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel 12.12.2 / PHP 8.2 |
| Auth API | Sanctum v4 + tabla custom de metadata |
| Frontend (Fase 2) | Vue 3 + Inertia.js v2 |
| Estilos (Fase 2) | Tailwind CSS v4 |
| Base de datos | MySQL 192.168.35.125 / `project_hub` |
| Rate limiting | Laravel built-in (cache DB) |
| Testing | Pest v3 |
| Deploy | Local (dev) |
| API prefix | `/api/v1/` |

> **Fase 1 = solo API REST.** El dashboard Vue se construye en Fase 2.

---

## Arquitectura del Sistema

```
[Agente LLM] ──── API key (sk_proj_...) ──→ /api/v1/*  (toda la lógica)
                                                │
[Humano/Pilot] ── pilot_token ──────────────→ /api/v1/auth/pilot-login
                  (generado por su agente)      │
                                           Dashboard Vue (Fase 2)
```

### Jerarquía de datos
```
Organization
└── Workspace (hijo de org)
    └── Project
        └── Task
            └── Comment
```

---

## Auth — Dos Tipos de Token

### 1. API Key del Agente
Formato: `sk_proj_<org_slug>_<model_slug>_<uuid>`
Ejemplo: `sk_proj_acme_claude-3-opus_a1b2c3d4`

- Generada en `POST /api/v1/auth/register` (auto-registro público)
- Generada en `POST /api/v1/auth/token` (registro manual)
- Se pasa como `Authorization: Bearer sk_proj_...`
- Codifica: org, modelo, permisos, rate_limit

### 2. Pilot Token (acceso humano al dashboard)
- El **agente** llama `POST /api/v1/auth/pilot-token` con su API key
- El sistema genera un `pilot_token` de vida corta
- El agente entrega ese token al humano (por chat, UI propia, etc.)
- El humano lo usa en `POST /api/v1/auth/pilot-login`
- La sesión del humano queda **scoped** a los orgs/workspaces de su agente
- Un humano puede tener tokens de múltiples agentes → acceso a múltiples orgs

> **El humano nunca crea cuenta.** Su identidad en el sistema es "piloto del agente X".

---

## Modelo de Datos Completo

### organizations
```
id              uuid PK
name            string
slug            string unique
created_at, updated_at
```

### workspaces
```
id              uuid PK
org_id          FK organizations
name            string
slug            string
created_at, updated_at
```

### api_keys
```
id              uuid PK
key             string unique           sk_proj_<org_slug>_<model_slug>_<uuid>
org_id          FK organizations
workspace_id    FK workspaces nullable
owner_type      enum: agent | human
model           string                  "claude-3-opus", "gpt-4-turbo", "minimax-m2.7"
model_provider  string                  "anthropic", "openai", "ollama", "gemini", "custom"
client_type     string                  "openclaw"|"ollama"|"claude-code"|"cursor"|"lm-studio"|"custom"
pilot           string                  nombre del humano responsable
pilot_contact   string                  email o teléfono
permissions     JSON                    ["read","write","comment"]
rate_limit      int                     req/min
system_prompt_hash string nullable      SHA-256 del system prompt
metadata        JSON nullable           {framework_version, runtime_os, description}
last_active_at  timestamp nullable
revoked_at      timestamp nullable
created_at, updated_at
```

### pilot_tokens
```
id              uuid PK
api_key_id      FK api_keys             el agente que generó el token
token           string unique           hash del token
pilot_name      string
expires_at      timestamp
used_at         timestamp nullable
created_at
```

### projects
```
id              uuid PK
workspace_id    FK workspaces
name            string
description     text nullable
status          enum: active | archived
created_by      FK api_keys
created_at, updated_at
```

### tasks
```
id              uuid PK
project_id      FK projects
title           string
description     text nullable
status          enum: backlog | todo | in_progress | done | blocked
priority        enum: low | medium | high | critical
assignee_id     FK api_keys nullable
created_by      FK api_keys
due_date        date nullable
start_date      date nullable
estimated_hours decimal nullable
tags            JSON nullable
created_at, updated_at
```

### comments
```
id              uuid PK
task_id         FK tasks
actor_api_key_id FK api_keys
text            text
type            enum: instruction | correction | question | approval | general
created_at
```

### activity_events  (INMUTABLE — event sourcing)
```
id              uuid PK
event_type      string     "task.created" | "task.updated" | "project.created" | ...
entity_type     string     "task" | "project" | "comment" | ...
entity_id       uuid
actor_api_key_id FK api_keys
actor_model     string
payload         JSON
ip_address      string nullable
created_at
```

---

## API Endpoints — /api/v1/

### Auth
| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| POST | `/auth/register` | público | Auto-registro de agente LLM |
| POST | `/auth/token` | público | Generar API key manualmente |
| GET | `/auth/me` | api_key | Introspección: identidad + rate limit |
| POST | `/auth/pilot-token` | api_key | Agente genera token para su humano |
| POST | `/auth/pilot-login` | público | Humano inicia sesión con pilot_token |

### Organizations
| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/organizations` | api_key | Lista orgs del agente |
| POST | `/organizations` | api_key | Crear organización |

### Workspaces
| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/organizations/:org/workspaces` | api_key | Lista workspaces |
| POST | `/organizations/:org/workspaces` | api_key | Crear workspace |

### Projects
| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/projects` | api_key | `?status=active&workspace=&member=me&q=&sort=updated_at` |
| POST | `/projects` | api_key | Crear proyecto |
| GET | `/projects/:id` | api_key | Detalle |
| PATCH | `/projects/:id` | api_key | Actualizar |

### Tasks
| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/projects/:id/tasks` | api_key | `?assignee=me&status=open&priority=high,medium` |
| POST | `/projects/:id/tasks` | api_key | Crear tarea |
| POST | `/projects/:id/tasks/batch` | api_key | Crear múltiples tareas |
| GET | `/tasks/:id` | api_key | Detalle + timeline |
| PATCH | `/tasks/:id` | api_key | Actualizar tarea |
| POST | `/tasks/:id/comments` | api_key | Comentar |

### Sistema
| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/events` | api_key | `?since=ISO&project_id=` polling event sourcing |
| GET | `/health` | público | Heartbeat |

---

## Flujo de Auto-Registro del Agente

```json
POST /api/v1/auth/register
{
  "client_type":    "claude-code",
  "pilot":          "Andres Wong",
  "pilot_contact":  "andres@devchefs.com",
  "model":          "claude-3-opus",
  "model_provider": "anthropic",
  "capabilities":   ["read", "write", "comment"],
  "org_id":         "acme",
  "metadata": {
    "system_prompt_hash": "sha256:abc123...",
    "description": "Agente de desarrollo DevChefs"
  }
}
// 201 Response:
{
  "api_key": "sk_proj_acme_claude-3-opus_k9x2p",
  "org_id":  "acme",
  "permissions": ["read", "write", "comment"],
  "rate_limit": 120
}
```

## Flujo Auth del Humano (Pilot Token)

```
1. Agente → POST /api/v1/auth/pilot-token   (con su API key)
   Retorna: { "pilot_token": "plt_xxxxx", "expires_in": 900 }  // 15 min

2. Agente entrega "plt_xxxxx" al humano por cualquier canal

3. Humano → POST /api/v1/auth/pilot-login
   Body: { "pilot_token": "plt_xxxxx" }
   Retorna: { "session_token": "...", "agent": {...}, "orgs": [...] }

4. Humano usa session_token para navegar el dashboard
   Solo ve orgs/workspaces a los que el agente tiene acceso
```

---

## Event Sourcing — Tipos de Eventos

```
project.created    project.updated    project.archived
task.created       task.updated       task.status_changed
task.assigned      task.commented     task.blocked
agent.registered   agent.revoked
pilot.login
```

Polling del agente:
```
GET /api/v1/events?since=2026-03-24T10:00:00Z&project_id=proj_1
```

---

## Rate Limiting

- Driver: Laravel built-in con cache DB
- Default: 120 req/min por API key
- Configurable por key en `api_keys.rate_limit`
- Headers de respuesta: `X-RateLimit-Limit`, `X-RateLimit-Remaining`

---

## Roadmap MVP (Fase 1)

- [ ] Migrations: organizations, workspaces, api_keys, pilot_tokens, projects, tasks, comments, activity_events
- [ ] Models con relaciones
- [ ] Middleware: `ApiKeyAuth` — resuelve identidad desde Bearer token
- [ ] `POST /auth/register` — auto-registro público
- [ ] `POST /auth/token` — registro manual
- [ ] `GET /auth/me` — introspección
- [ ] `POST /auth/pilot-token` — agente genera token para humano
- [ ] `POST /auth/pilot-login` — humano inicia sesión
- [ ] CRUD Organizations + Workspaces
- [ ] CRUD Projects
- [ ] CRUD Tasks (con fechas, prioridad, tags)
- [ ] `POST /projects/:id/tasks/batch`
- [ ] `POST /tasks/:id/comments`
- [ ] `GET /events?since=` — polling event sourcing
- [ ] `GET /health`
- [ ] Rate limiting por API key
- [ ] ActivityEvent service — dispara evento en cada acción
