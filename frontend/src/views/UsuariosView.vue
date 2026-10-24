<script setup>
import { onMounted, ref } from 'vue'
import UsuarioForm from '../components/usuarios/UsuarioForm.vue'
import UsuariosTable from '../components/usuarios/UsuariosTable.vue'
import Vacio from '../components/comunes/Vacio.vue'
import { useCatalogo } from '../composables/useCatalogo'
import { obtenerUsuarios, agregarUsuario, editarUsuario, eliminarUsuario } from '../services/usuariosService'
import { obtenerSucursalesCatalogo } from '../services/authService'
import { mensajeError } from '../services/api'
import { session } from '../stores/session'

const sucursales = ref([])
const cargandoSucursales = ref(true)
const errorSucursales = ref('')
async function cargarSucursales() {
  cargandoSucursales.value = true
  errorSucursales.value = ''
  try { sucursales.value = await obtenerSucursalesCatalogo() }
  catch (e) { errorSucursales.value = mensajeError(e) }
  finally { cargandoSucursales.value = false }
}
onMounted(cargarSucursales)
const { registros, cargando, error, filtros, hayFiltros, mostrarFormulario, seleccionado,
  guardando, errorForm, cargar, abrir, cerrar, guardar, eliminar } = useCatalogo({
  obtener: obtenerUsuarios, agregar: agregarUsuario, editar: editarUsuario, eliminar: eliminarUsuario
}, 'usuario', { rol: '' })
</script>
<template>
  <div class="stack" :aria-busy="cargando">
    <div class="search"><span class="icon">search</span><input v-model="filtros.q" placeholder="Buscar por nombre o usuario" aria-label="Buscar usuario" maxlength="100" /><button v-if="filtros.q" class="icon-btn" aria-label="Limpiar búsqueda" @click="filtros.q = ''"><span class="icon">close</span></button></div>
    <div class="row wrap">
      <label class="field" style="width:220px"><select v-model="filtros.activo"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select><span>Estado</span></label>
      <label class="field" style="width:220px"><select v-model="filtros.rol"><option value="">Todos los roles</option><option value="empleado">Empleado</option><option value="superusuario">Superusuario</option></select><span>Rol</span></label>
      <label class="field" style="width:220px"><select v-model="filtros.orden"><option value="nombre:asc">Nombre (A–Z)</option><option value="nombre:desc">Nombre (Z–A)</option><option value="usuario:asc">Usuario</option><option value="created_at:desc">Más recientes</option></select><span>Ordenar por</span></label>
    </div>
    <div v-if="errorSucursales" class="stack" role="alert"><p class="form-error">{{ errorSucursales }}</p><button class="btn text" @click="cargarSucursales">Reintentar carga de sucursales</button></div>
    <div v-if="error" class="stack" role="alert"><p class="form-error">{{ error }}</p><button class="btn text" @click="cargar">Reintentar</button></div>
    <div v-else-if="cargando" class="spinner" aria-label="Cargando usuarios" />
    <Vacio v-else-if="!registros.length" icono="admin_panel_settings" :titulo="hayFiltros ? 'Sin resultados' : 'Aún no hay usuarios'" texto="Pruebe con otra búsqueda o agregue un usuario." />
    <UsuariosTable v-else :usuarios="registros" :propio-id="session.usuario._id" @editar="abrir" @eliminar="eliminar" />
    <Teleport to="body"><button class="fab" :disabled="cargandoSucursales || !!errorSucursales" @click="abrir()"><span class="icon">add</span>Agregar usuario</button></Teleport>
    <UsuarioForm v-if="mostrarFormulario" :usuario="seleccionado" :sucursales="sucursales" :es-propio="seleccionado?._id === session.usuario._id" :guardando="guardando" :error-servidor="errorForm" @guardar="guardar" @cancelar="cerrar" />
  </div>
</template>
