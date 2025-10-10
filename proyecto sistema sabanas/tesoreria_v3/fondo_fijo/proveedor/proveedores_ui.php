<?php
session_start();
require_once __DIR__ . '../../../../conexion/configv2.php';

if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Proveedores</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{font-family:"Segoe UI",Arial,sans-serif;--bg:#f6f7fb;--card:#fff;--line:#e5e7eb;--muted:#64748b;--primary:#2563eb;--danger:#dc2626;}
  *{box-sizing:border-box;} body{margin:0;background:var(--bg);color:#111827;}
  header{background:#fff;border-bottom:1px solid var(--line);padding:14px 24px;display:flex;gap:12px;align-items:center;}
  header h1{margin:0;font-size:20px;} main{padding:22px;max-width:1200px;margin:0 auto;}
  button{font:inherit;font-weight:600;padding:9px 14px;border-radius:8px;border:1px solid var(--line);cursor:pointer;background:#fff;}
  button.primary{background:var(--primary);border-color:var(--primary);color:#fff;}
  button.danger{background:var(--danger);border-color:var(--danger);color:#fff;}
  button[disabled]{opacity:.55;cursor:not-allowed;}
  .filters{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-bottom:16px;}
  label{font-size:13px;color:var(--muted);display:grid;gap:6px;font-weight:600;}
  input,select{font:inherit;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:#fff;}
  table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden;}
  th,td{padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px;}
  thead{background:#eef2ff;color:#1d4ed8;}
  tbody tr:hover{background:#f3f4f6;}
  .empty{padding:16px;text-align:center;color:#6b7280;}
  .modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;justify-content:center;align-items:flex-start;padding:40px 16px;z-index:40;}
  .modal-backdrop.active{display:flex;}
  .modal{background:#fff;border-radius:12px;max-width:720px;width:100%;border:1px solid var(--line);overflow:hidden;}
  .modal header{padding:14px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;}
  .modal header h2{margin:0;font-size:18px;}
  .modal header button{border:none;background:none;font-size:22px;color:#6b7280;cursor:pointer;}
  .modal .content{padding:16px;display:grid;gap:12px;}
  .grid2{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
  .error-text{color:#b91c1c;font-size:13px;}
  .toolbar{display:flex;gap:10px;align-items:center;margin-bottom:10px;}
  .badge{display:inline-flex;align-items:center;font-size:12px;border-radius:999px;padding:2px 8px;}
  .badge.activo{background:#dcfce7;color:#166534;}
  .badge.inactivo{background:#fee2e2;color:#991b1b;}
  .tag{font-size:12px;background:#eef2ff;color:#1e3aa8;border-radius:999px;padding:2px 8px;}
</style>
</head>
<body>
<header>
  <h1>Proveedores</h1>
  <div style="margin-left:auto" class="toolbar">
    <button id="btn-new" class="primary">Nuevo proveedor</button>
    <button id="btn-refresh">Actualizar</button>
  </div>
</header>

<main>
  <section class="filters" id="filters">
    <label>Buscar
      <input type="text" name="q" placeholder="Nombre, RUC o email">
    </label>
    <label>Tipo
      <select name="tipo">
        <option value="">Todos</option>
        <option value="PROVEEDOR">PROVEEDOR</option>
        <option value="FONDO_FIJO">FONDO_FIJO</option>
        <option value="SERVICIO">SERVICIO</option>
        <option value="TRANSPORTISTA">TRANSPORTISTA</option>
        <option value="OTRO">OTRO</option>
      </select>
    </label>
    <label>Estado
      <select name="estado">
        <option value="">Todos</option>
        <option value="Activo">Activo</option>
        <option value="Inactivo">Inactivo</option>
      </select>
    </label>
    <label>
      <span>Incluir borrados</span>
      <select name="include_deleted">
        <option value="0" selected>No</option>
        <option value="1">Sí</option>
      </select>
    </label>
    <div style="display:flex;gap:8px;align-items:center;">
      <button class="primary" id="btn-search">Aplicar</button>
      <button id="btn-clear">Limpiar</button>
    </div>
  </section>

  <section>
    <table id="tbl">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>RUC</th>
          <th>Email</th>
          <th>Teléfono</th>
          <th>Tipo</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div id="empty" class="empty" style="display:none;">Sin resultados.</div>
  </section>
</main>

<!-- Modal form -->
<div class="modal-backdrop" id="form-modal">
  <div class="modal">
    <form id="form-prov">
      <header>
        <h2 id="form-title">Nuevo proveedor</h2>
        <button type="button" id="form-close">&times;</button>
      </header>
      <div class="content">
        <input type="hidden" name="id_proveedor">
        <div class="grid2">
          <label>Nombre*<input type="text" name="nombre" required maxlength="255"></label>
          <label>RUC*<input type="text" name="ruc" required maxlength="15"></label>
          <label>Dirección*<input type="text" name="direccion" required maxlength="255"></label>
          <label>Teléfono*<input type="text" name="telefono" required maxlength="25"></label>
          <label>Email*<input type="email" name="email" required maxlength="100"></label>
          <label>Tipo*
            <select name="tipo" required>
              <option value="PROVEEDOR">PROVEEDOR</option>
              <option value="FONDO_FIJO">FONDO_FIJO</option>
              <option value="SERVICIO">SERVICIO</option>
              <option value="TRANSPORTISTA">TRANSPORTISTA</option>
              <option value="OTRO">OTRO</option>
            </select>
          </label>
          <label>Estado*
            <select name="estado" required>
              <option value="Activo">Activo</option>
              <option value="Inactivo">Inactivo</option>
            </select>
          </label>
          <label>País (opcional)<input type="number" name="id_pais" min="1" step="1"></label>
          <label>Ciudad (opcional)<input type="number" name="id_ciudad" min="1" step="1"></label>
        </div>
        <div id="form-error" class="error-text"></div>
      </div>
      <footer style="padding:12px 16px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--line);">
        <button type="button" id="form-cancel">Cancelar</button>
        <button type="submit" class="primary" id="form-save">Guardar</button>
      </footer>
    </form>
  </div>
</div>

<script>
const apiUrl = '../proveedor/proveedores_api.php';

const tbody = document.querySelector('#tbl tbody');
const emptyState = document.getElementById('empty');

const formModal = document.getElementById('form-modal');
const form = document.getElementById('form-prov');
const formTitle = document.getElementById('form-title');
const formError = document.getElementById('form-error');

function escapeHtml(str){return (str??'').toString().replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}

function badgeEstado(val){
  const cls = (val==='Activo'?'activo':'inactivo');
  return `<span class="badge ${cls}">${escapeHtml(val)}</span>`;
}
function render(rows){
  tbody.innerHTML='';
  if(!rows.length){emptyState.style.display='block';return;}
  emptyState.style.display='none';
  rows.forEach(r=>{
    const tr=document.createElement('tr');
    const deleted = !!r.deleted_at;
    tr.innerHTML = `
      <td>${r.id_proveedor}</td>
      <td>${escapeHtml(r.nombre)}</td>
      <td>${escapeHtml(r.ruc)}</td>
      <td>${escapeHtml(r.email)}</td>
      <td>${escapeHtml(r.telefono)}</td>
      <td><span class="tag">${escapeHtml(r.tipo)}</span></td>
      <td>${badgeEstado(r.estado)} ${deleted?'<small class="tag" style="background:#fde68a;color:#92400e">Borrado</small>':''}</td>
      <td style="display:flex;gap:6px;flex-wrap:wrap;">
        <button data-action="edit" data-id="${r.id_proveedor}">Editar</button>
        <button data-action="del" data-id="${r.id_proveedor}" class="danger" ${deleted?'disabled':''}>Eliminar</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

async function loadData(){
  const p = new URLSearchParams();
  document.querySelectorAll('#filters input, #filters select').forEach(el=>{
    if(el.name && el.value.trim()!=='') p.set(el.name, el.value.trim());
  });
  try{
    const res = await fetch(`${apiUrl}?${p.toString()}`, {credentials:'same-origin'});
    const j = await res.json();
    if(!j.ok) throw new Error(j.error||'Error al listar');
    render(j.data||[]);
  }catch(e){ alert(e.message); }
}

document.getElementById('btn-refresh').addEventListener('click', loadData);
document.getElementById('btn-search').addEventListener('click', loadData);
document.getElementById('btn-clear').addEventListener('click', ()=>{
  document.querySelectorAll('#filters input, #filters select').forEach(el=>el.value='');
  document.querySelector('#filters [name="include_deleted"]').value = '0';
  loadData();
});

document.getElementById('btn-new').addEventListener('click', ()=>{
  form.reset();
  form.id_proveedor.value='';
  formTitle.textContent='Nuevo proveedor';
  formError.textContent='';
  formModal.classList.add('active');
});

function closeForm(){ formModal.classList.remove('active'); }
document.getElementById('form-close').addEventListener('click', closeForm);
document.getElementById('form-cancel').addEventListener('click', closeForm);
formModal.addEventListener('click', e=>{ if(e.target===formModal) closeForm(); });
formModal.querySelector('.modal').addEventListener('click', e=>e.stopPropagation());

tbody.addEventListener('click', async e=>{
  const btn = e.target.closest('button[data-action]');
  if(!btn) return;
  const id = Number(btn.dataset.id);
  const action = btn.dataset.action;

  if(action==='edit'){
    // cargar proveedor
    try{
      const res = await fetch(`${apiUrl}?id=${id}`, {credentials:'same-origin'});
      const j = await res.json();
      if(!j.ok) throw new Error(j.error||'No se pudo obtener proveedor');
      const p = j.proveedor;
      form.reset();
      form.id_proveedor.value = p.id_proveedor;
      form.nombre.value = p.nombre || '';
      form.ruc.value = p.ruc || '';
      form.direccion.value = p.direccion || '';
      form.telefono.value = p.telefono || '';
      form.email.value = p.email || '';
      form.tipo.value = p.tipo || 'PROVEEDOR';
      form.estado.value = p.estado || 'Activo';
      form.id_pais.value = p.id_pais || '';
      form.id_ciudad.value = p.id_ciudad || '';
      formTitle.textContent = `Editar proveedor #${p.id_proveedor}`;
      formError.textContent='';
      formModal.classList.add('active');
    }catch(err){ alert(err.message); }
  }

  if(action==='del'){
    if(!confirm('¿Eliminar (baja lógica) este proveedor?')) return;
    try{
      const res = await fetch(`${apiUrl}?id=${id}`, {method:'DELETE', credentials:'same-origin'});
      const j = await res.json();
      if(!j.ok) throw new Error(j.error||'No se pudo eliminar');
      await loadData();
    }catch(err){ alert(err.message); }
  }
});

form.addEventListener('submit', async e=>{
  e.preventDefault();
  formError.textContent='';
  const payload = {
    nombre: form.nombre.value.trim(),
    ruc: form.ruc.value.trim(),
    direccion: form.direccion.value.trim(),
    telefono: form.telefono.value.trim(),
    email: form.email.value.trim(),
    tipo: form.tipo.value,
    estado: form.estado.value,
    id_pais: form.id_pais.value ? Number(form.id_pais.value) : null,
    id_ciudad: form.id_ciudad.value ? Number(form.id_ciudad.value) : null
  };
  const isEdit = !!form.id_proveedor.value;

  try{
    let res,j;
    if(isEdit){
      res = await fetch(`${apiUrl}?id=${form.id_proveedor.value}`, {
        method:'PATCH',
        headers:{'Content-Type':'application/json'},
        credentials:'same-origin',
        body:JSON.stringify(payload)
      });
      j = await res.json();
    }else{
      res = await fetch(apiUrl, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'same-origin',
        body:JSON.stringify(payload)
      });
      j = await res.json();
    }
    if(!j.ok) throw new Error(j.error||'Error al guardar');
    closeForm();
    await loadData();
  }catch(err){
    formError.textContent = err.message;
  }
});

// init
loadData();
</script>
</body>
</html>
