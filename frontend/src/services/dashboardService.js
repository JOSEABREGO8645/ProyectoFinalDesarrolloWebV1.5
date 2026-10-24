import api from './api'
export const obtenerDashboard = async filtros => (await api.get('/dashboard', { params: filtros })).data
