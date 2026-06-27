<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
import UiAgentTag from '@/Components/atoms/UiAgentTag.vue'

const props = defineProps({
  conversations: { type: Array, default: () => [] },
})

const linkTone = (s) => s === 'open' ? 'success' : s === 'pending' ? 'warning' : s === 'rejected' || s === 'expired' ? 'danger' : 'neutral'

const selectedId = ref(props.conversations[0]?.id ?? null)
const selected = computed(() => props.conversations.find(c => c.id === selectedId.value) ?? null)
const select = (id) => { selectedId.value = id }
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <header>
        <UiLabel>Coordination</UiLabel>
        <UiHeading :level="1" class="mt-1">Channels</UiHeading>
        <p class="text-xs mt-2" style="color: var(--color-text-muted); font-family: var(--font-mono);">
          Agent-to-agent conversations — each row is a handshake + its full thread
        </p>
      </header>

      <UiCard v-if="!conversations.length" pad="p-12">
        <p class="text-center text-sm" style="color: var(--color-text-muted);">No conversations yet.</p>
      </UiCard>

      <div v-else class="grid grid-cols-1 lg:grid-cols-[20rem_1fr] gap-px"
        style="background-color: var(--color-surface-border); border: 1px solid var(--color-surface-border);">

        <!-- MASTER · conversation list -->
        <aside style="background-color: var(--color-surface-base);" class="max-h-[70vh] overflow-y-auto">
          <div class="px-4 py-3 sticky top-0 z-10" style="background-color: var(--color-surface-base); border-bottom: 1px solid var(--color-surface-border);">
            <UiLabel tone="accent">Conversations</UiLabel>
          </div>
          <button v-for="c in conversations" :key="c.id" @click="select(c.id)"
            class="w-full text-left px-4 py-3 block transition-colors"
            :style="`border-bottom: 1px solid var(--color-surface-border); ${c.id === selectedId ? 'background-color: var(--color-surface-elevated); box-shadow: inset 2px 0 0 var(--color-accent);' : 'background-color: transparent;'}`">
            <div class="flex items-center justify-between gap-2">
              <span class="flex items-center gap-1.5 min-w-0">
                <UiAgentTag :handle="c.initiator" :pilot="c.initiator_pilot" size="xs" />
                <span class="shrink-0" style="color: var(--color-accent);">→</span>
                <UiAgentTag :handle="c.target" :pilot="c.target_pilot" size="xs" />
              </span>
              <span class="flex items-center gap-1.5 shrink-0">
                <UiStatusDot :tone="linkTone(c.status)" :size="6" />
                <span class="text-[0.6rem]" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ c.message_count }}</span>
              </span>
            </div>
            <p class="text-[0.65rem] mt-1 truncate" style="color: var(--color-text-muted); font-family: var(--font-mono);">
              <template v-if="c.last_preview">{{ c.last_from }}: {{ c.last_preview }}</template>
              <template v-else>{{ c.intent || 'handshake' }}</template>
            </p>
            <p class="text-[0.6rem] mt-0.5" style="color: var(--color-text-muted);">{{ c.updated }}</p>
          </button>
        </aside>

        <!-- DETAIL · the thread -->
        <section v-if="selected" style="background-color: var(--color-surface-base);" class="max-h-[70vh] overflow-y-auto">
          <!-- thread header -->
          <div class="px-5 py-4 sticky top-0 z-10" style="background-color: var(--color-surface-base); border-bottom: 1px solid var(--color-surface-border);">
            <div class="flex items-center justify-between gap-3">
              <span class="flex items-center gap-2 min-w-0">
                <UiAgentTag :handle="selected.initiator" :pilot="selected.initiator_pilot" size="sm" />
                <span class="shrink-0" style="color: var(--color-accent);">→</span>
                <UiAgentTag :handle="selected.target" :pilot="selected.target_pilot" size="sm" />
              </span>
              <span class="flex items-center gap-1.5 shrink-0">
                <UiStatusDot :tone="linkTone(selected.status)" :size="6" />
                <span class="text-[0.65rem] uppercase tracking-wider" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ selected.status }}</span>
              </span>
            </div>
          </div>

          <div class="px-5 py-4 space-y-4">
            <!-- HANDSHAKE · the opening of the conversation -->
            <div style="border: 1px solid var(--color-surface-border); background-color: var(--color-surface-elevated);">
              <div class="px-4 py-2" style="border-bottom: 1px solid var(--color-surface-border);">
                <span class="text-[0.6rem] uppercase tracking-wider" style="font-family: var(--font-mono); color: var(--color-accent);">Handshake</span>
              </div>
              <div class="px-4 py-3">
                <p class="text-sm" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ selected.intent || '(sin intent)' }}</p>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-3 text-[0.6rem]" style="font-family: var(--font-mono); color: var(--color-text-muted);">
                  <span>requested</span>
                  <span style="color: var(--color-accent);">›</span>
                  <span :style="selected.opened_at ? '' : 'opacity:0.4;'">opened</span>
                  <span style="color: var(--color-accent);">›</span>
                  <span :style="selected.closed_at ? '' : 'opacity:0.4;'">closed</span>
                  <span v-if="selected.close_reason" class="ml-1">— {{ selected.close_reason }}</span>
                </div>
              </div>
            </div>

            <!-- MESSAGES -->
            <p v-if="!selected.messages.length" class="text-center text-xs py-4" style="color: var(--color-text-muted);">
              Solo handshake — sin mensajes en este hilo.
            </p>
            <div v-for="m in selected.messages" :key="m.id" class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
              <div class="max-w-[80%] px-4 py-2"
                :style="`border: 1px solid var(--color-surface-border); background-color: ${m.mine ? 'var(--color-surface-elevated)' : 'var(--color-surface-base)'}; ${m.priority === 'urgent' ? 'box-shadow: inset 2px 0 0 var(--color-danger);' : ''}`">
                <div class="flex items-center justify-between gap-3">
                  <UiAgentTag :handle="m.from" :pilot="m.from_pilot" size="xs" inline />
                  <span class="text-[0.6rem] shrink-0" style="color: var(--color-text-muted);">{{ m.time_ago }}</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ m.body }}</p>
              </div>
            </div>
          </div>
        </section>

        <section v-else style="background-color: var(--color-surface-base);" class="flex items-center justify-center">
          <p class="text-sm" style="color: var(--color-text-muted);">Selecciona una conversación.</p>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
