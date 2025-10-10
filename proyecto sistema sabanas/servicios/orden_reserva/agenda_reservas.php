<?php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('X-Content-Type-Options: nosniff');

function json_error($msg, $code = 400)
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => false, 'error' => $msg]);
  exit;
}

function json_ok($data = [])
{
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => true] + $data);
  exit;
}

function s($x)
{
  return is_string($x) ? trim($x) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = json_decode(file_get_contents('php://input'), true) ?? $_POST;
  $op = strtolower(s($in['op'] ?? ''));
  if ($op === '') json_error('Parametro op requerido');

  $TZ = 'America/Asuncion';

  try {
    switch ($op) {
      case 'list_profesionales': {
        $r = pg_query($conn, "SELECT id_profesional, nombre FROM public.profesional WHERE estado='Activo' ORDER BY nombre");
        if (!$r) json_error('No se pudieron listar los profesionales');
        $rows = [];
        while ($row = pg_fetch_assoc($r)) {
          $row['id_profesional'] = (int)$row['id_profesional'];
          $rows[] = $row;
        }
        json_ok(['rows' => $rows]);
      }

      case 'list_reservas': {
        $fDesde = s($in['fecha_desde'] ?? '');
        $fHasta = s($in['fecha_hasta'] ?? '');
        $estado = s($in['estado'] ?? '');
        $q = s($in['q'] ?? '');
        $idProf = (int)($in['id_profesional'] ?? 0);
        $page = max(1, (int)($in['page'] ?? 1));
        $pageSize = min(100, max(5, (int)($in['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        if ($fDesde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDesde)) json_error('fecha_desde invalida');
        if ($fHasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fHasta)) json_error('fecha_hasta invalida');

        $params = [$TZ];
        $where = ['1=1'];

        if ($fDesde !== '') {
          $params[] = $fDesde;
          $where[] = "(rc.inicio_ts AT TIME ZONE $1)::date >= $" . count($params) . "::date";
        }
        if ($fHasta !== '') {
          $params[] = $fHasta;
          $where[] = "(rc.inicio_ts AT TIME ZONE $1)::date <= $" . count($params) . "::date";
        }
        if ($estado !== '' && $estado !== 'Todos') {
          $params[] = $estado;
          $where[] = "rc.estado = $" . count($params);
        }
        if ($idProf > 0) {
          $params[] = $idProf;
          $where[] = "rc.id_profesional = $" . count($params);
        }
        if ($q !== '') {
          $params[] = '%' . $q . '%';
          $idx1 = count($params);
          $params[] = '%' . $q . '%';
          $idx2 = count($params);
          $where[] = "((c.nombre || ' ' || COALESCE(c.apellido,'')) ILIKE $" . $idx1 . " OR CAST(rc.id_reserva AS TEXT) ILIKE $" . $idx2 . ")";
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sqlCount = "
          SELECT COUNT(*)
          FROM public.reserva_cab rc
          JOIN public.clientes c ON c.id_cliente = rc.id_cliente
          LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional
          $whereSql
        ";
        $countRes = pg_query_params($conn, $sqlCount, $params);
        if (!$countRes) json_error('No se pudo contar las reservas');
        $total = (int)pg_fetch_result($countRes, 0, 0);

        $sqlData = "
          SELECT rc.id_reserva,
                 rc.estado,
                 to_char(rc.inicio_ts AT TIME ZONE $1, 'YYYY-MM-DD HH24:MI') AS inicio_local,
                 to_char(rc.fin_ts AT TIME ZONE $1, 'YYYY-MM-DD HH24:MI') AS fin_local,
                 c.nombre || ' ' || COALESCE(c.apellido,'') AS cliente,
                 COALESCE(p.nombre, 'Sin asignar') AS profesional,
                 rc.id_profesional
          FROM public.reserva_cab rc
          JOIN public.clientes c ON c.id_cliente = rc.id_cliente
          LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional
          $whereSql
          ORDER BY rc.inicio_ts
          LIMIT $pageSize OFFSET $offset
        ";
        $dataRes = pg_query_params($conn, $sqlData, $params);
        if (!$dataRes) json_error('No se pudieron obtener las reservas');

        $rows = [];
        while ($row = pg_fetch_assoc($dataRes)) {
          $row['id_reserva'] = (int)$row['id_reserva'];
          $row['id_profesional'] = $row['id_profesional'] !== null ? (int)$row['id_profesional'] : null;
          $rows[] = $row;
        }

        json_ok([
          'rows' => $rows,
          'total' => $total,
          'page' => $page,
          'page_size' => $pageSize
        ]);
      }

      case 'get_reserva': {
        $id = (int)($in['id_reserva'] ?? 0);
        if ($id <= 0) json_error('id_reserva invalido');

        $sqlCab = "
          SELECT rc.id_reserva,
                 rc.estado,
                 rc.notas,
                 to_char(rc.inicio_ts AT TIME ZONE $1, 'YYYY-MM-DD HH24:MI') AS inicio_local,
                 to_char(rc.fin_ts AT TIME ZONE $1, 'YYYY-MM-DD HH24:MI') AS fin_local,
                 c.id_cliente,
                 c.nombre,
                 c.apellido,
                 c.ruc_ci,
                 c.telefono,
                 c.direccion,
                 rc.id_profesional,
                 COALESCE(p.nombre, 'Sin asignar') AS profesional
          FROM public.reserva_cab rc
          JOIN public.clientes c ON c.id_cliente = rc.id_cliente
          LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional
          WHERE rc.id_reserva = $2
          LIMIT 1
        ";
        $cabRes = pg_query_params($conn, $sqlCab, [$TZ, $id]);
        if (!$cabRes || pg_num_rows($cabRes) === 0) json_error('Reserva no encontrada', 404);
        $cab = pg_fetch_assoc($cabRes);
        $cab['id_reserva'] = (int)$cab['id_reserva'];
        $cab['id_cliente'] = (int)$cab['id_cliente'];
        $cab['id_profesional'] = $cab['id_profesional'] !== null ? (int)$cab['id_profesional'] : null;

        $sqlDet = "
          SELECT item_nro,
                 id_producto,
                 descripcion,
                 cantidad,
                 precio_unitario,
                 COALESCE(tipo_iva,'EXE') AS tipo_iva,
                 COALESCE(duracion_min,0) AS duracion_min
          FROM public.reserva_det
          WHERE id_reserva=$1
          ORDER BY item_nro
        ";
        $detRes = pg_query_params($conn, $sqlDet, [$id]);
        $items = [];
        if ($detRes) {
          while ($row = pg_fetch_assoc($detRes)) {
            $row['item_nro'] = (int)$row['item_nro'];
            $row['id_producto'] = (int)$row['id_producto'];
            $row['cantidad'] = (float)$row['cantidad'];
            $row['precio_unitario'] = (float)$row['precio_unitario'];
            $row['duracion_min'] = (int)$row['duracion_min'];
            $items[] = $row;
          }
        }

        json_ok(['cab' => $cab, 'items' => $items]);
      }

      case 'cancel_reserva': {
        $id = (int)($in['id_reserva'] ?? 0);
        $motivo = s($in['motivo'] ?? '');
        if ($id <= 0) json_error('id_reserva invalido');

        pg_query($conn, 'BEGIN');
        $sel = pg_query_params($conn, "SELECT estado FROM public.reserva_cab WHERE id_reserva=$1 FOR UPDATE", [$id]);
        if (!$sel || pg_num_rows($sel) === 0) {
          pg_query($conn, 'ROLLBACK');
          json_error('Reserva no encontrada', 404);
        }
        $estadoActual = pg_fetch_result($sel, 0, 0);
        if ($estadoActual === 'Cancelada') {
          pg_query($conn, 'ROLLBACK');
          json_ok(['already' => true, 'estado' => 'Cancelada']);
        }

        $notaExtra = null;
        if ($motivo !== '') {
          $notaExtra = '[Cancelada ' . date('Y-m-d H:i') . '] ' . $motivo;
        }

        $sql = "
          UPDATE public.reserva_cab
             SET estado='Cancelada',
                 notas = CASE
                           WHEN $2::text IS NULL THEN notas
                           ELSE concat_ws(E'\n', notas, $2::text)
                         END
           WHERE id_reserva=$1
        ";
        $ok = pg_query_params($conn, $sql, [$id, $notaExtra]);
        if (!$ok) {
          pg_query($conn, 'ROLLBACK');
          json_error('No se pudo cancelar la reserva');
        }
        pg_query($conn, 'COMMIT');
        json_ok(['estado' => 'Cancelada']);
      }

      case 'list_agenda': {
        $fecha = s($in['fecha'] ?? '');
        $desde = s($in['desde'] ?? '08:00');
        $hasta = s($in['hasta'] ?? '20:00');
        $step = (int)($in['intervalo_min'] ?? 30);
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) json_error('fecha invalida');
        if (!preg_match('/^\d{2}:\d{2}$/', $desde) || !preg_match('/^\d{2}:\d{2}$/', $hasta)) json_error('Rango horario invalido');
        if ($desde >= $hasta) json_error('Horario hasta debe ser mayor');
        if ($step < 5) $step = 5;
        if ($step > 180) $step = 180;
        $idProf = (int)($in['id_profesional'] ?? 0);

        $paramsPros = [];
        $wherePros = ["estado='Activo'"];
        if ($idProf > 0) {
          $paramsPros[] = $idProf;
          $wherePros[] = "id_profesional = $" . count($paramsPros);
        }
        $prosRes = pg_query_params($conn, "SELECT id_profesional, nombre FROM public.profesional WHERE " . implode(' AND ', $wherePros) . " ORDER BY nombre", $paramsPros);
        if (!$prosRes) json_error('No se pudieron obtener los profesionales');
        $profesionales = [];
        while ($row = pg_fetch_assoc($prosRes)) {
          $row['id_profesional'] = (int)$row['id_profesional'];
          $profesionales[] = $row;
        }
        if (empty($profesionales)) {
          json_ok([
            'profesionales' => [],
            'reservas' => [],
            'bloqueos' => [],
            'fecha' => $fecha,
            'desde' => $desde,
            'hasta' => $hasta,
            'step' => $step
          ]);
        }

        $paramsRes = [$TZ, $fecha];
        $whereRes = ["(rc.inicio_ts AT TIME ZONE $1)::date = $2::date"];
        if ($idProf > 0) {
          $paramsRes[] = $idProf;
          $whereRes[] = "rc.id_profesional = $" . count($paramsRes);
        }
        $sqlRes = "
          SELECT rc.id_reserva,
                 rc.estado,
                 rc.id_profesional,
                 to_char(rc.inicio_ts AT TIME ZONE $1, 'YYYY-MM-DD HH24:MI') AS inicio,
                 to_char(rc.fin_ts AT TIME ZONE $1, 'YYYY-MM-DD HH24:MI') AS fin,
                 c.nombre || ' ' || COALESCE(c.apellido,'') AS cliente,
                 COALESCE(p.nombre, 'Sin asignar') AS profesional
          FROM public.reserva_cab rc
          JOIN public.clientes c ON c.id_cliente = rc.id_cliente
          LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional
          WHERE " . implode(' AND ', $whereRes) . "
          ORDER BY rc.inicio_ts
        ";
        $resRes = pg_query_params($conn, $sqlRes, $paramsRes);
        if (!$resRes) json_error('No se pudo obtener la agenda');
        $reservas = [];
        while ($row = pg_fetch_assoc($resRes)) {
          $row['id_reserva'] = (int)$row['id_reserva'];
          $row['id_profesional'] = $row['id_profesional'] !== null ? (int)$row['id_profesional'] : null;
          $reservas[] = $row;
        }

        $paramsBloq = [$TZ, $fecha];
        $whereBloq = ["(b.inicio_ts AT TIME ZONE $1)::date = $2::date"];
        if ($idProf > 0) {
          $paramsBloq[] = $idProf;
          $whereBloq[] = "b.id_profesional = $" . count($paramsBloq);
        }
        $sqlBloq = "
          SELECT b.id_bloqueo,
                 b.id_profesional,
                 to_char(b.inicio_ts AT TIME ZONE $1, 'YYYY-MM-DD HH24:MI') AS inicio,
                 to_char(b.fin_ts AT TIME ZONE $1, 'YYYY-MM-DD HH24:MI') AS fin,
                 COALESCE(b.motivo, 'Bloqueo') AS motivo
          FROM public.agenda_bloqueo b
          WHERE " . implode(' AND ', $whereBloq) . "
          ORDER BY b.inicio_ts
        ";
        $bloqRes = pg_query_params($conn, $sqlBloq, $paramsBloq);
        $bloqueos = [];
        if ($bloqRes) {
          while ($row = pg_fetch_assoc($bloqRes)) {
            $row['id_bloqueo'] = (int)$row['id_bloqueo'];
            $row['id_profesional'] = (int)$row['id_profesional'];
            $bloqueos[] = $row;
          }
        }

        json_ok([
          'profesionales' => $profesionales,
          'reservas' => $reservas,
          'bloqueos' => $bloqueos,
          'fecha' => $fecha,
          'desde' => $desde,
          'hasta' => $hasta,
          'step' => $step
        ]);
      }

      default:
        json_error('Operacion no disponible');
    }
  } catch (Throwable $e) {
    json_error($e->getMessage());
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agenda de reservas</title>
  <style>
    body{margin:0;padding:20px;font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f5f7fb;color:#0f172a;}
    h1{margin:0 0 16px;font-size:26px;}
    h2{margin:0 0 8px;font-size:18px;}
    .shell{display:grid;gap:18px;}
    @media(min-width:960px){.shell{grid-template-columns:320px 1fr;}}
    .card{background:#fff;border-radius:14px;box-shadow:0 10px 26px rgba(15,23,42,0.12);padding:18px;display:flex;flex-direction:column;gap:16px;}
    .filters{display:grid;gap:12px;}
    .filters label{display:flex;flex-direction:column;font-size:13px;color:#64748b;gap:6px;}
    .filters input,.filters select{padding:8px 10px;border:1px solid #cbd5f5;border-radius:10px;font-size:14px;background:#fff;}
    .filters input:focus,.filters select:focus{outline:2px solid rgba(59,130,246,0.25);}
    .row{display:flex;gap:10px;flex-wrap:wrap;}
    button{border:none;border-radius:10px;padding:9px 14px;font-weight:600;cursor:pointer;}
    .primary{background:#2563eb;color:#fff;}
    .ghost{background:#e2e8f0;color:#1e293b;}
    .danger{background:#dc2626;color:#fff;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    th,td{padding:9px 8px;border-bottom:1px solid #e2e8f0;text-align:left;}
    thead th{text-transform:uppercase;font-size:11px;color:#475569;}
    tbody tr:hover{background:#f1f5f9;}
    .estado-pill{display:inline-block;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:600;}
    .estado-pendiente{background:rgba(59,130,246,0.15);color:#1d4ed8;}
    .estado-confirmada{background:rgba(16,185,129,0.16);color:#047857;}
    .estado-realizada{background:rgba(20,184,166,0.16);color:#0f766e;}
    .estado-cancelada{background:rgba(148,163,184,0.18);color:#475569;}
    .calendar{display:flex;flex-direction:column;gap:6px;}
    .cal-head,.cal-row{display:grid;grid-template-columns:140px 1fr;gap:10px;align-items:center;}
    .cal-head{font-size:11px;text-transform:uppercase;color:#64748b;}
    .cal-label{font-weight:600;font-size:13px;color:#1e293b;}
    .cal-track{position:relative;min-height:48px;border-radius:10px;background:rgba(226,232,240,0.6);overflow:hidden;}
    .cal-track::after{content:'';position:absolute;inset:0;border:1px solid rgba(148,163,184,0.35);border-radius:10px;pointer-events:none;}
    .cal-track{background-image:repeating-linear-gradient(to right, rgba(148,163,184,0.25) 0, rgba(148,163,184,0.25) 1px, transparent 1px, transparent calc(100%/var(--slots)));}
    .cal-event{position:absolute;top:6px;height:calc(100% - 12px);border-radius:9px;padding:5px 7px;font-size:11px;color:#fff;border:none;cursor:pointer;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .cal-event.cancelada{background:rgba(148,163,184,0.5);color:#475569;text-decoration:line-through;cursor:default;}
    .cal-event.pendiente{background:rgba(59,130,246,0.85);}
    .cal-event.confirmada{background:rgba(16,185,129,0.85);}
    .cal-event.realizada{background:rgba(20,184,166,0.85);}
    .cal-block{position:absolute;top:8px;height:calc(100% - 16px);border-radius:8px;background:rgba(148,163,184,0.45);color:#1e293b;font-size:11px;padding:4px 6px;pointer-events:none;}
    .empty{text-align:center;padding:24px 8px;font-size:13px;color:#64748b;}
    .modal{position:fixed;inset:0;background:rgba(15,23,42,0.45);display:none;align-items:center;justify-content:center;padding:20px;z-index:900;}
    .modal.open{display:flex;}
    .modal-box{background:#fff;border-radius:14px;width:min(680px,100%);max-height:90vh;overflow-y:auto;padding:22px;box-shadow:0 24px 60px rgba(15,23,42,0.25);position:relative;}
    .modal-close{position:absolute;top:14px;right:14px;background:none;border:none;font-size:22px;color:#1e293b;cursor:pointer;}
    .modal h2{margin:0 0 6px;}
    .modal-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));font-size:13px;}
    .modal-grid .label{color:#64748b;font-size:12px;}
    .modal-table{width:100%;border-collapse:collapse;margin-top:8px;font-size:13px;}
    .modal-table th,.modal-table td{border-bottom:1px solid #e2e8f0;padding:6px;}
    textarea{resize:vertical;min-height:70px;border:1px solid #cbd5f5;border-radius:10px;padding:8px;font-size:14px;}
    .toast{position:fixed;right:18px;top:18px;background:#334155;color:#fff;padding:10px 14px;border-radius:10px;font-size:13px;opacity:0;transform:translateY(-10px);transition:all .2s;z-index:950;}
    .toast.show{opacity:1;transform:translateY(0);}
  </style>
    <link rel="stylesheet" type="text/css" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/css/styles_servicios.css" />

</head>
<body>
  <div id="navbar-container"></div>
  <script src="../../servicios/navbar/navbar.js"></script>
  <h1>Agenda de reservas</h1>
  <div class="shell">
    <section class="card">
      <h2>Filtros</h2>
      <div class="filters">
        <label>Fecha desde<input type="date" id="f_desde"></label>
        <label>Fecha hasta<input type="date" id="f_hasta"></label>
        <label>Estado
          <select id="f_estado">
            <option value="Todos">Todos</option>
            <option value="Pendiente">Pendiente</option>
            <option value="Confirmada">Confirmada</option>
            <option value="Realizada">Realizada</option>
            <option value="Cancelada">Cancelada</option>
          </select>
        </label>
        <label>Profesional
          <select id="f_prof"></select>
        </label>
        <label>Buscar<input type="text" id="f_q" placeholder="Cliente o numero"></label>
        <label>Hora desde<input type="time" id="f_hora_desde" value="08:00"></label>
        <label>Hora hasta<input type="time" id="f_hora_hasta" value="20:00"></label>
        <label>Intervalo (min)<input type="number" id="f_step" value="30" min="5" max="180"></label>
      </div>
      <div class="row">
        <button class="primary" id="btnBuscar">Buscar</button>
        <button class="ghost" id="btnLimpiar">Limpiar</button>
      </div>
    </section>
    <section class="card" style="min-height:420px;">
      <div>
        <h2>Calendario</h2>
        <div id="calendar" class="calendar"></div>
      </div>
      <div>
        <h2>Reservas</h2>
        <div style="overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Profesional</th>
                <th>Inicio</th>
                <th>Fin</th>
                <th>Estado</th>
                <th style="text-align:right;">Acciones</th>
              </tr>
            </thead>
            <tbody id="tbody-reservas">
              <tr><td colspan="7" class="empty">Sin datos</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
  <div class="modal" id="modal">
    <div class="modal-box">
      <button class="modal-close" type="button" id="modal-close">&times;</button>
      <h2 id="modal-title">Reserva</h2>
      <div class="modal-grid" style="margin-bottom:12px;">
        <div><div class="label">Cliente</div><div id="m-cliente"></div></div>
        <div><div class="label">Profesional</div><div id="m-prof"></div></div>
        <div><div class="label">Horario</div><div id="m-horario"></div></div>
        <div><div class="label">Estado</div><div id="m-estado"></div></div>
      </div>
      <div>
        <div class="label">Servicios</div>
        <table class="modal-table" id="m-items">
          <thead>
            <tr><th>#</th><th>Producto</th><th>Cant</th><th>Duracion</th><th>Precio</th><th>IVA</th></tr>
          </thead>
          <tbody><tr><td colspan="6" class="empty">Sin servicios</td></tr></tbody>
        </table>
      </div>
      <div style="margin-top:12px;">
        <div class="label">Notas</div>
        <div id="m-notas" style="white-space:pre-wrap;font-size:13px;margin-top:4px;"></div>
      </div>
      <div style="margin-top:16px;">
        <div class="label">Motivo cancelacion (opcional)</div>
        <textarea id="m-motivo" placeholder="Escribi el motivo"></textarea>
      </div>
      <div class="row" style="margin-top:14px;">
        <button class="danger" id="m-btn-cancelar">Cancelar reserva</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>
  <script>
    const $ = (sel) => document.querySelector(sel);
    const state = { currentReserva: null };

    function showToast(msg, tone = 'info') {
      const el = $('#toast');
      if (!el) return;
      el.textContent = msg;
      el.style.background = tone === 'error' ? '#b91c1c' : tone === 'success' ? '#047857' : '#334155';
      el.classList.add('show');
      setTimeout(() => el.classList.remove('show'), 2200);
    }

    async function api(op, payload = {}) {
      const res = await fetch('agenda_reservas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ op, ...payload })
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Error');
      return data;
    }

    function estadoClase(estado) {
      return 'estado-pill estado-' + (estado ? estado.toLowerCase() : 'pendiente');
    }

    function diffMinutes(start, end) {
      const [sh, sm] = start.split(':').map(Number);
      const [eh, em] = end.split(':').map(Number);
      return eh * 60 + em - (sh * 60 + sm);
    }

    function renderCalendar(data) {
      const wrap = $('#calendar');
      if (!wrap) return;
      wrap.innerHTML = '';
      if (!data || !data.profesionales || data.profesionales.length === 0) {
        wrap.innerHTML = '<div class="empty">Sin profesionales activos.</div>';
        return;
      }
      const totalMin = diffMinutes(data.desde, data.hasta);
      if (totalMin <= 0) {
        wrap.innerHTML = '<div class="empty">Rango horario invalido.</div>';
        return;
      }
      const slots = Math.max(1, Math.round(totalMin / data.step));
      const head = document.createElement('div');
      head.className = 'cal-head';
      head.innerHTML = '<div></div><div style="display:flex;justify-content:space-between;"><span>' + data.desde + '</span><span>' + data.hasta + '</span></div>';
      wrap.appendChild(head);

      data.profesionales.forEach((prof) => {
        const row = document.createElement('div');
        row.className = 'cal-row';
        const label = document.createElement('div');
        label.className = 'cal-label';
        label.textContent = prof.nombre;
        row.appendChild(label);
        const track = document.createElement('div');
        track.className = 'cal-track';
        track.style.setProperty('--slots', slots);

        data.reservas
          .filter(r => Number(r.id_profesional) === Number(prof.id_profesional))
          .forEach(r => {
            const inicio = r.inicio.slice(11, 16);
            const fin = r.fin.slice(11, 16);
            const start = Math.max(0, diffMinutes(data.desde, inicio));
            const end = Math.min(totalMin, diffMinutes(data.desde, fin));
            if (end <= 0 || start >= totalMin) return;
            const width = Math.max(4, ((end - start) / totalMin) * 100);
            const left = Math.max(0, (start / totalMin) * 100);
            const btn = document.createElement('button');
            btn.className = 'cal-event ' + (r.estado ? r.estado.toLowerCase() : 'pendiente');
            btn.style.left = left + '%';
            btn.style.width = width + '%';
            btn.textContent = inicio + ' ' + r.cliente;
            btn.title = r.cliente + ' - ' + r.estado + '\n' + inicio + ' a ' + fin;
            if (r.estado !== 'Cancelada') {
              btn.addEventListener('click', () => openReserva(r.id_reserva));
            } else {
              btn.classList.add('cancelada');
            }
            track.appendChild(btn);
          });

        data.bloqueos
          .filter(b => Number(b.id_profesional) === Number(prof.id_profesional))
          .forEach(b => {
            const inicio = b.inicio.slice(11, 16);
            const fin = b.fin.slice(11, 16);
            const start = Math.max(0, diffMinutes(data.desde, inicio));
            const end = Math.min(totalMin, diffMinutes(data.desde, fin));
            if (end <= 0 || start >= totalMin) return;
            const width = Math.max(4, ((end - start) / totalMin) * 100);
            const left = Math.max(0, (start / totalMin) * 100);
            const div = document.createElement('div');
            div.className = 'cal-block';
            div.style.left = left + '%';
            div.style.width = width + '%';
            div.textContent = b.motivo;
            track.appendChild(div);
          });

        row.appendChild(track);
        wrap.appendChild(row);
      });
    }

    function renderTabla(rows) {
      const tbody = $('#tbody-reservas');
      if (!tbody) return;
      tbody.innerHTML = '';
      if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty">Sin reservas para los filtros.</td></tr>';
        return;
      }
      rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${row.id_reserva}</td>
          <td>${row.cliente}</td>
          <td>${row.profesional}</td>
          <td>${row.inicio_local.slice(11, 16)}</td>
          <td>${row.fin_local.slice(11, 16)}</td>
          <td><span class="${estadoClase(row.estado)}">${row.estado}</span></td>
          <td style="text-align:right;display:flex;gap:6px;justify-content:flex-end;">
            <button class="ghost" data-accion="ver" data-id="${row.id_reserva}">Ver</button>
            <button class="danger" data-accion="cancelar" data-id="${row.id_reserva}" ${row.estado === 'Cancelada' ? 'disabled' : ''}>Cancelar</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    async function cargarProfesionales() {
      const sel = $('#f_prof');
      if (!sel) return;
      sel.innerHTML = '<option value="0">Todos</option>';
      try {
        const data = await api('list_profesionales');
        data.rows.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id_profesional;
          opt.textContent = p.nombre;
          sel.appendChild(opt);
        });
      } catch (err) {
        showToast(err.message, 'error');
      }
    }

    function filtros() {
      return {
        fecha_desde: $('#f_desde')?.value || '',
        fecha_hasta: $('#f_hasta')?.value || '',
        estado: $('#f_estado')?.value || 'Todos',
        id_profesional: Number($('#f_prof')?.value || 0),
        q: $('#f_q')?.value || '',
        desde: $('#f_hora_desde')?.value || '08:00',
        hasta: $('#f_hora_hasta')?.value || '20:00',
        intervalo_min: Number($('#f_step')?.value || 30)
      };
    }

    async function recargar() {
      const f = filtros();
      if (!f.fecha_desde) {
        showToast('Selecciona una fecha.', 'error');
        return;
      }
      try {
        const [tabla, agenda] = await Promise.all([
          api('list_reservas', {
            fecha_desde: f.fecha_desde,
            fecha_hasta: f.fecha_hasta || f.fecha_desde,
            estado: f.estado,
            id_profesional: f.id_profesional,
            q: f.q
          }),
          api('list_agenda', {
            fecha: f.fecha_desde,
            desde: f.desde,
            hasta: f.hasta,
            intervalo_min: f.intervalo_min,
            id_profesional: f.id_profesional
          })
        ]);
        renderTabla(tabla.rows);
        renderCalendar(agenda);
      } catch (err) {
        showToast(err.message, 'error');
      }
    }

    function limpiar() {
      const hoy = new Date().toISOString().slice(0, 10);
      if ($('#f_desde')) $('#f_desde').value = hoy;
      if ($('#f_hasta')) $('#f_hasta').value = hoy;
      if ($('#f_estado')) $('#f_estado').value = 'Todos';
      if ($('#f_prof')) $('#f_prof').value = '0';
      if ($('#f_q')) $('#f_q').value = '';
      if ($('#f_hora_desde')) $('#f_hora_desde').value = '08:00';
      if ($('#f_hora_hasta')) $('#f_hora_hasta').value = '20:00';
      if ($('#f_step')) $('#f_step').value = 30;
    }

    function closeModal() {
      $('#modal')?.classList.remove('open');
      state.currentReserva = null;
      if ($('#m-motivo')) $('#m-motivo').value = '';
    }

    async function openReserva(id) {
      try {
        const data = await api('get_reserva', { id_reserva: id });
        state.currentReserva = id;
        $('#modal-title').textContent = 'Reserva #' + data.cab.id_reserva;
        $('#m-cliente').textContent = (data.cab.nombre || '') + ' ' + (data.cab.apellido || '');
        $('#m-prof').textContent = data.cab.profesional || 'Sin asignar';
        $('#m-horario').textContent = data.cab.inicio_local + ' a ' + data.cab.fin_local;
        $('#m-estado').innerHTML = '<span class="' + estadoClase(data.cab.estado) + '">' + data.cab.estado + '</span>';
        $('#m-notas').textContent = data.cab.notas || 'Sin notas.';
        const tbody = $('#m-items tbody');
        tbody.innerHTML = '';
        if (!data.items || data.items.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" class="empty">Sin servicios</td></tr>';
        } else {
          data.items.forEach(it => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${it.item_nro}</td>
              <td>${it.descripcion}</td>
              <td>${it.cantidad}</td>
              <td>${it.duracion_min} min</td>
              <td>${Number(it.precio_unitario).toLocaleString()}</td>
              <td>${it.tipo_iva}</td>
            `;
            tbody.appendChild(tr);
          });
        }
        const btn = $('#m-btn-cancelar');
        if (btn) btn.disabled = data.cab.estado === 'Cancelada';
        $('#modal').classList.add('open');
      } catch (err) {
        showToast(err.message, 'error');
      }
    }

    async function cancelarReserva() {
      if (!state.currentReserva) return;
      if (!confirm('Confirmas que queres cancelar la reserva?')) return;
      const motivo = $('#m-motivo')?.value || '';
      try {
        await api('cancel_reserva', { id_reserva: state.currentReserva, motivo });
        showToast('Reserva cancelada.', 'success');
        closeModal();
        await recargar();
      } catch (err) {
        showToast(err.message, 'error');
      }
    }

    function initEvents() {
      $('#btnBuscar')?.addEventListener('click', recargar);
      $('#btnLimpiar')?.addEventListener('click', () => { limpiar(); recargar(); });
      $('#tbody-reservas')?.addEventListener('click', (ev) => {
        const btn = ev.target.closest('button[data-accion]');
        if (!btn) return;
        const id = Number(btn.dataset.id);
        if (!id) return;
        if (btn.dataset.accion === 'ver') {
          openReserva(id);
        } else if (btn.dataset.accion === 'cancelar') {
          openReserva(id).then(() => {
            const cancelBtn = $('#m-btn-cancelar');
            if (cancelBtn) cancelBtn.focus();
          });
        }
      });
      $('#modal-close')?.addEventListener('click', closeModal);
      $('#m-btn-cancelar')?.addEventListener('click', cancelarReserva);
      document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') closeModal();
      });
    }

    window.addEventListener('DOMContentLoaded', async () => {
      const hoy = new Date().toISOString().slice(0, 10);
      if ($('#f_desde')) $('#f_desde').value = hoy;
      if ($('#f_hasta')) $('#f_hasta').value = hoy;
      await cargarProfesionales();
      initEvents();
      recargar();
    });
  </script>
</body>
</html>
