<script setup>
// Molecule · StatCard — editorial stat: mono label up top, big display number,
// a thin accent baseline. No tinted icon-chip; the number is the hero.
import { computed } from 'vue'
import UiCard from '../atoms/UiCard.vue'
import UiLabel from '../atoms/UiLabel.vue'
import UiIcon from '../atoms/UiIcon.vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  note:  { type: String, default: '' },
  icon:  { type: String, default: '' },
  index: { type: String, default: '' }, // e.g. "01"
  tone:  { type: String, default: 'primary' }, // primary | accent | danger | success | warning
})

const valueColor = computed(() => ({
  accent: 'var(--color-accent)', danger: 'var(--color-danger)',
  success: 'var(--color-success)', warning: 'var(--color-warning)',
  primary: 'var(--color-text-primary)',
}[props.tone] || 'var(--color-text-primary)'))
</script>

<template>
  <UiCard pad="p-0" variant="elevated">
    <div class="flex items-start justify-between px-4 pt-3">
      <UiLabel>{{ label }}</UiLabel>
      <span class="text-[0.6rem] tabular-nums" style="font-family: var(--font-mono); color: var(--color-text-muted);">{{ index }}</span>
    </div>
    <div class="flex items-end justify-between px-4 pt-1">
      <span class="font-display leading-none tabular-nums" :style="`font-size: 2.75rem; color: ${valueColor}; letter-spacing: -0.03em;`">{{ value }}</span>
      <UiIcon v-if="icon" :path="icon" :size="18" :stroke="1.25" style="color: var(--color-text-muted); margin-bottom: 0.4rem;" />
    </div>
    <p class="px-4 pb-3 pt-1 text-xs truncate" style="color: var(--color-text-muted);">{{ note }}</p>
    <div style="height: 2px; background-color: var(--color-accent);"></div>
  </UiCard>
</template>
