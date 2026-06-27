<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiBadge from '@/Components/atoms/UiBadge.vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import UiInput from '@/Components/atoms/UiInput.vue'
import UiRule from '@/Components/atoms/UiRule.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
import UiAgentTag from '@/Components/atoms/UiAgentTag.vue'
import { router, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
  memories: Array, stats: Object, filters: Object, search_mode: String,
  embed_model: String, workspaces: Array, active_workspace_id: String,
})

const searchQuery       = ref(props.filters?.q ?? '')
const activeType        = ref(props.filters?.type ?? '')
const semanticMode      = ref(props.filters?.semantic ?? false)
const activeWorkspaceId = ref(props.active_workspace_id ?? '')

const activeWorkspace = computed(() => props.workspaces?.find(w => w.id === activeWorkspaceId.value) ?? null)
const workspaceMap = computed(() => {
  const map = {}; for (const w of (props.workspaces ?? [])) map[w.id] = w.name; return map
})
const showWorkspaceBadge = computed(() => !activeWorkspaceId.value && (props.workspaces?.length ?? 0) > 1)

function runSearch() {
  router.get('/memory', {
    q: searchQuery.value || undefined, type: activeType.value || undefined,
    semantic: semanticMode.value || undefined, workspace_id: activeWorkspaceId.value || undefined,
  }, { preserveState: true, replace: true })
}
function setType(t) { activeType.value = t; runSearch() }
function setWorkspace(id) { activeWorkspaceId.value = id; runSearch() }
function clearSearch() {
  searchQuery.value = ''; activeType.value = ''; semanticMode.value = false; activeWorkspaceId.value = ''
  router.get('/memory', {}, { preserveState: false, replace: true })
}

const revealModal = ref(null)
const revealLoading = ref(false)
async function revealValue(memory) {
  revealLoading.value = true; revealModal.value = null
  try {
    const res = await fetch(`/memory/${memory.id}/reveal`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    if (res.ok) revealModal.value = await res.json()
  } finally { revealLoading.value = false }
}
function closeReveal() { revealModal.value = null }

const ICON = {
  search:  'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
  lock:    'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
  close:   'M6 18L18 6M6 6l12 12',
  bolt:    'M13 10V3L4 14h7v7l9-11h-7z',
  warn:    'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
}

const typeConfig = {
  credential: { label: 'Credential', color: 'var(--color-danger)',        icon: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z' },
  domain:     { label: 'Domain',     color: 'var(--color-accent)',         icon: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9' },
  ip:         { label: 'IP',         color: 'var(--color-warning)',        icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01' },
  fact:       { label: 'Fact',       color: 'var(--color-success)',        icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
  config:     { label: 'Config',     color: 'var(--color-neutral)',        icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
  note:       { label: 'Note',       color: 'var(--color-text-secondary)', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' },
  skill:      { label: 'Skill',      color: 'var(--color-accent)',         icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
  other:      { label: 'Other',      color: 'var(--color-text-muted)',     icon: 'M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z' },
}
const types = ['credential', 'domain', 'ip', 'fact', 'config', 'note', 'skill', 'other']
const typeColor = t => typeConfig[t]?.color ?? 'var(--color-text-muted)'
const typeLabel = t => typeConfig[t]?.label ?? t
const typeIcon  = t => typeConfig[t]?.icon  ?? ''
function scoreColor(s) { return s >= 0.85 ? 'var(--color-success)' : s >= 0.65 ? 'var(--color-warning)' : 'var(--color-text-muted)' }
function fmt(iso) { return iso ? new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—' }
</script>

<template>
  <AppLayout>
    <div class="space-y-8">

      <!-- ── Masthead ── -->
      <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <UiLabel>Knowledge</UiLabel>
          <UiHeading :level="1" class="mt-1">Shared Memory</UiHeading>
          <p class="text-xs mt-2" style="color: var(--color-text-muted); font-family: var(--font-mono);">
            {{ activeWorkspace ? activeWorkspace.name : 'All workspaces' }} · {{ stats?.total ?? 0 }} memories · {{ stats?.embedded ?? 0 }} embedded
          </p>
        </div>
        <UiBadge tone="neutral"><UiStatusDot tone="success" :size="6" /> {{ embed_model }}</UiBadge>
      </header>

      <!-- ── Workspace tabs ── -->
      <div v-if="workspaces?.length > 1" class="inline-flex flex-wrap gap-px max-w-full self-start" style="background-color: var(--color-surface-border); border: 1px solid var(--color-surface-border);">
        <button @click="setWorkspace('')"
          class="flex items-center gap-2 px-3 py-2 text-xs uppercase tracking-wider font-medium transition-colors"
          :style="!activeWorkspaceId
            ? 'background-color: var(--color-accent); color: var(--color-accent-contrast);'
            : 'background-color: var(--color-surface-elevated); color: var(--color-text-muted);'">
          All
          <span class="tabular-nums" style="font-family: var(--font-mono);">{{ workspaces.reduce((s, w) => s + (w.memory_count ?? 0), 0) }}</span>
        </button>
        <button v-for="ws in workspaces" :key="ws.id" @click="setWorkspace(ws.id)"
          class="flex items-center gap-2 px-3 py-2 text-xs uppercase tracking-wider font-medium transition-colors"
          :style="activeWorkspaceId === ws.id
            ? 'background-color: var(--color-accent); color: var(--color-accent-contrast);'
            : 'background-color: var(--color-surface-elevated); color: var(--color-text-muted);'">
          <span class="truncate max-w-[120px] normal-case tracking-normal">{{ ws.name }}</span>
          <span class="tabular-nums" style="font-family: var(--font-mono);">{{ ws.memory_count ?? 0 }}</span>
        </button>
      </div>

      <!-- ── Type metrics (clickable filters) ── -->
      <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 gap-px" style="background-color: var(--color-surface-border);">
        <button v-for="t in ['credential','ip','domain','fact']" :key="t" @click="setType(activeType === t ? '' : t)"
          class="text-left px-4 py-3 transition-colors"
          :style="`background-color: var(--color-surface-elevated); ${activeType === t ? 'box-shadow: inset 0 -2px 0 ' + typeColor(t) + ';' : ''}`">
          <div class="flex items-center gap-1.5 mb-1">
            <UiIcon :path="typeIcon(t)" :size="13" :style="`color: ${typeColor(t)}`" />
            <UiLabel>{{ typeLabel(t) }}</UiLabel>
          </div>
          <p class="font-display tabular-nums" style="font-size: 1.6rem; color: var(--color-text-primary); letter-spacing: -0.02em;">{{ stats.by_type[t] ?? 0 }}</p>
        </button>
      </div>

      <!-- ── Search + filters ── -->
      <UiCard pad="p-4" class="space-y-3">
        <div class="flex gap-2">
          <div class="flex-1 relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--color-text-muted);"><UiIcon :path="ICON.search" :size="16" /></span>
            <input v-model="searchQuery" @keydown.enter="runSearch" type="text"
              :placeholder="activeWorkspace ? `Search in ${activeWorkspace.name}…` : 'Search all memories…'"
              class="w-full pl-9 pr-3 py-2.5 text-sm outline-none transition-colors"
              style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary); font-family: var(--font-mono);"
              @focus="$event.target.style.borderColor = 'var(--color-accent)'"
              @blur="$event.target.style.borderColor = 'var(--color-surface-border)'" />
          </div>
          <UiButton variant="solid" @click="runSearch">Search</UiButton>
          <UiButton v-if="searchQuery || activeType || activeWorkspaceId" variant="outline" @click="clearSearch">Clear</UiButton>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button @click="semanticMode = !semanticMode; runSearch()"
            class="flex items-center gap-1.5 px-3 py-1.5 text-xs uppercase tracking-wider transition-colors"
            :style="semanticMode
              ? 'background-color: var(--color-accent); color: var(--color-accent-contrast); border: 1px solid var(--color-accent);'
              : 'border: 1px solid var(--color-surface-border); color: var(--color-text-muted);'">
            <UiIcon :path="ICON.bolt" :size="12" /> Semantic
          </button>
          <span style="color: var(--color-surface-border);">|</span>
          <button v-for="t in types" :key="t" @click="setType(activeType === t ? '' : t)"
            class="px-2.5 py-1.5 text-xs uppercase tracking-wider transition-colors"
            :style="activeType === t
              ? `color: ${typeColor(t)}; border: 1px solid ${typeColor(t)};`
              : 'border: 1px solid var(--color-surface-border); color: var(--color-text-muted);'">
            {{ typeLabel(t) }}
          </button>
        </div>

        <div v-if="search_mode !== 'list'" class="flex items-center gap-1.5 text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">
          <span v-if="search_mode === 'semantic'" class="flex items-center gap-1" style="color: var(--color-accent);"><UiIcon :path="ICON.bolt" :size="12" /> Semantic search</span>
          <span v-else-if="search_mode === 'keyword_fallback'" class="flex items-center gap-1" style="color: var(--color-warning);"><UiIcon :path="ICON.warn" :size="12" /> Keyword fallback (Ollama unreachable)</span>
          <span v-else-if="search_mode === 'keyword'">Keyword search</span>
          · {{ memories.length }} result{{ memories.length !== 1 ? 's' : '' }}
          <span v-if="activeWorkspace">in {{ activeWorkspace.name }}</span>
        </div>
      </UiCard>

      <!-- ── Empty ── -->
      <UiCard v-if="memories.length === 0" pad="p-12">
        <div class="text-center">
          <span class="inline-flex" style="color: var(--color-text-muted);"><UiIcon :path="typeIcon('fact')" :size="36" :stroke="1" /></span>
          <p class="text-sm mt-3" style="color: var(--color-text-muted);">No memories found.</p>
          <p v-if="filters?.q || filters?.type" class="text-xs mt-1" style="color: var(--color-text-muted);">Try clearing the filters or switching to semantic search.</p>
          <p v-else class="text-xs mt-1" style="color: var(--color-text-muted);">Agents store memories via <code class="px-1" style="background: var(--color-surface-base); font-family: var(--font-mono);">POST /api/v1/memory</code></p>
        </div>
      </UiCard>

      <!-- ── Memory cards ── -->
      <div v-else>
        <div v-for="(memory, i) in memories" :key="memory.id"
          class="px-4 py-4"
          :style="`background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); ${i > 0 ? 'border-top: none;' : ''} box-shadow: inset 2px 0 0 ${typeColor(memory.type)};`">
          <div class="flex items-start gap-3">
            <span class="mt-0.5 shrink-0" :style="`color: ${typeColor(memory.type)}`"><UiIcon :path="typeIcon(memory.type)" :size="18" /></span>

            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 mb-1.5">
                <Link :href="`/memory/${memory.id}`" class="font-medium text-sm link-underline" style="color: var(--color-text-primary);">{{ memory.label }}</Link>
                <UiBadge :tone="memory.type === 'credential' ? 'danger' : 'neutral'">{{ typeLabel(memory.type) }}</UiBadge>
                <UiBadge v-if="showWorkspaceBadge && memory.workspace_id" tone="neutral">{{ workspaceMap[memory.workspace_id] ?? '—' }}</UiBadge>
                <span v-if="memory.memory_key" class="text-xs px-1.5 py-0.5" style="background-color: var(--color-surface-base); color: var(--color-text-muted); font-family: var(--font-mono); border: 1px solid var(--color-surface-border);">{{ memory.memory_key }}</span>
                <UiBadge v-if="memory.is_sensitive" tone="danger">sensitive</UiBadge>
                <UiBadge v-if="memory.is_expired" tone="danger">expired</UiBadge>
                <UiBadge v-if="!memory.is_embedded" tone="warning">not embedded</UiBadge>
                <span v-if="memory._score !== undefined" class="text-xs px-1.5 py-0.5 ml-auto" :style="`color: ${scoreColor(memory._score)}; border: 1px solid ${scoreColor(memory._score)}; font-family: var(--font-mono);`">{{ (memory._score * 100).toFixed(0) }}% match</span>
              </div>

              <p v-if="memory.is_sensitive" class="text-xs mb-2 italic flex items-center gap-1" style="color: var(--color-text-muted);">
                <UiIcon :path="ICON.lock" :size="12" /> sensitive — click Reveal to view
              </p>
              <p v-else class="text-xs mb-2 line-clamp-2" style="color: var(--color-text-secondary);">{{ memory.content }}</p>

              <div v-if="memory.value" class="mb-2">
                <div v-if="memory.is_sensitive" class="flex items-center gap-2">
                  <code class="text-xs px-2 py-1" style="background-color: var(--color-surface-base); color: var(--color-text-muted); font-family: var(--font-mono); border: 1px solid var(--color-surface-border);">{{ Object.keys(memory.value).join(', ') }} · hidden</code>
                  <UiButton variant="outline" size="sm" :disabled="revealLoading" @click="revealValue(memory)">{{ revealLoading ? 'Loading…' : 'Reveal' }}</UiButton>
                </div>
                <code v-else class="text-xs px-2 py-1 block truncate" style="background-color: var(--color-surface-base); color: var(--color-text-secondary); font-family: var(--font-mono); border: 1px solid var(--color-surface-border);">{{ JSON.stringify(memory.value) }}</code>
              </div>

              <div v-if="memory.tags?.length" class="flex flex-wrap gap-1 mb-2">
                <span v-for="tag in memory.tags" :key="tag" class="text-[0.65rem] px-1.5 py-0.5" style="background-color: var(--color-surface-base); color: var(--color-text-muted); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">{{ tag }}</span>
              </div>

              <div class="flex flex-wrap items-center gap-3 text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">
                <span v-if="memory.creator" class="flex items-center gap-1">by <UiAgentTag :handle="memory.creator?.model" :pilot="memory.creator?.pilot" size="xs" /></span>
                <span>{{ fmt(memory.created_at) }}</span>
                <span v-if="memory.expires_at" style="color: var(--color-warning);">expires {{ fmt(memory.expires_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Reveal modal ── -->
    <Teleport to="body">
      <div v-if="revealModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(0,0,0,0.7);" @click.self="closeReveal">
        <div class="w-full max-w-lg" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
          <div class="flex items-center justify-between px-5 py-4" style="border-bottom: 1px solid var(--color-surface-border);">
            <div class="flex items-center gap-2">
              <span :style="`color: ${typeColor(revealModal.type)}`"><UiIcon :path="typeIcon(revealModal.type)" :size="16" /></span>
              <span class="font-medium text-sm" style="color: var(--color-text-primary);">{{ revealModal.label }}</span>
            </div>
            <button @click="closeReveal" style="color: var(--color-text-muted);" aria-label="Close"><UiIcon :path="ICON.close" :size="16" /></button>
          </div>
          <div class="p-5 space-y-4">
            <div>
              <UiLabel>Content</UiLabel>
              <p class="text-sm mt-1" style="color: var(--color-text-secondary);">{{ revealModal.content }}</p>
            </div>
            <div v-if="revealModal.value">
              <UiLabel>Value</UiLabel>
              <div class="space-y-px mt-2" style="background-color: var(--color-surface-border);">
                <div v-for="(val, key) in revealModal.value" :key="key" class="flex items-center gap-2 px-3 py-2" style="background-color: var(--color-surface-base);">
                  <span class="text-xs shrink-0" style="color: var(--color-text-muted); font-family: var(--font-mono); min-width: 80px;">{{ key }}</span>
                  <code class="text-xs flex-1 truncate select-all" style="color: var(--color-accent); font-family: var(--font-mono);">{{ val }}</code>
                  <button @click="navigator.clipboard.writeText(String(val))" class="text-xs px-1.5 py-0.5 shrink-0 uppercase tracking-wider" style="color: var(--color-text-muted); border: 1px solid var(--color-surface-border);">copy</button>
                </div>
              </div>
            </div>
            <p class="text-xs" style="color: var(--color-text-muted);">This data is visible only to authenticated pilots. Close this modal when done.</p>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
