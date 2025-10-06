<?php
/**
 * timbrado_admin.php — UI con tarjetas
 * Administra:
 *   - TIMBRADOS (CRUD + validaciones mínimas)
 *   - ASIGNACIONES (crear/editar/activar-desactivar)
 *
 * Tablas:
 *   public.timbrado
 *   public.timbrado_asignacion
 *   public.caja
 *
 * Requisitos:
 *   - Conexión PG en $conn: ../../conexion/configv2.php
 */

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header_remove('X-Powered-By');

function json_out($data, $code=200){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function is_post(){ return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function body_json(){ $raw=file_get_contents('php://input'); return $raw? (json_decode($raw,true) ?: []) : []; }

$action = $_GET['action'] ?? $_POST['action'] ?? null;

/* ============================ API: TIMBRADO ============================ */
if ($action === 'timbrado.list') {
  $q = $_GET['q'] ?? null; // buscar por numero_timbrado o PPP
  $estado = (isset($_GET['estado']) && $_GET['estado'] !== '') ? $_GET['estado'] : null;
  $limit = (int)($_GET['limit'] ?? 300);
  $offset = (int)($_GET['offset'] ?? 0);

  $sql = "SELECT id_timbrado, numero_timbrado, tipo_comprobante, tipo_documento,
                 establecimiento, punto_expedicion, nro_desde, nro_hasta, nro_actual,
                 fecha_inicio, fecha_fin, estado
          FROM public.timbrado
          WHERE ($1::text IS NULL
                  OR numero_timbrado ILIKE '%'||$1||'%'
                  OR (establecimiento||'-'||punto_expedicion) ILIKE '%'||$1||'%')
            AND (NULLIF($2::text,'') IS NULL OR estado = $2)
          ORDER BY id_timbrado DESC
          LIMIT $3 OFFSET $4";
  $res = pg_query_params($conn, $sql, [$q, $estado, $limit, $offset]);
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  json_out(['ok'=>true, 'items'=>pg_fetch_all($res) ?: []]);
}

if ($action === 'timbrado.save' && is_post()) {
  $in = body_json();
  $id    = $in['id_timbrado'] ?? null;
  $num   = trim($in['numero_timbrado'] ?? '');
  $tcomp = trim($in['tipo_comprobante'] ?? '');
  $tdoc  = trim($in['tipo_documento'] ?? '');
  $estab = trim($in['establecimiento'] ?? '');
  $pexp  = trim($in['punto_expedicion'] ?? '');
  $desde = (int)($in['nro_desde'] ?? 0);
  $hasta = (int)($in['nro_hasta'] ?? 0);
  $actual= (int)($in['nro_actual'] ?? 0);
  $fi    = $in['fecha_inicio'] ?? null;
  $ff    = $in['fecha_fin'] ?? null;
  $estado= $in['estado'] ?? 'Vigente';

  if ($num==='' || $tcomp==='' || $tdoc==='' || $estab==='' || $pexp==='' || $desde<1 || $hasta<$desde || !$fi || !$ff) {
    json_out(['ok'=>false, 'error'=>'Datos incompletos o inválidos. Verificá los campos.'], 400);
  }
  if ($actual < 0 || $actual > $hasta) json_out(['ok'=>false, 'error'=>'nro_actual fuera de rango.'], 400);

  $hoy_res = pg_query($conn, "SELECT current_date::date AS hoy");
  $hoy = pg_fetch_assoc($hoy_res)['hoy'];
  if ($ff < $hoy) $estado = 'Vencido';

  if ($id) {
    $sql = "UPDATE public.timbrado
            SET numero_timbrado=$2, tipo_comprobante=$3, tipo_documento=$4,
                establecimiento=$5, punto_expedicion=$6, nro_desde=$7, nro_hasta=$8,
                nro_actual=$9, fecha_inicio=$10, fecha_fin=$11, estado=$12
            WHERE id_timbrado=$1
            RETURNING id_timbrado";
    $res = pg_query_params($conn, $sql, [$id,$num,$tcomp,$tdoc,$estab,$pexp,$desde,$hasta,$actual,$fi,$ff,$estado]);
  } else {
    $sql = "INSERT INTO public.timbrado
              (numero_timbrado, tipo_comprobante, tipo_documento, establecimiento, punto_expedicion,
               nro_desde, nro_hasta, nro_actual, fecha_inicio, fecha_fin, estado)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11)
            RETURNING id_timbrado";
    $res = pg_query_params($conn, $sql, [$num,$tcomp,$tdoc,$estab,$pexp,$desde,$hasta,$actual,$fi,$ff,$estado]);
  }
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  $row = pg_fetch_assoc($res);
  json_out(['ok'=>true, 'id_timbrado'=>$row['id_timbrado'] ?? null, 'Estado'=>$estado]);
}

if ($action === 'timbrado.toggle' && is_post()) {
  $in = body_json();
  $id = $in['id_timbrado'] ?? null;
  $estado = $in['estado'] ?? null;
  if (!$id || !$estado) json_out(['ok'=>false, 'error'=>'Parámetros inválidos.'], 400);

  $q = pg_query_params($conn, "SELECT fecha_fin FROM public.timbrado WHERE id_timbrado=$1", [$id]);
  if (!$q) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  $ff = pg_fetch_assoc($q)['fecha_fin'] ?? null;
  $hoy_res = pg_query($conn, "SELECT current_date::date AS hoy");
  $hoy = pg_fetch_assoc($hoy_res)['hoy'];
  if ($ff && $ff < $hoy) $estado = 'Vencido';

  $res = pg_query_params($conn, "UPDATE public.timbrado SET estado=$2 WHERE id_timbrado=$1", [$id, $estado]);
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  json_out(['ok'=>true, 'estado'=>$estado]);
}

/* ===== Enums dinámicos para selects (tipos, docs, números) ===== */
if ($action === 'timbrado.enums') {
  $q1 = pg_query($conn, "SELECT DISTINCT tipo_comprobante FROM public.timbrado WHERE tipo_comprobante <> '' ORDER BY 1 ASC");
  $q2 = pg_query($conn, "SELECT DISTINCT tipo_documento  FROM public.timbrado WHERE tipo_documento  <> '' ORDER BY 1 ASC");
  $q3 = pg_query($conn, "SELECT DISTINCT numero_timbrado  FROM public.timbrado WHERE numero_timbrado <> '' ORDER BY 1 DESC");
  if (!$q1 || !$q2 || !$q3) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);

  $tipos = array_map(fn($r)=>$r['tipo_comprobante'], pg_fetch_all($q1) ?: []);
  $docs  = array_map(fn($r)=>$r['tipo_documento'],  pg_fetch_all($q2) ?: []);
  $nums  = array_map(fn($r)=>$r['numero_timbrado'], pg_fetch_all($q3) ?: []);
  json_out(['ok'=>true, 'tipos'=>$tipos, 'docs'=>$docs, 'numeros'=>$nums]);
}

/* ============================ API: ASIGNACIÓN =========================== */
if ($action === 'asig.list') {
  $id_caja = isset($_GET['id_caja']) && $_GET['id_caja']!=='' ? (int)$_GET['id_caja'] : null;
  $id_tim = isset($_GET['id_timbrado']) && $_GET['id_timbrado']!=='' ? (int)$_GET['id_timbrado'] : null;
  $estado = (isset($_GET['estado']) && $_GET['estado'] !== '') ? $_GET['estado'] : null;

  $sql = "SELECT a.id_asignacion, a.id_caja, a.id_timbrado, a.estado,
                 c.nombre AS caja_nombre,
                 t.numero_timbrado, t.tipo_comprobante, t.tipo_documento,
                 t.establecimiento, t.punto_expedicion,
                 t.nro_desde, t.nro_hasta, t.nro_actual,
                 t.fecha_inicio, t.fecha_fin, t.estado AS estado_timbrado
          FROM public.timbrado_asignacion a
          JOIN public.caja c ON c.id_caja = a.id_caja
          JOIN public.timbrado t ON t.id_timbrado = a.id_timbrado
          WHERE ($1::int IS NULL OR a.id_caja = $1)
            AND ($2::int IS NULL OR a.id_timbrado = $2)
            AND (NULLIF($3::text,'') IS NULL OR a.estado = $3)
          ORDER BY a.id_asignacion DESC";
  $res = pg_query_params($conn, $sql, [$id_caja, $id_tim, $estado]);
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  json_out(['ok'=>true, 'items'=>pg_fetch_all($res) ?: []]);
}

if ($action === 'asig.save' && is_post()) {
  $in = body_json();
  $id = $in['id_asignacion'] ?? null;
  $id_caja = $in['id_caja'] ?? null;
  $id_tim = $in['id_timbrado'] ?? null;
  $estado = $in['estado'] ?? 'Vigente';

  if (!$id_caja || !$id_tim) json_out(['ok'=>false, 'error'=>'Caja y Timbrado son obligatorios.'], 400);

  if ($id) {
    $sql = "UPDATE public.timbrado_asignacion
            SET id_caja=$2, id_timbrado=$3, estado=$4
            WHERE id_asignacion=$1
            RETURNING id_asignacion";
    $res = pg_query_params($conn, $sql, [$id, $id_caja, $id_tim, $estado]);
  } else {
    $sql = "INSERT INTO public.timbrado_asignacion (id_timbrado, id_caja, estado)
            VALUES ($1,$2,$3)
            RETURNING id_asignacion";
    $res = pg_query_params($conn, $sql, [$id_tim, $id_caja, $estado]);

    if (!$res && strpos(pg_last_error($conn), 'ux_asignacion_caja_ppp_vig') !== false && strtolower($estado)==='vigente') {
      pg_query_params($conn, "UPDATE public.timbrado_asignacion
                              SET estado='Inactivo'
                              WHERE id_timbrado=$1 AND id_caja=$2 AND lower(estado)='vigente'",
                              [$id_tim, $id_caja]);
      $res = pg_query_params($conn, $sql, [$id_tim, $id_caja, $estado]);
    }
  }

  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  $row = pg_fetch_assoc($res);
  json_out(['ok'=>true, 'id_asignacion'=>$row['id_asignacion'] ?? null]);
}

if ($action === 'asig.toggle' && is_post()) {
  $in = body_json();
  $id = $in['id_asignacion'] ?? null;
  $estado = $in['estado'] ?? null;
  if (!$id || !$estado) json_out(['ok'=>false, 'error'=>'Parámetros inválidos'], 400);

  if (strtolower($estado) === 'vigente') {
    $q = pg_query_params($conn, "SELECT id_timbrado, id_caja FROM public.timbrado_asignacion WHERE id_asignacion=$1", [$id]);
    if (!$q) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
    $row = pg_fetch_assoc($q);
    if ($row) {
      pg_query_params($conn, "UPDATE public.timbrado_asignacion
                              SET estado='Inactivo'
                              WHERE id_timbrado=$1 AND id_caja=$2 AND lower(estado)='vigente' AND id_asignacion<>$3",
                              [$row['id_timbrado'], $row['id_caja'], $id]);
    }
  }

  $res = pg_query_params($conn, "UPDATE public.timbrado_asignacion SET estado=$2 WHERE id_asignacion=$1", [$id, $estado]);
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  json_out(['ok'=>true]);
}

if ($action === 'combo.cajas') {
  $res = pg_query($conn, "SELECT id_caja, nombre FROM public.caja ORDER BY nombre ASC");
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  json_out(['ok'=>true, 'items'=>pg_fetch_all($res) ?: []]);
}

if ($action === 'combo.timbrados') {
  $todos = ($_GET['todos'] ?? '') === 'true';
  if ($todos) {
    $res = pg_query($conn, "SELECT id_timbrado, numero_timbrado, establecimiento, punto_expedicion, tipo_comprobante, tipo_documento, estado FROM public.timbrado ORDER BY id_timbrado DESC");
  } else {
    $res = pg_query($conn, "SELECT id_timbrado, numero_timbrado, establecimiento, punto_expedicion, tipo_comprobante, tipo_documento, estado FROM public.timbrado WHERE estado='Vigente' ORDER BY id_timbrado DESC");
  }
  if (!$res) json_out(['ok'=>false, 'error'=>pg_last_error($conn)], 500);
  json_out(['ok'=>true, 'items'=>pg_fetch_all($res) ?: []]);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Timbrados & Asignaciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>:root {
  --bg: #f6f7fb;
  --card: #fff;
  --line: #ececf2;
  --muted: #6b7280;
  --primary: #2563eb;
  --danger: #e11d48;
  --ok: #10b981;
  --chip: #eef2ff;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: Inter, system-ui, Segoe UI, Arial;
  background: var(--bg);
  color: #111;
}

header.top {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 14px 16px;
  background: #fff;
  border-bottom: 1px solid var(--line);
}

h1 {
  font-size: 18px;
  margin: 0;
}

main {
  max-width: 1200px;
  margin: 0 auto;
  padding: 18px;
}

/* Toolbar */
.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin: 0 0 14px 0;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--chip);
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: 6px 10px;
}

.chip select,
.chip input {
  border: none;
  background: transparent;
  outline: none;
  font: inherit;
}

.chip input[type="text"] {
  min-width: 200px;
}

.btn {
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
}

.btn.primary {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}

.btn.icon {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 6px;
  border-bottom: 1px solid var(--line);
  margin: 0 0 14px 0;
}

.tab {
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-bottom: none;
  background: #fafafa;
  border-top-left-radius: 8px;
  border-top-right-radius: 8px;
  cursor: pointer;
}

.tab.active {
  background: #fff;
  font-weight: 600;
}

/* Cards */
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 12px;
}

.card {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
}

.card .top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.title {
  font-weight: 700;
}

.muted {
  color: var(--muted);
  font-size: 13px;
}

.row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  font-size: 14px;
}

.kv {
  display: inline-flex;
  gap: 6px;
  align-items: center;
  background: #f9fafb;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 6px 8px;
}

.actions {
  display: flex;
  gap: 8px;
  margin-top: 6px;
}

.badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  border: 1px solid var(--line);
  background: #fff;
}

.ok {
  background: #ecfdf5;
  color: #065f46;
  border-color: #a7f3d0;
}

.off {
  background: #fff1f2;
  color: #9f1239;
  border-color: #fecdd3;
}

/* Modal */
.modal {
  position: fixed;
  inset: 0;
  display: none;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, .38);
  z-index: 30;
  padding: 16px;
}

.modal .card {
  max-width: 760px;
  width: 100%;
  box-shadow: 0 15px 50px rgba(0, 0, 0, .2);
}

.modal .card header {
  padding-bottom: 10px;
  border-bottom: 1px solid var(--line);
  font-weight: 600;
}

.content {
  display: grid;
  gap: 10px;
  margin-top: 10px;
}

.grid2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

label.l {
  display: grid;
  gap: 6px;
  font-size: 13px;
}

input[type="text"],
input[type="date"],
input[type="number"],
select {
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: #fff;
}

/* Small helpers */
.spacer { flex: 1; }

</style>
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">

</head>
<body>
  <div id="navbar-container"></div>
  <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>


<header class="top">
  <h1>Timbrados & Asignaciones</h1>
  <div class="spacer"></div>
  <button class="btn icon" onclick="refreshActive()">⟲ Refrescar</button>
  <button class="btn primary icon" onclick="openTimbradoForm()">＋ Nuevo Timbrado</button>
  <button class="btn icon" onclick="openAsigForm()">＋ Nueva Asignación</button>
</header>

<main>
  <div class="tabs">
    <div id="tabTim" class="tab active" onclick="showTab('tim')">Timbrados</div>
    <div id="tabAsig" class="tab" onclick="showTab('asig')">Asignaciones</div>
  </div>

  <!-- TIMBRADOS -->
  <section id="viewTim">
    <div class="toolbar">
      <div class="chip">🔎
        <input id="tim_q" type="text" placeholder="Buscar Nº o PPP..." />
      </div>
      <div class="chip">Estado:
        <select id="tim_estado">
          <option value="">Todos</option>
          <option>Vigente</option>
          <option>Inactivo</option>
          <option>Vencido</option>
        </select>
      </div>
      <button class="btn" onclick="loadTimbrados()">Aplicar</button>
    </div>
    <div id="cardsTim" class="cards"></div>
  </section>

  <!-- ASIGNACIONES -->
  <section id="viewAsig" style="display:none">
    <div class="toolbar">
      <div class="chip">Caja:
        <select id="asig_caja"><option value="">Todas</option></select>
      </div>
      <div class="chip">Timbrado:
        <select id="asig_tim"><option value="">Vigentes</option></select>
      </div>
      <div class="chip">Estado:
        <select id="asig_estado">
          <option value="">Todos</option>
          <option>Vigente</option>
          <option>Inactivo</option>
          <option>Vencido</option>
        </select>
      </div>
      <button class="btn" onclick="loadAsignaciones()">Aplicar</button>
    </div>
    <div id="cardsAsig" class="cards"></div>
  </section>
</main>

<!-- MODAL TIMBRADO -->
<div class="modal" id="modalTim">
  <div class="card" role="dialog" aria-modal="true" aria-labelledby="timTitle">
    <header id="timTitle">Nuevo Timbrado</header>
    <div class="content">
      <input type="hidden" id="tim_id">
      <div class="grid2">
        <label class="l">Nº Timbrado
          <select id="tim_num_sel"></select>
        </label>
        <label class="l">Estado
          <select id="tim_estado_form">
            <option>Vigente</option>
            <option>Inactivo</option>
            <option>Vencido</option>
          </select>
        </label>
      </div>
      <label class="l" id="wrap_tim_num_new" style="display:none">Nº Timbrado (nuevo)
        <input id="tim_num_new" type="text" placeholder="12345678" />
      </label>

      <div class="grid2">
        <label class="l">Tipo Comprobante
          <select id="tim_tcomp_sel"></select>
        </label>
        <label class="l">Tipo Documento
          <select id="tim_tdoc_sel"></select>
        </label>
      </div>

      <div class="grid2">
        <label class="l">Establecimiento
          <input id="tim_est" type="text" maxlength="3" placeholder="001" />
        </label>
        <label class="l">Punto de Expedición
          <input id="tim_pexp" type="text" maxlength="3" placeholder="001" />
        </label>
      </div>
      <div class="grid2">
        <label class="l">Desde
          <input id="tim_desde" type="number" min="1" value="1" />
        </label>
        <label class="l">Hasta
          <input id="tim_hasta" type="number" min="1" value="9999999" />
        </label>
      </div>
      <label class="l">Número actual
        <input id="tim_actual" type="number" min="0" value="0" />
      </label>
      <div class="grid2">
        <label class="l">Fecha inicio
          <input id="tim_fi" type="date" />
        </label>
        <label class="l">Fecha fin
          <input id="tim_ff" type="date" />
        </label>
      </div>
    </div>
    <div class="actions">
      <button class="btn" id="btnCancelTim">Cancelar</button>
      <button class="btn primary" onclick="saveTim()">Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL ASIGNACIÓN -->
<div class="modal" id="modalAsig">
  <div class="card" role="dialog" aria-modal="true" aria-labelledby="asigTitle">
    <header id="asigTitle">Nueva Asignación</header>
    <div class="content">
      <input type="hidden" id="asig_id">
      <div class="grid2">
        <label class="l">Caja
          <select id="asig_caja_form"></select>
        </label>
        <label class="l">Timbrado
          <select id="asig_tim_form"></select>
        </label>
      </div>
      <label class="l">Estado
        <select id="asig_estado_form">
          <option>Vigente</option>
          <option>Inactivo</option>
          <option>Vencido</option>
        </select>
      </label>
    </div>
    <div class="actions">
      <button class="btn" id="btnCancelAsig">Cancelar</button>
      <button class="btn primary" onclick="saveAsig()">Guardar</button>
    </div>
  </div>
</div>

<script>
/* ==================== ENUMS / HELPERS ==================== */
let ENUMS = { tipos:[], docs:[], numeros:[] };
const OPT_NUEVO = '__nuevo__';

async function loadEnums(){
  const res = await fetch('?action=timbrado.enums');
  const j = await res.json();
  if(!j.ok){ console.error(j.error); return; }
  ENUMS = j;
}
function fillSelect(sel, arr, placeholder){
  sel.innerHTML = '';
  if (placeholder) {
    const ph = document.createElement('option');
    ph.value = ''; ph.textContent = placeholder; ph.disabled = true; ph.selected = true;
    sel.appendChild(ph);
  }
  (arr||[]).forEach(v=>{
    const o = document.createElement('option');
    o.value = v; o.textContent = v;
    sel.appendChild(o);
  });
}
function ensureOption(sel, value){
  if (!value) return;
  const exists = Array.from(sel.options).some(o=>o.value===value);
  if (!exists){
    const o = document.createElement('option');
    o.value = value; o.textContent = value; sel.appendChild(o);
  }
}
function stateBadgeHTML(s){
  if(s==='Vigente') return '<span class="badge ok">Vigente</span>';
  if(s==='Vencido') return '<span class="badge off">Vencido</span>';
  return '<span class="badge">Inactivo</span>';
}
function esc(s){
  return (s??'').toString().replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]||m));
}
function asAttr(r){
  return JSON.stringify(r).replace(/</g,'\\u003c').replace(/>/g,'\\u003e').replace(/&/g,'\\u0026').replace(/'/g,'\\u0027');
}

/* ==================== TABS ==================== */
function showTab(which){
  document.getElementById('tabTim').classList.toggle('active', which==='tim');
  document.getElementById('tabAsig').classList.toggle('active', which==='asig');
  document.getElementById('viewTim').style.display = which==='tim' ? '' : 'none';
  document.getElementById('viewAsig').style.display = which==='asig' ? '' : 'none';
}
function refreshActive(){
  if (document.getElementById('viewTim').style.display !== 'none') loadTimbrados();
  else loadAsignaciones();
}

/* ==================== TIMBRADOS ==================== */
async function loadTimbrados(){
  const q = document.getElementById('tim_q').value.trim();
  const estado = document.getElementById('tim_estado').value;
  const url = `?action=timbrado.list&q=${encodeURIComponent(q)}&estado=${encodeURIComponent(estado)}&limit=300&offset=0`;
  const res = await fetch(url);
  const j = await res.json();
  const wrap = document.getElementById('cardsTim'); wrap.innerHTML='';

  (j.items||[]).forEach(r=>{
    const div = document.createElement('div'); div.className='card';
    div.innerHTML = `
      <div class="top">
        <div class="title">#${r.id_timbrado} · ${esc(r.numero_timbrado)}</div>
        <div>${stateBadgeHTML(r.estado)}</div>
      </div>
      <div class="row">
        <span class="kv">PPP ${esc(r.establecimiento)}-${esc(r.punto_expedicion)}</span>
        <span class="kv">${esc(r.tipo_comprobante)} / ${esc(r.tipo_documento)}</span>
      </div>
      <div class="row">
        <span class="kv">Rango: ${r.nro_desde} – ${r.nro_hasta}</span>
        <span class="kv">Actual: ${r.nro_actual}</span>
      </div>
      <div class="muted">Vigencia: ${esc(r.fecha_inicio)} → ${esc(r.fecha_fin)}</div>
      <div class="actions">
        <button class="btn" onclick="openTimbradoForm(${r.id_timbrado}, ${asAttr(r)})">Editar</button>
        <select class="btn" onchange="toggleTimEstado(${r.id_timbrado}, this.value)">
          <option value="">Cambiar estado…</option>
          <option ${r.estado==='Vigente'?'disabled':''}>Vigente</option>
          <option ${r.estado==='Inactivo'?'disabled':''}>Inactivo</option>
          <option ${r.estado==='Vencido'?'disabled':''}>Vencido</option>
        </select>
      </div>
    `;
    wrap.appendChild(div);
  });

  if (!wrap.children.length){
    wrap.innerHTML = `<div class="muted">No hay timbrados con ese filtro.</div>`;
  }
}

async function openTimbradoForm(id=null, r=null){
  if (!ENUMS.tipos.length) await loadEnums();

  const modal = document.getElementById('modalTim');
  modal.style.display='flex';

  document.getElementById('timTitle').textContent = id? 'Editar Timbrado' : 'Nuevo Timbrado';
  document.getElementById('tim_id').value = id||'';

  const selNum  = document.getElementById('tim_num_sel');
  const selTipo = document.getElementById('tim_tcomp_sel');
  const selDoc  = document.getElementById('tim_tdoc_sel');

  selNum.innerHTML = '';
  const optNew = document.createElement('option'); optNew.value = OPT_NUEVO; optNew.textContent = '(Nuevo…)';
  selNum.appendChild(optNew);
  (ENUMS.numeros||[]).forEach(v=>{
    const o = document.createElement('option'); o.value=v; o.textContent=v; selNum.appendChild(o);
  });

  fillSelect(selTipo, ENUMS.tipos, '(Elegí tipo)');
  fillSelect(selDoc,  ENUMS.docs,  '(Elegí doc)');

  if (r){
    ensureOption(selNum,  r.numero_timbrado);
    ensureOption(selTipo, r.tipo_comprobante);
    ensureOption(selDoc,  r.tipo_documento);

    selNum.value  = r.numero_timbrado;
    selTipo.value = r.tipo_comprobante;
    selDoc.value  = r.tipo_documento;

    toggleNumNuevoUI(false);
    document.getElementById('tim_est').value   = r.establecimiento;
    document.getElementById('tim_pexp').value  = r.punto_expedicion;
    document.getElementById('tim_desde').value = r.nro_desde;
    document.getElementById('tim_hasta').value = r.nro_hasta;
    document.getElementById('tim_actual').value= r.nro_actual;
    document.getElementById('tim_fi').value    = r.fecha_inicio;
    document.getElementById('tim_ff').value    = r.fecha_fin;
    document.getElementById('tim_estado_form').value = r.estado;
  } else {
    selNum.value = OPT_NUEVO;
    toggleNumNuevoUI(true);
    document.getElementById('tim_num_new').value = '';
    selTipo.value = ''; selDoc.value = '';
    document.getElementById('tim_est').value   = '';
    document.getElementById('tim_pexp').value  = '';
    document.getElementById('tim_desde').value = 1;
    document.getElementById('tim_hasta').value = 9999999;
    document.getElementById('tim_actual').value= 0;
    document.getElementById('tim_fi').value    = '';
    document.getElementById('tim_ff').value    = '';
    document.getElementById('tim_estado_form').value = 'Vigente';
  }

  selNum.onchange = () => toggleNumNuevoUI(selNum.value === OPT_NUEVO);
}
function toggleNumNuevoUI(show){
  document.getElementById('wrap_tim_num_new').style.display = show ? '' : 'none';
  if (show) document.getElementById('tim_num_new').focus();
}
function closeTim(){ document.getElementById('modalTim').style.display='none'; }

async function saveTim(){
  const selNum  = document.getElementById('tim_num_sel');
  const selTipo = document.getElementById('tim_tcomp_sel');
  const selDoc  = document.getElementById('tim_tdoc_sel');

  const numero_timbrado = (selNum.value === OPT_NUEVO)
    ? document.getElementById('tim_num_new').value.trim()
    : selNum.value;

  const payload = {
    id_timbrado: toNull(document.getElementById('tim_id').value),
    numero_timbrado,
    tipo_comprobante: selTipo.value,
    tipo_documento: selDoc.value,
    establecimiento: document.getElementById('tim_est').value.trim(),
    punto_expedicion: document.getElementById('tim_pexp').value.trim(),
    nro_desde: Number(document.getElementById('tim_desde').value),
    nro_hasta: Number(document.getElementById('tim_hasta').value),
    nro_actual: Number(document.getElementById('tim_actual').value),
    fecha_inicio: document.getElementById('tim_fi').value,
    fecha_fin: document.getElementById('tim_ff').value,
    estado: document.getElementById('tim_estado_form').value
  };

  if (!payload.numero_timbrado){ alert('Ingresá el Nº de timbrado'); return; }
  if (!payload.tipo_comprobante){ alert('Elegí el Tipo de comprobante'); return; }
  if (!payload.tipo_documento){ alert('Elegí el Tipo de documento'); return; }

  const res = await fetch('?action=timbrado.save', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)
  });
  const j = await res.json();
  if(!j.ok){ alert(j.error||'Error al guardar'); return; }
  closeTim(); await loadTimbrados(); await loadTimCombo(); await loadEnums();
}

async function toggleTimEstado(id, value){
  if(!value) return;
  const res = await fetch('?action=timbrado.toggle', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id_timbrado:id, estado:value})});
  const j = await res.json();
  if(!j.ok){ alert(j.error||'Error cambiando estado'); return; }
  loadTimbrados(); await loadTimCombo();
}

/* ==================== ASIGNACIONES ==================== */
async function loadCajas(){
  const res = await fetch('?action=combo.cajas');
  const j = await res.json();
  const selF = document.getElementById('asig_caja');
  const selForm = document.getElementById('asig_caja_form');
  selF.innerHTML = '<option value="">Todas</option>';
  selForm.innerHTML = '';
  (j.items||[]).forEach(x=>{
    const o = document.createElement('option'); o.value=x.id_caja; o.textContent=x.nombre; selF.appendChild(o);
    const o2 = document.createElement('option'); o2.value=x.id_caja; o2.textContent=x.nombre; selForm.appendChild(o2);
  });
}
async function loadTimCombo(){
  const res = await fetch('?action=combo.timbrados');
  const j = await res.json();
  const selF = document.getElementById('asig_tim');
  const selForm = document.getElementById('asig_tim_form');
  selF.innerHTML = '<option value="">Vigentes</option>';
  selForm.innerHTML = '';
  (j.items||[]).forEach(x=>{
    const label = `#${x.id_timbrado} ${x.numero_timbrado} (${x.establecimiento}-${x.punto_expedicion}) [${x.tipo_comprobante}/${x.tipo_documento}]`;
    const o = document.createElement('option'); o.value=x.id_timbrado; o.textContent=label; selF.appendChild(o);
    const o2 = document.createElement('option'); o2.value=x.id_timbrado; o2.textContent=label; selForm.appendChild(o2);
  });
}
async function loadAsignaciones(){
  const id_caja = document.getElementById('asig_caja').value;
  const id_tim = document.getElementById('asig_tim').value;
  const estado = document.getElementById('asig_estado').value;
  const url = `?action=asig.list&id_caja=${encodeURIComponent(id_caja)}&id_timbrado=${encodeURIComponent(id_tim)}&estado=${encodeURIComponent(estado)}`;
  const res = await fetch(url);
  const j = await res.json();
  const wrap = document.getElementById('cardsAsig'); wrap.innerHTML='';

  (j.items||[]).forEach(r=>{
    const div = document.createElement('div'); div.className='card';
    div.innerHTML = `
      <div class="top">
        <div class="title">${esc(r.caja_nombre)} (#${r.id_caja})</div>
        <div>${stateBadgeHTML(r.estado)}</div>
      </div>
      <div class="row">
        <span class="kv">Timbrado #${r.id_timbrado} · ${esc(r.numero_timbrado)}</span>
        <span class="kv">PPP ${esc(r.establecimiento)}-${esc(r.punto_expedicion)}</span>
      </div>
      <div class="row">
        <span class="kv">${esc(r.tipo_comprobante)} / ${esc(r.tipo_documento)}</span>
        <span class="kv">Rango: ${r.nro_desde} – ${r.nro_hasta}</span>
        <span class="kv">Actual: ${r.nro_actual}</span>
      </div>
      <div class="muted">Vigencia: ${esc(r.fecha_inicio)} → ${esc(r.fecha_fin)} · Timbrado: ${esc(r.estado_timbrado)}</div>
      <div class="actions">
        <button class="btn" onclick="openAsigForm(${r.id_asignacion}, ${r.id_caja}, ${r.id_timbrado}, '${r.estado}')">Editar</button>
        <select class="btn" onchange="toggleAsigEstado(${r.id_asignacion}, this.value)">
          <option value="">Cambiar estado…</option>
          <option ${r.estado==='Vigente'?'disabled':''}>Vigente</option>
          <option ${r.estado==='Inactivo'?'disabled':''}>Inactivo</option>
          <option ${r.estado==='Vencido'?'disabled':''}>Vencido</option>
        </select>
      </div>
    `;
    wrap.appendChild(div);
  });

  if (!wrap.children.length){
    wrap.innerHTML = `<div class="muted">No hay asignaciones con ese filtro.</div>`;
  }
}
function openAsigForm(id=null, id_caja=null, id_tim=null, estado='Vigente'){
  const modal = document.getElementById('modalAsig');
  modal.style.display='flex';
  document.getElementById('asigTitle').textContent = id? 'Editar Asignación' : 'Nueva Asignación';
  document.getElementById('asig_id').value = id||'';
  document.getElementById('asig_caja_form').value = id_caja||'';
  document.getElementById('asig_tim_form').value = id_tim||'';
  document.getElementById('asig_estado_form').value = estado||'Vigente';
}
function closeAsig(){ document.getElementById('modalAsig').style.display='none'; }

async function saveAsig(){
  const payload = {
    id_asignacion: toNull(document.getElementById('asig_id').value),
    id_caja: toNull(document.getElementById('asig_caja_form').value),
    id_timbrado: toNull(document.getElementById('asig_tim_form').value),
    estado: document.getElementById('asig_estado_form').value
  };
  if (!payload.id_caja || !payload.id_timbrado){ alert('Seleccioná Caja y Timbrado.'); return; }
  const res = await fetch('?action=asig.save', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
  const j = await res.json();
  if(!j.ok){ alert(j.error||'Error al guardar'); return; }
  closeAsig(); loadAsignaciones();
}

/* ==================== Utils ==================== */
function toNull(v){ return (v===''||v===null||v===undefined) ? null : (/^-?\d+$/.test(v) ? Number(v) : v); }

/* ==================== Cierre de Modales ==================== */
function bindModalClose(){
  ['modalTim','modalAsig'].forEach(id=>{
    const overlay = document.getElementById(id);
    const card = overlay.querySelector('.card');
    card.addEventListener('click', e=> e.stopPropagation()); // no cerrar si clic dentro
    overlay.addEventListener('click', ()=> {
      if (id==='modalTim') closeTim(); else closeAsig();
    });
  });
  document.getElementById('btnCancelTim').addEventListener('click', closeTim);
  document.getElementById('btnCancelAsig').addEventListener('click', closeAsig);
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape'){
      const timOpen  = getComputedStyle(document.getElementById('modalTim')).display !== 'none';
      const asigOpen = getComputedStyle(document.getElementById('modalAsig')).display !== 'none';
      if (timOpen) closeTim();
      else if (asigOpen) closeAsig();
    }
  });
}

/* ==================== Init ==================== */
(async function init(){
  showTab('tim');
  await loadEnums();
  await loadCajas();
  await loadTimCombo();
  loadTimbrados();
  bindModalClose();
})();
</script>
</body>
</html>
