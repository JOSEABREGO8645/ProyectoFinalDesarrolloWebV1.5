import api from './api'

export const obtenerProveedores = async (filtros = {}) => (await api.get('/proveedores/buscar', { params: filtros })).data
export const obtenerProveedor = async id => (await api.get(`/proveedores/${id}`)).data
export const agregarProveedor = async datos => (await api.post('/proveedores', datos)).data
export const editarProveedor = async (id, datos) => (await api.put(`/proveedores/${id}`, datos)).data
export const eliminarProveedor = async id => (await api.delete(`/proveedores/${id}`)).data
