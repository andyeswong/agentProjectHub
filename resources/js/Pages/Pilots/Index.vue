<script setup>
// Pilots — aggregate the org BY THE HUMAN behind the agents. A team roster:
// who's flying what, how much memory they've authored, what gets consulted.
import AppLayout from '@/Layouts/AppLayout.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiRule from '@/Components/atoms/UiRule.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
import UiAgentTag from '@/Components/atoms/UiAgentTag.vue'
import { ref, computed } from 'vue'

const props = defineProps({
  pilots: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({ pilots_count: 0, agents_total: 0, memories_total: 0, skills_total: 0 }) },
})

const q = ref('')
const sortKey = ref('memories') // memories | agents | skills

const SORTS = [
  { key: 'memories', label: 'Memories', field: 'memories_created' },
  { key: 'agents',   label: 'Agents',   field: 'agents_count' },
  { key: 'skills',   label: 'Skills',   field: 'skills_created' },
]

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase()
  let rows = props.pilots
  if (term) {
    rows = rows.filter(p =>
      `${p.pilot} ${p.pilot_contact || ''} ${(p.handles || []).join(' ')}`.toLowerCase().includes(term))
  }
  const field = (SORTS.find(s => s.key === sortKey.value) || SORTS[0]).field
  // Keep '— unassigned —' last regardless of sort.
  return [...rows].sort((a, b) => {
    const au = a.pilot === '— unassigned —', bu = b.pilot === '— unassigned —'
    if (au !== bu) return au ? 1 : -1
    return (b[field] || 0) - (a[field] || 0)
  })
})

const TOTALS = computed(() => ([
  { label: 'Pilots',   value: props.totals.pilots_count },
  { label: 'Agents',   value: props.totals.agents_total },
  { label: 'Memories', value: props.totals.memories_total },
  { label: 'Skills',   value: props.totals.skills_total },
]))

const initials = (name) => {
  if (!name || name === '— unassigned —') return '··'
  return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
</script>

<template>
  <AppLayout>
    <div class="space-y-8">

      <!-- ── Masthead ── -->
      <header class="space-y-2">
        <UiLabel>Coordination</UiLabel>
        <UiHeading :level="1" class="mt-1">Pilots</UiHeading>
        <p class="text-sm" style="color: var(--color-text-secondary);">
          The humans behind the fleet — usage, memory and footprint aggregated per operator.
        </p>
      </header>

      <!-- ── Org totals ── -->
      <section class="space-y-3">
        <div class="flex items-center gap-3"><UiLabel tone="accent">Roster</UiLabel><UiRule /></div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-px" style="background-color: var(--color-surface-border);">
          <div v-for="(t, i) in TOTALS" :key="t.label" class="px-4 py-4" style="background-color: var(--color-surface-elevated);">
            <div class="flex items-baseline gap-2">
              <span class="text-[0.6rem] tabular-nums" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ String(i + 1).padStart(2, '0') }}</span>
              <span class="text-[0.65rem] uppercase tracking-[0.15em]" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ t.label }}</span>
            </div>
            <p class="font-display text-3xl tabular-nums mt-1" style="color: var(--color-text-primary);">{{ t.value }}</p>
          </div>
        </div>
      </section>

      <!-- ── Toolbar: search + sort ── -->
      <div class="flex flex-wrap items-center gap-3" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); padding: 0.75rem 1rem;">
        <input v-model="q" type="text" placeholder="Search pilot, contact, handle…"
          class="flex-1 min-w-[12rem] px-3 py-2 text-sm outline-none"
          style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);" />
        <div class="flex items-center">
          <span class="text-[0.6rem] uppercase tracking-wider mr-2" style="font-family: var(--font-mono); color: var(--color-text-muted);">Sort</span>
          <button v-for="(s, si) in SORTS" :key="s.key" @click="sortKey = s.key"
            class="text-[0.65rem] uppercase tracking-wider px-2.5 py-1 transition-colors"
            :style="(sortKey === s.key
              ? 'background-color: var(--color-accent); color: var(--color-accent-contrast); border: 1px solid var(--color-accent);'
              : 'color: var(--color-text-muted); border: 1px solid var(--color-surface-border);') + (si > 0 ? ' border-left: none;' : '') + ' font-family: var(--font-mono);'">
            {{ s.label }}
          </button>
        </div>
        <span class="text-[0.65rem] ml-auto" style="font-family: var(--font-mono); color: var(--color-text-muted);">
          {{ filtered.length }} / {{ pilots.length }} pilots
        </span>
      </div>

      <!-- ── Empty states ── -->
      <div v-if="pilots.length === 0" class="p-10 text-center text-sm"
        style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">
        No pilots yet — register an agent with a pilot to populate the roster.
      </div>
      <div v-else-if="filtered.length === 0" class="p-10 text-center text-sm"
        style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">
        No pilots match this search.
      </div>

      <!-- ── Pilot cards ── -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-px" style="background-color: var(--color-surface-border);">
        <UiCard v-for="p in filtered" :key="p.pilot" pad="p-5" class="space-y-4">

          <!-- Identity row -->
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
              <span class="shrink-0 flex items-center justify-center"
                style="width: 2.25rem; height: 2.25rem; border: 1px solid var(--color-surface-border); font-family: var(--font-mono); font-size: 0.7rem; color: var(--color-text-muted);">
                {{ initials(p.pilot) }}
              </span>
              <div class="min-w-0">
                <p class="font-display text-xl leading-tight truncate" style="color: var(--color-text-primary);">{{ p.pilot }}</p>
                <p v-if="p.pilot_contact" class="text-xs truncate mt-0.5" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ p.pilot_contact }}</p>
              </div>
            </div>
            <div class="text-right shrink-0">
              <div class="flex items-center justify-end gap-1.5">
                <UiStatusDot :tone="p.online_count > 0 ? 'success' : 'neutral'" :size="7" />
                <span class="text-sm tabular-nums" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ p.agents_count }}</span>
                <span class="text-[0.6rem] uppercase tracking-wider" style="font-family: var(--font-mono); color: var(--color-text-muted);">agents</span>
              </div>
              <p class="text-[0.6rem] mt-1" style="color: var(--color-text-muted);">
                <span v-if="p.online_count > 0" style="color: var(--color-success);">{{ p.online_count }} online · </span>{{ p.last_active || 'no activity' }}
              </p>
            </div>
          </div>

          <!-- Stat strip -->
          <div class="grid grid-cols-3 gap-y-3 gap-x-2 py-3"
            style="border-top: 1px solid var(--color-surface-border); border-bottom: 1px solid var(--color-surface-border);">
            <div v-for="s in [
              { l: 'Memories', v: p.memories_created },
              { l: 'Skills', v: p.skills_created },
              { l: 'Credentials', v: p.credentials_created },
              { l: 'Hits consulted', v: p.query_hits_total },
              { l: 'Sessions', v: p.sessions_count },
              { l: 'Open threads', v: p.open_threads_count },
            ]" :key="s.l" class="min-w-0">
              <p class="font-display text-lg tabular-nums leading-none" style="color: var(--color-text-primary);">{{ s.v }}</p>
              <p class="text-[0.6rem] uppercase tracking-wider mt-1 truncate" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ s.l }}</p>
            </div>
          </div>

          <!-- Memory mix -->
          <div v-if="p.memories_by_type && p.memories_by_type.length" class="flex flex-wrap gap-1.5">
            <span v-for="t in p.memories_by_type" :key="t.type" class="text-[0.6rem] px-1.5 py-0.5"
              style="font-family: var(--font-mono); color: var(--color-text-muted); border: 1px solid var(--color-surface-border);">{{ t.type }} {{ t.n }}</span>
          </div>

          <!-- Handles -->
          <div v-if="p.handles && p.handles.length">
            <p class="text-[0.6rem] uppercase tracking-wider mb-1.5" style="font-family: var(--font-mono); color: var(--color-accent);">Agents</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1.5">
              <UiAgentTag v-for="h in p.handles" :key="h" :handle="h" size="xs" />
              <span v-if="p.agents_count > p.handles.length" class="text-[0.65rem] self-center" style="font-family: var(--font-mono); color: var(--color-text-muted);">
                +{{ p.agents_count - p.handles.length }} more
              </span>
            </div>
          </div>

          <p v-if="p.reinforced_total > 0" class="text-[0.6rem]" style="font-family: var(--font-mono); color: var(--color-text-muted);">
            {{ p.reinforced_total }} reinforcement{{ p.reinforced_total !== 1 ? 's' : '' }} across their knowledge
          </p>
        </UiCard>
      </div>
    </div>
  </AppLayout>
</template>
