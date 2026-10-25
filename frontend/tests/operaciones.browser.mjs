import assert from 'node:assert/strict'
import { mkdir, writeFile } from 'node:fs/promises'
import { conectarNavegador } from './browser.mjs'
import { leerEnCaja, prepararCobro } from './caja-helpers.mjs'

const frontend = process.env.E2E_FRONTEND_URL || 'http://127.0.0.1:5175'
const usuario = process.env.E2E_USER, password = process.env.E2E_PASSWORD
assert.ok(usuario && password, 'Se requieren cuentas exclusivas de pruebas')
const navegador = await conectarNavegador()
const { cdp, evaluar, esperar, llenar, click, errores } = navegador
const api = (url, method = 'get', data) => evaluar(`(async () => {
  const api = (await import('/src/services/api.js')).default;
  const r = await api.request({url: ${JSON.stringify(url)}, method: ${JSON.stringify(method)}, data: ${JSON.stringify(data)}, validateStatus: () => true});
  return {status: r.status, data: r.data};
})()`)
async function entrar(nombre) {
  await esperar(`!!document.getElementById('form-login')`, 'No mostró login')
  await llenar('form-login', { Usuario: nombre, Contraseña: password })
  await click('Iniciar sesión')
  await esperar(`!!document.querySelector('.bienvenida')`, 'No mostró la bienvenida')
  await esperar(`!!document.querySelector('.rail') && !document.querySelector('.spinner')`, 'No abrió la sesión')
}
async function salir() {
  await evaluar(`document.querySelector('[aria-label="Cerrar sesión"]').click()`)
  await esperar(`!!document.getElementById('form-login')`, 'No cerró sesión')
}
async function modulo(tipo) {
  await evaluar(`location.hash = ${JSON.stringify(tipo)}`)
  await esperar(`document.querySelector('h1')?.textContent.toLowerCase() === ${JSON.stringify(tipo)} && !document.querySelector('.spinner')`, `No cargó ${tipo}`)
}
async function abrir() {
  await evaluar(`document.querySelector('.fab').click()`)
  await esperar(`!!document.getElementById('form-operacion') && !document.querySelector('.spinner')`, 'No abrió el formulario de operación')
}
async function agregar(cantidad) {
  await esperar(`!!document.querySelector('[aria-label="Agregar Producto Operaciones E2E"]:not(:disabled)')`, 'No encontró el producto de prueba')
  await evaluar(`document.querySelector('[aria-label="Agregar Producto Operaciones E2E"]').click()`)
  await esperar(`!!document.querySelector('[aria-label="Cantidad de Producto Operaciones E2E"]')`, 'No agregó el producto')
  await evaluar(`(() => { const i = document.querySelector('[aria-label="Cantidad de Producto Operaciones E2E"]'); i.value = ${cantidad}; i.dispatchEvent(new Event('input', {bubbles:true})); })()`)
}
async function cerrarDetalle() { await click('Cerrar', '.dialog footer button'); await esperar(`!document.querySelector('[role="dialog"]')`, 'No cerró el detalle') }
async function verDocumento(id) {
  if (await evaluar(`!!document.querySelector('.pos-caja')`)) await click('Historial y kardex')
  await esperar(`!!document.querySelector('[data-documento="${id}"] button')`, 'No está el documento en el historial')
  await evaluar(`document.querySelector('[data-documento="${id}"] button').click()`)
  await esperar(`!!document.querySelector('.comprobante')`, 'No abrió el comprobante')
}
async function accion(texto, motivo) {
  await click(texto, '.dialog footer button')
  await esperar(`!!document.querySelector('.dialog textarea')`, 'No pidió el motivo de la acción')
  await evaluar(`(() => { const d = [...document.querySelectorAll('.dialog')].at(-1); const i = d.querySelector('textarea'); i.value = ${JSON.stringify(motivo)}; i.dispatchEvent(new Event('input', {bubbles:true})); })()`)
  await evaluar(`(() => { const d = [...document.querySelectorAll('.dialog')].at(-1); [...d.querySelectorAll('footer button')].find(b => b.textContent.trim() === ${JSON.stringify(texto)}).click(); })()`)
  await esperar(`document.querySelectorAll('.dialog').length === 1 && !document.querySelector('.dialog footer button:disabled')`, 'No terminó la acción')
}

try {
  await cdp('Page.enable'); await cdp('Runtime.enable'); await cdp('Network.enable')
  await cdp('Emulation.setDeviceMetricsOverride', { width: 1365, height: 900, deviceScaleFactor: 1, mobile: false })
  await cdp('Network.clearBrowserCookies')
  await navegador.navegar(`${frontend}/#ingresos`)
  await entrar(usuario)
  await modulo('ingresos')
  const perfil = (await api('/auth/me')).data
  const proveedor = (await api('/catalogos/proveedores')).data.find(p => p.nombre === 'Proveedor Operaciones E2E')
  const destino = (await api('/catalogos/destinos')).data.find(s => s.nombre === 'Destino Operaciones E2E')
  await abrir()
  await llenar('form-operacion', { 'Proveedor *': proveedor._id, 'Factura / referencia *': 'E2E ingreso con respuesta perdida' })
  await agregar(10)
  let ingresoOriginal
  navegador.interceptar(async solicitud => {
    if (solicitud.request.method === 'POST' && !ingresoOriginal) {
      const body = await cdp('Fetch.getResponseBody', { requestId: solicitud.requestId })
      ingresoOriginal = JSON.parse(body.base64Encoded ? Buffer.from(body.body, 'base64').toString() : body.body)
      await cdp('Fetch.failRequest', { requestId: solicitud.requestId, errorReason: 'ConnectionClosed' })
    } else { await cdp('Fetch.continueRequest', { requestId: solicitud.requestId }) }
  })
  await cdp('Fetch.enable', { patterns: [{ urlPattern: '*api/ingresos', requestStage: 'Response' }] })
  await click('Registrar ingreso', '.dialog footer button')
  await esperar(`document.querySelector('#form-operacion')?.textContent.includes('No se confirmó la respuesta')`, 'No protegió el reintento tras perder la respuesta')
  assert.ok(ingresoOriginal?._id, 'El servidor no confirmó el primer ingreso')
  assert.equal(await evaluar(`document.querySelector('[aria-label="Referencia o motivo"]').disabled`), true)
  await cdp('Fetch.disable'); navegador.interceptar(null)
  await click('Reintentar registro')
  await esperar(`!!document.querySelector('.comprobante') && !document.getElementById('form-operacion')`, 'No recuperó el ingreso con el mismo identificador')
  const ingresos = (await api('/ingresos')).data.filter(d => d.motivo === 'E2E ingreso con respuesta perdida')
  assert.equal(ingresos.length, 1); assert.equal(ingresos[0]._id, ingresoOriginal._id)
  assert.equal(ingresos[0].total_centavos, 470)
  await cerrarDetalle()
  await click('Kardex de inventario')
  await esperar(`document.querySelector('.tabla-operaciones tbody')?.textContent.includes('Producto Operaciones E2E')`, 'No mostró el kardex')
  console.log('OK: ingreso, kardex y reintento real después de perder la respuesta sin duplicar stock')

  await modulo('ventas'); await leerEnCaja(navegador, '3*000000-OPS-E2E')
  await esperar(`document.querySelector('[data-producto="000000-OPS-E2E"] input')?.value==='3'`, 'No leyó el producto')
  await prepararCobro(navegador, '5.00', '0000')
  await click('Facturar venta', '.dialog footer button')
  await esperar(`document.querySelector('.dialog')?.textContent.includes('Código de facturación incorrecto')`, 'No rechazó el PIN incorrecto')
  await llenar('form-autorizar', { 'Código de facturación (4 dígitos) *': '4321' })
  await click('Facturar venta', '.dialog footer button')
  await esperar(`document.querySelector('.comprobante')?.textContent.includes('Cambio: Q 1.97')`, 'No mostró el cambio calculado en el servidor')
  const venta = (await api('/ventas')).data.find(d => d.usuario_id === perfil._id && d.items.some(i => i.codigo === '000000-OPS-E2E'))
  assert.equal(venta.total_centavos, 303)
  assert.equal(await evaluar(`!![...document.querySelectorAll('.dialog button')].find(b => b.textContent === 'Imprimir comprobante')`), true)
  await cerrarDetalle()
  console.log('OK: venta con PIN, precios del servidor, efectivo, cambio y comprobante')

  await modulo('traslados'); await abrir()
  await llenar('form-operacion', { 'Destino *': destino._id, 'Motivo del traslado *': 'E2E envío para recepción' })
  await agregar(2); await click('Enviar traslado')
  await esperar(`document.querySelector('.comprobante')?.textContent.includes('enviado')`, 'No registró el envío')
  const traslado = (await api('/traslados')).data.find(d => d.motivo === 'E2E envío para recepción')
  await cerrarDetalle(); await salir(); await entrar('origen.operaciones.e2e'); await modulo('traslados'); await verDocumento(traslado._id)
  assert.equal(await evaluar(`[...document.querySelectorAll('.dialog button')].some(b => b.textContent === 'Confirmar recepción')`), false)
  assert.equal((await api(`/traslados/${traslado._id}/confirmar`, 'post', { motivo: 'Origen no autorizado' })).status, 403)
  await cerrarDetalle(); await salir(); await entrar('destino.operaciones.e2e'); await modulo('traslados'); await verDocumento(traslado._id)
  assert.equal(await evaluar(`[...document.querySelectorAll('.dialog button')].some(b => b.textContent === 'Anular documento')`), false)
  await accion('Confirmar recepción', 'Recepción completa E2E')
  await esperar(`document.querySelector('.comprobante')?.textContent.includes('confirmado')`, 'No confirmó el traslado')
  assert.equal((await api(`/traslados/${traslado._id}/confirmar`, 'post', { motivo: 'Duplicado E2E' })).status, 409)
  assert.equal((await api('/ventas')).status, 403)
  await cerrarDetalle(); await salir(); await entrar(usuario); await modulo('traslados')
  console.log('OK: traslado en dos sucursales, recepción, permisos y bloqueo de doble confirmación')

  await abrir(); await llenar('form-operacion', { 'Destino *': destino._id, 'Motivo del traslado *': 'E2E envío para anular' }); await agregar(1)
  await click('Enviar traslado'); await esperar(`!!document.querySelector('.comprobante')`, 'No creó el segundo traslado')
  await accion('Anular documento', 'Cancelación E2E')
  await esperar(`document.querySelector('.comprobante')?.textContent.includes('anulado')`, 'No anuló el envío')
  const origenStock = (await api(`/catalogos/productos?q=000000-OPS-E2E&sucursal_id=${perfil.sucursal_id}`)).data[0]
  const destinoStock = (await api(`/catalogos/productos?q=000000-OPS-E2E&sucursal_id=${destino._id}`)).data[0]
  assert.equal(origenStock.existencia, 5); assert.equal(destinoStock.existencia, 2)
  await cerrarDetalle(); await modulo('ventas'); await verDocumento(venta._id)
  await accion('Anular documento', 'Venta cancelada E2E')
  await esperar(`document.querySelector('.comprobante')?.textContent.includes('anulado')`, 'No anuló la venta')
  await cerrarDetalle()
  console.log('OK: anulaciones auditadas y conservación de unidades')

  await cdp('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true })
  await modulo('ingresos'); await abrir()
  await llenar('form-operacion', { 'Proveedor *': proveedor._id, 'Factura / referencia *': 'Prueba móvil' }); await agregar(1)
  assert.equal(await evaluar(`document.documentElement.scrollWidth <= innerWidth`), true, 'La pantalla desborda en móvil')
  assert.equal(await evaluar(`Math.abs(document.querySelector('.scrim').getBoundingClientRect().width - innerWidth) < 2`), true)
  await mkdir('test-results', { recursive: true })
  await writeFile('test-results/ingreso-movil.png', Buffer.from((await cdp('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
  await click('Cancelar'); await salir()
  assert.deepEqual(errores, [], 'Hay excepciones JavaScript')
  console.log('OK: formulario móvil y cierre de sesión')
} catch (error) {
  await mkdir('test-results', { recursive: true })
  await writeFile('test-results/operaciones-error.png', Buffer.from((await cdp('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
  console.error(await evaluar(`document.body.innerText`))
  throw error
} finally { await cdp('Fetch.disable').catch(() => {}); navegador.cerrar() }
