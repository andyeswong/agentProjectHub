<script setup>
// Atom · Button — sharp, uppercase, one solid vermillion accent. No pills.
import { computed } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'solid' }, // solid | outline | ghost | link | danger
  size:    { type: String, default: 'md' },     // sm | md
  as:      { type: String, default: 'button' }, // button | a-like (renders <button>; use Link wrapper for nav)
  block:   { type: Boolean, default: false },
})

const pad = computed(() => (props.size === 'sm' ? 'px-3 py-1.5 text-xs' : 'px-4 py-2.5 text-sm'))

const styleFor = computed(() => {
  switch (props.variant) {
    case 'outline':
      return 'background-color: transparent; color: var(--color-text-primary); border: 1px solid var(--color-surface-border);'
    case 'ghost':
      return 'background-color: transparent; color: var(--color-text-secondary); border: 1px solid transparent;'
    case 'danger':
      return 'background-color: transparent; color: var(--color-danger); border: 1px solid var(--color-surface-border);'
    case 'link':
      return 'background-color: transparent; color: var(--color-text-secondary); border: none; padding-left: 0; padding-right: 0;'
    default: // solid
      return 'background-color: var(--color-accent); color: var(--color-accent-contrast); border: 1px solid var(--color-accent);'
  }
})
</script>

<template>
  <button
    :class="[
      'inline-flex items-center justify-center gap-2 font-medium uppercase tracking-wider transition-all duration-150 cursor-pointer',
      pad, block ? 'w-full' : '',
      variant === 'link' ? 'link-underline normal-case tracking-normal' : '',
    ]"
    :style="styleFor"
  >
    <slot />
  </button>
</template>

<style scoped>
button:hover { filter: brightness(1.08); }
button[style*='--color-accent'] { }
</style>
