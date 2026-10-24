<script setup>
import { forma } from '../../utils/format'
defineProps({ proveedores: { type: Array, required: true } })
defineEmits(['editar', 'eliminar'])
</script>
<template>
  <div class="list">
    <div v-for="(proveedor, i) in proveedores" :key="proveedor._id" class="li" :style="{ '--i': Math.min(i, 14) }">
      <div class="avatar" :style="{ borderRadius: forma(i) }">{{ proveedor.nombre.charAt(0).toUpperCase() }}</div>
      <div class="grow">
        <div class="t1">{{ proveedor.nombre }}</div>
        <div class="t2">{{ [proveedor.nit && `NIT ${proveedor.nit}`, proveedor.contacto, proveedor.telefono, proveedor.correo, proveedor.direccion].filter(Boolean).join(' · ') || 'Sin datos de contacto' }}</div>
      </div>
      <span v-if="!proveedor.activo" class="chip bad">Inactivo</span>
      <div class="acts">
        <button class="icon-btn" aria-label="Editar proveedor" @click="$emit('editar', proveedor)"><span class="icon">edit</span></button>
        <button class="icon-btn danger" aria-label="Eliminar proveedor" @click="$emit('eliminar', proveedor)"><span class="icon">delete</span></button>
      </div>
    </div>
  </div>
</template>
