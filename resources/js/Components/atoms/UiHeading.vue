<script setup>
// Atom · Heading — display serif, tight. The voice of the page.
import { computed } from 'vue'
const props = defineProps({
  level: { type: [Number, String], default: 2 }, // 1..4 -> tag + scale
  accent: { type: Boolean, default: false },
})
const tag = computed(() => 'h' + Math.min(Math.max(Number(props.level), 1), 6))
const size = computed(() => ({
  1: 'text-4xl md:text-5xl',
  2: 'text-2xl md:text-3xl',
  3: 'text-xl',
  4: 'text-lg',
}[Number(props.level)] || 'text-2xl'))
</script>

<template>
  <component
    :is="tag"
    :class="['font-display leading-tight', size]"
    :style="`letter-spacing: -0.015em; color: ${accent ? 'var(--color-accent)' : 'var(--color-text-primary)'};`"
  >
    <slot />
  </component>
</template>
