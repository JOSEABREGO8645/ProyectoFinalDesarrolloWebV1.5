<script setup>
import OperacionesPanel from '../components/operaciones/OperacionesPanel.vue'
import { ventasService } from '../services/ventasService'
import { nextTick, ref } from 'vue'
import VentaCaja from '../components/ventas/VentaCaja.vue'
const historial = ref(false), ultima = ref(null), caja = ref(null)
async function volver() { historial.value = false; await nextTick(); caja.value?.enfocar() }
</script>
<template><div class="stack"><div v-if="historial" class="row"><button class="btn tonal" @click="volver"><span class="icon">point_of_sale</span>Volver a la caja</button></div><VentaCaja ref="caja" v-show="!historial" @venta="ultima = $event" @historial="historial = true" /><OperacionesPanel v-show="historial" tipo="ventas" :servicio="ventasService" solo-consulta :documento-inicial="ultima" @detalle-cerrado="!historial && caja?.enfocar()" /></div></template>
