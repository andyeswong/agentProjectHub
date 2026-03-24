<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    stats: Object,
    recentEvents: Array,
})

const org = computed(() => usePage().props.auth?.org)

// ── Event feed ──────────────────────────────────────────────
const eventColor = (type) => {
    if (type.includes('created'))        return 'var(--color-success)'
    if (type.includes('status_changed')) return 'var(--color-accent)'
    if (type.includes('blocked'))        return 'var(--color-danger)'
    if (type.includes('commented'))      return 'var(--color-warning)'
    if (type.includes('registered'))     return 'var(--color-accent)'
    return 'var(--color-neutral)'
}

// ── Invite panel ─────────────────────────────────────────────
const inviteOpen = ref(false)

const invite = ref({
    colleague_name: '',
    colleague_contact: '',
    model: '',
    provider: 'anthropic',
    permissions: ['read_projects', 'write_tasks', 'post_comments'],
})

const providers = [
    { value: 'anthropic', label: 'Anthropic',     example: 'claude-sonnet-4-6' },
    { value: 'openai',    label: 'OpenAI',         example: 'gpt-4o' },
    { value: 'ollama',    label: 'Ollama',          example: 'llama3.2' },
    { value: 'gemini',    label: 'Gemini',          example: 'gemini-2.0-flash' },
    { value: 'custom',    label: 'Custom',          example: 'my-model' },
]

const selectedProvider = computed(() => providers.find(p => p.value === invite.value.provider))

const permissionOptions = [
    { value: 'read_projects',  label: 'Read projects' },
    { value: 'write_tasks',    label: 'Write tasks' },
    { value: 'post_comments',  label: 'Post comments' },
    { value: 'manage_agents',  label: 'Manage agents' },
    { value: 'admin',          label: 'Admin' },
]

function togglePerm(perm) {
    const idx = invite.value.permissions.indexOf(perm)
    if (idx === -1) invite.value.permissions.push(perm)
    else invite.value.permissions.splice(idx, 1)
}

const invitePrompt = computed(() => {
    const model    = invite.value.model || selectedProvider.value.example
    const pilot    = invite.value.colleague_name || 'Your Name'
    const contact  = invite.value.colleague_contact || ''
    const orgSlug  = org.value?.slug ?? 'your-org'
    const orgName  = org.value?.name ?? 'your organization'
    const perms    = JSON.stringify(invite.value.permissions)
    const base     = window.location.origin

    return `You have been invited to join ${orgName} on ProjectHub LLM.

Please register by making the following HTTP request:

POST ${base}/api/v1/auth/register
Content-Type: application/json

{
  "org_id": "${orgSlug}",
  "model": "${model}",
  "model_provider": "${invite.value.provider}",
  "client_type": "api",
  "pilot": "${pilot}",${contact ? `\n  "pilot_contact": "${contact}",` : ''}
  "capabilities": ${perms}
}

After registering, save the \`api_key\` from the response — you'll need it for all API calls.

Then generate a pilot token so you can access the dashboard:

POST ${base}/api/v1/auth/pilot-token
Authorization: Bearer <your api_key>

Give the \`pilot_token\` from the response to ${pilot} so they can log in at:
${base}/login`
})

const copied = ref(false)
function copyPrompt() {
    navigator.clipboard.writeText(invitePrompt.value)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold" style="color: var(--color-text-primary);">Dashboard</h1>

                <!-- Invite button -->
                <button @click="inviteOpen = !inviteOpen"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all"
                    :style="inviteOpen
                        ? 'background-color: rgba(56,189,248,0.15); color: var(--color-accent); border: 1px solid rgba(56,189,248,0.3);'
                        : 'background-color: var(--color-surface-elevated); color: var(--color-text-secondary); border: 1px solid var(--color-surface-border);'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Invite Agent
                </button>
            </div>

            <!-- ── Invite panel ── -->
            <div v-if="inviteOpen" class="rounded-xl overflow-hidden" style="border: 1px solid rgba(56,189,248,0.25); background-color: var(--color-surface-elevated);">

                <!-- Panel header -->
                <div class="flex items-center justify-between px-6 py-4"
                    style="border-bottom: 1px solid var(--color-surface-border); background-color: rgba(56,189,248,0.04);">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                            style="background-color: rgba(56,189,248,0.12); border: 1px solid rgba(56,189,248,0.2);">
                            <svg class="w-4 h-4" style="color: var(--color-accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold" style="color: var(--color-text-primary);">Invite an Agent</h2>
                            <p class="text-xs" style="color: var(--color-text-muted);">
                                Generate a prompt your colleague can paste into their AI agent to join
                                <span style="color: var(--color-accent);">{{ org?.name }}</span>
                            </p>
                        </div>
                    </div>
                    <button @click="inviteOpen = false" class="p-1 rounded" style="color: var(--color-text-muted);"
                        onmouseover="this.style.color='var(--color-text-primary)'"
                        onmouseout="this.style.color='var(--color-text-muted)'">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-0" style="min-height: 0;">

                    <!-- Left: form -->
                    <div class="p-6 space-y-4" style="border-right: 1px solid var(--color-surface-border);">
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--color-text-muted);">Customize the invitation</p>

                        <!-- Colleague name + contact -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs mb-1" style="color: var(--color-text-secondary);">Colleague's name</label>
                                <input v-model="invite.colleague_name" type="text" placeholder="Bob"
                                    class="w-full px-3 py-2 rounded-lg text-xs outline-none"
                                    style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary);"
                                    onfocus="this.style.borderColor='var(--color-accent)'"
                                    onblur="this.style.borderColor='var(--color-surface-border)'" />
                            </div>
                            <div>
                                <label class="block text-xs mb-1" style="color: var(--color-text-secondary);">Contact (optional)</label>
                                <input v-model="invite.colleague_contact" type="text" placeholder="bob@acme.com"
                                    class="w-full px-3 py-2 rounded-lg text-xs outline-none"
                                    style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary);"
                                    onfocus="this.style.borderColor='var(--color-accent)'"
                                    onblur="this.style.borderColor='var(--color-surface-border)'" />
                            </div>
                        </div>

                        <!-- Provider -->
                        <div>
                            <label class="block text-xs mb-2" style="color: var(--color-text-secondary);">AI Provider</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="p in providers" :key="p.value"
                                    type="button"
                                    @click="invite.provider = p.value; invite.model = ''"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all"
                                    :style="invite.provider === p.value
                                        ? 'background-color: rgba(56,189,248,0.15); border: 1px solid var(--color-accent); color: var(--color-accent);'
                                        : 'background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-secondary);'">
                                    {{ p.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Model -->
                        <div>
                            <label class="block text-xs mb-1" style="color: var(--color-text-secondary);">Model <span style="color: var(--color-text-muted);">(optional)</span></label>
                            <input v-model="invite.model" type="text" :placeholder="selectedProvider.example"
                                class="w-full px-3 py-2 rounded-lg text-xs outline-none"
                                style="font-family: var(--font-mono); background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary);"
                                onfocus="this.style.borderColor='var(--color-accent)'"
                                onblur="this.style.borderColor='var(--color-surface-border)'" />
                        </div>

                        <!-- Permissions -->
                        <div>
                            <label class="block text-xs mb-2" style="color: var(--color-text-secondary);">Permissions to grant</label>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="perm in permissionOptions" :key="perm.value"
                                    type="button"
                                    @click="togglePerm(perm.value)"
                                    class="text-xs px-2.5 py-1 rounded-full transition-all"
                                    :style="invite.permissions.includes(perm.value)
                                        ? 'background-color: rgba(56,189,248,0.15); border: 1px solid var(--color-accent); color: var(--color-accent);'
                                        : 'background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-secondary);'">
                                    {{ perm.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: generated prompt -->
                    <div class="flex flex-col" style="min-height: 0;">
                        <div class="flex items-center justify-between px-4 py-3"
                            style="border-bottom: 1px solid var(--color-surface-border); background-color: var(--color-surface-base);">
                            <div class="flex items-center gap-1.5">
                                <div class="w-2.5 h-2.5 rounded-full" style="background-color: rgba(239,68,68,0.4);"></div>
                                <div class="w-2.5 h-2.5 rounded-full" style="background-color: rgba(245,158,11,0.4);"></div>
                                <div class="w-2.5 h-2.5 rounded-full" style="background-color: rgba(34,197,94,0.4);"></div>
                                <span class="ml-2 text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">invite_prompt.txt</span>
                            </div>
                            <button @click="copyPrompt"
                                class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-md transition-all"
                                :style="copied
                                    ? 'background-color: rgba(34,197,94,0.15); color: var(--color-success); border: 1px solid rgba(34,197,94,0.3);'
                                    : 'background-color: var(--color-surface-elevated); color: var(--color-text-secondary); border: 1px solid var(--color-surface-border);'">
                                <svg v-if="!copied" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ copied ? 'Copied!' : 'Copy prompt' }}
                            </button>
                        </div>
                        <pre class="flex-1 p-4 text-xs leading-relaxed whitespace-pre-wrap overflow-auto"
                            style="background-color: var(--color-surface-base); color: var(--color-text-secondary); font-family: var(--font-mono); max-height: 360px;">{{ invitePrompt }}</pre>
                    </div>
                </div>

                <!-- How it works footer -->
                <div class="px-6 py-4 flex flex-wrap gap-6" style="border-top: 1px solid var(--color-surface-border); background-color: rgba(56,189,248,0.02);">
                    <div v-for="(item, i) in [
                        { icon: '📋', text: 'Colleague copies this prompt into their AI agent' },
                        { icon: '🔑', text: 'Agent registers and gets an API key for this org' },
                        { icon: '🎫', text: 'Agent generates a pilot token for the colleague' },
                        { icon: '✅', text: 'Colleague logs in at /login with their token' },
                    ]" :key="i" class="flex items-center gap-2 text-xs" style="color: var(--color-text-muted);">
                        <span>{{ item.icon }}</span>
                        <span>{{ item.text }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Stat cards ── -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div v-for="(item, key) in [
                    { label: 'Projects',      value: stats.projects,      color: 'var(--color-accent)' },
                    { label: 'Open Tasks',    value: stats.open_tasks,    color: 'var(--color-warning)' },
                    { label: 'Blocked',       value: stats.blocked_tasks, color: 'var(--color-danger)' },
                    { label: 'Active Agents', value: stats.agents,        color: 'var(--color-success)' },
                ]" :key="key"
                    class="rounded-lg p-5"
                    style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);"
                >
                    <p class="text-xs mb-2" style="color: var(--color-text-muted);">{{ item.label }}</p>
                    <p class="text-3xl font-bold" :style="{ color: item.color }">{{ item.value }}</p>
                </div>
            </div>

            <!-- ── Activity Feed ── -->
            <div class="rounded-lg" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                <div class="px-5 py-4 border-b" style="border-color: var(--color-surface-border);">
                    <h2 class="text-sm font-medium" style="color: var(--color-text-primary);">Activity Feed</h2>
                </div>

                <div v-if="recentEvents.length === 0" class="px-5 py-8 text-center text-sm" style="color: var(--color-text-muted);">
                    No activity yet.
                </div>

                <ul v-else class="divide-y" style="--tw-divide-color: var(--color-surface-border);">
                    <li v-for="event in recentEvents" :key="event.id"
                        class="flex items-start gap-4 px-5 py-4"
                    >
                        <div class="mt-1.5 w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: eventColor(event.type) }"></div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-medium" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ event.type }}</span>
                                <span class="text-xs" style="color: var(--color-accent);">{{ event.actor_model }}</span>
                                <span v-if="event.actor_pilot" class="text-xs" style="color: var(--color-text-muted);">via {{ event.actor_pilot }}</span>
                            </div>
                            <p v-if="event.payload && Object.keys(event.payload).length" class="text-xs mt-0.5 truncate" style="color: var(--color-text-muted);">
                                {{ Object.entries(event.payload).map(([k,v]) => `${k}: ${v}`).join(' · ') }}
                            </p>
                        </div>

                        <span class="text-xs shrink-0" style="color: var(--color-text-muted);">{{ event.time_ago }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
