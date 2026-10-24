import { reactive, watchEffect } from 'vue'

export const ui = reactive({
  toast: null,
  confirm: null,
  tema: localStorage.getItem('tema') || 'auto'
})

let temporizador
export function notificar(msg, tipo = 'info') {
  ui.toast = { msg, tipo, id: Date.now() }
  clearTimeout(temporizador)
  temporizador = setTimeout(() => (ui.toast = null), 3800)
}

/** Diálogo de confirmación. Con pedirTexto devuelve el texto escrito; si se cancela devuelve false. */
export function confirmar({ titulo, mensaje = '', aceptar = 'Aceptar', peligro = false, pedirTexto = null }) {
  return new Promise(resolver => {
    ui.confirm = { titulo, mensaje, aceptar, peligro, pedirTexto, texto: '', resolver }
  })
}

const oscuro = window.matchMedia('(prefers-color-scheme: dark)')
watchEffect(() => {
  const dark = ui.tema === 'dark' || (ui.tema === 'auto' && oscuro.matches)
  document.documentElement.dataset.tema = dark ? 'dark' : 'light'
})
export function alternarTema() {
  const actualmenteOscuro = document.documentElement.dataset.tema === 'dark'
  ui.tema = actualmenteOscuro ? 'light' : 'dark'
  localStorage.setItem('tema', ui.tema)
}