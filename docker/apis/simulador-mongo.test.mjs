import test from 'node:test'
import assert from 'node:assert/strict'
import { MongoClient } from 'mongodb'
import { readFile } from 'node:fs/promises'
import { spawn } from 'node:child_process'
import { once } from 'node:events'
import { randomBytes } from 'node:crypto'
import net from 'node:net'
import { setTimeout as pausa } from 'node:timers/promises'

// Solo se lee la URI local en memoria. No se registra ni se pasa por argv.
let uri = process.env.TEST_MONGO_URI
if (!uri) {
  const envLocal = await readFile(new URL('../../backend/.env', import.meta.url), 'utf8')
  uri = envLocal.match(/^DB_URI\s*=\s*["']?([^\r\n"']+)/m)?.[1]
}
assert.ok(uri, 'Configure una conexión MongoDB local para las pruebas.')
async function puertoLibre() {
  const s = net.createServer(); s.listen(0, '127.0.0.1'); await once(s, 'listening')
  const port = s.address().port; await new Promise(ok => s.close(ok)); return port
}
for (const tipo of ['facturacion', 'pagos']) {
  test(tipo + ': idempotencia MongoDB entre procesos y reinicios', async () => {
    const base = 'libreria_apis_test_' + randomBytes(8).toString('hex')
    assert.match(base, /^libreria_apis_test_[a-f0-9]{16}$/)
    const cliente = new MongoClient(uri, { serverSelectionTimeoutMS: 5000 })
    const procesos = []
    const token = randomBytes(32).toString('hex'), clave = randomBytes(32).toString('hex')
    const documento = { documento_id: 'a'.repeat(24), numero: 1, moneda: 'GTQ', total_centavos: 303, sucursal: { id: 'b'.repeat(24), nombre: 'Sucursal ficticia' }, receptor: { nombre: 'Cliente ficticio', identificador: 'CF' }, items: [{ codigo: '0001', nombre: 'Cuaderno', cantidad: 3, precio_centavos: 101, subtotal_centavos: 303 }] }
    const detener = async proceso => { if (proceso.exitCode === null && proceso.signalCode === null) { const fin = once(proceso, 'exit'); proceso.kill(); await fin } }
    async function iniciar() {
      const port = await puertoLibre()
      const proceso = spawn(process.execPath, ['docker/apis/simulador.mjs'], { windowsHide: true, stdio: 'ignore', env: { ...process.env, NODE_ENV: 'test', API_TIPO: tipo, API_PUERTO: String(port), API_HOST: '127.0.0.1', API_TOKEN: token, API_MONGO_URI: uri, API_MONGO_DATABASE: base } })
      procesos.push(proceso)
      const origen = 'http://127.0.0.1:' + port
      for (let i = 0; i < 160; i++) {
        assert.equal(proceso.exitCode, null, 'El proveedor no pudo abrir su base temporal')
        try { const salud = await fetch(origen + '/salud'); if (salud.ok) { assert.equal((await salud.json()).almacen, 'mongodb'); return { proceso, origen } } } catch {}
        await pausa(50)
      }
      throw Error('El proveedor no respondió')
    }
    const enviar = (origen, body = documento, auth = token) => fetch(origen + (tipo === 'facturacion' ? '/v1/facturas' : '/v1/comprobantes-pago'), { method: 'POST', headers: { Authorization: 'Bearer ' + auth, 'Idempotency-Key': clave, 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
    try {
      await cliente.connect()
      const a = await iniciar(), b = await iniciar()
      assert.equal((await enviar(a.origen, documento, 'invalido')).status, 401)
      assert.equal((await enviar(a.origen, { ...documento, total_centavos: 1 })).status, 422)
      const respuestas = await Promise.all(Array.from({ length: 12 }, async (_, i) => {
        const r = await enviar(i % 2 ? a.origen : b.origen); assert.equal(r.status, 200); return r.json()
      }))
      for (const r of respuestas) assert.deepEqual(r, respuestas[0])
      assert.equal(await cliente.db(base).collection('comprobantes').countDocuments(), 1)
      assert.equal((await enviar(b.origen, { ...documento, numero: 2 })).status, 409)
      await detener(a.proceso); await detener(b.proceso)
      const reiniciado = await iniciar()
      assert.deepEqual(await (await enviar(reiniciado.origen)).json(), respuestas[0])
    } finally {
      await Promise.all(procesos.map(detener))
      if (/^libreria_apis_test_[a-f0-9]{16}$/.test(base)) await cliente.db(base).dropDatabase()
      await cliente.close()
    }
  })
}
