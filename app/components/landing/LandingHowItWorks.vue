<script setup lang="ts">
const { t } = useI18n()
const steps = ['find', 'write', 'track'] as const
</script>

<template>
  <section id="how" class="bg-muted/40 border-y border-default scroll-mt-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20">
      <div class="reveal max-w-xl mx-auto text-center">
        <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.how.eyebrow') }}</p>
        <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.how.title') }}</h2>
      </div>
      <ol class="mt-12 grid gap-8 sm:grid-cols-3">
        <li v-for="(s, i) in steps" :key="s" class="reveal" :style="{ transitionDelay: `${i * 0.1}s` }">
          <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary shrink-0">
              <UIcon :name="t(`landing.how.${s}.icon`)" class="size-5" aria-hidden="true" />
            </span>
            <span class="font-mono text-sm text-primary tabular-nums">0{{ i + 1 }}</span>
          </div>
          <h3 class="mt-4 font-serif text-xl font-semibold">{{ t(`landing.how.${s}.title`) }}</h3>
          <p class="mt-2 text-sm text-muted text-pretty">{{ t(`landing.how.${s}.text`) }}</p>
        </li>
      </ol>
    </div>
  </section>
</template>
