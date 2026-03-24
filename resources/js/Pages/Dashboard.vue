<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    stats: Object,
    recentEvents: Array,
})

const eventColor = (type) => {
    if (type.includes('created'))        return 'var(--color-success)'
    if (type.includes('status_changed')) return 'var(--color-accent)'
    if (type.includes('blocked'))        return 'var(--color-danger)'
    if (type.includes('commented'))      return 'var(--color-warning)'
    if (type.includes('registered'))     return 'var(--color-accent)'
    return 'var(--color-neutral)'
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <h1 class="text-xl font-semibold" style="color: var(--color-text-primary);">Dashboard</h1>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div v-for="(item, key) in [
                    { label: 'Projects',      value: stats.projects,      color: 'var(--color-accent)' },
                    { label: 'Open Tasks',    value: stats.open_tasks,    color: 'var(--color-warning)' },
                    { label: 'Blocked',       value: stats.blocked_tasks, color: 'var(--color-danger)' },
                    { label: 'Active Agents', value: stats.agents,        color: 'var(--color-success)' },
                ]" :key="key"
                    class="rounded-lg p-5"
                    style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);"
                >
                    <p class="text-xs mb-2" style="color: var(--color-text-muted);">{{ item.label }}</p>
                    <p class="text-3xl font-bold" :style="{ color: item.color }">{{ item.value }}</p>
                </div>
            </div>

            <!-- Activity Feed -->
            <div class="rounded-lg" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                <div class="px-5 py-4 border-b" style="border-color: var(--color-surface-border);">
                    <h2 class="text-sm font-medium" style="color: var(--color-text-primary);">Activity Feed</h2>
                </div>

                <div v-if="recentEvents.length === 0" class="px-5 py-8 text-center text-sm" style="color: var(--color-text-muted);">
                    No activity yet.
                </div>

                <ul v-else class="divide-y" style="--tw-divide-color: var(--color-surface-border);">
                    <li v-for="event in recentEvents" :key="event.id"
                        class="flex items-start gap-4 px-5 py-4"
                    >
                        <!-- Dot -->
                        <div class="mt-1.5 w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: eventColor(event.type) }"></div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-medium" style="font-family: var(--font-mono); color: var(--color-text-primary);">{{ event.type }}</span>
                                <span class="text-xs" style="color: var(--color-accent);">{{ event.actor_model }}</span>
                                <span v-if="event.actor_pilot" class="text-xs" style="color: var(--color-text-muted);">via {{ event.actor_pilot }}</span>
                            </div>
                            <p v-if="event.payload && Object.keys(event.payload).length" class="text-xs mt-0.5 truncate" style="color: var(--color-text-muted);">
                                {{ Object.entries(event.payload).map(([k,v]) => `${k}: ${v}`).join(' · ') }}
                            </p>
                        </div>

                        <span class="text-xs shrink-0" style="color: var(--color-text-muted);">{{ event.time_ago }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
