<?php
session_start();

if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}

require_once __DIR__ . '/../../conexion/configv2.php';

$TZ = 'America/Asuncion';
$today = new DateTime('now', new DateTimeZone($TZ));
$defaultDesde = (clone $today)->modify('first day of this month')->format('Y-m-d');
$defaultHasta = $today->format('Y-m-d');

$fechaDesde = $_GET['desde'] ?? $defaultDesde;
$fechaHasta = $_GET['hasta'] ?? $defaultHasta;
$estadoFiltro = $_GET['estado'] ?? 'activas';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
    $fechaDesde = $defaultDesde;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
    $fechaHasta = $defaultHasta;
}

if ($fechaHasta < $fechaDesde) {
    $fechaHasta = $fechaDesde;
}

$paramsBase = [$TZ, $fechaDesde, $fechaHasta];
$where = [
    "(rc.inicio_ts AT TIME ZONE $1)::date BETWEEN $2::date AND $3::date"
];

$estadosActivos = ['Pendiente', 'Confirmada', 'Completada'];

switch ($estadoFiltro) {
    case 'completadas':
        $where[] = "rc.estado = 'Completada'";
        break;
    case 'activas':
        $placeholders = implode(',', array_map(fn($idx) => "'" . pg_escape_string($idx) . "'", $estadosActivos));
        $where[] = "rc.estado IN ($placeholders)";
        break;
    case 'todas':
    default:
        $estadoFiltro = 'todas';
        break;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$error = null;
$summary = [
    'reservas' => 0,
    'importe' => 0.0,
    'minutos' => 0
];
$rankingServicios = [];
$rankingProfesionales = [];

$sqlResumen = "
    SELECT
        COUNT(DISTINCT rc.id_reserva) AS reservas,
        COALESCE(SUM(rd.cantidad * rd.precio_unitario), 0)::numeric(14,2) AS importe,
        COALESCE(SUM(COALESCE(rd.duracion_min, 0)), 0) AS minutos
    FROM public.reserva_cab rc
    LEFT JOIN public.reserva_det rd ON rd.id_reserva = rc.id_reserva
    $whereSql
";

$resResumen = pg_query_params($conn, $sqlResumen, $paramsBase);
if ($resResumen) {
    $summaryRow = pg_fetch_assoc($resResumen);
    if ($summaryRow) {
        $summary['reservas'] = (int)$summaryRow['reservas'];
        $summary['importe'] = (float)$summaryRow['importe'];
        $summary['minutos'] = (int)$summaryRow['minutos'];
    }
} else {
    $error = 'No se pudo obtener el resumen general. Intente nuevamente.';
}

if (!$error) {
    $sqlServicios = "
        SELECT
            COALESCE(rd.id_producto, 0) AS id_producto,
            COALESCE(NULLIF(rd.descripcion, ''), 'Servicio sin descripción') AS servicio,
            COUNT(DISTINCT rc.id_reserva) AS reservas,
            COALESCE(SUM(rd.cantidad), 0)::numeric(14,2) AS cantidad_total,
            COALESCE(SUM(rd.cantidad * rd.precio_unitario), 0)::numeric(14,2) AS ingreso,
            COALESCE(SUM(COALESCE(rd.duracion_min, 0)), 0) AS minutos
        FROM public.reserva_cab rc
        JOIN public.reserva_det rd ON rd.id_reserva = rc.id_reserva
        $whereSql
        GROUP BY rd.id_producto, servicio
        ORDER BY ingreso DESC, servicio
        LIMIT 50
    ";

    $resServicios = pg_query_params($conn, $sqlServicios, $paramsBase);
    if ($resServicios) {
        while ($row = pg_fetch_assoc($resServicios)) {
            $rankingServicios[] = [
                'id_producto' => (int)$row['id_producto'],
                'servicio' => $row['servicio'],
                'reservas' => (int)$row['reservas'],
                'cantidad' => (float)$row['cantidad_total'],
                'ingreso' => (float)$row['ingreso'],
                'minutos' => (int)$row['minutos']
            ];
        }
    } else {
        $error = 'No se pudo generar el ranking de servicios.';
    }
}

if (!$error) {
    $sqlProfesionales = "
        SELECT
            COALESCE(p.nombre, 'Sin asignar') AS profesional,
            COUNT(DISTINCT rc.id_reserva) AS reservas,
            COALESCE(SUM(rd.cantidad), 0)::numeric(14,2) AS servicios_realizados,
            COALESCE(SUM(rd.cantidad * rd.precio_unitario), 0)::numeric(14,2) AS ingreso,
            COALESCE(SUM(COALESCE(rd.duracion_min, 0)), 0) AS minutos
        FROM public.reserva_cab rc
        LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional
        LEFT JOIN public.reserva_det rd ON rd.id_reserva = rc.id_reserva
        $whereSql
        GROUP BY profesional
        ORDER BY ingreso DESC, profesional
        LIMIT 50
    ";

    $resProfesionales = pg_query_params($conn, $sqlProfesionales, $paramsBase);
    if ($resProfesionales) {
        while ($row = pg_fetch_assoc($resProfesionales)) {
            $rankingProfesionales[] = [
                'profesional' => $row['profesional'],
                'reservas' => (int)$row['reservas'],
                'servicios' => (float)$row['servicios_realizados'],
                'ingreso' => (float)$row['ingreso'],
                'minutos' => (int)$row['minutos']
            ];
        }
    } else {
        $error = 'No se pudo generar el ranking de profesionales.';
    }
}

$usuario = htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$fechaDesdeHuman = DateTime::createFromFormat('Y-m-d', $fechaDesde, new DateTimeZone($TZ));
$fechaHastaHuman = DateTime::createFromFormat('Y-m-d', $fechaHasta, new DateTimeZone($TZ));
$rangoTexto = ($fechaDesdeHuman ? $fechaDesdeHuman->format('d/m/Y') : $fechaDesde)
    . ' al '
    . ($fechaHastaHuman ? $fechaHastaHuman->format('d/m/Y') : $fechaHasta);

function minutos_a_horas($minutos)
{
    $horas = floor($minutos / 60);
    $mins = $minutos % 60;
    return sprintf('%02dh %02dm', $horas, $mins);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Ranking de Servicios y Profesionales</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(135deg, #fef4ff 0%, #ffe5f4 45%, #f8f9ff 100%);
            --surface: rgba(255, 255, 255, 0.94);
            --text: #3b1f42;
            --muted: #90719f;
            --accent: #c02678;
            --accent-soft: rgba(192, 38, 120, 0.14);
            --border: rgba(192, 38, 120, 0.18);
            --shadow: 0 24px 48px rgba(192, 38, 120, 0.16);
            --radius: 18px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Poppins", system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        header {
            position: sticky;
            top: 0;
            z-index: 5;
            backdrop-filter: blur(12px);
            background: rgba(255, 255, 255, 0.82);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 12px 32px rgba(59, 31, 66, 0.08);
        }

        header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 24px 48px;
        }

        h1 {
            margin: 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2rem;
            color: var(--accent);
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .filters label {
            font-size: 0.85rem;
            color: var(--muted);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filters input,
        .filters select {
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            font: inherit;
            color: inherit;
            min-width: 160px;
        }

        .filters button {
            padding: 10px 16px;
            border-radius: 12px;
            border: none;
            background: var(--accent);
            color: #fff;
            font: inherit;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }

        .filters button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(192, 38, 120, 0.24);
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin: 28px 0;
        }

        .card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .card h3 {
            margin: 0 0 10px;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--muted);
        }

        .card .value {
            font-size: 1.85rem;
            font-weight: 600;
            color: var(--accent);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.92);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 12px 32px rgba(59, 31, 66, 0.12);
        }

        thead {
            background: var(--accent);
            color: #fff;
        }

        th,
        td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(192, 38, 120, 0.14);
            font-size: 0.95rem;
        }

        tbody tr:hover {
            background: rgba(192, 38, 120, 0.06);
        }

        .empty {
            padding: 24px;
            text-align: center;
            color: var(--muted);
        }

        @media (max-width: 720px) {
            header .container {
                flex-direction: column;
                align-items: flex-start;
            }

            .filters {
                width: 100%;
            }
        }
    </style>
        <link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/css/styles_servicios.css">

</head>

<body>
    <div id="navbar-container"></div>
    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/navbar/navbar.js"></script>

    <header>
        <div class="container">
            <div>
                <h1>Ranking de servicios y profesionales</h1>
                <div class="muted">Período: <?= htmlspecialchars($rangoTexto, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <form method="get" class="filters">
                <label>Desde
                    <input type="date" name="desde" value="<?= htmlspecialchars($fechaDesde, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Hasta
                    <input type="date" name="hasta" value="<?= htmlspecialchars($fechaHasta, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Estado
                    <select name="estado">
                        <option value="activas" <?= $estadoFiltro === 'activas' ? 'selected' : '' ?>>Pendiente/Confirmada/Completada</option>
                        <option value="completadas" <?= $estadoFiltro === 'completadas' ? 'selected' : '' ?>>Solo completadas</option>
                        <option value="todas" <?= $estadoFiltro === 'todas' ? 'selected' : '' ?>>Todas (incluye canceladas)</option>
                    </select>
                </label>
                <button type="submit">Actualizar</button>
            </form>
        </div>
    </header>

    <main>
        <section class="summary">
            <div class="card">
                <h3>Total de reservas</h3>
                <div class="value"><?= number_format($summary['reservas'], 0, ',', '.') ?></div>
            </div>
            <div class="card">
                <h3>Ingreso estimado</h3>
                <div class="value">Gs. <?= number_format($summary['importe'], 0, ',', '.') ?></div>
            </div>
            <div class="card">
                <h3>Horas agenda</h3>
                <div class="value"><?= minutos_a_horas($summary['minutos']) ?></div>
            </div>
            <div class="card">
                <h3>Consultado por</h3>
                <div class="value" style="font-size:1.1rem;"><?= $usuario ?></div>
            </div>
        </section>

        <?php if ($error): ?>
            <section class="card empty"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></section>
        <?php else: ?>
            <section class="grid">
                <div>
                    <div class="card" style="margin-bottom:16px;">
                        <h3>Top 50 servicios</h3>
                    </div>
                    <?php if (empty($rankingServicios)): ?>
                        <div class="card empty">No se registran servicios en el rango seleccionado.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Servicio</th>
                                    <th>Reservas</th>
                                    <th>Cantidad</th>
                                    <th>Duración</th>
                                    <th>Ingreso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rankingServicios as $srv): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($srv['servicio'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= number_format($srv['reservas'], 0, ',', '.') ?></td>
                                        <td><?= number_format($srv['cantidad'], 2, ',', '.') ?></td>
                                        <td><?= minutos_a_horas($srv['minutos']) ?></td>
                                        <td>Gs. <?= number_format($srv['ingreso'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="card" style="margin-bottom:16px;">
                        <h3>Top 50 profesionales</h3>
                    </div>
                    <?php if (empty($rankingProfesionales)): ?>
                        <div class="card empty">No se registran profesionales en el rango seleccionado.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Profesional</th>
                                    <th>Reservas</th>
                                    <th>Servicios</th>
                                    <th>Duración</th>
                                    <th>Ingreso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rankingProfesionales as $pro): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($pro['profesional'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= number_format($pro['reservas'], 0, ',', '.') ?></td>
                                        <td><?= number_format($pro['servicios'], 2, ',', '.') ?></td>
                                        <td><?= minutos_a_horas($pro['minutos']) ?></td>
                                        <td>Gs. <?= number_format($pro['ingreso'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>

</html>
