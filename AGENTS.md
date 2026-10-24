# Librería San Francisco — instrucciones para agentes de IA

ERP web de inventario, ventas y traslados entre sucursales.
Stack 3: MongoDB (Docker) + Laravel/PHP (backend/) + Vue 3/Vite/Axios (frontend/).

## Arquitectura (no romper)
- Backend: Model → Repository → Service → Controller delgado. Validación con FormRequest. Rutas en routes/api.php.
- Reglas de negocio SOLO en app/Services. Acceso a datos SOLO en app/Repositories. El Controller no consulta modelos.
- Errores de regla de negocio: lanzar App\Exceptions\ReglaNegocioException (422 por defecto, 409 conflicto).
- Referencias entre colecciones: se guarda el _id del documento como STRING.
- Toda entrada/salida de inventario pasa por InventarioService y deja rastro en `movimientos` (kardex).
- Permisos en el backend con middleware `modulo:<nombre>`; denegar por defecto. Ocultar botones en Vue NO sustituye al backend.
- Frontend: views/ + components/<modulo>/ (Table, Form) + services/<modulo>Service.js. Sin Vue Router (navegación por hash). Sin librerías de UI; estilos en src/style.css (Material 3).
- Textos de la interfaz en español y directos ("Guardar cambios", no "Enviar").

## Colecciones
usuarios, auth_sessions, sucursales, proveedores, clientes, productos, existencias, movimientos, ventas, traslados, ingresos, contadores.

## Antes de dar algo por terminado
1. php artisan route:list --path=api
2. Probar en Postman (caso válido, inválido, sin permiso, id inexistente)
3. Probar en el navegador
4. Registrar en MEMORY.md solo lo verificado