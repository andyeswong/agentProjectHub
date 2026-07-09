<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiBadge from '@/Components/atoms/UiBadge.vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import UiInput from '@/Components/atoms/UiInput.vue'
import UiRule from '@/Components/atoms/UiRule.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
  assets: Array, stats: Object, filters: Object, search_mode: String,
  embed_model: String, workspaces: Array, active_workspace_id: String,
})

const ICONS = {
  upload: 'M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4',
  image:  'M4 5a2 2 0 012-2h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm0 12l4.5-4.5a1 1 0 011.4 0l3.6 3.6m0 0l2.1-2.1a1 1 0 011.4 0L20 17M14 9a1 1 0 11-2 0 1 1 0 012 0z',
  file:   'M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V18a2 2 0 01-2 2z',
  trash:  'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
  search: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
}
const KINDS = ['logo', 'logo-mark', 'icon', 'screenshot', 'reference', 'moodboard', 'mockup', 'other']

// ── Filters / search ─────────────────────────────────────────────────────
const searchQuery       = ref(props.filters?.q ?? '')
const activeKind        = ref(props.filters?.kind ?? '')
const semanticMode      = ref(props.filters?.semantic ?? false)
const activeWorkspaceId = ref(props.active_workspace_id ?? '')

const showWorkspaceBadge = computed(() => !activeWorkspaceId.value && (props.workspaces?.length ?? 0) > 1)
const workspaceMap = computed(() => {
  const m = {}; for (const w of (props.workspaces ?? [])) m[w.id] = w.name; return m
})

function runSearch() {
  router.get('/assets', {
    q: searchQuery.value || undefined,
    kind: activeKind.value || undefined,
    semantic: semanticMode.value || undefined,
    workspace_id: activeWorkspaceId.value || undefined,
  }, { preserveState: true, replace: true })
}
function setKind(k) { activeKind.value = activeKind.value === k ? '' : k; runSearch() }
function setWorkspace(id) { activeWorkspaceId.value = id; runSearch() }
function clearSearch() {
  searchQuery.value = ''; activeKind.value = ''; semanticMode.value = false; activeWorkspaceId.value = ''
  router.get('/assets', {}, { preserveState: false, replace: true })
}

// ── Upload ───────────────────────────────────────────────────────────────
const MAX_BYTES = 8 * 1024 * 1024   // matches AssetController::MAX_BYTES

const fileInput   = ref(null)
const selected    = ref(null)   // { name, mime, sizeKb, previewUrl }
const b64         = ref('')
const uploadKind  = ref('reference')
const description = ref('')
const uploading   = ref(false)
const uploadError = ref('')

function pickFile() { fileInput.value?.click() }
function onFilePicked(e) {
  const f = e.target.files?.[0]
  if (!f) return
  uploadError.value = ''

  // Reject oversized files here so the user gets a message instead of a 413.
  if (f.size > MAX_BYTES) {
    uploadError.value = `“${f.name}” pesa ${(f.size / 1048576).toFixed(1)} MB. El máximo por asset es 8 MB.`
    resetUpload()
    return
  }

  selected.value = { name: f.name, mime: f.type, sizeKb: Math.max(1, Math.round(f.size / 1024)), previewUrl: URL.createObjectURL(f) }
  const reader = new FileReader()
  reader.onload = () => { b64.value = String(reader.result).split(',')[1] ?? '' }
  reader.readAsDataURL(f)
}
function resetUpload() {
  selected.value = null; b64.value = ''; description.value = ''; uploadKind.value = 'reference'
  if (fileInput.value) fileInput.value.value = ''
}
function upload() {
  if (!b64.value || uploading.value) return
  uploading.value = true
  router.post('/assets', {
    data: b64.value,
    filename: selected.value.name,
    mime_type: selected.value.mime || undefined,
    kind: uploadKind.value,
    description: description.value || undefined,
    workspace_id: activeWorkspaceId.value || undefined,
  }, { preserveScroll: true, onSuccess: () => resetUpload(), onFinish: () => { uploading.value = false } })
}

// ── Delete ─────────────────────────────────────────────────────────────────
const deleting = ref(null)
function confirmDelete(id) { deleting.value = deleting.value === id ? null : id }
function doDelete(id) {
  router.delete(`/assets/${id}`, { preserveScroll: true, onFinish: () => { deleting.value = null } })
}

function fmtSize(bytes) {
  if (!bytes) return '—'
  return bytes < 1024 ? `${bytes} B` : bytes < 1048576 ? `${(bytes / 1024).toFixed(0)} KB` : `${(bytes / 1048576).toFixed(1)} MB`
}
</script>

<template>
  <AppLayout>
    <div class="px-6 py-6 max-w-7xl mx-auto w-full">

      <!-- Header -->
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <UiHeading>Assets</UiHeading>
          <UiLabel>Biblioteca de marca &amp; referencias — logos, screenshots, moodboards</UiLabel>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-right">
            <p class="text-2xl font-display leading-none" style="color: var(--color-text-primary);">{{ stats?.total ?? 0 }}</p>
            <UiLabel>assets</UiLabel>
          </div>
          <div class="text-right">
            <p class="text-2xl font-display leading-none" style="color: var(--color-accent);">{{ stats?.embedded ?? 0 }}</p>
            <UiLabel>embedded</UiLabel>
          </div>
        </div>
      </div>

      <UiRule class="my-5" />

      <!-- Upload panel -->
      <UiCard class="mb-6">
        <div class="flex items-start gap-4 flex-wrap">
          <input ref="fileInput" type="file" class="hidden" @change="onFilePicked" />

          <!-- Preview / drop target -->
          <button
            type="button" @click="pickFile"
            class="shrink-0 flex items-center justify-center overflow-hidden transition-colors"
            style="width: 96px; height: 96px; border: 1px dashed var(--color-surface-border); background-color: var(--color-surface-base);"
          >
            <img v-if="selected?.previewUrl && selected.mime?.startsWith('image/')" :src="selected.previewUrl" class="w-full h-full object-cover" alt="preview" />
            <UiIcon v-else :path="ICONS.upload" :size="26" style="color: var(--color-text-muted);" />
          </button>

          <!-- Fields -->
          <div class="flex-1 min-w-[240px]">
            <div class="flex items-center gap-2 flex-wrap">
              <UiButton variant="secondary" @click="pickFile">
                <UiIcon :path="ICONS.file" :size="14" /> {{ selected ? 'Cambiar archivo' : 'Elegir archivo' }}
              </UiButton>
              <span v-if="selected" class="text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">
                {{ selected.name }} · {{ selected.sizeKb }} KB
              </span>
            </div>

            <div class="mt-3 flex items-center gap-2 flex-wrap">
              <UiLabel>Tipo</UiLabel>
              <button
                v-for="k in KINDS" :key="k" type="button" @click="uploadKind = k"
                class="text-xs px-2 py-1 transition-colors"
                :style="uploadKind === k
                  ? 'color: var(--color-accent-contrast, #fff); background-color: var(--color-accent);'
                  : 'color: var(--color-text-muted); border: 1px solid var(--color-surface-border);'"
              >{{ k }}</button>
            </div>

            <div class="mt-3">
              <UiInput v-model="description" placeholder="Descripción (se embebe para búsqueda semántica)…" />
            </div>

            <p v-if="uploadError" class="mt-3 text-xs" style="color: var(--color-danger, #dc2626);">{{ uploadError }}</p>

            <div class="mt-3 flex items-center gap-2">
              <UiButton :disabled="!b64 || uploading" @click="upload">
                <UiIcon :path="ICONS.upload" :size="14" /> {{ uploading ? 'Subiendo…' : 'Subir asset' }}
              </UiButton>
              <UiButton v-if="selected" variant="ghost" @click="resetUpload">Cancelar</UiButton>
            </div>
          </div>
        </div>
      </UiCard>

      <!-- Search + filters -->
      <div class="flex items-center gap-2 flex-wrap mb-4">
        <div class="flex-1 min-w-[220px] flex items-center gap-2">
          <UiInput v-model="searchQuery" placeholder="Buscar por nombre o descripción…" @keyup.enter="runSearch" class="flex-1" />
          <UiButton variant="secondary" @click="runSearch"><UiIcon :path="ICONS.search" :size="14" /></UiButton>
        </div>
        <label class="flex items-center gap-2 text-xs cursor-pointer" style="color: var(--color-text-muted);">
          <input type="checkbox" v-model="semanticMode" @change="runSearch" /> Semántico
        </label>
        <UiButton v-if="filters?.q || filters?.kind || filters?.semantic" variant="ghost" @click="clearSearch">Limpiar</UiButton>
      </div>

      <!-- Kind filter chips -->
      <div class="flex items-center gap-2 flex-wrap mb-2">
        <button
          v-for="k in KINDS" :key="k" type="button" @click="setKind(k)"
          class="text-xs px-2 py-1 transition-colors"
          :style="activeKind === k
            ? 'color: var(--color-accent); border: 1px solid var(--color-accent);'
            : 'color: var(--color-text-muted); border: 1px solid var(--color-surface-border);'"
        >{{ k }}<span v-if="stats?.by_kind?.[k]" class="ml-1 opacity-60">{{ stats.by_kind[k] }}</span></button>
      </div>

      <!-- Workspace tabs (multi-workspace orgs) -->
      <div v-if="(workspaces?.length ?? 0) > 1" class="flex items-center gap-2 flex-wrap mb-4">
        <button type="button" @click="setWorkspace('')" class="text-xs px-2 py-1"
          :style="!activeWorkspaceId ? 'color: var(--color-accent);' : 'color: var(--color-text-muted);'">Todos</button>
        <button v-for="w in workspaces" :key="w.id" type="button" @click="setWorkspace(w.id)" class="text-xs px-2 py-1"
          :style="activeWorkspaceId === w.id ? 'color: var(--color-accent);' : 'color: var(--color-text-muted);'">
          {{ w.name }} <span class="opacity-60">{{ w.asset_count }}</span>
        </button>
      </div>

      <p v-if="search_mode !== 'list'" class="text-xs mb-3" style="color: var(--color-text-muted); font-family: var(--font-mono);">
        modo: {{ search_mode }}<span v-if="search_mode === 'semantic'"> · {{ embed_model }}</span>
      </p>

      <!-- Grid -->
      <div v-if="assets?.length" class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
        <UiCard v-for="a in assets" :key="a.id" class="flex flex-col overflow-hidden">
          <!-- Thumb -->
          <div class="relative flex items-center justify-center overflow-hidden" style="aspect-ratio: 4/3; background-color: var(--color-surface-base); border-bottom: 1px solid var(--color-surface-border);">
            <img v-if="a.is_image" :src="a.web_url" :alt="a.filename" class="w-full h-full object-contain" loading="lazy" />
            <UiIcon v-else :path="ICONS.file" :size="34" style="color: var(--color-text-muted);" />
            <span v-if="a._score !== undefined" class="absolute top-1 right-1 text-[10px] px-1.5 py-0.5" style="background-color: var(--color-accent); color: #fff; font-family: var(--font-mono);">{{ a._score.toFixed(2) }}</span>
          </div>
          <!-- Meta -->
          <div class="p-3 flex flex-col gap-2 flex-1">
            <div class="flex items-center justify-between gap-2">
              <UiBadge>{{ a.kind }}</UiBadge>
              <span class="text-[10px]" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ fmtSize(a.size) }}</span>
            </div>
            <p class="text-sm truncate" style="color: var(--color-text-primary);" :title="a.filename">{{ a.filename }}</p>
            <p v-if="a.description" class="text-xs line-clamp-2" style="color: var(--color-text-muted);">{{ a.description }}</p>
            <div class="flex items-center justify-between mt-auto pt-1">
              <span class="text-[10px]" style="color: var(--color-text-muted); font-family: var(--font-mono);">
                {{ a.width && a.height ? `${a.width}×${a.height}` : '' }}
                <span v-if="showWorkspaceBadge"> · {{ workspaceMap[a.workspace_id] }}</span>
              </span>
              <div class="flex items-center gap-1">
                <a :href="a.web_url" target="_blank" rel="noopener" class="text-[11px]" style="color: var(--color-text-muted);">ver</a>
                <button v-if="deleting !== a.id" type="button" @click="confirmDelete(a.id)" class="p-1" style="color: var(--color-text-muted);" title="Eliminar">
                  <UiIcon :path="ICONS.trash" :size="13" />
                </button>
                <button v-else type="button" @click="doDelete(a.id)" class="text-[11px] px-1.5 py-0.5" style="color: #fff; background-color: var(--color-danger, #dc2626);">borrar</button>
              </div>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Empty -->
      <UiCard v-else class="text-center py-12">
        <div class="flex flex-col items-center gap-3">
          <UiIcon :path="ICONS.image" :size="30" style="color: var(--color-text-muted);" />
          <p class="text-sm" style="color: var(--color-text-muted);">
            {{ filters?.q ? 'Sin resultados para tu búsqueda.' : 'Aún no hay assets. Sube un logo o una referencia para empezar.' }}
          </p>
        </div>
      </UiCard>

    </div>
  </AppLayout>
</template>
