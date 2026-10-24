<script setup>
import OperacionesPanel from '../components/operaciones/OperacionesPanel.vue'
import { trasladosService } from '../services/trasladosService'
</script>
<template><OperacionesPanel tipo="traslados" :servicio="trasladosService" /></template>
