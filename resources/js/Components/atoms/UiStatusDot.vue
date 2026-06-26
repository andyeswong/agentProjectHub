<script setup>
// Atom · StatusDot — a small SQUARE marker (sharp, not a circle). Tone = state.
import { computed } from 'vue'
const props = defineProps({
  tone: { type: String, default: 'success' }, // success | warning | danger | neutral | accent
  size: { type: [Number, String], default: 7 },
})
const color = computed(() => ({
  success: 'var(--color-success)',
  warning: 'var(--color-warning)',
  danger:  'var(--color-danger)',
  neutral: 'var(--color-neutral)',
  accent:  'var(--color-accent)',
}[props.tone] || 'var(--color-success)'))
</script>

<template>
  <span class="inline-block shrink-0" :style="`width:${size}px; height:${size}px; background-color:${color};`"></span>
</template>
