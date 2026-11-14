<script setup lang="ts">
import { computed, ref } from 'vue'
import InputText from 'primevue/inputtext'

import EmptyState from '@/components/ui/EmptyState.vue'
import { helpSections } from '@/data/help'

const query = ref('')

const normalize = (text: string) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

const sections = computed(() => {
  const q = normalize(query.value.trim())
  if (!q) return helpSections
  return helpSections.filter((s) => normalize([s.title, s.summary, ...s.body].join(' ')).includes(q))
})

/** Agrupa las líneas "- " consecutivas en una sola lista. */
function blocks(body: string[]) {
  const out: { list: boolean; items: string[] }[] = []
  for (const line of body) {
    const isItem = line.startsWith('- ')
    const text = isItem ? line.slice(2) : line
    const last = out[out.length - 1]
    if (isItem && last?.list) last.items.push(text)
    else out.push({ list: isItem, items: [text] })
  }
  return out
}
</script>

<template>
  <div class="grid gap-10 lg:grid-cols-[14rem_minmax(0,1fr)]">
    <nav aria-label="Índice de la guía" class="lg:sticky lg:top-6 lg:self-start">
      <label for="help-search" class="sr-only">Buscar en la guía</label>
      <InputText id="help-search" v-model="query" type="search" placeholder="Buscar en la guía…" fluid />
      <ol class="mt-4 space-y-1.5 text-sm">
        <li v-for="s in sections" :key="s.id">
          <a :href="`#${s.id}`" class="text-app-muted underline-offset-4 hover:text-app-accent hover:underline">{{ s.title }}</a>
        </li>
      </ol>
    </nav>

    <div class="min-w-0 space-y-12">
      <EmptyState v-if="sections.length === 0" title="Sin resultados" description="Probá con otra palabra, por ejemplo «comprobante», «cuota» o «desactivar»." />

      <article v-for="s in sections" :id="s.id" :key="s.id" class="scroll-mt-6 border-t-2 border-app-rule pt-4">
        <h2 class="font-display text-2xl font-medium tracking-tight">{{ s.title }}</h2>
        <p class="mt-0.5 text-sm text-app-muted">{{ s.summary }}</p>

        <div class="mt-4 max-w-prose space-y-3 text-sm leading-relaxed">
          <template v-for="(b, i) in blocks(s.body)" :key="i">
            <ul v-if="b.list" class="list-disc space-y-1.5 pl-5">
              <li v-for="item in b.items" :key="item">{{ item }}</li>
            </ul>
            <template v-else>
              <p v-for="p in b.items" :key="p">{{ p }}</p>
            </template>
          </template>
        </div>
      </article>
    </div>
  </div>
</template>
