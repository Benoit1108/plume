<script setup lang="ts">
const { t } = useI18n()
const features = ['pipeline', 'replies', 'drafting', 'sourcing', 'dashboard'] as const
</script>

<template>
  <section id="features" class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20 scroll-mt-16">
    <div class="reveal max-w-xl mx-auto text-center">
      <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.features.eyebrow') }}</p>
      <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.features.title') }}</h2>
    </div>
    <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="(f, i) in features"
        :key="f"
        class="reveal border border-default rounded-xl p-5 bg-elevated/40 motion-safe:transition motion-safe:duration-200 hover:border-primary/40 hover:-translate-y-0.5"
        :style="{ transitionDelay: `${(i % 3) * 0.08}s` }"
      >
        <UIcon :name="t(`landing.features.${f}.icon`)" class="size-6 text-primary" aria-hidden="true" />
        <h3 class="mt-3 font-serif text-lg font-semibold">{{ t(`landing.features.${f}.title`) }}</h3>
        <p class="mt-1 text-sm text-muted text-pretty">{{ t(`landing.features.${f}.text`) }}</p>
      </div>
    </div>
  </section>
</template>
