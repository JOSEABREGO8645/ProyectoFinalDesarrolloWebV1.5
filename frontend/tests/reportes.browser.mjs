import assert from 'node:assert/strict'
import { mkdir, readFile, writeFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import { setTimeout as pausa } from 'node:timers/promises'
import { conectarNavegador } from './browser.mjs'

const n=await conectarNavegador(), {cdp,evaluar,esperar,llenar,click}=n
const solicitudes=[]
n.escuchar('Network.requestWillBeSent',evento=>solicitudes.push(evento.request.url))
const frontend=process.env.E2E_FRONTEND_URL||'http://127.0.0.1:5175', password=process.env.E2E_PASSWORD
assert.ok(password)
const api=url=>evaluar(`(async()=>{const api=(await import('/src/services/api.js')).default;const r=await api.get(${JSON.stringify(url)},{validateStatus:()=>true});return {status:r.status,data:r.data};})()`)
async function entrar(usuario) {
  await esperar(`!!document.getElementById('form-login')`,'No mostró login')
  await llenar('form-login',{Usuario:usuario,Contraseña:password});await click('Iniciar sesión')
  await esperar(`!!document.querySelector('.bienvenida')`,'La bienvenida no fue la primera pantalla')
  assert.equal(await evaluar(`location.hash`),'#inicio')
}
async function salir(){await evaluar(`document.querySelector('[aria-label="Cerrar sesión"]').click()`);await esperar(`!!document.getElementById('form-login')`,'No cerró sesión')}
async function abrirReportes(){await evaluar(`location.hash='reportes'`);await esperar(`!!document.querySelector('.resultado-reporte')&&!document.querySelector('.spinner')`,'No cargó Reportes')}
async function seleccionar(tipo,titulo){await evaluar(`(()=>{const i=document.querySelector('[aria-label="Tipo de reporte"]');i.value=${JSON.stringify(tipo)};i.dispatchEvent(new Event('change',{bubbles:true}));})()`);await esperar(`document.querySelector('.resultado-reporte h2')?.textContent===${JSON.stringify(titulo)}&&!document.querySelector('.spinner')`,'No generó '+tipo)}
function contarFilasCsv(texto){let dentro=false,total=0;for(let i=0;i<texto.length;i++){if(texto[i]==='"'){if(dentro&&texto[i+1]==='"')i++;else dentro=!dentro}else if(texto[i]==='\n'&&!dentro)total++}return total-1}

try {
  await cdp('Page.enable');await cdp('Runtime.enable');await cdp('Network.enable');await cdp('Network.clearBrowserCookies')
  await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:1000,deviceScaleFactor:1,mobile:false})
  await n.navegar(`${frontend}/#ventas`);await entrar(process.env.E2E_USER)
  assert.deepEqual(await evaluar(`[...document.querySelectorAll('.rail a')].slice(0,2).map(a=>a.getAttribute('href'))`),['#inicio','#ventas'])
  assert.equal(await evaluar(`document.querySelector('.bienvenida h2').textContent`),'Bienvenido a Librería San Francisco')
  assert.equal(solicitudes.some(url=>/\/api\/ventas(?:[/?]|$)/.test(url)),false,'Abrió Ventas antes de mostrar la bienvenida')
  await esperar(`getComputedStyle(document.querySelector('.bienvenida')).opacity === '1'`,'No terminó la animación de bienvenida')
  await mkdir('test-results',{recursive:true})
  await writeFile('test-results/bienvenida-escritorio.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png'})).data,'base64'))
  await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true})
  assert.equal(await evaluar(`document.documentElement.scrollWidth<=innerWidth`),true)
  await writeFile('test-results/bienvenida-movil.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png'})).data,'base64'))
  await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:1000,deviceScaleFactor:1,mobile:false})
  console.log('OK: Inicio primero y Ventas debajo, bienvenida al iniciar sesión y vista móvil')
  await abrirReportes()
  const perfil=(await api('/auth/me')).data
  const meta=(await api('/reportes')).data
  assert.equal(meta.reportes.length,9)
  const stock=(await api(`/reportes/existencias?sucursal_id=${perfil.sucursal_id}&por_pagina=25`)).data
  assert.ok(stock.paginacion.total>25)
  assert.equal(await evaluar(`document.querySelectorAll('.resultado-reporte tbody tr').length`),25)
  await click('Siguiente');await esperar(`document.querySelector('.resultado-reporte')?.textContent.includes('Página 2 de')&&!document.querySelector('.spinner')`,'No paginó')
  const descargas=resolve('test-results','descargas-reportes-'+Date.now());await mkdir(descargas,{recursive:true})
  await cdp('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:descargas})
  await click('Exportar CSV completo')
  let csv
  for(let i=0;i<120;i++){try{csv=await readFile(resolve(descargas,'reporte-existencias.csv'),'utf8');break}catch{await pausa(100)}}
  assert.ok(csv,'No descargó el CSV');assert.equal(contarFilasCsv(csv),stock.paginacion.total);assert.ok(csv.includes('000-REP-E2E'))
  await click('Imprimir página');await esperar(`!!document.querySelector('.reporte-comprobante')`,'No mostró vista de impresión')
  assert.equal(await evaluar(`document.querySelector('.reporte-comprobante').textContent.includes('Página 2 de')`),true)
  const pdf=await cdp('Page.printToPDF',{printBackground:true,landscape:true})
  assert.ok(Buffer.from(pdf.data,'base64').length>3000);await writeFile('test-results/reporte-existencias.pdf',Buffer.from(pdf.data,'base64'))
  await click('Cerrar','.dialog footer button')
  console.log('OK: paginación, CSV completo con códigos originales y comprobante imprimible en PDF')
  for(const reporte of meta.reportes){await seleccionar(reporte.id,reporte.titulo);assert.equal(await evaluar(`!!document.querySelector('.tabla-reporte')`),true)}
  assert.equal(await evaluar(`document.querySelector('.resultado-reporte').textContent.includes('=PRUEBA()')`),true)
  const auditoriaCsv=await evaluar(`(async()=>{const api=(await import('/src/services/api.js')).default;return (await api.get('/reportes/anulaciones',{params:{sucursal_id:${JSON.stringify(perfil.sucursal_id)},formato:'csv'},responseType:'text'})).data;})()`)
  assert.ok(auditoriaCsv.includes("'=PRUEBA()"))
  assert.equal(await evaluar(`import('/src/utils/reportes.js').then(m=>m.dineroExacto('9999999999000001.41'))`),'Q 9,999,999,999,000,001.41')
  await seleccionar('existencias','Existencias de productos')
  await evaluar(`(()=>{const i=document.querySelector('[aria-label="Buscar en reporte"]');i.value='000-REP-E2E';i.dispatchEvent(new Event('input',{bubbles:true}));})()`)
  await click('Consultar');await esperar(`document.querySelectorAll('.resultado-reporte tbody tr').length===1&&!document.querySelector('.spinner')`,'No aplicó el filtro')
  assert.equal(await evaluar(`document.querySelector('.resultado-reporte tbody').textContent.includes('Libro Reportes E2E')`),true)
  await writeFile('test-results/reportes-escritorio.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png'})).data,'base64'))
  await cdp('Fetch.enable',{patterns:[{urlPattern:'*api/reportes/existencias*'}]});await click('Consultar')
  await esperar(`!!document.querySelector('[role="alert"]')`,'No mostró falla de red');await cdp('Fetch.disable');await click('Reintentar')
  await esperar(`!!document.querySelector('.resultado-reporte')&&!document.querySelector('[role="alert"]')&&!document.querySelector('.spinner')`,'No recuperó el reporte')
  await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true})
  assert.equal(await evaluar(`document.documentElement.scrollWidth<=innerWidth`),true)
  await writeFile('test-results/reportes-movil.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png'})).data,'base64'))
  await evaluar(`document.querySelector('[aria-label="Ir al inicio"]').click()`);await esperar(`!!document.querySelector('.bienvenida')`,'El botón de Inicio no volvió a la bienvenida')
  console.log('OK: nueve reportes, filtros, auditoría, importes exactos, error/reintento y móvil')
  await salir();await entrar('analista.reportes.e2e');await abrirReportes()
  assert.equal(await evaluar(`!!document.querySelector('[aria-label="Sucursal del reporte"]')`),false)
  assert.equal((await api('/reportes/existencias?sucursal_id=000000000000000000000000')).status,403)
  assert.equal((await api('/reportes/existencias?formato=csv&sucursal_id=000000000000000000000000')).status,403)
  await salir();await entrar('sin.reportes.e2e')
  assert.equal(await evaluar(`!!document.querySelector('.rail a[href="#reportes"]')`),false)
  await evaluar(`location.hash='reportes'`);await esperar(`document.querySelector('main [role="alert"]')?.textContent.includes('No tiene permiso')`,'Permitió abrir Reportes sin permiso')
  assert.equal((await api('/reportes')).status,403)
  await salir();assert.deepEqual(n.errores,[])
  console.log('OK: permiso Reportes y aislamiento de sucursales para consulta y exportación')
}catch(e){await mkdir('test-results',{recursive:true});await writeFile('test-results/reportes-error.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png'})).data,'base64'));console.error(await evaluar(`document.body.innerText`));throw e}
finally{await cdp('Fetch.disable').catch(()=>{});n.cerrar()}
