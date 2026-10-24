<script setup>
defineProps({ icono: { type: String, default: 'inventory_2' }, titulo: String, texto: String })
</script>
<template>
  <div class="empty"><div class="blob"><span class="icon">{{ icono }}</span></div>
    <h3 class="title">{{ titulo }}</h3><p style="margin:0;max-width:34ch">{{ texto }}</p><slot /></div>
</template>