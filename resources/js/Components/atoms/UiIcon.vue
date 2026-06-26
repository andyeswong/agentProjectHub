<script setup>
// Atom · Icon — inline stroke SVG from a path `d`. SVG only, never emoji.
defineProps({
  path:   { type: String, required: true },
  size:   { type: [Number, String], default: 16 },
  stroke: { type: [Number, String], default: 1.5 },
})
</script>

<template>
  <svg
    :width="size" :height="size" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" :stroke-width="stroke"
    stroke-linecap="round" stroke-linejoin="round" class="shrink-0"
    aria-hidden="true"
  >
    <path :d="path" />
  </svg>
</template>
