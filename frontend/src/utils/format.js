export const Q = n => 'Q ' + Number(n || 0).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
export const fecha = f => (f ? new Date(f).toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'short' }) : '')
export const pad = (n, l = 5) => String(n ?? '').padStart(l, '0')

// Formas orgánicas para avatares (variedad de formas = táctica de Material 3 Expressive)
const FORMAS = [
  '50%',
  '32% 68% 55% 45% / 45% 40% 60% 55%',
  '30%',
  '58% 42% 38% 62% / 42% 55% 45% 58%',
  '42% 58% 62% 38% / 58% 38% 62% 42%'
]
export const forma = i => FORMAS[Math.abs(i) % FORMAS.length]