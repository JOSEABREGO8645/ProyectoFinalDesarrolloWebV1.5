<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import OperacionForm from './OperacionForm.vue'
import OperacionesTable from './OperacionesTable.vue'
import Dialog from '../comunes/Dialog.vue'
import ComprobantePrueba from '../integraciones/ComprobantePrueba.vue'
import { obtenerSucursalesCatalogo } from '../../services/authService'
import { obtenerKardex } from '../../services/operacionesService'
import { mensajeError } from '../../services/api'
import { session, esSuper } from '../../stores/session'
import { confirmar, notificar } from '../../stores/ui'
import { Q, fecha, pad } from '../../utils/format'

const props = defineProps({ tipo: String, servicio: Object, soloConsulta: Boolean, documentoInicial: Object })
const emit = defineEmits(['detalle-cerrado'])
const singular = { ingresos: 'ingreso', ventas: 'venta', traslados: 'traslado' }[props.tipo]
const estados = { ingresos: ['registrado', 'anulado'], ventas: ['emitida', 'anulado'], traslados: ['enviado', 'confirmado', 'anulado'] }[props.tipo]
const filtros = reactive({ sucursal_id: session.usuario.sucursal_id, estado: '' })
const sucursales = ref([]), documentos = ref([]), movimientos = ref([])
const cargando = ref(false), error = ref(''), errorSucursales = ref(''), errorForm = ref(''), errorAccion = ref('')
const formulario = ref(false), guardando = ref(false), accionando = ref(false), incierto = ref(false), kardex = ref(false), seleccionado = ref(null)
let consulta = 0, detalleConsulta = 0, activo = true, datosPendientes = null
const puedeConfirmar = computed(() => props.tipo === 'traslados' && seleccionado.value?.estado === 'enviado' && seleccionado.value.destino_id === session.usuario.sucursal_id)
const puedeAnular = computed(() => seleccionado.value && seleccionado.value.estado === (props.tipo === 'ingresos' ? 'registrado' : props.tipo === 'ventas' ? 'emitida' : 'enviado') && (props.tipo === 'traslados' ? seleccionado.value.origen_id === session.usuario.sucursal_id : esSuper()))

async function cargarSucursales() {
  errorSucursales.value = ''
  try { const lista = await obtenerSucursalesCatalogo(); if (activo) sucursales.value = lista }
  catch (e) { if (activo) errorSucursales.value = mensajeError(e) }
}
async function cargar() {
  const turno = ++consulta, verKardex = kardex.value
  cargando.value = true; error.value = ''
  try {
    const parametros = Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== ''))
    const lista = await (verKardex ? obtenerKardex(parametros) : props.servicio.obtener(parametros))
    if (activo && turno === consulta) { if (verKardex) movimientos.value = lista; else documentos.value = lista }
  } catch (e) { if (activo && turno === consulta) error.value = mensajeError(e) }
  finally { if (activo && turno === consulta) cargando.value = false }
}
function abrir() { errorForm.value = ''; datosPendientes = null; incierto.value = false; formulario.value = true }
function cerrarForm() {
  if (guardando.value) return
  formulario.value = false; datosPendientes = null
  if (incierto.value) cargar()
  incierto.value = false
}
async function guardar(datos) {
  if (guardando.value) return
  guardando.value = true; errorForm.value = ''
  if (!incierto.value) datosPendientes = JSON.parse(JSON.stringify(datos))
  try {
    const documento = await props.servicio.registrar(datosPendientes)
    if (!activo) return
    formulario.value = false; incierto.value = false; datosPendientes = null
    seleccionado.value = documento; errorAccion.value = ''
    notificar('Registro guardado correctamente.', 'ok'); await cargar()
  } catch (e) {
    if (activo) { errorForm.value = mensajeError(e); incierto.value = incierto.value || !e.response || e.response.status >= 500 }
  } finally { if (activo) guardando.value = false }
}
async function ver(id) {
  const turno = ++detalleConsulta
  error.value = ''; errorAccion.value = ''
  try { const d = await props.servicio.buscar(id); if (activo && turno === detalleConsulta) seleccionado.value = d }
  catch (e) { if (activo && turno === detalleConsulta) error.value = mensajeError(e) }
}
async function finalizar(accion) {
  if (accionando.value) return
  accionando.value = true; errorAccion.value = ''
  const id = seleccionado.value._id
  try {
    const motivo = await confirmar({ titulo: accion === 'confirmar' ? 'Confirmar recepción' : `Anular ${singular}`,
      mensaje: accion === 'confirmar' ? 'Se sumarán las unidades al inventario del destino.' : 'Se revertirán los movimientos del documento. Esta acción queda registrada.',
      aceptar: accion === 'confirmar' ? 'Confirmar recepción' : 'Anular documento', peligro: accion === 'anular', pedirTexto: accion === 'confirmar' ? 'Observación de recepción' : 'Motivo de anulación' })
    if (!motivo || !activo) return
    const documento = await props.servicio[accion](id, motivo)
    if (!activo) return
    seleccionado.value = documento; notificar('Documento actualizado.', 'ok'); await cargar()
  } catch (e) { if (activo) errorAccion.value = mensajeError(e) }
  finally { if (activo) accionando.value = false }
}
function imprimir() { window.print() }
watch(() => props.documentoInicial, d => { if (d) { seleccionado.value = d; cargar() } }, { immediate: true })
watch(seleccionado, d => { if (!d) emit('detalle-cerrado') })
watch([() => filtros.sucursal_id, () => filtros.estado, kardex], cargar)
onMounted(() => { cargarSucursales(); cargar() })
onUnmounted(() => { activo = false; datosPendientes = null })
</script>
<template>
  <div class="stack" :aria-busy="cargando">
    <div class="row wrap">
      <button class="chip" :class="{ sel: !kardex }" @click="kardex = false">Documentos</button><button class="chip" :class="{ sel: kardex }" @click="kardex = true">Kardex de inventario</button>
      <label v-if="esSuper()" class="field" style="width:240px"><select v-model="filtros.sucursal_id" aria-label="Filtrar sucursal"><option value="">Todas las sucursales</option><option v-for="s in sucursales" :key="s._id" :value="s._id">{{ s.nombre }}</option></select><span>Sucursal</span></label>
      <p v-else class="muted">{{ session.usuario.sucursal_nombre }}</p>
      <label v-if="!kardex" class="field" style="width:200px"><select v-model="filtros.estado" aria-label="Filtrar estado"><option value="">Todos los estados</option><option v-for="e in estados" :key="e" :value="e">{{ e }}</option></select><span>Estado</span></label>
      <button class="btn text" @click="cargar">Actualizar</button>
    </div>
    <div v-if="errorSucursales" role="alert"><p class="form-error">{{ errorSucursales }}</p><button class="btn text" @click="cargarSucursales">Reintentar sucursales</button></div>
    <div v-if="error" role="alert"><p class="form-error">{{ error }}</p><button class="btn text" @click="cargar">Reintentar</button></div>
    <div v-else-if="cargando" class="spinner" aria-label="Cargando documentos" />
    <template v-else-if="kardex">
      <p class="muted">Entradas y salidas de los tres procesos. Se muestran hasta 200 movimientos recientes.</p>
      <div v-if="movimientos.length" class="tabla-scroll"><table class="tabla-operaciones"><thead><tr><th>Fecha</th><th>Producto</th><th>Movimiento</th><th>Cantidad</th><th>Antes → después</th><th>Responsable</th></tr></thead><tbody><tr v-for="m in movimientos" :key="m._id"><td>{{ fecha(m.created_at) }}</td><td>{{ m.nombre }}<small>{{ m.codigo }}</small></td><td>{{ m.tipo }} #{{ pad(m.numero) }}</td><td>{{ m.cantidad > 0 ? '+' : '' }}{{ m.cantidad }}</td><td>{{ m.antes }} → {{ m.despues }}</td><td>{{ m.usuario_nombre }}</td></tr></tbody></table></div>
      <p v-else class="card muted">Aún no hay movimientos de inventario.</p>
    </template>
    <template v-else>
      <p class="muted">Se muestran hasta 200 documentos recientes. {{ tipo === 'traslados' ? 'Los enviados esperan confirmación del destino.' : 'Las anulaciones conservan el historial y quedan en el kardex.' }}</p>
      <OperacionesTable v-if="documentos.length" :documentos="documentos" :tipo="tipo" @ver="ver" />
      <p v-else class="card muted">No hay {{ tipo }} para estos filtros.</p>
    </template>
    <Teleport v-if="!soloConsulta" to="body"><button class="fab" :disabled="guardando" @click="abrir"><span class="icon">add</span>{{ tipo === 'ingresos' ? 'Registrar ingreso' : tipo === 'ventas' ? 'Nueva venta' : 'Nuevo traslado' }}</button></Teleport>
    <OperacionForm v-if="formulario" :tipo="tipo" :guardando="guardando" :incierto="incierto" :error="errorForm" @guardar="guardar" @cerrar="cerrarForm" />
    <Dialog v-if="seleccionado" :titulo="`${singular.charAt(0).toUpperCase() + singular.slice(1)} #${pad(seleccionado.numero)}`" ancho="lg" @cerrar="!accionando && (seleccionado = null)">
      <article class="comprobante stack">
        <h3>Librería San Francisco</h3><strong>{{ singular }} #{{ pad(seleccionado.numero) }} · {{ seleccionado.estado }}</strong>
        <p>{{ seleccionado.sucursal_nombre }}<br />{{ fecha(seleccionado.created_at) }} · {{ seleccionado.usuario_nombre }}</p>
        <p v-if="tipo === 'ingresos'">Proveedor: {{ seleccionado.proveedor_nombre }}</p>
        <p v-if="tipo === 'ventas'">Cliente: {{ seleccionado.cliente_nombre }} · NIT: {{ seleccionado.cliente_nit }}</p>
        <p v-if="tipo === 'traslados'">{{ seleccionado.origen_nombre }} → {{ seleccionado.destino_nombre }}</p>
        <p v-if="seleccionado.motivo">Referencia: {{ seleccionado.motivo }}</p>
        <div class="tabla-scroll"><table class="tabla-operaciones"><thead><tr><th>Producto</th><th>Cantidad</th><th v-if="tipo !== 'traslados'">Precio</th><th v-if="tipo !== 'traslados'">Subtotal</th></tr></thead><tbody><tr v-for="item in seleccionado.items" :key="item.producto_id"><td>{{ item.nombre }}<small>{{ item.codigo }}</small></td><td>{{ item.cantidad }}</td><td v-if="tipo !== 'traslados'">{{ Q(item.precio_centavos / 100) }}</td><td v-if="tipo !== 'traslados'">{{ Q(item.subtotal_centavos / 100) }}</td></tr></tbody></table></div>
        <strong v-if="tipo !== 'traslados'">Total: {{ Q(seleccionado.total_centavos / 100) }}</strong>
        <p v-if="tipo === 'ventas'">Efectivo: {{ Q(seleccionado.efectivo_centavos / 100) }} · Cambio: {{ Q(seleccionado.cambio_centavos / 100) }}</p>
        <p v-if="seleccionado.estado === 'anulado'">Anulado por {{ seleccionado.anulado_por }} · {{ fecha(seleccionado.anulado_at) }}<br />{{ seleccionado.motivo_anulacion }}</p>
        <p v-if="seleccionado.estado === 'confirmado'">Recibido por {{ seleccionado.confirmado_por }} · {{ fecha(seleccionado.confirmado_at) }}<br />{{ seleccionado.observacion }}</p>
        <ComprobantePrueba v-if="tipo !== 'traslados'" :key="seleccionado._id" :tipo="tipo" :documento="seleccionado" @actualizar="ver(seleccionado._id)" />
      </article>
      <p v-if="errorAccion" class="form-error" role="alert">{{ errorAccion }}</p>
      <template #acciones><button class="btn text" :disabled="accionando" @click="seleccionado = null">Cerrar</button><button class="btn text" :disabled="accionando" @click="imprimir">Imprimir comprobante</button><button v-if="puedeAnular" class="btn danger" :disabled="accionando" @click="finalizar('anular')">Anular documento</button><button v-if="puedeConfirmar" class="btn" :disabled="accionando" @click="finalizar('confirmar')">Confirmar recepción</button></template>
    </Dialog>
  </div>
</template>
