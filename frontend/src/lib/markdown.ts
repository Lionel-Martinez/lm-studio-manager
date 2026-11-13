import DOMPurify from 'dompurify'
import { marked } from 'marked'

// Los enlaces de las notas siempre abren en otra pestaña y sin acceso a esta ventana.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  if (node.tagName === 'A') {
    node.setAttribute('target', '_blank')
    node.setAttribute('rel', 'noopener noreferrer')
  }
})

/**
 * Convierte Markdown a HTML seguro. Todo pasa por DOMPurify, que elimina
 * scripts, eventos (onclick…) y enlaces `javascript:`, así que se puede usar con v-html.
 */
export function renderMarkdown(source: string): string {
  const html = marked.parse(source, { async: false, gfm: true, breaks: true }) as string
  return DOMPurify.sanitize(html)
}
