<script setup>
import { computed } from 'vue'
import { ui } from '../../stores/ui'
import Dialog from './Dialog.vue'

const c = computed(() => ui.confirm)
const valido = computed(() => !c.value?.pedirTexto || c.value.texto.trim().length >= 3)
function cerrar(valor) {
  const r = ui.confirm.resolver
  ui.confirm = null
  r(valor)
}
const aceptar = () => valido.value && cerrar(c.value.pedirTexto ? c.value.texto.trim() : true)
</script>
<template>
  <Dialog v-if="c" :titulo="c.titulo" ancho="sm" @cerrar="cerrar(false)">
    <p v-if="c.mensaje" class="muted" style="margin-top:0">{{ c.mensaje }}</p>
    <label v-if="c.pedirTexto" class="field">
      <textarea v-model="c.texto" placeholder=" " autofocus @keydown.ctrl.enter="aceptar" />
      <span>{{ c.pedirTexto }}</span><small>Mínimo 3 caracteres</small>
    </label>
    <template #acciones>
      <button class="btn text" @click="cerrar(false)">Cancelar</button>
      <button class="btn" :class="{ danger: c.peligro }" :disabled="!valido" @click="aceptar">{{ c.aceptar }}</button>
    </template>
  </Dialog>
</template>