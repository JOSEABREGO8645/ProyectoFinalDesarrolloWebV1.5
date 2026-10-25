<script setup>
import { onUnmounted, ref } from 'vue'
import { consultarCambio } from '../../services/integracionesService'
import { mensajeError } from '../../services/api'

const monto = ref(''), resultado = ref(null), cargando = ref(false), error = ref('')
let activo = true
async function consultar() {
  if (cargando.value) return
  error.value = ''; resultado.value = null
  if (monto.value !== '' && !/^\d{1,8}(\.\d{1,2})?$/.test(String(monto.value))) { error.value = 'Ingrese un importe en USD con hasta dos decimales.'; return }
  cargando.value = true
  try { const dato = await consultarCambio(monto.value); if (activo) resultado.value = dato }
  catch (e) { if (activo) error.value = e.response?.data?.message || mensajeError(e) }
  finally { if (activo) cargando.value = false }
}
onUnmounted(() => { activo = false })
</script>
<template>
  <details class="integracion-box no-print">
    <summary>Consultar tipo de cambio USD / GTQ</summary>
    <div class="stack" style="margin-top:16px" :aria-busy="cargando">
      <p class="muted">Referencia del Banco de Guatemala. El documento se registra en quetzales.</p>
      <label class="field"><input v-model="monto" type="text" inputmode="decimal" maxlength="11" placeholder=" " :disabled="cargando" aria-label="Importe de consulta en USD" /><span>USD para convertir (opcional)</span></label>
      <button type="button" class="btn text" :disabled="cargando" @click="consultar">{{ cargando ? 'Consultando…' : 'Consultar Banco de Guatemala' }}</button>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <p v-if="resultado" role="status">1 USD = Q {{ resultado.referencia }} · Referencia: {{ resultado.fecha }}<br /><span v-if="resultado.conversion">USD {{ resultado.conversion.usd }} = Q {{ resultado.conversion.gtq }}</span></p>
    </div>
  </details>
</template>
