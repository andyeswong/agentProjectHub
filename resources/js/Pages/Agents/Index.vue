<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import InviteAgentPanel from '@/Components/InviteAgentPanel.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ agents: Array, personalities: { type: Array, default: () => [] } })

const inviteOpen = ref(false)
const PLUS = 'M12 4v16m8-8H4'
const PERMS = ['read', 'write', 'comment', 'comms', 'reveal_secrets', 'read_projects', 'write_tasks', 'post_comments', 'manage_agents', 'admin']

// ── Inline editor ───────────────────────────────────────────────────────────
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

      <div v-if="agents.length === 0" class="p-10 text-center text-sm" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">
        No agents registered yet.
      </div>

      <div v-else>
        <div v-for="(a, i) in agents" :key="a.id"
          :style="`background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); ${i > 0 ? 'border-top: none;' : ''} ${a.is_revoked ? 'opacity: 0.55;' : ''}`">

          <!-- Row -->
          <div class="flex items-center gap-4 px-4 py-3">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-medium" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ a.model ?? '—' }}</span>
                <span class="text-[0.6rem] uppercase tracking-wider px-1 py-0.5" style="font-family: var(--font-mono); color: var(--color-text-secondary); border: 1px solid var(--color-surface-border);">{{ a.model_provider ?? '—' }}</span>
                <span class="text-[0.6rem] uppercase tracking-wider px-1 py-0.5" style="font-family: var(--font-mono); color: var(--color-text-muted); border: 1px solid var(--color-surface-border);">{{ a.client_type }}</span>
                <span v-if="a.personality_slug" class="text-[0.6rem] uppercase tracking-wider px-1 py-0.5" style="font-family: var(--font-mono); color: var(--color-accent); border: 1px solid var(--color-accent);">self:{{ a.personality_slug }}</span>
              </div>
              <p class="text-xs mt-0.5" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ a.pilot ?? '—' }}<span v-if="a.pilot_contact"> · {{ a.pilot_contact }}</span> · {{ a.last_active_at ? a.last_active_ago : 'never' }}</p>
            </div>

            <div class="hidden lg:flex flex-wrap gap-1 max-w-[40%] justify-end">
              <span v-for="perm in (a.permissions ?? [])" :key="perm" class="text-[0.6rem] px-1 py-0.5" style="background-color: var(--color-surface-base); color: var(--color-text-secondary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">{{ perm }}</span>
            </div>

            <div class="flex items-center gap-3 shrink-0">
              <span v-if="a.is_revoked" class="text-[0.65rem] uppercase tracking-wider" style="color: var(--color-danger); font-family: var(--font-mono);">revoked</span>
              <span v-else class="flex items-center gap-1.5"><UiStatusDot tone="success" :size="6" /><span class="text-xs" style="color: var(--color-success);">active</span></span>
              <button @click="editing === a.id ? editing = null : openEdit(a)" class="text-[0.65rem] uppercase tracking-wider link-underline" style="color: var(--color-text-muted);">{{ editing === a.id ? 'close' : 'edit' }}</button>
            </div>
          </div>

          <!-- Editor -->
          <div v-if="editing === a.id" class="px-4 py-4" style="border-top: 1px solid var(--color-surface-border); background-color: var(--color-surface-sunken); box-shadow: inset 2px 0 0 var(--color-accent);">
            <UiLabel>Permissions</UiLabel>
            <div class="flex flex-wrap gap-1.5 mt-1.5 mb-4">
              <button v-for="p in PERMS" :key="p" @click="togglePerm(p)"
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
                <select v-model="draft.personality_slug" class="block mt-1.5 px-3 py-2 text-sm outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">
                  <option value="">— none —</option>
                  <option v-for="slug in personalities" :key="slug" :value="slug">{{ slug }}</option>
                </select>
              </div>
              <div class="flex items-center gap-2">
                <UiButton variant="solid" size="sm" :disabled="saving" @click="save(a)">{{ saving ? 'Saving…' : 'Save' }}</UiButton>
                <UiButton v-if="!a.is_revoked" variant="danger" size="sm" @click="revoke(a)">Revoke</UiButton>
                <UiButton v-else variant="outline" size="sm" @click="restore(a)">Restore</UiButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
