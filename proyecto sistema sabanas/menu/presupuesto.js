// presupuesto.js (versión con edición fluida sin repintar toda la tabla)
let resumenPorProducto = {}; // id_producto -> {nombre, pedida, cotizada, pendiente, precio_sugerido}
let detalle = [];            // [{id_producto, nombre, cantidad, precio_unitario}]

const $ = s => document.querySelector(s);

document.addEventListener('DOMContentLoaded', () => {
  setFechas();
  cargarPedidos();
  cargarProveedores();

  $('#pedido').addEventListener('change', cargarProductosDelPedido);
  $('#btnAgregar').addEventListener('click', agregarLinea);
  $('#btnGuardar').addEventListener('click', guardarPresupuesto);
});

function setFechas(){
  const hoy = new Date().toISOString().slice(0,10);
  const fr = $('#fecha_registro');
  if (fr) fr.value = hoy;
}

async function cargarPedidos(){
  try{
    // 👇 Agregamos el scope=presupuesto para filtrar
    const res = await fetch('pedidos_options.php?scope=presupuesto');
    const data = await res.json();
    const sel = $('#pedido');
    sel.innerHTML = '<option value="">Selecciona un pedido</option>';
    data.forEach(r=>{
      const opt = document.createElement('option');
      opt.value = r.numero_pedido;
      opt.textContent = `#${r.numero_pedido} - ${r.departamento_solicitante} (${r.estado})`;
      sel.appendChild(opt);
    });
  }catch(e){
    console.error(e);
    alert('Error cargando pedidos');
  }
}


async function cargarProveedores(){
  try{
    const res = await fetch('proveedores_options.php');
    const data = await res.json();
    const sel = $('#proveedor');
    sel.innerHTML = '<option value="">Selecciona un proveedor</option>';
    data.forEach(r=>{
      const opt = document.createElement('option');
      opt.value = r.id_proveedor;
      opt.textContent = r.nombre;
      sel.appendChild(opt);
    });
  }catch(e){
    console.error(e);
    alert('Error cargando proveedores');
  }
}

async function cargarProductosDelPedido(){
  // limpiar detalle y tabla
  detalle = [];
  pintarDetalle(); // solo una vez para limpiar

  const nro = $('#pedido').value;
  const selProd = $('#producto');
  resumenPorProducto = {};

  if(!nro){
    if (selProd) selProd.innerHTML = '<option value="">Selecciona un pedido primero</option>';
    return;
  }

  try{
    const res = await fetch('pedido_resumen.php?numero_pedido='+encodeURIComponent(nro));
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error');

    const items = json.data || [];

    if (selProd) selProd.innerHTML = '<option value="">(opcional) Agregar más productos del pedido</option>';

    items.forEach(it=>{
      resumenPorProducto[it.id_producto] = it;
      if (selProd && +it.pendiente > 0) {
        const opt = document.createElement('option');
        opt.value = it.id_producto;
        opt.textContent = `${it.nombre} · Pend:${it.pendiente}`;
        selProd.appendChild(opt);
      }
      const cant = Math.max(0, +it.pendiente || 0);
      if (cant > 0) {
        const precio = it.precio_sugerido ? +it.precio_sugerido : 0;
        detalle.push({
          id_producto: +it.id_producto,
          nombre: it.nombre,
          cantidad: cant,
          precio_unitario: precio
        });
      }
    });

    pintarDetalle(); // render inicial

    if (selProd && selProd.options.length === 1) {
      selProd.innerHTML = '<option value="">Sin pendientes en este pedido</option>';
    }
  }catch(e){
    console.error(e);
    alert('Error cargando productos del pedido');
    if (selProd) selProd.innerHTML = '<option value="">Error</option>';
  }
}

function agregarLinea(){
  const selProd = $('#producto');
  if (!selProd) return;

  const idp  = +selProd.value;
  const cant = +($('#cantidad')?.value || 0);
  const prec = +($('#precio')?.value || 0);

  if(!idp){ alert('Elegí un producto'); return; }
  if(!cant || cant <= 0){ alert('Cantidad inválida'); return; }
  if(prec < 0){ alert('Precio inválido'); return; }

  const info = resumenPorProducto[idp];
  if(!info){ alert('Producto inválido'); return; }

  const yaAgregado = detalle
    .filter(d => d.id_producto === idp)
    .reduce((acc,d)=> acc + d.cantidad, 0);

  const disponible = (+info.pendiente) - yaAgregado;
  if (cant > disponible) {
    alert(`No podés exceder el Pendiente. Disponible: ${disponible}`);
    return;
  }

  detalle.push({ id_producto:idp, nombre:info.nombre, cantidad:cant, precio_unitario:prec });
  pintarDetalle();

  selProd.value = '';
  const c = $('#cantidad'); if (c) c.value = '';
  const p = $('#precio');   if (p) p.value = '';
}

/** Renderiza la tabla. IMPORTANTE: los inputs llaman a editarCantidad/editarPrecio SIN repintar. */
function pintarDetalle(){
  const tb = $('#tablaDetalle tbody');
  if (!tb) return;
  tb.innerHTML = '';

  detalle.forEach((d, i) => {
    const lineTotal = (Number(d.cantidad) || 0) * (Number(d.precio_unitario) || 0);
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${d.id_producto}</td>
      <td>${d.nombre}</td>
      <td>
        <input type="number" min="1" value="${d.cantidad}" inputmode="numeric"
               oninput="editarCantidad(${i}, this)" style="width:90px">
      </td>
      <td>
        <input type="number" min="0" step="0.01" value="${d.precio_unitario}" inputmode="decimal"
               oninput="editarPrecio(${i}, this)" style="width:110px">
      </td>
      <td id="rowTotal-${i}">${lineTotal.toFixed(2)}</td>
      <td><button type="button" onclick="quitar(${i})">Quitar</button></td>
    `;
    tb.appendChild(tr);
  });

  actualizarTotalGeneral();
}

/** Actualiza SOLO la fila y el total general (sin repintar toda la tabla). */
function editarCantidad(idx, el){
  const nueva = Math.max(1, +el.value || 1);
  const item = detalle[idx];
  if (!item) return;

  const idp = item.id_producto;
  const info = resumenPorProducto[idp];
  if (info) {
    const sumaOtras = detalle.reduce((acc, d, i) => acc + (i!==idx && d.id_producto===idp ? (Number(d.cantidad)||0) : 0), 0);
    const disponible = (+info.pendiente) - sumaOtras;

    if (nueva > disponible){
      alert(`No puede exceder el pendiente. Disponible: ${disponible}`);
      item.cantidad = disponible > 0 ? disponible : 1;
      el.value = item.cantidad; // reflejar corrección sin repintar
    } else {
      item.cantidad = nueva;
    }
  } else {
    item.cantidad = nueva;
  }

  actualizarTotalFila(idx);
  actualizarTotalGeneral();
}

function editarPrecio(idx, el){
  const n = +el.value;
  if (!detalle[idx]) return;
  detalle[idx].precio_unitario = n >= 0 ? n : 0;
  if (n < 0) el.value = 0; // corregir visualmente
  actualizarTotalFila(idx);
  actualizarTotalGeneral();
}

function actualizarTotalFila(idx){
  const d = detalle[idx];
  const total = (Number(d.cantidad)||0) * (Number(d.precio_unitario)||0);
  const cell = document.getElementById(`rowTotal-${idx}`);
  if (cell) cell.textContent = total.toFixed(2);
}

function actualizarTotalGeneral(){
  let total = 0;
  detalle.forEach(d => total += (Number(d.cantidad)||0) * (Number(d.precio_unitario)||0));
  const tot = $('#total');
  if (tot) tot.textContent = total.toFixed(2);
}

function quitar(idx){
  detalle.splice(idx,1);
  pintarDetalle(); // re-render para reindexar ids rowTotal-*
}

async function guardarPresupuesto(){
  const numero_pedido = $('#pedido')?.value;
  const id_proveedor  = $('#proveedor')?.value;
  const fecha_registro= $('#fecha_registro')?.value;
  const fecha_venc    = $('#fecha_vencimiento')?.value;

  if(!numero_pedido){ alert('Seleccioná un pedido'); return; }
  if(!id_proveedor){  alert('Seleccioná un proveedor'); return; }
  if(detalle.length === 0){ alert('No hay líneas en el detalle'); return; }

  const fd = new FormData();
  fd.append('numero_pedido', numero_pedido);
  fd.append('id_proveedor', id_proveedor);
  if (fecha_registro) fd.append('fecharegistro', fecha_registro);
  if (fecha_venc)     fd.append('fechavencimiento', fecha_venc);

  detalle.forEach(d=>{
    fd.append('id_producto[]', d.id_producto);
    fd.append('cantidad[]', d.cantidad);
    fd.append('precio_unitario[]', d.precio_unitario);
  });

  try{
    const res = await fetch('guardar_presupuesto.php', { method:'POST', body: fd });
    const json = await res.json();
    if (!res.ok || !json.ok) {
      alert('Error al guardar: ' + (json.error || ''));
      return;
    }
    alert('Presupuesto guardado. ID: ' + json.id_presupuesto);

    // reset mínimo
    detalle = [];
    pintarDetalle();
    await cargarProductosDelPedido(); // refresca pendientes
  }catch(e){
    console.error(e);
    alert('Error al guardar presupuesto');
  }
}
