export async function leerEnCaja(n, texto) {
  await n.esperar("!!document.querySelector('[aria-label=\"Buscar o escanear producto\"]')", 'No abrió la caja')
  await n.evaluar(`(()=>{const i=document.querySelector('[aria-label="Buscar o escanear producto"]');i.focus();i.value=${JSON.stringify(texto)};i.dispatchEvent(new Event('input',{bubbles:true}));i.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}))})()`)
  await n.esperar("!document.querySelector('.pos-caja [role=\"status\"]')?.textContent.includes('Procesando')", 'No terminó la lectura')
}
export async function prepararCobro(n, efectivo, codigo='4321') {
  await n.click('Guardar (facturar)')
  await n.esperar("!!document.getElementById('form-cobro')",'No abrió cobro')
  await n.llenar('form-cobro',{'Efectivo recibido *':efectivo})
  await n.click('Confirmar cobro','.dialog footer button')
  await n.esperar("!!document.getElementById('form-autorizar')",'No pidió autorización')
  await n.llenar('form-autorizar',{'Código de facturación (4 dígitos) *':codigo})
}
export async function facturar(n, efectivo='10.00', codigo='4321') {
  await prepararCobro(n,efectivo,codigo);await n.click('Facturar venta','.dialog footer button')
}
