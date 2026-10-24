import assert from 'node:assert/strict'
import { mkdir, writeFile } from 'node:fs/promises'
import { setTimeout as pausa } from 'node:timers/promises'
import { conectarNavegador } from './browser.mjs'

const frontend = process.env.E2E_FRONTEND_URL || 'http://127.0.0.1:5175'
const usuario = process.env.E2E_USER
const password = process.env.E2E_PASSWORD
assert.ok(usuario && password, 'Configure E2E_USER y E2E_PASSWORD con cuentas de prueba')
const empleado = `empleado.${Date.now()}`
const claveEmpleado = 'EmpleadoE2ESeguro2026!'
const navegador = await conectarNavegador()
const { cdp, evaluar, esperar, click, llenar, errores } = navegador
const api = expresion => evaluar(`(async () => { const {default: api} = await import('/src/services/api.js'); ${expresion} })()`)

async function login(nombre, clave) {
  await esperar(`!!document.getElementById('form-login')`, 'No mostró el formulario de login')
  await llenar('form-login', { 'Usuario': nombre, 'Contraseña': clave })
  await click('Iniciar sesión')
}
async function salir() {
  await evaluar(`document.querySelector('[aria-label="Cerrar sesión"]').click()`)
  await esperar(`!!document.getElementById('form-login')`, 'No cerró la sesión')
}
async function usuarios() {
  await evaluar(`location.hash = 'usuarios'`)
  await esperar(`document.querySelector('.topbar h1')?.textContent === 'Usuarios' && !!document.querySelector('.li') && !document.querySelector('.spinner')`, 'No cargó Usuarios')
}
async function captura(nombre) {
  await pausa(700)
  await writeFile(`test-results/${nombre}.png`, Buffer.from((await cdp('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
}

try {
  await cdp('Page.enable')
  await cdp('Runtime.enable')
  await cdp('Network.enable')
  await cdp('Network.clearBrowserCookies')
  await cdp('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false })
  await navegador.navegar(frontend)
  await esperar(`!!document.getElementById('form-login')`, 'No cargó el login inicial')
  assert.equal(await evaluar(`!!document.querySelector('.rail')`), false, 'Se mostraron módulos sin autenticación')
  await mkdir('test-results', { recursive: true })
  await captura('login-escritorio')
  await login(usuario, 'incorrecta')
  await esperar(`document.querySelector('[role="alert"]')?.textContent.includes('Usuario o contraseña incorrectos.')`, 'No informó el fallo de credenciales')
  await login(usuario, password)
  await esperar(`!!document.querySelector('.rail a[href="#usuarios"]')`, 'El superusuario no pudo ingresar')
  assert.equal(await evaluar(`Object.keys(localStorage).some(k => /token|password/i.test(k)) || Object.keys(sessionStorage).some(k => /token|password/i.test(k))`), false, 'Se persistieron credenciales en Web Storage')
  assert.equal(await evaluar(`document.cookie.includes('libreria_refresh')`), false, 'JavaScript puede leer el refresh token')
  const cookies = (await cdp('Network.getCookies', { urls: [`${frontend}/api/auth/refresh`] })).cookies
  assert.equal(cookies.find(cookie => cookie.name === 'libreria_refresh')?.httpOnly, true)
  await cdp('Page.reload')
  await pausa(700)
  await esperar(`!!document.querySelector('.rail a[href="#usuarios"]')`, 'No recuperó la sesión al recargar')
  console.log('OK: Login, errores, cookie HttpOnly, tokens en memoria y recuperación al recargar')

  await usuarios()
  let renovaciones = 0
  const primeras = new Set()
  navegador.escuchar('Network.requestWillBeSent', evento => { if (evento.request.url.endsWith('/api/auth/refresh')) renovaciones++ })
  navegador.interceptar(evento => {
    const url = new URL(evento.request.url).pathname
    if (!primeras.has(url)) {
      primeras.add(url)
      return cdp('Fetch.fulfillRequest', { requestId: evento.requestId, responseCode: 401, responseHeaders: [{ name: 'Content-Type', value: 'application/json' }], body: Buffer.from('{}').toString('base64') })
    }
    return cdp('Fetch.continueRequest', { requestId: evento.requestId })
  })
  await cdp('Fetch.enable', { patterns: [{ urlPattern: '*api/productos/buscar*', requestStage: 'Request' }, { urlPattern: '*api/catalogos/proveedores*', requestStage: 'Request' }] })
  const estados = await api(`return (await Promise.all([api.get('/productos/buscar'), api.get('/catalogos/proveedores')])).map(r => r.status);`)
  assert.deepEqual(estados, [200, 200])
  assert.equal(renovaciones, 1, 'Las solicitudes simultáneas duplicaron la renovación')
  await cdp('Fetch.disable')
  navegador.interceptar(null)
  console.log('OK: Renovación automática compartida entre solicitudes simultáneas')

  await click('addAgregar usuario')
  await esperar(`!!document.getElementById('form-usuario')`, 'No abrió el formulario de usuario')
  await click('Guardar cambios')
  await esperar(`document.querySelector('[role="alert"]')?.textContent.includes('Complete el nombre')`, 'No validó el usuario en el cliente')
  const sucursalId = await evaluar(`document.querySelector('#form-usuario select option:nth-child(2)').value`)
  await llenar('form-usuario', { 'Nombre completo *': 'Empleado del navegador', 'Usuario *': empleado, 'Contraseña *': claveEmpleado, 'Sucursal asignada *': sucursalId, 'Código de facturación': '1234' })
  await click('menu_bookCatálogo')
  await click('Guardar cambios')
  await esperar(`!document.querySelector('[role="dialog"]') && document.body.textContent.includes(${JSON.stringify(empleado)})`, 'No creó el empleado')
  const perfil = await api(`return (await api.get('/admin/usuarios/buscar', { params: { q: ${JSON.stringify(empleado)} } })).data[0];`)
  assert.equal(Object.hasOwn(perfil, 'password'), false)
  assert.equal(Object.hasOwn(perfil, 'codigo_hash'), false)
  assert.deepEqual(perfil.modulos, ['catalogo'])
  assert.equal(perfil.tiene_codigo, true)
  await captura('usuarios-escritorio')

  await salir()
  const cerrado = await evaluar(`fetch('/api/auth/refresh', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } }).then(r => r.status)`)
  assert.equal(cerrado, 401, 'El refresh sobrevivió al cierre de sesión')
  await login(empleado, claveEmpleado)
  await esperar(`document.querySelector('.topbar h1')?.textContent === 'Inicio' && !document.querySelector('.spinner')`, 'El empleado no ingresó a su resumen permitido')
  assert.equal(await evaluar(`!!document.querySelector('.rail a[href="#usuarios"]') || !!document.querySelector('.rail a[href="#proveedores"]')`), false, 'Se mostraron módulos no asignados')
  assert.equal(await api(`try { await api.get('/admin/usuarios'); return 200; } catch(e) { return e.response.status; }`), 403)
  assert.equal(await evaluar(`fetch('/api/productos', { headers: { Accept: 'application/json' } }).then(r => r.status)`), 401, 'La cookie sola permitió acceder sin JWT')
  await evaluar(`location.hash = 'usuarios'`)
  await esperar(`document.querySelector('[role="alert"]')?.textContent.includes('No tiene permiso para este módulo.')`, 'La navegación por hash ignoró el permiso')
  assert.equal(await evaluar(`!!document.querySelector('.fab')`), false, 'Se renderizó el módulo no autorizado')
  await evaluar(`location.hash = 'catalogo'`)
  await esperar(`document.querySelector('.topbar h1')?.textContent === 'Catálogo' && !document.querySelector('.spinner')`, 'No volvió al catálogo permitido')
  console.log('OK: Empleado con módulos asignados, rechazo 403 y navegación protegida')

  await salir()
  await login(usuario, password)
  await esperar(`!!document.querySelector('.rail a[href="#usuarios"]')`, 'No reingresó el administrador')
  await usuarios()
  await evaluar(`(() => { const fila = [...document.querySelectorAll('.li')].find(f => f.textContent.includes(${JSON.stringify(empleado)})); fila.querySelector('[aria-label="Editar usuario"]').click(); })()`)
  await esperar(`document.querySelector('#form-usuario input[type="password"]')?.value === ''`, 'No dejó vacía la contraseña al editar')
  assert.equal(await evaluar(`document.querySelector('#form-usuario select').value`), sucursalId)
  await llenar('form-usuario', { 'Nombre completo *': 'Empleado actualizado' })
  await click('Guardar cambios')
  await esperar(`!document.querySelector('[role="dialog"]') && document.body.textContent.includes('Empleado actualizado')`, 'No editó el empleado')
  await click('addAgregar usuario')
  await esperar(`!!document.getElementById('form-usuario')`, 'No abrió el formulario de duplicado')
  await llenar('form-usuario', { 'Nombre completo *': 'Duplicado', 'Usuario *': empleado, 'Contraseña *': claveEmpleado, 'Sucursal asignada *': sucursalId })
  await click('Guardar cambios')
  await esperar(`document.querySelector('[role="alert"]')?.textContent.includes('Ya existe una cuenta con ese usuario.')`, 'No rechazó el usuario duplicado')
  await click('Cancelar')
  await evaluar(`(() => { const fila = [...document.querySelectorAll('.li')].find(f => f.textContent.includes(${JSON.stringify(empleado)})); fila.querySelector('[aria-label="Editar usuario"]').click(); })()`)
  await esperar(`!!document.getElementById('form-usuario')`, 'No abrió el formulario para desactivar')
  await evaluar(`document.querySelector('#form-usuario input[type="checkbox"]').click()`)
  await cdp('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true })
  await captura('usuario-form-movil')
  assert.equal(await evaluar(`document.documentElement.scrollWidth <= innerWidth`), true, 'El formulario desborda en móvil')
  await click('Guardar cambios')
  await esperar(`!document.querySelector('[role="dialog"]') && [...document.querySelectorAll('.li')].some(f => f.textContent.includes(${JSON.stringify(empleado)}) && f.textContent.includes('Inactivo'))`, 'No desactivó al empleado')
  await salir()
  await captura('login-movil')
  await login(empleado, claveEmpleado)
  await esperar(`document.querySelector('[role="alert"]')?.textContent.includes('Usuario o contraseña incorrectos.')`, 'El usuario inactivo pudo ingresar')
  await login(usuario, password)
  await esperar(`!!document.querySelector('.rail a[href="#usuarios"]')`, 'No volvió el administrador')
  await usuarios()
  await evaluar(`(() => { const fila = [...document.querySelectorAll('.li')].find(f => f.textContent.includes(${JSON.stringify(empleado)})); fila.querySelector('[aria-label="Eliminar usuario"]').click(); })()`)
  await esperar(`!!document.querySelector('[role="dialog"]')`, 'No pidió confirmación al eliminar usuario')
  await click('Eliminar')
  await esperar(`!document.querySelector('[role="dialog"]') && !document.body.textContent.includes(${JSON.stringify(empleado)})`, 'No eliminó al usuario de prueba')
  assert.deepEqual(errores, [], 'Hay excepciones JavaScript durante los flujos de autenticación')
  console.log('OK: Usuarios: validación, duplicados, edición, desactivación, eliminación y móvil')
  await salir()
} finally {
  navegador.cerrar()
}
