<script setup>
import { computed, ref } from 'vue'
import { Q } from '../../utils/format'
const props = defineProps({ dias: Array })
const seleccion = ref(null)
const seleccionado = computed(() => props.dias.find(d => d.fecha === seleccion.value))
const maximo = computed(() => Math.max(1, ...props.dias.map(d => d.centavos)))
const corta = f => new Date(`${f}T12:00:00`).toLocaleDateString('es-GT', { day: 'numeric', month: 'short' })
</script>
<template>
  <section class="card stack" aria-label="Ventas por día">
    <h2>Ventas por día</h2><p class="muted">Ventas emitidas; los documentos anulados se excluyen. Seleccione un día para ver su total.</p>
    <p v-if="!dias.some(d => d.centavos || d.documentos)" class="muted">No hay ventas emitidas en este período.</p>
    <div v-else class="grafica-scroll"><div class="grafica-ventas" :style="{ gridTemplateColumns: `repeat(${dias.length}, minmax(28px, 1fr))` }">
      <button v-for="dia in dias" :key="dia.fecha" class="dia-ventas" :class="{ seleccionado: seleccionado?.fecha === dia.fecha }" :aria-label="`${corta(dia.fecha)}: ${Q(dia.centavos / 100)}, ${dia.documentos} ventas`" :title="`${corta(dia.fecha)} · ${Q(dia.centavos / 100)}`" @click="seleccion = dia.fecha">
        <span class="barra-ventas" :style="{ height: `${Math.max(2, dia.centavos / maximo * 140)}px` }" /><small>{{ corta(dia.fecha) }}</small>
      </button>
    </div></div>
    <p v-if="seleccionado" role="status">{{ corta(seleccionado.fecha) }}: <strong>{{ Q(seleccionado.centavos / 100) }}</strong> · {{ seleccionado.documentos }} ventas</p>
  </section>
</template>
