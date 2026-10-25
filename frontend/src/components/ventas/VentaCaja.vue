<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import ClienteVenta from './ClienteVenta.vue'
import CobroVenta from './CobroVenta.vue'
import { obtenerProductosOperacion } from '../../services/operacionesService'
import { ventasService } from '../../services/ventasService'
import { borradorCaja } from '../../stores/caja'
import { session } from '../../stores/session'
import { confirmar } from '../../stores/ui'
import { mensajeError } from '../../services/api'
import { leerCodigo, centavos } from '../../utils/caja'
import { Q } from '../../utils/format'

const emit = defineEmits(['venta', 'historial'])
const venta = borradorCaja(), sucursal = session.usuario.sucursal_id
const entrada = ref(''), buscador = ref(null), productos = ref([]), buscando = ref(false), pendientes = ref(0)
const avisos = ref([]), error = ref(''), errorBusqueda = ref(''), estado = ref(''), clienteAbierto = ref(false), cobrando = ref(false), revisando = ref(false), pinRequerido = ref(false)
const bloqueado = computed(() => venta.guardando || venta.incierto || revisando.value)
const total = computed(() => venta.items.reduce((s, i) => s + i.precio_centavos * i.cantidad, 0))
const unidades = computed(() => venta.items.reduce((s, i) => s + i.cantidad, 0))
let activo = true, temporizador, turno = 0, cola = Promise.resolve()
const cantidadActual = id => venta.items.find(i => i._id === id)?.cantidad || 0
async function enfocar() { await nextTick(); if (activo && !cobrando.value && !clienteAbierto.value) buscador.value?.focus() }
defineExpose({ enfocar })
async function buscar() {
  const actual = ++turno
  buscando.value = true; errorBusqueda.value = ''
  try {
    const q = entrada.value.trim().split('*').at(-1).trim().slice(0, 100)
    const lista = await obtenerProductosOperacion({ q, sucursal_id: sucursal })
    if (activo && turno === actual) productos.value = lista
  } catch (e) { if (activo && turno === actual) errorBusqueda.value = mensajeError(e) }
  finally { if (activo && turno === actual) buscando.value = false }
}
watch(entrada, () => { clearTimeout(temporizador); temporizador = setTimeout(buscar, 250) })
watch(() => venta.ultima, documento => {
  if (activo && documento) { emit('venta', documento); venta.ultima = null }
})
function validarCantidad(producto, cantidad) {
  if (!Number.isInteger(cantidad) || cantidad < 1 || cantidad > 100000) throw new Error('La cantidad debe ser un entero entre 1 y 100000.')
  if (cantidad > producto.existencia) throw new Error(producto.nombre + ': solo hay ' + producto.existencia + ' unidades en esta sucursal.')
  const precio = producto.precio_centavos ?? centavos(producto.precio_venta)
  if (precio === null) throw new Error('El precio del producto no es válido.')
  const nuevo = venta.items.filter(i => i._id !== producto._id).reduce((s, i) => s + i.precio_centavos * i.cantidad, 0) + precio * cantidad
  if (nuevo > 9999999999) throw new Error('El total excede Q 99,999,999.99.')
  return precio
}
function agregar(producto, cantidad = 1) {
  if (bloqueado.value) return
  const item = venta.items.find(i => i._id === producto._id)
  if (!item && venta.items.length >= 100) throw new Error('El detalle admite hasta 100 productos distintos.')
  const nueva = (item?.cantidad || 0) + cantidad, precio = validarCantidad(producto, nueva)
  if (item) Object.assign(item, producto, { cantidad: nueva, precio_centavos: precio })
  else venta.items.push({ ...producto, cantidad: nueva, precio_centavos: precio })
  estado.value = cantidad + ' × ' + producto.nombre + ' agregado al detalle.'
}
function elegir(producto) {
  try { agregar(producto); error.value = '' } catch (e) { error.value = e.message }
  enfocar()
}
function escanear(evento) {
  if (evento?.repeat || bloqueado.value || cobrando.value || !entrada.value.trim()) return
  const texto = entrada.value; entrada.value = ''; pendientes.value++
  cola = cola.then(async () => {
    if (!activo) return
    try {
      const lectura = leerCodigo(texto)
      const lista = await obtenerProductosOperacion({ codigo: lectura.codigo, sucursal_id: sucursal })
      if (!activo) return
      if (lista.length !== 1) throw new Error('No existe un producto activo con ese código.')
      agregar(lista[0], lectura.cantidad)
    } catch (e) { if (activo) avisos.value.push(texto + ': ' + (e.isAxiosError ? mensajeError(e) : e.message)) }
    finally { if (activo) enfocar() }
  }).finally(() => { pendientes.value-- })
}
function cambiarCantidad(item, evento) {
  try { validarCantidad(item, Number(evento.target.value)); item.cantidad = Number(evento.target.value); error.value = '' }
  catch (e) { error.value = e.message; evento.target.value = item.cantidad }
}
async function descartar() {
  if (bloqueado.value || pendientes.value) return
  if (venta.items.length && !await confirmar({ titulo: 'Limpiar detalle', mensaje: 'Se quitarán los productos y el cliente sin registrar.', aceptar: 'Limpiar detalle' })) return
  venta.items = []; venta.cliente = null; venta.motivo = ''; venta.operacion_id = crypto.randomUUID(); avisos.value = []; error.value = ''; enfocar()
}
async function cobrar() {
  if (bloqueado.value) { if (venta.incierto) cobrando.value = true; return }
  if (!venta.items.length || pendientes.value || avisos.value.length) return
  revisando.value = true; error.value = ''
  try {
    const actualizados = await Promise.all(venta.items.map(async item => {
      const lista = await obtenerProductosOperacion({ codigo: item.codigo, sucursal_id: sucursal })
      const p = lista.find(p => p._id === item._id)
      if (!p) throw new Error(item.nombre + ' ya no está disponible. Revise el detalle.')
      if (p.existencia < item.cantidad) throw new Error(p.nombre + ': el inventario cambió; quedan ' + p.existencia + ' unidades.')
      const precio = centavos(p.precio_venta)
      if (precio === null) throw new Error('El precio de ' + p.nombre + ' no es válido.')
      return { ...p, cantidad: item.cantidad, precio_centavos: precio }
    }))
    if (!activo) return
    venta.items = actualizados
    if (total.value > 9999999999) throw new Error('El total excede Q 99,999,999.99.')
    cobrando.value = true
  } catch (e) { if (activo) error.value = e.isAxiosError ? mensajeError(e) : e.message }
  finally { if (activo) revisando.value = false }
}
function completar(documento) {
  venta.items = []; venta.cliente = null; venta.motivo = ''; venta.operacion_id = crypto.randomUUID()
  venta.pendiente = null; venta.incierto = false; venta.ultima = documento
  if (activo) {
    cobrando.value = false; error.value = ''; estado.value = 'Venta registrada correctamente.'; pinRequerido.value = false
    emit('venta', documento); venta.ultima = null; buscar()
  }
}
async function guardar(datos) {
  if (venta.guardando) return
  if (!venta.incierto) venta.pendiente = {
    operacion_id: venta.operacion_id, sucursal_id: sucursal, cliente_id: venta.cliente?._id || null,
    motivo: venta.motivo.trim(), items: venta.items.map(i => ({ producto_id: i._id, cantidad: i.cantidad })),
    total_esperado_centavos: total.value, efectivo: datos.efectivo, codigo: datos.codigo
  }
  else if (pinRequerido.value) venta.pendiente.codigo = datos.codigo
  venta.guardando = true; error.value = ''
  try { completar(await ventasService.registrar(JSON.parse(JSON.stringify(venta.pendiente)))) }
  catch (e) {
    if (!e.response || e.response.status >= 500) venta.incierto = true
    pinRequerido.value = e.response?.status === 422 && String(e.response.data?.message).includes('Código de facturación')
    if (!venta.incierto) venta.pendiente = null
    if (activo) error.value = mensajeError(e)
  } finally { venta.guardando = false }
}
async function verificar() {
  if (!venta.pendiente || venta.guardando) return
  venta.guardando = true; error.value = ''
  try {
    const lista = await ventasService.obtener({ sucursal_id: sucursal, operacion_id: venta.pendiente.operacion_id })
    if (lista.length === 1) completar(lista[0])
    else error.value = 'Todavía no aparece confirmada. Reintente esta misma facturación.'
  } catch (e) { if (activo) error.value = mensajeError(e) }
  finally { venta.guardando = false }
}
function seleccionarCliente(cliente) { venta.cliente = cliente; clienteAbierto.value = false; enfocar() }
function revisarHistorial() { cobrando.value = false; emit('historial') }
onMounted(() => { buscar(); enfocar(); if (venta.ultima) { emit('venta', venta.ultima); venta.ultima = null } })
onUnmounted(() => { activo = false; clearTimeout(temporizador) })
</script>
<template>
  <section class="pos-caja stack" aria-label="Caja de ventas">
    <div class="row wrap"><div class="grow"><h2>Caja de ventas</h2><p class="muted">{{ session.usuario.sucursal_nombre }} · Inventario de la sucursal activa</p></div><button class="btn text" @click="emit('historial')">Historial y kardex</button></div>
    <div v-if="venta.incierto" class="card stack" role="alert"><strong>Hay una facturación pendiente de confirmar.</strong><p>El detalle se conserva. Verifique el registro o reintente la misma venta antes de iniciar otra.</p><div class="row wrap"><button class="btn" :disabled="venta.guardando" @click="cobrando = true">Reintentar facturación</button><button class="btn text" :disabled="venta.guardando" @click="verificar">Verificar venta registrada</button></div></div>
    <div class="pos-barra">
      <form class="search" @submit.prevent="escanear()"><span class="icon">barcode_scanner</span><input ref="buscador" v-model="entrada" aria-label="Buscar o escanear producto" placeholder="Código de barras o nombre del producto" maxlength="150" autocomplete="off" spellcheck="false" :disabled="bloqueado" @keydown.enter.prevent="escanear($event)" /></form>
      <button class="btn tonal" :disabled="bloqueado || !!pendientes" @click="clienteAbierto = true"><span class="icon">person_add</span>Agregar cliente (NIT)</button>
    </div>
    <p class="muted pos-ayuda">Código + Enter agrega una unidad. Para varias, escriba cantidad*código: <strong>5*001234</strong> + Enter. Por nombre, seleccione el producto.</p>
    <p v-if="pendientes" role="status" class="muted">Procesando {{ pendientes }} lecturas…</p><p v-else class="pos-estado" role="status">{{ estado }}</p>
    <div v-if="avisos.length" class="form-error stack" role="alert"><strong>Revise estas lecturas antes de facturar:</strong><ul><li v-for="(aviso, i) in avisos" :key="i">{{ aviso }}</li></ul><button class="btn text" @click="avisos = []">Entendido</button></div>
    <p v-if="error && !cobrando" class="form-error" role="alert">{{ error }}</p>
    <div class="pos-distribucion">
      <div class="stack">
        <section class="card stack">
          <div class="row wrap"><h3 class="grow">Productos</h3><button class="btn text" :disabled="buscando || bloqueado" @click="buscar">Actualizar productos</button></div>
          <p v-if="errorBusqueda" class="form-error" role="alert">{{ errorBusqueda }}</p><p v-else-if="buscando" class="muted">Buscando productos…</p>
          <div class="pos-productos"><button v-for="p in productos.slice(0, 8)" :key="p._id" class="producto-operacion" :aria-label="'Agregar ' + p.nombre" :disabled="bloqueado || p.existencia <= cantidadActual(p._id)" @click="elegir(p)"><strong>{{ p.nombre }}</strong><small>{{ p.codigo }} · {{ Q(Number(p.precio_venta)) }} · {{ p.existencia }} disponibles</small><span class="icon">add_circle</span></button></div>
          <p v-if="!buscando && !productos.length" class="muted">No hay productos para esta búsqueda.</p>
        </section>
        <section class="card stack">
          <div class="row wrap"><h3 class="grow">Detalle de la venta</h3><button class="btn text" :disabled="bloqueado || !!pendientes" @click="descartar">Limpiar detalle</button></div>
          <div v-if="venta.items.length" class="tabla-scroll"><table class="tabla-operaciones pos-detalle"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th><th /></tr></thead><tbody><tr v-for="item in venta.items" :key="item._id" :data-producto="item.codigo"><td>{{ item.nombre }}<small>{{ item.codigo }}</small></td><td><input class="cantidad-operacion" type="number" :value="item.cantidad" min="1" :max="Math.min(item.existencia,100000)" step="1" :disabled="bloqueado || !!pendientes" :aria-label="'Cantidad de ' + item.nombre" @change="cambiarCantidad(item,$event)" /></td><td>{{ Q(item.precio_centavos / 100) }}</td><td>{{ Q(item.precio_centavos * item.cantidad / 100) }}</td><td><button class="icon-btn danger" :aria-label="'Quitar ' + item.nombre" :disabled="bloqueado || !!pendientes" @click="venta.items = venta.items.filter(i => i._id !== item._id)"><span class="icon">close</span></button></td></tr></tbody></table></div>
          <div v-else class="pos-vacio"><span class="icon">shopping_bag</span><p>Escanee el primer producto para comenzar.</p></div>
        </section>
      </div>
      <aside class="card stack pos-resumen">
        <h3>Resumen de venta</h3><p><strong>{{ venta.cliente?.nombre || 'Consumidor Final' }}</strong><br />NIT: {{ venta.cliente?.nit || 'CF' }}</p>
        <button v-if="venta.cliente" class="btn text" :disabled="bloqueado" @click="venta.cliente = null">Usar Consumidor Final</button>
        <p class="muted">{{ venta.items.length }} productos · {{ unidades }} unidades</p>
        <label class="field"><input v-model="venta.motivo" maxlength="300" placeholder=" " :disabled="bloqueado" /><span>Observación (opcional)</span></label>
        <div class="pos-total"><span>Total</span><strong>{{ Q(total / 100) }}</strong></div>
        <p v-if="!session.usuario.tiene_codigo" class="form-error">Configure su código de facturación en Usuarios antes de cobrar.</p>
        <button class="btn pos-facturar" :disabled="venta.guardando || revisando || !venta.items.length || !!pendientes || !!avisos.length || !session.usuario.tiene_codigo" @click="cobrar">{{ revisando ? 'Revisando precios…' : venta.incierto ? 'Reintentar facturación' : 'Guardar (facturar)' }}</button>
      </aside>
    </div>
    <ClienteVenta v-if="clienteAbierto" @seleccionar="seleccionarCliente" @cerrar="clienteAbierto = false; enfocar()" />
    <CobroVenta v-if="cobrando" :total="venta.pendiente?.total_esperado_centavos ?? total" :cliente="venta.cliente" :guardando="venta.guardando" :incierto="venta.incierto" :pedir-codigo="pinRequerido" :efectivo-inicial="venta.pendiente?.efectivo || ''" :error="error" @guardar="guardar" @cerrar="cobrando = false; error = ''; enfocar()" @historial="revisarHistorial" @verificar="verificar" />
  </section>
</template>
