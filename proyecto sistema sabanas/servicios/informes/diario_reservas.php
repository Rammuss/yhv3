<?php
session_start();

if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}

require_once __DIR__ . '/../../conexion/configv2.php';

$TZ = 'America/Asuncion';
$today = new DateTime('now', new DateTimeZone($TZ));
$fechaParam = $_GET['fecha'] ?? $today->format('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaParam)) {
    $fechaParam = $today->format('Y-m-d');
}

$params = [$TZ, $fechaParam];

$sql = "
    SELECT
        rc.id_reserva,
        rc.estado,
        to_char(rc.inicio_ts AT TIME ZONE $1, 'HH24:MI') AS hora_inicio,
        to_char(rc.fin_ts AT TIME ZONE $1, 'HH24:MI') AS hora_fin,
        (c.nombre || ' ' || COALESCE(c.apellido,'')) AS cliente,
        COALESCE(p.nombre, 'Sin asignar') AS profesional,
        COALESCE(string_agg(
            CASE
                WHEN rd.descripcion IS NULL THEN NULL
                ELSE rd.descripcion || CASE
                    WHEN rd.cantidad IS NULL OR rd.cantidad = 1 THEN ''
                    ELSE ' x' || trim(to_char(rd.cantidad, 'FM999G990'))
                END
            END,
            ', ' ORDER BY rd.item_nro
        ), '') AS servicios,
        COALESCE(SUM(rd.cantidad * rd.precio_unitario), 0)::numeric(14,2) AS importe,
        EXTRACT(EPOCH FROM (rc.fin_ts - rc.inicio_ts)) / 60 AS duracion_min
    FROM public.reserva_cab rc
    JOIN public.clientes c ON c.id_cliente = rc.id_cliente
    LEFT JOIN public.profesional p ON p.id_profesional = rc.id_profesional
    LEFT JOIN public.reserva_det rd ON rd.id_reserva = rc.id_reserva
    WHERE (rc.inicio_ts AT TIME ZONE $1)::date = $2::date
    GROUP BY rc.id_reserva, rc.estado, rc.inicio_ts, rc.fin_ts, c.nombre, c.apellido, p.nombre
    ORDER BY rc.inicio_ts;
";

$reservas = [];
$error = null;
$result = pg_query_params($conn, $sql, $params);

if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $row['id_reserva'] = (int)$row['id_reserva'];
        $row['importe'] = (float)$row['importe'];
        $row['duracion_min'] = round((float)$row['duracion_min']);
        $reservas[] = $row;
    }
} else {
    $error = 'No se pudo obtener la información de reservas. Intente nuevamente.';
}

$totales = [
    'reservas' => 0,
    'importe' => 0.0,
    'duracion_min' => 0,
    'por_estado' => [],
    'por_profesional' => [],
];

foreach ($reservas as $row) {
    $totales['reservas']++;
    $totales['importe'] += $row['importe'];
    $totales['duracion_min'] += $row['duracion_min'];

    $estado = $row['estado'] ?: 'Sin estado';
    $totales['por_estado'][$estado] = ($totales['por_estado'][$estado] ?? 0) + 1;

    $prof = $row['profesional'] ?: 'Sin asignar';
    if (!isset($totales['por_profesional'][$prof])) {
        $totales['por_profesional'][$prof] = ['cantidad' => 0, 'importe' => 0.0];
    }
    $totales['por_profesional'][$prof]['cantidad']++;
    $totales['por_profesional'][$prof]['importe'] += $row['importe'];
}

ksort($totales['por_estado']);
uasort($totales['por_profesional'], function ($a, $b) {
    return $b['cantidad'] <=> $a['cantidad'];
});

$horasTotales = $totales['duracion_min'] / 60;
$usuario = htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$fechaFormateada = DateTime::createFromFormat('Y-m-d', $fechaParam, new DateTimeZone($TZ));
$fechaMostrar = $fechaFormateada ? $fechaFormateada->format('d/m/Y') : $fechaParam;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Informe Diario de Reservas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(135deg, #fff1f7 0%, #ffe4f2 45%, #fef9ff 100%);
            --surface: rgba(255, 255, 255, 0.92);
            --text: #3d2241;
            --muted: #8b6c8f;
            --accent: #d63384;
            --accent-soft: rgba(214, 51, 132, 0.14);
            --border: rgba(214, 51, 132, 0.18);
            --shadow: 0 24px 48px rgba(188, 70, 137, 0.18);
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

        .app {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            backdrop-filter: blur(12px);
            background: rgba(255, 255, 255, 0.85);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 12px 30px rgba(188, 70, 137, 0.12);
            position: sticky;
            top: 0;
            z-index: 5;
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
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 24px 48px;
            width: 100%;
        }

        h1 {
            margin: 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2rem;
            color: var(--accent);
        }

        .filters {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filters label {
            font-size: 0.9rem;
            display: flex;
            flex-direction: column;
            gap: 6px;
            color: var(--muted);
        }

        .filters input,
        .filters button {
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            font: inherit;
            color: inherit;
        }

        .filters button {
            background: var(--accent);
            color: #fff;
            border: none;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }

        .filters button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(214, 51, 132, 0.26);
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
            font-size: 1.9rem;
            font-weight: 600;
            color: var(--accent);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.88);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 12px 32px rgba(61, 34, 65, 0.12);
        }

        thead {
            background: var(--accent);
            color: #fff;
        }

        th,
        td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(214, 51, 132, 0.12);
            font-size: 0.95rem;
        }

        tbody tr:hover {
            background: rgba(214, 51, 132, 0.06);
        }

        .empty {
            padding: 28px 22px;
            text-align: center;
            color: var(--muted);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .badge[data-estado="Pendiente"] {
            background: rgba(247, 178, 51, 0.18);
            color: #a26305;
        }

        .badge[data-estado="Confirmada"],
        .badge[data-estado="Completada"] {
            background: rgba(45, 179, 126, 0.18);
            color: #1b7a52;
        }

        .badge[data-estado="Cancelada"] {
            background: rgba(239, 68, 68, 0.18);
            color: #b91c1c;
        }

        .actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-secondary {
            background: #fff;
            color: var(--accent);
            border: 1px solid var(--border);
            padding: 10px 14px;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(214, 51, 132, 0.2);
        }

        @media (max-width: 720px) {
            header .container {
                flex-direction: column;
                align-items: flex-start;
            }

            .filters {
                width: 100%;
            }

            table {
                font-size: 0.88rem;
            }

            th,
            td {
                padding: 10px 12px;
            }
        }
    </style>
    <link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/css/styles_servicios.css">

</head>

<body>
    <div class="app">
        <div id="navbar-container"></div>
        <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/navbar/navbar.js"></script>

        <header>
            <div class="container">
                <div>
                    <h1>Informe diario de reservas</h1>
                    <div class="muted">Fecha seleccionada: <?= htmlspecialchars($fechaMostrar, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="filters">
                    <form method="get" class="filters">
                        <label>Fecha
                            <input type="date" name="fecha" value="<?= htmlspecialchars($fechaParam, ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                        <div class="actions">
                            <button type="submit">Actualizar</button>
                            <button type="button" class="btn-secondary" onclick="window.print()">Imprimir</button>
                        </div>
                    </form>
                </div>
            </div>
        </header>

        <main>
            <section class="summary">
                <div class="card">
                    <h3>Reservas del día</h3>
                    <div class="value"><?= number_format($totales['reservas'], 0, ',', '.') ?></div>
                </div>
                <div class="card">
                    <h3>Horas agendadas</h3>
                    <div class="value"><?= number_format($horasTotales, 1, ',', '.') ?> h</div>
                </div>
                <div class="card">
                    <h3>Importe estimado</h3>
                    <div class="value">Gs. <?= number_format($totales['importe'], 0, ',', '.') ?></div>
                </div>
                <div class="card">
                    <h3>Generado por</h3>
                    <div class="value" style="font-size:1.2rem;"><?= $usuario ?></div>
                </div>
            </section>

            <?php if ($totales['por_estado']): ?>
                <section class="card" style="margin-bottom: 24px;">
                    <h3>Distribución por estado</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:12px;">
                        <?php foreach ($totales['por_estado'] as $estado => $cantidad): ?>
                            <div class="badge" data-estado="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?> · <?= number_format($cantidad, 0, ',', '.') ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($totales['por_profesional']): ?>
                <section class="card" style="margin-bottom: 24px;">
                    <h3>Profesionales destacados</h3>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:12px;">
                        <?php foreach ($totales['por_profesional'] as $nombre => $info): ?>
                            <div style="background: rgba(255,255,255,0.9); border-radius: 14px; padding:12px; border:1px solid var(--border);">
                                <div style="font-weight:600;"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="font-size:0.9rem; color:var(--muted);"><?= number_format($info['cantidad'], 0, ',', '.') ?> reservas</div>
                                <div style="font-size:0.9rem; color:var(--muted);">Gs. <?= number_format($info['importe'], 0, ',', '.') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section>
                <?php if ($error): ?>
                    <div class="card empty">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php elseif (empty($reservas)): ?>
                    <div class="card empty">
                        No se registran reservas para la fecha seleccionada.
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Horario</th>
                                <th>Clienta</th>
                                <th>Profesional</th>
                                <th>Servicios</th>
                                <th>Duración (min)</th>
                                <th>Estado</th>
                                <th>Importe (Gs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas as $res): ?>
                                <tr>
                                    <td><?= htmlspecialchars($res['id_reserva'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($res['hora_inicio'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($res['hora_fin'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($res['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($res['profesional'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($res['servicios'], ENT_QUOTES, 'UTF-8') ?: 'N/A' ?></td>
                                    <td><?= number_format($res['duracion_min'], 0, ',', '.') ?></td>
                                    <td><span class="badge" data-estado="<?= htmlspecialchars($res['estado'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($res['estado'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= number_format($res['importe'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>

</html>
