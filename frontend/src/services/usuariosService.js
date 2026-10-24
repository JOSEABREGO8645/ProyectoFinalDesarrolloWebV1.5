import api from './api'

export const obtenerUsuarios = async (filtros = {}) => (await api.get('/admin/usuarios/buscar', { params: filtros })).data
export const obtenerUsuario = async id => (await api.get(`/admin/usuarios/${id}`)).data
export const agregarUsuario = async datos => (await api.post('/admin/usuarios', datos)).data
export const editarUsuario = async (id, datos) => (await api.put(`/admin/usuarios/${id}`, datos)).data
export const eliminarUsuario = async id => (await api.delete(`/admin/usuarios/${id}`)).data
