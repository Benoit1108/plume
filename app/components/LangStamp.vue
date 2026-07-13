<script setup lang="ts">
defineProps<{ code: string }>()
</script>

<template>
  <span
    class="font-mono text-[11px] uppercase tracking-wide text-primary border border-default rounded-[3px] px-1.5 py-0.5 bg-elevated"
  >{{ code }}</span>
</template>
