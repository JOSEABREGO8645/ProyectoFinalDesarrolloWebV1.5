import { reactive, watch } from 'vue'
import { session } from './session'

const borradores = new Map()
watch(() => session.usuario?._id, () => borradores.clear(), { flush: 'sync' })
export function borradorCaja() {
  const clave = session.usuario._id + ':' + session.usuario.sucursal_id
  if (!borradores.has(clave)) borradores.set(clave, reactive({
    items: [], cliente: null, motivo: '', operacion_id: crypto.randomUUID(),
    pendiente: null, incierto: false, guardando: false, ultima: null
  }))
  return borradores.get(clave)
}
