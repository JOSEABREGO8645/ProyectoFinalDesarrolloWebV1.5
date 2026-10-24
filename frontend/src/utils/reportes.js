import { fecha } from './format'

export function dineroExacto(valor) {
  if (valor == null || valor === '') return '—'
  const partes = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(String(valor))
  if (!partes) return String(valor)
  return `Q ${partes[1]}${BigInt(partes[2]).toLocaleString('es-GT')}.${(partes[3] || '').padEnd(2, '0')}`
}
export function celdaReporte(valor, tipo) {
  if (valor == null || valor === '') return '—'
  if (tipo === 'dinero') return dineroExacto(valor)
  if (tipo === 'fecha') return fecha(valor)
  if (tipo === 'entero') return Number(valor).toLocaleString('es-GT')
  return String(valor)
}
