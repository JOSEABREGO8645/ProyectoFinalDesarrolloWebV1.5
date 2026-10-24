export const MODULOS = [
  { id: 'ventas', titulo: 'Ventas', icono: 'point_of_sale' },
  { id: 'inicio', titulo: 'Inicio', icono: 'home', asignable: false },
  { id: 'dashboard', titulo: 'Dashboard', icono: 'dashboard', asignable: false },
  { id: 'sucursales', titulo: 'Sucursales', icono: 'store' },
  { id: 'catalogo', titulo: 'Catálogo', icono: 'menu_book' },
  { id: 'proveedores', titulo: 'Proveedores', icono: 'storefront' },
  { id: 'clientes', titulo: 'Clientes', icono: 'group' },
  { id: 'ingresos', titulo: 'Ingresos', icono: 'inventory_2' },
  { id: 'traslados', titulo: 'Traslados', icono: 'local_shipping' },
  { id: 'reportes', titulo: 'Reportes', icono: 'assessment' },
  { id: 'usuarios', titulo: 'Usuarios', icono: 'admin_panel_settings', soloSuper: true }
]

export const MODULOS_ASIGNABLES = MODULOS.filter(modulo => !modulo.soloSuper && modulo.asignable !== false)
