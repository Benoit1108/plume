<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 24 })
</script>

<template>
  <span
    class="inline-flex items-center gap-2 font-serif font-semibold leading-none"
    :style="{ fontSize: `${size}px` }"
  >
    <svg
      :width="size * 0.86"
      :height="size * 0.86"
      viewBox="0 0 24 24"
      fill="none"
      class="text-primary"
      aria-hidden="true"
    >
      <path d="M20 4C11 6 6 12 5 20l2-1c1-6 5-11 13-13Z" fill="currentColor" />
      <path d="M7 19 5 21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
    </svg>
    <span>Plume</span>
  </span>
</template>
