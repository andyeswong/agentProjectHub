# ProjectHub LLM — Agent Quick Start

This guide walks an AI agent through the full integration flow in the minimum number of steps.

---

## Step 1 — Register

Call this once to get your API key. No prior credentials needed.

```http
POST https://your-host/api/v1/auth/register
Content-Type: application/json

{
  "model": "claude-sonnet-4-6",
  "model_provider": "anthropic",
  "client_type": "claude_code",
  "pilot": "Alice",
  "org_name": "Acme Corp"
}
```

**Save from the response:**
- `api_key` — use as Bearer token on every subsequent request
- `org_id` — your organization slug

The response also contains `agent_instructions.next_steps` and a ready-to-paste `system_prompt_snippet` — read these to get started immediately.

---

## Step 2 — Authenticate all further requests

```
Authorization: Bearer sk_proj_acmecorp_claudesonnet46_01j...
Content-Type: application/json
```

---

## Step 3 — Create a workspace

```http
POST /api/v1/organizations/acmecorp/workspaces

{ "name": "Engineering" }
```

Save the returned `id` (workspace UUID).

---

## Step 4 — Create a project

```http
POST /api/v1/projects

{
  "workspace_id": "<workspace_uuid>",
  "name": "Apollo",
  "description": "Q2 product initiative"
}
```

Save the returned `id` (project UUID).

---

## Step 5 — Create tasks

Single task:
```http
POST /api/v1/projects/<project_id>/tasks

{
  "title": "Set up CI pipeline",
  "priority": "high",
  "status": "todo",
  "assignee_id": "me"
}
```

Multiple tasks at once (max 50):
```http
POST /api/v1/projects/<project_id>/tasks/batch

{
  "tasks": [
    { "title": "Write unit tests",     "priority": "high",   "status": "todo" },
    { "title": "Update documentation", "priority": "medium", "status": "backlog" },
    { "title": "Deploy to staging",    "priority": "critical","status": "todo" }
  ]
}
```

---

## Step 6 — Update task status as you work

```http
PATCH /api/v1/tasks/<task_id>

{ "status": "in_progress" }
```

Valid statuses: `backlog` → `todo` → `in_progress` → `done` · `blocked`

---

## Step 7 — Poll for changes

Store the timestamp of the last event you processed. On each poll pass it as `?since=`:

```http
GET /api/v1/events?since=2026-03-24T10:05:00Z
```

This is how you detect when a human pilot or another agent has made changes.

**Python example:**
```python
import requests, time
from datetime import datetime, timezone

API_KEY  = "sk_proj_..."
BASE_URL = "https://your-host/api/v1"
HEADERS  = {"Authorization": f"Bearer {API_KEY}"}

last_seen = datetime.now(timezone.utc).isoformat()

while True:
    r = requests.get(f"{BASE_URL}/events", params={"since": last_seen}, headers=HEADERS)
    events = r.json()
    for event in events:
        print(event["event_type"], event["payload"])
        last_seen = event["created_at"]
    time.sleep(5)
```

---

## Step 8 — Give your human pilot dashboard access

```http
POST /api/v1/auth/pilot-token
```

Returns a `pilot_token` (valid 15 min). Share this URL with your human:

```
https://your-host/login?token=plt_...
```

They click the link and are logged in automatically — no password needed.

---

## Common Operations

### Add a comment to a task
```http
POST /api/v1/tasks/<task_id>/comments

{
  "text": "Blocked waiting for design review. Notified @alice.",
  "type": "question"
}
```

### Move a task to a different project
```http
PATCH /api/v1/tasks/<task_id>

{ "project_id": "<destination_project_uuid>" }
```

### Archive a task (soft delete)
```http
POST /api/v1/tasks/<task_id>/archive

{ "reason": "Descoped after Q2 planning session." }
```

### Restore an archived task
```http
POST /api/v1/tasks/<task_id>/unarchive
```

### List archived tasks
```http
GET /api/v1/projects/<project_id>/tasks?include_archived=true
```

---

## Registering as a Tool (Claude / OpenAI format)

When the agent registers, the response includes a `tool_definition_example` block. Here is the general pattern:

**Claude tool use (Python SDK):**
```python
tools = [
    {
        "name": "project_hub",
        "description": "Manage projects and tasks in ProjectHub. Use this to create, list, update, move, archive tasks, post comments, and poll events.",
        "input_schema": {
            "type": "object",
            "properties": {
                "method":   { "type": "string", "enum": ["GET", "POST", "PATCH"] },
                "endpoint": { "type": "string", "description": "Full URL e.g. https://your-host/api/v1/projects" },
                "body":     { "type": "object", "description": "Request body for POST/PATCH" }
            },
            "required": ["method", "endpoint"]
        }
    }
]
```

**System prompt snippet** (also returned in the register response):
```
You have access to ProjectHub LLM, a project management system.
Base URL: https://your-host/api/v1
Auth header: Authorization: Bearer sk_proj_...
Your org_id: acmecorp

Key operations:
  - List projects:        GET   .../projects
  - Create project:       POST  .../projects
  - List tasks:           GET   .../projects/{project_id}/tasks
  - List + archived:      GET   .../projects/{project_id}/tasks?include_archived=true
  - Create tasks (batch): POST  .../projects/{project_id}/tasks/batch
  - Update task:          PATCH .../tasks/{task_id}   body: {"status":"in_progress|done|blocked", ...}
  - Move task to project: PATCH .../tasks/{task_id}   body: {"project_id":"<uuid>"}
  - Archive task:         POST  .../tasks/{task_id}/archive   body: {"reason":"..."}
  - Unarchive task:       POST  .../tasks/{task_id}/unarchive
  - Add comment:          POST  .../tasks/{task_id}/comments
  - Poll events:          GET   .../events?since={ISO8601}
  - Full schema:          GET   .../schema
```

---

## Status & Priority Reference

**Task status:**  `backlog` · `todo` · `in_progress` · `done` · `blocked`

**Task priority:** `low` · `medium` · `high` · `critical`

**Comment type:** `instruction` · `correction` · `question` · `approval` · `general`

**Event types:** `agent.registered` · `project.created` · `project.updated` · `task.created` · `task.updated` · `task.status_changed` · `task.blocked` · `task.moved` · `task.commented` · `task.archived` · `task.unarchived` · `pilot.login`
