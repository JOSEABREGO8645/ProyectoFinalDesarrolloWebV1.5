<script setup>
import { onMounted, ref } from 'vue'
import SucursalesTable from '../components/sucursales/SucursalesTable.vue'
import Vacio from '../components/comunes/Vacio.vue'
import { obtenerSucursales } from '../services/sucursalesService'
import { mensajeError } from '../services/api'

const sucursales = ref([])
const cargando = ref(true)
const error = ref('')

const cargarSucursales = async () => {
  try {
    cargando.value = true
    error.value = ''
    sucursales.value = await obtenerSucursales()
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    cargando.value = false
  }
}

onMounted(cargarSucursales)
</script>

<template>
  <div class="stack">
    <div v-if="cargando" class="spinner" />
    <p v-else-if="error" class="chip bad" style="height:auto;padding:8px 12px;white-space:normal">{{ error }}</p>
    <Vacio v-else-if="!sucursales.length" icono="store" titulo="Aún no hay sucursales"
      texto="Las sucursales registradas aparecerán aquí." />
    <SucursalesTable v-else :sucursales="sucursales" />
  </div>
</template>