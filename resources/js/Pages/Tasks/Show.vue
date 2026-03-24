<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    task:     Object,
    timeline: Array,
})

const statusColor = {
    backlog:     'var(--color-neutral)',
    todo:        'var(--color-text-secondary)',
    in_progress: 'var(--color-warning)',
    done:        'var(--color-success)',
    blocked:     'var(--color-danger)',
}

const priorityColor = {
    low:      'var(--color-neutral)',
    medium:   'var(--color-accent)',
    high:     'var(--color-warning)',
    critical: 'var(--color-danger)',
}

const commentTypeColor = {
    instruction: 'var(--color-accent)',
    correction:  'var(--color-warning)',
    question:    'var(--color-text-secondary)',
    approval:    'var(--color-success)',
    general:     'var(--color-text-muted)',
}

const eventDotColor = (type) => {
    if (type.includes('created'))        return 'var(--color-success)'
    if (type.includes('status_changed')) return 'var(--color-accent)'
    if (type.includes('blocked'))        return 'var(--color-danger)'
    if (type.includes('commented'))      return 'var(--color-warning)'
    return 'var(--color-neutral)'
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">

            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-xs" style="color: var(--color-text-muted);">
                <Link href="/projects" style="color: var(--color-accent);">Projects</Link>
                <span>/</span>
                <Link :href="`/projects/${task.project_id}`" style="color: var(--color-accent);">{{ task.project?.name }}</Link>
                <span>/</span>
                <span style="color: var(--color-text-primary);">{{ task.title }}</span>
            </div>

            <!-- Task header -->
            <div class="rounded-lg p-6" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                <div class="flex items-start justify-between gap-4">
                    <h1 class="text-xl font-semibold" style="color: var(--color-text-primary);">{{ task.title }}</h1>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-xs px-2 py-1 rounded-full"
                            :style="{ backgroundColor: statusColor[task.status] + '20', color: statusColor[task.status] }">
                            {{ task.status }}
                        </span>
                        <span class="text-xs px-2 py-1 rounded"
                            :style="{ backgroundColor: priorityColor[task.priority] + '20', color: priorityColor[task.priority], fontFamily: 'var(--font-mono)' }">
                            {{ task.priority }}
                        </span>
                    </div>
                </div>

                <!-- Meta row -->
                <div class="flex flex-wrap gap-4 mt-4 text-xs" style="color: var(--color-text-muted);">
                    <span v-if="task.assignee">
                        Assignee: <span style="color: var(--color-text-primary);">{{ task.assignee.pilot ?? task.assignee.model }}</span>
                    </span>
                    <span v-if="task.due_date">
                        Due: <span style="color: var(--color-text-primary);">{{ task.due_date }}</span>
                    </span>
                    <span v-if="task.start_date">
                        Start: <span style="color: var(--color-text-primary);">{{ task.start_date }}</span>
                    </span>
                    <span v-if="task.estimated_hours">
                        Est: <span style="color: var(--color-text-primary);">{{ task.estimated_hours }}h</span>
                    </span>
                </div>

                <!-- Tags -->
                <div v-if="task.tags?.length" class="flex flex-wrap gap-2 mt-3">
                    <span v-for="tag in task.tags" :key="tag"
                        class="text-xs px-2 py-0.5 rounded-full"
                        style="background-color: var(--color-surface-border); color: var(--color-text-secondary);">
                        {{ tag }}
                    </span>
                </div>

                <!-- Description -->
                <p v-if="task.description" class="mt-4 text-sm" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ task.description }}</p>
            </div>

            <!-- Two columns: comments + timeline -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                <!-- Comments -->
                <div class="rounded-lg" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <div class="px-5 py-4 border-b" style="border-color: var(--color-surface-border);">
                        <h2 class="text-sm font-medium" style="color: var(--color-text-primary);">Comments</h2>
                    </div>

                    <div v-if="task.comments?.length === 0" class="px-5 py-8 text-center text-sm" style="color: var(--color-text-muted);">
                        No comments yet.
                    </div>

                    <ul v-else class="divide-y" style="--tw-divide-color: var(--color-surface-border);">
                        <li v-for="comment in task.comments" :key="comment.id" class="px-5 py-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-medium" style="color: var(--color-text-primary);">
                                        {{ comment.actor?.pilot ?? comment.actor?.model }}
                                    </span>
                                    <span class="text-xs px-1.5 py-0.5 rounded"
                                        :style="{ backgroundColor: commentTypeColor[comment.type] + '20', color: commentTypeColor[comment.type], fontFamily: 'var(--font-mono)' }">
                                        {{ comment.type }}
                                    </span>
                                </div>
                                <span class="text-xs" style="color: var(--color-text-muted);">{{ comment.created_at }}</span>
                            </div>
                            <p class="text-sm" style="color: var(--color-text-secondary); white-space: pre-wrap;">{{ comment.text }}</p>
                        </li>
                    </ul>
                </div>

                <!-- Timeline -->
                <div class="rounded-lg" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <div class="px-5 py-4 border-b" style="border-color: var(--color-surface-border);">
                        <h2 class="text-sm font-medium" style="color: var(--color-text-primary);">Timeline</h2>
                    </div>

                    <div v-if="timeline.length === 0" class="px-5 py-8 text-center text-sm" style="color: var(--color-text-muted);">
                        No events yet.
                    </div>

                    <ul v-else class="px-5 py-4 space-y-4">
                        <li v-for="event in timeline" :key="event.id" class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div class="w-2 h-2 rounded-full mt-1 shrink-0" :style="{ backgroundColor: eventDotColor(event.type) }"></div>
                                <div class="w-px flex-1 mt-1" style="background-color: var(--color-surface-border);"></div>
                            </div>
                            <div class="pb-4">
                                <span class="text-xs font-medium" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ event.type }}</span>
                                <p class="text-xs mt-0.5" style="color: var(--color-accent);">{{ event.actor_model }}
                                    <span v-if="event.actor_pilot" style="color: var(--color-text-muted);">— {{ event.actor_pilot }}</span>
                                </p>
                                <p v-if="event.payload" class="text-xs mt-1" style="color: var(--color-text-muted);">
                                    {{ Object.values(event.payload).join(' · ') }}
                                </p>
                                <p class="text-xs mt-1" style="color: var(--color-text-muted);">{{ event.time_ago }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
