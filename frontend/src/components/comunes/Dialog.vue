<script setup>
import { onMounted, onUnmounted } from 'vue'
defineProps({ titulo: String, ancho: { type: String, default: '' } })
const emit = defineEmits(['cerrar'])
const tecla = e => e.key === 'Escape' && emit('cerrar')
onMounted(() => window.addEventListener('keydown', tecla))
onUnmounted(() => window.removeEventListener('keydown', tecla))
</script>
<template>
  <div class="scrim" @mousedown.self="emit('cerrar')">
    <section class="dialog" :class="ancho" role="dialog" aria-modal="true" :aria-label="titulo">
      <header>
        <h2>{{ titulo }}</h2>
        <button class="icon-btn" type="button" @click="emit('cerrar')" aria-label="Cerrar"><span class="icon">close</span></button>
      </header>
      <div class="body"><slot /></div>
      <footer v-if="$slots.acciones"><slot name="acciones" /></footer>
    </section>
  </div>
</template>