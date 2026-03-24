<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    project: Object,
    kanban:  Object,
})

const columns = [
    { key: 'backlog',     label: 'Backlog',      color: 'var(--color-neutral)' },
    { key: 'todo',        label: 'To Do',         color: 'var(--color-text-secondary)' },
    { key: 'in_progress', label: 'In Progress',   color: 'var(--color-warning)' },
    { key: 'done',        label: 'Done',          color: 'var(--color-success)' },
    { key: 'blocked',     label: 'Blocked',       color: 'var(--color-danger)' },
]

const priorityColor = {
    low:      'var(--color-neutral)',
    medium:   'var(--color-accent)',
    high:     'var(--color-warning)',
    critical: 'var(--color-danger)',
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">

            <!-- Header -->
            <div>
                <div class="flex items-center gap-2 text-xs mb-2" style="color: var(--color-text-muted);">
                    <Link href="/projects" style="color: var(--color-accent);">Projects</Link>
                    <span>/</span>
                    <span>{{ project.workspace?.name }}</span>
                </div>
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-xl font-semibold" style="color: var(--color-text-primary);">{{ project.name }}</h1>
                        <p v-if="project.description" class="text-sm mt-1" style="color: var(--color-text-secondary);">{{ project.description }}</p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full mt-1"
                        :style="project.status === 'active'
                            ? 'background-color: rgba(34,197,94,0.1); color: var(--color-success);'
                            : 'background-color: var(--color-surface-border); color: var(--color-text-muted);'">
                        {{ project.status }}
                    </span>
                </div>

                <!-- Stats row -->
                <div class="flex gap-4 mt-3 text-xs" style="color: var(--color-text-muted);">
                    <span>{{ project.task_counts.total }} tasks</span>
                    <span style="color: var(--color-warning);">{{ project.task_counts.open }} open</span>
                    <span style="color: var(--color-success);">{{ project.task_counts.done }} done</span>
                    <span v-if="project.task_counts.blocked > 0" style="color: var(--color-danger);">{{ project.task_counts.blocked }} blocked</span>
                </div>
            </div>

            <!-- Kanban board -->
            <div class="flex gap-4 overflow-x-auto pb-4">
                <div
                    v-for="col in columns"
                    :key="col.key"
                    class="shrink-0 w-64 rounded-lg p-3 flex flex-col gap-3"
                    style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border);"
                >
                    <!-- Column header -->
                    <div class="flex items-center justify-between px-1">
                        <span class="text-xs font-semibold uppercase tracking-wider" :style="{ color: col.color, fontFamily: 'var(--font-mono)' }">
                            {{ col.label }}
                        </span>
                        <span class="text-xs px-1.5 py-0.5 rounded-full" style="background-color: var(--color-surface-border); color: var(--color-text-muted);">
                            {{ (kanban[col.key] ?? []).length }}
                        </span>
                    </div>

                    <!-- Task cards -->
                    <Link
                        v-for="task in (kanban[col.key] ?? [])"
                        :key="task.id"
                        :href="`/tasks/${task.id}`"
                        class="block rounded-md p-3 border-l-2 transition-colors"
                        :style="{ borderColor: col.color, backgroundColor: 'var(--color-surface-elevated)', border: `1px solid var(--color-surface-border)`, borderLeft: `2px solid ${col.color}` }"
                        onmouseover="this.style.borderColor='rgba(56,189,248,0.3)'; this.style.borderLeftColor=this.style.borderLeftColor"
                        onmouseout="this.style.borderColor='var(--color-surface-border)'"
                    >
                        <p class="text-xs font-medium mb-2" style="color: var(--color-text-primary);">{{ task.title }}</p>

                        <div class="flex items-center justify-between">
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                :style="{ backgroundColor: priorityColor[task.priority] + '20', color: priorityColor[task.priority], fontFamily: 'var(--font-mono)' }">
                                {{ task.priority }}
                            </span>

                            <span v-if="task.assignee" class="text-xs truncate max-w-[80px]" style="color: var(--color-text-muted);">
                                {{ task.assignee.pilot ?? task.assignee.model }}
                            </span>
                        </div>

                        <p v-if="task.due_date" class="text-xs mt-1" style="color: var(--color-text-muted);">
                            Due {{ task.due_date }}
                        </p>
                    </Link>

                    <div v-if="(kanban[col.key] ?? []).length === 0" class="text-xs text-center py-4" style="color: var(--color-text-muted);">
                        Empty
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
