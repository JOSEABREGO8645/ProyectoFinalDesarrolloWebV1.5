# Librería San Francisco — ERP web (Stack 3)

Inventario, ventas y traslados entre sucursales.
MongoDB (Docker) · Laravel + mongodb/laravel-mongodb · Vue 3 + Vite + Axios.

## Estructura
backend/ (API) · frontend/ (interfaz) · database/ (scripts y diccionario de datos) · docker/ (configuración de contenedores) · docs/ (diagramas, manuales, evidencias)

## Ejecución local
1. docker compose up -d
2. Terminal 1: cd backend, luego php artisan serve
3. Terminal 2: cd frontend, luego npm run dev

## Configuración

Para una instalación nueva, copie backend/.env.example a backend/.env y frontend/.env.example a frontend/.env. Complete DB_URI con las credenciales del contenedor local y ejecute `php artisan key:generate` y `php artisan libreria:jwt-clave` desde backend/. No sobrescriba archivos .env de una instalación existente. Después de cambiar variables, ejecute `php artisan config:clear` y reinicie Vite si cambió frontend/.env o vite.config.js.

El frontend usa VITE_API_URL=/api y el proxy de Vite hacia VITE_API_PROXY_TARGET. Así la cookie de renovación pertenece al mismo origen que la interfaz. FRONTEND_ORIGINS enumera los orígenes permitidos para la sesión; por defecto, localhost:5173 y 127.0.0.1:5173 con esquema http. La publicación deberá conservar el acceso a /api mediante un proxy del mismo origen o una configuración equivalente del mismo sitio.

## Primer acceso y Usuarios

Desde backend/, ejecutar `php artisan libreria:crear-admin --generar-clave`. Usa una sucursal activa existente; --sucursal permite escoger su ID. Solo para una instalación vacía, agregar --crear-sucursal permite crear Sucursal Central. El comando no reemplaza cuentas existentes y no habilita registro público.

El usuario inicial es admin y su contraseña aleatoria se guarda en backend/storage/app/private/acceso-inicial.txt, excluido de Git. En producción no se genera ese archivo: se solicita la contraseña de forma oculta o se toma LIBRERIA_ADMIN_PASSWORD del entorno. El superusuario administra cuentas desde Usuarios; contraseñas y códigos de facturación se almacenan con hash y nunca aparecen en los listados. Cambiar la contraseña, rol, sucursal, estado o módulos invalida las sesiones anteriores. Una sucursal con usuarios activos no puede desactivarse antes de reasignarlos o desactivarlos.

## Avance funcional

Sucursales, Proveedores, Clientes, Catálogo y Usuarios cuentan con formularios, búsqueda, edición y eliminación. Login JWT y permisos ya protegen la API y la navegación. El superusuario dispone de todos los módulos; un empleado tiene acceso completo solo a los módulos asignados. Usuarios es exclusivo del superusuario. El empleado consulta existencias de su sucursal; el superusuario puede consultar totales o elegir una sucursal. Los procesos que modifican inventario corresponden a la fase 10.

El access token dura 15 minutos y permanece en memoria. El refresh token rota en una cookie HttpOnly, SameSite=Lax (Secure en producción), con vencimiento absoluto de 7 días. La interfaz recupera la sesión al recargar, comparte una renovación entre solicitudes simultáneas y revoca la sesión al salir. Los POST de sesión requieren X-Requested-With: XMLHttpRequest y un origen permitido cuando el cliente envía Origin.

## Pruebas

- Backend: desde backend/, ejecutar `php artisan test --compact`. Los catálogos requieren MongoDB local disponible y usan bases aleatorias aisladas; no modifican la base del proyecto. No se ejecutan migraciones relacionales.
- Frontend: desde frontend/, ejecutar `npm run build`.
- Postman: importar docs/postman/auth.postman_collection.json y catalogos.postman_collection.json. Configurar base_url, usuario y password de un superusuario de pruebas; la colección de auth también requiere password_empleado. Incluyen login/logout, renovaciones, revocación, rechazos sin permiso y datos inválidos. Crean datos ficticios y eliminan sus registros propios. Usar una base aislada, pues las pruebas de seguridad revocan deliberadamente sus sesiones.
- Navegador: los scripts de frontend/tests/ utilizan CDP de Edge/Chrome sin dependencias adicionales del frontend. Iniciar un perfil exclusivo con --headless=new, --remote-debugging-port=9222 y --user-data-dir apuntando a frontend/test-results/edge-profile. Desde frontend/, ejecutar `python tests/run-browser.py`: prepara una BD y caché temporales, inicia backend en 8002 y frontend en 5175, prueba autenticación/Usuarios y catálogos, detiene los servidores y elimina únicamente su BD de pruebas. MongoDB debe estar disponible. Las capturas y logs quedan en frontend/test-results/ (ignorado).
- Newman: el mismo ejecutor acepta `python tests/run-browser.py auth.postman_collection.json catalogos.postman_collection.json`. Requiere Newman en frontend/node_modules/.cache/newman; puede prepararse desde la raíz con `npm exec --cache ./frontend/node_modules/.cache/newman --yes --package=newman -- newman --version`. Esta herramienta de pruebas no modifica las dependencias del frontend.

El README de publicación y los manuales completos se terminan en la Fase 15. MEMORY.md distingue los resultados comprobados de los pendientes.
