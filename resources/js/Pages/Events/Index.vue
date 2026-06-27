<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
import { router } from '@inertiajs/vue3'
defineProps({ events: { type: Array, default: () => [] }, kinds: { type: Array, default: () => [] }, filter: String })

const tone = (t) => t.includes('created') || t.includes('stored') ? 'success'
  : t.includes('blocked') || t.includes('revoked') || t.includes('deleted') ? 'danger'
  : t.includes('comment') || t.includes('updated') ? 'warning' : 'accent'
function setFilter(k) { router.get('/events', k ? { type: k } : {}, { preserveState: true, replace: true }) }
const payloadStr = (p) => p ? Object.entries(p).map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : v}`).join('  ·  ') : ''
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <header>
        <UiLabel>Audit</UiLabel>
        <UiHeading :level="1" class="mt-1">Activity</UiHeading>
      </header>

      <!-- Filter chips -->
      <div class="flex flex-wrap gap-1.5">
        <button @click="setFilter('')" class="text-xs uppercase tracking-wider px-2.5 py-1.5 transition-colors"
          :style="!filter ? 'background-color: var(--color-accent); color: var(--color-accent-contrast);' : 'color: var(--color-text-muted); border: 1px solid var(--color-surface-border);'">All</button>
        <button v-for="k in kinds" :key="k" @click="setFilter(k)" class="text-xs uppercase tracking-wider px-2.5 py-1.5 transition-colors"
          :style="filter === k ? 'background-color: var(--color-accent); color: var(--color-accent-contrast);' : 'color: var(--color-text-muted); border: 1px solid var(--color-surface-border);'">{{ k }}</button>
      </div>

      <div v-if="!events.length" class="p-10 text-center text-sm" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">No events.</div>

      <div v-else>
        <div v-for="(e, i) in events" :key="e.id" class="flex items-start gap-3 px-4 py-3"
          :style="`background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); ${i>0?'border-top:none;':''}`">
          <UiStatusDot :tone="tone(e.type)" :size="7" class="mt-1.5" />
          <div class="flex-1 min-w-0">
            <div class="flex items-baseline justify-between gap-2">
              <span class="text-xs font-medium" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ e.type }}</span>
              <span class="text-[0.65rem] shrink-0" style="color: var(--color-text-muted);">{{ e.time_ago }}</span>
            </div>
            <p class="text-[0.65rem] mt-0.5" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ e.actor_model }}<span v-if="e.actor_pilot"> · {{ e.actor_pilot }}</span></p>
            <p v-if="payloadStr(e.payload)" class="text-[0.65rem] mt-0.5 truncate" style="color: var(--color-text-muted);">{{ payloadStr(e.payload) }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
