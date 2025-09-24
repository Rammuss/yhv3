<?php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Emitir Nota (NC / ND)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root{ --bg:#f6f7fb; --surface:#fff; --text:#1f2937; --muted:#6b7280;
         --primary:#2563eb; --danger:#ef4444; --shadow:0 10px 24px rgba(0,0,0,.08); --radius:14px; }
  body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
  .card{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;margin-bottom:16px}
  h1{margin:0 0 10px} h2{margin:18px 0 8px;font-size:1.1rem}
  label{display:block;margin:8px 0 4px}
  input,select,textarea{padding:8px;border:1px solid #e5e7eb;border-radius:10px;width:100%;max-width:440px}
  .row{display:flex;gap:18px;flex-wrap:wrap}
  .btn{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer}
  .btn[disabled]{opacity:.6;cursor:not-allowed}
  .btn-danger{background:var(--danger)} .btn-sm{padding:6px 10px;font-size:.9rem}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{border:1px solid #e5e7eb;padding:8px;text-align:center}
  th{background:#eef2ff} .muted{color:var(--muted);font-size:.92rem}
  .right{display:flex;justify-content:flex-end;gap:8px}
  .totals{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px}
  .totals .kv{background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px}
  .sugs{position:relative;max-width:440px}
  .sugs-list{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:var(--shadow);z-index:10;max-height:260px;overflow:auto}
  .sug{padding:8px;cursor:pointer} .sug:hover{background:#f3f4f6}
  .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:20}
  .modal > .inner{background:#fff;border-radius:16px;max-width:900px;margin:60px auto;padding:16px}
  #toast{position:fixed; right:16px; top:16px; z-index:9999; display:none; padding:12px 14px; border-radius:10px; box-shadow:0 10px 24px rgba(0,0,0,.15); color:#fff; font-weight:600; max-width: 80vw;}
  .preview{display:none; gap:12px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin-top:10px}
  .preview .col{flex:1; min-width:260px}
  .chip{display:inline-block;background:#eef2ff;border:1px solid #dbeafe;border-radius:999px;padding:4px 10px;font-size:.85rem;margin-right:6px;cursor:pointer}
  .chip.active{background:#2563eb;color:#fff;border-color:#2563eb}
  .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:8px}
  .filters .group{display:flex;flex-direction:column}
  .facet{display:flex;gap:8px;flex-wrap:wrap}
  .facet label{display:flex;gap:6px;align-items:center;margin:0}
  .pill{display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:999px;padding:6px 10px}
  .muted-sm{color:var(--muted);font-size:.85rem}
  .footlist{display:flex;justify-content:space-between;align-items:center;margin-top:8px}
  .hint{font-size:.88rem;color:#6b7280}
</style>
</head>
<body>
  <div id="navbar-container"></div>

<div class="wrap">
  <div class="card">
    <h1>Emitir Nota</h1>
    <p class="muted">Creá una <b>NC</b> (crédito) o <b>ND</b> (débito). La NC puede:
      <br>• <b>Aplicar a cuotas</b> (descuenta saldo abierto de una factura a crédito), o
      <br>• Quedar <b>pendiente</b> para devolución en caja (egreso manual).
    </p>
  </div>

  <!-- PASO 1: Buscar Factura -->
  <div class="card">
    <h2>Buscar factura</h2>

    <div class="facet">
      <span class="chip" data-preset="">Todas</span>
      <span class="chip" data-preset="hoy">Hoy</span>
      <span class="chip" data-preset="ult7">Últimos 7 días</span>
      <span class="chip" data-preset="este_mes">Este mes</span>
      <span class="chip" data-preset="mes_pasado">Mes pasado</span>
    </div>

    <div class="filters">
      <div class="group" style="flex:1;min-width:300px">
        <label>Búsqueda (Nº, cliente, RUC)</label>
        <input type="text" id="qFac" placeholder="Ej: 001-001-0000123, Juan, 1234567">
      </div>

      <div class="group">
        <label>Condición</label>
        <select id="filtroCond">
          <option value="">Todas</option>
          <option value="Contado">Contado</option>
          <option value="Credito">Crédito</option>
        </select>
      </div>

      <div class="group">
        <label>Estados</label>
        <div class="pill">
          <label><input type="checkbox" class="chkEstado" value="Emitida" checked> Emitida</label>
          <label><input type="checkbox" class="chkEstado" value="Cancelada"> Cancelada</label>
          <label><input type="checkbox" class="chkEstado" value="Anulada"> Anulada</label>
        </div>
      </div>

      <div class="group"><label>Desde</label><input type="date" id="desde"></div>
      <div class="group"><label>Hasta</label><input type="date" id="hasta"></div>

      <div class="group"><button class="btn" type="button" id="btnBuscarFac">Buscar</button></div>
      <div class="group"><button class="btn btn-danger" type="button" id="btnLimpiarFac">Limpiar</button></div>
    </div>

    <div class="sugs-list" id="listFac" style="display:none; position:relative; margin-top:8px; max-height:320px;"></div>
    <div class="footlist">
      <div class="muted-sm" id="metaList"></div>
      <div><button class="btn btn-sm" type="button" id="btnVerMas" style="display:none">Ver más</button></div>
    </div>

    <div class="row" style="margin-top:10px">
      <div>
        <label>ID Factura seleccionada</label>
        <input type="number" id="id_factura" name="id_factura" readonly>
      </div>
    </div>

    <div id="preview" class="preview">
      <div class="col" id="previewInfo"></div>
      <div class="col">
        <div class="right">
          <button class="btn btn-sm" type="button" id="btnCargarItems">Cargar ítems</button>
          <button class="btn btn-sm" type="button" id="btnUsarFactura">Usar factura</button>
        </div>
        <p class="hint" style="margin-top:8px">Si la factura es <b>Crédito</b> y tiene cuotas abiertas, podés elegir <b>“Emitir y aplicar a cuotas”</b> más abajo.</p>
      </div>
    </div>
  </div>

  <!-- PASO 2: Cabecera de la Nota -->
  <form class="card" id="formNota" autocomplete="off">
    <h2>Cabecera de la Nota</h2>
    <div class="row">
      <div>
        <label>Clase de nota</label>
        <select name="clase" id="clase" required>
          <option value="NC">Nota de Crédito</option>
          <option value="ND">Nota de Débito</option>
        </select>
      </div>

      <div class="sugs">
        <label>Buscar cliente (CI/RUC o Nombre)</label>
        <input type="text" id="buscarCliente" placeholder="Ej: 1234567 o Juan Pérez">
        <div id="sugerencias" class="sugs-list" style="display:none"></div>
      </div>
      <div>
        <label>ID Cliente</label>
        <input type="number" id="id_cliente" name="id_cliente" required readonly>
      </div>
      <div>
        <label>Fecha emisión</label>
        <input type="date" id="fecha_emision" name="fecha_emision" required>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Motivo (opcional)</label>
        <input type="number" id="id_motivo" name="id_motivo" placeholder="ID motivo">
      </div>
      <div style="flex:1; min-width:280px">
        <label>Detalle del motivo (opcional)</label>
        <input type="text" id="motivo_texto" name="motivo_texto" placeholder="Texto libre del motivo">
      </div>
      <div id="bloqueAfecta">
        <label>&nbsp;</label>
        <label><input type="checkbox" id="afecta_stock" name="afecta_stock" value="1"> Afecta stock (solo NC)</label>
      </div>
    </div>

    <!-- ACCIÓN POST-EMISIÓN -->
    <h2>Acción post-emisión</h2>
    <div class="row">
      <div>
        <label>Acción</label>
        <select id="accionPost">
          <option value="emitir">Solo emitir (pendiente)</option>
          <option value="aplicar_cuotas">Emitir y aplicar a cuotas (Crédito)</option>
        </select>
      </div>

      <div id="boxAplicar" style="display:none; border:1px dashed #e5e7eb; padding:8px; border-radius:8px;">
        <label>Factura (Crédito) a aplicar</label>
        <select id="ddlFacturaCredito"><option value="">— Seleccioná —</option></select>
        <div class="muted" id="infoSaldos" style="margin-top:6px">Saldo abierto: —</div>
      </div>
    </div>

    <!-- DETALLE -->
    <h2>Ítems</h2>
    <div class="right">
      <button class="btn" type="button" onclick="abrirBuscador()">Buscar producto/servicio</button>
      <button class="btn btn-danger" type="button" onclick="limpiarItems()">Limpiar ítems</button>
    </div>

    <table id="tabla">
      <thead>
      <tr>
        <th>Descripción</th>
        <th>Precio</th>
        <th>IVA</th>
        <th>Cantidad</th>
        <th>Descuento</th>
        <th>Subtotal</th>
        <th>Quitar</th>
      </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>

    <div class="totals">
      <div class="kv"><b>Bruto:</b> <span id="tBruto">0</span></div>
      <div class="kv"><b>Desc.:</b> <span id="tDesc">0</span></div>
      <div class="kv"><b>IVA:</b> <span id="tIva">0</span></div>
      <div class="kv"><b>Neto:</b> <span id="tNeto">0</span></div>
    </div>

    <div class="right" style="margin-top:12px">
      <button class="btn" id="btnEmitir" type="submit">Emitir Nota</button>
    </div>

    <input type="hidden" id="detalle_json" name="detalle_json">
  </form>

  <!-- MODAL BUSCADOR ÍTEMS -->
  <div class="modal" id="modalBuscar">
    <div class="inner">
      <div class="row">
        <div style="flex:1">
          <label>Buscar producto/servicio</label>
          <input type="text" id="qProd" placeholder="Nombre..." onkeyup="buscarProductos()">
        </div>
        <div class="right" style="align-items:flex-end">
          <button class="btn" type="button" onclick="cerrarBuscador()">Cerrar</button>
        </div>
      </div>
      <table style="margin-top:10px">
        <thead>
          <tr>
            <th>Descripción</th><th>Precio</th><th>IVA</th><th>Agregar</th>
          </tr>
        </thead>
        <tbody id="tbodyBuscar"></tbody>
      </table>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
/* ===== Endpoints ===== */
const URL_FACTURAS_BUSCAR   = '../../venta_v3/notas/facturas_buscar.php';
const URL_FACTURA_DETALLE   = '../../venta_v3/notas/factura_detalle.php';
const URL_NOTAS_EMITIR      = '../../venta_v3/notas/notas_emitir.php';
const URL_NOTAS_APLICAR     = '../../venta_v3/notas/aplicar_nc_cuotas.php';  // NUEVO
const URL_FACTS_CRED_ABIERT = '../../venta_v3/notas/facturas_credito_abiertas.php'; // NUEVO
const URL_CLIENTES_BUSCAR   = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/cliente/clientes_buscar.php';
const URL_PROD_BUSCAR       = 'productos_disponibles.php';

/* ===== Toast ===== */
function showToast(msg, type='ok') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = (type==='ok') ? '#16a34a' : '#ef4444';
  t.style.display = 'block';
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(()=>{ t.style.display='none'; }, 3000);
}

/* ===== Fecha hoy ===== */
const inputFecha = document.getElementById('fecha_emision');
function setHoy(){
  const d=new Date(), y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), dd=String(d.getDate()).padStart(2,'0');
  inputFecha.value = `${y}-${m}-${dd}`;
}
document.addEventListener('DOMContentLoaded', setHoy);

/* ===== Clase → toggle afecta stock ===== */
const selClase = document.getElementById('clase');
const chkAfecta = document.getElementById('afecta_stock');
selClase.addEventListener('change', ()=>{
  const isNC = selClase.value==='NC';
  chkAfecta.disabled = !isNC;
  if (!isNC) chkAfecta.checked = false;
});

/* ===== Autocompletar Cliente ===== */
const inpCli = document.getElementById('buscarCliente');
const sugsBox = document.getElementById('sugerencias');
const idCliente = document.getElementById('id_cliente');
let cliTimer=null;
inpCli.addEventListener('keyup', ()=>{
  const q = inpCli.value.trim();
  clearTimeout(cliTimer);
  if (q.length<2){ sugsBox.style.display='none'; sugsBox.innerHTML=''; return; }
  cliTimer = setTimeout(async ()=>{
    const res = await fetch(`${URL_CLIENTES_BUSCAR}?q=${encodeURIComponent(q)}`);
    const js = await res.json();
    sugsBox.innerHTML='';
    if (js.ok && js.data.length){
      js.data.forEach(c=>{
        const d=document.createElement('div');
        d.className='sug';
        d.textContent = `${c.nombre_completo} (${c.ruc_ci||'s/CI'})`;
        d.onclick = ()=>{ inpCli.value = d.textContent; idCliente.value = c.id_cliente; sugsBox.style.display='none'; maybeLoadCreditoList(); };
        sugsBox.appendChild(d);
      });
      sugsBox.style.display='block';
    }else{ sugsBox.style.display='none'; }
  }, 250);
});
document.addEventListener('click',(e)=>{ if (!sugsBox.contains(e.target) && e.target!==inpCli) sugsBox.style.display='none'; });

/* ===== Buscar Facturas ===== */
const chips = Array.from(document.querySelectorAll('.chip'));
const qFac = document.getElementById('qFac');
const listFac = document.getElementById('listFac');
const filtroCond = document.getElementById('filtroCond');
const chkEstados = Array.from(document.querySelectorAll('.chkEstado'));
const desde = document.getElementById('desde');
const hasta = document.getElementById('hasta');
const btnBuscarFac = document.getElementById('btnBuscarFac');
const btnLimpiarFac = document.getElementById('btnLimpiarFac');
const btnVerMas = document.getElementById('btnVerMas');
const metaList = document.getElementById('metaList');
const idFacturaInput = document.getElementById('id_factura');
const preview = document.getElementById('preview');
const previewInfo = document.getElementById('previewInfo');
const btnCargarItems = document.getElementById('btnCargarItems');
const btnUsarFactura  = document.getElementById('btnUsarFactura');

let facSel=null;
let paging = { limit: 10, offset: 0, total: 0, has_more: false };
let lastQuery = null;

chips.forEach(ch => ch.addEventListener('click', ()=>{
  chips.forEach(c=>c.classList.remove('active'));
  ch.classList.add('active');
  const p = ch.dataset.preset || '';
  if (p==='') { desde.value=''; hasta.value=''; }
  document.body.dataset.preset = p;
  paging.offset = 0;
  performSearch(true);
}));

btnBuscarFac.addEventListener('click', ()=>{ paging.offset=0; performSearch(true); });
btnLimpiarFac.addEventListener('click', ()=>{
  qFac.value=''; filtroCond.value='';
  chkEstados.forEach(x=>x.checked = (x.value==='Emitida'));
  desde.value=''; hasta.value='';
  chips.forEach(c=>c.classList.remove('active')); document.body.dataset.preset='';
  paging = { limit: 10, offset: 0, total: 0, has_more:false };
  listFac.style.display='none'; listFac.innerHTML=''; metaList.textContent=''; idFacturaInput.value=''; preview.style.display='none'; facSel=null;
  clearCreditoSelect();
});

btnVerMas.addEventListener('click', ()=>{ if (!paging.has_more) return; paging.offset += paging.limit; performSearch(false); });
qFac.addEventListener('keyup', (e)=>{ if (e.key==='Enter') { paging.offset=0; performSearch(true); } });

async function performSearch(resetList){
  const estados = chkEstados.filter(x=>x.checked).map(x=>x.value);
  const params = new URLSearchParams();
  if (qFac.value.trim()!=='') params.set('q', qFac.value.trim());
  if (filtroCond.value) params.set('cond', filtroCond.value);

  if (estados.length===0) {
    params.append('estados[]', '___none___');
  } else if (estados.length===3) {
    params.set('estado','*');
  } else {
    estados.forEach(s => params.append('estados[]', s));
  }

  const preset = document.body.dataset.preset || '';
  if (preset) params.set('preset', preset);
  if (desde.value) params.set('desde', desde.value);
  if (hasta.value) params.set('hasta', hasta.value);

  params.set('limit', paging.limit);
  params.set('offset', paging.offset);

  const url = `${URL_FACTURAS_BUSCAR}?${params.toString()}`;
  lastQuery = url;

  const res = await fetch(url);
  const js  = await res.json();

  if (!js.success) { showToast(js.error || 'No se pudo buscar', 'err'); return; }

  const rows = js.data || [];
  paging.total = js.total_count || 0;
  paging.has_more = !!js.has_more;

  if (resetList) listFac.innerHTML='';

  if (rows.length) {
    rows.forEach(f=>{
      const d=document.createElement('div');
      d.className='sug';
      d.innerHTML = `
        <b>${f.numero_documento}</b> · ${f.fecha} · ${f.cliente} (${f.ruc||'s/CI'}) ·
        ${Number(f.total).toLocaleString('es-PY',{minimumFractionDigits:2})}
        <span class="muted-sm">· ${f.condicion_venta} · ${f.estado}</span>`;
      d.onclick = ()=> selectFactura(f.id_factura);
      listFac.appendChild(d);
    });
    listFac.style.display='block';
  } else if (resetList) {
    listFac.innerHTML = '<div class="sug muted">Sin resultados</div>';
    listFac.style.display='block';
  }

  const mostrados = (resetList ? 0 : paging.offset) + rows.length;
  metaList.textContent = `Mostrando ${mostrados} de ${paging.total}`;
  btnVerMas.style.display = paging.has_more ? 'inline-block' : 'none';
}

async function selectFactura(id){
  listFac.style.display='none';
  const res = await fetch(`${URL_FACTURA_DETALLE}?id=${id}`);
  const js  = await res.json();
  if (!js.success){ showToast(js.error||'No se pudo cargar factura','err'); return; }
  facSel = js.data;
  idFacturaInput.value = facSel.id_factura;
  renderFacturaPreview(facSel);
  maybeLoadCreditoList(); // por si quiere aplicar
}

function renderFacturaPreview(fac){
  preview.style.display='flex';
  const tot  = Number(fac.total_factura||fac.total_neto||0).toLocaleString('es-PY',{minimumFractionDigits:2});
  const hist = (fac.notas||[]).length
    ? fac.notas.map(n=>`<span class="chip">${n.clase} ${n.numero_documento} (${n.estado})</span>`).join(' ')
    : '<span class="muted">Sin notas previas</span>';

  previewInfo.innerHTML = `
    <div><b>Nº:</b> ${fac.numero_documento} — <b>Fecha:</b> ${fac.fecha_emision}</div>
    <div><b>Cliente:</b> ${fac.cliente} (${fac.ruc_ci||'s/CI'})</div>
    <div><b>PPP:</b> ${fac.ppp||'-'} — <b>Timbrado:</b> ${fac.timbrado_numero||'-'}</div>
    <div style="margin-top:6px"><b>Total:</b> ${tot} — <b>Condición:</b> ${fac.condicion_venta} — <b>Estado:</b> ${fac.estado}</div>
    <div style="margin-top:6px"><b>Notas previas:</b><br>${hist}</div>
  `;
}

/* ===== Acción post-emisión ===== */
const accionSel = document.getElementById('accionPost');
const boxAplicar = document.getElementById('boxAplicar');
const ddlFacturaCredito = document.getElementById('ddlFacturaCredito');
const infoSaldos = document.getElementById('infoSaldos');

accionSel.addEventListener('change', ()=>{
  const v = accionSel.value;
  boxAplicar.style.display = (v==='aplicar_cuotas') ? 'block' : 'none';
  if (v==='aplicar_cuotas') maybeLoadCreditoList();
});

function clearCreditoSelect(){
  ddlFacturaCredito.innerHTML = '<option value="">— Seleccioná —</option>';
  infoSaldos.textContent = 'Saldo abierto: —';
}

async function maybeLoadCreditoList(){
  if (accionSel.value!=='aplicar_cuotas') return;
  const idCli = Number(idCliente.value||0);
  if (!idCli){ clearCreditoSelect(); return; }

  // Si ya hay una factura seleccionada de vista previa y es Crédito, la priorizamos
  if (facSel && String(facSel.condicion_venta).toLowerCase()==='credito') {
    ddlFacturaCredito.innerHTML = `<option value="${facSel.id_factura}">${facSel.numero_documento} | ${facSel.fecha_emision}</option>`;
    infoSaldos.textContent = 'Saldo abierto: (se consultará al aplicar)';
    return;
  }

  try{
    const r = await fetch(`${URL_FACTS_CRED_ABIERT}?id_cliente=${idCli}`);
    const j = await r.json();
    clearCreditoSelect();
    if (j.success && (j.data||[]).length){
      (j.data||[]).forEach(f=>{
        const opt = document.createElement('option');
        opt.value = f.id_factura;
        opt.textContent = `${f.numero_documento} | ${f.fecha} | saldo: ${Number(f.saldo_abierto||0).toFixed(2)}`;
        ddlFacturaCredito.appendChild(opt);
      });
      infoSaldos.textContent = 'Elegí la factura a afectar';
    } else {
      infoSaldos.textContent = 'Sin facturas a crédito con saldo abierto';
    }
  }catch(e){
    infoSaldos.textContent = 'Error al cargar facturas';
  }
}

/* ===== Ítems ===== */
const tbody = document.getElementById('tbody');
const detalleInput = document.getElementById('detalle_json');
let items = []; // {id_producto, descripcion, cantidad, precio_unitario, descuento, tipo_iva}

function ivaRate(tipo){
  // Acepta '10', '10%', '5', '5%', 'EX'
  const t = String(tipo||'').toUpperCase();
  if (t==='10' || t==='10%') return 0.10;
  if (t==='5'  || t==='5%')  return 0.05;
  return 0.00;
}
function fmt2(n){ return Number(n||0).toLocaleString('es-PY',{minimumFractionDigits:2}); }

function render(){
  tbody.innerHTML='';
  let tBruto=0, tDesc=0, tIva=0, tNeto=0;

  items.forEach((it, i)=>{
    const bruto = it.cantidad*it.precio_unitario;
    const base  = Math.max(0, bruto - (it.descuento||0));
    const iva   = base*ivaRate(it.tipo_iva);
    const neto  = base+iva;

    tBruto+=bruto; tDesc+=(it.descuento||0); tIva+=iva; tNeto+=neto;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="text-align:left">${it.descripcion}</td>
      <td>${fmt2(it.precio_unitario)}</td>
      <td>${(String(it.tipo_iva||'EX').toUpperCase().includes('10')?'10':String(it.tipo_iva||'EX').toUpperCase().includes('5')?'5':'EX')}</td>
      <td><input type="number" min="1" step="1" value="${it.cantidad}" oninput="chgCant(${i},this.value)" style="width:90px"></td>
      <td><input type="number" min="0" step="0.01" value="${it.descuento||0}" oninput="chgDesc(${i},this.value)" style="width:110px"></td>
      <td>${fmt2(neto)}</td>
      <td><button class="btn btn-danger btn-sm" type="button" onclick="quitar(${i})">X</button></td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('tBruto').textContent=fmt2(tBruto);
  document.getElementById('tDesc').textContent =fmt2(tDesc);
  document.getElementById('tIva').textContent  =fmt2(tIva);
  document.getElementById('tNeto').textContent =fmt2(tNeto);

  detalleInput.value = JSON.stringify(items);
}
function chgCant(i,v){ v=parseInt(v||'0',10); if(!Number.isFinite(v)||v<1) v=1; items[i].cantidad=v; render(); }
function chgDesc(i,v){ v=parseFloat(v||'0'); if(!Number.isFinite(v)||v<0) v=0; const max=items[i].cantidad*items[i].precio_unitario; if(v>max) v=max; items[i].descuento=v; render(); }
function quitar(i){ items.splice(i,1); render(); }
function limpiarItems(){ items=[]; render(); }

/* ===== Modal productos ===== */
const modal = document.getElementById('modalBuscar');
const tbodyBuscar = document.getElementById('tbodyBuscar');
const inpProd = document.getElementById('qProd');
function abrirBuscador(){ modal.style.display='block'; buscarProductos(); }
function cerrarBuscador(){ modal.style.display='none'; tbodyBuscar.innerHTML=''; inpProd.value=''; }

let prodTimer=null;
async function buscarProductos(){
  clearTimeout(prodTimer);
  prodTimer=setTimeout(async ()=>{
    const q = inpProd.value.trim();
    const url = q ? `${URL_PROD_BUSCAR}?q=${encodeURIComponent(q)}` : URL_PROD_BUSCAR;
    const res = await fetch(url);
    const js  = await res.json();
    tbodyBuscar.innerHTML='';
    if(js.success && js.productos.length){
      js.productos.forEach(p=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`
          <td style="text-align:left">${p.nombre}</td>
          <td>${Number(p.precio_unitario).toLocaleString('es-PY',{minimumFractionDigits:2})}</td>
          <td>${p.tipo_iva||'EX'}</td>
          <td><button class="btn btn-sm" type="button"
              onclick='agregar(${JSON.stringify({id_producto:p.id_producto, descripcion:p.nombre, precio_unitario:Number(p.precio_unitario), tipo_iva:(p.tipo_iva||"EX")}).replace(/'/g,"&#39;")})'>+</button></td>`;
        tbodyBuscar.appendChild(tr);
      });
    }else{
      tbodyBuscar.innerHTML='<tr><td colspan="4">Sin resultados</td></tr>';
    }
  },200);
}
function agregar(prod){
  items.push({ id_producto: prod.id_producto, descripcion: prod.descripcion, cantidad: 1, precio_unitario: prod.precio_unitario, descuento: 0, tipo_iva: prod.tipo_iva });
  render();
}

/* ===== Cargar items desde factura ===== */
btnCargarItems.addEventListener('click', ()=>{
  if(!facSel || !facSel.detalle){ showToast('Sin detalle de factura','err'); return; }
  items = facSel.detalle.map(d=>({
    id_producto: d.id_producto || null,
    descripcion: d.descripcion,
    cantidad: d.cantidad,
    precio_unitario: d.precio_unitario,
    descuento: 0,
    tipo_iva: (d.tipo_iva && d.tipo_iva.toLowerCase().includes('10')) ? '10'
           : (d.tipo_iva && d.tipo_iva.toLowerCase().includes('5'))  ? '5' : 'EX'
  }));
  render();
  showToast('Ítems cargados desde la factura');
});

btnUsarFactura.addEventListener('click', ()=>{
  if(!facSel){ showToast('Seleccioná una factura','err'); return; }
  if (facSel.id_cliente && facSel.cliente){
    idCliente.value = facSel.id_cliente;
    document.getElementById('buscarCliente').value = `${facSel.cliente} (${facSel.ruc_ci||'s/CI'})`;
  }
  maybeLoadCreditoList();
  showToast('Factura seleccionada');
});

/* ===== Submit Nota ===== */
const form = document.getElementById('formNota');
const btnEmitir = document.getElementById('btnEmitir');
form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  if(!idCliente.value){ showToast('Seleccioná un cliente','err'); return; }
  if(items.length===0){ showToast('Agregá al menos un ítem','err'); return; }

  const clase = document.getElementById('clase').value;
  const payload = {
    clase,
    id_cliente: Number(idCliente.value),
    id_factura: (document.getElementById('id_factura').value ? Number(document.getElementById('id_factura').value) : null),
    id_motivo: document.getElementById('id_motivo').value || null,
    motivo_texto: document.getElementById('motivo_texto').value || null,
    fecha_emision: document.getElementById('fecha_emision').value,
    afecta_stock: (clase==='NC') ? document.getElementById('afecta_stock').checked : false,
    detalle: items
  };

  // Validación: si va a aplicar a cuotas, necesitamos una factura Crédito
  const accion = accionSel.value;
  let id_factura_aplicar = null;
  if (accion==='aplicar_cuotas'){
    // Prioriza la factura seleccionada en el paso 1 si existe y es Crédito
    if (facSel && String(facSel.condicion_venta).toLowerCase()==='credito'){
      id_factura_aplicar = facSel.id_factura;
    } else {
      id_factura_aplicar = Number(ddlFacturaCredito.value||0) || null;
    }
    if (!id_factura_aplicar){
      showToast('Elegí la factura a crédito para aplicar la NC','err');
      return;
    }
  }

  btnEmitir.disabled=true; btnEmitir.textContent='Emitiendo...';
  try{
    // 1) Emitir NC/ND
    const res = await fetch(URL_NOTAS_EMITIR, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const js = await res.json();
    if(!js.success){ showToast(js.error||'Error al emitir','err'); return; }

    // 2) Si corresponde, aplicar automáticamente a cuotas
    if (accion==='aplicar_cuotas' && js.clase==='NC' && id_factura_aplicar){
      const r2 = await fetch(URL_NOTAS_APLICAR, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id_nc: js.id_nota, id_factura: id_factura_aplicar })
      });
      const k = await r2.json();
      if (!k.success){ showToast('NC emitida, pero no se pudo aplicar a cuotas: '+(k.error||''), 'err'); }
      else { showToast(`✅ NC ${js.numero_documento} emitida y aplicada (Gs ${Number(k.aplicado_total||0).toFixed(2)})`,'ok'); }
    } else {
      showToast(`✅ ${js.clase} Nº ${js.numero_documento} emitida`,'ok');
    }

    // Reset UI
    items=[]; render(); form.reset(); setHoy();
    preview.style.display='none'; facSel=null; document.getElementById('id_factura').value='';
    clearCreditoSelect();
    accionSel.value='emitir'; boxAplicar.style.display='none';

  }catch(err){
    showToast('Error de red/servidor','err');
  } finally {
    btnEmitir.disabled=false; btnEmitir.textContent='Emitir Nota';
  }
});

/* ===== Render inicial ===== */
render();
performSearch(true);
</script>
<script src="/../TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>
</body>
</html>
