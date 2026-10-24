<script setup>
import ProveedoresTable from '../components/proveedores/ProveedoresTable.vue'
import ProveedorForm from '../components/proveedores/ProveedorForm.vue'
import Vacio from '../components/comunes/Vacio.vue'
import { useCatalogo } from '../composables/useCatalogo'
import { obtenerProveedores, agregarProveedor, editarProveedor, eliminarProveedor } from '../services/proveedoresService'

const { registros, cargando, error, filtros, hayFiltros, mostrarFormulario, seleccionado,
  guardando, errorForm, cargar, abrir, cerrar, guardar, eliminar } = useCatalogo({
  obtener: obtenerProveedores, agregar: agregarProveedor, editar: editarProveedor, eliminar: eliminarProveedor
}, 'proveedor')
</script>
<template>
  <div class="stack" :aria-busy="cargando">
    <div class="search">
      <span class="icon">search</span>
      <input v-model="filtros.q" placeholder="Buscar proveedor por nombre, NIT o contacto" aria-label="Buscar proveedor" maxlength="100" />
      <button v-if="filtros.q" class="icon-btn" aria-label="Limpiar búsqueda" @click="filtros.q = ''"><span class="icon">close</span></button>
    </div>
    <div class="row wrap">
      <div class="segmented" aria-label="Estado del proveedor">
        <button :class="{ on: filtros.activo === '' }" @click="filtros.activo = ''">Todos</button>
        <button :class="{ on: filtros.activo === '1' }" @click="filtros.activo = '1'">Activos</button>
        <button :class="{ on: filtros.activo === '0' }" @click="filtros.activo = '0'">Inactivos</button>
      </div>
      <label class="field" style="width:220px">
        <select v-model="filtros.orden"><option value="nombre:asc">Nombre (A–Z)</option><option value="nombre:desc">Nombre (Z–A)</option><option value="created_at:desc">Más recientes</option></select>
        <span>Ordenar por</span>
      </label>
    </div>
    <div v-if="error" class="stack" role="alert"><p class="form-error">{{ error }}</p><button class="btn text" @click="cargar">Reintentar</button></div>
    <div v-else-if="cargando" class="spinner" aria-label="Cargando proveedores" />
    <Vacio v-else-if="!registros.length" icono="storefront" :titulo="hayFiltros ? 'Sin resultados' : 'Aún no hay proveedores'" :texto="hayFiltros ? 'Pruebe con otra búsqueda o filtro.' : 'Use el botón + para agregar el primero.'" />
    <ProveedoresTable v-else :proveedores="registros" @editar="abrir" @eliminar="eliminar" />
    <Teleport to="body"><button class="fab" @click="abrir()"><span class="icon">add</span>Agregar proveedor</button></Teleport>
    <ProveedorForm v-if="mostrarFormulario" :proveedor="seleccionado" :guardando="guardando" :error-servidor="errorForm" @guardar="guardar" @cancelar="cerrar" />
  </div>
</template>
