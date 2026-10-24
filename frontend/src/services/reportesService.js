import api from './api'
export const obtenerTiposReporte = async () => (await api.get('/reportes')).data
export const obtenerReporte = async (tipo, filtros) => (await api.get(`/reportes/${tipo}`, { params: filtros })).data
export const obtenerProductosReporte = async q => (await api.get('/reportes/productos', { params: { q } })).data
export async function descargarReporte(tipo, filtros) {
  try { return (await api.get(`/reportes/${tipo}`, { params: { ...filtros, formato: 'csv' }, responseType: 'blob' })).data }
  catch (error) {
    if (error.response?.data instanceof Blob) {
      try { error.response.data = JSON.parse(await error.response.data.text()) } catch { /* Se conserva el error de conexión. */ }
    }
    throw error
  }
}
