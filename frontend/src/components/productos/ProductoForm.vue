<script setup>
import { reactive, ref, watch } from 'vue'
import Dialog from '../comunes/Dialog.vue'

const props = defineProps({ producto: { type: Object, default: null }, proveedores: { type: Array, required: true }, guardando: Boolean, errorServidor: String })
const emit = defineEmits(['guardar', 'cancelar'])
const formulario = reactive({ codigo: '', nombre: '', descripcion: '', categoria: '', proveedor_id: '', precio_costo: '', precio_venta: '', stock_minimo: 5, activo: true })
const errorLocal = ref('')
watch(() => props.producto, valor => {
  for (const campo of Object.keys(formulario)) formulario[campo] = valor?.[campo] ?? ({ stock_minimo: 5, activo: true }[campo] ?? '')
  errorLocal.value = ''
}, { immediate: true })
function enviar() {
  errorLocal.value = ''
  if (!formulario.codigo.trim() || !formulario.nombre.trim()) errorLocal.value = 'Complete el código y el nombre del producto'
  else if (['precio_costo', 'precio_venta'].some(campo => !/^\d+(\.\d{1,2})?$/.test(String(formulario[campo])) || Number(formulario[campo]) > 99999999.99)) errorLocal.value = 'Los precios deben ser positivos o cero y tener como máximo dos decimales'
  else if (formulario.stock_minimo === '' || !Number.isInteger(Number(formulario.stock_minimo)) || Number(formulario.stock_minimo) < 0 || Number(formulario.stock_minimo) > 100000000) errorLocal.value = 'El stock mínimo debe ser un número entero entre 0 y 100000000'
  if (!errorLocal.value && !props.guardando) emit('guardar', { ...formulario, codigo: formulario.codigo.trim(), nombre: formulario.nombre.trim(), proveedor_id: formulario.proveedor_id || null, stock_minimo: Number(formulario.stock_minimo) })
}
</script>
<template>
  <Dialog :titulo="producto ? 'Editar producto' : 'Nuevo producto'" ancho="md" @cerrar="!guardando && emit('cancelar')">
    <form id="form-producto" class="grid2" novalidate @submit.prevent="enviar">
      <label class="field"><input v-model="formulario.codigo" placeholder=" " maxlength="40" :disabled="guardando" autofocus /><span>Código / código de barras *</span></label>
      <label class="field"><input v-model="formulario.nombre" placeholder=" " maxlength="150" :disabled="guardando" /><span>Nombre del producto *</span></label>
      <label class="field"><input v-model="formulario.categoria" placeholder=" " maxlength="80" :disabled="guardando" /><span>Categoría</span></label>
      <label class="field"><select v-model="formulario.proveedor_id" :disabled="guardando"><option value="">Sin proveedor</option><option v-for="proveedor in proveedores" :key="proveedor._id" :value="proveedor._id">{{ proveedor.nombre }}{{ proveedor.activo ? '' : ' (inactivo)' }}</option></select><span>Proveedor</span><small v-if="!proveedores.length">Aún no hay proveedores registrados</small></label>
      <label class="field"><input v-model="formulario.precio_costo" placeholder=" " type="number" min="0" max="99999999.99" step="0.01" :disabled="guardando" /><span>Precio de costo (Q) *</span></label>
      <label class="field"><input v-model="formulario.precio_venta" placeholder=" " type="number" min="0" max="99999999.99" step="0.01" :disabled="guardando" /><span>Precio de venta (Q) *</span></label>
      <label class="field"><input v-model="formulario.stock_minimo" placeholder=" " type="number" min="0" max="100000000" step="1" :disabled="guardando" /><span>Stock mínimo</span></label>
      <label class="switch"><input v-model="formulario.activo" type="checkbox" :disabled="guardando" /><span>Producto activo</span></label>
      <label class="field full"><textarea v-model="formulario.descripcion" placeholder=" " maxlength="500" :disabled="guardando" /><span>Descripción</span></label>
    </form>
    <p v-if="errorLocal || errorServidor" class="form-error" role="alert">{{ errorLocal || errorServidor }}</p>
    <template #acciones>
      <button class="btn text" type="button" :disabled="guardando" @click="emit('cancelar')">Cancelar</button>
      <button class="btn" type="submit" form="form-producto" :disabled="guardando">{{ guardando ? 'Guardando…' : 'Guardar cambios' }}</button>
    </template>
  </Dialog>
</template>
