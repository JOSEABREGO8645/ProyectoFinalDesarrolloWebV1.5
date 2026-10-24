<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import ReporteTable from '../components/reportes/ReporteTable.vue'
import Dialog from '../components/comunes/Dialog.vue'
import { obtenerTiposReporte, obtenerReporte, descargarReporte, obtenerProductosReporte } from '../services/reportesService'
import { obtenerSucursalesCatalogo, obtenerProveedoresCatalogo } from '../services/authService'
import { obtenerClientesOperacion } from '../services/operacionesService'
import { mensajeError } from '../services/api'
import { session, esSuper } from '../stores/session'
import { celdaReporte } from '../utils/reportes'
import { fecha } from '../utils/format'

const tipos=ref([]), tipo=ref('existencias'), datos=ref(null), sucursales=ref([]), proveedores=ref([]), clientes=ref([]), productos=ref([])
const cargando=ref(true), iniciando=ref(true), exportando=ref(false), error=ref(''), errorOpciones=ref(''), busquedaProducto=ref(''), impresion=ref(false)
const filtros=reactive({sucursal_id:session.usuario.sucursal_id,desde:'',hasta:'',q:'',categoria:'',producto_id:'',proveedor_id:'',cliente_id:'',estado:'',por_pagina:25})
const definicion=computed(()=>tipos.value.find(t=>t.id===tipo.value))
const permite=campo=>definicion.value?.filtros.includes(campo)
let activo=true, consulta=0, turnoProductos=0, temporizador, aplicados=null
async function inicializar() {
  iniciando.value=true;error.value='';errorOpciones.value=''
  try {
    const [meta,sedes,prov,cli,prod]=await Promise.all([obtenerTiposReporte(),obtenerSucursalesCatalogo(),obtenerProveedoresCatalogo(),obtenerClientesOperacion(''),obtenerProductosReporte('')])
    if(!activo)return
    tipos.value=meta.reportes;sucursales.value=sedes;proveedores.value=prov;clientes.value=cli;productos.value=prod
    filtros.desde=meta.desde;filtros.hasta=meta.hasta
    await cargar()
  } catch(e) {if(activo)error.value=mensajeError(e)}
  finally {if(activo){iniciando.value=false;cargando.value=false}}
}
function parametros() {
  const p={sucursal_id:filtros.sucursal_id,por_pagina:filtros.por_pagina}
  if(definicion.value.fechas){p.desde=filtros.desde;p.hasta=filtros.hasta}
  for(const campo of definicion.value.filtros)if(filtros[campo]!=='' && filtros[campo]!=null)p[campo]=typeof filtros[campo]==='string'?filtros[campo].trim():filtros[campo]
  return Object.fromEntries(Object.entries(p).filter(([,v])=>v!==''))
}
async function cargar(pagina=1,paginando=false) {
  if(!definicion.value)return
  const turno=++consulta
  const id=paginando?datos.value.reporte.id:tipo.value
  const p={...(paginando?aplicados:parametros()),pagina}
  cargando.value=true;error.value=''
  try {
    const respuesta=await obtenerReporte(id,p)
    if(activo && turno===consulta){datos.value=respuesta;aplicados=p}
  } catch(e){if(activo&&turno===consulta)error.value=mensajeError(e)}
  finally{if(activo&&turno===consulta)cargando.value=false}
}
function cambiar() {
  for(const c of ['q','categoria','producto_id','proveedor_id','cliente_id','estado'])filtros[c]=''
  busquedaProducto.value='';cargar()
}
async function buscarProductos() {
  const turno=++turnoProductos;errorOpciones.value=''
  try {const lista=await obtenerProductosReporte(busquedaProducto.value.trim());if(activo&&turno===turnoProductos)productos.value=lista}
  catch(e){if(activo&&turno===turnoProductos)errorOpciones.value=mensajeError(e)}
}
watch(busquedaProducto,()=>{filtros.producto_id='';turnoProductos++;clearTimeout(temporizador);temporizador=setTimeout(buscarProductos,250)})
async function exportar() {
  if(exportando.value || !datos.value)return
  exportando.value=true;error.value=''
  try {
    const blob=await descargarReporte(datos.value.reporte.id,aplicados)
    if(!activo)return
    const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=`reporte-${datos.value.reporte.id}.csv`;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000)
  } catch(e){if(activo)error.value=mensajeError(e)}
  finally{if(activo)exportando.value=false}
}
function imprimir(){window.print()}
onMounted(inicializar)
onUnmounted(()=>{activo=false;consulta++;turnoProductos++;clearTimeout(temporizador)})
</script>
<template>
  <div class="stack reportes" :aria-busy="cargando">
    <div><h2>Reportes de la librería</h2><p class="muted">Nueve análisis distintos con filtros, impresión por página y exportación completa en CSV.</p></div>
    <form v-if="tipos.length" id="form-reportes" class="grid2" @submit.prevent="cargar()">
      <label class="field"><select v-model="tipo" aria-label="Tipo de reporte" :disabled="cargando||exportando" @change="cambiar"><option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.titulo }}</option></select><span>Reporte</span></label>
      <label v-if="esSuper()" class="field"><select v-model="filtros.sucursal_id" aria-label="Sucursal del reporte" :disabled="cargando"><option value="">Todas las sucursales</option><option v-for="s in sucursales" :key="s._id" :value="s._id">{{ s.nombre }}</option></select><span>Sucursal</span></label>
      <p v-else class="muted">{{ session.usuario.sucursal_nombre }}</p>
      <template v-if="definicion?.fechas"><label class="field"><input v-model="filtros.desde" type="date" required :disabled="cargando" aria-label="Desde" /><span>Desde</span></label><label class="field"><input v-model="filtros.hasta" type="date" required :min="filtros.desde" :disabled="cargando" aria-label="Hasta" /><span>Hasta</span></label></template>
      <label v-if="permite('q')" class="field"><input v-model="filtros.q" placeholder=" " maxlength="100" :disabled="cargando" aria-label="Buscar en reporte" /><span>Código o nombre</span></label>
      <label v-if="permite('categoria')" class="field"><input v-model="filtros.categoria" placeholder=" " maxlength="80" :disabled="cargando" /><span>Categoría exacta</span></label>
      <template v-if="permite('producto_id')"><label class="field"><input v-model="busquedaProducto" placeholder=" " maxlength="100" :disabled="cargando" aria-label="Buscar producto del reporte" /><span>Buscar producto para filtrar</span></label><label class="field"><select v-model="filtros.producto_id" aria-label="Producto del reporte" :disabled="cargando"><option value="">Todos los productos</option><option v-for="p in productos" :key="p._id" :value="p._id">{{ p.codigo }} · {{ p.nombre }}{{ p.activo?'':' (inactivo)' }}</option></select><span>Producto</span></label></template>
      <label v-if="permite('proveedor_id')" class="field"><select v-model="filtros.proveedor_id" :disabled="cargando" aria-label="Proveedor del reporte"><option value="">Todos los proveedores</option><option v-for="p in proveedores" :key="p._id" :value="p._id">{{ p.nombre }}</option></select><span>Proveedor</span></label>
      <label v-if="permite('cliente_id')" class="field"><select v-model="filtros.cliente_id" :disabled="cargando" aria-label="Cliente del reporte"><option value="">Todos los clientes / CF</option><option v-for="c in clientes" :key="c._id" :value="c._id">{{ c.nit }} · {{ c.nombre }}</option></select><span>Cliente registrado</span></label>
      <label v-if="permite('estado')" class="field"><select v-model="filtros.estado" :disabled="cargando" aria-label="Estado del traslado"><option value="">Todos los estados</option><option value="enviado">Enviado</option><option value="confirmado">Confirmado</option><option value="anulado">Anulado</option></select><span>Estado</span></label>
      <label class="field"><select v-model.number="filtros.por_pagina" :disabled="cargando" aria-label="Filas por página"><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select><span>Filas por página</span></label>
      <div class="row"><button class="btn" :disabled="cargando||exportando">Consultar</button></div>
    </form>
    <p v-if="errorOpciones" class="form-error" role="alert">{{ errorOpciones }} <button class="btn text" @click="buscarProductos">Reintentar productos</button></p>
    <div v-if="error" role="alert"><p class="form-error">{{ error }}</p><button class="btn text" :disabled="cargando" @click="iniciando||!tipos.length?inicializar():cargar()">Reintentar</button></div>
    <div v-else-if="cargando" class="spinner" aria-label="Generando reporte" />
    <section v-else-if="datos" class="stack resultado-reporte">
      <h2>{{ datos.reporte.titulo }}</h2><p class="muted">{{ datos.reporte.descripcion }}</p>
      <p class="muted">{{ datos.filtros.sucursal_nombre }} · {{ datos.filtros.desde?`${datos.filtros.desde} a ${datos.filtros.hasta}`:'Inventario actual' }} · {{ datos.filtros.zona }} · Generado {{ fecha(datos.generado_at) }}</p>
      <div class="row wrap"><span v-for="r in datos.resumen" :key="r.label" class="chip info">{{ r.label }}: {{ celdaReporte(r.valor,r.tipo) }}</span></div>
      <p class="muted">Los totales corresponden al reporte completo con los filtros aplicados. Pulse Consultar después de cambiar filtros.</p>
      <div class="row wrap"><button class="btn tonal" :disabled="exportando" @click="exportar">{{ exportando?'Exportando…':'Exportar CSV completo' }}</button><button class="btn text" @click="impresion=true">Imprimir página</button><span class="muted">{{ datos.paginacion.total }} resultados</span></div>
      <ReporteTable :columnas="datos.columnas" :filas="datos.filas" />
      <div class="row wrap"><button class="btn text" :disabled="datos.paginacion.pagina<=1" @click="cargar(datos.paginacion.pagina-1,true)">Anterior</button><span>Página {{ datos.paginacion.pagina }} de {{ datos.paginacion.paginas }}</span><button class="btn text" :disabled="datos.paginacion.pagina>=datos.paginacion.paginas" @click="cargar(datos.paginacion.pagina+1,true)">Siguiente</button></div>
    </section>
    <Dialog v-if="impresion&&datos" titulo="Vista de impresión" ancho="lg" @cerrar="impresion=false">
      <article class="comprobante reporte-comprobante stack"><h2>Librería San Francisco</h2><h3>{{ datos.reporte.titulo }}</h3><p>{{ datos.reporte.descripcion }}</p><p>{{ datos.filtros.sucursal_nombre }} · {{ datos.filtros.desde?`${datos.filtros.desde} a ${datos.filtros.hasta}`:'Inventario actual' }} · {{ datos.filtros.zona }}</p><p v-for="campo in ['q','categoria','producto_id_nombre','proveedor_id_nombre','cliente_id_nombre','estado'].filter(c=>datos.filtros[c])" :key="campo">Filtro: {{ datos.filtros[campo] }}</p><p>Generado {{ fecha(datos.generado_at) }} · Página {{ datos.paginacion.pagina }} de {{ datos.paginacion.paginas }} · {{ datos.paginacion.total }} resultados totales</p><ReporteTable :columnas="datos.columnas" :filas="datos.filas" /><p>Totales del reporte completo:</p><p v-for="r in datos.resumen" :key="r.label">{{ r.label }}: {{ celdaReporte(r.valor,r.tipo) }}</p></article>
      <template #acciones><button class="btn text" @click="impresion=false">Cerrar</button><button class="btn" @click="imprimir">Imprimir</button></template>
    </Dialog>
  </div>
</template>
