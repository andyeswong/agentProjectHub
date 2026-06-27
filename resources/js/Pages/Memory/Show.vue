<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import UiAgentTag from '@/Components/atoms/UiAgentTag.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ memory: Object, related: Array })

const typeColor = {
  credential: 'var(--color-danger)', domain: 'var(--color-accent)', ip: 'var(--color-warning)',
  fact: 'var(--color-success)', config: 'var(--color-neutral)', note: 'var(--color-text-secondary)',
  skill: 'var(--color-accent)', other: 'var(--color-text-muted)',
}
const tc = (t) => typeColor[t] ?? 'var(--color-text-muted)'
const fmt = (iso) => iso ? new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'

// reveal sensitive
const revealed = ref(null)
async function reveal() {
  const r = await fetch(`/memory/${props.memory.id}/reveal`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  if (r.ok) revealed.value = await r.json()
}

// integrate (complement)
const note = ref('')
const origin = ref('')
const posting = ref(false)
function integrate() {
  if (!note.value.trim() || posting.value) return
  posting.value = true
  router.post(`/memory/${props.memory.id}/integrate`, { note: note.value, origin: origin.value || null }, {
    preserveScroll: true, onSuccess: () => { note.value = ''; origin.value = '' }, onFinish: () => posting.value = false,
  })
}
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Breadcrumb -->
      <div class="flex items-center gap-1.5 text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">
        <Link href="/memory" style="color: var(--color-accent);">Memory</Link><span>/</span>
        <span class="truncate" style="color: var(--color-text-primary);">{{ memory.memory_key ?? memory.label }}</span>
      </div>

      <!-- Header -->
      <UiCard pad="p-6" :style="`box-shadow: inset 3px 0 0 ${tc(memory.type)};`">
        <div class="flex items-start justify-between gap-3">
          <h1 class="font-display text-2xl md:text-3xl leading-tight" style="color: var(--color-text-primary); letter-spacing: -0.015em;">{{ memory.label }}</h1>
          <div class="flex flex-wrap items-center gap-2 shrink-0 justify-end">
            <span class="text-[0.65rem] uppercase tracking-wider px-1.5 py-0.5" :style="`color: ${tc(memory.type)}; border: 1px solid var(--color-surface-border); font-family: var(--font-mono);`">{{ memory.type }}</span>
            <span v-if="memory.reinforced_count" class="text-[0.65rem] uppercase tracking-wider px-1.5 py-0.5" style="color: var(--color-accent); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">×{{ memory.reinforced_count }} reinforced</span>
          </div>
        </div>
        <p v-if="memory.memory_key" class="text-xs mt-1" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ memory.memory_key }}</p>

        <!-- Content -->
        <p v-if="!memory.is_sensitive" class="mt-4 text-sm" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ memory.content }}</p>
        <div v-else class="mt-4">
          <p v-if="!revealed" class="text-sm italic flex items-center gap-2" style="color: var(--color-text-muted);">
            <UiIcon path="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" :size="13" /> sensitive
            <UiButton variant="outline" size="sm" @click="reveal">Reveal</UiButton>
          </p>
          <p v-else class="text-sm" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ revealed.content }}</p>
        </div>

        <!-- Value -->
        <div v-if="(revealed?.value ?? memory.value)" class="mt-3">
          <code class="block text-xs px-3 py-2 overflow-x-auto" style="background-color: var(--color-surface-base); color: var(--color-accent); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">{{ JSON.stringify(revealed?.value ?? memory.value, null, 2) }}</code>
        </div>

        <div v-if="memory.tags?.length" class="flex flex-wrap gap-1 mt-3">
          <span v-for="tag in memory.tags" :key="tag" class="text-[0.6rem] px-1.5 py-0.5" style="background-color: var(--color-surface-base); color: var(--color-text-muted); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">{{ tag }}</span>
        </div>

        <p v-if="memory.origin" class="text-xs mt-3" style="color: var(--color-text-muted);">origin: {{ memory.origin }}</p>
        <p class="text-xs mt-2 flex items-center gap-1.5" style="color: var(--color-text-muted); font-family: var(--font-mono);">by <UiAgentTag :handle="memory.creator?.model" :pilot="memory.creator?.pilot" size="xs" /> · {{ fmt(memory.created_at) }}</p>
      </UiCard>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Related (spreading-activation) -->
        <section class="space-y-3">
          <div class="flex items-center gap-3"><UiLabel tone="accent">Related</UiLabel><span class="flex-1" style="height:1px;background-color:var(--color-surface-border);"></span></div>
          <UiCard v-if="!related.length" pad="p-8"><p class="text-center text-sm" style="color: var(--color-text-muted);">No associations. Add <code style="font-family: var(--font-mono);">[[wikilinks]]</code> in the content to weave the graph.</p></UiCard>
          <div v-else>
            <Link v-for="(r, i) in related" :key="r.id" :href="`/memory/${r.id}`"
              class="block px-4 py-3 transition-colors"
              :style="`background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); ${i>0?'border-top:none;':''} box-shadow: inset 2px 0 0 ${tc(r.type)};`"
              @mouseover="(e)=>e.currentTarget.style.backgroundColor='var(--color-surface-hover)'" @mouseout="(e)=>e.currentTarget.style.backgroundColor='var(--color-surface-elevated)'">
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium truncate" style="color: var(--color-text-primary);">{{ r.note?.replace('wikilink ','') ?? r.label }}</span>
                <span class="text-[0.6rem] tabular-nums shrink-0" style="font-family: var(--font-mono); color: var(--color-accent);">w {{ r.weight }}</span>
              </div>
              <p class="text-xs mt-0.5 truncate" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ r.type }} · {{ r.via ?? 'edge' }}</p>
            </Link>
          </div>
        </section>

        <!-- Integration history + composer -->
        <section class="space-y-3">
          <div class="flex items-center gap-3"><UiLabel tone="accent">Integration history</UiLabel><span class="flex-1" style="height:1px;background-color:var(--color-surface-border);"></span></div>
          <UiCard pad="p-4">
            <UiLabel>Complement (append, never overwrite)</UiLabel>
            <textarea v-model="note" rows="2" placeholder="A correction, a better way, an error-trail…"
              class="w-full mt-1.5 px-3 py-2 text-sm outline-none resize-y" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border);"
              @focus="$event.target.style.borderColor='var(--color-accent)'" @blur="$event.target.style.borderColor='var(--color-surface-border)'"></textarea>
            <div class="flex items-center gap-2 mt-2">
              <input v-model="origin" placeholder="origin (optional)" class="flex-1 px-3 py-1.5 text-xs outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);" />
              <UiButton variant="solid" size="sm" :disabled="posting || !note.trim()" @click="integrate">{{ posting ? '…' : 'Integrate' }}</UiButton>
            </div>
          </UiCard>

          <UiCard pad="p-0">
            <div v-if="!memory.integration_log?.length" class="px-5 py-6 text-center text-sm" style="color: var(--color-text-muted);">No integrations yet.</div>
            <ul v-else>
              <li v-for="(log, i) in memory.integration_log" :key="i" class="px-4 py-3" :style="`box-shadow: inset 2px 0 0 var(--color-accent); ${i>0?'border-top: 1px solid var(--color-surface-border);':''}`">
                <p class="text-[0.65rem]" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ fmt(log.at) }}</p>
                <p class="text-sm mt-0.5" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ log.note }}</p>
              </li>
            </ul>
          </UiCard>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
