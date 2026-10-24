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
- Fases 4 y 5: frontend Vue conectado a la API y CRUD de Sucursales implementado con formulario en diálogo, búsqueda, filtro de estado, ordenamiento, confirmación de eliminación, notificaciones y cambio de tema. El usuario confirmó que ya funciona después de corregir la conexión.
- Corrección de conexión: frontend/.env tenía vacía VITE_API_URL; se configuró http://127.0.0.1:8000/api. Se eliminaron definiciones duplicadas en backend/.env para usar MongoDB, sesiones y caché en archivos y cola sync. Los archivos .env siguen excluidos de Git.
- Verificación técnica: php artisan config:clear y route:list --path=api ejecutados correctamente (7 rutas); configuración efectiva mongodb/file/file/sync; GET /api/ping y /api/sucursales/buscar respondieron 200 mediante el kernel de Laravel con encabezados CORS; npm run build completó correctamente y Axios resolvió la URL de Sucursales hacia el backend. No se realizaron nuevas pruebas en Postman ni una revisión visual automatizada del navegador.
