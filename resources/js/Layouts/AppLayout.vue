<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const page  = usePage()
const auth  = computed(() => page.props.auth)
const flash = computed(() => page.props.flash)

const nav = [
    { label: 'Dashboard', href: '/dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { label: 'Projects',  href: '/projects',  icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z' },
    { label: 'Memory',    href: '/memory',    icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
    { label: 'Agents',    href: '/agents',    icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v10m0 0h10M9 13H5m4 0a2 2 0 010 4H5a2 2 0 010-4' },
]

const isActive = (href) => page.url.startsWith(href)

const sidebarOpen = ref(false)

function logout() {
    router.post('/logout')
}
</script>

<template>
    <div class="flex h-[100dvh] overflow-hidden" style="background-color: var(--color-surface-base); color: var(--color-text-primary);">

        <!-- ── Desktop sidebar (md+) ── -->
        <aside class="hidden md:flex flex-col w-60 shrink-0 border-r" style="border-color: var(--color-surface-border); background-color: var(--color-surface-base);">

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
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors border-l-2"
                    :style="isActive(item.href)
                        ? 'border-color: var(--color-accent); color: var(--color-accent); background-color: var(--color-surface-hover);'
                        : 'border-color: transparent; color: var(--color-text-secondary);'"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <!-- Agent identity -->
            <div class="px-4 py-4 border-t space-y-3" style="border-color: var(--color-surface-border);">
                <div class="rounded-md p-3" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border);">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: var(--color-success);"></span>
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
                    <button @click="logout" class="text-xs px-2 py-1 rounded transition-colors"
                        style="color: var(--color-text-muted);"
                        onmouseover="this.style.color='var(--color-danger)'"
                        onmouseout="this.style.color='var(--color-text-muted)'">
                        Logout
                    </button>
                </div>
            </div>
        </aside>

        <!-- ── Main area ── -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            <!-- Mobile top bar -->
            <header class="md:hidden flex items-center justify-between px-4 h-14 shrink-0 border-b" style="border-color: var(--color-surface-border); background-color: var(--color-surface-base);">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold" style="color: var(--color-accent);">ProjectHub</span>
                    <span class="text-xs px-1.5 py-0.5 rounded" style="background-color: var(--color-surface-border); color: var(--color-text-secondary); font-family: var(--font-mono);">LLM</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-xs font-medium leading-none" style="color: var(--color-text-primary);">{{ auth?.pilot ?? 'Pilot' }}</p>
                        <p class="text-xs leading-none mt-0.5 truncate max-w-[100px]" style="color: var(--color-accent); font-family: var(--font-mono);">{{ auth?.agent?.model }}</p>
                    </div>
                    <span class="w-2 h-2 rounded-full" style="background-color: var(--color-success);"></span>
                </div>
            </header>

            <!-- Flash messages -->
            <div v-if="flash?.error || flash?.success" class="px-4 md:px-6 py-3 text-sm shrink-0"
                :style="flash?.error
                    ? 'background-color: rgba(239,68,68,0.1); color: var(--color-danger); border-bottom: 1px solid rgba(239,68,68,0.2);'
                    : 'background-color: rgba(34,197,94,0.1); color: var(--color-success); border-bottom: 1px solid rgba(34,197,94,0.2);'">
                {{ flash?.error || flash?.success }}
            </div>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-4 md:p-6 pb-24 md:pb-6">
                <slot />
            </main>
        </div>

        <!-- ── Mobile bottom nav ── -->
        <nav class="md:hidden fixed bottom-0 inset-x-0 z-50 flex items-stretch border-t"
            style="background-color: var(--color-surface-base); border-color: var(--color-surface-border); padding-bottom: env(safe-area-inset-bottom);">

            <Link v-for="item in nav" :key="item.href" :href="item.href"
                class="flex-1 flex flex-col items-center justify-center gap-1 py-3 text-xs transition-colors"
                :style="isActive(item.href)
                    ? 'color: var(--color-accent);'
                    : 'color: var(--color-text-muted);'">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                </svg>
                <span :class="isActive(item.href) ? 'font-medium' : ''">{{ item.label }}</span>
            </Link>

            <button @click="logout"
                class="flex-1 flex flex-col items-center justify-center gap-1 py-3 text-xs transition-colors"
                style="color: var(--color-text-muted);">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Logout</span>
            </button>
        </nav>

    </div>
</template>
