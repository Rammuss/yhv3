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
<title>Profesionales</title>
<link rel="stylesheet" type="text/css" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/css/styles_servicios.css" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{margin:0;padding:24px;font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto;background:#f8fafc;color:#0f172a;}
  h1{margin:0 0 16px;}
  .layout{display:grid;grid-template-columns:minmax(320px,2.2fr) minmax(280px,1fr);gap:16px;}
  .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(15,23,42,.08);padding:18px;}
  table{width:100%;border-collapse:collapse;}
  th,td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:13px;text-align:left;}
  th{background:#eff6ff;text-transform:uppercase;font-size:12px;color:#1e3a8a;}
  .muted{color:#64748b;font-size:12px;}
  label{display:block;margin:12px 0 4px;text-transform:uppercase;font-size:11px;color:#475569;letter-spacing:.05em;}
  input,select,button{width:100%;padding:9px 12px;border:1px solid #cbd5f5;border-radius:10px;font-size:14px;}
  button{cursor:pointer;background:#2563eb;color:#fff;border:none;font-weight:600;}
  button.sec{background:#e2e8f0;color:#0f172a;}
  button.danger{background:#dc2626;color:#fff;}
  button:disabled{opacity:.6;cursor:not-allowed;}
  .actions{display:flex;gap:8px;}
  .badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;}
  .activo{background:#dcfce7;color:#166534;}
  .inactivo{background:#fee2e2;color:#b91c1c;}
  #toast{position:fixed;top:20px;right:20px;min-width:200px;padding:10px 14px;border-radius:10px;color:#fff;background:#2563eb;box-shadow:0 10px 24px rgba(15,23,42,.25);display:none;}
</style>
</head>
<body>
    <div id="navbar-container"></div>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/navbar/navbar.js"></script>
  <h1>Gestión de profesionales</h1>
  <div class="layout">
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;">
        <div>
          <label>Filtrar por estado</label>
          <select id="f_estado">
            <option>Todos</option>
            <option>Activo</option>
            <option>Inactivo</option>
          </select>
        </div>
        <div style="flex:0 0 auto;margin-top:22px;">
          <button class="sec" onclick="loadProfesionales()">Actualizar</button>
        </div>
      </div>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Estado</th>
            <th style="width:120px;"></th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
      <div id="empty" class="muted" style="margin-top:12px;display:none;">Sin profesionales.</div>
    </div>

    <div class="card">
      <h2 id="form_title" style="margin:0 0 10px;font-size:17px;">Nuevo profesional</h2>
      <form id="formPro" autocomplete="off">
        <input type="hidden" id="id_profesional">
        <label>Nombre</label>
        <input id="nombre" required placeholder="Nombre completo">
        <label>Teléfono</label>
        <input id="telefono" placeholder="(0981) 000-000">
        <label>Email</label>
        <input id="email" type="email" placeholder="correo@dominio.com">
        <label>Estado</label>
        <select id="estado">
          <option value="Activo">Activo</option>
          <option value="Inactivo">Inactivo</option>
        </select>
        <div class="actions" style="margin-top:16px;">
          <button type="submit" id="btnGuardar">Guardar</button>
          <button type="button" class="sec" onclick="resetForm()">Cancelar</button>
        </div>
      </form>
      <div class="muted" style="margin-top:10px;">Seleccioná un profesional de la tabla para editarlo.</div>
    </div>
  </div>

  <div id="toast"></div>

<script>
const API = '../profesional/api_profesional.php';
const $ = (id)=>document.getElementById(id);

let profesionales = [];
let editingId = null;

function showToast(msg,type='ok'){
  const toast = $('toast');
  toast.style.background = type==='error' ? '#dc2626' : '#2563eb';
  toast.textContent = msg;
  toast.style.display = 'block';
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(()=> toast.style.display='none', 2600);
}

async function api(op, payload = {}){
  const res = await fetch(API,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({op, ...payload})
  });
  const data = await res.json();
  if(!data.success) throw new Error(data.error || 'Error');
  return data;
}

async function loadProfesionales(){
  try{
    const estado = $('f_estado').value;
    const r = await api('list',{estado});
    profesionales = r.rows || [];
    renderTable();
  }catch(e){
    showToast(e.message,'error');
  }
}
function renderTable(){
  const tbody = $('tbody');
  tbody.innerHTML = '';
  if(!profesionales.length){
    $('empty').style.display='block';
    return;
  }
  $('empty').style.display='none';
  profesionales.forEach(p=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${p.id_profesional}</td>
      <td>${p.nombre}</td>
      <td>${p.telefono||'-'}</td>
      <td>${p.email||'-'}</td>
      <td><span class="badge ${p.estado==='Activo'?'activo':'inactivo'}">${p.estado}</span></td>
      <td style="display:flex;gap:6px;">
        <button class="sec" style="padding:6px 8px;font-size:12px;" onclick="editar(${p.id_profesional})">Editar</button>
        <button class="sec" style="padding:6px 8px;font-size:12px;" onclick="toggleEstado(${p.id_profesional})">${p.estado==='Activo'?'Inactivar':'Activar'}</button>
        <button class="danger" style="padding:6px 8px;font-size:12px;" onclick="borrar(${p.id_profesional})">×</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}
async function editar(id){
  try{
    const r = await api('get',{id_profesional:id});
    const p = r.row;
    editingId = p.id_profesional;
    $('form_title').textContent = 'Editar profesional';
    $('id_profesional').value = p.id_profesional;
    $('nombre').value = p.nombre || '';
    $('telefono').value = p.telefono || '';
    $('email').value = p.email || '';
    $('estado').value = p.estado || 'Activo';
  }catch(e){
    showToast(e.message,'error');
  }
}
function resetForm(){
  editingId = null;
  $('form_title').textContent = 'Nuevo profesional';
  $('id_profesional').value='';
  $('nombre').value='';
  $('telefono').value='';
  $('email').value='';
  $('estado').value='Activo';
}

$('formPro').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const payload = {
    nombre: $('nombre').value.trim(),
    telefono: $('telefono').value.trim() || null,
    email: $('email').value.trim() || null,
    estado: $('estado').value
  };
  if(payload.nombre===''){
    showToast('Nombre requerido','error');
    return;
  }
  try{
    $('btnGuardar').disabled = true;
    if(editingId){
      await api('update',{id_profesional:editingId, ...payload});
      showToast('Profesional actualizado');
    }else{
      await api('create',payload);
      showToast('Profesional creado');
    }
    resetForm();
    await loadProfesionales();
  }catch(err){
    showToast(err.message,'error');
  }finally{
    $('btnGuardar').disabled = false;
  }
});

async function borrar(id){
  if(!confirm('¿Eliminar profesional?')) return;
  try{
    await api('delete',{id_profesional:id});
    showToast('Profesional eliminado');
    if(editingId===id) resetForm();
    await loadProfesionales();
  }catch(e){
    showToast(e.message,'error');
  }
}
async function toggleEstado(id){
  try{
    const r = await api('toggle',{id_profesional:id});
    showToast('Estado actualizado a '+r.estado);
    await loadProfesionales();
  }catch(e){
    showToast(e.message,'error');
  }
}

window.addEventListener('DOMContentLoaded', loadProfesionales);
</script>
</body>
</html>
