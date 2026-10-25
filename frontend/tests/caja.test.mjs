import test from 'node:test'
import assert from 'node:assert/strict'
import { leerCodigo, centavos } from '../src/utils/caja.js'
test('lector conserva ceros y códigos alfanuméricos y admite multiplicador',()=>{
  assert.deepEqual(leerCodigo('001234'),{codigo:'001234',cantidad:1})
  assert.deepEqual(leerCodigo('5*001234'),{codigo:'001234',cantidad:5})
  assert.deepEqual(leerCodigo(' 3 * LIBRO-01 '),{codigo:'LIBRO-01',cantidad:3})
  for(const texto of ['0*123','100001*123','-1*123','1.5*123','2*','1*2*3','', 'a'.repeat(41)])assert.throws(()=>leerCodigo(texto))
})
test('efectivo en centavos exactos y sin notación científica',()=>{
  assert.equal(centavos('10.01'),1001);assert.equal(centavos('0'),0);assert.equal(centavos('99999999.99'),9999999999)
  for(const entrada of ['','-1','1.001','1e3','100000000.00','texto'])assert.equal(centavos(entrada),null)
})
