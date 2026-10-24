<script setup>
import SucursalesView from './views/SucursalesView.vue'
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
      </header>
      <SucursalesView class="page" />
    </main>
  </div>
</template>