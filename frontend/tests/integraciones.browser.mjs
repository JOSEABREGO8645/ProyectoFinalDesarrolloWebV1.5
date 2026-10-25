import assert from 'node:assert/strict'
import { mkdir, writeFile } from 'node:fs/promises'
import { conectarNavegador } from './browser.mjs'

const n = await conectarNavegador()
const {cdp,evaluar,esperar,llenar,click} = n
const api = (url,method='get',data) => evaluar(`(async()=>{const a=(await import('/src/services/api.js')).default;const r=await a.request({url:${JSON.stringify(url)},method:${JSON.stringify(method)},data:${JSON.stringify(data)},validateStatus:()=>true});return {status:r.status,data:r.data}})()`)
async function modulo(tipo) {
  await evaluar(`location.hash=${JSON.stringify(tipo)}`)
  await esperar(`document.querySelector('h1')?.textContent.toLowerCase()===${JSON.stringify(tipo)}&&!document.querySelector('.spinner')`,'No abrió el módulo')
}
async function detalle(tipo,id) {
  await modulo(tipo)
  if (tipo === 'ventas') await click('Historial y kardex')
  await esperar(`!!document.querySelector('[data-documento="${id}"] button')`,'No listó el documento')
  await evaluar(`document.querySelector('[data-documento="${id}"] button').click()`)
  await esperar(`!!document.querySelector('.comprobante')`,'No abrió el comprobante')
}
try {
  await cdp('Page.enable'); await cdp('Runtime.enable'); await cdp('Network.enable')
  await cdp('Network.clearBrowserCookies')
  await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:900,deviceScaleFactor:1,mobile:false})
  await n.navegar((process.env.E2E_FRONTEND_URL||'http://127.0.0.1:5175')+'/')
  await esperar(`!!document.getElementById('form-login')`,'No mostró login')
  await llenar('form-login',{'Usuario':process.env.E2E_USER,'Contraseña':process.env.E2E_PASSWORD})
  await click('Iniciar sesión')
  await esperar(`!!document.querySelector('.bienvenida')`,'No mostró Inicio')
  const productos=(await api('/catalogos/productos')).data
  const p=productos.find(p=>p.codigo==='000000-OPS-E2E')
  const proveedor=(await api('/catalogos/proveedores')).data.find(p=>p.nombre==='Proveedor Operaciones E2E')
  const ingreso=(await api('/ingresos','post',{operacion_id:crypto.randomUUID(),proveedor_id:proveedor._id,motivo:'Integración navegador',items:[{producto_id:p._id,cantidad:4}]})).data
  const venta=(await api('/ventas','post',{operacion_id:crypto.randomUUID(),codigo:'4321',efectivo:'10.00',items:[{producto_id:p._id,cantidad:1}]})).data
  assert.ok(ingreso._id&&venta._id)
  const antes=(await api('/inventario/movimientos')).data.length
  await detalle('ventas',venta._id)
  await click('Generar factura de prueba')
  await esperar(`document.querySelector('.comprobante')?.textContent.includes('FAC-DEMO-')`,'No confirmó factura por HTTP')
  assert.ok((await evaluar(`document.querySelector('.comprobante').textContent`)).includes('SIN VALOR FISCAL'))
  const factura=(await api('/ventas/'+venta._id)).data.integraciones.facturacion
  const replay=await api('/ventas/'+venta._id+'/factura-prueba','post')
  assert.equal(replay.status,200);assert.equal(replay.data.referencia,factura.referencia)
  await click('Cerrar','.dialog footer button')
  await detalle('ingresos',ingreso._id)
  await click('Generar pago de prueba')
  await esperar(`document.querySelector('.comprobante')?.textContent.includes('PAGO-DEMO-')`,'No confirmó pago por HTTP')
  assert.ok((await evaluar(`document.querySelector('.comprobante').textContent`)).includes('SIN MOVIMIENTO BANCARIO'))
  await mkdir('test-results',{recursive:true})
  const captura=await cdp('Page.captureScreenshot',{format:'png'})
  await writeFile('test-results/integraciones-comprobante.png',Buffer.from(captura.data,'base64'))
  await click('Cerrar','.dialog footer button')
  await evaluar(`document.querySelector('.fab').click()`)
  await esperar(`!!document.getElementById('form-operacion')&&!document.querySelector('.spinner')`,'No abrió ingreso')
  await evaluar(`document.querySelector('.integracion-box summary').click()`)
  await llenar('form-operacion',{'USD para convertir (opcional)':'10.01'})
  await click('Consultar Banco de Guatemala')
  await esperar(`document.querySelector('.integracion-box')?.textContent.includes('Q 76.58')`,'No convirtió la referencia SOAP independiente de pruebas')
  await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true})
  assert.equal(await evaluar(`document.documentElement.scrollWidth<=window.innerWidth`),true)
  await click('Cancelar','.dialog footer button')
  assert.equal((await api('/inventario/movimientos')).data.length,antes)
  await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:900,deviceScaleFactor:1,mobile:false})
  assert.deepEqual(n.errores,[])
  console.log('OK: factura y pago por HTTP independiente, reintento idéntico, referencia SOAP y conversión exacta, móvil y kardex sin cambios')
} finally { n.cerrar() }
