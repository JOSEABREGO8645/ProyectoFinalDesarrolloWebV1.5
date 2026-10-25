<script setup>
import { reactive, ref, watch } from 'vue'
import Dialog from '../comunes/Dialog.vue'
import { MODULOS_ASIGNABLES } from '../../config/modulos'

const props = defineProps({ usuario: { type: Object, default: null }, sucursales: { type: Array, required: true }, esPropio: Boolean, guardando: Boolean, errorServidor: String })
const emit = defineEmits(['guardar', 'cancelar'])
const formulario = reactive({ nombre: '', usuario: '', password: '', codigo: '', sucursal_id: '', sucursal_ids: [], rol: 'empleado', modulos: [], activo: true })
const errorLocal = ref('')
watch(() => props.usuario, valor => {
  Object.assign(formulario, { nombre: valor?.nombre ?? '', usuario: valor?.usuario ?? '', password: '', codigo: '',
    sucursal_id: valor?.sucursal_id ?? '', sucursal_ids: [...(valor?.sucursal_ids ?? (valor?.sucursal_id ? [valor.sucursal_id] : []))], rol: valor?.rol ?? 'empleado', modulos: [...(valor?.modulos ?? [])], activo: valor?.activo ?? true })
  errorLocal.value = ''
}, { immediate: true })
function alternarModulo(id) {
  formulario.modulos = formulario.modulos.includes(id) ? formulario.modulos.filter(m => m !== id) : [...formulario.modulos, id]
}
watch(() => formulario.sucursal_id, id => {
  if (id && !formulario.sucursal_ids.includes(id)) formulario.sucursal_ids.push(id)
})
function alternarSucursal(id) {
  formulario.sucursal_ids = formulario.sucursal_ids.includes(id) ? formulario.sucursal_ids.filter(s => s !== id) : [...formulario.sucursal_ids, id]
  if (!formulario.sucursal_ids.includes(formulario.sucursal_id)) formulario.sucursal_id = formulario.sucursal_ids[0] || ''
}
function enviar() {
  errorLocal.value = ''
  if (!formulario.nombre.trim()) errorLocal.value = 'Complete el nombre'
  else if (!/^[a-zA-Z0-9._-]{3,40}$/.test(formulario.usuario.trim())) errorLocal.value = 'El usuario debe tener de 3 a 40 letras, números, puntos, guiones o guiones bajos'
  else if ((!props.usuario || formulario.password) && (formulario.password.length < 10 || new TextEncoder().encode(formulario.password).length > 72)) errorLocal.value = 'Use una contraseña de al menos 10 caracteres y como máximo 72 caracteres simples'
  else if (formulario.codigo && !/^\d{4}$/.test(formulario.codigo)) errorLocal.value = 'El código de facturación debe tener 4 dígitos'
  else if (!formulario.sucursal_id || !formulario.sucursal_ids.length) errorLocal.value = 'Seleccione al menos una sucursal'
  if (!errorLocal.value && !props.guardando) {
    const datos = { ...formulario, nombre: formulario.nombre.trim(), usuario: formulario.usuario.trim(), modulos: formulario.rol === 'superusuario' ? [] : [...formulario.modulos] }
    if (props.usuario && !datos.password) delete datos.password
    if (!datos.codigo) delete datos.codigo
    emit('guardar', datos)
  }
}
</script>
<template>
  <Dialog :titulo="usuario ? 'Editar usuario' : 'Nuevo usuario'" ancho="md" @cerrar="!guardando && emit('cancelar')">
    <form id="form-usuario" class="grid2" novalidate @submit.prevent="enviar">
      <label class="field full"><input v-model="formulario.nombre" placeholder=" " maxlength="120" :disabled="guardando" autofocus /><span>Nombre completo *</span></label>
      <label class="field"><input v-model="formulario.usuario" placeholder=" " maxlength="40" autocomplete="off" :disabled="guardando" /><span>Usuario *</span></label>
      <label class="field"><input v-model="formulario.password" type="password" placeholder=" " maxlength="72" autocomplete="new-password" :disabled="guardando" /><span>{{ usuario ? 'Nueva contraseña' : 'Contraseña *' }}</span><small>{{ usuario ? 'Deje vacío para conservarla' : 'Mínimo 10 caracteres' }}</small></label>
      <label class="field"><input v-model="formulario.codigo" type="password" placeholder=" " maxlength="4" inputmode="numeric" autocomplete="new-password" :disabled="guardando" /><span>Código de facturación</span><small>4 dígitos; vacío conserva el código actual</small></label>
      <label class="field"><select v-model="formulario.sucursal_id" :disabled="guardando"><option value="">Seleccione una sucursal</option><option v-for="sucursal in sucursales" :key="sucursal._id" :value="sucursal._id" :disabled="formulario.activo && !sucursal.activo">{{ sucursal.nombre }}{{ sucursal.activo ? '' : ' (inactiva)' }}</option></select><span>Sucursal asignada *</span></label>
      <fieldset class="full modulos-field"><legend>Sucursales asignadas *</legend><p class="muted">Seleccione una o varias. La sucursal del campo anterior será la principal al iniciar sesión.</p><div class="row wrap"><button v-for="s in sucursales" :key="s._id" type="button" class="chip" :class="{ sel: formulario.sucursal_ids.includes(s._id) }" :aria-pressed="formulario.sucursal_ids.includes(s._id)" :aria-label="'Asignar sucursal ' + s.nombre" :disabled="guardando || (formulario.activo && !s.activo)" @click="alternarSucursal(s._id)">{{ s.nombre }}{{ s.activo ? '' : ' (inactiva)' }}</button></div><p v-if="formulario.rol === 'superusuario'" class="muted">El superusuario puede alternar entre todas las sucursales activas.</p></fieldset>
      <label class="field"><select v-model="formulario.rol" :disabled="guardando || esPropio"><option value="empleado">Empleado</option><option value="superusuario">Superusuario</option></select><span>Rol *</span></label>
      <label class="switch"><input v-model="formulario.activo" type="checkbox" :disabled="guardando || esPropio" /><span>Usuario activo</span></label>
      <fieldset v-if="formulario.rol === 'empleado'" class="full modulos-field"><legend>Módulos disponibles</legend><div class="row wrap"><button v-for="modulo in MODULOS_ASIGNABLES" :key="modulo.id" type="button" class="chip" :class="{ sel: formulario.modulos.includes(modulo.id) }" :aria-pressed="formulario.modulos.includes(modulo.id)" :disabled="guardando" @click="alternarModulo(modulo.id)"><span class="icon">{{ modulo.icono }}</span>{{ modulo.titulo }}</button></div></fieldset>
    </form>
    <p v-if="errorLocal || errorServidor" class="form-error" role="alert">{{ errorLocal || errorServidor }}</p>
    <template #acciones>
      <button class="btn text" type="button" :disabled="guardando" @click="emit('cancelar')">Cancelar</button>
      <button class="btn" type="submit" form="form-usuario" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar cambios' }}</button>
    </template>
  </Dialog>
</template>
