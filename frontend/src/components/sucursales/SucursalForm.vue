<script setup>
import { reactive, ref, watch } from 'vue'
import Dialog from '../comunes/Dialog.vue'

const props = defineProps({
  sucursal: { type: Object, default: null },
  guardando: { type: Boolean, default: false },
  errorServidor: { type: String, default: '' }
})
const emit = defineEmits(['guardar', 'cancelar'])

const formulario = reactive({ nombre: '', direccion: '', telefono: '', activo: true })
const errorLocal = ref('')

watch(() => props.sucursal, (valor) => {
  Object.assign(formulario, {
    nombre: valor?.nombre ?? '',
    direccion: valor?.direccion ?? '',
    telefono: valor?.telefono ?? '',
    activo: valor?.activo ?? true
  })
  errorLocal.value = ''
}, { immediate: true })

const enviar = () => {
  errorLocal.value = ''
  if (!formulario.nombre.trim()) {
    errorLocal.value = 'Complete «Nombre de la sucursal»'
    return
  }
  if (formulario.telefono && !/^[0-9+\-\s()]+$/.test(formulario.telefono)) {
    errorLocal.value = 'El teléfono solo puede contener números, espacios, paréntesis, + y guiones'
    return
  }
  emit('guardar', { ...formulario, nombre: formulario.nombre.trim() })
}
</script>

<template>
  <Dialog :titulo="sucursal ? 'Editar sucursal' : 'Nueva sucursal'" ancho="md" @cerrar="emit('cancelar')">
    <form id="form-sucursal" class="grid2" novalidate @submit.prevent="enviar">
      <label class="field full">
        <input v-model="formulario.nombre" placeholder=" " maxlength="120" autofocus />
        <span>Nombre de la sucursal *</span>
      </label>
      <label class="field full">
        <input v-model="formulario.direccion" placeholder=" " maxlength="250" />
        <span>Dirección</span>
      </label>
      <label class="field">
        <input v-model="formulario.telefono" placeholder=" " maxlength="25" inputmode="tel" />
        <span>Teléfono</span>
      </label>
      <label class="switch">
        <input v-model="formulario.activo" type="checkbox" />
        <span>Sucursal activa</span>
      </label>
    </form>

    <p v-if="errorLocal || errorServidor" class="chip bad" style="height:auto;padding:8px 12px;margin-top:12px;white-space:normal">
      {{ errorLocal || errorServidor }}
    </p>

    <template #acciones>
      <button class="btn text" type="button" @click="emit('cancelar')">Cancelar</button>
      <button class="btn" type="submit" form="form-sucursal" :disabled="guardando">
        {{ guardando ? 'Guardando…' : 'Guardar' }}
      </button>
    </template>
  </Dialog>
</template>