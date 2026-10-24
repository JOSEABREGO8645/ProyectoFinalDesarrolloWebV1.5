<script setup>
import SucursalesView from './views/SucursalesView.vue'
import ToastHost from './components/comunes/ToastHost.vue'
import ConfirmHost from './components/comunes/ConfirmHost.vue'
import { ui, alternarTema } from './stores/ui'
</script>

<template>
  <div class="app">
    <nav class="rail" aria-label="Módulos">
      <div class="logo" title="Librería San Francisco"><span class="icon fill" style="font-size:28px">auto_stories</span></div>
      <a href="#sucursales" class="nav-item on"><span class="pill"><span class="icon">store</span></span>Sucursales</a>
    </nav>

    <main class="main">
      <header class="topbar">
        <h1 class="headline grow">Sucursales</h1>
        <button class="icon-btn" title="Cambiar tema" @click="alternarTema">
          <span class="icon">{{ ui.tema === 'dark' ? 'light_mode' : 'dark_mode' }}</span>
        </button>
      </header>
      <SucursalesView class="page" />
    </main>
  </div>
  <ToastHost />
  <ConfirmHost />
</template>