<script setup>
import { onMounted, ref } from 'vue'
import ProductosTable from '../components/productos/ProductosTable.vue'
import ProductoForm from '../components/productos/ProductoForm.vue'
import Vacio from '../components/comunes/Vacio.vue'
import { useCatalogo } from '../composables/useCatalogo'
import { obtenerProductos, agregarProducto, editarProducto, eliminarProducto } from '../services/productosService'
import { obtenerProveedoresCatalogo, obtenerSucursalesCatalogo } from '../services/authService'
import { session, esSuper } from '../stores/session'
import { mensajeError } from '../services/api'

const proveedores = ref([])
const sucursales = ref([])
const cargandoCatalogos = ref(true)
const errorCatalogos = ref('')
async function cargarCatalogos() {
  cargandoCatalogos.value = true
  errorCatalogos.value = ''
  try {
    [proveedores.value, sucursales.value] = await Promise.all([obtenerProveedoresCatalogo(), obtenerSucursalesCatalogo()])
  } catch (e) {
    errorCatalogos.value = mensajeError(e)
  } finally {
    cargandoCatalogos.value = false
  }
}
const { registros, cargando, error, filtros, hayFiltros, mostrarFormulario, seleccionado,
  guardando, errorForm, cargar, abrir, cerrar, guardar, eliminar } = useCatalogo({
  obtener: obtenerProductos, agregar: agregarProducto, editar: editarProducto, eliminar: eliminarProducto
}, 'producto', { proveedor_id: '', sucursal_id: session.usuario.sucursal_id, categoria: '' })
onMounted(cargarCatalogos)
</script>
<template>
  <div class="stack" :aria-busy="cargando">
    <div class="search">
      <span class="icon">search</span>
      <input v-model="filtros.q" placeholder="Buscar por código, nombre o categoría" aria-label="Buscar producto" maxlength="100" />
      <button v-if="filtros.q" class="icon-btn" aria-label="Limpiar búsqueda" @click="filtros.q = ''"><span class="icon">close</span></button>
    </div>
    <div v-if="errorCatalogos" class="stack" role="alert"><p class="form-error">{{ errorCatalogos }}</p><button class="btn text" @click="cargarCatalogos">Reintentar carga de proveedores y sucursales</button></div>
    <div class="row wrap">
      <label class="field" style="width:220px"><select v-model="filtros.activo"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select><span>Estado</span></label>
      <label class="field" style="width:220px"><select v-model="filtros.proveedor_id" :disabled="cargandoCatalogos"><option value="">Todos los proveedores</option><option v-for="p in proveedores" :key="p._id" :value="p._id">{{ p.nombre }}</option></select><span>Proveedor</span></label>
      <label v-if="esSuper()" class="field" style="width:220px"><select v-model="filtros.sucursal_id" :disabled="cargandoCatalogos"><option value="">Todas las sucursales</option><option v-for="s in sucursales" :key="s._id" :value="s._id">{{ s.nombre }}</option></select><span>Existencias de</span></label>
      <label class="field" style="width:220px"><input v-model="filtros.categoria" placeholder=" " maxlength="80" /><span>Categoría exacta</span></label>
      <label class="field" style="width:220px"><select v-model="filtros.orden"><option value="nombre:asc">Nombre (A–Z)</option><option value="nombre:desc">Nombre (Z–A)</option><option value="precio_venta:asc">Menor precio</option><option value="precio_venta:desc">Mayor precio</option><option value="created_at:desc">Más recientes</option></select><span>Ordenar por</span></label>
    </div>
    <p class="muted">{{ !esSuper() ? `Existencias de ${session.usuario.sucursal_nombre}.` : filtros.sucursal_id ? 'Existencias de la sucursal seleccionada.' : 'Existencias totales de todas las sucursales.' }}</p>
    <div v-if="error" class="stack" role="alert"><p class="form-error">{{ error }}</p><button class="btn text" @click="cargar">Reintentar</button></div>
    <div v-else-if="cargando" class="spinner" aria-label="Cargando productos" />
    <Vacio v-else-if="!registros.length" icono="menu_book" :titulo="hayFiltros ? 'Sin resultados' : 'Aún no hay productos'" :texto="hayFiltros ? 'Pruebe con otra búsqueda o filtro.' : 'Use el botón + para agregar el primero.'" />
    <ProductosTable v-else :productos="registros" @editar="abrir" @eliminar="eliminar" />
    <Teleport to="body"><button class="fab" :disabled="cargandoCatalogos || !!errorCatalogos" @click="abrir()"><span class="icon">add</span>Agregar producto</button></Teleport>
    <ProductoForm v-if="mostrarFormulario" :producto="seleccionado" :proveedores="proveedores" :guardando="guardando" :error-servidor="errorForm" @guardar="guardar" @cancelar="cerrar" />
  </div>
</template>
