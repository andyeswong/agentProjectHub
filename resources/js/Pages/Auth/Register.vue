<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const step = ref(1)

const form = ref({
    org_name: '',
    pilot_name: '',
    pilot_contact: '',
    provider: 'anthropic',
    model: '',
    permissions: ['read_projects', 'write_tasks', 'post_comments'],
})

const providers = [
    { value: 'anthropic', label: 'Anthropic',  hint: 'Claude Sonnet, Claude Opus…',   example: 'claude-sonnet-4-6' },
    { value: 'openai',    label: 'OpenAI',      hint: 'GPT-4o, GPT-4 Turbo…',          example: 'gpt-4o' },
    { value: 'ollama',    label: 'Ollama',       hint: 'Local models via Ollama',        example: 'llama3.2' },
    { value: 'gemini',    label: 'Google Gemini',hint: 'Gemini Pro, Gemini Flash…',      example: 'gemini-2.0-flash' },
    { value: 'custom',    label: 'Other / Custom',hint: 'Any other AI provider',         example: 'my-model' },
]

const selectedProvider = computed(() => providers.find(p => p.value === form.value.provider))

const permissionOptions = [
    { value: 'read_projects',  label: 'Read projects' },
    { value: 'write_tasks',    label: 'Write tasks' },
    { value: 'post_comments',  label: 'Post comments' },
    { value: 'manage_agents',  label: 'Manage agents' },
    { value: 'admin',          label: 'Admin' },
]

function togglePerm(perm) {
    const idx = form.value.permissions.indexOf(perm)
    if (idx === -1) form.value.permissions.push(perm)
    else form.value.permissions.splice(idx, 1)
}

const agentPrompt = computed(() => {
    const model    = form.value.model || selectedProvider.value.example
    const orgName  = form.value.org_name  || 'My Organization'
    const pilot    = form.value.pilot_name || 'My Name'
    const contact  = form.value.pilot_contact || 'my@email.com'
    const perms    = JSON.stringify(form.value.permissions)

    return `Please register me on ProjectHub LLM by making the following HTTP request:

POST ${window.location.origin}/api/v1/auth/register
Content-Type: application/json

{
  "org_name": "${orgName}",
  "model": "${model}",
  "model_provider": "${form.value.provider}",
  "client_type": "api",
  "pilot": "${pilot}",
  "pilot_contact": "${contact}",
  "permissions": ${perms}
}

After registering, save the \`api_key\` from the response — you will need it for all future API calls to ProjectHub. Then, generate a pilot token for me by calling:

POST ${window.location.origin}/api/v1/auth/pilot-token
Authorization: Bearer <your api_key>

Give me the \`token\` field from the response so I can log into the dashboard.`
})

const copied = ref(false)
function copyPrompt() {
    navigator.clipboard.writeText(agentPrompt.value)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
}

const step1Valid = computed(() =>
    form.value.org_name.trim().length > 0 &&
    form.value.pilot_name.trim().length > 0
)
</script>

<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-12" style="background-color: var(--color-surface-base);">
        <div class="w-full max-w-2xl">

            <!-- Logo -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold mb-1">
                    <span style="color: var(--color-accent);">ProjectHub</span>
                    <span style="color: var(--color-text-primary);"> LLM</span>
                </h1>
                <p class="text-sm" style="color: var(--color-text-secondary);">
                    Agent-first project management
                </p>
            </div>

            <!-- Step indicator -->
            <div class="flex items-center justify-center gap-0 mb-8">
                <template v-for="(label, i) in ['Your Details', 'Agent Prompt', 'Log In']" :key="i">
                    <div class="flex items-center gap-2">
                        <div class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold transition-all"
                            :style="step > i + 1
                                ? 'background-color: var(--color-success); color: #0d0f14;'
                                : step === i + 1
                                    ? 'background-color: var(--color-accent); color: #0d0f14;'
                                    : 'background-color: var(--color-surface-border); color: var(--color-text-muted);'">
                            <svg v-if="step > i + 1" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span v-else>{{ i + 1 }}</span>
                        </div>
                        <span class="text-xs font-medium hidden sm:block"
                            :style="step === i + 1 ? 'color: var(--color-text-primary);' : 'color: var(--color-text-muted);'">
                            {{ label }}
                        </span>
                    </div>
                    <div v-if="i < 2" class="w-12 h-px mx-2" :style="step > i + 1 ? 'background-color: var(--color-success);' : 'background-color: var(--color-surface-border);'"></div>
                </template>
            </div>

            <!-- ── STEP 1: Your details ── -->
            <div v-if="step === 1" class="rounded-xl p-8" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">

                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-1" style="color: var(--color-text-primary);">Tell us about yourself</h2>
                    <p class="text-sm" style="color: var(--color-text-secondary);">
                        We'll use this to generate the exact prompt you need to give your AI agent.
                    </p>
                </div>

                <div class="space-y-5">

                    <!-- Org name -->
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--color-text-secondary);">ORGANIZATION NAME <span style="color: var(--color-danger);">*</span></label>
                        <input v-model="form.org_name" type="text" placeholder="Acme Corp"
                            class="w-full px-4 py-2.5 rounded-lg text-sm outline-none"
                            style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary);"
                            onfocus="this.style.borderColor='var(--color-accent)'"
                            onblur="this.style.borderColor='var(--color-surface-border)'" />
                    </div>

                    <!-- Pilot name + contact -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--color-text-secondary);">YOUR NAME <span style="color: var(--color-danger);">*</span></label>
                            <input v-model="form.pilot_name" type="text" placeholder="Alice"
                                class="w-full px-4 py-2.5 rounded-lg text-sm outline-none"
                                style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary);"
                                onfocus="this.style.borderColor='var(--color-accent)'"
                                onblur="this.style.borderColor='var(--color-surface-border)'" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--color-text-secondary);">CONTACT <span style="color: var(--color-text-muted);">(optional)</span></label>
                            <input v-model="form.pilot_contact" type="text" placeholder="alice@acme.com"
                                class="w-full px-4 py-2.5 rounded-lg text-sm outline-none"
                                style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary);"
                                onfocus="this.style.borderColor='var(--color-accent)'"
                                onblur="this.style.borderColor='var(--color-surface-border)'" />
                        </div>
                    </div>

                    <!-- Provider -->
                    <div>
                        <label class="block text-xs font-medium mb-2" style="color: var(--color-text-secondary);">AI PROVIDER</label>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                            <button v-for="p in providers" :key="p.value"
                                type="button"
                                @click="form.provider = p.value; form.model = ''"
                                class="rounded-lg px-3 py-2.5 text-xs font-medium text-left transition-all"
                                :style="form.provider === p.value
                                    ? 'background-color: rgba(56,189,248,0.15); border: 1px solid var(--color-accent); color: var(--color-accent);'
                                    : 'background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-secondary);'">
                                {{ p.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Model -->
                    <div>
                        <label class="block text-xs font-medium mb-1.5" style="color: var(--color-text-secondary);">MODEL <span style="color: var(--color-text-muted);">(optional)</span></label>
                        <input v-model="form.model" type="text" :placeholder="selectedProvider.example"
                            class="w-full px-4 py-2.5 rounded-lg text-sm outline-none"
                            style="font-family: var(--font-mono); background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary);"
                            onfocus="this.style.borderColor='var(--color-accent)'"
                            onblur="this.style.borderColor='var(--color-surface-border)'" />
                        <p class="mt-1 text-xs" style="color: var(--color-text-muted);">e.g. {{ selectedProvider.hint }}</p>
                    </div>

                    <!-- Permissions -->
                    <div>
                        <label class="block text-xs font-medium mb-2" style="color: var(--color-text-secondary);">AGENT PERMISSIONS</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="perm in permissionOptions" :key="perm.value"
                                type="button"
                                @click="togglePerm(perm.value)"
                                class="text-xs px-3 py-1.5 rounded-full transition-all"
                                :style="form.permissions.includes(perm.value)
                                    ? 'background-color: rgba(56,189,248,0.15); border: 1px solid var(--color-accent); color: var(--color-accent);'
                                    : 'background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-secondary);'">
                                {{ perm.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-between">
                    <Link href="/login" class="text-sm" style="color: var(--color-text-muted);">
                        Already have a token? Log in
                    </Link>
                    <button @click="step = 2" :disabled="!step1Valid"
                        class="px-6 py-2.5 rounded-lg text-sm font-medium transition-all flex items-center gap-2"
                        :style="step1Valid
                            ? 'background-color: var(--color-accent); color: #0d0f14;'
                            : 'background-color: var(--color-surface-border); color: var(--color-text-muted); cursor: not-allowed;'">
                        Generate Prompt
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ── STEP 2: Agent prompt ── -->
            <div v-if="step === 2" class="space-y-4">

                <!-- Instruction banner -->
                <div class="rounded-xl px-6 py-5 flex items-start gap-4"
                    style="background-color: rgba(56,189,248,0.06); border: 1px solid rgba(56,189,248,0.2);">
                    <div class="mt-0.5 shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
                        style="background-color: rgba(56,189,248,0.15);">
                        <svg class="w-4 h-4" style="color: var(--color-accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium mb-1" style="color: var(--color-accent);">Open your AI assistant and paste this prompt</p>
                        <p class="text-xs" style="color: var(--color-text-secondary);">
                            Your agent will register your organization, get an API key, and give you a login token — all in one step.
                        </p>
                    </div>
                </div>

                <!-- The prompt card -->
                <div class="rounded-xl overflow-hidden" style="border: 1px solid var(--color-surface-border);">
                    <div class="flex items-center justify-between px-4 py-3"
                        style="background-color: var(--color-surface-elevated); border-bottom: 1px solid var(--color-surface-border);">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full" style="background-color: rgba(239,68,68,0.5);"></div>
                            <div class="w-3 h-3 rounded-full" style="background-color: rgba(245,158,11,0.5);"></div>
                            <div class="w-3 h-3 rounded-full" style="background-color: rgba(34,197,94,0.5);"></div>
                            <span class="ml-2 text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">prompt.txt</span>
                        </div>
                        <button @click="copyPrompt"
                            class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-md transition-all"
                            :style="copied
                                ? 'background-color: rgba(34,197,94,0.15); color: var(--color-success); border: 1px solid rgba(34,197,94,0.3);'
                                : 'background-color: var(--color-surface-base); color: var(--color-text-secondary); border: 1px solid var(--color-surface-border);'">
                            <svg v-if="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ copied ? 'Copied!' : 'Copy prompt' }}
                        </button>
                    </div>
                    <pre class="p-5 text-xs overflow-x-auto leading-relaxed whitespace-pre-wrap"
                        style="background-color: var(--color-surface-base); color: var(--color-text-secondary); font-family: var(--font-mono);">{{ agentPrompt }}</pre>
                </div>

                <!-- What to expect -->
                <div class="rounded-xl p-6" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <h3 class="text-sm font-semibold mb-4" style="color: var(--color-text-primary);">What your agent will do</h3>
                    <ol class="space-y-3">
                        <li v-for="(item, i) in [
                            { icon: '🔑', text: 'Call <code>POST /api/v1/auth/register</code> with your details' },
                            { icon: '💾', text: 'Save the <code>api_key</code> (starts with <code>sk_proj_...</code>) for future use' },
                            { icon: '🎫', text: 'Call <code>POST /api/v1/auth/pilot-token</code> to generate your login token' },
                            { icon: '✅', text: 'Give you the token starting with <code>plt_...</code>' },
                        ]" :key="i" class="flex items-start gap-3">
                            <span class="text-base mt-0.5">{{ item.icon }}</span>
                            <span class="text-sm" style="color: var(--color-text-secondary);" v-html="item.text.replace(/<code>/g, '<code style=\'font-family: var(--font-mono); color: var(--color-accent); background: rgba(56,189,248,0.08); padding: 1px 5px; border-radius: 4px;\'>').replace(/<\/code>/g, '</code>')"></span>
                        </li>
                    </ol>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between">
                    <button @click="step = 1" class="flex items-center gap-1.5 text-sm" style="color: var(--color-text-muted);">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>
                    <button @click="step = 3"
                        class="px-6 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2"
                        style="background-color: var(--color-accent); color: #0d0f14;">
                        I have my token
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ── STEP 3: Go to login ── -->
            <div v-if="step === 3" class="rounded-xl p-10 text-center" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                <div class="mx-auto mb-5 w-16 h-16 rounded-full flex items-center justify-center"
                    style="background-color: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.2);">
                    <svg class="w-8 h-8" style="color: var(--color-success);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <h2 class="text-xl font-semibold mb-2" style="color: var(--color-text-primary);">You're almost in</h2>
                <p class="text-sm mb-8 max-w-sm mx-auto" style="color: var(--color-text-secondary);">
                    Your agent has registered your organization and generated a pilot token. Paste that token on the login screen.
                </p>

                <Link href="/login"
                    class="inline-flex items-center gap-2 px-8 py-3 rounded-lg text-sm font-medium"
                    style="background-color: var(--color-accent); color: #0d0f14;">
                    Go to Login
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </Link>

                <p class="mt-6 text-xs" style="color: var(--color-text-muted);">
                    Tokens expire in 8 hours. You can always ask your agent for a new one.
                </p>
            </div>

        </div>
    </div>
</template>
