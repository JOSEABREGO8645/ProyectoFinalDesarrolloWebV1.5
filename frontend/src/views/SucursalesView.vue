<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import SucursalesTable from '../components/sucursales/SucursalesTable.vue'
import SucursalForm from '../components/sucursales/SucursalForm.vue'
import Vacio from '../components/comunes/Vacio.vue'
import {
  obtenerSucursales, agregarSucursal,
  editarSucursal, eliminarSucursal
} from '../services/sucursalesService'
import { mensajeError } from '../services/api'
import { notificar, confirmar } from '../stores/ui'

const sucursales = ref([])
const cargando = ref(true)
const mostrarFormulario = ref(false)
const seleccionada = ref(null)
const guardando = ref(false)
const errorForm = ref('')

const filtros = reactive({ q: '', estado: '', orden: 'nombre:asc' })
const ESTADOS = [
  { valor: '', texto: 'Todas', icono: 'store' },
  { valor: '1', texto: 'Activas', icono: 'check_circle' },
  { valor: '0', texto: 'Inactivas', icono: 'block' }
]
const ORDENES = [
  { valor: 'nombre:asc', texto: 'Nombre (A–Z)' },
  { valor: 'nombre:desc', texto: 'Nombre (Z–A)' },
  { valor: 'created_at:desc', texto: 'Más recientes' }
]
const hayFiltros = computed(() => !!filtros.q || filtros.estado !== '')

const cargar = async () => {
  try {
    cargando.value = true
    const [orden, dir] = filtros.orden.split(':')
    sucursales.value = await obtenerSucursales({
      q: filtros.q || undefined,
      activo: filtros.estado === '' ? undefined : filtros.estado,
      orden,
      dir
    })
  } catch (e) {
    notificar(mensajeError(e), 'error')
  } finally {
    cargando.value = false
  }
}

let temporizador
watch(() => filtros.q, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(cargar, 250)
})
watch(() => [filtros.estado, filtros.orden], cargar)

const nueva = () => {
  seleccionada.value = null
  errorForm.value = ''
  mostrarFormulario.value = true
}

const editar = (sucursal) => {
  seleccionada.value = sucursal
  errorForm.value = ''
  mostrarFormulario.value = true
}

const cancelar = () => {
  mostrarFormulario.value = false
  seleccionada.value = null
}

const guardar = async (datos) => {
  guardando.value = true
  errorForm.value = ''
  try {
    if (seleccionada.value) {
      await editarSucursal(seleccionada.value._id, datos)
      notificar('Cambios guardados', 'ok')
    } else {
      await agregarSucursal(datos)
      notificar('Sucursal agregada', 'ok')
    }
    cancelar()
    await cargar()
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    guardando.value = false
  }
}

const eliminar = async (sucursal) => {
  const acepto = await confirmar({
    titulo: 'Eliminar sucursal',
    mensaje: `Se eliminará «${sucursal.nombre}». Esta acción no se puede deshacer.`,
    aceptar: 'Eliminar',
    peligro: true
  })
  if (!acepto) return
  try {
    await eliminarSucursal(sucursal._id)
    notificar('Sucursal eliminada', 'ok')
    await cargar()
  } catch (e) {
    notificar(mensajeError(e), 'error')
  }
}

onMounted(cargar)
</script>

<template>
  <div class="stack">
    <div class="search">
      <span class="icon">search</span>
      <input v-model="filtros.q" placeholder="Buscar sucursal por nombre, dirección o teléfono" aria-label="Buscar sucursal" />
      <button v-if="filtros.q" class="icon-btn" aria-label="Limpiar" @click="filtros.q = ''"><span class="icon">close</span></button>
    </div>

    <div class="row wrap">
      <div class="segmented" role="tablist">
        <button v-for="e in ESTADOS" :key="e.valor" :class="{ on: filtros.estado === e.valor }" @click="filtros.estado = e.valor">
          <span class="icon">{{ e.icono }}</span>{{ e.texto }}
        </button>
      </div>
      <label class="field" style="width:220px">
        <select v-model="filtros.orden">
          <option v-for="o in ORDENES" :key="o.valor" :value="o.valor">{{ o.texto }}</option>
        </select>
        <span>Ordenar por</span>
      </label>
    </div>

    <div v-if="cargando && !sucursales.length" class="spinner" />
    <Vacio v-else-if="!sucursales.length" icono="store"
      :titulo="hayFiltros ? 'Sin resultados' : 'Aún no hay sucursales'"
      :texto="hayFiltros ? 'Pruebe con otra búsqueda o filtro.' : 'Use el botón + para agregar la primera.'" />
    <SucursalesTable v-else :sucursales="sucursales" @editar="editar" @eliminar="eliminar" />

    <button class="fab" @click="nueva"><span class="icon">add</span>Agregar sucursal</button>

    <SucursalForm v-if="mostrarFormulario" :sucursal="seleccionada" :guardando="guardando"
      :error-servidor="errorForm" @guardar="guardar" @cancelar="cancelar" />
  </div>
</template>
