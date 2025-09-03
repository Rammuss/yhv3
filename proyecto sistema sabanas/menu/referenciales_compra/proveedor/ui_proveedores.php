<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Referencial de Proveedores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../../styles.css">
  <script src="../../navbar.js" defer></script>
  <style>
    :root{
      --b:#0c49aa; --bg:#f5f7fb; --br:#e9e9e9; --txt:#1f2937; --muted:#6b7280;
    }
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
    tr.eliminado td{color:#888;background:#fbfbfb}
    @media (max-width:920px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>
  <div id="navbar-container"></div>

  <div class="wrap">
    <h1>Proveedores</h1>

    <!-- Formulario -->
    <div class="card">
      <h3 style="margin-top:0">Registro / Edición</h3>
      <form id="formProv" autocomplete="off">
        <input type="hidden" id="id_proveedor" name="id_proveedor">

        <div class="grid">
          <div>
            <label>Nombre/Razón Social *</label>
            <input type="text" id="nombre" name="nombre" required>
          </div>
          <div>
            <label>RUC *</label>
            <input type="text" id="ruc" name="ruc" required>
          </div>
          <div>
            <label>Teléfono *</label>
            <input type="text" id="telefono" name="telefono" required>
          </div>
          <div>
            <label>Email *</label>
            <input type="email" id="email" name="email" required>
          </div>
          <div>
            <label>País *</label>
            <select id="id_pais" name="id_pais" required></select>
          </div>
          <div>
            <label>Ciudad *</label>
            <select id="id_ciudad" name="id_ciudad" required></select>
          </div>
          <div class="grid" style="grid-template-columns: 1fr;">
            <div>
              <label>Dirección *</label>
              <input type="text" id="direccion" name="direccion" required>
            </div>
          </div>
          <div>
            <label>Tipo *</label>
            <select id="tipo" name="tipo" required>
              <option value="PROVEEDOR">Proveedor</option>
              <option value="FONDO_FIJO">Fondo Fijo</option>
              <option value="SERVICIO">Servicio</option>
              <option value="TRANSPORTISTA">Transportista</option>
              <option value="OTRO">Otro</option>
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
        <input id="filtro" placeholder="Buscar por nombre, RUC o email…" style="flex:1">
        <select id="filtroTipo">
          <option value="">Todos los tipos</option>
          <option value="PROVEEDOR">Proveedor</option>
          <option value="FONDO_FIJO">Fondo Fijo</option>
          <option value="SERVICIO">Servicio</option>
          <option value="TRANSPORTISTA">Transportista</option>
          <option value="OTRO">Otro</option>
        </select>
        <label style="display:flex;align-items:center;gap:6px">
          <input type="checkbox" id="verEliminados"> Incluir eliminados
        </label>
        <button class="btn" id="btnRefrescar">Refrescar</button>
      </div>

      <table id="tabla">
        <thead>
          <tr>
            <th>ID</th>
            <th>Proveedor</th>
            <th>RUC</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Ciudad</th>
            <th>País</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody><!-- rows --></tbody>
      </table>
    </div>
  </div>

  <script>
    // Ajustá si tus PHP están en otra carpeta
    const RUTAS = {
      listar:    'proveedores_listar.php',      // acepta: ?q=, ?tipo=, ?incluir_eliminados=1
      guardar:   'proveedor_guardar.php',
      eliminar:  'proveedor_eliminar.php',      // soft delete
      restaurar: 'proveedor_restaurar.php',
      paises:    'paises_options.php',
      ciudades:  'ciudades_options.php'         // ?id_pais=
    };

    const $ = s => document.querySelector(s);
    let proveedores = [];     // cache de la tabla
    let ciudadesCache = {};   // cache por id_pais

    document.addEventListener('DOMContentLoaded', () => {
      cargarPaises().then(()=> cargarCiudades($('#id_pais').value));

      $('#btnNuevo').addEventListener('click', limpiarForm);
      $('#btnRefrescar').addEventListener('click', listar);
      $('#filtro').addEventListener('input', filtrarEnMemoria);
      $('#filtroTipo').addEventListener('change', listar);
      $('#verEliminados').addEventListener('change', listar);
      $('#formProv').addEventListener('submit', onGuardar);
      $('#id_pais').addEventListener('change', (e)=> cargarCiudades(e.target.value));

      listar();
    });

    async function cargarPaises(){
      try{
        const r = await fetch(RUTAS.paises);
        const data = await r.json();
        const sel = $('#id_pais');
        sel.innerHTML = '';
        (data||[]).forEach(p=>{
          const o = document.createElement('option');
          o.value = p.id_pais;
          o.textContent = p.nombre;
          sel.appendChild(o);
        });
      }catch(e){
        console.warn('No se pudo cargar países, usando fallback');
        $('#id_pais').innerHTML = `
          <option value="1">Paraguay</option>
          <option value="2">Brasil</option>
          <option value="3">Argentina</option>
          <option value="4">Bolivia</option>
        `;
      }
    }

    async function cargarCiudades(id_pais){
      if(!id_pais){ $('#id_ciudad').innerHTML = '<option value="">Seleccione país</option>'; return; }
      if (ciudadesCache[id_pais]){
        renderCiudades(ciudadesCache[id_pais]); return;
      }
      try{
        const r = await fetch(`${RUTAS.ciudades}?id_pais=${encodeURIComponent(id_pais)}`);
        const data = await r.json();
        ciudadesCache[id_pais] = data||[];
        renderCiudades(ciudadesCache[id_pais]);
      }catch(e){
        console.warn('No se pudo cargar ciudades, usando fallback');
        $('#id_ciudad').innerHTML = `
          <option value="1">Asunción</option>
          <option value="2">Villa Elisa</option>
          <option value="3">Lambaré</option>
        `;
      }
    }

    function renderCiudades(list){
      const sel = $('#id_ciudad');
      sel.innerHTML = '';
      (list||[]).forEach(c=>{
        const o = document.createElement('option');
        o.value = c.id_ciudad;
        o.textContent = c.nombre;
        sel.appendChild(o);
      });
    }

    async function listar(){
      const params = new URLSearchParams();
      const tipo = $('#filtroTipo').value || '';
      const incluir = $('#verEliminados').checked ? '1' : '';
      const q = ($('#filtro').value || '').trim();

      if (tipo) params.set('tipo', tipo);
      if (incluir) params.set('incluir_eliminados', '1');
      if (q) params.set('q', q);

      try{
        const r = await fetch(RUTAS.listar + (params.toString()? ('?'+params.toString()) : ''));
        const j = await r.json();
        if(!Array.isArray(j)) throw new Error('Respuesta inválida');
        proveedores = j;
        renderTabla(proveedores);
      }catch(e){
        console.error(e);
        alert('Error cargando proveedores');
      }
    }

    function renderTabla(rows){
      const tb = $('#tabla tbody');
      tb.innerHTML = '';
      if(!rows || rows.length===0){
        tb.innerHTML = '<tr><td colspan="10" class="right muted">Sin resultados</td></tr>';
        return;
      }
      rows.forEach(p=>{
        // ► Detectar estado real desde backend
        const tieneDeletedAt = p.deleted_at !== null && p.deleted_at !== undefined;
        const esActivoBackend = (p.estado || '').toLowerCase() === 'activo';

        const eliminado = tieneDeletedAt || !esActivoBackend;
        const estadoTxt = tieneDeletedAt
            ? 'Eliminado'
            : (esActivoBackend ? 'Activo' : (p.estado || 'Inactivo'));

        const tr = document.createElement('tr');
        if (eliminado) tr.classList.add('eliminado');

        tr.innerHTML = `
          <td>${p.id_proveedor}</td>
          <td>${esc(p.nombre)}</td>
          <td>${esc(p.ruc||'')}</td>
          <td>${esc(p.telefono||'')}</td>
          <td>${esc(p.email||'')}</td>
          <td>${esc(p.ciudad||'')}</td>
          <td>${esc(p.pais||'')}</td>
          <td>${esc(p.tipo||'PROVEEDOR')}</td>
          <td>${esc(estadoTxt)}</td>
          <td>
            <button class="btn small" onclick='editar(${JSON.stringify(p.id_proveedor)})'>Editar</button>
            ${!eliminado
              ? `<button class="btn small danger" onclick='eliminarProv(${JSON.stringify(p.id_proveedor)})'>Eliminar</button>`
              : `<button class="btn small" onclick='restaurarProv(${JSON.stringify(p.id_proveedor)})'>Restaurar</button>`
            }
          </td>
        `;
        tb.appendChild(tr);
      });
    }

    function filtrarEnMemoria(){
      const q = ($('#filtro').value||'').toLowerCase();
      if(!q){ renderTabla(proveedores); return; }
      const f = proveedores.filter(p =>
        (p.nombre||'').toLowerCase().includes(q) ||
        (p.ruc||'').toLowerCase().includes(q) ||
        (p.email||'').toLowerCase().includes(q)
      );
      renderTabla(f);
    }

    // ====== CRUD ======
    async function onGuardar(e){
      e.preventDefault();
      const nombre = $('#nombre').value.trim();
      const ruc = $('#ruc').value.trim();
      const telefono = $('#telefono').value.trim();
      const email = $('#email').value.trim();
      const direccion = $('#direccion').value.trim();
      const id_pais = $('#id_pais').value;
      const id_ciudad = $('#id_ciudad').value;
      const tipo = $('#tipo').value;

      if(!nombre || !ruc || !telefono || !email || !direccion || !id_pais || !id_ciudad || !tipo){
        alert('Completá todos los campos obligatorios'); return;
      }
      if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){
        alert('Email inválido'); return;
      }

      const fd = new FormData($('#formProv'));
      try{
        const r = await fetch(RUTAS.guardar, {method:'POST', body: fd});
        const j = await r.json();
        if(!j.ok){ throw new Error(j.error||'No se pudo guardar'); }
        alert(j.msg || 'Proveedor guardado');
        limpiarForm();
        listar();
      }catch(err){
        console.error(err);
        alert(err.message||'Error guardando');
      }
    }

    window.editar = async function(id){
      const p = proveedores.find(x=> x.id_proveedor == id);
      if(!p) return;
      $('#id_proveedor').value = p.id_proveedor;
      $('#nombre').value = p.nombre || '';
      $('#ruc').value = p.ruc || '';
      $('#telefono').value = p.telefono || '';
      $('#email').value = p.email || '';
      $('#direccion').value = p.direccion || '';
      $('#tipo').value = p.tipo || 'PROVEEDOR';

      if (p.id_pais){ $('#id_pais').value = p.id_pais; await cargarCiudades(p.id_pais); }
      if (p.id_ciudad){ $('#id_ciudad').value = p.id_ciudad; }

      window.scrollTo({top:0, behavior:'smooth'});
    }

    window.eliminarProv = async function(id){
      if(!confirm('¿Eliminar (soft) este proveedor?')) return;
      const fd = new FormData();
      fd.append('id_proveedor', id);
      try{
        const r = await fetch(RUTAS.eliminar, {method:'POST', body: fd});
        const j = await r.json();
        if(!j.ok) throw new Error(j.error||'No se pudo eliminar');
        alert(j.msg || 'Proveedor eliminado');
        listar();
      }catch(e){
        alert(e.message||'Error eliminando');
      }
    }

    window.restaurarProv = async function(id){
      if(!confirm('¿Restaurar este proveedor?')) return;
      const fd = new FormData();
      fd.append('id_proveedor', id);
      try{
        const r = await fetch(RUTAS.restaurar, {method:'POST', body: fd});
        const j = await r.json();
        if(!j.ok) throw new Error(j.error||'No se pudo restaurar');
        alert(j.msg || 'Proveedor restaurado');
        listar();
      }catch(e){
        alert(e.message||'Error restaurando');
      }
    }

    function limpiarForm(){
      $('#formProv').reset();
      $('#id_proveedor').value = '';
      const pid = $('#id_pais').value;
      cargarCiudades(pid);
    }

    function esc(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  </script>
</body>
</html>
