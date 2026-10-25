// Impresión nativa de Chromium. Requiere un perfil exclusivo Edge/CDP en 9222.
import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { readFile, writeFile, stat } from 'node:fs/promises'
import { resolve, sep, extname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { conectarNavegador } from '../../frontend/tests/browser.mjs'

const docs = fileURLToPath(new URL('../', import.meta.url))
const indice = JSON.parse(await readFile(resolve(docs, 'documentos.json'), 'utf8'))
const tipos = { '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.svg': 'image/svg+xml', '.png': 'image/png' }
const servidor = createServer(async (req, res) => {
  try {
    const nombre = decodeURIComponent(new URL(req.url, 'http://127.0.0.1').pathname).replace(/^\//, '')
    const ruta = resolve(docs, nombre)
    if (!ruta.startsWith(resolve(docs) + sep) || !(await stat(ruta)).isFile()) throw Error('No disponible')
    res.writeHead(200, { 'Content-Type': tipos[extname(ruta)] || 'application/octet-stream' })
    res.end(await readFile(ruta))
  } catch { res.writeHead(404); res.end('No disponible') }
})
await new Promise((ok, error) => { servidor.once('error', error); servidor.listen(8766, '127.0.0.1', ok) })
const n = await conectarNavegador()
const validacion = []
try {
  await n.cdp('Page.enable'); await n.cdp('Runtime.enable')
  await n.cdp('Emulation.setEmulatedMedia', { media: 'print' })
  for (const doc of indice.documentos) {
    await n.cdp('Emulation.setDeviceMetricsOverride', { width: doc.horizontal ? 1009 : 680, height: 1000, deviceScaleFactor: 1, mobile: false })
    await n.navegar('http://127.0.0.1:8766/' + doc.fuente)
    await n.evaluar('document.fonts.ready.then(()=>Promise.all([...document.images].map(i=>i.decode()))).then(()=>true)')
    const contenido = await n.evaluar(`({secciones:document.querySelectorAll('.hoja').length,imagenes:[...document.images].every(i=>i.complete&&i.naturalWidth>0),autor:document.body.textContent.includes('José Alfredo Davila Abrego'),carne:document.body.textContent.includes('7490236074')})`)
    assert.equal(contenido.secciones, doc.secciones)
    assert.ok(contenido.imagenes && contenido.autor && contenido.carne)
    const impreso = await n.cdp('Page.printToPDF', {
      printBackground: true, preferCSSPageSize: true, displayHeaderFooter: true, generateTaggedPDF: true,
      headerTemplate: '<div style="font:8px Arial;color:#666;width:100%;margin:0 15mm">LIBRERÍA SAN FRANCISCO · DESARROLLO WEB</div>',
      footerTemplate: '<div style="font:8px Arial;color:#666;width:100%;margin:0 15mm;display:flex;justify-content:space-between"><span>' + doc.titulo + '</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>',
      marginTop: 0.63, marginBottom: 0.67, marginLeft: 0.59, marginRight: 0.59,
    })
    const pdf = Buffer.from(impreso.data, 'base64')
    assert.equal(pdf.subarray(0, 5).toString(), '%PDF-')
    assert.ok(pdf.length > 10000)
    const binario = pdf.toString('latin1')
    const nodosPaginas = [...binario.matchAll(/\/Type\s*\/Pages\b[\s\S]*?endobj/g)]
    const paginas = Math.max(...nodosPaginas.map(m => Number(m[0].match(/\/Count\s+(\d+)/)?.[1])))
    assert.ok(paginas > 0, 'No se pudo verificar el número de páginas')
    assert.equal(paginas, [...binario.matchAll(/\/Type\s*\/Page\b/g)].length)
    assert.equal(paginas, doc.secciones, 'El contenido se desborda de las páginas previstas: ' + doc.pdf)
    await writeFile(resolve(docs, doc.pdf), pdf)
    validacion.push({ archivo: doc.pdf, paginas, secciones: doc.secciones, bytes: pdf.length, imagenes_validas: true, identidad_verificada: true })
    console.log(`${doc.pdf}: ${paginas} páginas (${doc.secciones} secciones), ${Math.round(pdf.length / 1024)} KiB.`)
  }
  assert.deepEqual(n.errores, [])
  await writeFile(resolve(docs, 'evidencias/validacion-documental.json'), JSON.stringify({ fecha: '2026-10-24', documentos: validacion }, null, 2) + '\n')
} finally {
  await n.cdp('Emulation.setEmulatedMedia', { media: 'screen' })
  n.cerrar(); await new Promise(ok => servidor.close(ok))
}
