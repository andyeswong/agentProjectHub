<script setup>
// Atom · Label — the uppercase mono micro-label that runs the editorial grid.
defineProps({ tone: { type: String, default: 'muted' } })
</script>

<template>
  <span
    class="text-[0.65rem] uppercase tracking-[0.15em] font-medium"
    :style="`font-family: var(--font-mono); color: ${tone === 'accent' ? 'var(--color-accent)' : 'var(--color-text-muted)'};`"
  >
    <slot />
  </span>
</template>
