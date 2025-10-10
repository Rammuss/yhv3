<?php
// ui_libro_ventas.php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}

require_once __DIR__ . '../../../../conexion/configv2.php';
header_remove('X-Powered-By');

function respond($payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['action'] ?? '') === 'consulta') {
    $desde     = $_GET['desde'] ?? '';
    $hasta     = $_GET['hasta'] ?? '';
    $docTipo   = $_GET['doc_tipo'] ?? '';
    $condicion = $_GET['condicion'] ?? '';
    $estado    = $_GET['estado'] ?? '';

    if (!$desde || !$hasta) {
        respond(['ok' => false, 'error' => 'Debe seleccionar el rango de fechas.'], 400);
    }

    $whereParts = ['lv.fecha_emision BETWEEN $1 AND $2'];
    $params = [$desde, $hasta];
    $idx = 3;

    foreach (['doc_tipo' => $docTipo, 'condicion_venta' => $condicion, 'estado_doc' => $estado] as $field => $value) {
        if ($value !== '') {
            $whereParts[] = "lv.{$field} = $" . $idx;
            $params[] = $value;
            $idx++;
        }
    }
    $where = implode(' AND ', $whereParts);

    $sqlSummary = "
        SELECT
            COUNT(*)::int                                AS registros,
            COALESCE(SUM(lv.grav10),0)::numeric(14,2)    AS grav10,
            COALESCE(SUM(lv.iva10),0)::numeric(14,2)     AS iva10,
            COALESCE(SUM(lv.grav5),0)::numeric(14,2)     AS grav5,
            COALESCE(SUM(lv.iva5),0)::numeric(14,2)      AS iva5,
            COALESCE(SUM(lv.exentas),0)::numeric(14,2)   AS exentas,
            COALESCE(SUM(lv.total),0)::numeric(14,2)     AS total
        FROM public.libro_ventas_new lv
        WHERE {$where}
    ";
    $resSummary = pg_query_params($conn, $sqlSummary, $params);
    if (!$resSummary) respond(['ok'=>false,'error'=>pg_last_error($conn)],500);
    $summary = pg_fetch_assoc($resSummary) ?: [
        'registros'=>0,'grav10'=>0,'iva10'=>0,'grav5'=>0,'iva5'=>0,'exentas'=>0,'total'=>0
    ];

    $sqlDocBreak = "
        SELECT doc_tipo, COUNT(*)::int AS cantidad,
               COALESCE(SUM(total),0)::numeric(14,2) AS total_doc
        FROM public.libro_ventas_new lv
        WHERE {$where}
        GROUP BY doc_tipo
        ORDER BY doc_tipo
    ";
    $resDoc = pg_query_params($conn, $sqlDocBreak, $params);
    if (!$resDoc) respond(['ok'=>false,'error'=>pg_last_error($conn)],500);
    $docBreak = pg_fetch_all($resDoc) ?: [];

    $sqlCondBreak = "
        SELECT condicion_venta, COUNT(*)::int AS cantidad,
               COALESCE(SUM(total),0)::numeric(14,2) AS total_cond
        FROM public.libro_ventas_new lv
        WHERE {$where}
        GROUP BY condicion_venta
        ORDER BY condicion_venta
    ";
    $resCond = pg_query_params($conn, $sqlCondBreak, $params);
    if (!$resCond) respond(['ok'=>false,'error'=>pg_last_error($conn)],500);
    $condBreak = pg_fetch_all($resCond) ?: [];

    $sqlRows = "
        SELECT
            lv.id_libro,
            lv.fecha_emision,
            lv.doc_tipo,
            lv.numero_documento,
            lv.timbrado_numero,
            lv.condicion_venta,
            lv.estado_doc,
            lv.grav10, lv.iva10, lv.grav5, lv.iva5, lv.exentas, lv.total,
            CONCAT(TRIM(c.nombre),' ',TRIM(c.apellido)) AS cliente
        FROM public.libro_ventas_new lv
        JOIN public.clientes c ON c.id_cliente = lv.id_cliente
        WHERE {$where}
        ORDER BY lv.fecha_emision DESC, lv.numero_documento DESC
        LIMIT 500
    ";
    $resRows = pg_query_params($conn, $sqlRows, $params);
    if (!$resRows) respond(['ok'=>false,'error'=>pg_last_error($conn)],500);
    $rows = pg_fetch_all($resRows) ?: [];

    respond([
        'ok' => true,
        'summary' => $summary,
        'doc_break'=> $docBreak,
        'cond_break'=> $condBreak,
        'rows' => $rows
    ]);
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Libro de Ventas · Reporte</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --bg: linear-gradient(135deg, #eef2f7 0%, #f4f7fb 40%, #fbfdfc 100%);
        --card: rgba(255, 255, 255, 0.92);
        --border: rgba(108, 122, 137, 0.2);
        --border-strong: rgba(108, 122, 137, 0.32);
        --shadow: 0 28px 52px rgba(45, 76, 125, 0.16);
        --text: #233142;
        --muted: #6c7a89;
        --accent: #3f72af;
        --accent-alt: #5fa8d3;
        --radius: 22px;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 32px 20px 40px;
        min-height: 100vh;
        font-family: "Poppins", system-ui, -apple-system, Segoe UI, sans-serif;
        background: var(--bg);
        color: var(--text);
        position: relative;
    }
    body::before,
    body::after {
        content: "";
        position: fixed;
        width: 420px;
        height: 420px;
        border-radius: 50%;
        filter: blur(150px);
        opacity: 0.4;
        z-index: 0;
    }
    body::before {
        top: -160px;
        left: -160px;
        background: rgba(95, 168, 211, 0.28);
    }
    body::after {
        bottom: -210px;
        right: -140px;
        background: rgba(111, 205, 190, 0.24);
    }
    .app {
        max-width: 1220px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
        display: grid;
        gap: 22px;
    }
    header.top {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        justify-content: space-between;
        align-items: center;
        background: var(--card);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        padding: 22px 26px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(12px);
    }
    .top-info {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .top-info h1 {
        margin: 0;
        font-family: "Playfair Display", "Poppins", serif;
        font-size: 2.1rem;
    }
    .top-info span { color: var(--muted); font-size: 0.95rem; }
    .filters {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }
    .filters label {
        display: flex;
        flex-direction: column;
        font-size: 0.85rem;
        color: var(--muted);
    }
    .filters input,
    .filters select {
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid var(--border);
        min-width: 150px;
        font-size: 0.95rem;
        background: rgba(255,255,255,0.94);
        box-shadow: inset 0 0 0 1px rgba(63,114,175,0.08);
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .filters input:focus,
    .filters select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 4px rgba(63,114,175,0.18);
    }
    .btn {
        padding: 12px 18px;
        border-radius: 999px;
        border: none;
        font-weight: 600;
        background: linear-gradient(135deg, var(--accent), var(--accent-alt));
        color: #fff;
        cursor: pointer;
        box-shadow: 0 16px 32px rgba(63,114,175,0.22);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .btn:hover { transform: translateY(-1px); box-shadow: 0 22px 40px rgba(63,114,175,0.26); }
    .btn:active { transform: translateY(0); box-shadow: 0 16px 30px rgba(63,114,175,0.24); }

    .cards { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); }
    .card {
        background: var(--card);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        padding: 18px 20px;
        box-shadow: var(--shadow);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .card h3 { margin:0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.8px; color: var(--muted); }
    .card strong { font-size: 1.6rem; font-weight: 600; }
    .card span { font-size: 0.85rem; color: var(--muted); }
    .card.small strong { font-size: 1.2rem; }

    .grid-panels { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }
    .panel {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px 22px;
        box-shadow: var(--shadow);
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .panel h2 {
        margin:0;
        font-family: "Playfair Display", "Poppins", serif;
        font-size: 1.5rem;
    }
    .panel table {
        width:100%; border-collapse:collapse; border-radius:18px; overflow:hidden;
    }
    .panel th, .panel td {
        padding: 10px 12px;
        border-bottom: 1px solid rgba(63,114,175,0.12);
        font-size: 0.94rem;
    }
    .panel th {
        text-align:left;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--muted);
        background: rgba(63,114,175,0.16);
    }
    .panel tr:last-child td { border-bottom: none; }
    .panel tbody tr:hover td { background: rgba(63,114,175,0.08); }
    .badge {
        display: inline-flex;
        align-items:center;
        gap:6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 500;
        background: rgba(63,114,175,0.12);
        color: var(--accent);
    }
    .badge.secondary {
        background: rgba(111,205,190,0.18);
        color: #2c7a68;
    }
    .muted { color: var(--muted); }
    .right { text-align:right; }

    .loading {
        display:flex; gap:10px; align-items:center;
        color: var(--muted);
    }
    .loading span {
        width:12px; height:12px; border-radius:50%; background: var(--accent);
        animation: pulse .9s infinite alternate;
    }
    .loading span:nth-child(2){ animation-delay: .15s; }
    .loading span:nth-child(3){ animation-delay: .3s; }
    @keyframes pulse { to { transform: translateY(-3px); opacity: 0.6; } }

    @media (max-width: 720px) {
        body { padding: 22px 12px 30px; }
        .filters label { width: 100%; }
        .filters input, .filters select, .btn { width: 100%; }
    }
</style>
</head>
<body>
<div class="app">
    <header class="top">
        <div class="top-info">
            <h1>Libro de Ventas</h1>
            <span>Visualización de comprobantes con filtros por fecha, tipo y estado.</span>
        </div>
        <form class="filters" id="formFiltros">
            <label>Desde
                <input type="date" id="fDesde" name="desde" required>
            </label>
            <label>Hasta
                <input type="date" id="fHasta" name="hasta" required>
            </label>
            <label>Documento
                <select id="fDocTipo" name="doc_tipo">
                    <option value="">Todos</option>
                    <option value="FACT">Factura</option>
                    <option value="NC">Nota de Crédito</option>
                    <option value="ND">Nota de Débito</option>
                </select>
            </label>
            <label>Condición
                <select id="fCondicion" name="condicion">
                    <option value="">Todas</option>
                    <option value="Contado">Contado</option>
                    <option value="Credito">Crédito</option>
                </select>
            </label>
            <label>Estado
                <select id="fEstado" name="estado">
                    <option value="">Todos</option>
                    <option value="Emitida">Emitida</option>
                    <option value="Anulada">Anulada</option>
                    <option value="Cancelada">Cancelada</option>
                </select>
            </label>
            <button type="submit" class="btn">Aplicar filtros</button>
        </form>
    </header>

    <section class="cards" id="cardsResumen"></section>

    <section class="grid-panels">
        <div class="panel">
            <h2>Resumen por documento</h2>
            <div class="muted" id="docBreakEmpty" style="display:none;">Sin resultados.</div>
            <table id="tableDocBreak" style="display:none;">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="panel">
            <h2>Condición de venta</h2>
            <div class="muted" id="condBreakEmpty" style="display:none;">Sin resultados.</div>
            <table id="tableCondBreak" style="display:none;">
                <thead>
                    <tr>
                        <th>Condición</th>
                        <th>Cantidad</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Detalle</h2>
        <div class="muted" id="rowsEmpty" style="display:none;">No se encontraron registros con los filtros aplicados.</div>
        <div class="loading" id="rowsLoading" style="display:none;">
            <span></span><span></span><span></span> Cargando...
        </div>
        <table id="tableRows" style="display:none;">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Documento</th>
                    <th>Timbrado</th>
                    <th>Cliente</th>
                    <th>Condición</th>
                    <th>Estado</th>
                    <th class="right">Grav.10</th>
                    <th class="right">Grav.5</th>
                    <th class="right">Exentas</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </section>
</div>

<script>
const currency = new Intl.NumberFormat('es-PY', {
    style: 'currency',
    currency: 'PYG',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
});
const numberFmt = new Intl.NumberFormat('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

function setDefaults() {
    const today = new Date();
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('fDesde').value = first.toISOString().substring(0,10);
    document.getElementById('fHasta').value = today.toISOString().substring(0,10);
}

async function fetchData() {
    const form = document.getElementById('formFiltros');
    const params = new URLSearchParams(new FormData(form)).toString();
    const url = `?action=consulta&${params}`;

    document.getElementById('rowsLoading').style.display = 'flex';
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (!data.ok) {
            alert(data.error || 'Error al obtener datos.');
            return;
        }
        renderSummary(data.summary);
        renderDocBreak(data.doc_break);
        renderCondBreak(data.cond_break);
        renderRows(data.rows);
    } catch (err) {
        console.error(err);
        alert('No se pudo obtener el reporte.');
    } finally {
        document.getElementById('rowsLoading').style.display = 'none';
    }
}

function renderSummary(summary) {
    const cards = document.getElementById('cardsResumen');
    const registros = Number(summary.registros || 0);
    const grav10 = Number(summary.grav10 || 0);
    const iva10 = Number(summary.iva10 || 0);
    const grav5 = Number(summary.grav5 || 0);
    const iva5 = Number(summary.iva5 || 0);
    const exentas = Number(summary.exentas || 0);
    const total = Number(summary.total || 0);
    const ivaTotal = iva10 + iva5;

    cards.innerHTML = `
        <div class="card">
            <h3>Comprobantes</h3>
            <strong>${numberFmt.format(registros)}</strong>
            <span>Registros encontrados</span>
        </div>
        <div class="card">
            <h3>Total</h3>
            <strong>${currency.format(total)}</strong>
            <span>Importe total</span>
        </div>
        <div class="card">
            <h3>Gravado 10%</h3>
            <strong>${currency.format(grav10)}</strong>
            <span>IVA 10%: ${currency.format(iva10)}</span>
        </div>
        <div class="card">
            <h3>Gravado 5%</h3>
            <strong>${currency.format(grav5)}</strong>
            <span>IVA 5%: ${currency.format(iva5)}</span>
        </div>
        <div class="card small">
            <h3>IVA total</h3>
            <strong>${currency.format(ivaTotal)}</strong>
        </div>
        <div class="card small">
            <h3>Exentas</h3>
            <strong>${currency.format(exentas)}</strong>
        </div>
    `;
}

function renderDocBreak(items) {
    const table = document.getElementById('tableDocBreak');
    const tbody = table.querySelector('tbody');
    const empty = document.getElementById('docBreakEmpty');

    if (!items || !items.length) {
        table.style.display = 'none';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    table.style.display = 'table';
    tbody.innerHTML = items.map(row => `
        <tr>
            <td>${row.doc_tipo}</td>
            <td>${numberFmt.format(row.cantidad)}</td>
            <td class="right">${currency.format(Number(row.total_doc || 0))}</td>
        </tr>
    `).join('');
}

function renderCondBreak(items) {
    const table = document.getElementById('tableCondBreak');
    const tbody = table.querySelector('tbody');
    const empty = document.getElementById('condBreakEmpty');

    if (!items || !items.length) {
        table.style.display = 'none';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    table.style.display = 'table';
    tbody.innerHTML = items.map(row => `
        <tr>
            <td>${row.condicion_venta}</td>
            <td>${numberFmt.format(row.cantidad)}</td>
            <td class="right">${currency.format(Number(row.total_cond || 0))}</td>
        </tr>
    `).join('');
}

function renderRows(rows) {
    const table = document.getElementById('tableRows');
    const tbody = table.querySelector('tbody');
    const empty = document.getElementById('rowsEmpty');

    if (!rows || !rows.length) {
        table.style.display = 'none';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    table.style.display = 'table';
    tbody.innerHTML = rows.map(row => `
        <tr>
            <td>${row.fecha_emision}</td>
            <td>${row.doc_tipo} ${row.numero_documento}</td>
            <td>${row.timbrado_numero}</td>
            <td>${row.cliente}</td>
            <td><span class="badge">${row.condicion_venta}</span></td>
            <td><span class="badge secondary">${row.estado_doc}</span></td>
            <td class="right">${currency.format(Number(row.grav10 || 0))}</td>
            <td class="right">${currency.format(Number(row.grav5 || 0))}</td>
            <td class="right">${currency.format(Number(row.exentas || 0))}</td>
            <td class="right">${currency.format(Number(row.total || 0))}</td>
        </tr>
    `).join('');
}

document.getElementById('formFiltros').addEventListener('submit', (e) => {
    e.preventDefault();
    fetchData();
});

setDefaults();
fetchData();
</script>
</body>
</html>
