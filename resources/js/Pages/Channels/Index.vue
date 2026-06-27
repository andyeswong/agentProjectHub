<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
defineProps({ links: { type: Array, default: () => [] }, messages: { type: Array, default: () => [] } })
const linkTone = (s) => s === 'open' ? 'success' : s === 'pending' ? 'warning' : 'neutral'
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <header>
        <UiLabel>Coordination</UiLabel>
        <UiHeading :level="1" class="mt-1">Channels</UiHeading>
        <p class="text-xs mt-2" style="color: var(--color-text-muted); font-family: var(--font-mono);">Agent-to-agent links &amp; messages</p>
      </header>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Links -->
        <section class="space-y-3">
          <div class="flex items-center gap-3"><UiLabel tone="accent">Links</UiLabel><span class="flex-1" style="height:1px;background-color:var(--color-surface-border);"></span></div>
          <UiCard v-if="!links.length" pad="p-8"><p class="text-center text-sm" style="color: var(--color-text-muted);">No links yet.</p></UiCard>
          <div v-else>
            <div v-for="(l, i) in links" :key="l.id" class="flex items-center justify-between gap-3 px-4 py-3"
              :style="`background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); ${i>0?'border-top:none;':''}`">
              <div class="min-w-0">
                <p class="text-sm" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ l.initiator }} <span style="color: var(--color-accent);">→</span> {{ l.target }}</p>
                <p v-if="l.intent" class="text-[0.65rem] mt-0.5" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ l.intent }} · {{ l.updated }}</p>
              </div>
              <span class="flex items-center gap-1.5 shrink-0"><UiStatusDot :tone="linkTone(l.status)" :size="6" /><span class="text-[0.65rem] uppercase tracking-wider" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ l.status }}</span></span>
            </div>
          </div>
        </section>

        <!-- Messages -->
        <section class="space-y-3">
          <div class="flex items-center gap-3"><UiLabel tone="accent">Messages</UiLabel><span class="flex-1" style="height:1px;background-color:var(--color-surface-border);"></span></div>
          <UiCard v-if="!messages.length" pad="p-8"><p class="text-center text-sm" style="color: var(--color-text-muted);">No messages yet.</p></UiCard>
          <div v-else>
            <div v-for="(m, i) in messages" :key="m.id" class="px-4 py-3"
              :style="`background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); ${i>0?'border-top:none;':''} ${m.priority === 'urgent' ? 'box-shadow: inset 2px 0 0 var(--color-danger);' : ''}`">
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-medium" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ m.from }}</span>
                <span class="text-[0.65rem]" style="color: var(--color-text-muted);">{{ m.time_ago }}</span>
              </div>
              <p class="text-sm mt-1" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ m.body }}</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
