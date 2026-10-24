export const MODULOS = [
  { id: 'sucursales', titulo: 'Sucursales', icono: 'store' },
  { id: 'catalogo', titulo: 'Catálogo', icono: 'menu_book' },
  { id: 'proveedores', titulo: 'Proveedores', icono: 'storefront' },
  { id: 'clientes', titulo: 'Clientes', icono: 'group' },
  { id: 'usuarios', titulo: 'Usuarios', icono: 'admin_panel_settings', soloSuper: true }
]

export const MODULOS_ASIGNABLES = MODULOS.filter(modulo => !modulo.soloSuper)
