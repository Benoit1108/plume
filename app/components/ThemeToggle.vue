<script setup lang="ts">
const colorMode = useColorMode()

const isDark = computed(() => colorMode.value === 'dark')

function toggle(): void {
  colorMode.preference = isDark.value ? 'light' : 'dark'
}
</script>

<template>
  <UButton
    :icon="isDark ? 'i-lucide-moon' : 'i-lucide-sun'"
    color="neutral"
    variant="ghost"
    size="sm"
    :aria-label="isDark ? 'Passer au thème clair' : 'Passer au thème sombre'"
    @click="toggle"
  />
</template>
