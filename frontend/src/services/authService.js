import api from './api'

export const obtenerPerfil = async () => (await api.get('/auth/me')).data
export const verificarCodigo = async codigo => (await api.post('/auth/verificar-codigo', { codigo })).data
export const obtenerSucursalesCatalogo = async () => (await api.get('/catalogos/sucursales')).data
export const obtenerProveedoresCatalogo = async () => (await api.get('/catalogos/proveedores')).data
