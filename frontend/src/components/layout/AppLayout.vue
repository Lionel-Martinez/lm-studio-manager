<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Button from 'primevue/button'

import ClientFormDialog from '@/components/clients/ClientFormDialog.vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import { useClientsStore } from '@/stores/clients'

const route = useRoute()
const clients = useClientsStore()

const pageTitle = computed(() => (route.meta.title as string) || 'Resumen')
const pageDescription = computed(() => (route.meta.description as string) || '')
</script>

<template>
  <div class="min-h-screen bg-app-bg text-app-text lg:flex">
    <AppSidebar />

    <div class="min-w-0 flex-1">
      <AppHeader :title="pageTitle" :description="pageDescription">
        <template #actions>
          <Button label="Nuevo cliente" @click="clients.openDialog()" />
        </template>
      </AppHeader>

      <main class="px-5 py-8 sm:px-8 lg:px-12 lg:pb-14">
        <RouterView />
      </main>
    </div>

    <ClientFormDialog />
  </div>
</template>
