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
<title>Nuevo Pedido</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#f6f7fb; --surface:#fff; --text:#1f2937; --muted:#6b7280;
    --primary:#2563eb; --danger:#ef4444; --shadow:0 10px 24px rgba(0,0,0,.08); --radius:14px;
  }
  body{margin:0;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
  .card{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;margin-bottom:16px}
  h1{margin:0 0 10px}
  h2{margin:18px 0 8px;font-size:1.1rem}
  label{display:block;margin:8px 0 4px}
  input,select,textarea{padding:8px;border:1px solid #e5e7eb;border-radius:10px;width:100%;max-width:440px}
  .row{display:flex;gap:18px;flex-wrap:wrap}
  .btn{background:var(--primary);color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer}
  .btn[disabled]{opacity:.6;cursor:not-allowed}
  .btn-danger{background:var(--danger)}
  .btn-sm{padding:6px 10px;font-size:.9rem}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{border:1px solid #e5e7eb;padding:8px;text-align:center}
  th{background:#eef2ff}
  .muted{color:var(--muted);font-size:.92rem}
  /* sugerencias */
  .sugs{position:relative;max-width:440px}
  .sugs-list{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:var(--shadow);z-index:10;max-height:260px;overflow:auto}
  .sug{padding:8px;cursor:pointer}
  .sug:hover{background:#f3f4f6}
  /* modal */
  .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:20}
  .modal > .inner{background:#fff;border-radius:16px;max-width:900px;margin:60px auto;padding:16px}
  .right{display:flex;justify-content:flex-end;gap:8px}
  .totals{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px}
  .totals .kv{background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px}
  /* Toast */
  #toast{
    position:fixed; right:16px; top:16px; z-index:9999;
    display:none; padding:12px 14px; border-radius:10px;
    box-shadow:0 10px 24px rgba(0,0,0,.15); color:#fff; font-weight:600;
    max-width: 80vw;
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Nuevo Pedido</h1>
    <p class="muted">Completá los datos, agregá productos y guardá el pedido. El stock se <b>reserva</b> al confirmar.</p>
  </div>

  <form class="card" method="post" action="pedido_guardar.php" id="formPedido" autocomplete="off">
    <!-- CABECERA -->
    <h2>Cliente</h2>
    <div class="row">
      <div class="sugs">
        <label>Buscar cliente (CI/RUC o Nombre)</label>
        <input type="text" id="buscarCliente" placeholder="Ej: 1234567 o Juan Pérez">
        <div id="sugerencias" class="sugs-list" style="display:none"></div>
      </div>
      <div>
        <label>ID Cliente seleccionado</label>
        <input type="number" id="id_cliente" name="id_cliente" required readonly>
      </div>
      <div>
        <label>Fecha del pedido</label>
        <input type="date" id="fecha_pedido" name="fecha_pedido" required>
      </div>
    </div>

    <label>Observación</label>
    <textarea name="observacion" rows="2" placeholder="Notas del pedido..."></textarea>

    <!-- DETALLE -->
    <h2>Ítems</h2>
    <div class="right">
      <button class="btn" type="button" onclick="abrirBuscador()">Buscar producto</button>
      <button class="btn btn-danger" type="button" onclick="limpiarItems()">Limpiar ítems</button>
    </div>

    <table id="tabla">
      <thead>
        <tr>
          <th>Producto</th>
          <th>Precio</th>
          <th>IVA</th>
          <th>Stock</th>
          <th>Cantidad</th>
          <th>Descuento</th>
          <th>Subtotal (est.)</th>
          <th>Quitar</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>

    <div class="totals" id="totals">
      <div class="kv"><b>Bruto:</b> <span id="tBruto">0</span></div>
      <div class="kv"><b>Desc.:</b> <span id="tDesc">0</span></div>
      <div class="kv"><b>IVA:</b> <span id="tIva">0</span></div>
      <div class="kv"><b>Neto:</b> <span id="tNeto">0</span></div>
    </div>

    <div class="right" style="margin-top:12px">
      <button class="btn" id="btnGuardar" type="submit">Guardar Pedido</button>
    </div>

    <!-- Contenedor oculto de inputs items[n][...] para enviar -->
    <div id="hiddenInputs"></div>
  </form>

  <!-- MODAL BUSCADOR PRODUCTOS -->
  <div class="modal" id="modalBuscar">
    <div class="inner">
      <div class="row">
        <div style="flex:1">
          <label>Buscar producto</label>
          <input type="text" id="qProd" placeholder="Nombre del producto..." onkeyup="buscarProductos()">
        </div>
        <div class="right" style="align-items:flex-end">
          <button class="btn" type="button" onclick="cerrarBuscador()">Cerrar</button>
        </div>
      </div>

      <table style="margin-top:10px">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Precio</th>
            <th>IVA</th>
            <th>Disponible</th>
            <th>Agregar</th>
          </tr>
        </thead>
        <tbody id="tbodyBuscar"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="toast"></div>

<script>
/* ====== Toast ====== */
function showToast(msg, type='ok') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = (type==='ok') ? '#16a34a' : '#ef4444';
  t.style.display = 'block';
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(()=>{ t.style.display='none'; }, 3000);
}

/* ====== Setear fecha por defecto (hoy) ====== */
const inputFecha = document.getElementById('fecha_pedido');
function setHoy() {
  const hoy = new Date();
  const yyyy = hoy.getFullYear();
  const mm = String(hoy.getMonth()+1).padStart(2,'0');
  const dd = String(hoy.getDate()).padStart(2,'0');
  inputFecha.value = `${yyyy}-${mm}-${dd}`;
}
document.addEventListener('DOMContentLoaded', setHoy);

/* ====== Autocompletar Cliente ====== */
const inpCli = document.getElementById('buscarCliente');
const sugsBox = document.getElementById('sugerencias');
const idCliente = document.getElementById('id_cliente');

let cliTimer = null;
inpCli.addEventListener('keyup', () => {
  const q = inpCli.value.trim();
  clearTimeout(cliTimer);
  if (q.length < 2) { sugsBox.style.display = 'none'; sugsBox.innerHTML=''; return; }
  cliTimer = setTimeout(async () => {
    const res = await fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/cliente/clientes_buscar.php?q='+encodeURIComponent(q));
    const js  = await res.json();
    sugsBox.innerHTML = '';
    if (js.ok && js.data.length) {
      js.data.forEach(c => {
        const d = document.createElement('div');
        d.className='sug';
        d.textContent = `${c.nombre_completo} (${c.ruc_ci||'s/CI'})`;
        d.onclick = () => {
          inpCli.value = c.nombre_completo + (c.ruc_ci?` (${c.ruc_ci})`:'');
          idCliente.value = c.id_cliente;
          sugsBox.style.display='none';
        };
        sugsBox.appendChild(d);
      });
      sugsBox.style.display='block';
    } else {
      sugsBox.style.display='none';
    }
  }, 250);
});
document.addEventListener('click', (e)=>{
  if (!sugsBox.contains(e.target) && e.target!==inpCli) sugsBox.style.display='none';
});

/* ====== Estado del carrito/ítems ====== */
const tbody = document.getElementById('tbody');
const hiddenInputs = document.getElementById('hiddenInputs');
let items = []; // {id_producto, nombre, precio_unitario, tipo_iva, stock_disponible, cantidad, descuento}

function ivaRate(tipo){ return tipo==='10%'?0.10:(tipo==='5%'?0.05:0.0); }
function fmt(n){ return Number(n||0).toLocaleString('es-PY', {minimumFractionDigits:0}); }
function fmt2(n){ return Number(n||0).toLocaleString('es-PY', {minimumFractionDigits:2}); }

function render(){
  tbody.innerHTML='';
  let tBruto=0, tDesc=0, tIva=0, tNeto=0;

  items.forEach((it, idx)=>{
    const subtotalBruto = it.cantidad * it.precio_unitario;
    const base = Math.max(0, subtotalBruto - (it.descuento||0));
    const iva = base * ivaRate(it.tipo_iva);
    const subNeto = base + iva;

    tBruto += subtotalBruto;
    tDesc  += (it.descuento||0);
    tIva   += iva;
    tNeto  += subNeto;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="text-align:left">${it.nombre}</td>
      <td>${fmt2(it.precio_unitario)}</td>
      <td>${it.tipo_iva||'Exento'}</td>
      <td>${fmt(it.stock_disponible)}</td>
      <td>
        <input type="number" min="1" step="1" value="${it.cantidad}"
               oninput="cambiarCantidad(${idx}, this.value)" style="width:90px">
      </td>
      <td>
        <input type="number" min="0" step="0.01" value="${it.descuento||0}"
               oninput="cambiarDescuento(${idx}, this.value)" style="width:110px">
      </td>
      <td>${fmt2(subNeto)}</td>
      <td><button type="button" class="btn btn-danger btn-sm" onclick="quitar(${idx})">X</button></td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('tBruto').textContent = fmt2(tBruto);
  document.getElementById('tDesc').textContent  = fmt2(tDesc);
  document.getElementById('tIva').textContent   = fmt2(tIva);
  document.getElementById('tNeto').textContent  = fmt2(tNeto);

  hiddenInputs.innerHTML='';
  items.forEach((it, i)=>{
    const baseName = `items[${i}]`;
    hiddenInputs.insertAdjacentHTML('beforeend', `
      <input type="hidden" name="${baseName}[id_producto]" value="${it.id_producto}">
      <input type="hidden" name="${baseName}[cantidad]" value="${it.cantidad}">
      <input type="hidden" name="${baseName}[precio_unitario]" value="${it.precio_unitario}">
      <input type="hidden" name="${baseName}[descuento]" value="${it.descuento||0}">
      <input type="hidden" name="${baseName}[tipo_iva]" value="${it.tipo_iva}">
    `);
  });
}

function cambiarCantidad(idx, val){
  const it = items[idx];
  let v = parseInt(val||'0',10);
  if (!Number.isFinite(v) || v<1) v=1;
  if (v > it.stock_disponible) v = it.stock_disponible;
  it.cantidad = v;
  render();
}
function cambiarDescuento(idx, val){
  const it = items[idx];
  let v = parseFloat(val||'0');
  if (!Number.isFinite(v) || v<0) v=0;
  const maxDesc = it.cantidad * it.precio_unitario;
  if (v > maxDesc) v = maxDesc;
  it.descuento = v;
  render();
}
function quitar(idx){ items.splice(idx,1); render(); }
function limpiarItems(){ items = []; render(); }

function agregarItem(prod){
  const found = items.find(p => p.id_producto === prod.id_producto);
  if (found){
    const posible = Math.min(found.stock_disponible, found.cantidad + 1);
    found.cantidad = posible;
  } else {
    items.push({
      id_producto: prod.id_producto,
      nombre: prod.nombre,
      precio_unitario: Number(prod.precio_unitario),
      tipo_iva: prod.tipo_iva || 'Exento',
      stock_disponible: Number(prod.stock_disponible),
      cantidad: 1,
      descuento: 0
    });
  }
  render();
}

/* ====== Buscador de productos (modal) ====== */
const modal = document.getElementById('modalBuscar');
const tbodyBuscar = document.getElementById('tbodyBuscar');
const inpProd = document.getElementById('qProd');

function abrirBuscador(){ modal.style.display='block'; buscarProductos(); }
function cerrarBuscador(){ modal.style.display='none'; tbodyBuscar.innerHTML=''; inpProd.value=''; }

let prodTimer=null;
async function buscarProductos(){
  clearTimeout(prodTimer);
  prodTimer = setTimeout(async ()=>{
    const q = inpProd.value.trim();
    const url = q ? `productos_disponibles.php?q=${encodeURIComponent(q)}` : 'productos_disponibles.php';
    const res = await fetch(url);
    const js = await res.json();
    tbodyBuscar.innerHTML = '';
    if (js.success && js.productos.length){
      js.productos.forEach(p=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td style="text-align:left">${p.nombre}</td>
          <td>${Number(p.precio_unitario).toLocaleString('es-PY',{minimumFractionDigits:2})}</td>
          <td>${p.tipo_iva || 'Exento'}</td>
          <td>${Number(p.stock_disponible)}</td>
          <td><button type="button" class="btn btn-sm" onclick='agregarItem(${JSON.stringify(p).replace(/'/g,"&#39;")})'>+</button></td>
        `;
        tbodyBuscar.appendChild(tr);
      });
    } else {
      tbodyBuscar.innerHTML = `<tr><td colspan="5">Sin resultados</td></tr>`;
    }
  }, 200);
}

/* ====== Submit por fetch + toast ====== */
const formPedido = document.getElementById('formPedido');
const btnGuardar  = document.getElementById('btnGuardar');

formPedido.addEventListener('submit', async (e)=>{
  e.preventDefault();

  if (!idCliente.value){ showToast('Seleccioná un cliente','err'); return; }
  if (items.length===0){ showToast('Agregá al menos un ítem','err'); return; }
  for (const it of items){
    if (it.cantidad < 1 || it.cantidad > it.stock_disponible){
      showToast('Cantidad inválida para '+it.nombre,'err'); return;
    }
  }

  const fd = new FormData(formPedido);

  btnGuardar.disabled = true;
  btnGuardar.textContent = 'Guardando...';

  try {
    const res  = await fetch('pedido_guardar.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With':'fetch' }
    });
    const json = await res.json();

    if (json.success) {
      showToast(`✅ Pedido #${json.id_pedido} guardado`, 'ok');
      items = [];
      render();
      formPedido.reset();
      setHoy(); // vuelve a poner hoy en la fecha
      document.getElementById('sugerencias').style.display='none';
    } else {
      showToast(json.error || 'No se pudo guardar el pedido', 'err');
    }
  } catch (err) {
    showToast('Error de red/servidor', 'err');
  } finally {
    btnGuardar.disabled = false;
    btnGuardar.textContent = 'Guardar Pedido';
  }
});

// Render inicial
render();
</script>
</body>
</html>
