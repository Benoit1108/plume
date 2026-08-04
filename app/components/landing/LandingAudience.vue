<script setup lang="ts">
const { t } = useI18n()
const audience = ['editorial', 'audiovisual', 'technical'] as const
</script>

<template>
  <section class="bg-muted/40 border-y border-default">
    <div class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20">
      <div class="reveal max-w-xl mx-auto text-center">
        <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.audience.eyebrow') }}</p>
        <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.audience.title') }}</h2>
      </div>
      <div class="mt-12 grid gap-4 sm:grid-cols-3">
        <div v-for="(a, i) in audience" :key="a" class="reveal rounded-xl border border-default bg-default p-5" :style="{ transitionDelay: `${i * 0.08}s` }">
          <UIcon :name="t(`landing.audience.${a}.icon`)" class="size-6 text-primary" aria-hidden="true" />
          <h3 class="mt-3 font-serif text-lg font-semibold">{{ t(`landing.audience.${a}.title`) }}</h3>
          <p class="mt-1 text-sm text-muted text-pretty">{{ t(`landing.audience.${a}.text`) }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
