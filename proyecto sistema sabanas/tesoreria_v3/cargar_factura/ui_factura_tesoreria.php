<?php
// factura_ui_tesoreria.php
// UI para cargar factura por Orden de Compra (CON OC), enchufado a tus endpoints existentes:
//
//   - Proveedores:        proveedores_options.php         (GET)
//   - Preparación OC:     factura_preparar.php            (GET ?id_proveedor=.. [&id_oc=..])
//   - Guardar Factura:    factura_guardar.php             (POST)
//
// Ajustá las rutas abajo (CONFIG) y servilo como una página PHP normal.

header('Content-Type: text/html; charset=utf-8');

/* ============ CONFIG: AJUSTAR RUTAS SEGÚN TU PROYECTO ============ */
$URL_PROVEEDORES   = '../cargar_factura/proveedores_options.php';
$URL_PREPARAR_OC   = '../cargar_factura/factura_preparar.php';
$URL_GUARDAR       = 'factura_guardar.php';
/* ================================================================== */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<title>Tesorería · Cargar Factura por OC</title>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<style>
  :root{ --bg:#0f172a; --card:#111827; --muted:#94a3b8; --txt:#e5e7eb; --ok:#16a34a; --err:#ef4444; --line:#243244; }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family:system-ui,Segoe UI,Roboto,Ubuntu; background:var(--bg); color:var(--txt); }
  .wrap{ max-width:1120px; margin:24px auto; padding:12px; }
  .card{ background:var(--card); border:1px solid #1f2937; border-radius:16px; padding:16px; box-shadow:0 10px 24px rgba(0,0,0,.25); }
  h1{ margin:0 0 12px; font-size:20px; }
  .grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
  label{ font-size:12px; color:var(--muted); display:block; margin-bottom:6px; }
  input,select{ width:100%; padding:10px 12px; border-radius:10px; border:1px solid #1f2937; background:#0b1220; color:var(--txt); }
  .muted{ color:var(--muted); font-size:12px; }
  .row{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  .btn{ padding:10px 14px; border-radius:10px; border:1px solid #334155; background:#0b1220; color:#e5e7eb; cursor:pointer; }
  .btn:hover{ filter:brightness(1.12); }
  .btn.ok{ border-color:#14532d; background:#052e16; }
  .btn.danger{ border-color:#7f1d1d; background:#220d0d; }
  .seg{ padding:12px; border:1px dashed #334155; border-radius:12px; margin-top:14px; }
  .tbl{ width:100%; border-collapse:collapse; margin-top:8px; font-size:14px; }
  .tbl th,.tbl td{ border:1px solid var(--line); padding:8px; text-align:left; }
  .tbl th{ color:#cbd5e1; background:#0b1220; }
  .right{ text-align:right; }
  .hidden{ display:none; }
  /* Modal */
  .modal-back{ position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:1000; }
  .modal{ width:min(980px,95vw); background:var(--card); border:1px solid var(--line); border-radius:16px; padding:16px; max-height:85vh; overflow:auto; }
  .modal-head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
  .tag{ display:inline-block; padding:2px 8px; border-radius:999px; background:#0b1220; border:1px solid #334155; font-size:12px; color:#cbd5e1;}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Cargar Factura por OC (Tesorería)</h1>
    <form id="frm" onsubmit="return enviarFactura(event)">
      <div class="grid">
        <div>
          <label>Proveedor</label>
          <select name="id_proveedor" id="id_proveedor" required></select>
          <div class="muted" id="prov_info">Seleccioná un proveedor</div>
        </div>
        <div>
          <label>Fecha de emisión</label>
          <input type="date" name="fecha_emision" value="<?=date('Y-m-d')?>" required>
        </div>
        <div>
          <label>Número de documento</label>
          <input type="text" name="numero_documento" required placeholder="001-001-0001234">
        </div>
        <div>
          <label>Timbrado (opcional)</label>
          <input type="text" name="timbrado_numero" inputmode="numeric" pattern="[0-9]*">
        </div>

        <div>
          <label>Moneda</label>
          <select name="moneda">
            <option value="PYG">PYG</option>
            <option value="USD">USD</option>
          </select>
        </div>
        <div>
          <label>Sucursal (opcional)</label>
          <input type="number" name="id_sucursal" placeholder="ID sucursal">
        </div>
        <div>
          <label>Condición</label>
          <select name="condicion" id="condicion" onchange="syncCondicion()">
            <option value="CREDITO">Crédito</option>
            <option value="CONTADO">Contado</option>
          </select>
        </div>
        <div>
          <label>Cuotas</label>
          <input type="number" name="cuotas" id="cuotas" value="1" min="1">
        </div>

        <div>
          <label>Días plazo 1ra cuota</label>
          <input type="number" name="dias_plazo" value="30" min="0">
        </div>
        <div>
          <label>Intervalo entre cuotas (días)</label>
          <input type="number" name="intervalo_dias" value="30" min="0">
        </div>
        <div style="grid-column: span 2">
          <label>Observación</label>
          <input type="text" name="observacion" placeholder="Texto libre">
        </div>
      </div>

      <div class="seg">
        <div class="row" style="justify-content:space-between">
          <div class="row">
            <span class="tag">Alta por OC (impacta stock)</span>
            <button type="button" class="btn" onclick="abrirModalOC()">Buscar OC pendientes</button>
          </div>
          <div class="muted">Agregá líneas desde el detalle de la OC.</div>
        </div>

        <table class="tbl" id="tbl_oc">
          <thead>
            <tr>
              <th>id_oc_det</th>
              <th>Cantidad</th>
              <th>Precio Unit.</th>
              <th>IVA</th>
              <th></th>
            </tr>
          </thead>
          <tbody><!-- líneas se agregan aquí --></tbody>
        </table>
      </div>

      <div class="row" style="justify-content:space-between; margin-top:10px">
        <span class="muted" id="msg"></span>
        <div>
          <button type="button" class="btn danger" onclick="limpiarFormulario()">Cancelar</button>
          <button class="btn ok" type="submit">Guardar factura</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: selector de OCs -->
<div class="modal-back" id="modalOC">
  <div class="modal" role="dialog" aria-modal="true" aria-label="Selector de OC">
    <div class="modal-head">
      <strong>Órdenes de compra con pendiente</strong>
      <button class="btn" type="button" onclick="cerrarModalOC()">✕</button>
    </div>
    <div class="row">
      <input id="oc_q" placeholder="(Opcional) filtrar por #OC o #pedido" style="flex:1">
      <button class="btn" type="button" onclick="cargarOCs()">Buscar</button>
    </div>
    <table class="tbl" style="margin-top:8px">
      <thead><tr><th>ID OC</th><th>N° pedido</th><th>Fecha</th><th>Pendiente total</th><th>Detalle</th></tr></thead>
      <tbody id="oc_list"><tr><td colspan="5">Seleccione un proveedor y pulse “Buscar”.</td></tr></tbody>
    </table>
  </div>
</div>

<script>
// ======= CONFIG JS (mismo que PHP arriba, por si lo servís desde otra carpeta) =======
const URL_PROVEEDORES = '<?= htmlspecialchars($URL_PROVEEDORES, ENT_QUOTES) ?>';
const URL_PREPARAR_OC = '<?= htmlspecialchars($URL_PREPARAR_OC, ENT_QUOTES) ?>';
const URL_GUARDAR     = '<?= htmlspecialchars($URL_GUARDAR, ENT_QUOTES) ?>';

// ======= Helpers =======
function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }
function syncCondicion(){ const c=document.getElementById('condicion').value; const q=document.getElementById('cuotas'); if(c==='CONTADO'){ q.value=1; q.setAttribute('readonly','readonly'); } else { q.removeAttribute('readonly'); } }
function limpiarTablaOC(){ document.querySelector('#tbl_oc tbody').innerHTML=''; }
function limpiarFormulario(){ if(confirm('¿Limpiar formulario?')){ document.getElementById('frm').reset(); limpiarTablaOC(); document.getElementById('prov_info').textContent='Seleccioná un proveedor'; } }

// ======= Proveedores =======
async function cargarProveedores(){
  const sel = document.getElementById('id_proveedor');
  sel.innerHTML = `<option value="">Cargando…</option>`;
  try{
    const r = await fetch(URL_PROVEEDORES);
    const items = await r.json();
    sel.innerHTML = `<option value="">-- Elegir --</option>` + items.map(it=>`<option value="${it.id_proveedor}">${escapeHtml(it.nombre)}</option>`).join('');
    sel.addEventListener('change', ()=>{
      const txt = sel.options[sel.selectedIndex]?.text || '';
      document.getElementById('prov_info').textContent = txt ? `Proveedor: ${txt}` : 'Seleccioná un proveedor';
      // cada vez que cambio de proveedor, limpio la tabla de líneas
      limpiarTablaOC();
    });
  }catch(e){
    sel.innerHTML = `<option value="">Error cargando proveedores</option>`;
  }
}
document.addEventListener('DOMContentLoaded', cargarProveedores);

// ======= Modal OC =======
const modalOC = document.getElementById('modalOC');
function abrirModalOC(){
  const idp = document.getElementById('id_proveedor').value;
  if(!idp){ alert('Primero seleccioná un proveedor'); return; }
  modalOC.style.display='flex';
  document.getElementById('oc_q').value='';
  cargarOCs();
}
function cerrarModalOC(){ modalOC.style.display='none'; }
document.addEventListener('keydown',(e)=>{ if(e.key==='Escape') cerrarModalOC(); });
modalOC.addEventListener('click', (e)=>{ if(e.target===modalOC) cerrarModalOC(); });

// Cargar lista de OCs con pendiente
async function cargarOCs(){
  const idp = document.getElementById('id_proveedor').value;
  const tb = document.getElementById('oc_list');
  tb.innerHTML = `<tr><td colspan="5">Cargando…</td></tr>`;
  try{
    // Tu factura_preparar.php devuelve modo "list" con ?id_proveedor=..
    const r = await fetch(`${URL_PREPARAR_OC}?id_proveedor=${encodeURIComponent(idp)}`);
    const data = await r.json();
    if(!data.ok){ tb.innerHTML = `<tr><td colspan="5">Error al cargar OCs</td></tr>`; return; }
    const items = data.ocs || [];
    const filtro = (document.getElementById('oc_q').value||'').toLowerCase();
    const list = filtro
      ? items.filter(oc => String(oc.id_oc).includes(filtro) || String(oc.numero_pedido||'').includes(filtro))
      : items;

    if(list.length===0){ tb.innerHTML = `<tr><td colspan="5">Sin OCs con pendiente</td></tr>`; return; }

    tb.innerHTML = list.map(oc=>`
      <tr>
        <td>${oc.id_oc}</td>
        <td>${escapeHtml(oc.numero_pedido??'')}</td>
        <td>${escapeHtml((oc.fecha_oc||'').substring(0,10))}</td>
        <td class="right">${oc.pendiente_total}</td>
        <td><button class="btn" type="button" onclick="mostrarDetalleOC(${oc.id_oc})">Ver detalle</button></td>
      </tr>
      <tr><td colspan="5"><div id="ocdet_${oc.id_oc}" class="muted"></div></td></tr>
    `).join('');
  }catch(e){
    tb.innerHTML = `<tr><td colspan="5">Error</td></tr>`;
  }
}

// Mostrar detalle de una OC (pendientes por línea)
async function mostrarDetalleOC(id_oc){
  const idp = document.getElementById('id_proveedor').value;
  const cont = document.getElementById('ocdet_'+id_oc);
  cont.innerHTML = 'Cargando detalle…';
  try{
    // Tu factura_preparar.php con ?id_proveedor=..&id_oc=..
    const r = await fetch(`${URL_PREPARAR_OC}?id_proveedor=${encodeURIComponent(idp)}&id_oc=${encodeURIComponent(id_oc)}`);
    const data = await r.json();
    if(!data.ok){ cont.textContent='Error cargando detalle'; return; }
    const rows = data.data || [];
    if(rows.length===0){ cont.textContent='Sin pendientes en esta OC'; return; }

    cont.innerHTML = `
      <table class="tbl">
        <thead>
          <tr>
            <th>id_oc_det</th>
            <th>Producto</th>
            <th>Pendiente</th>
            <th>Cant a facturar</th>
            <th>Precio Unit.</th>
            <th>IVA</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          ${rows.map(d=>`
            <tr>
              <td>${d.id_oc_det}</td>
              <td>${escapeHtml(d.producto||('ID '+d.id_producto))}</td>
              <td class="right">${d.pendiente}</td>
              <td><input type="number" min="1" max="${d.pendiente}" value="${d.pendiente}" style="width:90px" class="oc_qty" data-ocdet="${d.id_oc_det}"></td>
              <td><input type="number" step="0.01" min="0" value="0" style="width:110px" class="oc_price" data-ocdet="${d.id_oc_det}"></td>
              <td>
                <select class="oc_iva" data-ocdet="${d.id_oc_det}">
                  <option value="10">10%</option>
                  <option value="5">5%</option>
                  <option value="EXENTA">Exenta</option>
                </select>
              </td>
              <td><button class="btn ok" type="button" onclick="agregarLineaOC(${d.id_oc_det})">Agregar</button></td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  }catch(e){
    cont.textContent='Error';
  }
}

// Al “Agregar” una línea del detalle → baja al formulario de factura
function agregarLineaOC(id_oc_det){
  const qty = document.querySelector(`.oc_qty[data-ocdet="${id_oc_det}"]`)?.value || 0;
  const prc = document.querySelector(`.oc_price[data-ocdet="${id_oc_det}"]`)?.value || 0;
  const iva = document.querySelector(`.oc_iva[data-ocdet="${id_oc_det}"]`)?.value || '10';

  const tbody = document.querySelector('#tbl_oc tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="number" name="id_oc_det[]" required value="${id_oc_det}"></td>
    <td><input type="number" name="cantidad[]" required min="1" step="1" value="${qty}"></td>
    <td><input type="number" name="precio_unitario[]" required step="0.01" min="0" value="${prc}"></td>
    <td>
      <select name="tipo_iva[]">
        <option value="10" ${iva==='10'?'selected':''}>10%</option>
        <option value="5" ${iva==='5'?'selected':''}>5%</option>
        <option value="EXENTA" ${iva==='EXENTA'?'selected':''}>Exenta</option>
      </select>
    </td>
    <td><button class="btn danger" type="button" onclick="this.closest('tr').remove()">–</button></td>
  `;
  tbody.appendChild(tr);
}

// ======= Enviar factura (POST a tu factura_guardar.php) =======
async function enviarFactura(ev){
  ev.preventDefault();
  const msg = document.getElementById('msg');
  const tbody = document.querySelector('#tbl_oc tbody');
  if(!tbody.children.length){ msg.innerHTML='❌ <span style="color:#fca5a5">Agregá al menos una línea de OC</span>'; return false; }

  const fd = new FormData(ev.target);
  // tu factura_guardar.php espera exactamente estos names:
  // id_proveedor, fecha_emision, numero_documento, timbrado_numero (op), moneda, id_sucursal (op),
  // condicion, cuotas, dias_plazo, intervalo_dias,
  // y en detalle: id_oc_det[], cantidad[], precio_unitario[], tipo_iva[]

  msg.textContent = 'Guardando…';
  try{
    const res = await fetch(URL_GUARDAR, { method:'POST', body: fd });
    const data = await res.json().catch(()=>({ok:false,error:'Respuesta inválida'}));
    if(data.ok){
      msg.innerHTML = `✅ Guardado. ID Factura: <b>${data.id_factura}</b>`;
      ev.target.reset();
      limpiarTablaOC();
      document.getElementById('prov_info').textContent='Seleccioná un proveedor';
    }else{
      msg.innerHTML = `❌ <span style="color:#fca5a5">${escapeHtml(data.error||'Error al guardar')}</span>`;
    }
  }catch(e){
    msg.innerHTML = `❌ <span style="color:#fca5a5">Error de red/servidor</span>`;
  }
  return false;
}
</script>
</body>
</html>
