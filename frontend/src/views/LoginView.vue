<script setup>
import { reactive, ref } from 'vue'
import { iniciarSesion } from '../stores/session'
import { mensajeError } from '../services/api'
import { alternarTema } from '../stores/ui'

const datos = reactive({ usuario: '', password: '' })
const guardando = ref(false)
const error = ref('')
async function ingresar() {
  if (guardando.value) return
  error.value = ''
  if (!datos.usuario.trim() || !datos.password) { error.value = 'Complete el usuario y la contraseña'; return }
  guardando.value = true
  try {
    await iniciarSesion(datos.usuario.trim(), datos.password)
    datos.password = ''
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    guardando.value = false
  }
}
</script>
<template>
  <div class="login">
    <section class="hero">
      <div class="shape s1" /><div class="shape s2" /><div class="shape s3" />
      <span class="icon fill" style="font-size:64px">auto_stories</span>
      <h1 style="margin:0;font-size:clamp(2rem,4vw,3.5rem)">Librería<br />San Francisco</h1>
      <p style="font-size:1.125rem">Inventario, ventas y sucursales en un solo lugar.</p>
    </section>
    <section class="panel">
      <form id="form-login" novalidate @submit.prevent="ingresar">
        <div class="row"><h2 class="grow">Iniciar sesión</h2><button class="icon-btn" type="button" aria-label="Cambiar tema" @click="alternarTema"><span class="icon">contrast</span></button></div>
        <label class="field"><input v-model="datos.usuario" placeholder=" " autocomplete="username" maxlength="40" :disabled="guardando" autofocus /><span>Usuario</span></label>
        <label class="field"><input v-model="datos.password" type="password" placeholder=" " autocomplete="current-password" maxlength="200" :disabled="guardando" /><span>Contraseña</span></label>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <button class="btn" type="submit" :disabled="guardando">{{ guardando ? 'Ingresando…' : 'Iniciar sesión' }}</button>
      </form>
    </section>
  </div>
</template>
