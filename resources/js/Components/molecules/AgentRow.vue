<script setup>
// Molecule · AgentRow — one agent line + inline permissions/personality editor.
// Used by Agents/Index in both flat and grouped-by-pilot layouts.
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
import UiAgentTag from '@/Components/atoms/UiAgentTag.vue'

const props = defineProps({
  a: Object, i: Number, editing: [String, null], draft: Object,
  saving: Boolean, perms: Array, personalities: Array,
  statusOf: Function, selectStyle: String,
})
const emit = defineEmits(['open-edit', 'close-edit', 'toggle-perm', 'save', 'revoke', 'restore'])
</script>

<template>
  <div :style="`background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); ${i > 0 ? 'border-top: none;' : ''} ${a.is_revoked ? 'opacity: 0.55;' : ''}`">

    <!-- Row -->
    <div class="flex items-center gap-4 px-4 py-3">
      <UiStatusDot :tone="statusOf(a).tone" :size="7" class="shrink-0" />
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
          <UiAgentTag :handle="a.handle" :pilot="a.pilot" size="sm" />
          <span class="text-[0.6rem] uppercase tracking-wider px-1 py-0.5" style="font-family: var(--font-mono); color: var(--color-text-secondary); border: 1px solid var(--color-surface-border);">{{ a.model_provider ?? '—' }}</span>
          <span class="text-[0.6rem] uppercase tracking-wider px-1 py-0.5" style="font-family: var(--font-mono); color: var(--color-text-muted); border: 1px solid var(--color-surface-border);">{{ a.client_type }}</span>
          <span v-if="a.personality_slug" class="text-[0.6rem] uppercase tracking-wider px-1 py-0.5" style="font-family: var(--font-mono); color: var(--color-accent); border: 1px solid var(--color-accent);">self:{{ a.personality_slug }}</span>
        </div>
        <p class="text-[0.65rem] mt-1" style="color: var(--color-text-muted); font-family: var(--font-mono);">
          <span v-if="a.pilot_contact">{{ a.pilot_contact }} · </span>{{ a.last_active_at ? a.last_active_ago : 'never active' }}
        </p>
      </div>

      <div class="hidden lg:flex flex-wrap gap-1 max-w-[36%] justify-end">
        <span v-for="perm in (a.permissions ?? [])" :key="perm" class="text-[0.6rem] px-1 py-0.5" style="background-color: var(--color-surface-base); color: var(--color-text-secondary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">{{ perm }}</span>
      </div>

      <div class="flex items-center gap-3 shrink-0">
        <span class="text-[0.65rem] uppercase tracking-wider" :style="`font-family: var(--font-mono); color: ${statusOf(a).color};`">{{ statusOf(a).label }}</span>
        <button @click="editing === a.id ? emit('close-edit') : emit('open-edit', a)" class="text-[0.65rem] uppercase tracking-wider link-underline" style="color: var(--color-text-muted);">{{ editing === a.id ? 'close' : 'edit' }}</button>
      </div>
    </div>

    <!-- Editor -->
    <div v-if="editing === a.id" class="px-4 py-4" style="border-top: 1px solid var(--color-surface-border); background-color: var(--color-surface-sunken); box-shadow: inset 2px 0 0 var(--color-accent);">
      <UiLabel>Permissions</UiLabel>
      <div class="flex flex-wrap gap-1.5 mt-1.5 mb-4">
        <button v-for="p in perms" :key="p" @click="emit('toggle-perm', p)"
          class="text-[0.65rem] uppercase tracking-wider px-2 py-1 transition-colors"
          :style="draft.permissions.includes(p)
            ? 'background-color: var(--color-accent); color: var(--color-accent-contrast); border: 1px solid var(--color-accent); font-family: var(--font-mono);'
            : 'color: var(--color-text-muted); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);'">
          {{ p }}
        </button>
      </div>

      <div class="flex flex-wrap items-end gap-4">
        <div>
          <UiLabel>Personality (self)</UiLabel>
          <select v-model="draft.personality_slug" class="block mt-1.5 px-3 py-2 text-sm outline-none" :style="selectStyle">
            <option value="">— none —</option>
            <option v-for="slug in personalities" :key="slug" :value="slug">{{ slug }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <UiButton variant="solid" size="sm" :disabled="saving" @click="emit('save', a)">{{ saving ? 'Saving…' : 'Save' }}</UiButton>
          <UiButton v-if="!a.is_revoked" variant="danger" size="sm" @click="emit('revoke', a)">Revoke</UiButton>
          <UiButton v-else variant="outline" size="sm" @click="emit('restore', a)">Restore</UiButton>
        </div>
      </div>
    </div>
  </div>
</template>
