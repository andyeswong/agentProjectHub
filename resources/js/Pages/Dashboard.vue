<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import InviteAgentPanel from '@/Components/InviteAgentPanel.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiRule from '@/Components/atoms/UiRule.vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import UiStatusDot from '@/Components/atoms/UiStatusDot.vue'
import StatCard from '@/Components/molecules/StatCard.vue'
import { ref, computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'

const props = defineProps({ stats: Object, recentProjects: Array, recentEvents: Array })
const page = usePage()
const auth = computed(() => page.props.auth)
const inviteOpen = ref(false)

const ICON = {
  projects: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
  tasks:    'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
  done:     'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
  blocked:  'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636',
  plus:     'M12 4v16m8-8H4',
}

const eventMeta = (type) => {
  const m = {
    'task.created':        { tone: 'success', label: 'Task created' },
    'task.status_changed': { tone: 'accent',  label: 'Status changed' },
    'task.blocked':        { tone: 'danger',  label: 'Task blocked' },
    'task.archived':       { tone: 'neutral', label: 'Task archived' },
    'task.moved':          { tone: 'accent',  label: 'Task moved' },
    'task.commented':      { tone: 'warning', label: 'Comment added' },
    'task.updated':        { tone: 'accent',  label: 'Task updated' },
    'project.created':     { tone: 'success', label: 'Project created' },
    'project.updated':     { tone: 'accent',  label: 'Project updated' },
    'agent.registered':    { tone: 'accent',  label: 'Agent registered' },
    'pilot.login':         { tone: 'neutral', label: 'Pilot login' },
  }
  return m[type] || { tone: 'neutral', label: type }
}

const formatPayload = (payload) => {
  if (!payload || !Object.keys(payload).length) return null
  return Object.entries(payload).filter(([, v]) => v !== null && v !== undefined && v !== '')
    .map(([k, v]) => `${k.replace(/_/g, ' ')}: ${v}`).join('  ·  ')
}
const shortModel = (m) => m ? m.split('-').slice(0, 3).join('-') : '—'
</script>

<template>
  <AppLayout>
    <div class="max-w-6xl space-y-10">

      <!-- ── Masthead (left-aligned, editorial) ── -->
      <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
          <UiLabel>Overview</UiLabel>
          <UiHeading :level="1" class="mt-1">Dashboard</UiHeading>
          <div class="flex items-center gap-2 mt-2">
            <UiStatusDot tone="success" />
            <span class="text-sm" style="color: var(--color-text-secondary);">{{ auth?.org?.name }}</span>
            <span style="color: var(--color-surface-border);">/</span>
            <span class="text-xs" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ auth?.agent?.model }}</span>
          </div>
        </div>
        <UiButton variant="outline" size="sm" @click="inviteOpen = !inviteOpen">
          <UiIcon :path="ICON.plus" :size="14" /> Invite Agent
        </UiButton>
      </header>

      <InviteAgentPanel v-if="inviteOpen" />

      <!-- ── Metrics ── -->
      <section class="space-y-3">
        <div class="flex items-center gap-3">
          <UiLabel tone="accent">Metrics</UiLabel><UiRule />
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-px" style="background-color: var(--color-surface-border);">
          <StatCard index="01" label="Projects"  :value="stats.projects"           note="active workspaces" :icon="ICON.projects" />
          <StatCard index="02" label="Open Tasks" :value="stats.open_tasks"  tone="warning" :note="`of ${stats.total_tasks} total`" :icon="ICON.tasks" />
          <StatCard index="03" label="Completed"  :value="`${stats.done_percent}%`" tone="success" :note="`${stats.done_tasks} done`" :icon="ICON.done" />
          <StatCard index="04" label="Blocked"    :value="stats.blocked_tasks" :tone="stats.blocked_tasks > 0 ? 'danger' : 'primary'" :note="`${stats.agents} agent${stats.agents !== 1 ? 's' : ''} active`" :icon="ICON.blocked" />
        </div>
      </section>

      <!-- ── Projects (7) + Activity (5) ── -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- Projects -->
        <section class="lg:col-span-7 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 flex-1"><UiLabel tone="accent">Recent projects</UiLabel><UiRule /></div>
            <Link href="/projects" class="text-xs uppercase tracking-wider link-underline shrink-0 ml-4" style="color: var(--color-text-muted);">View all</Link>
          </div>

          <UiCard v-if="recentProjects.length === 0" pad="p-10">
            <p class="text-center text-sm" style="color: var(--color-text-muted);">No active projects yet.</p>
          </UiCard>

          <div v-else>
            <Link v-for="(p, i) in recentProjects" :key="p.id" :href="`/projects/${p.id}`"
              class="group block px-4 py-4 transition-colors duration-150"
              :style="`text-decoration:none; background-color: var(--color-surface-elevated); border:1px solid var(--color-surface-border); ${i > 0 ? 'border-top:none;' : ''}`"
              @mouseover="(e) => e.currentTarget.style.backgroundColor = 'var(--color-surface-hover)'"
              @mouseout="(e) => e.currentTarget.style.backgroundColor = 'var(--color-surface-elevated)'">
              <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-start gap-3 min-w-0">
                  <span class="text-[0.6rem] tabular-nums mt-1" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ String(i + 1).padStart(2, '0') }}</span>
                  <div class="min-w-0">
                    <p class="font-display text-lg leading-tight truncate" style="color: var(--color-text-primary);">{{ p.name }}</p>
                    <p v-if="p.description" class="text-xs truncate mt-0.5" style="color: var(--color-text-muted);">{{ p.description }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                  <span v-if="p.blocked_tasks > 0" class="text-[0.65rem] uppercase tracking-wider px-1.5 py-0.5" style="font-family: var(--font-mono); color: var(--color-danger); border: 1px solid var(--color-surface-border);">{{ p.blocked_tasks }} blocked</span>
                  <span class="font-display text-lg tabular-nums" style="color: var(--color-accent);">{{ p.done_percent }}%</span>
                </div>
              </div>
              <div class="h-0.5 mb-2" style="background-color: var(--color-surface-border);">
                <div class="h-full transition-all duration-500" :style="`width: ${p.done_percent}%; background-color: var(--color-accent);`"></div>
              </div>
              <div class="flex items-center gap-3 text-xs" style="font-family: var(--font-mono); color: var(--color-text-muted);">
                <span><span style="color: var(--color-warning);">{{ p.open_tasks }}</span> open</span>
                <span style="color: var(--color-surface-border);">·</span>
                <span><span style="color: var(--color-success);">{{ p.done_tasks }}</span> done</span>
                <span style="color: var(--color-surface-border);">·</span>
                <span>{{ p.total_tasks }} total</span>
                <span class="ml-auto text-[0.65rem]">{{ p.updated_at }}</span>
              </div>
            </Link>
          </div>
        </section>

        <!-- Activity -->
        <section class="lg:col-span-5 space-y-3">
          <div class="flex items-center gap-3">
            <UiLabel tone="accent">Activity</UiLabel><UiRule />
            <span class="flex items-center gap-1.5 shrink-0"><UiStatusDot tone="success" :size="6" /><UiLabel>live</UiLabel></span>
          </div>

          <UiCard pad="p-0">
            <div v-if="recentEvents.length === 0" class="px-5 py-12 text-center text-sm" style="color: var(--color-text-muted);">No activity yet.</div>
            <ul v-else class="overflow-y-auto max-h-[540px]">
              <li v-for="(event, i) in recentEvents" :key="event.id"
                class="flex items-start gap-3 px-4 py-3"
                :style="i > 0 ? 'border-top: 1px solid var(--color-surface-border);' : ''">
                <UiStatusDot :tone="eventMeta(event.type).tone" :size="7" class="mt-1.5" />
                <div class="flex-1 min-w-0">
                  <div class="flex items-baseline justify-between gap-2">
                    <span class="text-xs font-medium truncate" style="color: var(--color-text-primary);">{{ eventMeta(event.type).label }}</span>
                    <span class="text-[0.65rem] shrink-0" style="color: var(--color-text-muted);">{{ event.time_ago }}</span>
                  </div>
                  <p class="text-[0.65rem] mt-0.5 truncate" style="color: var(--color-text-muted); font-family: var(--font-mono);">
                    {{ shortModel(event.actor_model) }}<span v-if="event.actor_pilot"> · {{ event.actor_pilot }}</span>
                  </p>
                  <p v-if="formatPayload(event.payload)" class="text-[0.65rem] mt-0.5 truncate" style="color: var(--color-text-muted);">{{ formatPayload(event.payload) }}</p>
                </div>
              </li>
            </ul>
          </UiCard>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
