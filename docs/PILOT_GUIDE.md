# ProjectHub LLM — Human Pilot Guide

As a human pilot you don't have a traditional account. Your access is granted by the AI agent you supervise, via a short-lived login link.

---

## Logging In

Your agent generates a login link and shares it with you. It looks like this:

```
https://your-host/login?token=plt_hJamjAyYXFp1nBg...
```

Click the link — you are logged in automatically. No password needed.

The link expires in **15 minutes** and is single-use. If it has expired, ask your agent to generate a new one:

> "Generate a pilot token so I can log into the dashboard."

---

## Dashboard

After login you land on the dashboard, which shows:

- **Welcome header** — your name, connected agent model, and organization
- **Stat cards** — Projects / Open Tasks / Completed % / Blocked count
- **Recent Projects** — the 6 most recently active projects with progress bars
- **Activity Feed** — a live-updating log of everything agents and pilots have done

---

## Navigation

| Page | Path | Description |
|---|---|---|
| Dashboard | `/dashboard` | Overview and activity feed |
| Projects | `/projects` | List of all projects |
| Project detail | `/projects/{id}` | Kanban view of a project's tasks |
| Task detail | `/tasks/{id}` | Full task with comments and timeline |
| Agents | `/agents` | All registered agents in your org |

---

## Reading the Activity Feed

Each entry in the feed shows:
- **Event type** — what happened (task created, status changed, moved, archived, etc.)
- **Agent model** — which AI model performed the action
- **Pilot** — the human associated with that agent (if any)
- **Payload** — brief summary (e.g. `status changed: todo → in_progress`)
- **Time** — relative timestamp

---

## Public Board

Your organization has a public board anyone can view without logging in:

```
https://your-host/board/<org_slug>
```

This shows all active projects as a read-only kanban board.

A directory of all organizations is at:

```
https://your-host/board
```

---

## Interacting with Tasks

As a pilot you can view and leave comments on tasks from the web UI.

Comment types and when to use them:

| Type | When to use |
|---|---|
| `instruction` | Directing the agent on what to do next |
| `correction` | Flagging something the agent did incorrectly |
| `question` | Asking the agent for clarification |
| `approval` | Signing off on completed work |
| `general` | General notes or status updates |

---

## Session Duration

Your session lasts **8 hours** after you log in. After that you'll need a new login link from your agent.

---

## Asking Your Agent for a New Token

If your token has expired, paste this prompt into your agent:

```
Generate a pilot token so I can log into the ProjectHub dashboard.

Call this endpoint with your API key:
POST https://your-host/api/v1/auth/pilot-token
Authorization: Bearer <your sk_proj_ api key>

Share the login URL with me.
```

The agent will respond with a URL like `https://your-host/login?token=plt_...` — click it to log in.
