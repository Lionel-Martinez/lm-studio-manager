
<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  title: string
  value: string
  description: string
  icon: Component
  accent?: 'green' | 'gold' | 'blue' | 'red'
}>()

const accentClasses = {
  green: 'bg-brand-500/10 text-brand-500',
  gold: 'bg-[#D6B56D]/10 text-[#D6B56D]',
  blue: 'bg-blue-500/10 text-blue-500',
  red: 'bg-red-500/10 text-red-500',
}
</script>

<template>
  <article class="rounded-xl border border-app-border bg-app-surface p-5 transition-colors hover:border-brand-500/30">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="text-sm font-medium text-app-muted">
          {{ title }}
        </p>

        <p class="mt-3 text-2xl font-bold tracking-tight text-app-text">
          {{ value }}
        </p>
      </div>

      <div
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
        :class="accentClasses[accent ?? 'green']"
      >
        <component :is="icon" :size="21" />
      </div>
    </div>

    <p class="mt-4 text-xs text-app-muted">
      {{ description }}
    </p>
  </article>
</template>
