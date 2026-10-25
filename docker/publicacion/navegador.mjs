import assert from 'node:assert/strict'
import { readFile, mkdir, writeFile } from 'node:fs/promises'
import { conectarNavegador } from '../../frontend/tests/browser.mjs'
const base = process.env.E2E_FRONTEND_URL || 'http://127.0.0.1:18080/'
const acceso = JSON.parse(await readFile(process.env.E2E_ACCESOS || new URL('../../backend/storage/app/private/demo/libreria_demo/accesos.json', import.meta.url), 'utf8'))
assert.equal(acceso.base, 'libreria_demo')
const cuenta = acceso.cuentas.find(c => ['caja.demo', 'maria.lopez'].includes(c.usuario))
const n = await conectarNavegador()
try {
  await n.cdp('Page.enable'); await n.cdp('Runtime.enable')
  await n.cdp('Emulation.setEmulatedMedia', { media: 'screen' })
  await n.cdp('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false })
  await n.navegar(base)
  await n.esperar(`!!document.getElementById('form-login')`, 'No abrió el acceso compilado')
  assert.equal(await n.evaluar(`!!document.querySelector('.demo-banner')`), false, 'La publicación conserva el aviso de demostración')
  await n.llenar('form-login', { Usuario: cuenta.usuario, Contraseña: cuenta.password })
  await n.click('Iniciar sesión')
  await n.esperar(`!!document.querySelector('.bienvenida')`, 'No inició sesión desde Vue compilado')
  assert.equal(await n.evaluar(`!!document.querySelector('.rail a[href="#usuarios"]')`), false)
  await n.evaluar(`location.hash='ventas'`)
  await n.esperar(`!!document.querySelector('.pos-caja')`, 'No abrió Caja')
  await n.navegar(new URL('#ventas', base).href)
  await n.esperar(`!!document.querySelector('.pos-caja')`, 'La cookie no renovó la sesión al recargar')
  await n.evaluar('document.fonts.ready.then(()=>true)')
  await n.esperar(`getComputedStyle(document.querySelector('.page')).opacity==='1'`, 'No terminó la animación')
  await mkdir('frontend/test-results', { recursive: true })
  await writeFile('frontend/test-results/publicacion-local.png', Buffer.from((await n.cdp('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
  await n.evaluar(`document.querySelector('[aria-label="Cerrar sesión"]').click()`)
  await n.esperar(`!!document.getElementById('form-login')`, 'No cerró sesión')
  assert.deepEqual(n.errores, [])
  console.log('Vue compilado: acceso, permisos, Caja y renovación correctos.')
} finally { n.cerrar() }
