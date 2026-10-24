<script setup>
import { reactive, ref, watch } from 'vue'
import Dialog from '../comunes/Dialog.vue'
import { limpiarNit, validarContacto } from '../../utils/contacto'

const props = defineProps({ cliente: { type: Object, default: null }, guardando: Boolean, errorServidor: String })
const emit = defineEmits(['guardar', 'cancelar'])
const formulario = reactive({ nombre: '', nit: '', telefono: '', correo: '', direccion: '' })
const errorLocal = ref('')
watch(() => props.cliente, valor => {
  for (const campo of Object.keys(formulario)) formulario[campo] = valor?.[campo] ?? ''
  errorLocal.value = ''
}, { immediate: true })
function enviar() {
  errorLocal.value = validarContacto(formulario)
  const nit = limpiarNit(formulario.nit)
  if (!errorLocal.value && !nit) errorLocal.value = 'Complete el NIT; use CF para consumidor final'
  if (!errorLocal.value && !props.guardando) emit('guardar', { ...formulario, nombre: formulario.nombre.trim(), nit })
}
</script>

<template>
  <Dialog :titulo="cliente ? 'Editar cliente' : 'Nuevo cliente'" ancho="md" @cerrar="!guardando && emit('cancelar')">
    <form id="form-cliente" class="grid2" novalidate @submit.prevent="enviar">
      <label class="field full"><input v-model="formulario.nombre" placeholder=" " maxlength="150" :disabled="guardando" autofocus /><span>Nombre completo *</span></label>
      <label class="field"><input v-model="formulario.nit" placeholder=" " maxlength="20" :disabled="guardando" /><span>NIT *</span><small>Use CF para consumidor final</small></label>
      <label class="field"><input v-model="formulario.telefono" placeholder=" " maxlength="25" inputmode="tel" :disabled="guardando" /><span>Teléfono</span></label>
      <label class="field"><input v-model="formulario.correo" placeholder=" " maxlength="150" type="email" :disabled="guardando" /><span>Correo</span></label>
      <label class="field"><input v-model="formulario.direccion" placeholder=" " maxlength="250" :disabled="guardando" /><span>Dirección</span></label>
    </form>
    <p v-if="errorLocal || errorServidor" class="form-error" role="alert">{{ errorLocal || errorServidor }}</p>
    <template #acciones>
      <button class="btn text" type="button" :disabled="guardando" @click="emit('cancelar')">Cancelar</button>
      <button class="btn" type="submit" form="form-cliente" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar cambios' }}</button>
    </template>
  </Dialog>
</template>
