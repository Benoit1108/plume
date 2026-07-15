<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 24 })
</script>

<template>
  <span
    class="inline-flex items-center gap-2 font-serif font-semibold leading-none"
    :style="{ fontSize: `${size}px` }"
  >
    <svg
      viewBox="0 0 24 24"
      fill="none"
      class="text-primary"
      style="width: 0.9em; height: 0.9em"
      aria-hidden="true"
    >
      <path d="M4 20c6-1 10-5 13-13 .5-1.4 2.4-1 2 .5C17 15 11 20 5 20.7 4 20.8 3.4 20.1 4 20z" fill="currentColor" />
      <path d="M12 12c2-4 5-6 8-7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" opacity="0.55" />
    </svg>
    <span>Plume</span>
  </span>
</template>
