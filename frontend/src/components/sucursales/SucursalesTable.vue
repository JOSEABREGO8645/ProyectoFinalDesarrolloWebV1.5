<script setup>
import { forma } from '../../utils/format'

defineProps({
  sucursales: { type: Array, required: true }
})
defineEmits(['editar', 'eliminar'])
</script>

<template>
  <div class="list">
    <div v-for="(sucursal, i) in sucursales" :key="sucursal._id" class="li" :style="{ '--i': Math.min(i, 14) }">
      <div class="avatar" :style="{ borderRadius: forma(i) }">{{ sucursal.nombre.charAt(0).toUpperCase() }}</div>
      <div class="grow">
        <div class="t1">{{ sucursal.nombre }}</div>
        <div class="t2">{{ [sucursal.direccion, sucursal.telefono].filter(Boolean).join(' · ') || 'Sin dirección' }}</div>
      </div>
      <span v-if="!sucursal.activo" class="chip bad">Inactiva</span>
      <div class="acts">
        <button class="icon-btn" aria-label="Editar" @click="$emit('editar', sucursal)"><span class="icon">edit</span></button>
        <button class="icon-btn danger" aria-label="Eliminar" @click="$emit('eliminar', sucursal)"><span class="icon">delete</span></button>
      </div>
    </div>
  </div>
</template>