<script setup>
import { Q, fecha, pad } from '../../utils/format'
defineProps({ documentos: Array, tipo: String })
defineEmits(['ver'])
</script>
<template>
  <div class="list">
    <div v-for="(d, i) in documentos" :key="d._id" class="li" :data-documento="d._id" :style="{ '--i': Math.min(i, 14) }">
      <div class="avatar"><span class="icon">{{ tipo === 'ventas' ? 'receipt_long' : tipo === 'ingresos' ? 'inventory_2' : 'local_shipping' }}</span></div>
      <div class="grow"><strong>{{ tipo === 'ventas' ? 'Venta' : tipo === 'ingresos' ? 'Ingreso' : 'Traslado' }} #{{ pad(d.numero) }}</strong><div class="t2">{{ tipo === 'traslados' ? `${d.origen_nombre} → ${d.destino_nombre}` : `${d.sucursal_nombre} · ${d.proveedor_nombre || d.cliente_nombre}` }}</div><div class="t2">{{ fecha(d.created_at) }} · {{ d.usuario_nombre }}</div></div>
      <strong v-if="tipo !== 'traslados'">{{ Q(d.total_centavos / 100) }}</strong>
      <span class="chip" :class="d.estado === 'anulado' ? 'bad' : d.estado === 'enviado' ? 'warn' : 'ok'">{{ d.estado }}</span>
      <button class="icon-btn" :aria-label="`Ver documento ${d.numero}`" @click="$emit('ver', d._id)"><span class="icon">visibility</span></button>
    </div>
  </div>
</template>
