import assert from 'node:assert/strict'
import { mkdir, writeFile } from 'node:fs/promises'
import { conectarNavegador } from './browser.mjs'
import { leerEnCaja, facturar } from './caja-helpers.mjs'
const n=await conectarNavegador()
const {cdp,evaluar,esperar,llenar,click}=n
const frontend=process.env.E2E_FRONTEND_URL
const api=(url,method='get',data,headers)=>evaluar(`(async()=>{const a=(await import('/src/services/api.js')).default;const r=await a.request({url:${JSON.stringify(url)},method:${JSON.stringify(method)},data:${JSON.stringify(data)},headers:${JSON.stringify(headers)},validateStatus:()=>true});return {status:r.status,data:r.data}})()`)
async function entrar(usuario,password){
  await esperar(`!!document.getElementById('form-login')`,'No mostró login')
  await llenar('form-login',{'Usuario':usuario,'Contraseña':password});await click('Iniciar sesión')
  await esperar(`!!document.querySelector('.bienvenida')`,'No mostró bienvenida')
}
async function salir(){await evaluar(`document.querySelector('[aria-label="Cerrar sesión"]').click()`);await esperar(`!!document.getElementById('form-login')`,'No cerró sesión')}
async function modulo(tipo){await evaluar(`location.hash=${JSON.stringify(tipo)}`);await esperar(`document.querySelector('h1')?.textContent.toLowerCase()===${JSON.stringify(tipo)}&&!document.querySelector('.spinner')`,'No abrió '+tipo)}
async function cambiar(id){
  await evaluar(`(()=>{const s=document.querySelector('[aria-label="Sucursal activa"]');s.value=${JSON.stringify(id)};s.dispatchEvent(new Event('change',{bubbles:true}))})()`)
  await esperar(`document.querySelector('[aria-label="Sucursal activa"]')?.value===${JSON.stringify(id)}&&!document.querySelector('.cambio-sucursal')`,'No cambió sucursal')
  await esperar(`!document.querySelector('.spinner')`,'No recargó la vista')
}
async function abrir(){
  await evaluar(`document.querySelector('.fab').click()`)
  await esperar(`!!document.getElementById('form-operacion')&&!document.querySelector('.spinner')`,'No abrió formulario')
}
async function agregar(cantidad=1){
  await evaluar(`(()=>{const i=document.querySelector('[aria-label="Buscar producto para detalle"]');i.value='000000-OPS-E2E';i.dispatchEvent(new Event('input',{bubbles:true}))})()`)
  await esperar(`!!document.querySelector('[aria-label="Agregar Producto Operaciones E2E"]:not(:disabled)')`,'No encontró producto con inventario')
  await evaluar(`document.querySelector('[aria-label="Agregar Producto Operaciones E2E"]').click()`)
  await evaluar(`(()=>{const i=document.querySelector('[aria-label="Cantidad de Producto Operaciones E2E"]');i.value=${cantidad};i.dispatchEvent(new Event('input',{bubbles:true}))})()`)
}
async function cerrarDetalle(){await click('Cerrar','.dialog footer button');await esperar(`!document.querySelector('[role="dialog"]')`,'No cerró detalle')}
try{
  await cdp('Page.enable');await cdp('Runtime.enable');await cdp('Network.enable');await cdp('Network.clearBrowserCookies')
  await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:1000,deviceScaleFactor:1,mobile:false})
  await n.navegar(frontend);await entrar(process.env.E2E_USER,process.env.E2E_PASSWORD)
  const perfil=(await api('/auth/me')).data
  const destino=(await api('/catalogos/destinos')).data.find(s=>s.nombre==='Destino Operaciones E2E')
  const proveedor=(await api('/catalogos/proveedores')).data.find(s=>s.nombre==='Proveedor Operaciones E2E')
  const producto=(await api('/catalogos/productos?q=000000-OPS-E2E')).data[0]
  await modulo('usuarios');await evaluar(`document.querySelector('.fab').click()`)
  await esperar(`!!document.getElementById('form-usuario')`,'No abrió Usuarios')
  await llenar('form-usuario',{'Nombre completo *':'Cajero con turno rotativo','Usuario *':'rotativo.multi.e2e','Contraseña *':'RotativoE2E2026!','Código de facturación':'4321','Sucursal asignada *':perfil.sucursal_id})
  await evaluar(`document.querySelector('[aria-label="Asignar sucursal Destino Operaciones E2E"]').click()`)
  for(const modulo of ['Ventas','Ingresos','Traslados','Catálogo'])await evaluar(`[...document.querySelectorAll('#form-usuario .chip')].find(b=>b.textContent.trim().endsWith(${JSON.stringify(modulo)})).click()`)
  await click('Guardar cambios','.dialog footer button')
  await esperar(`!document.getElementById('form-usuario')&&document.querySelector('.list')?.textContent.includes('rotativo.multi.e2e')`,'No guardó usuario')
  const registrado=(await api('/admin/usuarios/buscar?q=rotativo.multi.e2e')).data[0]
  assert.deepEqual(registrado.sucursal_ids,[perfil.sucursal_id,destino._id])
  assert.ok(await evaluar(`document.querySelector('.list').textContent.includes('Destino Operaciones E2E')`))
  const ingreso=await api('/ingresos','post',{operacion_id:crypto.randomUUID(),proveedor_id:proveedor._id,motivo:'Stock para turno rotativo',items:[{producto_id:producto._id,cantidad:10}]})
  assert.equal(ingreso.status,201)
  await salir();await entrar('rotativo.multi.e2e','RotativoE2E2026!')
  assert.equal(await evaluar(`document.querySelectorAll('[aria-label="Sucursal activa"] option').length`),2)
  await modulo('ventas');await leerEnCaja(n,'000000-OPS-E2E')
  await esperar(`document.querySelector('[data-producto="000000-OPS-E2E"] input')?.value==='1'`,'No agregó en caja')
  await facturar(n,'10.00')
  await esperar(`!!document.querySelector('.comprobante')`,'No cobró en origen');await cerrarDetalle()
  await modulo('traslados');await abrir();await agregar(2)
  await llenar('form-operacion',{'Destino *':destino._id,'Motivo del traslado *':'Cambio de turno'})
  await click('Enviar traslado')
  await esperar(`!!document.querySelector('.comprobante')`,'No envió traslado')
  const doc=(await api('/traslados')).data.find(d=>d.motivo==='Cambio de turno')
  assert.equal(await evaluar(`[...document.querySelectorAll('.dialog button')].some(b=>b.textContent.trim()==='Confirmar recepción')`),false)
  assert.equal((await api('/traslados/'+doc._id+'/confirmar','post',{motivo:'Desde origen'})).status,403)
  await cerrarDetalle();await cambiar(destino._id)
  assert.equal((await api('/auth/me')).data.sucursal_id,destino._id)
  const antes=(await api('/catalogos/productos?q=000000-OPS-E2E')).data[0].existencia
  // En una base compartida de suites, el destino puede tener saldos anteriores.
  const ventasAntes=(await api('/ventas')).data.length
  await esperar(`!!document.querySelector('[data-documento="${doc._id}"] button')`,'No listó el traslado recibido')
  await evaluar(`document.querySelector('[data-documento="${doc._id}"] button').click()`)
  await esperar(`!!document.querySelector('.comprobante')`,'No abrió traslado en destino')
  assert.equal(await evaluar(`[...document.querySelectorAll('.dialog button')].some(b=>b.textContent.trim()==='Anular documento')`),false)
  assert.equal((await api('/traslados/'+doc._id+'/anular','post',{motivo:'Desde destino'})).status,403)
  await click('Confirmar recepción','.dialog footer button')
  await esperar(`!!document.querySelector('.dialog textarea')`,'No pidió observación')
  await evaluar(`(()=>{const i=[...document.querySelectorAll('.dialog')].at(-1).querySelector('textarea');i.value='Recepción durante turno';i.dispatchEvent(new Event('input',{bubbles:true}))})()`)
  await evaluar(`[...document.querySelectorAll('.dialog')].at(-1).querySelector('footer button:last-child').click()`)
  await esperar(`document.querySelectorAll('.dialog').length===1&&document.querySelector('.comprobante')?.textContent.includes('confirmado')`,'No recibió en destino')
  assert.equal((await api('/catalogos/productos?q=000000-OPS-E2E')).data[0].existencia,antes+2)
  await cerrarDetalle();await modulo('ventas');await leerEnCaja(n,'000000-OPS-E2E')
  await esperar(`document.querySelector('[data-producto="000000-OPS-E2E"] input')?.value==='1'`,'No agregó en destino')
  await facturar(n,'10.00')
  await esperar(`!!document.querySelector('.comprobante')`,'No cobró en destino')
  assert.equal((await api('/ventas')).data.length,ventasAntes+1)
  assert.equal((await api('/catalogos/productos?q=000000-OPS-E2E')).data[0].existencia,antes+1)
  await cerrarDetalle()
  // Una petición con contexto anterior debe rechazarse sin operar.
  const cruda=await evaluar(`(async()=>{const s=(await import('/src/services/api.js')).default;const token=(await s.get('/auth/me')).config.headers.Authorization;return (await fetch('/api/productos',{headers:{Authorization:token,'X-Sucursal-Id':${JSON.stringify(perfil.sucursal_id)},Accept:'application/json'}})).status})()`)
  assert.equal(cruda,409)
  await n.navegar(frontend+'/#catalogo')
  await esperar(`!!document.querySelector('[aria-label="Sucursal activa"]')&&!document.querySelector('.spinner')`,'No recuperó sesión')
  assert.equal(await evaluar(`document.querySelector('[aria-label="Sucursal activa"]').value`),destino._id)
  await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true})
  assert.equal(await evaluar(`getComputedStyle(document.querySelector('.session-meta')).display!=='none'&&document.documentElement.scrollWidth<=innerWidth`),true)
  await mkdir('test-results',{recursive:true})
  await writeFile('test-results/sucursales-rotativas-movil.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png'})).data,'base64'))
  await salir()
  assert.deepEqual(n.errores,[])
  console.log('OK: alta con varias sucursales, cambio en cabecera, cobro en ambas con inventarios separados, origen anula/destino confirma, pestaña obsoleta y móvil')
}finally{await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:1000,deviceScaleFactor:1,mobile:false});n.cerrar()}
