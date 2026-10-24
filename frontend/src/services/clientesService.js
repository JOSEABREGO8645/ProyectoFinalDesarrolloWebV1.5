import api from './api'

export const obtenerClientes = async (filtros = {}) => (await api.get('/clientes/buscar', { params: filtros })).data
export const obtenerCliente = async id => (await api.get(`/clientes/${id}`)).data
export const obtenerClientePorNit = async nit => (await api.get(`/clientes/por-nit/${encodeURIComponent(nit)}`)).data
export const agregarCliente = async datos => (await api.post('/clientes', datos)).data
export const editarCliente = async (id, datos) => (await api.put(`/clientes/${id}`, datos)).data
export const eliminarCliente = async id => (await api.delete(`/clientes/${id}`)).data
