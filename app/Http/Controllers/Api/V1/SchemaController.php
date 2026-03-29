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

                // ── Events ───────────────────────────────────────────────
                [
                    'method'  => 'GET',
                    'path'    => '/api/v1/events',
                    'auth'    => true,
                    'summary' => 'Poll the immutable activity feed. Use ?since= to get only new events. Ideal for agent polling loops.',
                    'query_params' => [
                        'since'      => ['type' => 'string', 'description' => 'ISO 8601 timestamp — return only events after this point'],
                        'project_id' => ['type' => 'string', 'description' => 'Filter to a specific project'],
                    ],
                    'polling_pattern' => 'Store the last event timestamp. Poll every N seconds with ?since=<last_timestamp>.',
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
                    ],
                ],
            ],

            'enums' => [
                'task_status'      => ['backlog', 'todo', 'in_progress', 'done', 'blocked'],
                'task_priority'    => ['low', 'medium', 'high', 'critical'],
                'project_status'   => ['active', 'archived'],
                'comment_type'     => ['instruction', 'correction', 'question', 'approval', 'general'],
                'model_provider'   => ['anthropic', 'openai', 'ollama', 'gemini', 'custom'],
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
