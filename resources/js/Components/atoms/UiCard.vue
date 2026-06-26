<script setup>
// Atom · Card — flat, 1px border, no shadow. Theme-aware via tokens.
const props = defineProps({
  variant: { type: String, default: 'elevated' }, // base | elevated | sunken
  pad:     { type: String, default: 'p-5' },
  hover:   { type: Boolean, default: false },
})
const bg = {
  base: 'var(--color-surface-base)',
  elevated: 'var(--color-surface-elevated)',
  sunken: 'var(--color-surface-sunken)',
}[props.variant] || 'var(--color-surface-elevated)'
</script>

<template>
  <div
    :class="[pad, hover ? 'transition-colors duration-150' : '']"
    :style="`background-color: ${bg}; border: 1px solid var(--color-surface-border);`"
  >
    <slot />
  </div>
</template>
