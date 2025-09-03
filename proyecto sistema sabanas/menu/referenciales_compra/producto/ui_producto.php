<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Referencial de Productos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../../styles.css">
  <script src="../../navbar.js" defer></script>
  <style>
    :root{ --b:#0c49aa; --bg:#f5f7fb; --br:#e9e9e9; --txt:#1f2937; --muted:#6b7280; }
    *{box-sizing:border-box}
    body{font-family:Arial, sans-serif;background:var(--bg);margin:0;padding:20px;color:var(--txt)}
    .wrap{max-width:1100px;margin:auto}
    h1{margin:0 0 14px}
    .card{background:#fff;border:1px solid var(--br);border-radius:12px;padding:16px;margin-bottom:16px}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    label{font-size:13px;color:#333}
    input,select{width:100%;padding:10px;border:1px solid #dcdcdc;border-radius:8px;background:#fff}
    .actions{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
    .btn{padding:9px 12px;border:1px solid #ddd;background:#fff;cursor:pointer;border-radius:8px}
    .btn:hover{background:#f3f3f3}
    .btn.primary{background:var(--b);color:#fff;border-color:var(--b)}
    .btn.primary:hover{filter:brightness(0.95)}
    .btn.danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
    .btn.small{padding:6px 10px;border-radius:6px}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #eee;padding:8px;text-align:left;vertical-align:top}
    th{background:#fafafa}
    .right{text-align:right}
    .muted{color:var(--muted)}
    tr.inactivo td{color:#888;background:#fbfbfb}
    .margen-neg { color:#b91c1c; font-weight:bold; }
    @media (max-width:920px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>
  <div id="navbar-container"></div>

  <div class="wrap">
    <h1>Productos</h1>

    <!-- Formulario -->
    <div class="card">
      <h3 style="margin-top:0">Registro / Edición</h3>
      <form id="formProd" autocomplete="off">
        <input type="hidden" id="id_producto" name="id_producto">

        <div class="grid">
          <div>
            <label>Nombre *</label>
            <input type="text" id="nombre" name="nombre" required>
          </div>
          <div>
            <label>Categoría</label>
            <input type="text" id="categoria" name="categoria" placeholder="p.ej. Sábanas">
          </div>

          <div>
            <label>Precio Venta *</label>
            <input type="number" step="0.01" min="0" id="precio_unitario" name="precio_unitario" required>
          </div>
          <div>
            <label>Precio Compra *</label>
            <input type="number" step="0.01" min="0" id="precio_compra" name="precio_compra" required>
          </div>

          <div>
            <label>IVA *</label>
            <select id="tipo_iva" name="tipo_iva" required>
              <option value="10%">10%</option>
              <option value="5%">5%</option>
              <option value="Exento">Exento</option>
            </select>
          </div>
          <div>
            <label>Estado</label>
            <select id="estado" name="estado">
              <option value="Activo">Activo</option>
              <option value="Inactivo">Inactivo</option>
            </select>
          </div>
        </div>

        <div class="actions">
          <button type="button" class="btn" id="btnNuevo">Nuevo</button>
          <button type="submit" class="btn primary" id="btnGuardar">Guardar</button>
        </div>
      </form>
    </div>

    <!-- Filtros y tabla -->
    <div class="card">
      <div class="row" style="margin-bottom:8px; gap:8px">
        <input id="filtro" placeholder="Buscar por nombre/categoría…" style="flex:1">
        <select id="filtroCategoria" style="min-width:180px">
          <option value="">Todas las categorías</option>
        </select>
        <select id="filtroIVA" style="min-width:140px">
          <option value="">Todos (IVA)</option>
          <option value="10%">10%</option>
          <option value="5%">5%</option>
          <option value="Exento">Exento</option>
        </select>
        <select id="filtroEstado" style="min-width:140px">
          <option value="">Todos (estado)</option>
          <option value="Activo">Activo</option>
          <option value="Inactivo">Inactivo</option>
        </select>
        <button class="btn" id="btnRefrescar">Refrescar</button>
      </div>

      <table id="tabla">
        <thead>
          <tr>
            <th>ID</th>
            <th>Producto</th>
            <th>Categoría</th>
            <th>IVA</th>
            <th class="right">P. Venta</th>
            <th class="right">P. Compra</th>
            <th class="right">Margen (Gs)</th>
            <th class="right">Margen %</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody><!-- rows --></tbody>
      </table>
    </div>
  </div>

  <script>
    const RUTAS = {
      listar:    'producto_listar.php',
      guardar:   'producto_guardar.php',
      eliminar:  'producto_eliminar.php',
      restaurar: 'producto_restaurar.php'
    };

    const $ = s => document.querySelector(s);
    let productos = [];

    document.addEventListener('DOMContentLoaded', () => {
      $('#btnNuevo').addEventListener('click', limpiarForm);
      $('#btnRefrescar').addEventListener('click', listar);
      $('#formProd').addEventListener('submit', onGuardar);

      $('#filtro').addEventListener('input', filtrarEnMemoria);
      $('#filtroCategoria').addEventListener('change', listar);
      $('#filtroIVA').addEventListener('change', listar);
      $('#filtroEstado').addEventListener('change', listar);

      listar();
    });

    async function listar(){
      const params = new URLSearchParams();
      const q = ($('#filtro').value || '').trim();
      const cat = $('#filtroCategoria').value || '';
      const iva = $('#filtroIVA').value || '';
      const est = $('#filtroEstado').value || '';

      if (q)   params.set('q', q);
      if (cat) params.set('categoria', cat);
      if (iva) params.set('iva', iva);
      if (est) params.set('estado', est);

      try{
        const r = await fetch(RUTAS.listar + (params.toString()? ('?'+params.toString()) : ''));
        let j; try { j = await r.json(); } catch { const raw = await r.text(); console.error('NO JSON:', raw); throw new Error('Respuesta no JSON'); }
        if(!Array.isArray(j)) throw new Error('Respuesta inválida');
        productos = j;
        renderFiltrosDinamicos(productos);
        renderTabla(productos);
      }catch(e){
        console.error(e);
        alert('Error cargando productos');
      }
    }

    function renderFiltrosDinamicos(rows){
      const setCat = new Set(rows.map(x => (x.categoria||'').trim()).filter(Boolean));
      const sel = $('#filtroCategoria');
      const current = sel.value;
      sel.innerHTML = '<option value="">Todas las categorías</option>';
      Array.from(setCat).sort((a,b)=>a.localeCompare(b)).forEach(c=>{
        const o = document.createElement('option'); o.value = c; o.textContent = c; sel.appendChild(o);
      });
      if (current && setCat.has(current)) sel.value = current;
    }

    function renderTabla(rows){
      const tb = $('#tabla tbody');
      tb.innerHTML = '';
      if(!rows || rows.length===0){
        tb.innerHTML = '<tr><td colspan="10" class="right muted">Sin resultados</td></tr>';
        return;
      }
      rows.forEach(p=>{
        const inactivo = (p.estado||'').toLowerCase() !== 'activo';
        const margenGs = (p.precio_unitario||0) - (p.precio_compra||0);
        const margenPct = (p.precio_unitario>0) ? (margenGs / p.precio_unitario) * 100 : 0;

        const tr = document.createElement('tr');
        if (inactivo) tr.classList.add('inactivo');

        tr.innerHTML = `
          <td>${p.id_producto}</td>
          <td>${esc(p.nombre)}</td>
          <td>${esc(p.categoria||'')}</td>
          <td>${esc(p.tipo_iva||'')}</td>
          <td class="right">${fmt(p.precio_unitario)}</td>
          <td class="right">${fmt(p.precio_compra)}</td>
          <td class="right ${margenGs<0?'margen-neg':''}">${fmt(margenGs)}</td>
          <td class="right ${margenGs<0?'margen-neg':''}">${margenPct.toFixed(2)}%</td>
          <td>${esc(p.estado||'')}</td>
          <td>
            <button class="btn small" onclick='editar(${JSON.stringify(p.id_producto)})'>Editar</button>
            ${!inactivo
              ? `<button class="btn small danger" onclick='eliminarProd(${JSON.stringify(p.id_producto)})'>Inactivar</button>`
              : `<button class="btn small" onclick='restaurarProd(${JSON.stringify(p.id_producto)})'>Activar</button>`
            }
          </td>
        `;
        tb.appendChild(tr);
      });
    }

    function filtrarEnMemoria(){
      const q = ($('#filtro').value||'').toLowerCase();
      if(!q){ renderTabla(productos); return; }
      const f = productos.filter(p =>
        (p.nombre||'').toLowerCase().includes(q) ||
        (p.categoria||'').toLowerCase().includes(q)
      );
      renderTabla(f);
    }

    async function onGuardar(e){
      e.preventDefault();
      const nombre = $('#nombre').value.trim();
      const pv = parseFloat($('#precio_unitario').value);
      const pc = parseFloat($('#precio_compra').value);
      if(!nombre || isNaN(pv) || isNaN(pc)){ alert('Completá nombre y precios'); return; }
      if(pv < 0 || pc < 0){ alert('Los precios no pueden ser negativos'); return; }

      const fd = new FormData($('#formProd'));
      try{
        const r = await fetch(RUTAS.guardar, {method:'POST', body: fd});
        let j; try { j = await r.json(); } catch { j = null; }
        if(!r.ok || !j || !j.ok) throw new Error(j?.error || 'No se pudo guardar');
        alert(j.msg || 'Producto guardado');
        limpiarForm(); listar();
      }catch(err){ console.error(err); alert(err.message||'Error guardando'); }
    }

    window.editar = function(id){
      const p = productos.find(x=> x.id_producto == id);
      if(!p) return;
      $('#id_producto').value = p.id_producto;
      $('#nombre').value = p.nombre || '';
      $('#categoria').value = p.categoria || '';
      $('#precio_unitario').value = p.precio_unitario || 0;
      $('#precio_compra').value = p.precio_compra || 0;
      $('#tipo_iva').value = p.tipo_iva || '10%';
      $('#estado').value = p.estado || 'Activo';
      window.scrollTo({top:0, behavior:'smooth'});
    }

    window.eliminarProd = async function(id){
      if(!confirm('¿Inactivar este producto?')) return;
      const fd = new FormData(); fd.append('id_producto', id);
      try{
        const r = await fetch(RUTAS.eliminar, {method:'POST', body: fd});
        const j = await r.json();
        if(!j.ok) throw new Error(j.error||'No se pudo inactivar');
        alert(j.msg || 'Producto inactivado'); listar();
      }catch(e){ alert(e.message||'Error inactivando'); }
    }

    window.restaurarProd = async function(id){
      if(!confirm('¿Activar este producto?')) return;
      const fd = new FormData(); fd.append('id_producto', id);
      try{
        const r = await fetch(RUTAS.restaurar, {method:'POST', body: fd});
        const j = await r.json();
        if(!j.ok) throw new Error(j.error||'No se pudo activar');
        alert(j.msg || 'Producto activado'); listar();
      }catch(e){ alert(e.message||'Error activando'); }
    }

    function limpiarForm(){
      $('#formProd').reset();
      $('#id_producto').value = '';
      $('#estado').value = 'Activo';
      $('#tipo_iva').value = '10%';
    }

    function fmt(n){ return Number(n||0).toLocaleString('es-PY',{minimumFractionDigits:2, maximumFractionDigits:2}); }
    function esc(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  </script>
</body>
</html>
