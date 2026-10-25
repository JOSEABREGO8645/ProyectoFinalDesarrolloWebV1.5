// Ilustraciones con datos ficticios. No registra ventas ni modifica catálogos.
import assert from 'node:assert/strict'
import { readFile, mkdir, writeFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { conectarNavegador } from '../../frontend/tests/browser.mjs'
import { leerEnCaja } from '../../frontend/tests/caja-helpers.mjs'

const raiz = new URL('../../', import.meta.url)
const acceso = JSON.parse(await readFile(new URL('backend/storage/app/private/demo/libreria_demo/accesos.json', raiz), 'utf8'))
assert.equal(acceso.base, 'libreria_demo')
const cuenta = acceso.cuentas.find(c => c.usuario === 'admin.demo')
assert.ok(cuenta)
const destino = new URL('../imagenes/', import.meta.url)
await mkdir(destino, { recursive: true })
const n = await conectarNavegador()
const { cdp, evaluar, esperar, llenar, click } = n
async function foto(nombre, dialogo = false) {
  await evaluar('document.fonts.ready.then(()=>true)')
  await esperar("!document.getAnimations().some(a=>a.playState==='running'&&Number.isFinite(a.effect.getComputedTiming().endTime))", 'No terminó la animación de la ilustración')
  let clip
  if (dialogo) clip = await evaluar(`(()=>{const r=document.querySelector('[role="dialog"]').getBoundingClientRect();return {x:r.x+scrollX,y:r.y+scrollY,width:r.width,height:r.height,scale:1}})()`)
  const captura = await cdp('Page.captureScreenshot', { format: 'png', ...(clip ? { clip } : {}) })
  await writeFile(new URL(nombre + '.png', destino), Buffer.from(captura.data, 'base64'))
}
async function abrir(hash) {
  await evaluar('location.hash=' + JSON.stringify(hash))
  await esperar(`!!document.querySelector('.page')&&!document.querySelector('.spinner')&&getComputedStyle(document.querySelector('.page')).opacity==='1'&&!document.querySelector('.page').getAnimations().some(a=>a.playState==='running')`, 'No terminó de abrir ' + hash)
  await evaluar('scrollTo(0,0)')
}
try {
  await cdp('Page.enable'); await cdp('Runtime.enable')
  await cdp('Emulation.setEmulatedMedia', { media: 'screen' })
  await cdp('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false })
  await n.navegar('http://127.0.0.1:5176/')
  await esperar(`!!document.getElementById('form-login')||!!document.querySelector('.rail')`, 'No abrió la demostración')
  if (await evaluar(`!!document.querySelector('.rail')`)) {
    await evaluar(`document.querySelector('[aria-label="Cerrar sesión"]').click()`)
    await esperar(`!!document.getElementById('form-login')`, 'No cerró la sesión de pruebas')
  }
  assert.ok(await evaluar(`document.querySelector('.demo-banner')?.textContent.includes('Datos ficticios')`))
  await foto('acceso')
  await llenar('form-login', { Usuario: cuenta.usuario, Contraseña: cuenta.password })
  await click('Iniciar sesión')
  await esperar(`!!document.querySelector('.bienvenida')`, 'No abrió Inicio')
  for (const hash of ['inicio', 'catalogo', 'usuarios', 'ingresos', 'traslados', 'dashboard', 'reportes', 'ventas']) {
    await abrir(hash)
    await foto(hash)
  }
  await leerEnCaja(n, '2*000-DEMO-001')
  await esperar(`document.querySelector('[data-producto="000-DEMO-001"] input')?.value==='2'`, 'No agregó la demostración del lector')
  await foto('venta-detalle')
  await click('Guardar (facturar)')
  await esperar(`!!document.getElementById('form-cobro')`, 'No abrió el cobro')
  await llenar('form-cobro', { 'Efectivo recibido *': '100.00' })
  await foto('venta-cobro', true)
  await click('Confirmar cobro')
  await esperar(`!!document.getElementById('form-autorizar')`, 'No abrió la autorización')
  await foto('venta-autorizacion', true)
  // Se abandona el borrador antes de autorizar: ningún POST de ventas.
  await click('Volver al cobro', '.dialog footer button')
  await click('Volver al detalle', '.dialog footer button')
  await evaluar(`document.querySelector('[aria-label="Cerrar sesión"]').click()`)
  await esperar(`!!document.getElementById('form-login')`, 'No cerró la sesión')
  assert.deepEqual(n.errores, [])
  console.log('12 ilustraciones de demostración guardadas en ' + fileURLToPath(destino))
} finally { n.cerrar() }
