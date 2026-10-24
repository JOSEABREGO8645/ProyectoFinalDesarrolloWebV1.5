<script setup>
import { onMounted, onUnmounted, reactive, ref } from 'vue'
import { obtenerDashboard } from '../services/dashboardService'
import { obtenerSucursalesCatalogo } from '../services/authService'
import { mensajeError } from '../services/api'
import { esSuper, session } from '../stores/session'
import { Q, fecha, pad } from '../utils/format'
import VentasDiarias from '../components/dashboard/VentasDiarias.vue'

const datos = ref(null), sucursales = ref([]), cargando = ref(false), error = ref(''), errorSucursales = ref('')
const filtros = reactive({ sucursal_id: session.usuario.sucursal_id, desde: '', hasta: '' })
let activo = true, turno = 0
async function cargar() {
  const actual = ++turno
  cargando.value = true; error.value = ''
  try {
    const respuesta = await obtenerDashboard(Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '')))
    if (!activo || actual !== turno) return
    datos.value = respuesta; filtros.desde = respuesta.periodo.desde; filtros.hasta = respuesta.periodo.hasta
  } catch (e) { if (activo && actual === turno) error.value = mensajeError(e) }
  finally { if (activo && actual === turno) cargando.value = false }
}
async function cargarSucursales() {
  errorSucursales.value = ''
  try { const lista = await obtenerSucursalesCatalogo(); if (activo) sucursales.value = lista }
  catch (e) { if (activo) errorSucursales.value = mensajeError(e) }
}
function periodo(dias) {
  if (!datos.value || cargando.value) return
  const fin = filtros.hasta || datos.value.periodo.hasta
  const hasta = new Date(`${fin}T12:00:00`)
  filtros.hasta = fin
  hasta.setDate(hasta.getDate() - dias + 1)
  filtros.desde = `${hasta.getFullYear()}-${String(hasta.getMonth() + 1).padStart(2, '0')}-${String(hasta.getDate()).padStart(2, '0')}`
  cargar()
}
onMounted(() => { cargar(); cargarSucursales() })
onUnmounted(() => { activo = false; turno++ })
</script>
<template>
  <div class="stack dashboard" :aria-busy="cargando">
    <div><h2>Resumen de la librería</h2><p class="muted">{{ datos?.sucursal_nombre || session.usuario.sucursal_nombre }} · Solo se muestran sus módulos permitidos.</p></div>
    <form class="row wrap" aria-label="Filtros del dashboard" @submit.prevent="cargar">
      <label v-if="esSuper()" class="field"><select v-model="filtros.sucursal_id" :disabled="cargando" aria-label="Sucursal del dashboard"><option value="">Todas las sucursales</option><option v-for="s in sucursales" :key="s._id" :value="s._id">{{ s.nombre }}{{ s.activo ? '' : ' (inactiva)' }}</option></select><span>Sucursal</span></label>
      <label class="field"><input v-model="filtros.desde" type="date" required :disabled="cargando" aria-label="Fecha inicial" /><span>Desde</span></label>
      <label class="field"><input v-model="filtros.hasta" type="date" required :min="filtros.desde" :disabled="cargando" aria-label="Fecha final" /><span>Hasta</span></label>
      <button class="btn" :disabled="cargando">{{ cargando ? 'Consultando…' : 'Consultar' }}</button>
      <button type="button" class="chip" :disabled="cargando || !datos" @click="periodo(7)">7 días</button><button type="button" class="chip" :disabled="cargando || !datos" @click="periodo(30)">30 días</button>
    </form>
    <div v-if="errorSucursales" role="alert"><p class="form-error">{{ errorSucursales }}</p><button class="btn text" @click="cargarSucursales">Reintentar sucursales</button></div>
    <div v-if="error" role="alert"><p class="form-error">{{ error }}</p><button class="btn text" :disabled="cargando" @click="cargar">Reintentar</button></div>
    <div v-else-if="cargando" class="spinner" aria-label="Consultando dashboard" />
    <template v-else-if="datos">
      <p class="muted">Actualizado: {{ fecha(datos.actualizado_at) }}. Fechas en {{ datos.periodo.zona }}. Inventario y pendientes muestran el estado actual.</p>
      <div class="stats">
        <a v-if="datos.inventario" href="#catalogo" class="stat c1"><span>Unidades en inventario</span><strong class="big" data-indicador="unidades">{{ datos.inventario.unidades.toLocaleString('es-GT') }}</strong><small>{{ datos.inventario.activos }} productos activos · {{ datos.inventario.inactivos }} inactivos</small></a>
        <a v-if="datos.ventas" href="#ventas" class="stat c2"><span>Ventas del período</span><strong class="big" data-indicador="ventas">{{ Q(datos.ventas.total_centavos / 100) }}</strong><small>{{ datos.ventas.documentos }} emitidas · {{ datos.ventas.anulados }} anuladas</small></a>
        <a v-if="datos.ingresos" href="#ingresos" class="stat c3"><span>Ingresos del período</span><strong class="big" data-indicador="ingresos">{{ Q(datos.ingresos.total_centavos / 100) }}</strong><small>{{ datos.ingresos.documentos }} registrados · {{ datos.ingresos.unidades }} unidades</small></a>
        <a v-if="datos.traslados" href="#traslados" class="stat c4"><span>Traslados pendientes</span><strong class="big" data-indicador="pendientes">{{ datos.traslados.pendientes.documentos }}</strong><small>{{ datos.traslados.pendientes.unidades }} unidades en tránsito · {{ datos.traslados.documentos }} confirmados en el período</small></a>
      </div>
      <VentasDiarias v-if="datos.ventas" :key="`${datos.sucursal_id}:${datos.periodo.desde}:${datos.periodo.hasta}`" :dias="datos.ventas.diarias" />
      <section v-if="datos.inventario" class="card stack">
        <div class="row wrap"><h2 class="grow">Atención al inventario</h2><span class="chip bad">{{ datos.inventario.agotados }} agotados</span><span class="chip warn">{{ datos.inventario.bajos }} con existencia baja</span></div>
        <p class="muted">Productos activos. Hasta 10 alertas con menor existencia.</p>
        <div v-if="datos.inventario.alertas.length" class="list"><div v-for="p in datos.inventario.alertas" :key="p._id" class="li"><div class="grow"><strong>{{ p.nombre }}</strong><div class="t2">{{ p.codigo }} · Mínimo {{ p.minimo }}</div></div><span class="chip" :class="p.cantidad <= 0 ? 'bad' : 'warn'">{{ p.cantidad }} · {{ p.estado }}</span></div></div>
        <p v-else class="muted">No hay productos agotados ni con existencia baja.</p>
      </section>
      <div class="dashboard-recientes">
        <section v-for="tipo in ['ventas', 'ingresos', 'traslados'].filter(t => datos[t])" :key="tipo" class="card stack">
          <h2>{{ tipo === 'ventas' ? 'Ventas recientes' : tipo === 'ingresos' ? 'Ingresos recientes' : 'Traslados recientes' }}</h2>
          <p v-if="!datos[tipo].recientes.length" class="muted">No hay documentos en este período.</p>
          <div v-for="d in datos[tipo].recientes" :key="d._id" class="dashboard-documento"><div class="row wrap"><strong>#{{ pad(d.numero) }}</strong><span class="chip" :class="d.estado === 'anulado' ? 'bad' : 'info'">{{ d.estado }}</span><strong v-if="tipo !== 'traslados'">{{ Q(d.total_centavos / 100) }}</strong></div><p>{{ tipo === 'traslados' ? `${d.origen_nombre} → ${d.destino_nombre}` : d.proveedor_nombre || d.cliente_nombre }}</p><small class="muted">{{ d.sucursal_nombre }} · {{ fecha(d.fecha) }}</small></div>
          <a class="btn text" :href="`#${tipo}`">Ver {{ tipo }}</a>
        </section>
      </div>
    </template>
  </div>
</template>
