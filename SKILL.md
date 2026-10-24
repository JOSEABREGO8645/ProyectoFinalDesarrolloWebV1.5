---
name: crud-modulo-libreria
description: Receta para crear un módulo CRUD en Librería San Francisco (Laravel + MongoDB + Vue) respetando Model → Repository → Service → Controller y una vista Vue por módulo. Úsela al agregar o modificar catálogos como sucursales, proveedores, clientes o productos.
---

# Receta: nuevo módulo CRUD

Cambie <Modulo> por el nombre singular (ej. Proveedor) y <modulos> por el plural en minúsculas (ej. proveedores).

## Backend (backend/)
1. php artisan make:model <Modulo>  → $collection explícito, $fillable y $casts.
2. php artisan make:request <Modulo>Request  → reglas de validación.
3. app/Repositories/<Modulo>Repository.php  → obtenerTodos, filtrar, buscarPorId, crear, actualizar, eliminar.
4. app/Services/<Modulo>Service.php  → reglas de negocio; lanza ReglaNegocioException.
5. php artisan make:controller <Modulo>Controller  → delgado: recibe, delega al Service, responde JSON.
6. routes/api.php  → /<modulos>/buscar ANTES de /<modulos>/{id}.
7. Probar en Postman: alta válida, alta inválida (422), duplicado, búsqueda, editar, eliminar, id inexistente (404).

## Frontend (frontend/src/)
1. services/<modulos>Service.js (Axios).
2. components/<modulos>/<Modulos>Table.vue y <Modulo>Form.vue.
3. views/<Modulos>View.vue.
4. Registrar el módulo en config/modulos.js y App.vue.

## Cierre
Anotar en MEMORY.md solo decisiones, archivos y pruebas verificadas.