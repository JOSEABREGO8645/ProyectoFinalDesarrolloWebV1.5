<script setup>
import ClientesTable from '../components/clientes/ClientesTable.vue'
import ClienteForm from '../components/clientes/ClienteForm.vue'
import Vacio from '../components/comunes/Vacio.vue'
import { useCatalogo } from '../composables/useCatalogo'
import { obtenerClientes, agregarCliente, editarCliente, eliminarCliente } from '../services/clientesService'

const { registros, cargando, error, filtros, hayFiltros, mostrarFormulario, seleccionado,
  guardando, errorForm, cargar, abrir, cerrar, guardar, eliminar } = useCatalogo({
  obtener: obtenerClientes, agregar: agregarCliente, editar: editarCliente, eliminar: eliminarCliente
}, 'cliente')
</script>
<template>
  <div class="stack" :aria-busy="cargando">
    <div class="search">
      <span class="icon">search</span>
      <input v-model="filtros.q" placeholder="Buscar cliente por nombre, NIT o teléfono" aria-label="Buscar cliente" maxlength="100" />
      <button v-if="filtros.q" class="icon-btn" aria-label="Limpiar búsqueda" @click="filtros.q = ''"><span class="icon">close</span></button>
    </div>
    <label class="field" style="width:220px">
      <select v-model="filtros.orden"><option value="nombre:asc">Nombre (A–Z)</option><option value="nombre:desc">Nombre (Z–A)</option><option value="nit:asc">NIT</option><option value="created_at:desc">Más recientes</option></select>
      <span>Ordenar por</span>
    </label>
    <div v-if="error" class="stack" role="alert"><p class="form-error">{{ error }}</p><button class="btn text" @click="cargar">Reintentar</button></div>
    <div v-else-if="cargando" class="spinner" aria-label="Cargando clientes" />
    <Vacio v-else-if="!registros.length" icono="group" :titulo="hayFiltros ? 'Sin resultados' : 'Aún no hay clientes'" :texto="hayFiltros ? 'Pruebe con otra búsqueda.' : 'Use el botón + para agregar el primero.'" />
    <ClientesTable v-else :clientes="registros" @editar="abrir" @eliminar="eliminar" />
    <Teleport to="body"><button class="fab" @click="abrir()"><span class="icon">add</span>Agregar cliente</button></Teleport>
    <ClienteForm v-if="mostrarFormulario" :cliente="seleccionado" :guardando="guardando" :error-servidor="errorForm" @guardar="guardar" @cancelar="cerrar" />
  </div>
</template>
