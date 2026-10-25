<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import Dialog from '../comunes/Dialog.vue'
import TipoCambio from '../integraciones/TipoCambio.vue'
import { centavos } from '../../utils/caja'
import { Q } from '../../utils/format'
const props = defineProps({ total: Number, cliente: Object, guardando: Boolean, incierto: Boolean, pedirCodigo: Boolean, efectivoInicial: String, error: String })
const emit = defineEmits(['guardar', 'cerrar', 'historial', 'verificar'])
const paso = ref(props.incierto ? 'codigo' : 'efectivo'), efectivo = ref(props.efectivoInicial || ''), codigo = ref(''), errorLocal = ref(''), pin = ref(null)
const importe = computed(() => centavos(efectivo.value))
const cambio = computed(() => importe.value === null ? 0 : Math.max(0, importe.value - props.total))
watch(() => props.error, () => { if (!props.incierto) codigo.value = '' })
async function continuar() {
  errorLocal.value = ''
  if (importe.value === null || importe.value < props.total) { errorLocal.value = 'El efectivo recibido debe cubrir el total de la venta.'; return }
  paso.value = 'codigo'; await nextTick(); pin.value?.focus()
}
function confirmar() {
  errorLocal.value = ''
  if (props.guardando) return
  if ((!props.incierto || props.pedirCodigo) && !/^[0-9]{4}$/.test(codigo.value)) { errorLocal.value = 'Ingrese su código de facturación de 4 dígitos.'; return }
  emit('guardar', { efectivo: efectivo.value, codigo: codigo.value })
}
function cerrar() { if (!props.guardando && !props.incierto) emit('cerrar') }
</script>
<template>
  <Dialog :titulo="paso === 'efectivo' ? 'Completar cobro' : 'Autorizar venta'" ancho="md" @cerrar="cerrar">
    <div class="stack">
      <div class="pos-cobro-total"><small>Total a facturar</small><strong>{{ Q(total / 100) }}</strong><span>{{ cliente ? cliente.nombre + ' · ' + cliente.nit : 'Consumidor Final · CF' }}</span></div>
      <form v-if="paso === 'efectivo'" id="form-cobro" class="stack" @submit.prevent="continuar">
        <label class="field"><input v-model="efectivo" inputmode="decimal" type="text" maxlength="11" placeholder=" " autofocus autocomplete="off" aria-label="Efectivo recibido" /><span>Efectivo recibido *</span></label>
        <div class="total-box"><span>Cambio</span><strong>{{ Q(cambio / 100) }}</strong></div>
        <TipoCambio />
      </form>
      <form v-else id="form-autorizar" class="stack" @submit.prevent="confirmar">
        <p>Efectivo: {{ Q((importe || 0) / 100) }} · Cambio: {{ Q(cambio / 100) }}</p>
        <p v-if="incierto" class="form-error" role="alert">La respuesta no se confirmó. Reintente esta misma facturación o verifique si ya está registrada.</p>
        <label v-if="!incierto || pedirCodigo" class="field"><input ref="pin" v-model="codigo" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder=" " autocomplete="off" :disabled="guardando" aria-label="Código de facturación" /><span>Código de facturación (4 dígitos) *</span></label>
        <button v-if="incierto" type="button" class="btn text" :disabled="guardando" @click="emit('verificar')">Verificar venta registrada</button>
      </form>
      <p v-if="errorLocal || error" class="form-error" role="alert">{{ errorLocal || error }}</p>
    </div>
    <template #acciones>
      <template v-if="paso === 'efectivo'"><button class="btn text" @click="cerrar">Volver al detalle</button><button class="btn" form="form-cobro" type="submit">Confirmar cobro</button></template>
      <template v-else><button v-if="!incierto" class="btn text" :disabled="guardando" @click="paso = 'efectivo'; errorLocal = ''">Volver al cobro</button><button v-else class="btn text" :disabled="guardando" @click="emit('historial')">Revisar historial</button><button class="btn" form="form-autorizar" type="submit" :disabled="guardando">{{ guardando ? 'Registrando…' : incierto ? 'Reintentar facturación' : 'Facturar venta' }}</button></template>
    </template>
  </Dialog>
</template>
