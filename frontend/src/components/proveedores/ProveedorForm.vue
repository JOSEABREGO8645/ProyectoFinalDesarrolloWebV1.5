<script setup>
import { reactive, ref, watch } from 'vue'
import Dialog from '../comunes/Dialog.vue'
import { validarContacto } from '../../utils/contacto'

const props = defineProps({ proveedor: { type: Object, default: null }, guardando: Boolean, errorServidor: String })
const emit = defineEmits(['guardar', 'cancelar'])
const formulario = reactive({ nombre: '', nit: '', contacto: '', telefono: '', correo: '', direccion: '', activo: true })
const errorLocal = ref('')
watch(() => props.proveedor, valor => {
  for (const campo of ['nombre', 'nit', 'contacto', 'telefono', 'correo', 'direccion']) formulario[campo] = valor?.[campo] ?? ''
  formulario.activo = valor?.activo ?? true
  errorLocal.value = ''
}, { immediate: true })
function enviar() {
  errorLocal.value = validarContacto(formulario)
  if (!errorLocal.value && !props.guardando) emit('guardar', { ...formulario, nombre: formulario.nombre.trim() })
}
</script>

<template>
  <Dialog :titulo="proveedor ? 'Editar proveedor' : 'Nuevo proveedor'" ancho="md" @cerrar="!guardando && emit('cancelar')">
    <form id="form-proveedor" class="grid2" novalidate @submit.prevent="enviar">
      <label class="field full"><input v-model="formulario.nombre" placeholder=" " maxlength="150" :disabled="guardando" autofocus /><span>Nombre o razón social *</span></label>
      <label class="field"><input v-model="formulario.nit" placeholder=" " maxlength="20" :disabled="guardando" /><span>NIT</span></label>
      <label class="field"><input v-model="formulario.contacto" placeholder=" " maxlength="120" :disabled="guardando" /><span>Persona de contacto</span></label>
      <label class="field"><input v-model="formulario.telefono" placeholder=" " maxlength="25" inputmode="tel" :disabled="guardando" /><span>Teléfono</span></label>
      <label class="field"><input v-model="formulario.correo" placeholder=" " maxlength="150" type="email" :disabled="guardando" /><span>Correo</span></label>
      <label class="field full"><input v-model="formulario.direccion" placeholder=" " maxlength="250" :disabled="guardando" /><span>Dirección</span></label>
      <label class="switch"><input v-model="formulario.activo" type="checkbox" :disabled="guardando" /><span>Proveedor activo</span></label>
    </form>
    <p v-if="errorLocal || errorServidor" class="form-error" role="alert">{{ errorLocal || errorServidor }}</p>
    <template #acciones>
      <button class="btn text" type="button" :disabled="guardando" @click="emit('cancelar')">Cancelar</button>
      <button class="btn" type="submit" form="form-proveedor" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar cambios' }}</button>
    </template>
  </Dialog>
</template>
