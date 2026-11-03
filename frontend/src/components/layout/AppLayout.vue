<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'

const route = useRoute()

const pageTitle = computed(() => {
  return (route.meta.title as string) || 'Dashboard'
})

const pageDescription = computed(() => {
  return (route.meta.description as string) || ''
})
</script>

<template>
  <div class="flex min-h-screen bg-gray-50 dark:bg-gray-950">
    <AppSidebar />

    <div class="flex min-w-0 flex-1 flex-col">
      <AppHeader
        :title="pageTitle"
        :description="pageDescription"
      />

      <main class="flex-1 p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
