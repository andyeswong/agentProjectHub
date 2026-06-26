<script setup>
// Atom · Rule — an editorial hairline. Optional accent tick at the start.
defineProps({ tick: { type: Boolean, default: false } })
</script>

<template>
  <div class="flex items-center" aria-hidden="true">
    <span v-if="tick" class="inline-block" style="width: 16px; height: 2px; background-color: var(--color-accent);"></span>
    <span class="flex-1" style="height: 1px; background-color: var(--color-surface-border);"></span>
  </div>
</template>
