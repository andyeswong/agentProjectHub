<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({ selves: { type: Array, default: () => [] } })

const levelColor = { core: 'var(--color-accent)', runtime: 'var(--color-warning)', channel: 'var(--color-success)' }
const indent = { core: '', runtime: 'ml-4 md:ml-6', channel: 'ml-8 md:ml-12' }
const matchOf = (l) => l.level === 'core' ? 'self' : l.level === 'runtime' ? l.match_client_type : `${l.match_client_type} / ${l.match_channel}`

// ── Edit ────────────────────────────────────────────────────────────────────
const editing = ref(null)
const draft = ref({})
const saving = ref(false)
function openEdit(l) {
  editing.value = l.id
  draft.value = {
    soul: l.soul || '', register: l.register || '', model_pref: l.model_pref || '',
    rules: (l.rules || []).join('\n'), scopes: (l.scopes || []).join(', '), status: l.status,
  }
}
function save(l) {
  saving.value = true
  router.patch(`/personality-layer/${l.id}`, {
    soul: draft.value.soul, register: draft.value.register, model_pref: draft.value.model_pref,
    rules: draft.value.rules.split('\n').map(s => s.trim()).filter(Boolean),
    scopes: draft.value.scopes.split(',').map(s => s.trim()).filter(Boolean),
    status: draft.value.status,
  }, { preserveScroll: true, onSuccess: () => editing.value = null, onFinish: () => saving.value = false })
}
</script>

<template>
  <AppLayout>
    <div class="space-y-8">
      <header>
        <UiLabel>Identity</UiLabel>
        <UiHeading :level="1" class="mt-1">Personalities</UiHeading>
        <p class="text-xs mt-2" style="color: var(--color-text-muted); font-family: var(--font-mono);">The self a stateless body wears — core → runtime → channel, resolved per body.</p>
      </header>

      <div v-if="!selves.length" class="p-10 text-center text-sm" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">No personalities yet.</div>

      <!-- Each self -->
      <section v-for="self in selves" :key="self.slug" class="space-y-2">
        <div class="flex items-baseline gap-3">
          <h2 class="font-display text-2xl" style="color: var(--color-text-primary); letter-spacing: -0.015em;">{{ self.name }}</h2>
          <span class="text-xs" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ self.slug }} · {{ self.layers.length }} layers</span>
        </div>

        <!-- Cascade -->
        <div v-for="l in self.layers" :key="l.id" :class="indent[l.level]"
          :style="`background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); box-shadow: inset 3px 0 0 ${levelColor[l.level]};`" class="p-4">

          <!-- Layer header -->
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-[0.6rem] uppercase tracking-wider px-1.5 py-0.5" :style="`color: ${levelColor[l.level]}; border: 1px solid var(--color-surface-border); font-family: var(--font-mono);`">{{ l.level }}</span>
              <span class="text-xs font-medium" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ matchOf(l) }}</span>
              <span v-if="l.status === 'draft'" class="text-[0.6rem] uppercase tracking-wider px-1.5 py-0.5" style="color: var(--color-warning); border: 1px solid var(--color-warning); font-family: var(--font-mono);">draft</span>
              <span class="text-[0.6rem]" style="color: var(--color-text-muted); font-family: var(--font-mono);">v{{ l.version }}</span>
            </div>
            <button @click="editing === l.id ? editing = null : openEdit(l)" class="text-[0.65rem] uppercase tracking-wider link-underline" style="color: var(--color-text-muted);">{{ editing === l.id ? 'close' : 'edit' }}</button>
          </div>

          <!-- View -->
          <template v-if="editing !== l.id">
            <p v-if="l.register" class="text-xs mt-2" style="color: var(--color-text-secondary);"><span style="color: var(--color-text-muted);">register:</span> {{ l.register }}</p>
            <p v-if="l.soul" class="text-sm mt-2 line-clamp-4" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ l.soul }}</p>
            <div class="flex flex-wrap gap-x-6 gap-y-1 mt-2 text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">
              <span v-if="l.rules.length">{{ l.rules.length }} rules</span>
              <span v-if="l.scopes.length">scopes: {{ l.scopes.join(', ') }}</span>
              <span v-if="l.model_pref">model: {{ l.model_pref }}</span>
            </div>
            <!-- refs (lazy pointer directory) -->
            <div v-if="l.refs.length" class="mt-2">
              <UiLabel>Refs (lazy pointers)</UiLabel>
              <div class="mt-1 space-y-0.5">
                <p v-for="(r, i) in l.refs" :key="i" class="text-[0.65rem]" style="color: var(--color-text-muted); font-family: var(--font-mono);">
                  <span :style="`color: ${r.load === 'eager' ? 'var(--color-accent)' : 'var(--color-text-muted)'};`">[{{ r.load || 'lazy' }}]</span>
                  {{ r.kind }}:{{ r.note || r.ref }} <span v-if="r.when">← {{ r.when }}</span>
                </p>
              </div>
            </div>
          </template>

          <!-- Edit -->
          <div v-else class="mt-3 space-y-3">
            <div>
              <UiLabel>Soul</UiLabel>
              <textarea v-model="draft.soul" rows="4" class="w-full mt-1 px-3 py-2 text-sm outline-none resize-y" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border);"></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div><UiLabel>Register</UiLabel><input v-model="draft.register" class="w-full mt-1 px-3 py-2 text-sm outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border);" /></div>
              <div><UiLabel>Model pref</UiLabel><input v-model="draft.model_pref" class="w-full mt-1 px-3 py-2 text-sm outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);" /></div>
            </div>
            <div><UiLabel>Rules (one per line)</UiLabel><textarea v-model="draft.rules" rows="4" class="w-full mt-1 px-3 py-2 text-sm outline-none resize-y" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border);"></textarea></div>
            <div><UiLabel>Scopes (comma-separated)</UiLabel><input v-model="draft.scopes" class="w-full mt-1 px-3 py-2 text-sm outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);" /></div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <UiLabel>Gate</UiLabel>
                <button @click="draft.status = draft.status === 'active' ? 'draft' : 'active'"
                  class="text-[0.65rem] uppercase tracking-wider px-2 py-1" :style="draft.status === 'active' ? 'background-color: var(--color-success); color: var(--color-accent-contrast); font-family: var(--font-mono);' : 'color: var(--color-warning); border: 1px solid var(--color-warning); font-family: var(--font-mono);'">{{ draft.status }}</button>
              </div>
              <UiButton variant="solid" size="sm" :disabled="saving" @click="save(l)">{{ saving ? 'Saving…' : 'Save' }}</UiButton>
            </div>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
