import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  withCredentials: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
})

const renovacion = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  withCredentials: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
})
let accessToken = null
let epoca = 0
let renovando = null
let alCambiar = () => {}

export function observarSesion(callback) { alCambiar = callback }
export function descartarSesion() {
  epoca++
  accessToken = null
  alCambiar(null)
}
export function aceptarSesion(datos) {
  accessToken = datos.access_token
  alCambiar(datos)
}

export async function renovarAccessToken() {
  if (!renovando) {
    const epocaInicial = epoca
    const solicitar = async () => {
      if (epoca !== epocaInicial) throw new Error('La sesión cambió durante la renovación')
      const { data } = await renovacion.post('/auth/refresh')
      if (epoca !== epocaInicial) throw new Error('La sesión cambió durante la renovación')
      aceptarSesion(data)
      return data
    }
    renovando = (navigator.locks
      ? navigator.locks.request('libreria-refresh', solicitar)
      : solicitar())
      .catch(error => {
        if (epoca === epocaInicial && [401, 403].includes(error.response?.status)) descartarSesion()
        throw error
      })
      .finally(() => { renovando = null })
  }
  return renovando
}

api.interceptors.request.use(config => {
  config.epocaSesion = epoca
  config.tokenEnviado = accessToken
  if (accessToken) config.headers.set('Authorization', `Bearer ${accessToken}`)
  else config.headers.delete('Authorization')
  return config
})
api.interceptors.response.use(respuesta => respuesta, async error => {
  const config = error.config
  if (error.response?.status !== 401 || !config || config.omitirRenovacion || /\/auth\/(login|refresh|logout)$/.test(config.url)) throw error
  if (config.epocaSesion !== epoca) throw error
  if (config.reintentado) { descartarSesion(); throw error }
  config.reintentado = true
  if (!accessToken || accessToken === config.tokenEnviado) await renovarAccessToken()
  return api(config)
})

/** Convierte cualquier error de Axios en un mensaje comprensible para el usuario. */
export function mensajeError(e) {
  const d = e?.response?.data
  if (e?.response?.status >= 500) return 'No fue posible completar la operación. Intente nuevamente.'
  if (d?.errors) {
    const primero = Object.values(d.errors).flat()[0]
    if (typeof primero === 'string') return primero
  }
  return d?.message || (e?.code === 'ERR_NETWORK'
    ? 'No hay conexión con el servidor. ¿Está iniciado el backend?'
    : 'Ocurrió un error inesperado')
}

export default api
