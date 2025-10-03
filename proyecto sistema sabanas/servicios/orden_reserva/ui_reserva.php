<?php
// reserva_ui.php — UI (GET) + API (POST) para Orden de Reserva (sin tabla de slots)
// Requiere tablas: profesional, reserva_cab, reserva_det, agenda_bloqueo, producto(tipo_item='S' o 'D')
// Opcional en producto: duracion_min (minutos). Si no existe o es NULL, se usa 30 para servicios.
// Usa clientes_buscar.php para buscar clientes.

session_start();
require_once __DIR__ . '/../../conexion/configv2.php'; // <-- AJUSTAR
header('X-Content-Type-Options: nosniff');

function json_error($msg,$code=400){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'error'=>$msg]); exit; }
function json_ok($data=[]){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>true]+$data); exit; }
function num($x){ return is_numeric($x)?0+$x:0; }
function s($x){ return is_string($x)?trim($x):null; }
function arr($x){ return is_array($x)?$x:[]; }

// === API (POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
  $op = strtolower(s($in['op'] ?? ''));
  if ($op==='') json_error('Parámetro op requerido');

  // Todas las horas de la UI se interpretan como locales en esta zona
  $TZ = 'America/Asuncion';

  try {
    switch ($op) {
      // 1) Profesionales activos
      case 'list_profesionales': {
        $r = pg_query($conn, "SELECT id_profesional, nombre FROM public.profesional WHERE estado='Activo' ORDER BY nombre");
        if(!$r) json_error('No se pudo listar profesionales');
        $rows=[]; while($x=pg_fetch_assoc($r)) $rows[] = $x;
        json_ok(['rows'=>$rows]);
      }

      // 2) Servicios y promociones (producto.tipo_item='S' o 'D')
      case 'list_servicios': {
        $sql = "SELECT id_producto,
                       nombre,
                       CASE WHEN tipo_item='S' THEN COALESCE(duracion_min,30) ELSE 0 END AS duracion_min,
                       precio_unitario,
                       tipo_item
                FROM public.producto
                WHERE estado='Activo' AND tipo_item IN ('S','D')
                ORDER BY CASE WHEN tipo_item='S' THEN 0 ELSE 1 END, nombre";
        $r = pg_query($conn,$sql);
        if(!$r) json_error('No se pudieron listar servicios/promos');
        $rows=[];
        while($x=pg_fetch_assoc($r)){
          $x['id_producto']    = (int)$x['id_producto'];
          $x['duracion_min']   = (int)$x['duracion_min'];
          $x['precio_unitario']= (float)$x['precio_unitario'];
          $rows[] = $x;
        }
        json_ok(['rows'=>$rows]);
      }

      // 3) Calcular slots disponibles
      case 'check_slots': {
        $fecha = s($in['fecha'] ?? '');
        $desde = s($in['jornada_desde'] ?? '08:00');
        $hasta = s($in['jornada_hasta'] ?? '20:00');
        $step  = max(5, (int)($in['intervalo_min'] ?? 15));
        $items = arr($in['items'] ?? []);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)) json_error('fecha inválida (YYYY-MM-DD)');
        if (!preg_match('/^\d{2}:\d{2}$/',$desde) || !preg_match('/^\d{2}:\d{2}$/',$hasta)) json_error('rango horario inválido');

        $dur_total = 0;
        $hay_servicio = false;
        foreach ($items as $it) {
          $idp = (int)($it['id_producto'] ?? 0);
          $qty = (float)($it['cantidad'] ?? 0);
          if ($idp<=0 || $qty<=0) continue;
          $r = pg_query_params($conn,"SELECT tipo_item, COALESCE(duracion_min,30)::int AS dur FROM public.producto WHERE id_producto=$1 AND estado='Activo' LIMIT 1",[$idp]);
          if(!$r || pg_num_rows($r)===0) json_error("Producto #$idp no válido");
          $row = pg_fetch_assoc($r);
          if ($row['tipo_item'] === 'S') {
            $hay_servicio = true;
            $dur_total += ((int)$row['dur']) * $qty;
          }
        }
        if (!$hay_servicio) json_error('Agregá al menos un servicio');
        if ($dur_total<=0) $dur_total = 30;

        $sql = "
WITH cfg AS (
  SELECT
    $1::date AS dia,
    $2::time AS desde,
    $3::time AS hasta,
    $4::int  AS step_min,
    $5::int  AS dur_min,
    $6::text AS tz
),
pros AS (
  SELECT id_profesional FROM public.profesional WHERE estado='Activo'
),
slots AS (
  SELECT
    ((to_char(cfg.dia,'YYYY-MM-DD')||' '||cfg.desde::text||' '||cfg.tz)::timestamptz + make_interval(mins => n*cfg.step_min))                AS inicio_tz,
    ((to_char(cfg.dia,'YYYY-MM-DD')||' '||cfg.desde::text||' '||cfg.tz)::timestamptz + make_interval(mins => n*cfg.step_min + cfg.dur_min)) AS fin_tz
  FROM cfg,
  generate_series(
    0,
    ((extract(epoch from (cfg.hasta - cfg.desde)) / 60)::int - cfg.dur_min) / cfg.step_min
  ) AS n
),
solapes_res AS (
  SELECT p.id_profesional, s.inicio_tz, s.fin_tz
  FROM pros p
  CROSS JOIN slots s
  JOIN public.reserva_cab rc
    ON rc.id_profesional = p.id_profesional
   AND rc.estado IN ('Pendiente','Confirmada')
   AND tstzrange(rc.inicio_ts, rc.fin_ts, '[)') &&
       tstzrange(s.inicio_tz, s.fin_tz, '[)')
),
solapes_bloq AS (
  SELECT p.id_profesional, s.inicio_tz, s.fin_tz
  FROM pros p
  CROSS JOIN slots s
  JOIN public.agenda_bloqueo b
    ON b.id_profesional = p.id_profesional
   AND tstzrange(b.inicio_ts, b.fin_ts, '[)') &&
       tstzrange(s.inicio_tz, s.fin_tz, '[)')
),
libres AS (
  SELECT
    s.inicio_tz, s.fin_tz,
    array_agg(p.id_profesional ORDER BY p.id_profesional) FILTER (
      WHERE so.id_profesional IS NULL AND bl.id_profesional IS NULL
    ) AS pros_libres
  FROM slots s
  JOIN pros p ON TRUE
  LEFT JOIN solapes_res so
    ON so.id_profesional = p.id_profesional
   AND so.inicio_tz = s.inicio_tz
   AND so.fin_tz    = s.fin_tz
  LEFT JOIN solapes_bloq bl
    ON bl.id_profesional = p.id_profesional
   AND bl.inicio_tz = s.inicio_tz
   AND bl.fin_tz    = s.fin_tz
  GROUP BY s.inicio_tz, s.fin_tz
)
SELECT
  inicio_tz AS inicio,
  fin_tz    AS fin,
  COALESCE(cardinality(pros_libres),0) AS profesionales_libres,
  COALESCE(pros_libres, '{}'::int[])   AS lista_profesionales
FROM libres
WHERE COALESCE(cardinality(pros_libres),0) > 0
ORDER BY inicio;
        ";
        $params = [$fecha, $desde, $hasta, $step, $dur_total, $TZ];
        $r = pg_query_params($conn,$sql,$params);
        if(!$r) json_error('No se pudieron calcular los slots');

        $rows=[];
        while($x=pg_fetch_assoc($r)){
          $x['profesionales_libres'] = (int)$x['profesionales_libres'];
          $x['inicio'] = substr($x['inicio'],0,19);
          $x['fin']    = substr($x['fin'],0,19);
          $rows[]=$x;
        }
        json_ok(['duracion_total_min'=>$dur_total,'rows'=>$rows]);
      }

      // 4) Crear reserva
      case 'create_reserva': {
        $id_cliente = (int)($in['id_cliente'] ?? 0);
        $inicio_ts  = s($in['inicio_ts'] ?? '');
        $id_prof    = (int)($in['id_profesional'] ?? 0);
        $estado     = s($in['estado'] ?? 'Pendiente');
        $items      = arr($in['items'] ?? []);
        $notas      = s($in['notas'] ?? null);

        if ($id_cliente<=0) json_error('id_cliente requerido');
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/',$inicio_ts)) json_error('inicio_ts inválido (YYYY-MM-DD HH:MM)');
        if (!in_array($estado, ['Pendiente','Confirmada'], true)) $estado='Pendiente';

        $dur_total = 0;
        $hay_servicio = false;
        $snap_items = [];

        foreach ($items as $it) {
          $idp = (int)($it['id_producto'] ?? 0);
          $qty = (float)($it['cantidad'] ?? 0);
          if ($idp<=0 || $qty<=0) continue;

          $r = pg_query_params($conn,
            "SELECT tipo_item, nombre, precio_unitario, tipo_iva, COALESCE(duracion_min,30)::int AS dur
             FROM public.producto
             WHERE id_producto=$1 AND estado='Activo'
             LIMIT 1", [$idp]
          );
          if(!$r || pg_num_rows($r)===0) json_error("Producto #$idp no válido");
          $row = pg_fetch_assoc($r);
          $tipo_item = $row['tipo_item'];

          if ($tipo_item === 'S') {
            $hay_servicio = true;
            $duracion = (int)$row['dur'];
            $dur_total += $duracion * $qty;
          } elseif ($tipo_item === 'D') {
            $duracion = 0;
          } else {
            json_error("Producto #$idp no habilitado para reservas");
          }

          $snap_items[] = [
            'id_producto'     => $idp,
            'descripcion'     => $row['nombre'],
            'precio_unitario' => (float)$row['precio_unitario'],
            'tipo_iva'        => $row['tipo_iva'],
            'duracion_min'    => $duracion,
            'cantidad'        => $qty
          ];
        }

        if(!$hay_servicio) json_error('Agregá al menos un servicio');
        if ($dur_total<=0) $dur_total = 30;

        $fin_ts_local = date('Y-m-d H:i', strtotime("$inicio_ts +$dur_total minutes"));
        $fecha_reserva = date('Y-m-d', strtotime($inicio_ts));

        pg_query($conn,'BEGIN');

        if ($id_prof<=0) {
          $q = "
            WITH rango AS (
              SELECT ($1||' '||$3)::timestamptz AS ini, ($2||' '||$3)::timestamptz AS fin
            ), pros AS (
              SELECT id_profesional FROM public.profesional WHERE estado='Activo'
            ), solape_res AS (
              SELECT rc.id_profesional
              FROM rango r
              JOIN public.reserva_cab rc
                ON tstzrange(rc.inicio_ts, rc.fin_ts, '[)') &&
                   tstzrange(r.ini, r.fin, '[)')
               AND rc.estado IN ('Pendiente','Confirmada')
            ), solape_bloq AS (
              SELECT b.id_profesional
              FROM rango r
              JOIN public.agenda_bloqueo b
                ON tstzrange(b.inicio_ts, b.fin_ts, '[)') &&
                   tstzrange(r.ini, r.fin, '[)')
            )
            SELECT p.id_profesional
            FROM pros p
            LEFT JOIN solape_res sr ON sr.id_profesional = p.id_profesional
            LEFT JOIN solape_bloq sb ON sb.id_profesional = p.id_profesional
            WHERE sr.id_profesional IS NULL AND sb.id_profesional IS NULL
            ORDER BY p.id_profesional
            LIMIT 1
          ";
          $rp = pg_query_params($conn, $q, [$inicio_ts, $fin_ts_local, $TZ]);
          if(!$rp || pg_num_rows($rp)===0){ pg_query($conn,'ROLLBACK'); json_error('No hay profesional libre en ese rango'); }
          $id_prof = (int)pg_fetch_result($rp,0,0);
        }

        $sqlCab = "
          INSERT INTO public.reserva_cab
            (id_cliente, id_profesional, fecha_reserva, inicio_ts, fin_ts, estado, notas)
          VALUES ($1,$2,$3, ($4||' '||$6)::timestamptz, ($5||' '||$6)::timestamptz, $7, $8)
          RETURNING id_reserva
        ";
        $rCab = pg_query_params($conn,$sqlCab,[
          $id_cliente,
          $id_prof,
          $fecha_reserva,
          $inicio_ts,
          $fin_ts_local,
          $TZ,
          $estado,
          $notas
        ]);
        if(!$rCab){ pg_query($conn,'ROLLBACK'); json_error('No se pudo crear la reserva (cabecera)'); }
        $id_reserva = (int)pg_fetch_result($rCab,0,0);

        $sqlDet = "
          INSERT INTO public.reserva_det
            (id_reserva, id_producto, descripcion, precio_unitario, tipo_iva, duracion_min, cantidad)
          VALUES ($1,$2,$3,$4,$5,$6,$7)
        ";
        foreach ($snap_items as $it) {
          $ok = pg_query_params($conn,$sqlDet,[
            $id_reserva, $it['id_producto'], $it['descripcion'], $it['precio_unitario'],
            $it['tipo_iva'], $it['duracion_min'], $it['cantidad']
          ]);
          if(!$ok){ pg_query($conn,'ROLLBACK'); json_error('Error al insertar detalle'); }
        }

        pg_query($conn,'COMMIT');
        json_ok([
          'id_reserva'=>$id_reserva,
          'id_profesional'=>$id_prof,
          'inicio_ts'=>$inicio_ts,
          'fin_ts'=>$fin_ts_local,
          'estado'=>$estado
        ]);
      }

      // 5) Reservas del día
      case 'list_reservas_dia': {
        $fecha = s($in['fecha'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha)) json_error('fecha inválida');
        $sql = "
          SELECT rc.id_reserva, rc.inicio_ts, rc.fin_ts, rc.estado,
                 c.nombre||' '||COALESCE(c.apellido,'') AS cliente,
                 p.nombre AS profesional
          FROM public.reserva_cab rc
          JOIN public.clientes c ON c.id_cliente = rc.id_cliente
          JOIN public.profesional p ON p.id_profesional = rc.id_profesional
          WHERE (rc.inicio_ts AT TIME ZONE $1)::date = $2::date
          ORDER BY rc.inicio_ts
        ";
        $r = pg_query_params($conn,$sql,[$TZ,$fecha]);
        if(!$r) json_error('No se pudo listar reservas');
        $rows=[]; while($x=pg_fetch_assoc($r)) $rows[]=$x;
        json_ok(['rows'=>$rows]);
      }

      default: json_error('op no reconocido');
    }
  } catch(Throwable $e){
    $msg = $e->getMessage();
    if (stripos($msg,'exclude')!==false || stripos($msg,'constraint')!==false) {
      $msg = 'El horario se ocupó recién. Probá con otro slot.';
    }
    json_error($msg);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Orden de Reserva</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" type="text/css" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/css/styles_servicios.css" />
<style>
  :root{ --g:#10b981; --r:#ef4444; --b:#111; --shadow:0 10px 20px rgba(0,0,0,.12); }
  body{ font-family:system-ui,Segoe UI,Roboto,Arial; margin:20px; color:#111; background:#fff }
  .card{ border:1px solid #e5e7eb; border-radius:10px; padding:12px; margin:10px 0; background:#fff }
  .row{ display:flex; gap:8px; flex-wrap:wrap; align-items:end }
  .row>*{ flex:1 }
  label{ display:block; font-size:12px; color:#374151; margin:6px 0 4px }
  input,select,button{ padding:8px 10px; border:1px solid #d1d5db; border-radius:8px }
  button{ background:#111; color:#fff; border:none; cursor:pointer; }
  button.sec{ background:#f3f4f6; color:#111 }
  table{ width:100%; border-collapse:collapse; margin-top:8px }
  th,td{ border:1px solid #e5e7eb; padding:8px; font-size:14px; text-align:left }
  th{ background:#f3f4f6; text-transform:uppercase; font-size:12px; letter-spacing:.05em }
  .grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap:10px; }
  .svc{ border:1px solid #e5e7eb; border-radius:10px; padding:10px; background:#fff; transition:transform .06s; }
  .svc:hover{ transform:scale(1.01) }
  .svc h4{ margin:0 0 6px; font-size:14px; }
  .svc.promo{ border-color:#fecaca; background:#fef2f2; }
  .svc-section{ margin-bottom:14px; }
  .svc-section h3{ margin:0 0 8px; font-size:15px; }
  .muted{ color:#6b7280; font-size:12px }
  .qtybtn{ padding:4px 8px; border-radius:6px; margin:0 3px; }
  .danger{ background:#fee2e2; color:#991b1b; }
  .stat{ flex:0 0 210px; font-weight:600; font-size:14px; }
  .stat span{ font-weight:700; }

  .toast-layer{ position:fixed; inset:0; pointer-events:none; display:grid; place-items:center; z-index:9999; }
  .toast-container{ display:flex; flex-direction:column; gap:10px; }
  .toast{ pointer-events:auto; min-width:280px; max-width:min(92vw,520px); background:#111; color:#fff; border-radius:12px; padding:12px 14px; box-shadow:var(--shadow); display:flex; align-items:flex-start; gap:12px; animation:toastIn .18s ease-out both; }
  .toast.error{ background:#991b1b }
  .toast .title{ font-weight:600; margin-bottom:2px }
  .toast .msg{ opacity:.95 }
  .toast .close{ margin-left:auto; background:transparent; border:0; color:#fff; font-size:18px; cursor:pointer; }
  @keyframes toastIn{ from{ transform:translateY(8px); opacity:0 } to{ transform:translateY(0); opacity:1 } }
  @keyframes toastOut{ from{ transform:translateY(0); opacity:1 } to{ transform:translateY(8px); opacity:0 } }

  .chip{
    padding:8px 12px;
    border:1px solid #d1d5db;
    border-radius:999px;
    background:#f3f4f6;
    color:#111;
    font-weight:600;
    letter-spacing:.02em;
    cursor:pointer;
    transition:all .14s ease;
    box-shadow:0 1px 3px rgba(17,24,39,.14);
  }
  .chip:hover{
    background:#e5e7eb;
    border-color:#cbd5f5;
    box-shadow:0 4px 12px rgba(17,24,39,.18);
  }
  .chip:focus-visible{
    outline:2px solid #111;
    outline-offset:2px;
  }
  .chip.sel{
    background:#111;
    color:#fff;
    border-color:#111;
    box-shadow:0 8px 24px rgba(17,17,17,.32);
  }
</style>
</head>
<body>
   <div id="navbar-container"></div>
<script src="../../servicios/navbar/navbar.js"></script>
  <h1>Orden de Reserva</h1>

  <!-- 1) Cliente -->
  <div class="card">
    <h3>1) Cliente</h3>
    <div class="row">
      <div>
        <label>Buscar nombre o RUC/CI</label>
        <input id="q_cliente" placeholder="Ej: Ana López o 1234567-8" />
      </div>
      <div style="flex:0"><button onclick="buscarClientes()">Buscar</button></div>
      <div>
        <label>Resultados</label>
        <select id="sel_cliente"></select>
      </div>
      <div style="flex:0"><button class="sec" onclick="usarCliente()">Usar cliente</button></div>
      <div>
        <label>ID Cliente</label>
        <input id="id_cliente" type="number" placeholder="-" />
      </div>
    </div>
  </div>

  <!-- 2) Servicios y promociones -->
  <div class="card">
    <h3>2) Servicios y descuentos</h3>
    <div class="muted">Clic en “＋” para agregar; “－” para quitar. Solo los servicios suman minutos.</div>
    <div id="servicios" style="margin-top:10px"></div>

    <table>
      <thead>
        <tr>
          <th>Ítem</th>
          <th>Tipo</th>
          <th>Dur. (min)</th>
          <th>Cant.</th>
          <th>Precio (Gs)</th>
          <th>Subtotal</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody_items"></tbody>
    </table>
    <div class="row" style="margin-top:8px">
      <div class="stat">Duración total (min): <span id="dur_total">0</span></div>
      <div class="stat">Total estimado (Gs): <span id="monto_total">0</span></div>
      <div>
        <label>Notas</label>
        <input id="notas" placeholder="Observación del cliente..." />
      </div>
    </div>
  </div>

  <!-- 3) Fecha y horarios -->
  <div class="card">
    <h3>3) Fecha y horario</h3>
    <div class="row">
      <div>
        <label>Fecha</label>
        <input id="fecha" type="date" />
      </div>
      <div>
        <label>Desde</label>
        <input id="desde" value="08:00" />
      </div>
      <div>
        <label>Hasta</label>
        <input id="hasta" value="20:00" />
      </div>
      <div>
        <label>Intervalo (min)</label>
        <input id="step" type="number" min="5" value="15" />
        <div class="muted">Min recomendado: <span id="step_recomendado">30</span></div>
      </div>
      <div style="flex:0">
        <button onclick="verSlots()">Ver horarios</button>
      </div>
    </div>

    <div id="slots" style="margin-top:10px; display:flex; flex-wrap:wrap; gap:8px"></div>

    <div class="row" style="margin-top:10px">
      <div>
        <label>Profesional (opcional si auto-asignás)</label>
        <select id="sel_profesional"><option value="">(Auto)</option></select>
      </div>
      <div>
        <label>Inicio seleccionado</label>
        <input id="inicio_sel" placeholder="YYYY-MM-DD HH:MM" />
      </div>
    </div>
  </div>

  <!-- 4) Confirmar -->
  <div class="card">
    <h3>4) Confirmar</h3>
    <div class="row">
      <div>
        <label>Estado</label>
        <select id="estado">
          <option>Pendiente</option>
          <option>Confirmada</option>
        </select>
      </div>
      <div style="flex:0">
        <button onclick="crearReserva()">Crear Reserva</button>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="toast-layer" aria-live="polite" aria-atomic="true">
    <div class="toast-container" id="toasts"></div>
  </div>

<script>
const API = location.pathname;
const CLIENTES_API = '../../venta_v3/cliente/clientes_buscar.php'; // <-- AJUSTAR
const $ = (id)=>document.getElementById(id);

let productos = [];           // [{id_producto,nombre,tipo_item,duracion_min, precio_unitario}]
let carrito = {};             // { id_producto: cantidad }
let ultimoSlots = [];         // cache de slots
let mapaProsPorSlot = {};     // key inicioISO => [ids]

function showToast(message, type='success', title=null, timeout=2200){
  const cont = $('toasts'); const el = document.createElement('div');
  el.className = 'toast' + (type==='error' ? ' error' : '');
  el.innerHTML = `
    <div>
      <div class="title">${title ? title : (type==='error'?'Error':'Listo')}</div>
      <div class="msg">${message}</div>
    </div>
    <button class="close" aria-label="Cerrar" onclick="closeToast(this)">×</button>
  `;
  cont.prepend(el);
  const t = setTimeout(()=>{ el.style.animation='toastOut .18s ease-in forwards'; setTimeout(()=>el.remove(),180); }, timeout);
  el._timer=t;
}
function closeToast(btn){ const el=btn.closest('.toast'); if(!el) return; clearTimeout(el._timer); el.style.animation='toastOut .18s ease-in forwards'; setTimeout(()=>el.remove(),180); }

function fmt(n){ return Number(n||0).toLocaleString('es-PY'); }
async function api(op, payload={}){
  const res = await fetch(API, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({op, ...payload})});
  const data = await res.json();
  if(!data.success) throw new Error(data.error||'Error');
  return data;
}

/* Cliente */
async function buscarClientes(){
  try{
    const q = $('q_cliente').value.trim();
    const url = `${CLIENTES_API}?q=${encodeURIComponent(q)}&page=1&page_size=10`;
    const res = await fetch(url, { headers:{'Accept':'application/json'} });
    const data = await res.json();
    if(!data.ok) throw new Error(data.error||'Error clientes');
    const sel = $('sel_cliente'); sel.innerHTML='';
    (data.data||[]).forEach(c=>{
      const opt = document.createElement('option');
      opt.value = c.id_cliente;
      opt.textContent = `${c.nombre_completo} — ${c.ruc_ci||'-'}`;
      sel.appendChild(opt);
    });
    showToast(`Encontrados ${data.data?.length||0} clientes.`);
  }catch(e){ showToast(e.message,'error'); }
}
function usarCliente(){
  const id = $('sel_cliente').value ? Number($('sel_cliente').value) : 0;
  if(id>0){ $('id_cliente').value = id; showToast('Cliente seleccionado.'); }
}

/* Productos (servicios + promos) */
async function cargarProductos(){
  try{
    const r = await api('list_servicios');
    productos = (r.rows||[]).map(item=>({
      ...item,
      id_producto: Number(item.id_producto),
      duracion_min: Number(item.duracion_min||0),
      precio_unitario: Number(item.precio_unitario||0)
    }));
    renderProductos();
  }catch(e){ showToast(e.message,'error'); }
}
function renderProductos(){
  const cont = $('servicios');
  cont.innerHTML = '';

  const servicios = productos.filter(x=>x.tipo_item==='S');
  const promos    = productos.filter(x=>x.tipo_item==='D');

  const buildSection = (items, title) => {
    if(items.length===0) return;
    const section = document.createElement('div');
    section.className = 'svc-section';
    const header = document.createElement('h3');
    header.textContent = title;
    section.appendChild(header);
    const grid = document.createElement('div');
    grid.className = 'grid';
    items.forEach(sv=>{
      const card = document.createElement('div');
      card.className = 'svc';
      if (sv.tipo_item === 'D') card.classList.add('promo');
      const detalle = sv.tipo_item === 'S'
        ? `Dur: ${sv.duracion_min||30} min • Gs ${fmt(sv.precio_unitario)}`
        : `Monto: Gs ${fmt(sv.precio_unitario)}`;
      card.innerHTML = `
        <h4>${sv.nombre}</h4>
        <div class="muted">${detalle}</div>
        <div style="margin-top:8px">
          <button class="qtybtn" onclick="addItem(${sv.id_producto},1)">＋</button>
          <button class="qtybtn" onclick="addItem(${sv.id_producto},-1)">－</button>
        </div>
      `;
      grid.appendChild(card);
    });
    section.appendChild(grid);
    cont.appendChild(section);
  };

  buildSection(servicios, 'Servicios');
  buildSection(promos, 'Promociones / Descuentos');

  renderCarrito();
}
function addItem(id_producto, delta){
  carrito[id_producto] = (carrito[id_producto]||0) + delta;
  if (carrito[id_producto] <= 0) delete carrito[id_producto];
  renderCarrito();
}
function renderCarrito(){
  const tb = $('tbody_items'); tb.innerHTML='';
  let totalMin = 0;
  let totalGs  = 0;

  Object.keys(carrito).map(Number).forEach(id=>{
    const prod = productos.find(x=>x.id_producto===id);
    if(!prod) return;
    const cant = carrito[id];
    const esServicio = prod.tipo_item === 'S';
    const duracion = esServicio ? (prod.duracion_min || 30) * cant : 0;
    const precio = prod.precio_unitario;
    const subtotal = precio * cant;

    if(esServicio) totalMin += duracion;
    totalGs += subtotal;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${prod.nombre}</td>
      <td>${esServicio ? 'Servicio' : 'Promo'}</td>
      <td>${esServicio ? prod.duracion_min || 30 : '–'}</td>
      <td>${cant}</td>
      <td>${fmt(precio)}</td>
      <td>${fmt(subtotal)}</td>
      <td>
        <button class="qtybtn" onclick="addItem(${id},+1)">＋</button>
        <button class="qtybtn" onclick="addItem(${id},-1)">－</button>
        <button class="qtybtn danger" onclick="removeItem(${id})">🗑</button>
      </td>
    `;
    tb.appendChild(tr);
  });
  $('dur_total').textContent = totalMin;
  $('monto_total').textContent = fmt(totalGs);
  const recomendadoSpan = $('step_recomendado');
  if (recomendadoSpan) {
    const recomendado = totalMin > 0 ? totalMin : 30;
    recomendadoSpan.textContent = recomendado;
  }
}
function removeItem(id){ delete carrito[id]; renderCarrito(); }

/* Slots */
async function verSlots(){
  try{
    const fecha = $('fecha').value;
    if(!fecha) throw new Error('Elegí la fecha');
    const items = Object.keys(carrito).map(id=>({id_producto:Number(id), cantidad:Number(carrito[id])}));
    if(items.length===0) throw new Error('Agregá al menos un servicio');

    const hayServicio = Object.keys(carrito).some(id=>{
      const prod = productos.find(x=>x.id_producto===Number(id));
      return prod && prod.tipo_item==='S';
    });
    if(!hayServicio) throw new Error('Agregá al menos un servicio');

    const payload = {
      fecha,
      jornada_desde: $('desde').value || '08:00',
      jornada_hasta: $('hasta').value || '20:00',
      intervalo_min: Number($('step').value||15),
      items
    };
    const r = await api('check_slots', payload);
    ultimoSlots = r.rows||[];
    mapaProsPorSlot = {};
    const wrap = $('slots'); wrap.innerHTML='';
    if (ultimoSlots.length===0) {
      wrap.textContent = 'Sin horarios disponibles para esa combinación.';
      showToast('Sin horarios disponibles','error');
      return;
    }
    ultimoSlots.forEach(sl=>{
      const chip = document.createElement('button');
      chip.className = 'chip';
      const h = sl.inicio.substring(11,16);
      chip.textContent = `${h} (${sl.profesionales_libres})`;
      chip.onclick = ()=>{
        document.querySelectorAll('#slots .chip').forEach(c=>c.classList.remove('sel'));
        chip.classList.add('sel');
        $('inicio_sel').value = `${fecha} ${h}`;
        mapaProsPorSlot[sl.inicio] = sl.lista_profesionales || [];
        cargarProfesionales(mapaProsPorSlot[sl.inicio]);
      };
      wrap.appendChild(chip);
    });
    showToast(`Encontrados ${ultimoSlots.length} slots.`);
  }catch(e){ showToast(e.message,'error'); }
}

async function cargarProfesionales(preferidos=[]){
  try{
    const r = await api('list_profesionales');
    const sel = $('sel_profesional'); sel.innerHTML = '<option value="">(Auto)</option>';
    r.rows.forEach(p=>{
      const opt = document.createElement('option');
      opt.value = p.id_profesional;
      opt.textContent = p.nombre;
      if (preferidos.includes(Number(p.id_profesional))) sel.appendChild(opt);
    });
    r.rows.forEach(p=>{
      if (![...sel.options].some(o=>Number(o.value)===Number(p.id_profesional))) {
        const opt = document.createElement('option');
        opt.value = p.id_profesional;
        opt.textContent = p.nombre + ' (ocupado)';
        sel.appendChild(opt);
      }
    });
  }catch(e){ showToast(e.message,'error'); }
}

/* Crear Reserva */
async function crearReserva(){
  try{
    const id_cliente = Number($('id_cliente').value||0);
    if(!id_cliente) throw new Error('Seleccioná un cliente');
    const inicio = $('inicio_sel').value.trim();
    if(!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(inicio)) throw new Error('Elegí un slot');

    const items = Object.keys(carrito).map(id=>({id_producto:Number(id), cantidad:Number(carrito[id])}));
    if(items.length===0) throw new Error('Agregá servicios');

    const hayServicio = Object.keys(carrito).some(id=>{
      const prod = productos.find(x=>x.id_producto===Number(id));
      return prod && prod.tipo_item==='S';
    });
    if(!hayServicio) throw new Error('Agregá al menos un servicio');

    const id_prof = Number($('sel_profesional').value||0);
    const r = await api('create_reserva', {
      id_cliente,
      inicio_ts: inicio, // local en Asunción
      id_profesional: id_prof || null,
      items,
      estado: $('estado').value,
      notas: $('notas').value || null
    });

    showToast(`Reserva #${r.id_reserva} creada para ${r.inicio_ts}.`, 'success', '¡Listo!');

    $('sel_cliente').innerHTML=''; $('id_cliente').value='';
    carrito = {}; renderCarrito();
    $('fecha').value=''; $('inicio_sel').value='';
    document.querySelector('#slots').innerHTML='';
    $('sel_profesional').innerHTML='<option value="">(Auto)</option>';
    $('notas').value='';
  }catch(e){ showToast(e.message,'error'); }
}

/* Init */
window.addEventListener('DOMContentLoaded', async ()=>{ await cargarProductos(); });
</script>
</body>
</html>
