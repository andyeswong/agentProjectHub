<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    org:      Object,
    projects: Array,
    stats:    Object,
})

const columns = [
    { key: 'backlog',     label: 'Backlog',     color: '#64748b' },
    { key: 'todo',        label: 'To Do',        color: '#94a3b8' },
    { key: 'in_progress', label: 'In Progress',  color: '#f59e0b' },
    { key: 'done',        label: 'Done',         color: '#22c55e' },
    { key: 'blocked',     label: 'Blocked',      color: '#ef4444' },
]

const priorityColor = {
    low:      '#64748b',
    medium:   '#38bdf8',
    high:     '#f59e0b',
    critical: '#ef4444',
}

// All projects open by default, collapsible
const open = ref(Object.fromEntries(props.projects.map(p => [p.id, true])))
function toggle(id) { open.value[id] = !open.value[id] }

const donePercent = (p) => p.task_counts.total > 0
    ? Math.round((p.task_counts.done / p.task_counts.total) * 100)
    : 0

const totalPercent = computed(() =>
    props.stats.total_tasks > 0
        ? Math.round((props.stats.done / props.stats.total_tasks) * 100)
        : 0
)
</script>

<template>
    <div class="min-h-[100dvh]" style="background-color: #0d0f14; color: #e2e8f0;">

        <!-- Top bar -->
        <header class="sticky top-0 z-30 border-b px-4 md:px-8"
            style="background-color: rgba(13,15,20,0.92); border-color: #1e2433; backdrop-filter: blur(12px);">
            <div class="max-w-7xl mx-auto flex items-center justify-between h-14">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="text-sm font-bold tracking-tight" style="color: #38bdf8;">ProjectHub</span>
                    <span class="hidden sm:block text-xs px-1.5 py-0.5 rounded" style="background-color: #1e2433; color: #94a3b8; font-family: monospace;">LLM</span>
                    <span class="text-xs" style="color: #475569;">/</span>
                    <span class="text-sm font-semibold truncate" style="color: #e2e8f0;">{{ org.name }}</span>
                </div>
                <Link href="/login"
                    class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg shrink-0 transition-all"
                    style="background-color: #1e2433; color: #38bdf8; border: 1px solid #1e2433;"
                    onmouseover="this.style.borderColor='rgba(56,189,248,0.4)'"
                    onmouseout="this.style.borderColor='#1e2433'">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    Login
                </Link>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 md:px-8 py-6 md:py-10 space-y-8">

            <!-- Org hero -->
            <div class="space-y-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold" style="color: #f1f5f9;">{{ org.name }}</h1>
                    <p class="text-sm mt-1" style="color: #64748b; font-family: monospace;">{{ org.slug }}</p>
                </div>

                <!-- Stat pills -->
                <div class="flex flex-wrap gap-3">
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs"
                        style="background-color: #151821; border: 1px solid #1e2433;">
                        <span style="color: #64748b;">Projects</span>
                        <span class="font-bold" style="color: #38bdf8;">{{ stats.projects }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs"
                        style="background-color: #151821; border: 1px solid #1e2433;">
                        <span style="color: #64748b;">Open</span>
                        <span class="font-bold" style="color: #f59e0b;">{{ stats.open }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs"
                        style="background-color: #151821; border: 1px solid #1e2433;">
                        <span style="color: #64748b;">Done</span>
                        <span class="font-bold" style="color: #22c55e;">{{ stats.done }}</span>
                    </div>
                    <div v-if="stats.blocked > 0" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs"
                        style="background-color: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2);">
                        <span style="color: #94a3b8;">Blocked</span>
                        <span class="font-bold" style="color: #ef4444;">{{ stats.blocked }}</span>
                    </div>
                </div>

                <!-- Overall progress bar -->
                <div v-if="stats.total_tasks > 0">
                    <div class="flex justify-between text-xs mb-1.5" style="color: #64748b;">
                        <span>Overall progress</span>
                        <span style="color: #22c55e;">{{ totalPercent }}%</span>
                    </div>
                    <div class="h-2 rounded-full overflow-hidden" style="background-color: #1e2433;">
                        <div class="h-full rounded-full transition-all duration-700"
                            style="background: linear-gradient(90deg, #22c55e, #38bdf8);"
                            :style="{ width: totalPercent + '%' }">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project list -->
            <div v-if="projects.length === 0" class="rounded-xl p-12 text-center text-sm"
                style="background-color: #151821; border: 1px solid #1e2433; color: #64748b;">
                No active projects.
            </div>

            <div v-else class="space-y-5">
                <div v-for="project in projects" :key="project.id"
                    class="rounded-xl overflow-hidden"
                    style="background-color: #151821; border: 1px solid #1e2433;">

                    <!-- Project header — tap to collapse -->
                    <button
                        @click="toggle(project.id)"
                        class="w-full flex items-center gap-4 px-4 md:px-6 py-4 text-left transition-colors"
                        style="background-color: #151821;"
                        onmouseover="this.style.backgroundColor='#1a1f2e'"
                        onmouseout="this.style.backgroundColor='#151821'">

                        <!-- Chevron -->
                        <svg class="w-4 h-4 shrink-0 transition-transform duration-200"
                            :class="open[project.id] ? 'rotate-90' : ''"
                            style="color: #475569;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>

                        <!-- Name + workspace -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-semibold" style="color: #f1f5f9;">{{ project.name }}</span>
                                <span v-if="project.workspace" class="text-xs px-1.5 py-0.5 rounded"
                                    style="background-color: #1e2433; color: #64748b; font-family: monospace;">
                                    {{ project.workspace }}
                                </span>
                            </div>
                            <p v-if="project.description && open[project.id]" class="text-xs mt-0.5 line-clamp-1" style="color: #64748b;">
                                {{ project.description }}
                            </p>
                        </div>

                        <!-- Mini stats -->
                        <div class="flex items-center gap-3 shrink-0">
                            <!-- Progress ring / text -->
                            <div class="hidden sm:flex items-center gap-2">
                                <div class="w-16 h-1.5 rounded-full overflow-hidden" style="background-color: #1e2433;">
                                    <div class="h-full rounded-full" style="background-color: #22c55e;"
                                        :style="{ width: donePercent(project) + '%' }"></div>
                                </div>
                                <span class="text-xs" style="color: #22c55e;">{{ donePercent(project) }}%</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <span style="color: #f59e0b;">{{ project.task_counts.open }} open</span>
                                <span v-if="project.task_counts.blocked > 0" style="color: #ef4444;">
                                    · {{ project.task_counts.blocked }} blocked
                                </span>
                            </div>
                        </div>
                    </button>

                    <!-- Kanban board — collapsible -->
                    <div v-if="open[project.id]" class="border-t" style="border-color: #1e2433;">

                        <!-- Horizontal snap scroll -->
                        <div class="-mb-px">
                            <div class="flex gap-3 overflow-x-auto px-4 md:px-6 py-4 snap-x snap-mandatory"
                                style="scrollbar-width: thin; scrollbar-color: #1e2433 transparent;">

                                <div v-for="col in columns" :key="col.key"
                                    class="shrink-0 w-[72vw] sm:w-56 md:w-60 rounded-lg p-3 flex flex-col gap-2 snap-start"
                                    style="background-color: #0d0f14; border: 1px solid #1e2433;">

                                    <!-- Column header -->
                                    <div class="flex items-center justify-between px-1 mb-1">
                                        <span class="text-xs font-semibold uppercase tracking-wider"
                                            :style="{ color: col.color, fontFamily: 'monospace' }">
                                            {{ col.label }}
                                        </span>
                                        <span class="text-xs px-1.5 py-0.5 rounded-full"
                                            style="background-color: #1e2433; color: #64748b;">
                                            {{ (project.kanban[col.key] ?? []).length }}
                                        </span>
                                    </div>

                                    <!-- Task cards -->
                                    <div v-for="task in (project.kanban[col.key] ?? [])" :key="task.id"
                                        class="rounded-md p-3"
                                        :style="{ backgroundColor: '#151821', borderLeft: `2px solid ${col.color}`, border: `1px solid #1e2433`, borderLeftWidth: '2px', borderLeftColor: col.color }">

                                        <p class="text-xs font-medium mb-2 leading-snug" style="color: #e2e8f0;">{{ task.title }}</p>

                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-xs px-1.5 py-0.5 rounded shrink-0"
                                                :style="{ backgroundColor: priorityColor[task.priority] + '18', color: priorityColor[task.priority], fontFamily: 'monospace' }">
                                                {{ task.priority }}
                                            </span>
                                            <span v-if="task.assignee" class="text-xs truncate" style="color: #64748b;">
                                                {{ task.assignee.pilot ?? task.assignee.model }}
                                            </span>
                                        </div>

                                        <p v-if="task.due_date" class="text-xs mt-1.5" style="color: #475569;">
                                            Due {{ task.due_date }}
                                        </p>
                                    </div>

                                    <div v-if="(project.kanban[col.key] ?? []).length === 0"
                                        class="text-xs text-center py-3" style="color: #334155;">
                                        —
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile column dots -->
                        <div class="flex justify-center gap-1.5 pb-3 sm:hidden">
                            <div v-for="col in columns" :key="col.key"
                                class="w-1.5 h-1.5 rounded-full"
                                :style="{ backgroundColor: col.color, opacity: '0.5' }">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-4 pb-8 border-t text-xs" style="border-color: #1e2433; color: #334155;">
                <span>
                    Powered by
                    <span style="color: #38bdf8; font-family: monospace;">ProjectHub LLM</span>
                </span>
                <Link href="/login" style="color: #475569;"
                    onmouseover="this.style.color='#38bdf8'"
                    onmouseout="this.style.color='#475569'">
                    Pilot login →
                </Link>
            </div>
        </div>
    </div>
</template>
