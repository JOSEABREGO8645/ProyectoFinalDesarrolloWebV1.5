<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import Dialog from '../comunes/Dialog.vue'
import TipoCambio from '../integraciones/TipoCambio.vue'
import { obtenerSucursalesCatalogo, obtenerProveedoresCatalogo } from '../../services/authService'
import { obtenerProductosOperacion, obtenerClientesOperacion, obtenerDestinos } from '../../services/operacionesService'
import { mensajeError } from '../../services/api'
import { session } from '../../stores/session'
import { Q } from '../../utils/format'

const props = defineProps({ tipo: String, guardando: Boolean, incierto: Boolean, error: String })
const emit = defineEmits(['guardar', 'cerrar'])
const titulo = { ingresos: 'Nuevo ingreso', ventas: 'Nueva venta', traslados: 'Nuevo traslado' }[props.tipo]
const datos = reactive({ operacion_id: crypto.randomUUID(), sucursal_id: session.usuario.sucursal_id,
  proveedor_id: '', cliente_id: '', destino_id: '', motivo: '', efectivo: '', codigo: '' })
const items = ref([])
const productos = ref([])
const sucursales = ref([])
const proveedores = ref([])
const destinos = ref([])
const clientes = ref([])
const q = ref('')
const consultaCliente = ref('')
const cargando = ref(true)
const buscando = ref(false)
const errorCarga = ref('')
const errorProductos = ref('')
const errorCliente = ref('')
const errorLocal = ref('')
const bloqueado = computed(() => props.guardando || props.incierto)
const precio = producto => Math.round(Number(producto[props.tipo === 'ingresos' ? 'precio_costo' : 'precio_venta'] || 0) * 100)
const total = computed(() => items.value.reduce((s, item) => s + precio(item) * Number(item.cantidad || 0), 0))
const opcionesDestino = computed(() => destinos.value.filter(s => s._id !== datos.sucursal_id))
let temporizador, temporizadorCliente, consulta = 0, consultaClientes = 0, activo = true

async function cargar() {
  cargando.value = true; errorCarga.value = ''
  try {
    const resultados = await Promise.all([obtenerSucursalesCatalogo(),
      props.tipo === 'ingresos' ? obtenerProveedoresCatalogo() : [],
      props.tipo === 'traslados' ? obtenerDestinos() : [],
      props.tipo === 'ventas' ? obtenerClientesOperacion('') : []])
    if (!activo) return
    ;[sucursales.value, proveedores.value, destinos.value, clientes.value] = resultados
    if (proveedores.value.filter(p => p.activo).length === 1) datos.proveedor_id = proveedores.value.find(p => p.activo)._id
    await buscar()
  } catch (e) { if (activo) errorCarga.value = mensajeError(e) }
  finally { if (activo) cargando.value = false }
}
async function buscar() {
  const turno = ++consulta
  buscando.value = true; errorProductos.value = ''
  try {
    const lista = await obtenerProductosOperacion({ q: q.value.trim(), sucursal_id: datos.sucursal_id,
      ...(props.tipo === 'ingresos' && datos.proveedor_id ? { proveedor_id: datos.proveedor_id } : {}) })
    if (activo && turno === consulta) productos.value = lista
  } catch (e) { if (activo && turno === consulta) { errorProductos.value = mensajeError(e); productos.value = [] } }
  finally { if (activo && turno === consulta) buscando.value = false }
}
async function buscarClientes() {
  const turno = ++consultaClientes
  errorCliente.value = ''
  try {
    const lista = await obtenerClientesOperacion(consultaCliente.value.trim())
    if (activo && turno === consultaClientes) clientes.value = lista
  } catch (e) { if (activo && turno === consultaClientes) errorCliente.value = mensajeError(e) }
}
watch(q, () => { clearTimeout(temporizador); temporizador = setTimeout(buscar, 250) })
watch(consultaCliente, () => { clearTimeout(temporizadorCliente); temporizadorCliente = setTimeout(buscarClientes, 250) })
watch(() => [datos.sucursal_id, datos.proveedor_id], () => {
  items.value = []; datos.destino_id = ''; productos.value = []; clearTimeout(temporizador)
  buscar()
})
function agregar(producto) {
  const item = items.value.find(i => i._id === producto._id)
  if (item) item.cantidad = Math.min(100000, Number(item.cantidad) + 1)
  else if (items.value.length < 100) items.value.push({ ...producto, cantidad: 1 })
  else errorLocal.value = 'El detalle admite hasta 100 productos distintos.'
}
function guardar() {
  if (props.guardando) return
  errorLocal.value = ''
  if (!items.value.length) { errorLocal.value = 'Agregue al menos un producto al detalle.'; return }
  if (items.value.some(i => !Number.isInteger(Number(i.cantidad)) || i.cantidad < 1 || i.cantidad > 100000)) {
    errorLocal.value = 'Las cantidades deben ser enteras entre 1 y 100000.'; return
  }
  const base = { operacion_id: datos.operacion_id, sucursal_id: datos.sucursal_id, motivo: datos.motivo.trim(),
    items: items.value.map(i => ({ producto_id: i._id, cantidad: Number(i.cantidad) })) }
  if (props.tipo === 'ingresos') base.proveedor_id = datos.proveedor_id
  if (props.tipo === 'traslados') base.destino_id = datos.destino_id
  if (props.tipo === 'ventas') Object.assign(base, { cliente_id: datos.cliente_id || null, efectivo: String(datos.efectivo), codigo: datos.codigo })
  emit('guardar', base)
}
onMounted(cargar)
onUnmounted(() => { activo = false; clearTimeout(temporizador); clearTimeout(temporizadorCliente) })
</script>
<template>
  <Dialog :titulo="titulo" ancho="lg" @cerrar="!guardando && emit('cerrar')">
    <div v-if="cargando" class="spinner" aria-label="Cargando formulario" />
    <div v-else-if="errorCarga" role="alert"><p class="form-error">{{ errorCarga }}</p><button class="btn text" @click="cargar">Reintentar</button></div>
    <form v-else id="form-operacion" class="stack" @submit.prevent="guardar">
      <p v-if="incierto" class="form-error" role="alert">No se confirmó la respuesta. Reintente este mismo registro o revise el historial antes de crear otro.</p>
      <div class="grid2">
        <label class="field"><select v-model="datos.sucursal_id" :disabled="true" aria-label="Sucursal de origen" required><option v-for="s in sucursales.filter(s => s.activo)" :key="s._id" :value="s._id">{{ s.nombre }}</option></select><span>{{ tipo === 'traslados' ? 'Origen' : 'Sucursal' }} *</span></label>
        <label v-if="tipo === 'ingresos'" class="field"><select v-model="datos.proveedor_id" :disabled="bloqueado" aria-label="Proveedor del ingreso" required><option value="">Seleccione proveedor</option><option v-for="p in proveedores.filter(p => p.activo)" :key="p._id" :value="p._id">{{ p.nombre }}</option></select><span>Proveedor *</span></label>
        <label v-if="tipo === 'traslados'" class="field"><select v-model="datos.destino_id" :disabled="bloqueado" aria-label="Sucursal de destino" required><option value="">Seleccione destino</option><option v-for="s in opcionesDestino" :key="s._id" :value="s._id">{{ s.nombre }}</option></select><span>Destino *</span></label>
        <label class="field"><input v-model="datos.motivo" :required="tipo !== 'ventas'" :minlength="tipo !== 'ventas' ? 3 : undefined" maxlength="300" placeholder=" " :disabled="bloqueado" aria-label="Referencia o motivo" /><span>{{ tipo === 'ingresos' ? 'Factura / referencia *' : tipo === 'traslados' ? 'Motivo del traslado *' : 'Observación' }}</span></label>
      </div>
      <div class="search"><span class="icon">search</span><input v-model="q" placeholder="Buscar por código o nombre" aria-label="Buscar producto para detalle" maxlength="100" :disabled="bloqueado" /></div>
      <div v-if="errorProductos" role="alert"><p class="form-error">{{ errorProductos }}</p><button type="button" class="btn text" @click="buscar">Reintentar búsqueda</button></div>
      <p v-else-if="buscando" class="muted">Buscando productos…</p>
      <div v-else class="productos-operacion">
        <button v-for="p in productos.slice(0, 10)" :key="p._id" type="button" class="producto-operacion" :aria-label="`Agregar ${p.nombre}`" :disabled="bloqueado || (tipo === 'ingresos' ? !datos.proveedor_id : p.existencia <= 0)" @click="agregar(p)">
          <strong>{{ p.nombre }}</strong><small>{{ p.codigo }} · {{ tipo !== 'traslados' ? Q(precio(p) / 100) + ' · ' : '' }}{{ p.existencia }} disponibles</small><span class="icon">add_circle</span>
        </button>
        <p v-if="!productos.length" class="muted">No hay productos disponibles para esta búsqueda.</p>
      </div>
      <div v-if="items.length" class="tabla-scroll"><table class="tabla-operaciones"><thead><tr><th>Producto</th><th>Cantidad</th><th v-if="tipo !== 'traslados'">Subtotal</th><th /></tr></thead><tbody>
        <tr v-for="item in items" :key="item._id"><td>{{ item.nombre }}<small class="muted">{{ item.codigo }}</small></td><td><input v-model.number="item.cantidad" class="cantidad-operacion" type="number" min="1" max="100000" step="1" required :disabled="bloqueado" :aria-label="`Cantidad de ${item.nombre}`" /></td><td v-if="tipo !== 'traslados'">{{ Q(precio(item) * item.cantidad / 100) }}</td><td><button type="button" class="icon-btn danger" :aria-label="`Quitar ${item.nombre}`" :disabled="bloqueado" @click="items = items.filter(i => i._id !== item._id)"><span class="icon">close</span></button></td></tr>
      </tbody></table></div>
      <p v-else class="muted">Agregue productos para completar el detalle.</p>
      <div v-if="tipo !== 'traslados'" class="total-box"><strong>Total estimado</strong><strong class="num">{{ Q(total / 100) }}</strong></div>
      <TipoCambio v-if="tipo !== 'traslados'" />
      <template v-if="tipo === 'ventas'">
        <p class="muted">El total definitivo usa los precios vigentes al guardar.</p>
        <div class="grid2">
          <label class="field"><input v-model="consultaCliente" placeholder=" " maxlength="100" :disabled="bloqueado" aria-label="Buscar cliente por NIT o nombre" /><span>Buscar cliente por NIT o nombre</span></label>
          <label class="field"><select v-model="datos.cliente_id" :disabled="bloqueado" aria-label="Cliente de venta"><option value="">Consumidor Final (CF)</option><option v-for="c in clientes" :key="c._id" :value="c._id">{{ c.nit }} · {{ c.nombre }}</option></select><span>Cliente</span></label>
          <label class="field"><input v-model="datos.efectivo" type="number" min="0" max="99999999.99" step="0.01" required placeholder=" " :disabled="bloqueado" aria-label="Efectivo recibido" /><span>Efectivo recibido *</span></label>
          <label class="field"><input v-model="datos.codigo" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required placeholder=" " autocomplete="off" :disabled="bloqueado" aria-label="Código de facturación" /><span>Código de facturación (4 dígitos) *</span></label>
        </div>
        <p v-if="errorCliente" class="form-error" role="alert">{{ errorCliente }} <button type="button" class="btn text" @click="buscarClientes">Reintentar clientes</button></p>
        <p v-if="!session.usuario.tiene_codigo" class="form-error">Configure su código de facturación en Usuarios antes de vender.</p>
        <p v-if="datos.efectivo !== ''" class="muted">Cambio estimado: {{ Q(Math.max(0, Math.round(Number(datos.efectivo) * 100) - total) / 100) }}</p>
      </template>
      <p v-if="errorLocal || error" class="form-error" role="alert">{{ errorLocal || error }}</p>
    </form>
    <template #acciones><button class="btn text" :disabled="guardando" @click="emit('cerrar')">{{ incierto ? 'Revisar historial' : 'Cancelar' }}</button><button class="btn" type="submit" form="form-operacion" :disabled="guardando || cargando || !!errorCarga || !items.length">{{ guardando ? 'Guardando…' : incierto ? 'Reintentar registro' : tipo === 'ventas' ? 'Registrar venta' : tipo === 'ingresos' ? 'Registrar ingreso' : 'Enviar traslado' }}</button></template>
  </Dialog>
</template>
