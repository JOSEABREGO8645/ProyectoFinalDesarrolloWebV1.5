import assert from 'node:assert/strict'
import { setTimeout as pausa } from 'node:timers/promises'

export async function conectarNavegador() {
  const navegador = process.env.CDP_URL || 'http://127.0.0.1:9222'
  const paginas = await (await fetch(`${navegador}/json/list`)).json()
  const paginasApp = paginas.filter(p => p.type === 'page' && !/^(edge|chrome|devtools|chrome-extension):/.test(p.url))
  const pagina = paginasApp.find(p => p.url.startsWith(process.env.E2E_FRONTEND_URL || 'http://127.0.0.1:5175'))
    || paginasApp.find(p => /^https?:/.test(p.url)) || paginasApp.find(p => p.url === 'about:blank')
  assert.ok(pagina, 'No hay una pestaña disponible para la prueba')
  const socket = new WebSocket(pagina.webSocketDebuggerUrl)
  await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject })
  let secuencia = 0
  const pendientes = new Map()
  const errores = []
  let interceptar = null
  const eventos = new Map()
  socket.onmessage = evento => {
    const mensaje = JSON.parse(evento.data)
    if (mensaje.method === 'Runtime.exceptionThrown') errores.push(mensaje.params.exceptionDetails.text)
    if (mensaje.method === 'Fetch.requestPaused') {
      const tarea = interceptar
        ? interceptar(mensaje.params)
        : cdp('Fetch.failRequest', { requestId: mensaje.params.requestId, errorReason: 'Failed' })
      Promise.resolve(tarea).catch(error => errores.push(error.message))
    }
    eventos.get(mensaje.method)?.(mensaje.params)
    if (mensaje.id) {
      const pendiente = pendientes.get(mensaje.id)
      pendientes.delete(mensaje.id)
      if (!pendiente) return
      if (mensaje.error) pendiente.reject(new Error(mensaje.error.message))
      else pendiente.resolve(mensaje.result)
    }
  }
  function cdp(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = ++secuencia
      pendientes.set(id, { resolve, reject })
      socket.send(JSON.stringify({ id, method, params }))
    })
  }
  async function evaluar(expression) {
    const respuesta = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })
    if (respuesta.exceptionDetails) throw new Error(respuesta.exceptionDetails.exception?.description || respuesta.exceptionDetails.text)
    return respuesta.result.value
  }
  async function esperar(expression, mensaje) {
    const limite = Date.now() + 12000
    while (Date.now() < limite) {
      if (await evaluar(expression)) return
      await pausa(100)
    }
    throw new Error(mensaje)
  }
  async function click(texto, selector = 'button') {
    await evaluar(`(() => {
      const boton = [...document.querySelectorAll(${JSON.stringify(selector)})].find(b => b.textContent.trim() === ${JSON.stringify(texto)});
      if (!boton) throw new Error('No se encontró el botón: ' + ${JSON.stringify(texto)});
      boton.click();
    })()`)
  }
  async function llenar(id, valores) {
    await evaluar(`(() => {
      const formulario = document.getElementById(${JSON.stringify(id)});
      for (const [etiqueta, valor] of Object.entries(${JSON.stringify(valores)})) {
        const label = [...formulario.querySelectorAll('label')].find(l => l.querySelector('span')?.textContent.trim() === etiqueta);
        if (!label) throw new Error('No se encontró el campo: ' + etiqueta);
        const input = label.querySelector('input, select, textarea');
        input.value = valor;
        input.dispatchEvent(new Event(input.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
      }
    })()`)
  }
  async function buscar(texto) {
    await evaluar(`(() => {
      const input = document.querySelector('.search input');
      input.value = ${JSON.stringify(texto)};
      input.dispatchEvent(new Event('input', { bubbles: true }));
    })()`)
  }
  async function navegar(url) {
    let temporizador
    const cargada = new Promise((resolve, reject) => {
      temporizador = setTimeout(() => reject(new Error('No terminó la navegación inicial')), 15000)
      eventos.set('Page.domContentEventFired', resolve)
    })
    try {
      const destino = new URL(url)
      destino.searchParams.set('e2e', `${Date.now()}-${Math.random()}`)
      await cdp('Page.navigate', { url: destino.href })
      await cargada
    } finally { clearTimeout(temporizador) }
  }
  return { cdp, evaluar, esperar, click, llenar, buscar, navegar, errores,
    interceptar: callback => { interceptar = callback },
    escuchar: (evento, callback) => eventos.set(evento, callback),
    cerrar: () => socket.close() }
}
