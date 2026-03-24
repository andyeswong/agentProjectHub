<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const page  = usePage()
const auth  = computed(() => page.props.auth)
const flash = computed(() => page.props.flash)

const nav = [
    { label: 'Dashboard', href: '/dashboard', icon: '▦' },
    { label: 'Projects',  href: '/projects',  icon: '◈' },
    { label: 'Agents',    href: '/agents',     icon: '◎' },
]

const isActive = (href) => page.url.startsWith(href)

function logout() {
    router.post('/logout')
}
</script>

<template>
    <div class="flex h-screen overflow-hidden" style="background-color: var(--color-surface-base); color: var(--color-text-primary);">

        <!-- Sidebar -->
        <aside class="flex flex-col w-64 shrink-0 border-r" style="border-color: var(--color-surface-border); background-color: var(--color-surface-base);">

            <!-- Logo -->
            <div class="flex items-center gap-2 px-5 py-5 border-b" style="border-color: var(--color-surface-border);">
                <span class="text-lg font-bold tracking-tight" style="color: var(--color-accent);">ProjectHub</span>
                <span class="text-xs px-1.5 py-0.5 rounded" style="background-color: var(--color-surface-border); color: var(--color-text-secondary); font-family: var(--font-mono);">LLM</span>
            </div>

            <!-- Org info -->
            <div class="px-5 py-3 border-b" style="border-color: var(--color-surface-border);">
                <p class="text-xs" style="color: var(--color-text-muted);">Organization</p>
                <p class="text-sm font-medium truncate" style="color: var(--color-text-primary);">{{ auth?.org?.name ?? '—' }}</p>
                <p class="text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ auth?.org?.slug }}</p>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors"
                    :class="isActive(item.href)
                        ? 'border-l-2 font-medium'
                        : 'border-l-2 border-transparent'"
                    :style="isActive(item.href)
                        ? 'border-color: var(--color-accent); color: var(--color-accent); background-color: var(--color-surface-hover);'
                        : 'color: var(--color-text-secondary);'"
                >
                    <span>{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <!-- Agent identity -->
            <div class="px-4 py-4 border-t space-y-3" style="border-color: var(--color-surface-border);">
                <div class="rounded-md p-3" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2 h-2 rounded-full" style="background-color: var(--color-success);"></span>
                        <span class="text-xs" style="color: var(--color-text-muted);">Connected agent</span>
                    </div>
                    <p class="text-xs font-medium truncate" style="font-family: var(--font-mono); color: var(--color-accent);">{{ auth?.agent?.model ?? '—' }}</p>
                    <p class="text-xs" style="color: var(--color-text-muted);">{{ auth?.agent?.client_type }}</p>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium" style="color: var(--color-text-primary);">{{ auth?.pilot ?? 'Pilot' }}</p>
                        <p class="text-xs" style="color: var(--color-text-muted);">Human supervisor</p>
                    </div>
                    <button
                        @click="logout"
                        class="text-xs px-2 py-1 rounded transition-colors"
                        style="color: var(--color-text-muted);"
                        onmouseover="this.style.color='var(--color-danger)'"
                        onmouseout="this.style.color='var(--color-text-muted)'"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- Flash messages -->
            <div v-if="flash?.error || flash?.success" class="px-6 py-3 text-sm"
                :style="flash?.error
                    ? 'background-color: rgba(239,68,68,0.1); color: var(--color-danger); border-bottom: 1px solid rgba(239,68,68,0.2);'
                    : 'background-color: rgba(34,197,94,0.1); color: var(--color-success); border-bottom: 1px solid rgba(34,197,94,0.2);'"
            >
                {{ flash?.error || flash?.success }}
            </div>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
