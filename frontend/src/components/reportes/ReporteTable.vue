<script setup>
import { celdaReporte } from '../../utils/reportes'
defineProps({ columnas: Array, filas: Array })
</script>
<template>
  <div class="tabla-scroll"><table class="tabla-operaciones tabla-reporte"><thead><tr><th v-for="c in columnas" :key="c.key" scope="col">{{ c.label }}</th></tr></thead><tbody>
    <tr v-for="(fila,i) in filas" :key="i"><td v-for="c in columnas" :key="c.key">{{ celdaReporte(fila[c.key],c.tipo) }}</td></tr>
    <tr v-if="!filas.length"><td :colspan="columnas.length">No hay resultados con estos filtros.</td></tr>
  </tbody></table></div>
</template>
