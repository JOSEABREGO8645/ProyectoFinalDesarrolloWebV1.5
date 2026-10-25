import api from './api'

export const crearServicioOperaciones = tipo => ({
  obtener: async filtros => (await api.get(`/${tipo}`, { params: filtros })).data,
  buscar: async id => (await api.get(`/${tipo}/${id}`)).data,
  registrar: async datos => (await api.post(`/${tipo}`, datos)).data,
  anular: async (id, motivo) => (await api.post(`/${tipo}/${id}/anular`, { motivo })).data,
  confirmar: async (id, motivo) => (await api.post(`/${tipo}/${id}/confirmar`, { motivo })).data
})
export const obtenerProductosOperacion = async filtros => (await api.get('/catalogos/productos', { params: filtros })).data
export const obtenerClientesOperacion = async q => (await api.get('/catalogos/clientes', { params: typeof q === 'object' ? q : { q } })).data
export const obtenerDestinos = async () => (await api.get('/catalogos/destinos')).data
export const obtenerKardex = async filtros => (await api.get('/inventario/movimientos', { params: filtros })).data
