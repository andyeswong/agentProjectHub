<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    memories:            Array,
    stats:               Object,
    filters:             Object,
    search_mode:         String,
    embed_model:         String,
    workspaces:          Array,   // [{ id, name, slug, memory_count }]
    active_workspace_id: String,
})

// ── Search state ──────────────────────────────────────────────────────────
const searchQuery       = ref(props.filters?.q ?? '')
const activeType        = ref(props.filters?.type ?? '')
const semanticMode      = ref(props.filters?.semantic ?? false)
const activeWorkspaceId = ref(props.active_workspace_id ?? '')

const activeWorkspace = computed(() =>
    props.workspaces?.find(w => w.id === activeWorkspaceId.value) ?? null
)

// Build a lookup map id → name for memory cards
const workspaceMap = computed(() => {
    const map = {}
    for (const w of (props.workspaces ?? [])) map[w.id] = w.name
    return map
})

const showWorkspaceBadge = computed(() => !activeWorkspaceId.value && (props.workspaces?.length ?? 0) > 1)

function runSearch() {
    router.get('/memory', {
        q:            searchQuery.value || undefined,
        type:         activeType.value || undefined,
        semantic:     semanticMode.value || undefined,
        workspace_id: activeWorkspaceId.value || undefined,
    }, { preserveState: true, replace: true })
}

function setType(type) {
    activeType.value = type
    runSearch()
}

function setWorkspace(id) {
    activeWorkspaceId.value = id
    runSearch()
}

function clearSearch() {
    searchQuery.value       = ''
    activeType.value        = ''
    semanticMode.value      = false
    activeWorkspaceId.value = ''
    router.get('/memory', {}, { preserveState: false, replace: true })
}

// ── Reveal modal ──────────────────────────────────────────────────────────
const revealModal   = ref(null)
const revealLoading = ref(false)

async function revealValue(memory) {
    revealLoading.value = true
    revealModal.value   = null
    try {
        const res = await fetch(`/memory/${memory.id}/reveal`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        if (res.ok) revealModal.value = await res.json()
    } finally {
        revealLoading.value = false
    }
}

function closeReveal() { revealModal.value = null }

// ── Type config ───────────────────────────────────────────────────────────
const typeConfig = {
    credential: { label: 'Credential', color: 'var(--color-danger)',        icon: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z' },
    domain:     { label: 'Domain',     color: 'var(--color-accent)',         icon: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9' },
    ip:         { label: 'IP',         color: 'var(--color-warning)',        icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01' },
    fact:       { label: 'Fact',       color: 'var(--color-success)',        icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
    config:     { label: 'Config',     color: 'var(--color-neutral)',        icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
    note:       { label: 'Note',       color: 'var(--color-text-secondary)', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
    skill:      { label: 'Skill',      color: '#a78bfa',                     icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
    other:      { label: 'Other',      color: 'var(--color-text-muted)',     icon: 'M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z' },
}

const types = ['credential', 'domain', 'ip', 'fact', 'config', 'note', 'skill', 'other']

const typeColor = t => typeConfig[t]?.color ?? 'var(--color-text-muted)'
const typeLabel = t => typeConfig[t]?.label ?? t
const typeIcon  = t => typeConfig[t]?.icon  ?? ''

function scoreColor(score) {
    if (score >= 0.85) return 'var(--color-success)'
    if (score >= 0.65) return 'var(--color-warning)'
    return 'var(--color-text-muted)'
}

function fmt(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
    <AppLayout>
        <div class="space-y-5">

            <!-- ── Header ── -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold" style="color: var(--color-text-primary);">Shared Memory</h1>
                    <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                        <span v-if="activeWorkspace">{{ activeWorkspace.name }}</span>
                        <span v-else>All workspaces</span>
                        · {{ stats?.total ?? 0 }} memories · {{ stats?.embedded ?? 0 }} embedded
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <!-- Embed model badge -->
                    <div class="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-md"
                        style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted); font-family: var(--font-mono);">
                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: var(--color-success);"></span>
                        {{ embed_model }}
                    </div>
                </div>
            </div>

            <!-- ── Workspace tabs ── -->
            <div v-if="workspaces?.length > 1"
                class="flex flex-wrap gap-2 pb-1"
                style="border-bottom: 1px solid var(--color-surface-border);">

                <!-- All -->
                <button
                    @click="setWorkspace('')"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-all"
                    :style="!activeWorkspaceId
                        ? 'background-color: var(--color-accent); color: #0d0f14;'
                        : 'border: 1px solid var(--color-surface-border); color: var(--color-text-muted); background-color: transparent;'">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    All workspaces
                    <span class="text-xs px-1.5 py-0.5 rounded-full font-mono leading-none"
                        :style="!activeWorkspaceId
                            ? 'background-color: rgba(0,0,0,0.2); color: #0d0f14;'
                            : 'background-color: var(--color-surface-base); color: var(--color-text-muted);'">
                        {{ workspaces.reduce((s, w) => s + (w.memory_count ?? 0), 0) }}
                    </span>
                </button>

                <!-- Per-workspace -->
                <button
                    v-for="ws in workspaces" :key="ws.id"
                    @click="setWorkspace(ws.id)"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-all"
                    :style="activeWorkspaceId === ws.id
                        ? 'background-color: var(--color-accent); color: #0d0f14;'
                        : 'border: 1px solid var(--color-surface-border); color: var(--color-text-muted); background-color: transparent;'">
                    <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                    <span class="truncate max-w-[120px]">{{ ws.name }}</span>
                    <span class="text-xs px-1.5 py-0.5 rounded-full font-mono leading-none shrink-0"
                        :style="activeWorkspaceId === ws.id
                            ? 'background-color: rgba(0,0,0,0.2); color: #0d0f14;'
                            : 'background-color: var(--color-surface-base); color: var(--color-text-muted);'">
                        {{ ws.memory_count ?? 0 }}
                    </span>
                </button>
            </div>

            <!-- ── Stats row ── -->
            <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div v-for="t in ['credential','ip','domain','fact']" :key="t"
                    class="rounded-lg p-3 cursor-pointer transition-all"
                    :style="`background-color: var(--color-surface-elevated); border: 1px solid ${activeType === t ? typeColor(t) + '60' : 'var(--color-surface-border)'};`"
                    @click="setType(activeType === t ? '' : t)">
                    <div class="flex items-center gap-1.5 mb-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                            :style="`color: ${typeColor(t)}`">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="typeIcon(t)" />
                        </svg>
                        <span class="text-xs" :style="`color: ${typeColor(t)}`">{{ typeLabel(t) }}</span>
                    </div>
                    <p class="text-lg font-semibold" style="color: var(--color-text-primary);">{{ stats.by_type[t] ?? 0 }}</p>
                </div>
            </div>

            <!-- ── Search + filters ── -->
            <div class="rounded-lg p-4 space-y-3"
                style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">

                <!-- Search bar -->
                <div class="flex gap-2">
                    <div class="flex-1 relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="color: var(--color-text-muted);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            @keydown.enter="runSearch"
                            type="text"
                            :placeholder="activeWorkspace ? `Search in ${activeWorkspace.name}…` : 'Search all memories…'"
                            class="w-full pl-9 pr-3 py-2 rounded-md text-sm outline-none"
                            style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary);"
                        />
                    </div>
                    <button @click="runSearch"
                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors shrink-0"
                        style="background-color: var(--color-accent); color: #0d0f14;">
                        Search
                    </button>
                    <button v-if="searchQuery || activeType || activeWorkspaceId" @click="clearSearch"
                        class="px-3 py-2 rounded-md text-sm transition-colors shrink-0"
                        style="color: var(--color-text-muted); border: 1px solid var(--color-surface-border);">
                        Clear
                    </button>
                </div>

                <!-- Mode toggle + type filters -->
                <div class="flex flex-wrap items-center gap-2">

                    <!-- Semantic toggle -->
                    <button @click="semanticMode = !semanticMode; runSearch()"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs transition-colors"
                        :style="semanticMode
                            ? 'background-color: rgba(56,189,248,0.15); color: var(--color-accent); border: 1px solid rgba(56,189,248,0.3);'
                            : 'border: 1px solid var(--color-surface-border); color: var(--color-text-muted);'">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Semantic
                    </button>

                    <span class="text-xs" style="color: var(--color-surface-border);">|</span>

                    <!-- Type filters -->
                    <button v-for="t in types" :key="t"
                        @click="setType(activeType === t ? '' : t)"
                        class="px-2.5 py-1 rounded-full text-xs transition-colors"
                        :style="activeType === t
                            ? `background-color: ${typeColor(t)}20; color: ${typeColor(t)}; border: 1px solid ${typeColor(t)}40;`
                            : 'border: 1px solid var(--color-surface-border); color: var(--color-text-muted);'">
                        {{ typeLabel(t) }}
                    </button>
                </div>

                <!-- Search mode indicator -->
                <div v-if="search_mode !== 'list'" class="flex items-center gap-1.5 text-xs" style="color: var(--color-text-muted);">
                    <span v-if="search_mode === 'semantic'" style="color: var(--color-accent);">⚡ Semantic search</span>
                    <span v-else-if="search_mode === 'keyword_fallback'" style="color: var(--color-warning);">⚠ Keyword fallback (Ollama unreachable)</span>
                    <span v-else-if="search_mode === 'keyword'">Keyword search</span>
                    · {{ memories.length }} result{{ memories.length !== 1 ? 's' : '' }}
                    <span v-if="activeWorkspace" style="color: var(--color-text-muted);">in {{ activeWorkspace.name }}</span>
                </div>
            </div>

            <!-- ── Empty state ── -->
            <div v-if="memories.length === 0"
                class="rounded-lg p-12 text-center"
                style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                <svg class="w-10 h-10 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"
                    style="color: var(--color-text-muted);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                <p class="text-sm" style="color: var(--color-text-muted);">No memories found.</p>
                <p v-if="filters?.q || filters?.type" class="text-xs mt-1" style="color: var(--color-text-muted);">
                    Try clearing the filters or switching to semantic search.
                </p>
                <p v-else-if="activeWorkspace" class="text-xs mt-1" style="color: var(--color-text-muted);">
                    No memories in <strong>{{ activeWorkspace.name }}</strong> yet.
                    Agents can store here via <code class="px-1 rounded" style="background: var(--color-surface-base); font-family: var(--font-mono);">POST /api/v1/memory</code>
                    with <code class="px-1 rounded" style="background: var(--color-surface-base); font-family: var(--font-mono);">workspace_id</code>.
                </p>
                <p v-else class="text-xs mt-1" style="color: var(--color-text-muted);">
                    Agents store memories via <code class="px-1 rounded" style="background: var(--color-surface-base); font-family: var(--font-mono);">POST /api/v1/memory</code>
                </p>
            </div>

            <!-- ── Memory cards ── -->
            <div v-else class="space-y-2.5">
                <div
                    v-for="memory in memories"
                    :key="memory.id"
                    class="rounded-lg p-4 transition-colors"
                    :style="`background-color: var(--color-surface-elevated); border: 1px solid ${memory.is_expired ? 'rgba(239,68,68,0.2)' : 'var(--color-surface-border)'};`"
                >
                    <div class="flex items-start gap-3">

                        <!-- Type icon -->
                        <div class="w-8 h-8 rounded-md flex items-center justify-center shrink-0 mt-0.5"
                            :style="`background-color: ${typeColor(memory.type)}15; border: 1px solid ${typeColor(memory.type)}30;`">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                                :style="`color: ${typeColor(memory.type)}`">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="typeIcon(memory.type)" />
                            </svg>
                        </div>

                        <!-- Main content -->
                        <div class="flex-1 min-w-0">

                            <!-- Top row: label + badges -->
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="font-medium text-sm" style="color: var(--color-text-primary);">{{ memory.label }}</span>

                                <span class="text-xs px-1.5 py-0.5 rounded"
                                    :style="`background-color: ${typeColor(memory.type)}15; color: ${typeColor(memory.type)};`">
                                    {{ typeLabel(memory.type) }}
                                </span>

                                <!-- Workspace badge — only when viewing all workspaces -->
                                <span v-if="showWorkspaceBadge && memory.workspace_id"
                                    class="flex items-center gap-1 text-xs px-1.5 py-0.5 rounded"
                                    style="background-color: var(--color-surface-base); color: var(--color-text-muted); border: 1px solid var(--color-surface-border);">
                                    <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    {{ workspaceMap[memory.workspace_id] ?? '—' }}
                                </span>

                                <span v-if="memory.memory_key"
                                    class="text-xs px-1.5 py-0.5 rounded"
                                    style="background-color: var(--color-surface-base); color: var(--color-text-muted); font-family: var(--font-mono); border: 1px solid var(--color-surface-border);">
                                    {{ memory.memory_key }}
                                </span>

                                <span v-if="memory.is_sensitive"
                                    class="text-xs px-1.5 py-0.5 rounded"
                                    style="background-color: rgba(239,68,68,0.1); color: var(--color-danger); border: 1px solid rgba(239,68,68,0.2);">
                                    sensitive
                                </span>

                                <span v-if="memory.is_expired"
                                    class="text-xs px-1.5 py-0.5 rounded"
                                    style="background-color: rgba(239,68,68,0.1); color: var(--color-danger);">
                                    expired
                                </span>

                                <span v-if="!memory.is_embedded"
                                    class="text-xs px-1.5 py-0.5 rounded"
                                    style="background-color: rgba(234,179,8,0.1); color: var(--color-warning);">
                                    not embedded
                                </span>

                                <!-- Semantic score -->
                                <span v-if="memory._score !== undefined"
                                    class="text-xs px-1.5 py-0.5 rounded ml-auto font-mono"
                                    :style="`background-color: ${scoreColor(memory._score)}15; color: ${scoreColor(memory._score)}; border: 1px solid ${scoreColor(memory._score)}30;`">
                                    {{ (memory._score * 100).toFixed(0) }}% match
                                </span>
                            </div>

                            <!-- Content -->
                            <p v-if="memory.is_sensitive"
                                class="text-xs mb-2 italic"
                                style="color: var(--color-text-muted);">
                                🔒 sensitive — click Reveal to view
                            </p>
                            <p v-else class="text-xs mb-2 line-clamp-2" style="color: var(--color-text-secondary);">{{ memory.content }}</p>

                            <!-- Value preview -->
                            <div v-if="memory.value" class="mb-2">
                                <div v-if="memory.is_sensitive" class="flex items-center gap-2">
                                    <code class="text-xs px-2 py-1 rounded"
                                        style="background-color: var(--color-surface-base); color: var(--color-text-muted); font-family: var(--font-mono); border: 1px solid var(--color-surface-border);">
                                        {{ Object.keys(memory.value).join(', ') }} · hidden
                                    </code>
                                    <button
                                        @click="revealValue(memory)"
                                        class="text-xs px-2 py-1 rounded transition-colors"
                                        :disabled="revealLoading"
                                        :style="revealLoading ? 'opacity:0.5;' : 'color: var(--color-accent); border: 1px solid rgba(56,189,248,0.3);'">
                                        {{ revealLoading ? 'Loading…' : 'Reveal' }}
                                    </button>
                                </div>
                                <code v-else class="text-xs px-2 py-1 rounded block truncate"
                                    style="background-color: var(--color-surface-base); color: var(--color-text-secondary); font-family: var(--font-mono); border: 1px solid var(--color-surface-border);">
                                    {{ JSON.stringify(memory.value) }}
                                </code>
                            </div>

                            <!-- Tags -->
                            <div v-if="memory.tags?.length" class="flex flex-wrap gap-1 mb-2">
                                <span v-for="tag in memory.tags" :key="tag"
                                    class="text-xs px-1.5 py-0.5 rounded"
                                    style="background-color: var(--color-surface-base); color: var(--color-text-muted); border: 1px solid var(--color-surface-border);">
                                    {{ tag }}
                                </span>
                            </div>

                            <!-- Footer -->
                            <div class="flex flex-wrap items-center gap-3 text-xs" style="color: var(--color-text-muted);">
                                <span v-if="memory.creator">
                                    by <span style="font-family: var(--font-mono); color: var(--color-text-secondary);">{{ memory.creator?.model ?? '—' }}</span>
                                </span>
                                <span>{{ fmt(memory.created_at) }}</span>
                                <span v-if="memory.expires_at" style="color: var(--color-warning);">
                                    expires {{ fmt(memory.expires_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Reveal modal ── -->
        <Teleport to="body">
            <div v-if="revealModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background-color: rgba(0,0,0,0.7);"
                @click.self="closeReveal">

                <div class="rounded-xl w-full max-w-lg overflow-hidden"
                    style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">

                    <!-- Modal header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color: var(--color-surface-border);">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                                :style="`color: ${typeColor(revealModal.type)}`">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="typeIcon(revealModal.type)" />
                            </svg>
                            <span class="font-medium text-sm" style="color: var(--color-text-primary);">{{ revealModal.label }}</span>
                        </div>
                        <button @click="closeReveal"
                            class="text-sm transition-colors"
                            style="color: var(--color-text-muted);"
                            onmouseover="this.style.color='var(--color-text-primary)'"
                            onmouseout="this.style.color='var(--color-text-muted)'">
                            ✕
                        </button>
                    </div>

                    <!-- Modal body -->
                    <div class="p-5 space-y-4">
                        <div>
                            <p class="text-xs mb-1" style="color: var(--color-text-muted);">Content</p>
                            <p class="text-sm" style="color: var(--color-text-secondary);">{{ revealModal.content }}</p>
                        </div>

                        <div v-if="revealModal.value">
                            <p class="text-xs mb-2" style="color: var(--color-text-muted);">Value</p>
                            <div class="space-y-2">
                                <div v-for="(val, key) in revealModal.value" :key="key"
                                    class="flex items-center gap-2 rounded-md px-3 py-2"
                                    style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border);">
                                    <span class="text-xs shrink-0" style="color: var(--color-text-muted); font-family: var(--font-mono); min-width: 80px;">{{ key }}</span>
                                    <code class="text-xs flex-1 truncate select-all"
                                        style="color: var(--color-accent); font-family: var(--font-mono);">{{ val }}</code>
                                    <button
                                        @click="navigator.clipboard.writeText(String(val))"
                                        class="text-xs px-1.5 py-0.5 rounded shrink-0 transition-colors"
                                        style="color: var(--color-text-muted); border: 1px solid var(--color-surface-border);"
                                        onmouseover="this.style.color='var(--color-text-primary)'"
                                        onmouseout="this.style.color='var(--color-text-muted)'">
                                        copy
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs" style="color: var(--color-text-muted);">
                            This data is visible only to authenticated pilots. Close this modal when done.
                        </p>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
