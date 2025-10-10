<?php
// ui_reporte_facturacion.php
// Reporte interactivo de facturación (cabecera + detalle)

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

if (isset($_GET['action']) && $_GET['action'] === 'consulta') {
    $fechaDesde = $_GET['desde'] ?? '';
    $fechaHasta = $_GET['hasta'] ?? '';
    $condicion  = $_GET['condicion'] ?? '';
    $estado     = $_GET['estado'] ?? '';

    if (!$fechaDesde || !$fechaHasta) {
        respond(['ok' => false, 'error' => 'Debés indicar rango de fechas.'], 400);
    }

    $whereParts = ['f.fecha_emision BETWEEN $1 AND $2'];
    $params = [$fechaDesde, $fechaHasta];
    $idx = 3;

    if ($condicion !== '') {
        $whereParts[] = "f.condicion_venta = $" . $idx;
        $params[] = $condicion;
        $idx++;
    }
    if ($estado !== '') {
        $whereParts[] = "f.estado = $" . $idx;
        $params[] = $estado;
        $idx++;
    }

    $where = implode(' AND ', $whereParts);

    $sqlSummary = "
        SELECT
            COUNT(*)::int                       AS total_facturas,
            COALESCE(SUM(f.total_bruto),0)::numeric(14,2)    AS total_bruto,
            COALESCE(SUM(f.total_descuento),0)::numeric(14,2) AS total_descuento,
            COALESCE(SUM(f.total_neto),0)::numeric(14,2)     AS total_neto,
            COALESCE(SUM(f.total_iva),0)::numeric(14,2)      AS total_iva,
            COALESCE(SUM(f.total_exentas),0)::numeric(14,2)  AS total_exentas,
            COALESCE(SUM(f.total_grav10),0)::numeric(14,2)   AS total_grav10,
            COALESCE(SUM(f.total_iva10),0)::numeric(14,2)    AS total_iva10,
            COALESCE(SUM(f.total_grav5),0)::numeric(14,2)    AS total_grav5,
            COALESCE(SUM(f.total_iva5),0)::numeric(14,2)     AS total_iva5
        FROM public.factura_venta_cab f
        WHERE {$where}
    ";
    $resSummary = pg_query_params($conn, $sqlSummary, $params);
    if (!$resSummary) {
        respond(['ok' => false, 'error' => pg_last_error($conn)], 500);
    }
    $summary = pg_fetch_assoc($resSummary);
    $summary = $summary ?: [
        'total_facturas' => 0,
        'total_bruto' => 0,
        'total_descuento' => 0,
        'total_neto' => 0,
        'total_iva' => 0,
        'total_exentas' => 0,
        'total_grav10' => 0,
        'total_iva10' => 0,
        'total_grav5' => 0,
        'total_iva5' => 0,
    ];

    $sqlTopClients = "
        SELECT
            CONCAT(TRIM(c.nombre),' ',TRIM(c.apellido)) AS cliente,
            COUNT(*)::int                               AS facturas,
            COALESCE(SUM(f.total_neto),0)::numeric(14,2) AS total_neto
        FROM public.factura_venta_cab f
        JOIN public.clientes c ON c.id_cliente = f.id_cliente
        WHERE {$where}
        GROUP BY cliente
        ORDER BY total_neto DESC
        LIMIT 5
    ";
    $resTop = pg_query_params($conn, $sqlTopClients, $params);
    if (!$resTop) {
        respond(['ok' => false, 'error' => pg_last_error($conn)], 500);
    }
    $topClients = pg_fetch_all($resTop) ?: [];

    $sqlInvoices = "
        SELECT
            f.id_factura,
            f.fecha_emision,
            f.numero_documento,
            CONCAT(TRIM(c.nombre),' ',TRIM(c.apellido)) AS cliente,
            f.condicion_venta,
            f.estado,
            f.total_neto,
            f.total_iva,
            f.total_exentas
        FROM public.factura_venta_cab f
        JOIN public.clientes c ON c.id_cliente = f.id_cliente
        WHERE {$where}
        ORDER BY f.fecha_emision DESC, f.numero_documento DESC
        LIMIT 500
    ";
    $resInvoices = pg_query_params($conn, $sqlInvoices, $params);
    if (!$resInvoices) {
        respond(['ok' => false, 'error' => pg_last_error($conn)], 500);
    }
    $invoices = pg_fetch_all($resInvoices) ?: [];

    respond([
        'ok' => true,
        'summary' => $summary,
        'top_clients' => $topClients,
        'invoices' => $invoices,
    ]);
}

// Vista UI
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Reporte de Facturación</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --bg: linear-gradient(140deg, #edf9ff 0%, #f2f6ff 35%, #fefafc 100%);
        --card: rgba(255, 255, 255, 0.9);
        --border: rgba(63, 198, 216, 0.18);
        --border-strong: rgba(63, 198, 216, 0.3);
        --shadow: 0 28px 48px rgba(23, 136, 166, 0.22);
        --text: #1f3d47;
        --muted: #6b8c96;
        --accent: #23a6bf;
        --accent-alt: #3fc6d8;
        --radius: 22px;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        font-family: "Poppins", system-ui, -apple-system, Segoe UI, sans-serif;
        background: var(--bg);
        color: var(--text);
        padding: 30px 20px 40px;
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
        opacity: 0.55;
        z-index: 0;
    }
    body::before {
        top: -160px;
        left: -140px;
        background: rgba(63, 198, 216, 0.28);
    }
    body::after {
        bottom: -200px;
        right: -140px;
        background: rgba(139, 224, 192, 0.24);
    }
    .app {
        position: relative;
        z-index: 1;
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        gap: 22px;
    }
    header.top {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: center;
        justify-content: space-between;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 22px 26px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(12px);
    }
    .brand {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .brand__badge {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 3px;
        font-weight: 600;
        color: var(--accent);
    }
    .brand__badge::before,
    .brand__badge::after {
        content: "";
        width: 18px;
        height: 1px;
        background: currentColor;
        opacity: 0.6;
    }
    .brand h1 {
        margin: 0;
        font-family: "Playfair Display", "Poppins", serif;
        font-size: 2.2rem;
    }
    .filters {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
    }
    .filters label {
        display: flex;
        flex-direction: column;
        font-size: 0.85rem;
        color: var(--muted);
    }
    .filters input,
    .filters select {
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid var(--border-strong);
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 0.95rem;
        min-width: 160px;
        box-shadow: inset 0 0 0 1px rgba(63, 198, 216, 0.08);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .filters input:focus,
    .filters select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 4px rgba(63, 198, 216, 0.18);
    }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        border-radius: 999px;
        border: none;
        background: linear-gradient(135deg, var(--accent), var(--accent-alt));
        color: #fff;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 16px 32px rgba(63, 198, 216, 0.24);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .btn:hover { transform: translateY(-1px); box-shadow: 0 22px 40px rgba(63, 198, 216, 0.26); }
    .btn:active { transform: translateY(0); box-shadow: 0 14px 26px rgba(63, 198, 216, 0.22); }

    .cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }
    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px 20px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(12px);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .card h3 {
        margin: 0;
        font-size: 0.92rem;
        text-transform: uppercase;
        color: var(--muted);
        letter-spacing: 0.8px;
    }
    .card strong {
        font-size: 1.6rem;
        font-weight: 600;
    }
    .card span {
        font-size: 0.85rem;
        color: var(--muted);
    }
    .card.small strong {
        font-size: 1.2rem;
    }

    .grid-double {
        display: grid;
        gap: 18px;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    }
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
        margin: 0;
        font-family: "Playfair Display", "Poppins", serif;
        font-size: 1.5rem;
    }
    .panel table {
        width: 100%;
        border-collapse: collapse;
        border-radius: 18px;
        overflow: hidden;
    }
    .panel table th,
    .panel table td {
        padding: 10px 12px;
        border-bottom: 1px solid rgba(63, 198, 216, 0.14);
        font-size: 0.94rem;
    }
    .panel table th {
        text-align: left;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--muted);
        background: rgba(63, 198, 216, 0.12);
    }
    .panel table tr:last-child td { border-bottom: none; }
    .panel table tbody tr:hover td { background: rgba(63, 198, 216, 0.08); }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 500;
        background: rgba(63, 198, 216, 0.12);
        color: var(--accent);
    }
    .badge.secondary {
        background: rgba(139, 224, 192, 0.18);
        color: #227458;
    }
    .muted { color: var(--muted); }
    .right { text-align: right; }

    .loading {
        display: flex;
        gap: 10px;
        align-items: center;
        color: var(--muted);
        font-size: 0.95rem;
    }
    .loading span {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--accent);
        animation: pulse 0.9s infinite alternate;
    }
    .loading span:nth-child(2) { animation-delay: 0.15s; }
    .loading span:nth-child(3) { animation-delay: 0.3s; }
    @keyframes pulse { to { transform: translateY(-3px); opacity: 0.6; } }

    @media (max-width: 720px) {
        body { padding: 20px 12px 30px; }
        .filters label { width: 100%; }
        .filters input, .filters select { min-width: 100%; }
        .btn { width: 100%; justify-content: center; }
    }
</style>
</head>
<body>
<div class="app">
    <header class="top">
        <div class="brand">
            <span class="brand__badge">Informes </span>
            <h1>Reporte de Facturación</h1>
            <span class="muted">Resumen integral de ventas con filtros dinámicos.</span>
        </div>
        <form class="filters" id="formFiltros">
            <label>Desde
                <input type="date" id="fDesde" name="desde" required>
            </label>
            <label>Hasta
                <input type="date" id="fHasta" name="hasta" required>
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
                </select>
            </label>
            <button type="submit" class="btn">Actualizar</button>
        </form>
    </header>

    <section class="cards" id="cardsResumen">
        <!-- se rellena con JS -->
    </section>

    <section class="grid-double">
        <div class="panel">
            <h2>Clientes destacados</h2>
            <div class="muted" id="topClientsEmpty" style="display:none;">No se registran clientes en el período.</div>
            <table id="tableTopClients" style="display:none;">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Facturas</th>
                        <th class="right">Total Neto</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="panel">
            <h2>IVA e Importe</h2>
            <table>
                <tbody id="tbodyIva">
                    <!-- se rellena con JS -->
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Detalle de Facturas</h2>
        <div class="muted" id="invoicesEmpty" style="display:none;">No se encontraron facturas con los filtros aplicados.</div>
        <div class="loading" id="invoicesLoading" style="display:none;">
            <span></span><span></span><span></span> Cargando detalle...
        </div>
        <table id="tableInvoices" style="display:none;">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>N° Documento</th>
                    <th>Cliente</th>
                    <th>Condición</th>
                    <th>Estado</th>
                    <th class="right">Neto</th>
                    <th class="right">IVA</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </section>
</div>

<script>
const fmtCurrency = new Intl.NumberFormat('es-PY', {
    style: 'currency',
    currency: 'PYG',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
});
const fmtNumber = new Intl.NumberFormat('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

function setDefaultDates() {
    const hoy = new Date();
    const primeroMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    const inputDesde = document.getElementById('fDesde');
    const inputHasta = document.getElementById('fHasta');
    inputDesde.value = primeroMes.toISOString().substring(0, 10);
    inputHasta.value = hoy.toISOString().substring(0, 10);
}

async function fetchData() {
    const form = document.getElementById('formFiltros');
    const params = new URLSearchParams(new FormData(form)).toString();
    const url = `?action=consulta&${params}`;

    try {
        document.getElementById('invoicesLoading').style.display = 'flex';
        const res = await fetch(url);
        const data = await res.json();
        if (!data.ok) {
            alert(data.error || 'Error al obtener datos.');
            return;
        }
        renderSummary(data.summary);
        renderTopClients(data.top_clients);
        renderInvoices(data.invoices);
        renderIva(data.summary);
    } catch (err) {
        console.error(err);
        alert('No se pudo obtener el reporte.');
    } finally {
        document.getElementById('invoicesLoading').style.display = 'none';
    }
}

function renderSummary(summary) {
    const cards = document.getElementById('cardsResumen');
    const totalFacturas = Number(summary.total_facturas || 0);
    const totalNeto = Number(summary.total_neto || 0);
    const totalIva = Number(summary.total_iva || 0);
    const totalBruto = Number(summary.total_bruto || 0);
    const descuentos = Number(summary.total_descuento || 0);
    const promedio = totalFacturas ? totalNeto / totalFacturas : 0;

    cards.innerHTML = `
        <div class="card">
            <h3>Facturas emitidas</h3>
            <strong>${fmtNumber.format(totalFacturas)}</strong>
            <span>En el período seleccionado</span>
        </div>
        <div class="card">
            <h3>Total Neto</h3>
            <strong>${fmtCurrency.format(totalNeto)}</strong>
            <span>Importe luego de descuentos</span>
        </div>
        <div class="card">
            <h3>IVA total</h3>
            <strong>${fmtCurrency.format(totalIva)}</strong>
            <span>Sumatoria de IVA 10% y 5%</span>
        </div>
        <div class="card">
            <h3>Ticket promedio</h3>
            <strong>${fmtCurrency.format(promedio)}</strong>
            <span>Promedio por factura</span>
        </div>
        <div class="card small">
            <h3>Bruto</h3>
            <strong>${fmtCurrency.format(totalBruto)}</strong>
            <span>Antes de descuentos</span>
        </div>
        <div class="card small">
            <h3>Descuentos</h3>
            <strong>${fmtCurrency.format(descuentos)}</strong>
            <span>Aplicados en facturas</span>
        </div>
    `;
}

function renderTopClients(list) {
    const table = document.getElementById('tableTopClients');
    const tbody = table.querySelector('tbody');
    const empty = document.getElementById('topClientsEmpty');

    if (!list || !list.length) {
        table.style.display = 'none';
        empty.style.display = 'block';
        return;
    }

    empty.style.display = 'none';
    table.style.display = 'table';
    tbody.innerHTML = list.map(cli => `
        <tr>
            <td>${cli.cliente}</td>
            <td>${fmtNumber.format(cli.facturas)}</td>
            <td class="right">${fmtCurrency.format(cli.total_neto)}</td>
        </tr>
    `).join('');
}

function renderIva(summary) {
    const tbody = document.getElementById('tbodyIva');
    tbody.innerHTML = `
        <tr>
            <td>Gravado 10%</td>
            <td class="right">${fmtCurrency.format(Number(summary.total_grav10||0))}</td>
        </tr>
        <tr>
            <td>IVA 10%</td>
            <td class="right">${fmtCurrency.format(Number(summary.total_iva10||0))}</td>
        </tr>
        <tr>
            <td>Gravado 5%</td>
            <td class="right">${fmtCurrency.format(Number(summary.total_grav5||0))}</td>
        </tr>
        <tr>
            <td>IVA 5%</td>
            <td class="right">${fmtCurrency.format(Number(summary.total_iva5||0))}</td>
        </tr>
        <tr>
            <td>Exentas</td>
            <td class="right">${fmtCurrency.format(Number(summary.total_exentas||0))}</td>
        </tr>
    `;
}

function renderInvoices(list) {
    const table = document.getElementById('tableInvoices');
    const tbody = table.querySelector('tbody');
    const empty = document.getElementById('invoicesEmpty');

    if (!list || !list.length) {
        table.style.display = 'none';
        empty.style.display = 'block';
        return;
    }

    empty.style.display = 'none';
    table.style.display = 'table';
    tbody.innerHTML = list.map(row => `
        <tr>
            <td>${row.fecha_emision}</td>
            <td>${row.numero_documento}</td>
            <td>${row.cliente}</td>
            <td><span class="badge">${row.condicion_venta}</span></td>
            <td><span class="badge secondary">${row.estado}</span></td>
            <td class="right">${fmtCurrency.format(Number(row.total_neto || 0))}</td>
            <td class="right">${fmtCurrency.format(Number(row.total_iva || 0))}</td>
        </tr>
    `).join('');
}

document.getElementById('formFiltros').addEventListener('submit', (ev) => {
    ev.preventDefault();
    fetchData();
});

setDefaultDates();
fetchData();
</script>
</body>
</html>
