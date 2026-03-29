<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    orgs:  Array,
    total: Object,
})

const totalPercent = computed(() =>
    props.total.tasks > 0
        ? Math.round((props.total.done / props.total.tasks) * 100)
        : 0
)

const donePercent = (org) =>
    org.task_stats.total > 0
        ? Math.round((org.task_stats.done / org.task_stats.total) * 100)
        : 0
</script>

<template>
    <div class="min-h-[100dvh]" style="background-color: #0d0f14; color: #e2e8f0;">

        <!-- Top bar -->
        <header class="sticky top-0 z-30 border-b px-4 md:px-8"
            style="background-color: rgba(13,15,20,0.92); border-color: #1e2433; backdrop-filter: blur(12px);">
            <div class="max-w-5xl mx-auto flex items-center justify-between h-14">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold tracking-tight" style="color: #38bdf8;">ProjectHub</span>
                    <span class="hidden sm:block text-xs px-1.5 py-0.5 rounded"
                        style="background-color: #1e2433; color: #94a3b8; font-family: monospace;">LLM</span>
                    <span class="text-xs" style="color: #475569;">/</span>
                    <span class="text-sm font-semibold" style="color: #e2e8f0;">Public Boards</span>
                </div>
                <Link href="/login"
                    class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg transition-all"
                    style="background-color: #1e2433; color: #38bdf8; border: 1px solid #1e2433;"
                    onmouseover="this.style.borderColor='rgba(56,189,248,0.4)'"
                    onmouseout="this.style.borderColor='#1e2433'">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    Login
                </Link>
            </div>
        </header>

        <div class="max-w-5xl mx-auto px-4 md:px-8 py-8 md:py-12 space-y-8">

            <!-- Hero -->
            <div class="space-y-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold" style="color: #f1f5f9;">Public Boards</h1>
                    <p class="text-sm mt-1" style="color: #64748b;">
                        All organizations with public project boards.
                    </p>
                </div>

                <!-- Global stats -->
                <div class="flex flex-wrap gap-3">
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs"
                        style="background-color: #151821; border: 1px solid #1e2433;">
                        <span style="color: #64748b;">Orgs</span>
                        <span class="font-bold" style="color: #38bdf8;">{{ total.orgs }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs"
                        style="background-color: #151821; border: 1px solid #1e2433;">
                        <span style="color: #64748b;">Open tasks</span>
                        <span class="font-bold" style="color: #f59e0b;">{{ total.open }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs"
                        style="background-color: #151821; border: 1px solid #1e2433;">
                        <span style="color: #64748b;">Done</span>
                        <span class="font-bold" style="color: #22c55e;">{{ total.done }}</span>
                    </div>
                </div>

                <!-- Global progress bar -->
                <div v-if="total.tasks > 0">
                    <div class="flex justify-between text-xs mb-1.5" style="color: #64748b;">
                        <span>Overall progress across all orgs</span>
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

            <!-- Empty state -->
            <div v-if="orgs.length === 0"
                class="rounded-xl p-16 text-center text-sm"
                style="background-color: #151821; border: 1px solid #1e2433; color: #64748b;">
                No organizations registered yet.
            </div>

            <!-- Org grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="org in orgs"
                    :key="org.slug"
                    :href="`/board/${org.slug}`"
                    class="group block rounded-xl p-5 space-y-4 transition-all"
                    style="background-color: #151821; border: 1px solid #1e2433; text-decoration: none;"
                    onmouseover="this.style.borderColor='rgba(56,189,248,0.35)'; this.style.backgroundColor='#1a1f2e'"
                    onmouseout="this.style.borderColor='#1e2433'; this.style.backgroundColor='#151821'">

                    <!-- Org name + slug -->
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold truncate transition-colors"
                                style="color: #f1f5f9;"
                                onmouseover="this.style.color='#38bdf8'"
                                onmouseout="this.style.color='#f1f5f9'">
                                {{ org.name }}
                            </h2>
                            <svg class="w-4 h-4 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity"
                                style="color: #38bdf8;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                        <p class="text-xs mt-0.5" style="color: #475569; font-family: monospace;">{{ org.slug }}</p>
                    </div>

                    <!-- Stats row -->
                    <div class="flex items-center gap-3 flex-wrap text-xs">
                        <span style="color: #64748b;">
                            <span class="font-semibold" style="color: #94a3b8;">{{ org.projects }}</span>
                            {{ org.projects === 1 ? 'project' : 'projects' }}
                        </span>
                        <span style="color: #334155;">·</span>
                        <span style="color: #64748b;">
                            <span class="font-semibold" style="color: #f59e0b;">{{ org.task_stats.open }}</span> open
                        </span>
                        <span style="color: #334155;">·</span>
                        <span style="color: #64748b;">
                            <span class="font-semibold" style="color: #22c55e;">{{ org.task_stats.done }}</span> done
                        </span>
                        <span v-if="org.task_stats.blocked > 0">
                            <span style="color: #334155;">·</span>
                            <span class="font-semibold ml-1" style="color: #ef4444;">{{ org.task_stats.blocked }}</span>
                            <span style="color: #64748b;"> blocked</span>
                        </span>
                    </div>

                    <!-- Progress bar -->
                    <div v-if="org.task_stats.total > 0">
                        <div class="flex justify-between text-xs mb-1" style="color: #475569;">
                            <span>Progress</span>
                            <span style="color: #22c55e;">{{ donePercent(org) }}%</span>
                        </div>
                        <div class="h-1.5 rounded-full overflow-hidden" style="background-color: #1e2433;">
                            <div class="h-full rounded-full transition-all duration-500"
                                style="background: linear-gradient(90deg, #22c55e, #38bdf8);"
                                :style="{ width: donePercent(org) + '%' }">
                            </div>
                        </div>
                    </div>
                    <div v-else class="h-1.5 rounded-full" style="background-color: #1e2433;"></div>

                    <!-- Agents badge -->
                    <div class="flex items-center gap-1.5 text-xs" style="color: #475569;">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V9l-6-6z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v6h6" />
                        </svg>
                        {{ org.agents }} {{ org.agents === 1 ? 'agent' : 'agents' }} registered
                    </div>
                </Link>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-4 pb-8 border-t text-xs"
                style="border-color: #1e2433; color: #334155;">
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
