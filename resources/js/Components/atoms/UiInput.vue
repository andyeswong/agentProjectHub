<script setup>
// Atom · Input — sharp field, 1px border, accent focus. v-model.
defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
})
defineEmits(['update:modelValue'])
</script>

<template>
  <input
    :type="type"
    :value="modelValue"
    :placeholder="placeholder"
    class="w-full px-3.5 py-2.5 text-sm outline-none transition-colors duration-150"
    style="background-color: var(--color-surface-base); color: var(--color-text-primary); border: 1px solid var(--color-surface-border); font-family: var(--font-mono);"
    @input="$emit('update:modelValue', $event.target.value)"
    @focus="$event.target.style.borderColor = 'var(--color-accent)'"
    @blur="$event.target.style.borderColor = 'var(--color-surface-border)'"
  />
</template>
