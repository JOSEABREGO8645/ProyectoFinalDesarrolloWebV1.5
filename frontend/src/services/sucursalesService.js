import api from './api'

export const obtenerSucursales = async () =>
  (await api.get('/sucursales')).data