<script setup>
// Atom · Badge — uppercase mono micro-label inside a 1px box.
import { computed } from 'vue'
const props = defineProps({
  tone: { type: String, default: 'neutral' }, // accent | neutral | success | warning | danger
})
const color = computed(() => ({
  accent:  'var(--color-accent)',
  success: 'var(--color-success)',
  warning: 'var(--color-warning)',
  danger:  'var(--color-danger)',
  neutral: 'var(--color-text-secondary)',
}[props.tone] || 'var(--color-text-secondary)'))
</script>

<template>
  <span
    class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[0.65rem] uppercase tracking-widest font-medium"
    :style="`font-family: var(--font-mono); color: ${color}; border: 1px solid var(--color-surface-border);`"
  >
    <slot />
  </span>
</template>
