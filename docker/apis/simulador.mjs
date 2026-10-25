import http from 'node:http'
import { createHash, timingSafeEqual } from 'node:crypto'
import { resolve } from 'node:path'
import { abrirHistorial } from './historial.mjs'

const tipo=process.env.API_TIPO, token=process.env.API_TOKEN, puerto=Number(process.env.API_PUERTO||process.env.PORT)
const host=process.env.API_HOST||'127.0.0.1'
if(!['127.0.0.1','0.0.0.0'].includes(host))throw new Error('Host del servicio no permitido.')
if(host==='0.0.0.0'&&process.env.NODE_ENV==='production'&&!process.env.API_MONGO_URI)throw new Error('La publicación requiere historial MongoDB persistente.')
const cambio=tipo==='cambio-prueba'&&process.env.NODE_ENV==='test'
if((!['facturacion','pagos'].includes(tipo)&&!cambio)||(!cambio&&(!token||token.length<16))||!Number.isInteger(puerto)||puerto<1024||puerto>65535)throw new Error('Configuración del servicio independiente incompleta.')
const ruta=resolve(process.env.API_DATOS||'backend/storage/app/private/apis-demo/'+tipo+'.json')
let cola=Promise.resolve()
const historial=cambio?null:await abrirHistorial({ruta,uri:process.env.API_MONGO_URI,base:process.env.API_MONGO_DATABASE})
class ErrorHttp extends Error {constructor(estado,mensaje){super(mensaje);this.estado=estado}}
const exigir=(valor,mensaje)=>{if(!valor)throw new ErrorHttp(422,mensaje)}
const objeto=x=>x!==null&&typeof x==='object'&&!Array.isArray(x)
const texto=(x,max)=>typeof x==='string'&&x.trim().length>0&&x.length<=max
const entero=(x,min,max)=>Number.isSafeInteger(x)&&x>=min&&x<=max
const exacto=(x,keys)=>objeto(x)&&Object.keys(x).length===keys.length&&keys.every(k=>Object.hasOwn(x,k))
const canon=x=>Array.isArray(x)?x.map(canon):objeto(x)?Object.fromEntries(Object.keys(x).sort().map(k=>[k,canon(x[k])])):x
function validar(d){
 exigir(exacto(d,['documento_id','numero','moneda','total_centavos','sucursal','receptor','items']),'Estructura del documento no válida.')
 exigir(/^[a-f0-9]{24}$/.test(d.documento_id)&&entero(d.numero,1,Number.MAX_SAFE_INTEGER)&&d.moneda==='GTQ'&&entero(d.total_centavos,1,9999999999),'Encabezado no válido o importe sin valor monetario.')
 exigir(exacto(d.sucursal,['id','nombre'])&&/^[a-f0-9]{24}$/.test(d.sucursal.id)&&texto(d.sucursal.nombre,150),'Sucursal no válida.')
 exigir(exacto(d.receptor,['nombre','identificador'])&&texto(d.receptor.nombre,150)&&texto(d.receptor.identificador,80),'Receptor no válido.')
 exigir(Array.isArray(d.items)&&d.items.length>0&&d.items.length<=100,'Detalle no válido.')
 let suma=0n
 for(const item of d.items){
  exigir(exacto(item,['codigo','nombre','cantidad','precio_centavos','subtotal_centavos'])&&texto(item.codigo,40)&&texto(item.nombre,150)&&entero(item.cantidad,1,100000)&&entero(item.precio_centavos,0,9999999999)&&Number.isSafeInteger(item.subtotal_centavos),'Línea no válida.')
  const subtotal=BigInt(item.cantidad)*BigInt(item.precio_centavos);exigir(subtotal===BigInt(item.subtotal_centavos),'Subtotal inconsistente.');suma+=subtotal
 }
 exigir(suma===BigInt(d.total_centavos),'El total no coincide con el detalle.')
}
function responder(res,estado,cuerpo){res.writeHead(estado,{'Content-Type':'application/json; charset=utf-8','Cache-Control':'no-store','X-Content-Type-Options':'nosniff'});res.end(JSON.stringify(cuerpo))}
async function cuerpo(req){let longitud=0,bloques=[];for await(const b of req){longitud+=b.length;if(longitud>262144)throw new ErrorHttp(413,'Documento demasiado grande.');bloques.push(b)}try{return JSON.parse(Buffer.concat(bloques).toString('utf8'))}catch{throw new ErrorHttp(422,'JSON no válido.')}}
const servidor=http.createServer({maxHeaderSize:8192},async(req,res)=>{
 try{
  if(req.url==='/salud'&&req.method==='GET'){await historial?.salud();responder(res,200,{ok:true,servicio:tipo,entorno:'simulado',almacen:historial?.tipo||'prueba'});return}
  if(cambio){
   if(req.method!=='POST'||req.url!=='/variables/ws/TipoCambio.asmx')throw new ErrorHttp(404,'Operación no disponible.')
   await new Promise(resolve=>{req.resume();req.on('end',resolve)})
   const fecha=new Intl.DateTimeFormat('es-GT',{timeZone:'America/Guatemala',day:'2-digit',month:'2-digit',year:'numeric'}).format(new Date())
   res.writeHead(200,{'Content-Type':'text/xml; charset=utf-8'});res.end(`<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><TipoCambioDiaResponse xmlns="http://www.banguat.gob.gt/variables/ws/"><TipoCambioDiaResult><CambioDolar><VarDolar><fecha>${fecha}</fecha><referencia>7.650000</referencia></VarDolar></CambioDolar><TotalItems>1</TotalItems></TipoCambioDiaResult></TipoCambioDiaResponse></soap:Body></soap:Envelope>`);return
  }
  const recibido=Buffer.from(req.headers.authorization||''),esperado=Buffer.from('Bearer '+token)
  if(recibido.length!==esperado.length||!timingSafeEqual(recibido,esperado))throw new ErrorHttp(401,'Credencial del servicio no válida.')
  const endpoint=tipo==='facturacion'?'/v1/facturas':'/v1/comprobantes-pago'
  if(req.method!=='POST'||req.url!==endpoint)throw new ErrorHttp(404,'Operación no disponible.')
  if(!String(req.headers['content-type']||'').startsWith('application/json'))throw new ErrorHttp(415,'Se requiere JSON.')
  const clave=req.headers['idempotency-key'];exigir(typeof clave==='string'&&/^[a-f0-9]{64}$/.test(clave),'Se requiere una clave de idempotencia.')
  const d=await cuerpo(req);validar(d)
  const huella=createHash('sha256').update(JSON.stringify(canon(d))).digest('hex')
  const tarea=cola.then(async()=>{
   const respuesta={entorno:'simulado',tipo,idempotency_key:clave,documento_id:d.documento_id,moneda:d.moneda,total_centavos:d.total_centavos,
    referencia:(tipo==='facturacion'?'FAC-DEMO-':'PAGO-DEMO-')+clave.slice(0,20).toUpperCase(),emitido_at:new Date().toISOString(),
    estado:'generado',validez:tipo==='facturacion'?'SIN_VALOR_FISCAL':'SIN_MOVIMIENTO_BANCARIO'}
   return historial.confirmar(clave,huella,respuesta)
  });cola=tarea.catch(()=>{})
  responder(res,200,await tarea)
 }catch(e){responder(res,e.estado||500,{error:e.estado?e.message:'No se pudo confirmar el documento de prueba.'})}
})
servidor.requestTimeout=10000;servidor.headersTimeout=10000
servidor.listen(puerto,host,()=>console.log(`Servicio ${tipo} de pruebas escuchando en ${host}:${puerto}.`))
servidor.on('error',()=>{console.error('No se pudo iniciar el servicio; revise su puerto y configuración.');process.exitCode=1})
for(const senal of ['SIGTERM','SIGINT'])process.once(senal,()=>servidor.close(async()=>{await historial?.cerrar()}))
