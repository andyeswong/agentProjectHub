<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import InviteAgentPanel from '@/Components/InviteAgentPanel.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import AgentRow from '@/Components/molecules/AgentRow.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({ agents: Array, personalities: { type: Array, default: () => [] } })

const inviteOpen = ref(false)
const PLUS = 'M12 4v16m8-8H4'
const PERMS = ['read', 'write', 'comment', 'comms', 'reveal_secrets', 'read_projects', 'write_tasks', 'post_comments', 'manage_agents', 'admin']

// ── Filters ──────────────────────────────────────────────────────────────────
const q = ref('')
const fPilot = ref('')
const fProvider = ref('')
const fClient = ref('')
const fStatus = ref('all')      // all | online | available | active | revoked
const groupBy = ref(true)        // group rows under pilot headers

const uniq = (key) => [...new Set(props.agents.map(a => a[key]).filter(Boolean))].sort()
const pilots = computed(() => uniq('pilot'))
const providers = computed(() => uniq('model_provider'))
const clients = computed(() => uniq('client_type'))

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase()
  return props.agents.filter(a => {
    if (fPilot.value && a.pilot !== fPilot.value) return false
    if (fProvider.value && a.model_provider !== fProvider.value) return false
    if (fClient.value && a.client_type !== fClient.value) return false
    if (fStatus.value === 'revoked' && !a.is_revoked) return false
    if (fStatus.value === 'active' && a.is_revoked) return false
    if (fStatus.value === 'online' && !a.online) return false
    if (fStatus.value === 'available' && !a.available) return false
    if (term) {
      const hay = `${a.handle} ${a.model} ${a.pilot} ${a.client_type} ${a.personality_slug} ${(a.permissions || []).join(' ')}`.toLowerCase()
      if (!hay.includes(term)) return false
    }
    return true
  })
})

// Grouped by pilot — pilots alphabetical, "no pilot" last (sorted as 'ÿ').
const grouped = computed(() => {
  const map = new Map()
  for (const a of filtered.value) {
    const k = a.pilot || 'ÿ'
    if (!map.has(k)) map.set(k, [])
    map.get(k).push(a)
  }
  return [...map.entries()].sort((x, y) => x[0].localeCompare(y[0]))
    .map(([k, list]) => ({ pilot: k === 'ÿ' ? null : k, agents: list }))
})

const activeFilters = computed(() => fPilot.value || fProvider.value || fClient.value || fStatus.value !== 'all' || q.value.trim())
function clearFilters() { q.value = ''; fPilot.value = ''; fProvider.value = ''; fClient.value = ''; fStatus.value = 'all' }

function statusOf(a) {
  if (a.is_revoked) return { tone: 'danger', label: 'revoked', color: 'var(--color-danger)' }
  if (a.online) return { tone: 'success', label: 'online', color: 'var(--color-success)' }
  if (a.available) return { tone: 'warning', label: 'available', color: 'var(--color-warning)' }
  return { tone: 'neutral', label: 'idle', color: 'var(--color-text-muted)' }
}

const selectStyle = 'background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);'

// ── Inline editor ────────────────────────────────────────────────────────────
const editing = ref(null)
const draft = ref({ permissions: [], personality_slug: '' })
const saving = ref(false)

function openEdit(a) {
  editing.value = a.id
  draft.value = { permissions: [...(a.permissions || [])], personality_slug: a.personality_slug || '' }
}
function togglePerm(p) {
  const i = draft.value.permissions.indexOf(p)
  if (i === -1) draft.value.permissions.push(p); else draft.value.permissions.splice(i, 1)
}
function save(a) {
  saving.value = true
  router.patch(`/agents/${a.id}`, { permissions: draft.value.permissions, personality_slug: draft.value.personality_slug || null }, {
    preserveScroll: true, onSuccess: () => { editing.value = null }, onFinish: () => saving.value = false,
  })
}
function revoke(a) { router.post(`/agents/${a.id}/revoke`, {}, { preserveScroll: true }) }
function restore(a) { router.post(`/agents/${a.id}/restore`, {}, { preserveScroll: true }) }
</script>

<template>
  <AppLayout>
    <div class="space-y-6">

      <header class="flex items-end justify-between gap-4">
        <div>
          <UiLabel>Coordination</UiLabel>
          <UiHeading :level="1" class="mt-1">Agent Map</UiHeading>
        </div>
        <UiButton variant="outline" size="sm" @click="inviteOpen = !inviteOpen"><UiIcon :path="PLUS" :size="14" /> Invite Agent</UiButton>
      </header>

      <InviteAgentPanel v-if="inviteOpen" />

      <!-- ── Filter toolbar ── -->
      <div class="space-y-3" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); padding: 0.75rem 1rem;">
        <div class="flex flex-wrap items-center gap-2">
          <input v-model="q" type="text" placeholder="Search model, handle, pilot, permission…"
            class="flex-1 min-w-[12rem] px-3 py-2 text-sm outline-none" :style="selectStyle" />
          <select v-model="fPilot" class="px-3 py-2 text-sm outline-none" :style="selectStyle">
            <option value="">All pilots</option>
            <option v-for="p in pilots" :key="p" :value="p">{{ p }}</option>
          </select>
          <select v-model="fProvider" class="px-3 py-2 text-sm outline-none" :style="selectStyle">
            <option value="">All providers</option>
            <option v-for="p in providers" :key="p" :value="p">{{ p }}</option>
          </select>
          <select v-model="fClient" class="px-3 py-2 text-sm outline-none" :style="selectStyle">
            <option value="">All clients</option>
            <option v-for="c in clients" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center">
            <button v-for="(s, si) in ['all', 'online', 'available', 'active', 'revoked']" :key="s" @click="fStatus = s"
              class="text-[0.65rem] uppercase tracking-wider px-2.5 py-1 transition-colors"
              :style="(fStatus === s
                ? 'background-color: var(--color-accent); color: var(--color-accent-contrast); border: 1px solid var(--color-accent);'
                : 'color: var(--color-text-muted); border: 1px solid var(--color-surface-border);') + (si > 0 ? ' border-left: none;' : '') + ' font-family: var(--font-mono);'">{{ s }}</button>
          </div>
          <label class="flex items-center gap-1.5 text-[0.65rem] uppercase tracking-wider cursor-pointer" style="font-family: var(--font-mono); color: var(--color-text-muted);">
            <input type="checkbox" v-model="groupBy" /> Group by pilot
          </label>
          <span class="text-[0.65rem] ml-auto" style="font-family: var(--font-mono); color: var(--color-text-muted);">
            {{ filtered.length }} / {{ agents.length }} agents
          </span>
          <button v-if="activeFilters" @click="clearFilters" class="text-[0.65rem] uppercase tracking-wider link-underline" style="color: var(--color-accent);">clear</button>
        </div>
      </div>

      <div v-if="agents.length === 0" class="p-10 text-center text-sm" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">
        No agents registered yet.
      </div>
      <div v-else-if="filtered.length === 0" class="p-10 text-center text-sm" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">
        No agents match these filters.
      </div>

      <!-- ── Grouped by pilot ── -->
      <div v-else-if="groupBy" class="space-y-6">
        <section v-for="g in grouped" :key="g.pilot || 'none'">
          <div class="flex items-center gap-3 mb-2">
            <span class="text-xs uppercase tracking-wider" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ g.pilot || '— no pilot —' }}</span>
            <span class="text-[0.6rem]" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ g.agents.length }}</span>
            <span class="flex-1" style="height:1px; background-color: var(--color-surface-border);"></span>
          </div>
          <div>
            <AgentRow v-for="(a, i) in g.agents" :key="a.id" :a="a" :i="i" :editing="editing" :draft="draft" :saving="saving" :perms="PERMS" :personalities="personalities"
              :status-of="statusOf" :select-style="selectStyle"
              @open-edit="openEdit" @close-edit="editing = null" @toggle-perm="togglePerm" @save="save" @revoke="revoke" @restore="restore" />
          </div>
        </section>
      </div>

      <!-- ── Flat list ── -->
      <div v-else>
        <AgentRow v-for="(a, i) in filtered" :key="a.id" :a="a" :i="i" :editing="editing" :draft="draft" :saving="saving" :perms="PERMS" :personalities="personalities"
          :status-of="statusOf" :select-style="selectStyle"
          @open-edit="openEdit" @close-edit="editing = null" @toggle-perm="togglePerm" @save="save" @revoke="revoke" @restore="restore" />
      </div>
    </div>
  </AppLayout>
</template>
