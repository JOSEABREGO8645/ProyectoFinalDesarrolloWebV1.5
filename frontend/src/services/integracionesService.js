import api from './api'

export async function generarPrueba(tipo, id) {
  const ruta = tipo === 'ventas' ? 'factura-prueba' : 'pago-prueba'
  return (await api.post(`/${tipo}/${id}/${ruta}`)).data
}
export async function consultarCambio(monto) {
  return (await api.get('/integraciones/tipo-cambio', { params: monto === '' ? {} : { monto_usd: monto } })).data
}
