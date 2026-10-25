import test from 'node:test'
import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { once } from 'node:events'
import { randomBytes, randomUUID } from 'node:crypto'
import { mkdir, readFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import net from 'node:net'
import { setTimeout as pausa } from 'node:timers/promises'

async function puertoLibre() {
  const s=net.createServer();s.listen(0,'127.0.0.1');await once(s,'listening')
  const puerto=s.address().port;await new Promise(r=>s.close(r));return puerto
}
for(const tipo of ['facturacion','pagos']) {
  test(tipo+': contrato HTTP, importes, concurrencia, persistencia y autenticación',async()=>{
    const puerto=await puertoLibre(), token=randomBytes(32).toString('hex')
    const carpeta=resolve('frontend/test-results/contrato-'+randomUUID())
    await mkdir(carpeta,{recursive:true})
    const archivo=resolve(carpeta,tipo+'.json')
    let proceso
    const url='http://127.0.0.1:'+puerto+(tipo==='facturacion'?'/v1/facturas':'/v1/comprobantes-pago')
    async function iniciar() {
      proceso=spawn(process.execPath,['docker/apis/simulador.mjs'],{env:{...process.env,API_TIPO:tipo,API_PUERTO:String(puerto),API_TOKEN:token,API_HOST:'127.0.0.1',API_MONGO_URI:'',API_MONGO_DATABASE:'',API_DATOS:archivo},windowsHide:true,stdio:'ignore'})
      for(let i=0;i<100;i++){
        if(proceso.exitCode!==null)throw new Error('El servicio no inició')
        try{if((await fetch('http://127.0.0.1:'+puerto+'/salud')).ok)return}catch{}
        await pausa(30)
      }
      throw new Error('No respondió el servicio')
    }
    async function detener(){if(proceso&&proceso.exitCode===null){const fin=once(proceso,'exit');proceso.kill();await fin}}
    const d={documento_id:'aaaaaaaaaaaaaaaaaaaaaaaa',numero:1,moneda:'GTQ',total_centavos:303,sucursal:{id:'bbbbbbbbbbbbbbbbbbbbbbbb',nombre:'Pruebas'},receptor:{nombre:'Consumidor Final',identificador:'CF'},items:[{codigo:'0001',nombre:'Cuaderno',cantidad:3,precio_centavos:101,subtotal_centavos:303}]}
    const clave=randomBytes(32).toString('hex')
    const enviar=(body=d,credencial=token,key=clave)=>fetch(url,{method:'POST',headers:{Authorization:'Bearer '+credencial,'Idempotency-Key':key,'Content-Type':'application/json'},body:JSON.stringify(body)})
    try{
      await iniciar()
      assert.equal((await enviar(d,'incorrecto')).status,401)
      assert.equal((await enviar({...d,total_centavos:1})).status,422)
      assert.equal((await enviar(d,token,'sin-clave')).status,422)
      const respuestas=await Promise.all(Array.from({length:6},()=>enviar().then(r=>{assert.equal(r.status,200);return r.json()})))
      for(const r of respuestas)assert.deepEqual(r,respuestas[0])
      assert.equal(respuestas[0].entorno,'simulado')
      assert.equal(respuestas[0].validez,tipo==='facturacion'?'SIN_VALOR_FISCAL':'SIN_MOVIMIENTO_BANCARIO')
      assert.equal((await enviar({...d,numero:2})).status,409)
      assert.equal(JSON.parse(await readFile(archivo,'utf8')).registros.length,1)
      await detener();await iniciar()
      assert.deepEqual(await (await enviar()).json(),respuestas[0])
    } finally {await detener()}
  })
}
