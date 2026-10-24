<script setup>
import { session, puede } from '../stores/session'
import { MODULOS_ASIGNABLES } from '../config/modulos'
</script>
<template>
  <section class="bienvenida card">
    <h2>Bienvenido a Librería San Francisco</h2>
    <div class="bienvenida-persona"><span class="icon fill">person</span></div>
    <p class="bienvenida-nombre">{{ session.usuario.nombre }}</p>
    <p class="muted">Ha iniciado sesión como {{ session.usuario.rol === 'superusuario' ? 'superusuario' : 'empleado' }}.</p>
    <div class="bienvenida-marca"><span class="icon fill">auto_stories</span><strong>Librería<br />San Francisco</strong></div>
    <p class="bienvenida-sucursal">Sucursal<br /><strong>{{ session.usuario.sucursal_nombre }}</strong></p>
    <div class="row wrap bienvenida-accesos"><a v-if="puede('ventas')" class="btn" href="#ventas"><span class="icon">point_of_sale</span>Ir a Ventas</a><a v-if="puede('dashboard')" class="btn tonal" href="#dashboard">Ver dashboard</a></div>
    <p v-if="!MODULOS_ASIGNABLES.some(m => puede(m.id)) && !puede('usuarios')" class="muted">No tiene módulos asignados. Solicite acceso al administrador.</p>
    <p v-else class="muted">Seleccione una opción de la barra lateral para comenzar.</p>
  </section>
</template>
