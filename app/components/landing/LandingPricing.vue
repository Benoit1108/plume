<script setup lang="ts">
const { t } = useI18n()
const included = ['pipeline', 'drafting', 'mailbox', 'sourcing', 'dashboard', 'support'] as const
</script>

<template>
  <section id="pricing" class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20 scroll-mt-16">
    <div class="reveal max-w-xl mx-auto text-center">
      <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.pricing.eyebrow') }}</p>
      <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.pricing.title') }}</h2>
    </div>
    <div class="reveal mt-10 max-w-md mx-auto">
      <div class="border border-primary/40 rounded-2xl p-6 bg-elevated/40">
        <div class="flex items-baseline justify-between">
          <h3 class="font-serif text-xl font-semibold">{{ t('landing.pricing.planName') }}</h3>
          <UBadge color="primary" variant="soft">{{ t('landing.pricing.badge') }}</UBadge>
        </div>
        <p class="mt-4">
          <span class="font-serif text-4xl font-semibold tabular-nums">{{ t('landing.pricing.monthly') }}</span>
          <span class="text-muted text-sm"> {{ t('landing.pricing.perMonth') }}</span>
        </p>
        <p class="text-xs text-muted mt-1">{{ t('landing.pricing.annual') }}</p>
        <ul class="mt-5 flex flex-col gap-2 text-sm">
          <li v-for="item in included" :key="item" class="flex items-start gap-2">
            <UIcon name="i-lucide-check" class="size-4 text-primary mt-0.5 shrink-0" aria-hidden="true" />
            <span>{{ t(`landing.pricing.included.${item}`) }}</span>
          </li>
        </ul>
        <UButton to="/register" block size="lg" class="mt-6">{{ t('landing.hero.cta') }}</UButton>
        <p class="text-center text-xs text-dimmed mt-2">{{ t('landing.pricing.trust') }}</p>
      </div>
    </div>
  </section>
</template>
