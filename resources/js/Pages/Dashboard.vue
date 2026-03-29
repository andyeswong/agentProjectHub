<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import InviteAgentPanel from '@/Components/InviteAgentPanel.vue'
import { ref, computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'

const props = defineProps({
    stats:          Object,
    recentProjects: Array,
    recentEvents:   Array,
})

const page    = usePage()
const auth    = computed(() => page.props.auth)
const inviteOpen = ref(false)

// ── Event helpers ────────────────────────────────────────────────────────────

const eventMeta = (type) => {
    if (type === 'task.created')        return { icon: 'M12 4v16m8-8H4',                                              color: '#22c55e',  label: 'Task created' }
    if (type === 'task.status_changed') return { icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', color: '#38bdf8',  label: 'Status changed' }
    if (type === 'task.blocked')        return { icon: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636', color: '#ef4444',  label: 'Task blocked' }
    if (type === 'task.archived')       return { icon: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12',   color: '#64748b',  label: 'Task archived' }
    if (type === 'task.moved')          return { icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',         color: '#a78bfa',  label: 'Task moved' }
    if (type === 'task.commented')      return { icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', color: '#f59e0b', label: 'Comment added' }
    if (type === 'task.updated')        return { icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', color: '#38bdf8', label: 'Task updated' }
    if (type === 'project.created')     return { icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z', color: '#22c55e', label: 'Project created' }
    if (type === 'project.updated')     return { icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z', color: '#38bdf8', label: 'Project updated' }
    if (type === 'agent.registered')    return { icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v10m0 0h10M9 13H5m4 0a2 2 0 010 4H5a2 2 0 010-4', color: '#a78bfa', label: 'Agent registered' }
    if (type === 'pilot.login')         return { icon: 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1', color: '#38bdf8', label: 'Pilot login' }
    return { icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: '#64748b', label: type }
}

const formatPayload = (payload) => {
    if (!payload || !Object.keys(payload).length) return null
    return Object.entries(payload)
        .filter(([, v]) => v !== null && v !== undefined && v !== '')
        .map(([k, v]) => `${k.replace(/_/g, ' ')}: ${v}`)
        .join('  ·  ')
}

const shortModel = (model) => model ? model.split('-').slice(0, 3).join('-') : '—'
</script>

<template>
    <AppLayout>
        <div class="space-y-7">

            <!-- ── Welcome header ── -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">
                        Welcome back, <span style="color: var(--color-accent);">{{ auth?.pilot ?? 'Pilot' }}</span>
                    </h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: var(--color-success);"></span>
                        <span class="text-sm" style="color: var(--color-text-muted);">
                            {{ auth?.org?.name }}
                            <span class="mx-1" style="color: var(--color-surface-border);">·</span>
                            <span style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ auth?.agent?.model }}</span>
                        </span>
                    </div>
                </div>

                <button @click="inviteOpen = !inviteOpen"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all self-start sm:self-auto"
                    :style="inviteOpen
                        ? 'background-color: rgba(56,189,248,0.12); color: var(--color-accent); border: 1px solid rgba(56,189,248,0.3);'
                        : 'background-color: var(--color-surface-elevated); color: var(--color-text-secondary); border: 1px solid var(--color-surface-border);'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Invite Agent
                </button>
            </div>

            <!-- ── Invite panel ── -->
            <InviteAgentPanel v-if="inviteOpen" />

            <!-- ── Stat cards ── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

                <!-- Projects -->
                <div class="rounded-xl p-5 flex flex-col gap-3"
                    style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-text-muted);">Projects</span>
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(56,189,248,0.1);">
                            <svg class="w-4 h-4" style="color: var(--color-accent);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <p class="text-3xl font-bold" style="color: var(--color-accent);">{{ stats.projects }}</p>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">active workspaces</p>
                    </div>
                </div>

                <!-- Open tasks -->
                <div class="rounded-xl p-5 flex flex-col gap-3"
                    style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-text-muted);">Open Tasks</span>
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(245,158,11,0.1);">
                            <svg class="w-4 h-4" style="color: var(--color-warning);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <p class="text-3xl font-bold" style="color: var(--color-warning);">{{ stats.open_tasks }}</p>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-muted);">of {{ stats.total_tasks }} total</p>
                    </div>
                </div>

                <!-- Done / progress -->
                <div class="rounded-xl p-5 flex flex-col gap-3"
                    style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-text-muted);">Completed</span>
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: rgba(34,197,94,0.1);">
                            <svg class="w-4 h-4" style="color: var(--color-success);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-end gap-2">
                            <p class="text-3xl font-bold" style="color: var(--color-success);">{{ stats.done_percent }}%</p>
                            <p class="text-sm mb-0.5" style="color: var(--color-text-muted);">{{ stats.done_tasks }} done</p>
                        </div>
                        <div class="mt-2 h-1.5 rounded-full overflow-hidden" style="background-color: var(--color-surface-border);">
                            <div class="h-full rounded-full transition-all duration-700"
                                style="background: linear-gradient(90deg, #22c55e, #38bdf8);"
                                :style="{ width: stats.done_percent + '%' }">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Blocked + Agents -->
                <div class="rounded-xl p-5 flex flex-col gap-3"
                    style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-text-muted);">Blocked</span>
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                            :style="stats.blocked_tasks > 0 ? 'background-color: rgba(239,68,68,0.1);' : 'background-color: var(--color-surface-border);'">
                            <svg class="w-4 h-4" :style="stats.blocked_tasks > 0 ? 'color: var(--color-danger);' : 'color: var(--color-text-muted);'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <p class="text-3xl font-bold" :style="stats.blocked_tasks > 0 ? 'color: var(--color-danger);' : 'color: var(--color-text-muted);'">
                            {{ stats.blocked_tasks }}
                        </p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: var(--color-success);"></span>
                            <p class="text-xs" style="color: var(--color-text-muted);">{{ stats.agents }} agent{{ stats.agents !== 1 ? 's' : '' }} active</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Recent projects + Activity feed ── -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

                <!-- Recent projects (3/5) -->
                <div class="lg:col-span-3 space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold" style="color: var(--color-text-primary);">Recent Projects</h2>
                        <Link href="/projects" class="text-xs transition-colors"
                            style="color: var(--color-text-muted);"
                            onmouseover="this.style.color='var(--color-accent)'"
                            onmouseout="this.style.color='var(--color-text-muted)'">
                            View all →
                        </Link>
                    </div>

                    <div v-if="recentProjects.length === 0"
                        class="rounded-xl p-10 text-center text-sm"
                        style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); color: var(--color-text-muted);">
                        No active projects yet.
                    </div>

                    <div v-else class="space-y-2">
                        <Link v-for="p in recentProjects" :key="p.id" :href="`/projects/${p.id}`"
                            class="block rounded-xl p-4 transition-all"
                            style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); text-decoration: none;"
                            onmouseover="this.style.borderColor='rgba(56,189,248,0.3)'; this.style.backgroundColor='var(--color-surface-hover)'"
                            onmouseout="this.style.borderColor='var(--color-surface-border)'; this.style.backgroundColor='var(--color-surface-elevated)'">

                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold truncate" style="color: var(--color-text-primary);">{{ p.name }}</p>
                                    <p v-if="p.description" class="text-xs truncate mt-0.5" style="color: var(--color-text-muted);">{{ p.description }}</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span v-if="p.blocked_tasks > 0"
                                        class="text-xs px-2 py-0.5 rounded-full font-medium"
                                        style="background-color: rgba(239,68,68,0.12); color: var(--color-danger);">
                                        {{ p.blocked_tasks }} blocked
                                    </span>
                                    <span class="text-xs" style="color: var(--color-success);">{{ p.done_percent }}%</span>
                                </div>
                            </div>

                            <!-- Progress bar -->
                            <div class="h-1.5 rounded-full overflow-hidden mb-2" style="background-color: var(--color-surface-border);">
                                <div class="h-full rounded-full transition-all duration-500"
                                    style="background: linear-gradient(90deg, #22c55e, #38bdf8);"
                                    :style="{ width: p.done_percent + '%' }">
                                </div>
                            </div>

                            <!-- Task pills -->
                            <div class="flex items-center gap-3 text-xs">
                                <span style="color: var(--color-text-muted);">
                                    <span class="font-medium" style="color: var(--color-warning);">{{ p.open_tasks }}</span> open
                                </span>
                                <span style="color: var(--color-surface-border);">·</span>
                                <span style="color: var(--color-text-muted);">
                                    <span class="font-medium" style="color: var(--color-success);">{{ p.done_tasks }}</span> done
                                </span>
                                <span style="color: var(--color-surface-border);">·</span>
                                <span style="color: var(--color-text-muted);">{{ p.total_tasks }} total</span>
                                <span class="ml-auto" style="color: var(--color-text-muted); font-size: 0.7rem;">{{ p.updated_at }}</span>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Activity feed (2/5) -->
                <div class="lg:col-span-2 flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold" style="color: var(--color-text-primary);">Activity</h2>
                        <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: var(--color-surface-border); color: var(--color-text-muted);">
                            live
                            <span class="inline-block w-1.5 h-1.5 rounded-full ml-1 align-middle" style="background-color: var(--color-success);"></span>
                        </span>
                    </div>

                    <div class="rounded-xl overflow-hidden flex-1"
                        style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">

                        <div v-if="recentEvents.length === 0"
                            class="px-5 py-12 text-center text-sm"
                            style="color: var(--color-text-muted);">
                            No activity yet.
                        </div>

                        <ul v-else class="divide-y overflow-y-auto max-h-[520px]" style="border-color: var(--color-surface-border);">
                            <li v-for="event in recentEvents" :key="event.id"
                                class="flex items-start gap-3 px-4 py-3 transition-colors"
                                onmouseover="this.style.backgroundColor='var(--color-surface-hover)'"
                                onmouseout="this.style.backgroundColor=''">

                                <!-- Icon -->
                                <div class="mt-0.5 w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                                    :style="{ backgroundColor: eventMeta(event.type).color + '18' }">
                                    <svg class="w-3.5 h-3.5" :style="{ color: eventMeta(event.type).color }"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            :d="eventMeta(event.type).icon" />
                                    </svg>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline justify-between gap-1">
                                        <span class="text-xs font-medium truncate" style="color: var(--color-text-primary);">
                                            {{ eventMeta(event.type).label }}
                                        </span>
                                        <span class="text-xs shrink-0" style="color: var(--color-text-muted); font-size: 0.65rem;">
                                            {{ event.time_ago }}
                                        </span>
                                    </div>
                                    <p class="text-xs mt-0.5" style="color: var(--color-text-muted); font-family: var(--font-mono); font-size: 0.65rem;">
                                        {{ shortModel(event.actor_model) }}<span v-if="event.actor_pilot"> · {{ event.actor_pilot }}</span>
                                    </p>
                                    <p v-if="formatPayload(event.payload)" class="text-xs mt-0.5 truncate" style="color: var(--color-text-muted); font-size: 0.65rem;">
                                        {{ formatPayload(event.payload) }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
