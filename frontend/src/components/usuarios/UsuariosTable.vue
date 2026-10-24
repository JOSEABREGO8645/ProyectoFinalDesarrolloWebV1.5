<script setup>
import { forma } from '../../utils/format'
defineProps({ usuarios: { type: Array, required: true }, propioId: String })
defineEmits(['editar', 'eliminar'])
</script>
<template>
  <div class="list">
    <div v-for="(usuario, i) in usuarios" :key="usuario._id" class="li" :style="{ '--i': Math.min(i, 14) }">
      <div class="avatar" :style="{ borderRadius: forma(i) }">{{ usuario.nombre.charAt(0).toUpperCase() }}</div>
      <div class="grow"><div class="t1">{{ usuario.nombre }}</div><div class="t2">@{{ usuario.usuario }} · {{ usuario.sucursal_nombre }} · {{ usuario.rol === 'superusuario' ? 'Todos los módulos' : `${usuario.modulos.length} módulos` }}{{ usuario.tiene_codigo ? '' : ' · sin código de facturación' }}</div></div>
      <span v-if="!usuario.activo" class="chip bad">Inactivo</span><span v-else-if="usuario.rol === 'superusuario'" class="chip info">Superusuario</span>
      <div class="acts"><button class="icon-btn" aria-label="Editar usuario" @click="$emit('editar', usuario)"><span class="icon">edit</span></button><button class="icon-btn danger" aria-label="Eliminar usuario" :disabled="usuario._id === propioId" @click="$emit('eliminar', usuario)"><span class="icon">delete</span></button></div>
    </div>
  </div>
</template>
