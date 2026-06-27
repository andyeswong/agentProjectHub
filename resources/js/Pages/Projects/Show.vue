<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiBadge from '@/Components/atoms/UiBadge.vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import UiAgentTag from '@/Components/atoms/UiAgentTag.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ project: Object, kanban: Object })

const columns = [
  { key: 'backlog',     label: 'Backlog',     color: 'var(--color-neutral)' },
  { key: 'todo',        label: 'To Do',        color: 'var(--color-text-secondary)' },
  { key: 'in_progress', label: 'In Progress',  color: 'var(--color-warning)' },
  { key: 'done',        label: 'Done',         color: 'var(--color-success)' },
  { key: 'blocked',     label: 'Blocked',      color: 'var(--color-danger)' },
]
const colColor = Object.fromEntries(columns.map(c => [c.key, c.color]))
const priorityColor = { low: 'var(--color-neutral)', medium: 'var(--color-accent)', high: 'var(--color-warning)', critical: 'var(--color-danger)' }
const PLUS = 'M12 4v16m8-8H4'

// ── Move a task to a column ─────────────────────────────────────────────────
function move(task, status) {
  if (task.status === status) return
  router.patch(`/tasks/${task.id}`, { status }, { preserveScroll: true })
}

// ── Add task ────────────────────────────────────────────────────────────────
const adding = ref(false)
const form = ref({ title: '', status: 'todo', priority: 'medium' })
const creating = ref(false)
function createTask() {
  if (!form.value.title.trim() || creating.value) return
  creating.value = true
  router.post(`/projects/${props.project.id}/tasks`, form.value, {
    preserveScroll: true,
    onSuccess: () => { form.value = { title: '', status: 'todo', priority: 'medium' }; adding.value = false },
    onFinish: () => creating.value = false,
  })
}
</script>

<template>
  <AppLayout>
    <div class="space-y-6">

      <!-- Masthead -->
      <header>
        <div class="flex items-center gap-1.5 text-xs mb-2" style="color: var(--color-text-muted); font-family: var(--font-mono);">
          <Link href="/projects" style="color: var(--color-accent);">Projects</Link><span>/</span>
          <span class="truncate">{{ project.workspace?.name }}</span>
        </div>
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h1 class="font-display text-2xl md:text-3xl leading-tight" style="color: var(--color-text-primary); letter-spacing: -0.015em;">{{ project.name }}</h1>
            <p v-if="project.description" class="text-sm mt-1 line-clamp-2" style="color: var(--color-text-secondary);">{{ project.description }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <UiBadge :tone="project.status === 'active' ? 'success' : 'neutral'">{{ project.status }}</UiBadge>
            <UiButton variant="outline" size="sm" @click="adding = !adding"><UiIcon :path="PLUS" :size="14" /> Add task</UiButton>
          </div>
        </div>

        <div class="flex flex-wrap gap-3 mt-3 text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">
          <span>{{ project.task_counts.total }} tasks</span>
          <span style="color: var(--color-warning);">{{ project.task_counts.open }} open</span>
          <span style="color: var(--color-success);">{{ project.task_counts.done }} done</span>
          <span v-if="project.task_counts.blocked > 0" style="color: var(--color-danger);">{{ project.task_counts.blocked }} blocked</span>
        </div>
      </header>

      <!-- Add-task composer -->
      <div v-if="adding" class="p-4" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); box-shadow: inset 2px 0 0 var(--color-accent);">
        <div class="flex flex-col md:flex-row gap-2">
          <input v-model="form.title" placeholder="Task title…" @keydown.enter="createTask"
            class="flex-1 px-3 py-2 text-sm outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border);"
            @focus="$event.target.style.borderColor='var(--color-accent)'" @blur="$event.target.style.borderColor='var(--color-surface-border)'" />
          <select v-model="form.status" class="px-3 py-2 text-sm outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">
            <option v-for="c in columns" :key="c.key" :value="c.key">{{ c.label }}</option>
          </select>
          <select v-model="form.priority" class="px-3 py-2 text-sm outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);">
            <option value="low">low</option><option value="medium">medium</option><option value="high">high</option><option value="critical">critical</option>
          </select>
          <UiButton variant="solid" size="sm" :disabled="creating || !form.title.trim()" @click="createTask">{{ creating ? 'Adding…' : 'Create' }}</UiButton>
        </div>
      </div>

      <!-- Kanban -->
      <div class="flex gap-px overflow-x-auto pb-4" style="background-color: var(--color-surface-border);">
        <div v-for="col in columns" :key="col.key" class="shrink-0 w-[78vw] sm:w-72 flex flex-col" style="background-color: var(--color-surface-base);">
          <div class="flex items-center justify-between px-3 py-2.5" style="border-bottom: 1px solid var(--color-surface-border); box-shadow: inset 0 -2px 0 var(--color-surface-base), inset 0 -2px 0 transparent;">
            <span class="text-xs font-medium uppercase tracking-wider" :style="`color: ${col.color}; font-family: var(--font-mono);`">{{ col.label }}</span>
            <span class="text-[0.65rem] tabular-nums" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ (kanban[col.key] ?? []).length }}</span>
          </div>

          <div class="flex flex-col gap-2 p-2 flex-1 min-h-[80px]">
            <div v-for="task in (kanban[col.key] ?? [])" :key="task.id" class="p-3 group"
              style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);" :style="`box-shadow: inset 2px 0 0 ${col.color};`">
              <Link :href="`/tasks/${task.id}`" class="block">
                <p class="text-xs font-medium mb-2" style="color: var(--color-text-primary);">{{ task.title }}</p>
                <div class="flex items-center justify-between gap-2">
                  <span class="text-[0.6rem] uppercase tracking-wider px-1 py-0.5" :style="`color: ${priorityColor[task.priority]}; border: 1px solid var(--color-surface-border); font-family: var(--font-mono);`">{{ task.priority }}</span>
                  <UiAgentTag v-if="task.assignee" :handle="task.assignee?.model" :pilot="task.assignee?.pilot" size="xs" />
                </div>
              </Link>
              <!-- Move control -->
              <div class="flex items-center gap-1 mt-2 pt-2" style="border-top: 1px solid var(--color-surface-border);">
                <span class="text-[0.55rem] uppercase tracking-wider mr-1" style="color: var(--color-text-muted); font-family: var(--font-mono);">move</span>
                <button v-for="c in columns" :key="c.key" @click="move(task, c.key)" :title="c.label"
                  class="inline-block transition-opacity" :style="`width: 9px; height: 9px; background-color: ${c.color}; opacity: ${task.status === c.key ? '1' : '0.3'};`"></button>
              </div>
            </div>
            <div v-if="(kanban[col.key] ?? []).length === 0" class="text-xs text-center py-4" style="color: var(--color-text-muted);">Empty</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
