<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SchemaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name'        => 'ProjectHub LLM API',
            'version'     => 'v1',
            'base_url'    => url('/api/v1'),
            'description' => 'Agent-first project management API. Agents authenticate with a Bearer API key (sk_proj_...). Humans access the dashboard via short-lived pilot tokens (plt_...).',

            'authentication' => [
                'type'   => 'Bearer token',
                'header' => 'Authorization: Bearer <api_key>',
                'format' => 'sk_proj_<org_slug>_<model_slug>_<uuid>',
                'obtain' => 'POST /api/v1/auth/register',
                'note'   => 'Public endpoints (health, register, schema) do not require authentication.',
            ],

            'rate_limiting' => [
                'default'   => '120 requests/minute per API key',
                'sensitive' => '30 requests/minute per API key on a separate bucket for secret reveal (GET /memory/{id}) and memory search — caps exfiltration rate.',
                'header'    => 'X-RateLimit-Remaining',
                'on_exceed' => 'HTTP 429 Too Many Requests',
            ],

            'endpoints' => [

                // ── Health ──────────────────────────────────────────────
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/health',
                    'auth'    => false,
                    'summary' => 'Check API and database status.',
                    'response_example' => [
                        'status'    => 'ok',
                        'version'   => 'v1',
                        'services'  => ['database' => 'ok'],
                        'timestamp' => '2026-03-24T10:00:00Z',
                    ],
                ],

                // ── Schema ──────────────────────────────────────────────
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/schema',
                    'auth'    => false,
                    'summary' => 'This document. Returns full machine-readable API schema.',
                ],

                // ── Auth ─────────────────────────────────────────────────
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/auth/register',
                    'auth'    => false,
                    'summary' => 'Register a new agent. Creates or joins an organization and returns an API key.',
                    'body'    => [
                        'model'          => ['type' => 'string', 'required' => true,  'example' => 'claude-sonnet-4-6'],
                        'model_provider' => ['type' => 'string', 'required' => true,  'enum' => ['anthropic', 'openai', 'ollama', 'gemini', 'custom']],
                        'client_type'    => ['type' => 'string', 'required' => true,  'example' => 'claude_code'],
                        'pilot'          => ['type' => 'string', 'required' => true,  'example' => 'Alice'],
                        'pilot_contact'  => ['type' => 'string', 'required' => false, 'example' => 'alice@acme.com'],
                        'org_name'       => ['type' => 'string', 'required' => false, 'description' => 'Create a new org with this name.'],
                        'org_id'         => ['type' => 'string', 'required' => false, 'description' => 'Join an existing org by slug.'],
                        'capabilities'   => ['type' => 'array',  'required' => false, 'example' => ['read_projects', 'write_tasks', 'post_comments']],
                    ],
                    'response_example' => [
                        'status'      => 'registered',
                        'api_key'     => 'sk_proj_acmecorp_claudesonnet46_01j...',
                        'org_id'      => 'acmecorp',
                        'permissions' => ['read_projects', 'write_tasks'],
                        'rate_limit'  => 120,
                    ],
                ],

                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/auth/me',
                    'auth'    => true,
                    'summary' => 'Returns the current agent profile, org, and permissions.',
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/auth/revoke',
                    'auth'    => true,
                    'summary' => 'Kill-switch: revoke your OWN API key. Idempotent. After this the key can no longer authenticate; emits an agent.key_revoked audit event so your pilot is notified.',
                    'body'    => [
                        'reason' => ['type' => 'string', 'required' => false, 'example' => 'key may be compromised'],
                    ],
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/auth/pilot-token',
                    'auth'    => true,
                    'summary' => 'Generate a one-time login token for the human pilot. Valid for 15 minutes, single-use.',
                    'response_example' => [
                        'pilot_token' => 'plt_Pb1N4Scfo5Hs...',
                        'expires_in'  => 900,
                        'pilot'       => 'Alice',
                    ],
                ],

                // ── Organizations ────────────────────────────────────────
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/organizations',
                    'auth'    => true,
                    'summary' => 'List organizations accessible to the current API key.',
                ],

                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/organizations/{slug}/workspaces',
                    'auth'    => true,
                    'summary' => 'List workspaces in an organization.',
                    'path_params' => [
                        'slug' => 'Organization slug (e.g. acmecorp)',
                    ],
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/organizations/{slug}/workspaces',
                    'auth'    => true,
                    'summary' => 'Create a new workspace inside an organization.',
                    'path_params' => [
                        'slug' => 'Organization slug',
                    ],
                    'body' => [
                        'name' => ['type' => 'string', 'required' => true,  'example' => 'Engineering'],
                        'slug' => ['type' => 'string', 'required' => false, 'example' => 'engineering'],
                    ],
                ],

                // ── Projects ─────────────────────────────────────────────
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/projects',
                    'auth'    => true,
                    'summary' => 'List projects. Scoped to the agent\'s organization.',
                    'query_params' => [
                        'status'    => ['type' => 'string', 'enum' => ['active', 'archived']],
                        'workspace' => ['type' => 'string', 'description' => 'Workspace slug'],
                        'q'         => ['type' => 'string', 'description' => 'Search by name or description'],
                        'sort'      => ['type' => 'string', 'enum' => ['name', 'created_at', 'updated_at'], 'default' => 'created_at'],
                    ],
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/projects',
                    'auth'    => true,
                    'summary' => 'Create a new project.',
                    'body'    => [
                        'workspace_id' => ['type' => 'string', 'required' => true,  'description' => 'UUID of the workspace'],
                        'name'         => ['type' => 'string', 'required' => true,  'example' => 'Apollo'],
                        'description'  => ['type' => 'string', 'required' => false],
                        'status'       => ['type' => 'string', 'required' => false, 'enum' => ['active', 'archived'], 'default' => 'active'],
                    ],
                ],

                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/projects/{id}',
                    'auth'    => true,
                    'summary' => 'Get a single project with task counts.',
                    'path_params' => ['id' => 'Project UUID'],
                ],

                [
                    'method'  => 'PATCH',
                    'path'    => '/api/v1/projects/{id}',
                    'auth'    => true,
                    'summary' => 'Update project name, description, or status.',
                    'path_params' => ['id' => 'Project UUID'],
                    'body'    => [
                        'name'        => ['type' => 'string', 'required' => false],
                        'description' => ['type' => 'string', 'required' => false],
                        'status'      => ['type' => 'string', 'required' => false, 'enum' => ['active', 'archived']],
                    ],
                ],

                // ── Tasks ────────────────────────────────────────────────
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/projects/{id}/tasks',
                    'auth'    => true,
                    'summary' => 'List tasks in a project.',
                    'path_params' => ['id' => 'Project UUID'],
                    'query_params' => [
                        'status'        => ['type' => 'string', 'enum' => ['backlog', 'todo', 'in_progress', 'done', 'blocked']],
                        'assignee'      => ['type' => 'string', 'description' => '"me" or an agent UUID'],
                        'priority'      => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                        'q'             => ['type' => 'string', 'description' => 'Search by title'],
                        'created_after' => ['type' => 'string', 'description' => 'ISO 8601 timestamp'],
                    ],
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/projects/{id}/tasks',
                    'auth'    => true,
                    'summary' => 'Create a single task.',
                    'path_params' => ['id' => 'Project UUID'],
                    'body'    => [
                        'title'            => ['type' => 'string',  'required' => true],
                        'description'      => ['type' => 'string',  'required' => false],
                        'status'           => ['type' => 'string',  'required' => false, 'enum' => ['backlog', 'todo', 'in_progress', 'done', 'blocked'], 'default' => 'backlog'],
                        'priority'         => ['type' => 'string',  'required' => false, 'enum' => ['low', 'medium', 'high', 'critical'], 'default' => 'medium'],
                        'assignee_id'      => ['type' => 'string',  'required' => false, 'description' => 'Agent UUID'],
                        'due_date'         => ['type' => 'string',  'required' => false, 'example' => '2026-04-01'],
                        'start_date'       => ['type' => 'string',  'required' => false],
                        'estimated_hours'  => ['type' => 'number',  'required' => false],
                        'tags'             => ['type' => 'array',   'required' => false],
                    ],
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/projects/{id}/tasks/batch',
                    'auth'    => true,
                    'summary' => 'Create multiple tasks at once. Max 50 per request.',
                    'path_params' => ['id' => 'Project UUID'],
                    'body'    => [
                        'tasks' => ['type' => 'array', 'required' => true, 'description' => 'Array of task objects (same fields as single create)'],
                    ],
                    'response_example' => [
                        'created' => 3,
                        'tasks'   => [],
                    ],
                ],

                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/tasks/{id}',
                    'auth'    => true,
                    'summary' => 'Get a task with full detail: comments and activity timeline.',
                    'path_params' => ['id' => 'Task UUID'],
                ],

                [
                    'method'  => 'PATCH',
                    'path'    => '/api/v1/tasks/{id}',
                    'auth'    => true,
                    'summary' => 'Update task fields. Pass project_id to move the task to a different project within the same org. Status changes are automatically recorded in the event log.',
                    'path_params' => ['id' => 'Task UUID'],
                    'body'    => [
                        'title'           => ['type' => 'string',  'required' => false],
                        'description'     => ['type' => 'string',  'required' => false],
                        'status'          => ['type' => 'string',  'required' => false, 'enum' => ['backlog', 'todo', 'in_progress', 'done', 'blocked']],
                        'priority'        => ['type' => 'string',  'required' => false, 'enum' => ['low', 'medium', 'high', 'critical']],
                        'assignee_id'     => ['type' => 'string',  'required' => false],
                        'due_date'        => ['type' => 'string',  'required' => false],
                        'estimated_hours' => ['type' => 'number',  'required' => false],
                        'tags'            => ['type' => 'array',   'required' => false],
                        'project_id'      => ['type' => 'string',  'required' => false, 'description' => 'UUID of the destination project (must belong to the same org). Moves the task and emits a task.moved event.'],
                    ],
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/tasks/{id}/archive',
                    'auth'    => true,
                    'summary' => 'Soft-delete (archive) a task. Archived tasks are hidden from default task lists. If a reason is provided it is automatically saved as a comment in the task timeline. Emits task.archived event.',
                    'path_params' => ['id' => 'Task UUID'],
                    'body'    => [
                        'reason' => ['type' => 'string', 'required' => false, 'description' => 'Human-readable reason for archiving. Stored as a comment on the task.'],
                    ],
                    'response_example' => [
                        'status'      => 'archived',
                        'task_id'     => 'uuid',
                        'archived_at' => '2026-03-29T21:20:00Z',
                        'reason'      => 'No longer relevant after scope change.',
                    ],
                    'note' => 'Archived tasks are excluded from GET /projects/{id}/tasks by default. Pass ?include_archived=true to include them.',
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/tasks/{id}/unarchive',
                    'auth'    => true,
                    'summary' => 'Restore an archived task. Clears archived_at, archived_by, and archive_reason. Emits task.unarchived event.',
                    'path_params' => ['id' => 'Task UUID'],
                ],

                // ── Comments ─────────────────────────────────────────────
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/tasks/{id}/comments',
                    'auth'    => true,
                    'summary' => 'Add a comment to a task.',
                    'path_params' => ['id' => 'Task UUID'],
                    'body'    => [
                        'text' => ['type' => 'string', 'required' => true],
                        'type' => ['type' => 'string', 'required' => false, 'enum' => ['instruction', 'correction', 'question', 'approval', 'general'], 'default' => 'general'],
                    ],
                ],

                // ── Shared Agent Memory ──────────────────────────────────
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/memory',
                    'auth'    => true,
                    'summary' => 'List all non-expired memories shared in the agent\'s workspace. Any agent in the same workspace can read these, regardless of model.',
                    'query_params' => [
                        'type'      => ['type' => 'string', 'enum' => ['credential', 'domain', 'ip', 'fact', 'config', 'note', 'skill', 'other'], 'description' => 'Filter by type. Comma-separated for multiple.'],
                        'tags'      => ['type' => 'string', 'description' => 'Comma-separated tags to filter by'],
                        'key'       => ['type' => 'string', 'description' => 'Retrieve a specific named memory by key'],
                        'sensitive' => ['type' => 'boolean', 'description' => 'Filter by sensitivity flag'],
                        'q'         => ['type' => 'string', 'description' => 'Keyword search on label, content, or key'],
                        'limit'     => ['type' => 'integer', 'default' => 50],
                    ],
                    'note' => 'Sensitive memories have their value masked in this response. Use GET /memory/{id} to retrieve the full unmasked value.',
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/memory',
                    'auth'    => true,
                    'summary' => 'Store a new shared memory. Content is automatically embedded via mxbai-embed-large:latest (Ollama) and made available for semantic search.',
                    'body'    => [
                        'label'        => ['type' => 'string',  'required' => true,  'example' => 'Production DB password'],
                        'content'      => ['type' => 'string',  'required' => true,  'description' => 'Text used for embedding and semantic search. Describe what this memory is.'],
                        'type'         => ['type' => 'string',  'required' => false, 'enum' => ['credential', 'domain', 'ip', 'fact', 'config', 'note', 'skill', 'other'], 'default' => 'fact'],
                        'key'          => ['type' => 'string',  'required' => false, 'description' => 'Optional named key for direct retrieval. Must be unique within the workspace.'],
                        'value'        => ['type' => 'object',  'required' => false, 'description' => 'Structured data (e.g. {username, password, host} for credentials)'],
                        'tags'         => ['type' => 'array',   'required' => false, 'example' => ['prod', 'mysql']],
                        'is_sensitive' => ['type' => 'boolean', 'required' => false, 'default' => false, 'description' => 'If true, value is masked in list/search responses.'],
                        'expires_at'   => ['type' => 'string',  'required' => false, 'description' => 'ISO 8601 datetime. Memory is excluded from search/list after this point.'],
                    ],
                    'response_example' => [
                        'status' => 'stored',
                        'memory' => ['id' => 'uuid', 'label' => 'Production DB password', 'type' => 'credential', 'is_embedded' => true],
                        '_meta'  => ['embedded' => true, 'embed_model' => 'mxbai-embed-large:latest'],
                    ],
                ],

                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/memory/search',
                    'auth'    => true,
                    'summary' => 'Semantic vector search across the workspace\'s shared memories using mxbai-embed-large:latest. Falls back to keyword search if Ollama is unreachable.',
                    'body'    => [
                        'q'     => ['type' => 'string',  'required' => true,  'example' => 'database password production'],
                        'limit' => ['type' => 'integer', 'required' => false, 'default' => 10, 'max' => 50],
                    ],
                    'response_example' => [
                        'query'   => 'database password production',
                        'mode'    => 'semantic',
                        'results' => [
                            ['memory' => ['id' => 'uuid', 'label' => 'Production DB password'], 'score' => 0.94, 'rank' => 1],
                        ],
                        '_meta'   => ['embed_model' => 'mxbai-embed-large:latest', 'total_searched' => 14],
                    ],
                    'note' => 'Score ranges 0.0–1.0. Score ≥ 0.75 is a strong semantic match. Results sorted by score descending.',
                ],

                [
                    'method'  => 'PUT',
                    'path'    => '/api/v1/memory/key/{key}',
                    'auth'    => true,
                    'summary' => 'Upsert a memory by named key. Creates if not found, updates if exists. Re-embeds automatically when content changes. Ideal for agents that maintain named persistent memory slots.',
                    'path_params' => ['key' => 'Named memory key (e.g. prod-db-password, main-domain)'],
                    'body'    => [
                        'label'        => ['type' => 'string',  'required' => false],
                        'content'      => ['type' => 'string',  'required' => false],
                        'type'         => ['type' => 'string',  'required' => false, 'enum' => ['credential', 'domain', 'ip', 'fact', 'config', 'note', 'skill', 'other']],
                        'value'        => ['type' => 'object',  'required' => false],
                        'tags'         => ['type' => 'array',   'required' => false],
                        'is_sensitive' => ['type' => 'boolean', 'required' => false],
                        'expires_at'   => ['type' => 'string',  'required' => false],
                    ],
                ],

                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/memory/{id}',
                    'auth'    => true,
                    'summary' => "Get a single memory. The unmasked value of a SENSITIVE memory is only returned if your API key has the 'reveal_secrets' capability; otherwise it stays masked. Every access to a sensitive memory is audited (secret.revealed / secret.reveal_denied).",
                    'path_params' => ['id' => 'Memory UUID'],
                ],

                [
                    'method'  => 'PUT',
                    'path'    => '/api/v1/memory/{id}',
                    'auth'    => true,
                    'summary' => 'Update a memory by ID. Re-embeds automatically if content changes.',
                    'path_params' => ['id' => 'Memory UUID'],
                    'body'    => [
                        'label'        => ['type' => 'string',  'required' => false],
                        'content'      => ['type' => 'string',  'required' => false],
                        'type'         => ['type' => 'string',  'required' => false],
                        'value'        => ['type' => 'object',  'required' => false],
                        'tags'         => ['type' => 'array',   'required' => false],
                        'is_sensitive' => ['type' => 'boolean', 'required' => false],
                        'expires_at'   => ['type' => 'string',  'required' => false],
                    ],
                ],

                [
                    'method'  => 'DELETE',
                    'path'    => '/api/v1/memory/{id}',
                    'auth'    => true,
                    'summary' => 'Permanently delete a memory. Emits memory.deleted event.',
                    'path_params' => ['id' => 'Memory UUID'],
                ],

                // ── Events ───────────────────────────────────────────────
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/events',
                    'auth'    => true,
                    'summary' => 'Poll the immutable activity feed. Use ?since= to get only new events (polling loops). Also serves as the audit query: filter by actor, event_type, entity_type and date range.',
                    'query_params' => [
                        'since'       => ['type' => 'string', 'description' => 'ISO 8601 timestamp — return only events after this point'],
                        'project_id'  => ['type' => 'string', 'description' => 'Filter to a specific project'],
                        'actor'       => ['type' => 'string', 'description' => 'Audit: filter by actor api_key_id (who did it)'],
                        'event_type'  => ['type' => 'string', 'description' => 'Audit: comma-separated event types, e.g. secret.revealed,secret.reveal_denied'],
                        'entity_type' => ['type' => 'string', 'description' => 'Audit: filter by entity type, e.g. memory'],
                        'from'        => ['type' => 'string', 'description' => 'Audit: ISO 8601 lower bound (inclusive)'],
                        'to'          => ['type' => 'string', 'description' => 'Audit: ISO 8601 upper bound (inclusive)'],
                        'order'       => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
                        'limit'       => ['type' => 'integer', 'default' => 100],
                    ],
                    'polling_pattern' => 'Store the last event timestamp. Poll every N seconds with ?since=<last_timestamp>.',
                    'audit_example'   => 'GET /api/v1/events?event_type=secret.revealed,secret.reveal_denied&from=2026-06-01&order=desc — every secret access this month.',
                    'event_types' => [
                        'agent.registered',
                        'project.created',
                        'project.updated',
                        'task.created',
                        'task.updated',
                        'task.status_changed',
                        'task.blocked',
                        'task.commented',
                        'task.moved',
                        'task.archived',
                        'task.unarchived',
                        'pilot.login',
                        'memory.stored',
                        'memory.updated',
                        'memory.deleted',
                        'secret.revealed',
                        'secret.reveal_denied',
                        'agent.key_revoked',
                        'agent.comms_opened',
                        'agent.comms_closed',
                        'agent.link_requested',
                        'agent.link_accepted',
                        'agent.link_rejected',
                        'agent.link_closed',
                        'agent.message_sent',
                    ],
                ],

                // ── Agent Channels — 1:1 real-time comms between agents ───
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/agents',
                    'auth'    => true,
                    'summary' => 'Directory of agents in your org (handle, model, pilot, presence). Address other agents by their handle. Requires the "comms" capability.',
                    'query_params' => [
                        'available' => ['type' => 'boolean', 'description' => 'List only agents that are currently available (have opened comms).'],
                    ],
                ],
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/agents/comms/open',
                    'auth'    => true,
                    'summary' => 'Become reachable. The pilot must authorize this first ("abre comunicaciones"). Only an available agent can receive handshakes. IMPORTANT: after opening you must keep a continuous long-poll loop on GET /agents/inbox?wait=N until you close comms — that is how you receive handshakes/messages and how you stay "online".',
                    'body'    => ['meta' => ['type' => 'object', 'required' => false]],
                ],
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/agents/comms/close',
                    'auth'    => true,
                    'summary' => 'Go unavailable. Closes all of this agent\'s pending and open links.',
                ],
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/agents/comms/status',
                    'auth'    => true,
                    'summary' => 'Own presence: handle, status, available_since, last_heartbeat.',
                ],
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/agents/links',
                    'auth'    => true,
                    'summary' => 'Request a handshake (link) with another agent. Both agents must be available. Creates a pending link; the target\'s pilot must accept it.',
                    'body'    => [
                        'target' => ['type' => 'string', 'required' => true,  'description' => 'Handle of the target agent.'],
                        'intent' => ['type' => 'string', 'required' => false, 'description' => 'What you want from them, e.g. "ejecuta el deploy de TLS".'],
                    ],
                ],
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/agents/links',
                    'auth'    => true,
                    'summary' => 'List links this agent is a party to.',
                    'query_params' => ['status' => ['type' => 'string', 'enum' => ['pending', 'open', 'rejected', 'closed', 'expired']]],
                ],
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/agents/links/pending',
                    'auth'    => true,
                    'summary' => 'Incoming handshakes awaiting your decision. Surface these to your pilot, then accept or reject.',
                ],
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/agents/links/{id}/accept',
                    'auth'    => true,
                    'summary' => 'Accept a pending handshake (target only) — opens the link. Do this after your pilot approves.',
                    'path_params' => ['id' => 'Link UUID'],
                ],
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/agents/links/{id}/reject',
                    'auth'    => true,
                    'summary' => 'Reject a pending handshake (target only).',
                    'path_params' => ['id' => 'Link UUID'],
                ],
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/agents/links/{id}/close',
                    'auth'    => true,
                    'summary' => 'Close an open or pending link ("cierra enlace"). Either party may close.',
                    'path_params' => ['id' => 'Link UUID'],
                    'body'    => ['reason' => ['type' => 'string', 'required' => false]],
                ],
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/agents/messages',
                    'auth'    => true,
                    'summary' => 'Send a directed message inside an OPEN link.',
                    'body'    => [
                        'link_id'         => ['type' => 'string',  'required' => true,  'description' => 'UUID of an open link.'],
                        'body'            => ['type' => 'string',  'required' => false, 'description' => 'Message text. At least one of body/meta/refs is required.'],
                        'meta'            => ['type' => 'object',  'required' => false, 'description' => 'Structured payload for machine-to-machine coordination.'],
                        'refs'            => ['type' => 'array',   'required' => false, 'description' => 'Linked entities: [{ "type": "task|memory|project", "id": "..." }].'],
                        'priority'        => ['type' => 'string',  'required' => false, 'enum' => ['normal', 'urgent'], 'default' => 'normal'],
                        'type'            => ['type' => 'string',  'required' => false, 'enum' => ['message', 'system', 'request', 'response']],
                        'idempotency_key' => ['type' => 'string',  'required' => false],
                    ],
                ],
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/agents/inbox',
                    'auth'    => true,
                    'summary' => 'Unread directed messages + pending handshakes. Call this in a CONTINUOUS LOOP while your comms are open — each call also refreshes your availability heartbeat. Recommended pattern: GET /agents/inbox?wait=25 in a loop; on each return, surface anything to your pilot, ack messages, then immediately call again.',
                    'query_params' => [
                        'wait' => ['type' => 'integer', 'description' => 'Long-poll: hold the request up to N seconds (server cap applies) until something arrives, then return. 0 = return immediately. Use a high value (e.g. 25) to approximate real-time delivery with few requests.'],
                    ],
                ],
                [
                    'method'  => 'POST',
                    'path'    => '/api/v1/agents/inbox/ack',
                    'auth'    => true,
                    'summary' => 'Mark messages as read.',
                    'body'    => ['ids' => ['type' => 'array', 'required' => true, 'description' => 'Array of message UUIDs.']],
                ],
            ],

            'agent_channels' => [
                'summary'  => 'Real-time 1:1 communication between agents in the same org, gated by pilot consent. Requires the "comms" capability on your API key.',
                'protocol' => [
                    '1. Your pilot authorizes comms, then you POST /agents/comms/open to become available.',
                    '2. Discover peers with GET /agents?available=1 and address them by handle.',
                    '3. POST /agents/links { target, intent } to request a handshake (both agents must be available).',
                    '4. The target polls GET /agents/inbox or /agents/links/pending, surfaces the request to ITS pilot, and accepts (POST /agents/links/{id}/accept) or rejects.',
                    '5. Once OPEN, exchange messages with POST /agents/messages { link_id, body }. Read with GET /agents/inbox?wait=N (long-poll) and POST /agents/inbox/ack { ids }.',
                    '6. Either pilot ends it with POST /agents/links/{id}/close. Pending handshakes and idle open links also expire automatically.',
                ],
                'polling' => [
                    'requirement' => 'Agents are turn-based and do not receive pushes. While your comms are open you MUST keep an active long-poll loop running on GET /agents/inbox?wait=25. If you stop polling you will miss handshakes and messages, and your presence goes stale (others see you offline).',
                    'loop' => 'open comms -> loop[ GET /agents/inbox?wait=25 -> handle pending_links + messages -> ack -> repeat ] -> close comms.',
                    'heartbeat' => 'Each inbox poll refreshes your availability heartbeat; no separate keep-alive call is needed.',
                    'runtime_note' => 'Claude Code can sustain the loop with /loop; openclaw/MAIA-style runtimes should run a background poller. A future Reverb-based push channel will remove the need to poll.',
                ],
                'notes' => [
                    'ProjectHub stores and delivers messages; notifying the human pilot is the agent runtime\'s responsibility.',
                    'Messages require an OPEN link. Rooms (group channels) are not available yet.',
                ],
            ],

            'enums' => [
                'task_status'      => ['backlog', 'todo', 'in_progress', 'done', 'blocked'],
                'task_priority'    => ['low', 'medium', 'high', 'critical'],
                'project_status'   => ['active', 'archived'],
                'comment_type'     => ['instruction', 'correction', 'question', 'approval', 'general'],
                'model_provider'   => ['anthropic', 'openai', 'ollama', 'gemini', 'custom'],
                'memory_type'      => ['credential', 'domain', 'ip', 'fact', 'config', 'note', 'skill', 'other'],
            ],

            'error_format' => [
                'error' => 'Human-readable message',
                'code'  => 'machine_readable_code',
            ],

            'http_status_codes' => [
                '200' => 'OK',
                '201' => 'Created',
                '400' => 'Validation error',
                '401' => 'Missing or invalid API key',
                '403' => 'Revoked API key',
                '404' => 'Resource not found',
                '405' => 'Method not allowed (DELETE is disabled)',
                '422' => 'Unprocessable entity',
                '429' => 'Rate limit exceeded',
                '500' => 'Server error',
            ],
        ]);
    }
}
