<script setup lang="ts">
export interface KpiItem {
  label: string
  value: string
  note?: string
  tone?: 'default' | 'accent' | 'danger'
}

defineProps<{
  items: KpiItem[]
}>()

const toneClasses = {
  default: '',
  accent: 'text-app-accent',
  danger: 'text-app-danger',
}
</script>

<template>
  <dl class="grid grid-cols-2 gap-px border-y border-app-border bg-app-border sm:grid-cols-3">
    <div v-for="item in items" :key="item.label" class="bg-app-bg px-5 py-6">
      <dt class="text-xs text-app-muted">{{ item.label }}</dt>

      <dd
        class="tabular mt-2 font-display text-3xl font-medium tracking-tight sm:text-4xl"
        :class="toneClasses[item.tone ?? 'default']"
      >
        {{ item.value }}
      </dd>

      <p v-if="item.note" class="mt-1 text-xs text-app-muted">{{ item.note }}</p>
    </div>
  </dl>
</template>
