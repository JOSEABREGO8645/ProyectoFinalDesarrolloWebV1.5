<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import Dialog from '../comunes/Dialog.vue'
import ClienteForm from '../clientes/ClienteForm.vue'
import { obtenerClientesOperacion } from '../../services/operacionesService'
import { agregarCliente } from '../../services/clientesService'
import { mensajeError } from '../../services/api'
import { limpiarNit } from '../../utils/contacto'
import { puede } from '../../stores/session'
const emit = defineEmits(['seleccionar', 'cerrar'])
const consulta = ref(''), clientes = ref([]), error = ref(''), cargando = ref(false), nuevo = ref(false), guardando = ref(false)
let activo = true, turno = 0
async function buscar(exacto = false) {
  const actual = ++turno; cargando.value = true; error.value = ''
  const nit = limpiarNit(consulta.value.trim())
  if (exacto && (!nit || nit === 'CF')) { emit('seleccionar', null); return }
  try {
    const lista = await obtenerClientesOperacion(exacto ? { nit } : { q: consulta.value.trim() })
    if (!activo || turno !== actual) return
    clientes.value = lista
    if (exacto && lista.length === 1) emit('seleccionar', lista[0])
    else if (!lista.length) error.value = 'No hay clientes para esa búsqueda.'
  } catch (e) { if (activo && turno === actual) error.value = mensajeError(e) }
  finally { if (activo && turno === actual) cargando.value = false }
}
async function guardar(datos) {
  if (guardando.value) return
  guardando.value = true; error.value = ''
  try { const r = await agregarCliente(datos); if (activo) emit('seleccionar', r.datos) }
  catch (e) { if (activo) error.value = mensajeError(e) }
  finally { if (activo) guardando.value = false }
}
onMounted(() => buscar())
onUnmounted(() => { activo = false })
</script>
<template>
  <ClienteForm v-if="nuevo" :nit-inicial="limpiarNit(consulta.trim())" :guardando="guardando" :error-servidor="error" @guardar="guardar" @cancelar="nuevo = false; error = ''" />
  <Dialog v-else titulo="Agregar cliente (NIT)" ancho="md" @cerrar="emit('cerrar')">
    <div class="stack">
      <form id="buscar-cliente-venta" class="row wrap" @submit.prevent="buscar(true)">
        <label class="field grow"><input v-model="consulta" maxlength="100" placeholder=" " autofocus aria-label="NIT o nombre del cliente" /><span>NIT o nombre del cliente</span></label>
        <button class="btn text" type="button" :disabled="cargando" @click="buscar()">Buscar</button>
        <button class="btn" type="submit">Agregar por NIT</button>
      </form>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <p v-if="cargando" class="muted">Buscando clientes…</p>
      <div class="pos-clientes"><button v-for="c in clientes" :key="c._id" class="producto-operacion" @click="emit('seleccionar', c)"><strong>{{ c.nombre }}</strong><small>NIT: {{ c.nit }}</small></button></div>
      <button v-if="puede('clientes')" class="btn tonal" type="button" @click="nuevo = true; error = ''">Registrar nuevo cliente</button>
    </div>
    <template #acciones><button class="btn text" @click="emit('cerrar')">Cancelar</button><button class="btn tonal" @click="emit('seleccionar', null)">Consumidor Final (CF)</button></template>
  </Dialog>
</template>
