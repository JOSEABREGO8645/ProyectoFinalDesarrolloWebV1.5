<script setup>
import { forma } from '../../utils/format'
defineProps({ clientes: { type: Array, required: true } })
defineEmits(['editar', 'eliminar'])
</script>
<template>
  <div class="list">
    <div v-for="(cliente, i) in clientes" :key="cliente._id" class="li" :style="{ '--i': Math.min(i, 14) }">
      <div class="avatar" :style="{ borderRadius: forma(i) }">{{ cliente.nombre.charAt(0).toUpperCase() }}</div>
      <div class="grow">
        <div class="t1">{{ cliente.nombre }}</div>
        <div class="t2">{{ [`NIT ${cliente.nit}`, cliente.telefono, cliente.correo, cliente.direccion].filter(Boolean).join(' · ') }}</div>
      </div>
      <div class="acts">
        <button class="icon-btn" aria-label="Editar cliente" @click="$emit('editar', cliente)"><span class="icon">edit</span></button>
        <button class="icon-btn danger" aria-label="Eliminar cliente" @click="$emit('eliminar', cliente)"><span class="icon">delete</span></button>
      </div>
    </div>
  </div>
</template>
