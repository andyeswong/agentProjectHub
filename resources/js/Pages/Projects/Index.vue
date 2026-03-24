<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    projects: Array,
    filters:  Object,
})

const statusFilter = ref(props.filters?.status ?? '')

function applyFilter(status) {
    statusFilter.value = status
    router.get('/projects', status ? { status } : {}, { preserveState: true, replace: true })
}

const statusColor = {
    active:   'var(--color-success)',
    archived: 'var(--color-neutral)',
}
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold" style="color: var(--color-text-primary);">Projects</h1>

                <!-- Filter tabs -->
                <div class="flex gap-1 rounded-lg p-1" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <button v-for="opt in [{label:'All', value:''},{label:'Active',value:'active'},{label:'Archived',value:'archived'}]"
                        :key="opt.value"
                        @click="applyFilter(opt.value)"
                        class="px-3 py-1 rounded text-xs transition-colors"
                        :style="statusFilter === opt.value
                            ? 'background-color: var(--color-accent); color: #0d0f14; font-weight:600;'
                            : 'color: var(--color-text-secondary);'"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- Empty -->
            <div v-if="projects.length === 0" class="rounded-lg p-10 text-center text-sm"
                style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">
                No projects found.
            </div>

            <!-- Grid -->
            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="project in projects"
                    :key="project.id"
                    :href="`/projects/${project.id}`"
                    class="block rounded-lg p-5 transition-colors group"
                    style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);"
                    onmouseover="this.style.borderColor='rgba(56,189,248,0.3)'"
                    onmouseout="this.style.borderColor='var(--color-surface-border)'"
                >
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="font-medium truncate pr-2" style="color: var(--color-text-primary);">{{ project.name }}</h3>
                        <span class="text-xs px-2 py-0.5 rounded-full shrink-0"
                            :style="{ backgroundColor: statusColor[project.status] + '20', color: statusColor[project.status] }">
                            {{ project.status }}
                        </span>
                    </div>

                    <p v-if="project.description" class="text-xs mb-3 line-clamp-2" style="color: var(--color-text-secondary);">
                        {{ project.description }}
                    </p>

                    <!-- Task progress bar -->
                    <div class="mb-3">
                        <div class="flex justify-between text-xs mb-1" style="color: var(--color-text-muted);">
                            <span>{{ project.task_counts.done }} / {{ project.task_counts.total }} done</span>
                            <span v-if="project.task_counts.blocked > 0" style="color: var(--color-danger);">{{ project.task_counts.blocked }} blocked</span>
                        </div>
                        <div class="h-1 rounded-full overflow-hidden" style="background-color: var(--color-surface-border);">
                            <div class="h-full rounded-full" style="background-color: var(--color-success);"
                                :style="{ width: project.task_counts.total > 0 ? ((project.task_counts.done / project.task_counts.total) * 100) + '%' : '0%' }">
                            </div>
                        </div>
                    </div>

                    <!-- Meta -->
                    <div class="flex items-center justify-between text-xs" style="color: var(--color-text-muted);">
                        <span>{{ project.workspace?.name }}</span>
                        <span>{{ project.task_counts.open }} open</span>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
