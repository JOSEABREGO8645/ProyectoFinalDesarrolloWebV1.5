<script setup>
import { Q, forma } from '../../utils/format'
defineProps({ productos: { type: Array, required: true } })
defineEmits(['editar', 'eliminar'])
</script>
<template>
  <div class="list">
    <div v-for="(producto, i) in productos" :key="producto._id" class="li" :style="{ '--i': Math.min(i, 14) }">
      <div class="avatar" :style="{ borderRadius: forma(i) }">{{ producto.nombre.charAt(0).toUpperCase() }}</div>
      <div class="grow">
        <div class="t1">{{ producto.nombre }}</div>
        <div class="t2">{{ [producto.codigo, Q(producto.precio_venta), producto.categoria, producto.proveedor_nombre].filter(Boolean).join(' · ') }}</div>
      </div>
      <span v-if="!producto.activo" class="chip bad">Inactivo</span>
      <span v-else class="chip" :class="producto.existencia <= 0 ? 'bad' : producto.existencia <= producto.stock_minimo ? 'warn' : 'ok'">{{ producto.existencia }} {{ producto.existencia <= 0 ? 'agotado' : producto.existencia <= producto.stock_minimo ? 'bajo' : 'disp.' }}</span>
      <div class="acts">
        <button class="icon-btn" aria-label="Editar producto" @click="$emit('editar', producto)"><span class="icon">edit</span></button>
        <button class="icon-btn danger" aria-label="Eliminar producto" @click="$emit('eliminar', producto)"><span class="icon">delete</span></button>
      </div>
    </div>
  </div>
</template>
