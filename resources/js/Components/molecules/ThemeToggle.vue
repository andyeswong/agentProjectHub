<script setup>
// Molecule · ThemeToggle — flips data-theme on <html> and persists the choice.
import { ref, onMounted } from 'vue'
import UiIcon from '../atoms/UiIcon.vue'

const SUN  = 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'
const MOON = 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'

const theme = ref('dark')

function apply(t) {
  theme.value = t
  document.documentElement.setAttribute('data-theme', t)
  try { localStorage.setItem('ph-theme', t) } catch (e) {}
}
function toggle() { apply(theme.value === 'dark' ? 'light' : 'dark') }

onMounted(() => {
  theme.value = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark'
})
</script>

<template>
  <button
    @click="toggle"
    class="inline-flex items-center justify-center w-8 h-8 transition-colors duration-150"
    style="border: 1px solid var(--color-surface-border); color: var(--color-text-secondary);"
    :title="theme === 'dark' ? 'Switch to light' : 'Switch to dark'"
    aria-label="Toggle theme"
  >
    <UiIcon :path="theme === 'dark' ? SUN : MOON" :size="15" />
  </button>
</template>
