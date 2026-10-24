import api from './api'

export const obtenerProductos = async (filtros = {}) => (await api.get('/productos/buscar', { params: filtros })).data
export const obtenerProducto = async id => (await api.get(`/productos/${id}`)).data
export const agregarProducto = async datos => (await api.post('/productos', datos)).data
export const editarProducto = async (id, datos) => (await api.put(`/productos/${id}`, datos)).data
export const eliminarProducto = async id => (await api.delete(`/productos/${id}`)).data
