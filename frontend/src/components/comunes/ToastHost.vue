<script setup>
import { ui } from '../../stores/ui'
</script>
<template>
  <div v-if="ui.toast" :key="ui.toast.id" class="toast" :class="ui.toast.tipo" role="status">
    <span class="icon fill">{{ ui.toast.tipo === 'ok' ? 'check_circle' : ui.toast.tipo === 'error' ? 'error' : 'info' }}</span>{{ ui.toast.msg }}
  </div>
</template>