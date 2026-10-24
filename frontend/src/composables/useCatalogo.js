import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { mensajeError } from '../services/api'
import { confirmar, notificar } from '../stores/ui'

export function useCatalogo(servicio, singular, filtrosExtra = {}) {
  const registros = ref([])
  const cargando = ref(true)
  const error = ref('')
  const mostrarFormulario = ref(false)
  const seleccionado = ref(null)
  const guardando = ref(false)
  const errorForm = ref('')
  const filtros = reactive({ q: '', activo: '', orden: 'nombre:asc', ...filtrosExtra })
  const hayFiltros = computed(() => Object.entries(filtros).some(([campo, valor]) => campo !== 'orden' && String(valor).trim() !== ''))
  let temporizador
  let peticion = 0

  async function cargar() {
    const numero = ++peticion
    cargando.value = true
    error.value = ''
    try {
      const [orden, dir] = filtros.orden.split(':')
      const datos = await servicio.obtener({
        ...Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '')),
        q: filtros.q.trim() || undefined,
        activo: filtros.activo === '' ? undefined : filtros.activo,
        orden, dir
      })
      if (numero === peticion) registros.value = datos
    } catch (e) {
      if (numero === peticion) error.value = mensajeError(e)
    } finally {
      if (numero === peticion) cargando.value = false
    }
  }

  function abrir(registro = null) {
    seleccionado.value = registro
    errorForm.value = ''
    mostrarFormulario.value = true
  }

  function cerrar() {
    if (guardando.value) return
    mostrarFormulario.value = false
    seleccionado.value = null
  }

  async function guardar(datos) {
    if (guardando.value) return
    guardando.value = true
    errorForm.value = ''
    try {
      if (seleccionado.value) await servicio.editar(seleccionado.value._id, datos)
      else await servicio.agregar(datos)
      mostrarFormulario.value = false
      seleccionado.value = null
      notificar('Datos guardados correctamente', 'ok')
      await cargar()
    } catch (e) {
      errorForm.value = mensajeError(e)
    } finally {
      guardando.value = false
    }
  }

  async function eliminar(registro) {
    const acepto = await confirmar({
      titulo: `Eliminar ${singular}`,
      mensaje: `Se eliminará «${registro.nombre}». Esta acción no se puede deshacer.`,
      aceptar: 'Eliminar', peligro: true
    })
    if (!acepto) return
    try {
      await servicio.eliminar(registro._id)
      notificar('Registro eliminado', 'ok')
      await cargar()
    } catch (e) {
      notificar(mensajeError(e), 'error')
    }
  }

  watch(() => filtros.q, () => {
    clearTimeout(temporizador)
    temporizador = setTimeout(cargar, 250)
  })
  watch(() => Object.entries(filtros).filter(([campo]) => campo !== 'q').map(([, valor]) => valor), () => {
    clearTimeout(temporizador)
    cargar()
  })
  onMounted(cargar)
  onUnmounted(() => { clearTimeout(temporizador); peticion++ })

  return { registros, cargando, error, filtros, hayFiltros, mostrarFormulario,
    seleccionado, guardando, errorForm, cargar, abrir, cerrar, guardar, eliminar }
}
