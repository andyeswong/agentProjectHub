<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UiHeading from '@/Components/atoms/UiHeading.vue'
import UiLabel from '@/Components/atoms/UiLabel.vue'
import UiButton from '@/Components/atoms/UiButton.vue'
import UiCard from '@/Components/atoms/UiCard.vue'
import UiBadge from '@/Components/atoms/UiBadge.vue'
import UiIcon from '@/Components/atoms/UiIcon.vue'
import UiRule from '@/Components/atoms/UiRule.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({ brand: Object, resolved: Object, library: Array, linked: Array, proposal: Object })

const ICONS = {
  copy:  'M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3',
  link:  'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
  unlink:'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101M3 3l18 18',
  check: 'M5 13l4 4L19 7',
}
const ROLES = ['logo', 'logo-mark', 'icon', 'hero-ref', 'moodboard', 'mockup', 'palette-ref']

const tokens = computed(() => props.resolved?.tokens ?? {})
const colors = computed(() => tokens.value.colors ?? {})
const fonts  = computed(() => tokens.value.fonts ?? {})
const radii  = computed(() => tokens.value.radii ?? {})
const assets = computed(() => props.resolved?.assets ?? {})
const rules  = computed(() => props.resolved?.rules ?? [])
const layers = computed(() => props.resolved?.layers ?? [])

const fam = (v) => (typeof v === 'string' ? v : v?.family ?? '')
const logo = computed(() => assets.value.logo?.[0] ?? assets.value['logo-mark']?.[0] ?? null)
const accent = computed(() => colors.value.accent ?? 'var(--color-accent)')
const boardBg = computed(() => colors.value.bg ?? colors.value['bg-base'] ?? 'var(--color-surface-base)')
const boardFg = computed(() => colors.value.text ?? colors.value['text-primary'] ?? 'var(--color-text-primary)')

// ── Copy to clipboard ────────────────────────────────────────────────────
const copied = ref('')
async function copy(text, tag) {
  try { await navigator.clipboard.writeText(text); copied.value = tag; setTimeout(() => (copied.value = ''), 1200) } catch {}
}

// ── Export ───────────────────────────────────────────────────────────────
const exportFmt = ref('css')
const exportText = computed(() => {
  if (exportFmt.value === 'json') return JSON.stringify(tokens.value, null, 2)
  if (exportFmt.value === 'tailwind') {
    return JSON.stringify({ theme: { extend: {
      colors: colors.value,
      fontFamily: Object.fromEntries(Object.entries(fonts.value).map(([k, v]) => [k, [fam(v)]])),
      borderRadius: radii.value,
    } } }, null, 2)
  }
  const lines = [':root {']
  for (const [k, v] of Object.entries(colors.value)) lines.push(`  --color-${k}: ${v};`)
  for (const [k, v] of Object.entries(fonts.value))  lines.push(`  --font-${k}: ${fam(v)};`)
  for (const [k, v] of Object.entries(radii.value))  lines.push(`  --radius-${k}: ${v};`)
  lines.push('}')
  return lines.join('\n')
})

// ── Attach / detach assets ───────────────────────────────────────────────
const picking     = ref(false)
const pickRole    = ref('moodboard')
const isLinked    = (id) => (props.linked ?? []).includes(id)
function attach(assetId) {
  router.post(`/brands/${props.brand.slug}/assets`, { asset_id: assetId, role: pickRole.value }, { preserveScroll: true })
}
function detach(assetId) {
  router.delete(`/brands/${props.brand.slug}/assets/${assetId}`, { preserveScroll: true })
}

// ── Extract palette from an image (F3) ───────────────────────────────────
const extracting = ref(false)
const images = computed(() => (props.library ?? []).filter(a => a.is_image))

function extractFrom(assetId) {
  router.get(`/brands/${props.brand.slug}`, { palette_from: assetId }, { preserveState: false, preserveScroll: true })
}
function clearProposal() {
  router.get(`/brands/${props.brand.slug}`, {}, { preserveState: false, preserveScroll: true })
}
/** Merge the proposed roles into this brand's OWN colors and persist. */
function applyProposal() {
  const own = JSON.parse(JSON.stringify(props.brand?.tokens ?? {}))
  own.colors = { ...(own.colors ?? {}), ...(props.proposal?.proposed ?? {}) }
  router.patch(`/brands/${props.brand.slug}`, { tokens: own }, { preserveScroll: true })
}

// ── Edit tokens as JSON ──────────────────────────────────────────────────
const editing   = ref(false)
// Seed from the brand's OWN tokens, never the resolved/merged set — otherwise
// saving would copy the parent's values into this child and break inheritance.
const tokenDraft = ref(JSON.stringify(props.brand?.tokens ?? {}, null, 2))
const editError = ref('')
function saveTokens() {
  let parsed
  try { parsed = JSON.parse(tokenDraft.value) } catch (e) { editError.value = 'JSON inválido: ' + e.message; return }
  editError.value = ''
  router.patch(`/brands/${props.brand.slug}`, { tokens: parsed }, {
    preserveScroll: true, onSuccess: () => { editing.value = false },
  })
}
</script>

<template>
  <AppLayout>
    <div class="px-6 py-6 max-w-6xl mx-auto w-full">

      <!-- Breadcrumb + meta -->
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 text-xs" style="color: var(--color-text-muted);">
          <Link href="/brands" style="color: var(--color-text-muted);">Brands</Link>
          <span>/</span>
          <span style="color: var(--color-text-primary); font-family: var(--font-mono);">{{ brand.slug }}</span>
        </div>
        <div class="flex items-center gap-2">
          <UiBadge v-if="brand.is_default">default</UiBadge>
          <UiBadge v-if="resolved?.inherits">hereda: {{ layers.map(l => l.slug).join(' → ') }}</UiBadge>
        </div>
      </div>

      <!-- ── Identity header: painted with the BRAND's own tokens ── -->
      <div class="mt-4 p-8 flex items-center gap-6 flex-wrap"
        :style="{ backgroundColor: boardBg, color: boardFg, border: '1px solid var(--color-surface-border)' }">
        <img v-if="logo" :src="logo.raw_url" :alt="resolved.name" class="object-contain" style="max-height: 64px; max-width: 200px;" />
        <div v-else class="shrink-0" :style="{ width: '56px', height: '56px', backgroundColor: accent }" />
        <div class="min-w-0">
          <h1 class="text-4xl leading-none" :style="{ fontFamily: fam(fonts.display) || 'var(--font-display)' }">
            {{ resolved?.name || brand.slug }}
          </h1>
          <p class="mt-2 text-sm opacity-70" :style="{ fontFamily: fam(fonts.body) || 'inherit' }">
            {{ Object.keys(colors).length }} colores · {{ Object.keys(fonts).length }} fuentes · {{ rules.length }} reglas
          </p>
        </div>
      </div>

      <!-- ── Palette ── -->
      <section class="mt-8">
        <UiLabel>Paleta — clic para copiar</UiLabel>
        <div v-if="Object.keys(colors).length" class="mt-3 grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));">
          <button v-for="(hex, key) in colors" :key="key" type="button" @click="copy(hex, key)"
            class="text-left transition-opacity hover:opacity-80"
            style="border: 1px solid var(--color-surface-border);">
            <div :style="{ backgroundColor: hex, height: '56px' }" />
            <div class="px-2 py-1.5 overflow-hidden">
              <p class="text-xs truncate" style="color: var(--color-text-primary); font-family: var(--font-mono);">--color-{{ key }}</p>
              <p class="text-[10px] flex items-center gap-1" style="color: var(--color-text-muted); font-family: var(--font-mono); overflow-wrap: anywhere;">
                {{ hex }}
                <UiIcon v-if="copied === key" :path="ICONS.check" :size="11" />
              </p>
            </div>
          </button>
        </div>
        <p v-else class="mt-2 text-sm" style="color: var(--color-text-muted);">Sin colores definidos. Edita los tokens abajo.</p>
      </section>

      <!-- ── Typography ── -->
      <section class="mt-8">
        <UiLabel>Tipografía</UiLabel>
        <div class="mt-3 space-y-3">
          <UiCard v-for="(v, key) in fonts" :key="key">
            <div class="flex items-baseline justify-between gap-3 flex-wrap">
              <span class="text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ key }}</span>
              <span class="text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ fam(v) }}</span>
            </div>
            <p class="mt-2 truncate" :style="{ fontFamily: fam(v), fontSize: key === 'display' ? '2rem' : '1.1rem', color: 'var(--color-text-primary)' }">
              Alianza de Líderes en Tecnología
            </p>
          </UiCard>
        </div>
      </section>

      <!-- ── Components rendered with the tokens ── -->
      <section class="mt-8">
        <UiLabel>Componentes</UiLabel>
        <div class="mt-3 p-6 flex items-center gap-3 flex-wrap"
          :style="{ backgroundColor: boardBg, border: '1px solid var(--color-surface-border)' }">
          <button type="button"
            :style="{ backgroundColor: accent, color: colors['accent-ink'] || '#fff', borderRadius: radii.pill || '999px',
                      padding: '12px 24px', fontFamily: fam(fonts.body) || 'inherit', fontWeight: 600, border: 'none' }">
            Botón primario
          </button>
          <button type="button"
            :style="{ background: 'transparent', color: boardFg, border: `1px solid ${colors.border || 'rgba(255,255,255,.2)'}`,
                      borderRadius: radii.pill || '999px', padding: '12px 24px', fontFamily: fam(fonts.body) || 'inherit' }">
            Ghost
          </button>
          <span v-for="c in ['Networking', 'Tecnología']" :key="c"
            :style="{ border: `1px solid ${colors.border || 'rgba(255,255,255,.2)'}`, color: boardFg,
                      borderRadius: radii.pill || '999px', padding: '6px 14px', fontSize: '.8rem' }">{{ c }}</span>
        </div>
      </section>

      <!-- ── Assets by role ── -->
      <section class="mt-8">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <UiLabel>Assets enlazados</UiLabel>
          <UiButton variant="secondary" @click="picking = !picking">
            <UiIcon :path="ICONS.link" :size="14" /> {{ picking ? 'Cerrar' : 'Enlazar asset' }}
          </UiButton>
        </div>

        <!-- picker -->
        <UiCard v-if="picking" class="mt-3">
          <div class="flex items-center gap-2 flex-wrap">
            <UiLabel>Rol</UiLabel>
            <button v-for="r in ROLES" :key="r" type="button" @click="pickRole = r"
              class="text-xs px-2 py-1"
              :style="pickRole === r ? 'color:#fff; background-color: var(--color-accent);' : 'color: var(--color-text-muted); border: 1px solid var(--color-surface-border);'">{{ r }}</button>
          </div>
          <div class="mt-4 grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
            <button v-for="a in library" :key="a.id" type="button" @click="attach(a.id)"
              class="text-left transition-opacity hover:opacity-80" style="border: 1px solid var(--color-surface-border);">
              <div class="flex items-center justify-center overflow-hidden" style="aspect-ratio: 4/3; background-color: var(--color-surface-base);">
                <img v-if="a.is_image" :src="a.web_url" class="w-full h-full object-contain" :alt="a.filename" loading="lazy" />
              </div>
              <p class="px-2 py-1 text-xs truncate" style="color: var(--color-text-primary);">{{ a.filename }}</p>
              <p v-if="isLinked(a.id)" class="px-2 pb-1 text-[10px]" style="color: var(--color-accent);">ya enlazado</p>
            </button>
          </div>
          <p v-if="!library?.length" class="text-sm" style="color: var(--color-text-muted);">
            La biblioteca está vacía. Sube algo en <Link href="/assets" style="color: var(--color-accent);">Assets</Link>.
          </p>
        </UiCard>

        <div v-if="Object.keys(assets).length" class="mt-3 space-y-4">
          <div v-for="(list, role) in assets" :key="role">
            <p class="text-xs mb-2" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ role }}</p>
            <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
              <UiCard v-for="a in list" :key="a.id" class="overflow-hidden">
                <div class="flex items-center justify-center overflow-hidden" style="aspect-ratio: 16/10; background-color: var(--color-surface-base);">
                  <img :src="a.raw_url" class="w-full h-full object-contain" :alt="a.filename" loading="lazy" />
                </div>
                <div class="pt-2 flex items-center justify-between gap-2">
                  <span class="text-xs truncate" style="color: var(--color-text-primary);">{{ a.filename }}</span>
                  <button type="button" @click="detach(a.id)" title="Desenlazar (el asset se queda en la biblioteca)"
                    style="color: var(--color-text-muted);"><UiIcon :path="ICONS.unlink" :size="13" /></button>
                </div>
                <p v-if="a.from_brand !== brand.slug" class="text-[10px]" style="color: var(--color-text-muted);">heredado de {{ a.from_brand }}</p>
              </UiCard>
            </div>
          </div>
        </div>
        <p v-else class="mt-2 text-sm" style="color: var(--color-text-muted);">Sin assets enlazados todavía.</p>
      </section>

      <!-- ── Extract palette from an image (F3) ── -->
      <section class="mt-8">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <div>
            <UiLabel>Extraer paleta de una imagen</UiLabel>
            <p class="text-xs mt-1" style="color: var(--color-text-muted);">
              Se leen los píxeles, no se le pregunta a un modelo. Los hex son promedios: ajústalos a tus valores exactos.
            </p>
          </div>
          <UiButton variant="secondary" @click="extracting = !extracting">{{ extracting ? 'Cerrar' : 'Elegir imagen' }}</UiButton>
        </div>

        <UiCard v-if="extracting && !proposal" class="mt-3">
          <div v-if="images.length" class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
            <button v-for="a in images" :key="a.id" type="button" @click="extractFrom(a.id)"
              class="text-left transition-opacity hover:opacity-80" style="border: 1px solid var(--color-surface-border);">
              <div class="flex items-center justify-center overflow-hidden" style="aspect-ratio: 4/3; background-color: var(--color-surface-base);">
                <img :src="a.web_url" class="w-full h-full object-contain" :alt="a.filename" loading="lazy" />
              </div>
              <p class="px-2 py-1 text-xs truncate" style="color: var(--color-text-primary);">{{ a.filename }}</p>
            </button>
          </div>
          <p v-else class="text-sm" style="color: var(--color-text-muted);">No hay imágenes en la biblioteca.</p>
        </UiCard>

        <UiCard v-if="proposal" class="mt-3">
          <p v-if="proposal.error" class="text-sm" style="color: var(--color-danger, #dc2626);">{{ proposal.error }}</p>
          <template v-else>
            <div class="flex items-center justify-between gap-3 flex-wrap">
              <p class="text-xs" style="color: var(--color-text-muted); font-family: var(--font-mono);">
                de {{ proposal.asset?.filename }} · {{ proposal.sampled?.toLocaleString() }} píxeles muestreados
              </p>
              <div class="flex items-center gap-2">
                <UiButton variant="ghost" @click="clearProposal">Descartar</UiButton>
                <UiButton @click="applyProposal">Aplicar a los tokens</UiButton>
              </div>
            </div>

            <p class="mt-4 text-xs" style="color: var(--color-text-muted);">Roles propuestos</p>
            <div class="mt-2 grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));">
              <div v-for="(hex, role) in proposal.proposed" :key="role" style="border: 1px solid var(--color-surface-border);">
                <div :style="{ backgroundColor: hex, height: '48px' }" />
                <div class="px-2 py-1.5">
                  <p class="text-xs truncate" style="color: var(--color-text-primary); font-family: var(--font-mono);">{{ role }}</p>
                  <p class="text-[10px]" style="color: var(--color-text-muted); font-family: var(--font-mono);">{{ hex }}</p>
                </div>
              </div>
            </div>

            <p class="mt-4 text-xs" style="color: var(--color-text-muted);">Paleta completa (cobertura de la imagen)</p>
            <div class="mt-2 flex items-stretch overflow-hidden" style="height: 34px; border: 1px solid var(--color-surface-border);">
              <div v-for="p in proposal.palette" :key="p.hex" :title="`${p.hex} — ${(p.ratio * 100).toFixed(1)}%`"
                :style="{ backgroundColor: p.hex, flexGrow: Math.max(p.ratio, 0.02) }" />
            </div>
            <p class="mt-2 text-xs" style="color: var(--color-text-muted);">
              Las fuentes no se infieren: no se pueden leer de los píxeles con honestidad.
            </p>
          </template>
        </UiCard>
      </section>

      <!-- ── Rules ── -->
      <section v-if="rules.length" class="mt-8">
        <UiLabel>Reglas</UiLabel>
        <UiCard class="mt-3">
          <ul class="space-y-2">
            <li v-for="(r, i) in rules" :key="i" class="text-sm flex gap-2" style="color: var(--color-text-primary);">
              <span :style="{ color: accent }">—</span><span>{{ r }}</span>
            </li>
          </ul>
        </UiCard>
      </section>

      <!-- ── Export (for devs) ── -->
      <section class="mt-8">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <UiLabel>Export</UiLabel>
          <div class="flex items-center gap-1">
            <button v-for="f in ['css', 'json', 'tailwind']" :key="f" type="button" @click="exportFmt = f"
              class="text-xs px-2 py-1"
              :style="exportFmt === f ? 'color: var(--color-accent); border: 1px solid var(--color-accent);' : 'color: var(--color-text-muted); border: 1px solid var(--color-surface-border);'">{{ f }}</button>
            <UiButton variant="ghost" @click="copy(exportText, 'export')">
              <UiIcon :path="ICONS.copy" :size="13" /> {{ copied === 'export' ? 'Copiado' : 'Copiar' }}
            </UiButton>
          </div>
        </div>
        <pre class="mt-3 p-4 text-xs overflow-x-auto"
          style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary); font-family: var(--font-mono);">{{ exportText }}</pre>
      </section>

      <!-- ── Edit tokens ── -->
      <section class="mt-8 mb-10">
        <div class="flex items-center justify-between gap-3">
          <UiLabel>Tokens (fuente de verdad)</UiLabel>
          <UiButton variant="secondary" @click="editing = !editing">{{ editing ? 'Cancelar' : 'Editar' }}</UiButton>
        </div>
        <div v-if="editing" class="mt-3">
          <textarea v-model="tokenDraft" rows="14" spellcheck="false"
            class="w-full p-3 text-xs"
            style="background-color: var(--color-surface-base); border: 1px solid var(--color-surface-border); color: var(--color-text-primary); font-family: var(--font-mono);"></textarea>
          <p v-if="editError" class="mt-2 text-xs" style="color: var(--color-danger, #dc2626);">{{ editError }}</p>
          <div class="mt-3"><UiButton @click="saveTokens">Guardar tokens</UiButton></div>
          <p class="mt-2 text-xs" style="color: var(--color-text-muted);">
            Editas los tokens de <b>{{ brand.slug }}</b>. Lo heredado del padre no se guarda aquí — solo lo que esta marca sobrescribe.
          </p>
        </div>
      </section>

    </div>
  </AppLayout>
</template>
