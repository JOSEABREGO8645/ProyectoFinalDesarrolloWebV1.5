# Memoria del proyecto — Librería San Francisco
(Solo decisiones, archivos y pruebas verificadas.)

## Decisiones
- Stack 3: MongoDB 8.2 (Docker, puerto 27018) + Laravel + Vue 3 / Vite / Axios.
- Arquitectura backend: Model → Repository → Service → Controller + FormRequest.
- Referencias entre colecciones como STRING con el _id del documento.
- Sin Vue Router: navegación por hash en App.vue.

## Hecho y verificado
- Fase 1: estructura y docker-compose (mongodb_libreria, puerto 27018) verificados con docker ps y Compass.
- Fase 2: Laravel + mongodb/laravel-mongodb conectados; GET /api/ping responde ok.
- Fase 3: API de Sucursales (CRUD + /buscar, nombre único, bloqueo de eliminación con historial) probada en Postman y Compass.