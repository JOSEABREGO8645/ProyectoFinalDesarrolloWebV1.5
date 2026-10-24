export function validarContacto(datos) {
  if (!datos.nombre.trim()) return 'Complete el nombre'
  if (datos.telefono && !/^[0-9+\-\s()]+$/.test(datos.telefono)) {
    return 'El teléfono solo puede contener números, espacios, paréntesis, + y guiones'
  }
  if (datos.correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.correo)) {
    return 'Escriba un correo electrónico válido'
  }
  return ''
}

export const limpiarNit = nit => nit.replace(/[-\s]/g, '').toUpperCase()
