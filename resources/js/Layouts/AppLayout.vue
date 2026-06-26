<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'

const page  = usePage()
const auth  = computed(() => page.props.auth)
const flash = computed(() => page.props.flash)

const nav = [
  { n: '01', label: 'Dashboard', href: '/dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { n: '02', label: 'Projects',  href: '/projects',  icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z' },
  { n: '03', label: 'Memory',    href: '/memory',    icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z' },
  { n: '04', label: 'Agents',    href: '/agents',    icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v10m0 0h10M9 13H5m4 0a2 2 0 010 4H5a2 2 0 010-4' },
  { n: '05', label: 'Identity',  href: '/personalities', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
]

const isActive = (href) => page.url.startsWith(href)
const logout = () => router.post('/logout')
</script>

<template>
  <div class="flex h-[100dvh] overflow-hidden" style="background-color: var(--color-surface-base); color: var(--color-text-primary);">

    <!-- ── Sidebar (md+) ── -->
    <aside class="hidden md:flex flex-col w-64 shrink-0" style="border-right: 1px solid var(--color-surface-border);">

      <!-- Wordmark -->
      <div class="px-5 py-5" style="border-bottom: 1px solid var(--color-surface-border);">
        <span class="font-display text-2xl leading-none" style="letter-spacing: -0.02em;">
          Project<span style="color: var(--color-accent);">Hub</span>
        </span>
        <div class="mt-2"><UiLabel>Agent memory &amp; coordination</UiLabel></div>
      </div>

      <!-- Org -->
      <div class="px-5 py-4" style="border-bottom: 1px solid var(--color-surface-border);">
        <UiLabel>Organization</UiLabel>
        <p class="text-sm font-medium truncate mt-1" style="color: var(--color-text-primary);">{{ auth?.org?.name ?? '—' }}</p>
        <p class="text-xs truncate" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ auth?.org?.slug }}</p>
      </div>

      <!-- Nav -->
      <nav class="flex-1 py-3">
        <Link
          v-for="item in nav" :key="item.href" :href="item.href"
          class="group flex items-center gap-3 px-5 py-3 text-sm transition-colors duration-150"
          :style="isActive(item.href)
            ? 'color: var(--color-text-primary); background-color: var(--color-surface-hover); box-shadow: inset 2px 0 0 var(--color-accent);'
            : 'color: var(--color-text-secondary);'"
        >
          <span class="text-[0.6rem] tabular-nums w-5" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ item.n }}</span>
          <UiIcon :path="item.icon" :size="16" :style="isActive(item.href) ? 'color: var(--color-accent);' : ''" />
          <span class="uppercase tracking-wider text-xs font-medium">{{ item.label }}</span>
        </Link>
      </nav>

      <!-- Agent identity -->
      <div class="px-5 py-4 space-y-3" style="border-top: 1px solid var(--color-surface-border);">
        <div class="flex items-center gap-2">
          <UiStatusDot tone="success" />
          <UiLabel>Connected agent</UiLabel>
        </div>
        <div>
          <p class="text-xs font-medium truncate" style="font-family: var(--font-mono); color: var(--color-accent);">{{ auth?.agent?.model ?? '—' }}</p>
          <p class="text-xs" style="color: var(--color-text-muted);">{{ auth?.agent?.client_type }}</p>
        </div>
        <div class="flex items-center justify-between pt-1">
          <div>
            <p class="text-xs font-medium" style="color: var(--color-text-primary);">{{ auth?.pilot ?? 'Pilot' }}</p>
            <UiLabel>Human supervisor</UiLabel>
          </div>
          <button @click="logout" class="text-xs uppercase tracking-wider link-underline" style="color: var(--color-text-muted);">Logout</button>
        </div>
      </div>
    </aside>

    <!-- ── Main ── -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

      <!-- Mobile top bar -->
      <header class="md:hidden flex items-center justify-between px-4 h-14 shrink-0" style="border-bottom: 1px solid var(--color-surface-border);">
        <span class="font-display text-xl">Project<span style="color: var(--color-accent);">Hub</span></span>
        <UiStatusDot tone="success" />
      </header>

      <!-- Flash -->
      <div v-if="flash?.error || flash?.success" class="px-4 md:px-8 py-3 text-sm shrink-0"
        :style="flash?.error
          ? 'color: var(--color-danger); border-bottom: 1px solid var(--color-surface-border); box-shadow: inset 2px 0 0 var(--color-danger);'
          : 'color: var(--color-success); border-bottom: 1px solid var(--color-surface-border); box-shadow: inset 2px 0 0 var(--color-success);'">
        {{ flash?.error || flash?.success }}
      </div>

      <main class="flex-1 overflow-y-auto p-4 md:p-8 pb-24 md:pb-8">
        <slot />
      </main>
    </div>

    <!-- ── Mobile bottom nav ── -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-50 flex items-stretch"
      style="background-color: var(--color-surface-base); border-top: 1px solid var(--color-surface-border); padding-bottom: env(safe-area-inset-bottom);">
      <Link v-for="item in nav" :key="item.href" :href="item.href"
        class="flex-1 flex flex-col items-center justify-center gap-1 py-3"
        :style="isActive(item.href) ? 'color: var(--color-accent);' : 'color: var(--color-text-muted);'">
        <UiIcon :path="item.icon" :size="18" />
        <span class="text-[0.6rem] uppercase tracking-wider">{{ item.label }}</span>
      </Link>
      <button @click="logout" class="flex-1 flex flex-col items-center justify-center gap-1 py-3" style="color: var(--color-text-muted);">
        <UiIcon path="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" :size="18" />
        <span class="text-[0.6rem] uppercase tracking-wider">Logout</span>
      </button>
    </nav>
  </div>
</template>
