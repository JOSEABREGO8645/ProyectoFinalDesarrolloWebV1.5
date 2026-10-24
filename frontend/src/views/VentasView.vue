<script setup>
import OperacionesPanel from '../components/operaciones/OperacionesPanel.vue'
import { ventasService } from '../services/ventasService'
</script>
<template><OperacionesPanel tipo="ventas" :servicio="ventasService" /></template>
