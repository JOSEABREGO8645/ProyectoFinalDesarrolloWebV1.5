import assert from 'node:assert/strict'
import { mkdir, writeFile } from 'node:fs/promises'
import { conectarNavegador } from './browser.mjs'

const n = await conectarNavegador()
const { cdp, evaluar, esperar, llenar, click } = n
const frontend = process.env.E2E_FRONTEND_URL || 'http://127.0.0.1:5175'
const password = process.env.E2E_PASSWORD
assert.ok(password)
async function login(usuario) {
  await esperar(`!!document.getElementById('form-login')`, 'No mostró login')
  await llenar('form-login', { Usuario: usuario, Contraseña: password }); await click('Iniciar sesión')
  await esperar(`!!document.querySelector('.rail') && !document.querySelector('.spinner')`, 'No cargó la sesión')
}
async function salir() {
  await evaluar(`document.querySelector('[aria-label="Cerrar sesión"]').click()`)
  await esperar(`!!document.getElementById('form-login')`, 'No cerró la sesión')
}
const api = url => evaluar(`(async()=>{const api=(await import('/src/services/api.js')).default;const r=await api.get(${JSON.stringify(url)},{validateStatus:()=>true});return {status:r.status,data:r.data};})()`)
try {
  await cdp('Page.enable'); await cdp('Runtime.enable'); await cdp('Network.enable')
  await cdp('Emulation.setDeviceMetricsOverride', { width: 1365, height: 1000, deviceScaleFactor: 1, mobile: false })
  await cdp('Network.clearBrowserCookies'); await n.navegar(`${frontend}/`)
  await login(process.env.E2E_USER)
  await esperar(`document.querySelector('h1')?.textContent === 'Inicio' && document.querySelector('[data-indicador="unidades"]')?.textContent === '5'`, 'No abrió el dashboard como inicio con stock real')
  assert.equal(await evaluar(`document.querySelector('[data-indicador="ventas"]').textContent`), 'Q 3.03')
  assert.equal(await evaluar(`document.querySelector('[data-indicador="ingresos"]').textContent`), 'Q 4.70')
  assert.equal(await evaluar(`document.querySelector('[data-indicador="pendientes"]').textContent`), '1')
  await esperar(`document.querySelectorAll('.dia-ventas').length === 7`, 'No mostró los siete días')
  await evaluar(`(() => { const dias=[...document.querySelectorAll('.dia-ventas')];dias.find(d=>d.title.includes('Q 3.03')).click() })()`)
  await esperar(`document.querySelector('[role="status"]')?.textContent.includes('Q 3.03')`, 'No se puede consultar el total de un día')
  await click('30 días')
  await esperar(`document.querySelectorAll('.dia-ventas').length === 30 && !document.querySelector('.spinner')`, 'No cambió el período a treinta días')
  const destinos = (await api('/catalogos/sucursales')).data
  const destino = destinos.find(s => s.nombre === 'Dashboard Destino E2E')
  await evaluar(`(() => { const i=document.querySelector('[aria-label="Sucursal del dashboard"]');i.value=${JSON.stringify(destino._id)};i.dispatchEvent(new Event('change',{bubbles:true})); })()`)
  await click('Consultar')
  await esperar(`document.querySelector('[data-indicador="unidades"]')?.textContent === '0' && !document.querySelector('.spinner')`, 'No filtró la sucursal de destino')
  assert.equal(await evaluar(`document.querySelector('[data-indicador="ventas"]').textContent`), 'Q 0.00')
  assert.equal(await evaluar(`document.querySelector('[data-indicador="pendientes"]').textContent`), '1')
  const origen = destinos.find(s => s.nombre === 'Dashboard Central E2E')
  await evaluar(`(() => { const i=document.querySelector('[aria-label="Sucursal del dashboard"]');i.value=${JSON.stringify(origen._id)};i.dispatchEvent(new Event('change',{bubbles:true})); })()`)
  await click('Consultar'); await esperar(`document.querySelector('[data-indicador="unidades"]')?.textContent === '5' && !document.querySelector('.spinner')`, 'No restauró la sucursal inicial')
  await click('7 días'); await esperar(`document.querySelectorAll('.dia-ventas').length === 7`, 'No restauró siete días')
  await mkdir('test-results', { recursive: true })
  await writeFile('test-results/dashboard-escritorio.png', Buffer.from((await cdp('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
  await cdp('Fetch.enable', { patterns: [{ urlPattern: '*api/dashboard*' }] })
  await click('Consultar'); await esperar(`document.querySelector('[role="alert"]')?.textContent.length > 0`, 'No mostró el error de red')
  await cdp('Fetch.disable'); await click('Reintentar')
  await esperar(`!!document.querySelector('[data-indicador="unidades"]') && !document.querySelector('[role="alert"]') && !document.querySelector('.spinner')`, 'No recuperó el dashboard después de la falla')
  await cdp('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true })
  assert.equal(await evaluar(`document.documentElement.scrollWidth <= innerWidth`), true, 'El dashboard desborda en móvil')
  await writeFile('test-results/dashboard-movil.png', Buffer.from((await cdp('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
  console.log('OK: dashboard con datos reales de prueba, gráfica interactiva, fechas, sucursales, error/reintento y móvil')
  await salir(); await login('dashboard.caja.e2e')
  await esperar(`!!document.querySelector('[data-indicador="ventas"]')`, 'El cajero no accedió a sus ventas')
  assert.equal(await evaluar(`!!document.querySelector('[data-indicador="unidades"]') || !!document.querySelector('[data-indicador="ingresos"]') || !!document.querySelector('[data-indicador="pendientes"]')`), false)
  assert.equal(await evaluar(`!!document.querySelector('[aria-label="Sucursal del dashboard"]')`), false)
  assert.equal((await api(`/dashboard?sucursal_id=${destino._id}`)).status, 403)
  await salir(); await login('dashboard.sin.e2e')
  assert.equal(await evaluar(`!!document.querySelector('.rail a[href="#inicio"]')`), false)
  await evaluar(`location.hash='inicio'`)
  await esperar(`document.querySelector('main [role="alert"]')?.textContent.includes('No tiene permiso')`, 'Un usuario sin módulo de inventario/operación pudo abrir Inicio')
  assert.equal((await api('/dashboard')).status, 403)
  await salir(); assert.deepEqual(n.errores, [])
  console.log('OK: datos y navegación del dashboard protegidos por módulo y sucursal')
} catch (e) {
  await mkdir('test-results', { recursive: true })
  await writeFile('test-results/dashboard-error.png', Buffer.from((await cdp('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
  console.error(await evaluar(`document.body.innerText`)); throw e
} finally { await cdp('Fetch.disable').catch(()=>{}); n.cerrar() }
