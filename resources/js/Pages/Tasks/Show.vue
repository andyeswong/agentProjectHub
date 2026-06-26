<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ task: Object, timeline: Array })

const STATUSES = ['backlog', 'todo', 'in_progress', 'done', 'blocked']
const statusColor = {
  backlog: 'var(--color-neutral)', todo: 'var(--color-text-secondary)',
  in_progress: 'var(--color-warning)', done: 'var(--color-success)', blocked: 'var(--color-danger)',
}
const priorityColor = {
  low: 'var(--color-neutral)', medium: 'var(--color-accent)', high: 'var(--color-warning)', critical: 'var(--color-danger)',
}
// Typed supervision intents — the human-supervises-agent-operates loop.
const INTENTS = [
  { key: 'instruction', tone: 'var(--color-accent)' },
  { key: 'correction',  tone: 'var(--color-warning)' },
  { key: 'approval',    tone: 'var(--color-success)' },
  { key: 'question',    tone: 'var(--color-text-secondary)' },
  { key: 'general',     tone: 'var(--color-text-muted)' },
]
const commentTypeColor = Object.fromEntries(INTENTS.map(i => [i.key, i.tone]))

const eventTone = (type) => type.includes('created') ? 'success'
  : type.includes('status') ? 'accent' : type.includes('blocked') ? 'danger'
  : type.includes('comment') ? 'warning' : 'neutral'

// ── Actions ───────────────────────────────────────────────────────────────
const savingStatus = ref(false)
function setStatus(s) {
  if (s === props.task.status || savingStatus.value) return
  savingStatus.value = true
  router.patch(`/tasks/${props.task.id}`, { status: s }, { preserveScroll: true, onFinish: () => savingStatus.value = false })
}

const text = ref('')
const intent = ref('instruction')
const posting = ref(false)
function postComment() {
  if (!text.value.trim() || posting.value) return
  posting.value = true
  router.post(`/tasks/${props.task.id}/comments`, { text: text.value, type: intent.value }, {
    preserveScroll: true,
    onSuccess: () => { text.value = '' },
    onFinish: () => posting.value = false,
  })
}
</script>

<template>
  <AppLayout>
    <div class="space-y-6">

      <!-- Breadcrumb -->
      <div class="flex items-center gap-1.5 text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">
        <Link href="/projects" style="color: var(--color-accent);">Projects</Link><span>/</span>
        <Link :href="`/projects/${task.project_id}`" class="truncate" style="color: var(--color-accent);">{{ task.project?.name }}</Link><span>/</span>
        <span class="truncate" style="color: var(--color-text-primary);">{{ task.title }}</span>
      </div>

      <!-- Header -->
      <UiCard pad="p-6">
        <div class="flex items-start justify-between gap-3">
          <h1 class="font-display text-2xl md:text-3xl leading-tight" style="color: var(--color-text-primary); letter-spacing: -0.015em;">{{ task.title }}</h1>
          <span class="shrink-0 text-[0.65rem] uppercase tracking-wider px-1.5 py-0.5" :style="`color: ${priorityColor[task.priority]}; border: 1px solid var(--color-surface-border); font-family: var(--font-mono);`">{{ task.priority }}</span>
        </div>

        <!-- Status control -->
        <div class="mt-4">
          <UiLabel>Status</UiLabel>
          <div class="flex flex-wrap gap-px mt-1.5" style="background-color: var(--color-surface-border); width: max-content; max-width: 100%;">
            <button v-for="s in STATUSES" :key="s" @click="setStatus(s)" :disabled="savingStatus"
              class="flex items-center gap-1.5 px-3 py-1.5 text-xs uppercase tracking-wider transition-colors"
              :style="task.status === s
                ? `background-color: ${statusColor[s]}; color: var(--color-accent-contrast);`
                : 'background-color: var(--color-surface-elevated); color: var(--color-text-muted);'">
              <span class="inline-block" style="width:6px;height:6px;" :style="`background-color: ${task.status === s ? 'var(--color-accent-contrast)' : statusColor[s]};`"></span>
              {{ s.replace('_', ' ') }}
            </button>
          </div>
        </div>

        <div class="flex flex-wrap gap-3 mt-4 text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">
          <span v-if="task.assignee">assignee: <span style="color: var(--color-text-primary);">{{ task.assignee.pilot ?? task.assignee.model }}</span></span>
          <span v-if="task.due_date">due: <span style="color: var(--color-text-primary);">{{ task.due_date }}</span></span>
          <span v-if="task.estimated_hours">est: <span style="color: var(--color-text-primary);">{{ task.estimated_hours }}h</span></span>
        </div>

        <div v-if="task.tags?.length" class="flex flex-wrap gap-1 mt-3">
          <span v-for="tag in task.tags" :key="tag" class="text-[0.65rem] px-1.5 py-0.5" style="background-color: var(--color-surface-base); color: var(--color-text-muted); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">{{ tag }}</span>
        </div>

        <p v-if="task.description" class="mt-4 text-sm" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ task.description }}</p>
      </UiCard>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <!-- Comments + composer -->
        <section class="space-y-3">
          <div class="flex items-center gap-3"><UiLabel tone="accent">Supervision</UiLabel><span class="flex-1" style="height:1px;background-color:var(--color-surface-border);"></span></div>

          <!-- Composer -->
          <UiCard pad="p-4">
            <div class="flex flex-wrap gap-px mb-2" style="background-color: var(--color-surface-border); width: max-content; max-width: 100%;">
              <button v-for="i in INTENTS" :key="i.key" @click="intent = i.key"
                class="px-2.5 py-1.5 text-[0.65rem] uppercase tracking-wider transition-colors"
                :style="intent === i.key ? `background-color: ${i.tone}; color: var(--color-accent-contrast);` : 'background-color: var(--color-surface-elevated); color: var(--color-text-muted);'">
                {{ i.key }}
              </button>
            </div>
            <textarea v-model="text" rows="3" placeholder="Leave an instruction, correction, approval…"
              class="w-full px-3 py-2.5 text-sm outline-none transition-colors resize-y"
              style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border);"
              @focus="$event.target.style.borderColor = 'var(--color-accent)'"
              @blur="$event.target.style.borderColor = 'var(--color-surface-border)'"></textarea>
            <div class="flex justify-end mt-2">
              <UiButton variant="solid" size="sm" :disabled="posting || !text.trim()" @click="postComment">{{ posting ? 'Posting…' : 'Post' }}</UiButton>
            </div>
          </UiCard>

          <!-- List -->
          <UiCard pad="p-0">
            <div v-if="!task.comments?.length" class="px-5 py-8 text-center text-sm" style="color: var(--color-text-muted);">No comments yet.</div>
            <ul v-else>
              <li v-for="(comment, i) in task.comments" :key="comment.id" class="px-4 py-3"
                :style="`box-shadow: inset 2px 0 0 ${commentTypeColor[comment.type]}; ${i > 0 ? 'border-top: 1px solid var(--color-surface-border);' : ''}`">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-medium" style="color: var(--color-text-primary);">{{ comment.actor?.pilot ?? comment.actor?.model }}</span>
                    <span class="text-[0.6rem] uppercase tracking-wider px-1 py-0.5" :style="`color: ${commentTypeColor[comment.type]}; border: 1px solid var(--color-surface-border); font-family: var(--font-mono);`">{{ comment.type }}</span>
                  </div>
                  <span class="text-[0.65rem]" style="color: var(--color-text-muted);">{{ comment.created_at }}</span>
                </div>
                <p class="text-sm" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ comment.text }}</p>
              </li>
            </ul>
          </UiCard>
        </section>

        <!-- Timeline -->
        <section class="space-y-3">
          <div class="flex items-center gap-3"><UiLabel tone="accent">Timeline</UiLabel><span class="flex-1" style="height:1px;background-color:var(--color-surface-border);"></span></div>
          <UiCard pad="p-0">
            <div v-if="!timeline.length" class="px-5 py-8 text-center text-sm" style="color: var(--color-text-muted);">No events yet.</div>
            <ul v-else class="px-5 py-4 space-y-4">
              <li v-for="event in timeline" :key="event.id" class="flex gap-3">
                <div class="flex flex-col items-center">
                  <UiStatusDot :tone="eventTone(event.type)" :size="7" class="mt-1" />
                  <div class="w-px flex-1 mt-1" style="background-color: var(--color-surface-border);"></div>
                </div>
                <div class="pb-2 min-w-0">
                  <span class="text-xs font-medium break-all" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ event.type }}</span>
                  <p class="text-[0.65rem] mt-0.5" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ event.actor_model }}<span v-if="event.actor_pilot"> · {{ event.actor_pilot }}</span> · {{ event.time_ago }}</p>
                  <p v-if="event.payload" class="text-[0.65rem] mt-0.5" style="color: var(--color-text-muted);">{{ Object.values(event.payload).join(' · ') }}</p>
                </div>
              </li>
            </ul>
          </UiCard>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
