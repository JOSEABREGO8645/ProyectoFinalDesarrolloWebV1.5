import { mkdir, readFile, writeFile, rename } from 'node:fs/promises'
import { dirname } from 'node:path'
import { randomUUID } from 'node:crypto'

function anterior(registro, huella) {
  if (registro.huella !== huella) {
    throw Object.assign(new Error('La clave ya corresponde a otro contenido.'), { estado: 409 })
  }
  return registro.respuesta
}

class HistorialArchivoRepository {
  tipo = 'archivo'
  registros = new Map()
  constructor(ruta) { this.ruta = ruta }
  async abrir() {
    try {
      const guardado = JSON.parse(await readFile(this.ruta, 'utf8'))
      if (guardado.version !== 1 || !Array.isArray(guardado.registros)) throw Error('Formato de historial inválido')
      this.registros = new Map(guardado.registros)
    } catch (e) { if (e.code !== 'ENOENT') throw e }
    return this
  }
  async confirmar(clave, huella, respuesta) {
    if (this.registros.has(clave)) return anterior(this.registros.get(clave), huella)
    const siguientes = new Map(this.registros)
    siguientes.set(clave, { huella, respuesta })
    await mkdir(dirname(this.ruta), { recursive: true })
    const temporal = this.ruta + '.' + randomUUID() + '.tmp'
    await writeFile(temporal, JSON.stringify({ version: 1, registros: [...siguientes] }), { flag: 'wx', mode: 0o600 })
    await rename(temporal, this.ruta)
    this.registros = siguientes
    return respuesta
  }
  async salud() { return true }
  async cerrar() {}
}

class HistorialMongoRepository {
  tipo = 'mongodb'
  constructor(cliente, base) { this.cliente = cliente; this.base = base }
  async abrir() {
    await this.cliente.connect()
    this.db = this.cliente.db(this.base)
    this.coleccion = this.db.collection('comprobantes')
    await this.salud()
    return this
  }
  async confirmar(clave, huella, respuesta) {
    const existente = await this.coleccion.findOne({ _id: clave })
    if (existente) return anterior(existente, huella)
    try {
      await this.coleccion.insertOne({ _id: clave, huella, respuesta })
      return respuesta
    } catch (error) {
      // Otro proceso puede ganar la inserción. El _id único resuelve la carrera.
      if (error.code !== 11000) throw error
      const ganador = await this.coleccion.findOne({ _id: clave })
      if (!ganador) throw Error('No se pudo recuperar el comprobante concurrente.')
      return anterior(ganador, huella)
    }
  }
  async salud() { await this.db.command({ ping: 1 }); return true }
  async cerrar() { await this.cliente.close() }
}

export async function abrirHistorial({ ruta, uri, base }) {
  if (!uri) return new HistorialArchivoRepository(ruta).abrir()
  if (!/^[a-zA-Z0-9_-]{1,38}$/.test(base || '')) throw Error('Falta una base válida del proveedor.')
  const { MongoClient } = await import('mongodb')
  const cliente = new MongoClient(uri, {
    maxPoolSize: 4, serverSelectionTimeoutMS: 5000, connectTimeoutMS: 5000,
    writeConcern: { w: 'majority', wtimeoutMS: 5000 },
  })
  try { return await new HistorialMongoRepository(cliente, base).abrir() }
  catch { await cliente.close(); throw Error('No se pudo abrir el historial MongoDB del proveedor.') }
}
