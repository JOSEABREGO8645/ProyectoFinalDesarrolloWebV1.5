<script setup>
import OperacionesPanel from '../components/operaciones/OperacionesPanel.vue'
import { ingresosService } from '../services/ingresosService'
</script>
<template><OperacionesPanel tipo="ingresos" :servicio="ingresosService" /></template>
