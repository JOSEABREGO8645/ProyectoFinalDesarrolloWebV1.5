import { createRequire } from 'node:module'
import { readdir, mkdir } from 'node:fs/promises'
import { resolve } from 'node:path'
const require=createRequire(import.meta.url)
const base=resolve('node_modules/.cache/newman/_npx')
const carpetas=await readdir(base)
let newman
for(const carpeta of carpetas){try{newman=require(resolve(base,carpeta,'node_modules/newman'));break}catch{}}
if(!newman)throw new Error('Prepare Newman según las instrucciones del README.')
const cuentas=JSON.parse(process.env.E2E_CUENTAS)
await mkdir('test-results',{recursive:true})
await new Promise((done,fail)=>newman.run({
  collection:require('../../docs/postman/demostracion.postman_collection.json'),
  environment:{values:[{key:'base_url',value:process.env.E2E_API_URL||'http://127.0.0.1:8003/api'},...cuentas.flatMap((c,i)=>[{key:'usuario_'+i,value:c.usuario},{key:'password_'+i,value:c.password}])]},
  reporters:['cli','json'],reporter:{json:{export:resolve('test-results',process.env.E2E_POSTMAN_REPORTE||'demostracion-postman.json')}}
},(error,resumen)=>{if(error||resumen.run.failures.length)fail(error||new Error('Falló Postman de demostración'));else done()}))
