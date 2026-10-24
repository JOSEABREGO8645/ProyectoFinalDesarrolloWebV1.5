import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { Accept: 'application/json' }
})

/** Convierte cualquier error de Axios en un mensaje comprensible para el usuario. */
export function mensajeError(e) {
  const d = e?.response?.data
  if (d?.errors) return Object.values(d.errors)[0][0]
  return d?.message || (e?.code === 'ERR_NETWORK'
    ? 'No hay conexión con el servidor. ¿Está iniciado el backend?'
    : 'Ocurrió un error inesperado')
}

export default api