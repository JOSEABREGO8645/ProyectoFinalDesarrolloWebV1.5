export function leerCodigo(entrada) {
  const texto = String(entrada).trim()
  const partes = texto.match(/^(\d+)\s*\*\s*(.+)$/)
  const cantidad = partes ? Number(partes[1]) : 1
  const codigo = (partes ? partes[2] : texto).trim()
  if (!Number.isSafeInteger(cantidad) || cantidad < 1 || cantidad > 100000) throw new Error('Use una cantidad entera entre 1 y 100000.')
  if (!codigo || codigo.length > 40 || /[\r\n\t*]/.test(codigo)) throw new Error('Ingrese el código de barras o cantidad*código, por ejemplo 5*001234.')
  return { cantidad, codigo }
}
export function centavos(entrada) {
  const texto = String(entrada)
  if (!/^\d{1,8}(\.\d{1,2})?$/.test(texto)) return null
  const [entero, decimal = ''] = texto.split('.')
  return Number(entero) * 100 + Number(decimal.padEnd(2, '0'))
}
export const decimal = valor => (valor / 100).toFixed(2)
