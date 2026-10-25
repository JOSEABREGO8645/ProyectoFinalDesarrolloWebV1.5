# Documentación y entrega

**Autor:** José Alfredo Davila Abrego · **Carné:** 7490236074. Desarrollo individual informado por el estudiante. El enunciado plantea equipos de tres; los documentos registran esa diferencia sin atribuir trabajo a otros integrantes.

El enunciado proporcionado, `Proyecto final - Desarrollio Web.docx`, pide documento del proyecto, diseño, manual técnico, **manual de usuario ilustrado**, evidencias y presentación. No fija una extensión obligatoria. Se entregan PDF y sus fuentes editables en HTML, CSS y SVG.

| Documento | Contenido |
|---|---|
| [01-Proyecto.pdf](01-Proyecto.pdf) | Problema, objetivos, alcance, 36 reglas y matrices de responsabilidad. |
| [02-Diseno.pdf](02-Diseno.pdf) | Modelo conceptual/documental, arquitectura y tres procesos. |
| [03-Manual-tecnico.pdf](03-Manual-tecnico.pdf) | Instalación, configuración, seguridad, APIs, demo y publicación. |
| [04-Manual-usuario.pdf](04-Manual-usuario.pdf) | Guía ilustrada de acceso, usuarios, sucursales, inventario, caja, traslados y reportes. |
| [05-Evidencias.pdf](05-Evidencias.pdf) | Casos, resultados locales verificables y límites de la evidencia. |
| [06-Presentacion.pdf](06-Presentacion.pdf) | Ocho diapositivas con guion de demostración y defensa. |

Las colecciones de Postman están en [postman/](postman/). El modelo de datos y las instrucciones de respaldo están en [database/](../database/). [PUBLICACION.md](PUBLICACION.md) detalla la preparación de hosting gratuito.

## Actualizar PDF

Requiere Python estándar, Node 24, PHP/dependencias del backend y Microsoft Edge con un **perfil exclusivo de pruebas** en el puerto CDP 9222. No conectar el generador al perfil personal del navegador.

1. Editar el contenido en `herramientas/generar_documentacion.py` y el estilo en `documentos.css`. Los HTML en `fuentes/` también son editables; regenerarlos desde Python reemplaza sus ediciones manuales.
2. Ejecutar desde la raíz:

```powershell
python docs/herramientas/generar_documentacion.py
node docs/herramientas/generar_pdf.mjs
powershell -NoProfile -ExecutionPolicy Bypass -File docs/herramientas/inspeccionar_pdf.ps1
```

Si todavía no hay perfil exclusivo CDP, puede iniciarse antes desde PowerShell:

```powershell
$perfilPdf = Join-Path (Get-Location) 'frontend/test-results/edge-profile'
$argumentosPdf = @('--headless=new', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=9222', "--user-data-dir=$perfilPdf", 'about:blank')
Start-Process 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe' -ArgumentList $argumentosPdf -WindowStyle Hidden
```

No lanzar otra copia si el puerto ya está en uso; reutilice únicamente el perfil exclusivo de pruebas que inició para este proyecto.

El generador de fuentes lee rutas y los conteos de resultados de Postman existentes en `frontend/test-results`. Si faltan, primero ejecutar las comprobaciones documentadas; no inventa resultados. La impresión usa fuentes y recursos locales. El inspector abre los PDF con el motor de Windows y guarda páginas de revisión en `frontend/test-results/pdf-inspeccion/`, ignorado por Git.

Para renovar las ilustraciones, iniciar la demostración (`python docker/demostracion.py`), conservar su conjunto de prueba y ejecutar `node docs/herramientas/capturar_manual.mjs`. El script usa únicamente `libreria_demo`, deja un borrador de venta sin registrar y cierra su sesión. No captura las credenciales.

## Pendiente de entrega pública

La publicación no está terminada hasta crear las cuentas gratuitas, desplegar y comprobar las URLs reales y un proceso completo en línea. Las pruebas locales y los PDF no sustituyen esa evidencia. Las credenciales se entregan por el canal privado que indique el docente; nunca en este documento ni en Git.
