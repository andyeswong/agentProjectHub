<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiBadge from '@/Components/atoms/UiBadge.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ projects: Array, filters: Object })
const statusFilter = ref(props.filters?.status ?? '')
function applyFilter(status) {
  statusFilter.value = status
  router.get('/projects', status ? { status } : {}, { preserveState: true, replace: true })
}
const tabs = [{ label: 'All', value: '' }, { label: 'Active', value: 'active' }, { label: 'Archived', value: 'archived' }]
const pct = (c) => c.total > 0 ? Math.round((c.done / c.total) * 100) : 0
const PLUS = 'M12 4v16m8-8H4'

const adding = ref(false)
const form = ref({ name: '', description: '' })
const creating = ref(false)
function createProject() {
  if (!form.value.name.trim() || creating.value) return
  creating.value = true
  router.post('/projects', form.value, {
    preserveScroll: true,
    onSuccess: () => { form.value = { name: '', description: '' }; adding.value = false },
    onFinish: () => creating.value = false,
  })
}
</script>

<template>
  <AppLayout>
    <div class="space-y-8">

      <!-- Masthead -->
      <header class="flex items-end justify-between gap-4">
        <div>
          <UiLabel>Workspaces</UiLabel>
          <UiHeading :level="1" class="mt-1">Projects</UiHeading>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <div class="flex gap-px" style="background-color: var(--color-surface-border);">
            <button v-for="opt in tabs" :key="opt.value" @click="applyFilter(opt.value)"
              class="px-3 py-2 text-xs uppercase tracking-wider font-medium transition-colors"
              :style="statusFilter === opt.value
                ? 'background-color: var(--color-accent); color: var(--color-accent-contrast);'
                : 'background-color: var(--color-surface-elevated); color: var(--color-text-muted);'">
              {{ opt.label }}
            </button>
          </div>
          <UiButton variant="outline" size="sm" @click="adding = !adding"><UiIcon :path="PLUS" :size="14" /> New</UiButton>
        </div>
      </header>

      <!-- New-project composer -->
      <div v-if="adding" class="p-4 flex flex-col md:flex-row gap-2" style="background-color: var(--color-surface-elevated); border: 1px solid var(--color-surface-border); box-shadow: inset 2px 0 0 var(--color-accent);">
        <input v-model="form.name" placeholder="Project name…" @keydown.enter="createProject"
          class="md:w-64 px-3 py-2 text-sm outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border);"
          @focus="$event.target.style.borderColor='var(--color-accent)'" @blur="$event.target.style.borderColor='var(--color-surface-border)'" />
        <input v-model="form.description" placeholder="Description (optional)…"
          class="flex-1 px-3 py-2 text-sm outline-none" style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border);"
          @focus="$event.target.style.borderColor='var(--color-accent)'" @blur="$event.target.style.borderColor='var(--color-surface-border)'" />
        <UiButton variant="solid" size="sm" :disabled="creating || !form.name.trim()" @click="createProject">{{ creating ? 'Creating…' : 'Create' }}</UiButton>
      </div>

      <UiCard v-if="projects.length === 0" pad="p-10">
        <p class="text-center text-sm" style="color: var(--color-text-muted);">No projects found.</p>
      </UiCard>

      <div v-else class="grid grid-cols-1 gap-px md:grid-cols-2 xl:grid-cols-3" style="background-color: var(--color-surface-border);">
        <Link v-for="(project, i) in projects" :key="project.id" :href="`/projects/${project.id}`"
          class="group block p-5 transition-colors"
          style="background-color: var(--color-surface-elevated);"
          @mouseover="(e) => e.currentTarget.style.backgroundColor = 'var(--color-surface-hover)'"
          @mouseout="(e) => e.currentTarget.style.backgroundColor = 'var(--color-surface-elevated)'">
          <div class="flex items-start justify-between mb-3 gap-2">
            <div class="flex items-start gap-2 min-w-0">
              <span class="text-[0.6rem] tabular-nums mt-1.5" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ String(i + 1).padStart(2, '0') }}</span>
              <h3 class="font-display text-lg leading-tight truncate" style="color: var(--color-text-primary);">{{ project.name }}</h3>
            </div>
            <UiBadge :tone="project.status === 'active' ? 'success' : 'neutral'">{{ project.status }}</UiBadge>
          </div>

          <p v-if="project.description" class="text-xs mb-4 line-clamp-2" style="color: var(--color-text-secondary);">{{ project.description }}</p>

          <div class="flex items-center justify-between text-xs mb-1" style="font-family: var(--font-mono); color: var(--color-text-muted);">
            <span>{{ project.task_counts.done }} / {{ project.task_counts.total }} done</span>
            <span class="font-display tabular-nums" style="font-size: 1.05rem; color: var(--color-accent);">{{ pct(project.task_counts) }}%</span>
          </div>
          <div class="h-0.5 mb-3" style="background-color: var(--color-surface-border);">
            <div class="h-full" :style="`width: ${pct(project.task_counts)}%; background-color: var(--color-accent);`"></div>
          </div>

          <div class="flex items-center justify-between text-xs" style="font-family: var(--font-mono); color: var(--color-text-muted);">
            <span>{{ project.workspace?.name }}</span>
            <span>
              <span style="color: var(--color-warning);">{{ project.task_counts.open }}</span> open<template v-if="project.task_counts.blocked > 0"> · <span style="color: var(--color-danger);">{{ project.task_counts.blocked }}</span> blocked</template>
            </span>
          </div>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
