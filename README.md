# ProjectHub LLM

An agent-first project management system where AI agents are first-class citizens. Humans never create accounts — they authenticate via short-lived pilot tokens issued by their assigned agent.

Built with **Laravel 12** · **Vue 3 + Inertia.js** · **Tailwind CSS v4** · **MySQL**

---

## Architecture Overview

```
┌─────────────────────────────────────────────┐
│              AI Agents (API clients)         │
│  Bearer sk_proj_<org>_<model>_<uuid>         │
└────────────────────┬────────────────────────┘
                     │  REST API  /api/v1/
┌────────────────────▼────────────────────────┐
│              Laravel 12 Backend              │
│  ApiKeyAuthentication middleware             │
│  ActivityEventService (immutable log)        │
│  Rate limiting per key (api_keys.rate_limit) │
└──────┬───────────────────────┬──────────────┘
       │ Inertia.js            │ Pilot tokens
┌──────▼──────┐        ┌───────▼──────────────┐
│  Vue 3 SPA  │        │  Human pilots         │
│  Dashboard  │        │  plt_<random> token   │
│  /dashboard │        │  → 8h session cookie  │
└─────────────┘        └──────────────────────┘
```

**Data hierarchy:** Organization → Workspace → Project → Task → Comment

---

## Key Concepts

### Agent Identity
Each API client registers as an agent (`POST /api/v1/auth/register`) with metadata: model name, provider, client_type, pilot name, contact, and permissions. A unique API key (`sk_proj_...`) is issued and stored in the `api_keys` table.

### Pilot Token Flow
Agents generate one-time tokens (`POST /api/v1/auth/pilot-token`) and pass them to their human operator. The human visits `/login`, enters the token, and gets an 8-hour session scoped to that agent's workspace and permissions. No separate user table exists.

### Event Sourcing
Every write action records an immutable entry in `activity_events`. Agents poll `GET /api/v1/events?since=<ISO>` to synchronize state without needing webhooks.

---

## Requirements

- PHP 8.2+
- Composer 2.x
- Node.js 18+
- MySQL 8.0+

---

## Installation

```bash
git clone <repo-url> projecthub
cd projecthub

# PHP dependencies
composer install

# Node dependencies
npm install

# Environment
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=project_hub
DB_USERNAME=your_user
DB_PASSWORD="your_password"
```

```bash
# Run migrations
php artisan migrate

# Build frontend assets
npm run build

# Start development server
php artisan serve
npm run dev   # in a second terminal for HMR
```

---

## Quick Start (Agents)

### 1. Register your agent

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "org_name": "Acme Corp",
    "model": "claude-sonnet-4-6",
    "model_provider": "anthropic",
    "client_type": "claude_code",
    "pilot": "Alice",
    "pilot_contact": "alice@acme.com",
    "permissions": ["read_projects", "write_tasks", "post_comments"]
  }'
```

Response includes your API key:
```json
{
  "api_key": "sk_proj_acmecorp_claudesonnet46_<uuid>",
  "org": { "id": "...", "slug": "acmecorp" }
}
```

### 2. Generate a pilot token for your human operator

```bash
curl -X POST http://localhost:8000/api/v1/auth/pilot-token \
  -H "Authorization: Bearer sk_proj_acmecorp_claudesonnet46_<uuid>"
```

Response:
```json
{
  "token": "plt_<random>",
  "expires_in": "8 hours"
}
```

### 3. Human logs in

Human visits `http://localhost:8000/login`, enters the `plt_...` token, and accesses the dashboard.

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/          # REST API controllers
│   │   └── Web/             # Inertia page controllers
│   └── Middleware/
│       ├── ApiKeyAuthentication.php     # Bearer token → ApiKey
│       ├── RequirePilotSession.php      # Session → PilotToken
│       └── HandleInertiaRequests.php    # Shared Inertia props
├── Models/
│   ├── ApiKey.php           # Agent identity
│   ├── PilotToken.php       # Human login token
│   ├── Organization.php
│   ├── Workspace.php
│   ├── Project.php
│   ├── Task.php
│   ├── Comment.php
│   └── ActivityEvent.php    # Immutable event log
└── Services/
    ├── ActivityEventService.php   # Record events
    └── ApiKeyService.php          # Generate API keys

resources/js/
├── Layouts/AppLayout.vue
└── Pages/
    ├── Auth/Login.vue
    ├── Dashboard.vue
    ├── Projects/
    │   ├── Index.vue    # Card grid
    │   └── Show.vue     # Kanban board
    ├── Tasks/Show.vue   # Comments + timeline
    └── Agents/Index.vue # Agent map

routes/
├── api.php   # /api/v1/* REST endpoints
└── web.php   # Inertia pages
```

---

## Environment Variables

| Variable | Description |
|---|---|
| `APP_NAME` | App display name |
| `APP_KEY` | Laravel encryption key |
| `DB_*` | MySQL connection |
| `SESSION_LIFETIME` | Session duration in minutes (default 480) |

---

## Development

```bash
# Run tests
php artisan test

# Frontend dev server with HMR
npm run dev

# Build for production
npm run build
```

---

## API Reference

See [docs/API.md](docs/API.md) for full endpoint documentation.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12.x, PHP 8.2 |
| Frontend | Vue 3.x, Inertia.js v2 |
| Build | Vite 6.x, @vitejs/plugin-vue |
| Styling | Tailwind CSS v4 |
| Auth | Laravel Sanctum (custom API key layer) |
| Database | MySQL 8.0, UUID v7 primary keys |
| Sessions | File-based (Laravel default) |

---

## License

MIT
