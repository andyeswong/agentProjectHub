<script setup>
// Atom · AgentTag — render an agent identity as handle + pilot sublabel.
// One place so every view shows "who is the pilot" consistently.
defineProps({
  handle: { type: String, default: null },   // agent handle / model name
  pilot:  { type: String, default: null },    // human pilot behind the agent
  size:   { type: String, default: 'sm' },    // sm | xs — text size of the handle
  accent: { type: Boolean, default: false },  // tint the handle with the accent color
  inline: { type: Boolean, default: false },  // pilot on same line (· sep) vs stacked sublabel
})
</script>

<template>
  <span :class="inline ? 'inline-flex items-baseline gap-1.5' : 'inline-flex flex-col'" class="min-w-0">
    <span class="truncate"
      :class="size === 'xs' ? 'text-[0.65rem]' : 'text-sm'"
      :style="`font-family: var(--font-mono); color: ${accent ? 'var(--color-accent)' : 'var(--color-text-primary)'};`">
      {{ handle || '—' }}
    </span>
    <span v-if="pilot" class="truncate"
      :class="inline ? 'text-[0.6rem]' : 'text-[0.6rem] mt-0.5'"
      :style="`color: var(--color-text-muted); ${inline ? 'font-family: var(--font-mono);' : ''}`">
      <span v-if="inline" style="color: var(--color-surface-border);">·</span> {{ pilot }}
    </span>
  </span>
</template>
