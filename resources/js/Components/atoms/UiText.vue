<script setup>
// Atom · Text — body copy / labels with tonal + mono control.
import { computed } from 'vue'
const props = defineProps({
  tone: { type: String, default: 'primary' }, // primary | secondary | muted | accent
  size: { type: String, default: 'sm' },        // xs | sm | base
  mono: { type: Boolean, default: false },
  as:   { type: String, default: 'p' },
})
const color = computed(() => ({
  primary: 'var(--color-text-primary)',
  secondary: 'var(--color-text-secondary)',
  muted: 'var(--color-text-muted)',
  accent: 'var(--color-accent)',
}[props.tone] || 'var(--color-text-primary)'))
const sz = computed(() => ({ xs: 'text-xs', sm: 'text-sm', base: 'text-base' }[props.size] || 'text-sm'))
</script>

<template>
  <component :is="as" :class="sz" :style="`color: ${color}; ${mono ? 'font-family: var(--font-mono);' : ''}`">
    <slot />
  </component>
</template>
