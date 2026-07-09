<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiBadge from '@/Components/atoms/UiBadge.vue'
import UiInput from '@/Components/atoms/UiInput.vue'
import UiRule from '@/Components/atoms/UiRule.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({ brands: Array, workspaces: Array })

const creating   = ref(false)
const slug       = ref('')
const name       = ref('')
const parentSlug = ref('')
const busy       = ref(false)

const byId = computed(() => {
  const m = {}; for (const b of props.brands ?? []) m[b.id] = b; return m
})
const parentOf = (b) => (b.parent_id ? byId.value[b.parent_id]?.slug : null)

function create() {
  if (!slug.value.trim() || busy.value) return
  busy.value = true
  router.post('/brands', {
    slug: slug.value.trim(), name: name.value.trim() || undefined,
    parent_slug: parentSlug.value || undefined,
  }, { onFinish: () => { busy.value = false } })
}
</script>

<template>
  <AppLayout>
    <div class="px-6 py-6 max-w-6xl mx-auto w-full">
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <UiHeading>Brands</UiHeading>
          <UiLabel>Identidad visual resolvible — tokens, reglas y assets por marca</UiLabel>
        </div>
        <UiButton @click="creating = !creating">{{ creating ? 'Cancelar' : 'Nueva marca' }}</UiButton>
      </div>

      <UiRule class="my-5" />

      <UiCard v-if="creating" class="mb-6">
        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
          <div>
            <UiLabel>Slug</UiLabel>
            <UiInput v-model="slug" placeholder="alti" class="mt-1" />
          </div>
          <div>
            <UiLabel>Nombre</UiLabel>
            <UiInput v-model="name" placeholder="ALTI" class="mt-1" />
          </div>
          <div>
            <UiLabel>Hereda de (opcional)</UiLabel>
            <select v-model="parentSlug" class="mt-1 w-full text-sm px-3 py-2"
              style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary);">
              <option value="">— ninguna —</option>
              <option v-for="b in brands" :key="b.id" :value="b.slug">{{ b.slug }}</option>
            </select>
          </div>
        </div>
        <div class="mt-4">
          <UiButton :disabled="!slug.trim() || busy" @click="create">{{ busy ? 'Creando…' : 'Crear marca' }}</UiButton>
        </div>
      </UiCard>

      <div v-if="brands?.length" class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
        <Link v-for="b in brands" :key="b.id" :href="`/brands/${b.slug}`" class="block">
          <UiCard class="h-full transition-colors hover:opacity-90">
            <div class="flex items-center gap-3">
              <span class="shrink-0" :style="{ width: '28px', height: '28px', backgroundColor: b.accent || 'var(--color-surface-border)' }" />
              <div class="min-w-0">
                <p class="text-sm font-medium truncate" style="color: var(--color-text-primary);">{{ b.name || b.slug }}</p>
                <p class="text-xs truncate" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ b.slug }}</p>
              </div>
            </div>
            <div class="mt-3 flex items-center gap-2 flex-wrap">
              <UiBadge v-if="b.is_default">default</UiBadge>
              <UiBadge v-if="parentOf(b)">hereda de {{ parentOf(b) }}</UiBadge>
              <span class="text-xs" style="color: var(--color-text-muted);">{{ b.assets_count }} assets</span>
            </div>
          </UiCard>
        </Link>
      </div>

      <UiCard v-else class="text-center py-12">
        <p class="text-sm" style="color: var(--color-text-muted);">
          Aún no hay marcas. Crea una y enlaza assets de la biblioteca para ver su board.
        </p>
      </UiCard>
    </div>
  </AppLayout>
</template>
