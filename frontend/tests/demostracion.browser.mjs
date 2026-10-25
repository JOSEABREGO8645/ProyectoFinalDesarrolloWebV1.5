import assert from 'node:assert/strict'
import { mkdir, writeFile } from 'node:fs/promises'
import { conectarNavegador } from './browser.mjs'
const cuentas=JSON.parse(process.env.E2E_CUENTAS)
const n=await conectarNavegador()
const {cdp,evaluar,esperar,llenar,click}=n
const api=path=>evaluar(`(async()=>{const a=(await import('/src/services/api.js')).default;const r=await a.get(${JSON.stringify(path)},{validateStatus:()=>true});return {status:r.status,data:r.data}})()`)
async function entrar(cuenta) {
  await esperar(`!!document.getElementById('form-login')`,'No mostró login')
  await llenar('form-login',{'Usuario':cuenta.usuario,'Contraseña':cuenta.password})
  await click('Iniciar sesión')
  await esperar(`!!document.querySelector('.bienvenida')`,'No mostró bienvenida')
}
async function salir(){await evaluar(`document.querySelector('[aria-label="Cerrar sesión"]').click()`);await esperar(`!!document.getElementById('form-login')`,'No cerró sesión')}
try{
  await cdp('Page.enable');await cdp('Runtime.enable');await cdp('Network.enable')
  await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:1000,deviceScaleFactor:1,mobile:false})
  const anteriores=(await cdp('Network.getAllCookies')).cookies.filter(c=>c.name==='libreria_refresh').map(c=>c.value)
  await n.navegar(process.env.E2E_FRONTEND_URL)
  await esperar(`!!document.getElementById('form-login')||!!document.querySelector('.bienvenida')`,'No abrió demo')
  if(await evaluar(`!!document.querySelector('.bienvenida')`))await salir()
  await entrar(cuentas[0])
  assert.equal(await evaluar(`document.querySelector('.demo-banner')?.textContent`),'Modo demostración · Datos ficticios')
  let cookies=(await cdp('Network.getAllCookies')).cookies
  assert.ok(cookies.some(c=>c.name==='libreria_demo_refresh'&&c.httpOnly))
  assert.deepEqual(cookies.filter(c=>c.name==='libreria_refresh').map(c=>c.value),anteriores)
  await n.navegar(process.env.E2E_FRONTEND_URL+'/#dashboard')
  await esperar(`!!document.querySelector('.dashboard')&&!document.querySelector('.spinner')`,'No recuperó la sesión demo')
  assert.equal((await api('/dashboard')).data.inventario.unidades,123)
  await mkdir('test-results',{recursive:true})
  await writeFile('test-results/demo-dashboard.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png'})).data,'base64'))
  await evaluar(`location.hash='reportes'`)
  await esperar(`!!document.querySelector('.resultado-reporte')&&!document.querySelector('.spinner')`,'No mostró reportes')
  const tipos=(await api('/reportes')).data.reportes
  assert.equal(tipos.length,9)
  for(const tipo of tipos) {
    await evaluar(`(()=>{const s=document.querySelector('[aria-label="Tipo de reporte"]');s.value=${JSON.stringify(tipo.id)};s.dispatchEvent(new Event('change',{bubbles:true}))})()`)
    await esperar(`document.querySelector('.resultado-reporte h2')?.textContent===${JSON.stringify(tipo.titulo)}&&!document.querySelector('.spinner')`,'No abrió '+tipo.id)
    assert.ok(await evaluar(`document.querySelectorAll('.resultado-reporte tbody tr').length>0`),tipo.id)
  }
  await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true})
  assert.equal(await evaluar(`document.documentElement.scrollWidth<=innerWidth`),true)
  await writeFile('test-results/demo-movil.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png'})).data,'base64'))
  await salir();await entrar(cuentas[3])
  assert.equal((await api('/productos')).status,403)
  assert.equal((await api('/reportes')).status,200)
  await salir()
  assert.deepEqual(n.errores,[])
  console.log('OK: demo persistente, aviso visible, sesión/cookie aisladas, 123 unidades, nueve reportes con datos, permisos y móvil')
}finally{await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:1000,deviceScaleFactor:1,mobile:false});n.cerrar()}
