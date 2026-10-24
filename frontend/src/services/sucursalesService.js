import api from './api'

// filtros: { q, activo (1/0), orden, dir }
export const obtenerSucursales = async (filtros = {}) =>
  (await api.get('/sucursales/buscar', { params: filtros })).data

export const obtenerSucursal = async (id) =>
  (await api.get(`/sucursales/${id}`)).data

export const agregarSucursal = async (datos) =>
  (await api.post('/sucursales', datos)).data

export const editarSucursal = async (id, datos) =>
  (await api.put(`/sucursales/${id}`, datos)).data

export const eliminarSucursal = async (id) =>
  (await api.delete(`/sucursales/${id}`)).data