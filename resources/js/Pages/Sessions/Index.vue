<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
defineProps({ sessions: { type: Array, default: () => [] }, open_count: Number })
const statusTone = (s) => s === 'done' ? 'neutral' : s === 'paused' ? 'warning' : 'success'
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <header>
        <UiLabel>Episodic</UiLabel>
        <UiHeading :level="1" class="mt-1">Sessions</UiHeading>
        <p class="text-xs mt-2" style="color: var(--color-text-muted); font-family: var(--font-mono);">What the agents are working on — {{ sessions.length }} sessions · {{ open_count }} with open threads</p>
      </header>

      <div v-if="!sessions.length" class="p-10 text-center text-sm" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">No sessions yet.</div>

      <div v-else>
        <div v-for="(s, i) in sessions" :key="s.id" class="px-4 py-4"
          :style="`background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); ${i>0?'border-top:none;':''} ${s.open_threads.length ? 'box-shadow: inset 2px 0 0 var(--color-accent);' : ''}`">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-display text-lg leading-tight" style="color: var(--color-text-primary);">{{ s.title || 'session' }}</p>
              <p class="text-xs mt-0.5" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ s.agent_handle }} · {{ s.last_active }}</p>
            </div>
            <span class="flex items-center gap-1.5 shrink-0"><UiStatusDot :tone="statusTone(s.status)" :size="6" /><span class="text-[0.65rem] uppercase tracking-wider" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ s.status }}</span></span>
          </div>
          <p v-if="s.summary" class="text-sm mt-2 line-clamp-2" style="color: var(--color-text-secondary);">{{ s.summary }}</p>
          <div v-if="s.open_threads.length" class="mt-3">
            <UiLabel tone="accent">Open threads</UiLabel>
            <ul class="mt-1 space-y-1">
              <li v-for="(t, j) in s.open_threads" :key="j" class="text-xs flex gap-2" style="color: var(--color-text-secondary);">
                <span style="color: var(--color-accent); font-family: var(--font-mono);">›</span><span class="line-clamp-1">{{ t }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
