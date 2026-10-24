<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import SucursalesView from './views/SucursalesView.vue'
import ProveedoresView from './views/ProveedoresView.vue'
import ClientesView from './views/ClientesView.vue'
import CatalogoView from './views/CatalogoView.vue'
import LoginView from './views/LoginView.vue'
import UsuariosView from './views/UsuariosView.vue'
import DashboardView from './views/DashboardView.vue'
import IngresosView from './views/IngresosView.vue'
import VentasView from './views/VentasView.vue'
import TrasladosView from './views/TrasladosView.vue'
import { session, inicializarSesion, cerrarSesion, puede } from './stores/session'
import { mensajeError } from './services/api'
import { MODULOS } from './config/modulos'
import ToastHost from './components/comunes/ToastHost.vue'
import ConfirmHost from './components/comunes/ConfirmHost.vue'
import { ui, alternarTema, notificar } from './stores/ui'

const vistas = { inicio: DashboardView, sucursales: SucursalesView, proveedores: ProveedoresView, clientes: ClientesView, catalogo: CatalogoView, usuarios: UsuariosView, ingresos: IngresosView, ventas: VentasView, traslados: TrasladosView }
const leerModulo = () => window.location.hash.slice(1)
const actual = ref(leerModulo())
const navegar = () => { actual.value = leerModulo() }
window.addEventListener('hashchange', navegar)
onUnmounted(() => window.removeEventListener('hashchange', navegar))
const disponibles = computed(() => MODULOS.filter(m => puede(m.id)))
const modulo = computed(() => MODULOS.find(m => m.id === actual.value) || disponibles.value[0] || null)
async function salir() {
  try { await cerrarSesion() } catch (e) { notificar(mensajeError(e), 'error') }
}
onMounted(inicializarSesion)
</script>

<template>
  <div v-if="!session.listo" class="boot-session">
    <div v-if="!session.error" class="spinner" aria-label="Comprobando sesión" />
    <template v-else><p class="form-error" role="alert">{{ session.error }}</p><button class="btn" @click="inicializarSesion">Reintentar conexión</button></template>
  </div>
  <LoginView v-else-if="!session.usuario" />
  <div v-else class="app">
    <nav class="rail" aria-label="Módulos">
      <div class="logo" title="Librería San Francisco"><span class="icon fill" style="font-size:28px">auto_stories</span></div>
      <a v-for="m in disponibles" :key="m.id" :href="`#${m.id}`" class="nav-item" :class="{ on: modulo?.id === m.id }" :aria-current="modulo?.id === m.id ? 'page' : undefined">
        <span class="pill"><span class="icon">{{ m.icono }}</span></span>{{ m.titulo }}
      </a>
    </nav>

    <main class="main">
      <header class="topbar">
        <h1 class="headline grow">{{ modulo?.titulo || 'Mi cuenta' }}</h1>
        <div class="session-meta"><strong>{{ session.usuario.nombre }}</strong><small>{{ session.usuario.sucursal_nombre }}</small></div>
        <button class="icon-btn" title="Cambiar tema" @click="alternarTema">
          <span class="icon">{{ ui.tema === 'dark' ? 'light_mode' : 'dark_mode' }}</span>
        </button>
        <button class="icon-btn" aria-label="Cerrar sesión" title="Cerrar sesión" :disabled="session.cerrando" @click="salir"><span class="icon">logout</span></button>
      </header>
      <component v-if="modulo && puede(modulo.id)" :is="vistas[modulo.id]" :key="`${session.usuario._id}:${modulo.id}`" class="page" />
      <div v-else class="card stack" role="alert"><p>{{ disponibles.length ? 'No tiene permiso para este módulo.' : 'No tiene módulos asignados. Solicite acceso al administrador.' }}</p><a v-if="disponibles.length" class="btn" :href="`#${disponibles[0].id}`">Ir a {{ disponibles[0].titulo }}</a></div>
    </main>
  </div>
  <ToastHost />
  <ConfirmHost />
</template>
