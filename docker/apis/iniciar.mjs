import { readFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawn } from 'node:child_process'

const raiz=resolve(dirname(fileURLToPath(import.meta.url)),'../..')
const config=JSON.parse(await readFile(resolve(raiz,'backend/storage/app/private/apis-demo/config.json'),'utf8'))
const hijos=[]
for(const tipo of ['facturacion','pagos']){
 const c=config[tipo]
 if(!c||!/^[a-f0-9]{64}$/.test(c.token)||!Number.isInteger(c.puerto))throw new Error('Ejecute primero php artisan libreria:apis-demo desde backend/.')
 const hijo=spawn(process.execPath,[resolve(raiz,'docker/apis/simulador.mjs')],{cwd:raiz,windowsHide:true,stdio:'inherit',env:{...process.env,API_TIPO:tipo,API_TOKEN:c.token,API_PUERTO:String(c.puerto),API_HOST:'127.0.0.1',API_MONGO_URI:'',API_MONGO_DATABASE:'',API_DATOS:resolve(raiz,'backend/storage/app/private/apis-demo/'+tipo+'.json')}})
 hijos.push(hijo);hijo.on('exit',codigo=>{if(codigo){for(const otro of hijos)otro.kill();process.exitCode=1}})
}
for(const senal of ['SIGINT','SIGTERM'])process.on(senal,()=>{for(const hijo of hijos)hijo.kill()})
process.on('exit',()=>{for(const hijo of hijos)hijo.kill()})
