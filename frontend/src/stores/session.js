import { reactive } from 'vue'
import api, { aceptarSesion, descartarSesion, observarSesion, renovarAccessToken, mensajeError, mutacionesEnCurso } from '../services/api'
import { notificar, ui } from './ui'

export const session = reactive({ usuario: null, listo: false, error: '', cerrando: false, cambiandoSucursal: false })
export const esSuper = () => session.usuario?.rol === 'superusuario'
export const puede = modulo => !!session.usuario && (modulo === 'inicio' ? true : modulo === 'dashboard'
  ? ['catalogo', 'ventas', 'ingresos', 'traslados'].some(puede)
  : esSuper() || (modulo !== 'usuarios' && session.usuario.modulos.includes(modulo)))
let temporizador

observarSesion(datos => {
  clearTimeout(temporizador)
  const teniaSesion = !!session.usuario
  if (session.usuario?.sucursal_id && datos?.usuario && session.usuario.sucursal_id !== datos.usuario.sucursal_id && ui.confirm) {
    ui.confirm.resolver(false); ui.confirm = null
  }
  session.usuario = datos?.usuario ?? null
  if (datos) {
    session.listo = true
    session.error = ''
    temporizador = setTimeout(() => renovarAccessToken().catch(() => {}), Math.max(1, datos.expires_in - 30) * 1000)
  } else if (teniaSesion) {
    if (ui.confirm) { ui.confirm.resolver(false); ui.confirm = null }
    notificar('La sesión terminó. Inicie sesión nuevamente.', 'info')
  }
})

export async function inicializarSesion() {
  session.error = ''
  try {
    await renovarAccessToken()
    session.listo = true
  } catch (error) {
    if (error.response?.status === 401) session.listo = true
    else { session.listo = false; session.error = mensajeError(error) }
  }
}

export async function iniciarSesion(usuario, password) {
  const { data } = await api.post('/auth/login', { usuario, password }, { omitirRenovacion: true })
  descartarSesion()
  window.location.hash = 'inicio'
  aceptarSesion(data)
}

export async function cerrarSesion() {
  if (session.cerrando) return
  session.cerrando = true
  try {
    await api.post('/auth/logout', {}, { omitirRenovacion: true })
    descartarSesion()
    window.location.hash = ''
  } finally {
    session.cerrando = false
  }
}

export async function cambiarSucursal(id) {
  if (session.cambiandoSucursal || mutacionesEnCurso.value || id === session.usuario?.sucursal_id) return
  session.cambiandoSucursal = true
  try {
    const { data } = await api.post('/auth/sucursal', { sucursal_id: id })
    if (ui.confirm) { ui.confirm.resolver(false); ui.confirm = null }
    aceptarSesion(data)
    notificar('Sucursal activa: ' + data.usuario.sucursal_nombre, 'ok')
  } catch (e) {
    await renovarAccessToken().catch(() => {})
    throw e
  } finally { session.cambiandoSucursal = false }
}
