<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { generarPrueba } from '../../services/integracionesService'
import { mensajeError } from '../../services/api'
import { fecha } from '../../utils/format'

const props = defineProps({ tipo: String, documento: Object })
const emit = defineEmits(['actualizar'])
const campo = computed(() => props.tipo === 'ventas' ? 'facturacion' : 'pagos')
const registro = computed(() => local.value || props.documento.integraciones?.[campo.value])
const local = ref(null), cargando = ref(false), error = ref('')
const vigente = computed(() => props.documento.estado === (props.tipo === 'ventas' ? 'emitida' : 'registrado'))
let activo = true
watch(() => props.documento._id, () => { local.value = null; error.value = '' })
async function generar() {
  if (cargando.value) return
  cargando.value = true; error.value = ''
  const id = props.documento._id
  try {
    const respuesta = await generarPrueba(props.tipo, id)
    if (activo && id === props.documento._id) { local.value = respuesta; emit('actualizar') }
  } catch (e) {
    if (activo && id === props.documento._id) { error.value = e.response?.data?.message || mensajeError(e); emit('actualizar') }
  } finally { if (activo) cargando.value = false }
}
onUnmounted(() => { activo = false })
</script>
<template>
  <section class="integracion-box stack" aria-label="Servicio externo de prueba" :aria-busy="cargando">
    <h4>{{ tipo === 'ventas' ? 'Factura de prueba' : 'Comprobante de pago de prueba' }}</h4>
    <p class="muted">{{ tipo === 'ventas' ? 'Simulación académica sin valor fiscal. No sustituye una factura FEL.' : 'Simulación académica sin movimiento bancario. No realiza pagos al proveedor.' }}</p>
    <p v-if="registro?.estado === 'completada'"><strong>{{ registro.referencia }}</strong><br />{{ fecha(registro.emitido_at) }} · {{ tipo === 'ventas' ? 'SIN VALOR FISCAL' : 'SIN MOVIMIENTO BANCARIO' }}</p>
    <template v-else>
      <p v-if="error || registro?.ultimo_error" class="form-error no-print" role="alert">{{ error || registro.ultimo_error }}</p>
      <button v-if="vigente" type="button" class="btn tonal no-print" :disabled="cargando" @click="generar">{{ cargando ? 'Consultando servicio…' : tipo === 'ventas' ? 'Generar factura de prueba' : 'Generar pago de prueba' }}</button>
    </template>
  </section>
</template>
