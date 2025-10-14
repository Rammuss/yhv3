<?php
session_start();

if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}

require_once __DIR__ . '/../../conexion/configv2.php';

function to_pg_array(array $input): string
{
    $escaped = array_map(
        fn($value) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"',
        $input
    );
    return '{' . implode(',', $escaped) . '}';
}

$TZ = 'America/Asuncion';
$today = new DateTime('now', new DateTimeZone($TZ));

$dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 60;
$dias = max(7, min(730, $dias));

$minReservas = isset($_GET['min_reservas']) ? (int)$_GET['min_reservas'] : 1;
$minReservas = max(0, min(100, $minReservas));

$soloActivos = ($_GET['solo_activos'] ?? '1') === '1';
$buscar = trim($_GET['q'] ?? '');

$estadoFiltro = $_GET['estado'] ?? 'activas';
$estadoOpciones = [
    'completadas' => ['Completada'],
    'activas' => ['Pendiente', 'Confirmada', 'Completada'],
    'todas' => []
];
if (!array_key_exists($estadoFiltro, $estadoOpciones)) {
    $estadoFiltro = 'activas';
}

$cutoff = (clone $today)->modify("-{$dias} days")->format('Y-m-d');
$estadoArray = $estadoFiltro === 'todas' ? '{}' : to_pg_array($estadoOpciones[$estadoFiltro]);

$params = [$TZ];
$sqlJoinEstado = '';
if ($estadoFiltro !== 'todas') {
    $params[] = $estadoArray;
    $estadoIdx = count($params);
    $sqlJoinEstado = "AND rc.estado = ANY($".$estadoIdx."::text[])";
}

$params[] = $cutoff;
$cutoffIdx = count($params);

$params[] = $minReservas;
$minIdx = count($params);

$sqlSoloActivos = $soloActivos ? 'AND activo IS TRUE' : '';

$sqlBusqueda = '';
if ($buscar !== '') {
    $params[] = '%'.$buscar.'%';
    $searchIdx = count($params);
    $sqlBusqueda = "AND (
        (nombre || ' ' || COALESCE(apellido,'')) ILIKE $" . $searchIdx . "
        OR COALESCE(telefono,'') ILIKE $" . $searchIdx . "
        OR COALESCE(ruc_ci,'') ILIKE $" . $searchIdx . "
    )";
} else {
    $searchIdx = null;
}

$sql = "
    WITH visitas AS (
        SELECT
            c.id_cliente,
            c.nombre,
            c.apellido,
            c.telefono,
            c.direccion,
            c.ruc_ci,
            c.activo,
            MAX(rc.inicio_ts AT TIME ZONE $1) AS ultima_visita,
            COUNT(DISTINCT rc.id_reserva) AS total_reservas,
            COUNT(DISTINCT CASE WHEN rc.estado = 'Completada' THEN rc.id_reserva END) AS completadas,
            COALESCE(SUM(rd.cantidad * rd.precio_unitario), 0)::numeric(14,2) AS total_ingreso
        FROM public.clientes c
        LEFT JOIN public.reserva_cab rc
            ON rc.id_cliente = c.id_cliente
            $sqlJoinEstado
        LEFT JOIN public.reserva_det rd
            ON rd.id_reserva = rc.id_reserva
        GROUP BY c.id_cliente
    )
    SELECT
        id_cliente,
        nombre,
        apellido,
        telefono,
        direccion,
        ruc_ci,
        activo,
        ultima_visita,
        total_reservas,
        completadas,
        total_ingreso
    FROM visitas
    WHERE (ultima_visita IS NULL OR ultima_visita::date <= $" . $cutoffIdx . "::date)
      AND total_reservas >= $" . $minIdx . "
      $sqlSoloActivos
      $sqlBusqueda
    ORDER BY ultima_visita NULLS FIRST, nombre
    LIMIT 400;
";

$result = pg_query_params($conn, $sql, $params);
$clientes = [];

$totalSinHistorial = 0;
$totalImporte = 0.0;
$diasSum = 0;
$diasCount = 0;

if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $ultimaVisita = $row['ultima_visita'];
        $diasSinVisita = null;
        if ($ultimaVisita !== null) {
            $dtUltima = DateTime::createFromFormat('Y-m-d H:i:s', $ultimaVisita, new DateTimeZone($TZ));
            if (!$dtUltima) {
                $dtUltima = new DateTime($ultimaVisita, new DateTimeZone($TZ));
            }
            $diff = $dtUltima ? $dtUltima->diff($today) : null;
            if ($diff) {
                $diasSinVisita = (int)$diff->format('%a');
                $diasSum += $diasSinVisita;
                $diasCount++;
            }
        } else {
            $totalSinHistorial++;
        }

        $row['total_reservas'] = (int)$row['total_reservas'];
        $row['completadas'] = (int)$row['completadas'];
        $row['total_ingreso'] = (float)$row['total_ingreso'];
        $row['dias_sin_visita'] = $diasSinVisita;
        $totalImporte += $row['total_ingreso'];

        $clientes[] = $row;
    }
}

$totalClientes = count($clientes);
$promedioDias = $diasCount > 0 ? round($diasSum / $diasCount, 1) : null;
$usuario = htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');

function format_fecha(?string $fecha, string $tz): string
{
    if ($fecha === null) {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $fecha, new DateTimeZone($tz));
    if (!$dt) {
        $dt = new DateTime($fecha, new DateTimeZone($tz));
    }
    return $dt ? $dt->format('d/m/Y H:i') : $fecha;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Clientes sin retorno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(135deg, #fff3f9 0%, #fbe9ff 45%, #f9f9ff 100%);
            --surface: rgba(255, 255, 255, 0.94);
            --text: #3a2044;
            --muted: #8d6f9f;
            --accent: #c02678;
            --border: rgba(192, 38, 120, 0.18);
            --shadow: 0 24px 48px rgba(192, 38, 120, 0.16);
            --radius: 18px;
        }

        * { box-sizing: border-box; }

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
            box-shadow: 0 12px 32px rgba(58, 32, 68, 0.08);
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
            min-width: 150px;
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

        main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 24px 56px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
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
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--accent);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.92);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 12px 32px rgba(58, 32, 68, 0.12);
        }

        thead {
            background: var(--accent);
            color: #fff;
        }

        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(192, 38, 120, 0.12);
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

        .actions {
            display: flex;
            gap: 12px;
        }

        .btn-secondary {
            background: #fff;
            color: var(--accent);
            border: 1px solid var(--border);
            padding: 10px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(192, 38, 120, 0.2);
        }

        @media print {
            header, .actions { display: none; }
            body { background: #fff; }
            table { box-shadow: none; }
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
    <link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_servicios.css">

</head>

<body>
    <div id="navbar-container"></div>
    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/navbar/navbar.js"></script>

    <header>
        <div class="container">
            <div>
                <h1>Clientes sin retorno</h1>
                <div class="muted">
                    Última visita mayor a <?= htmlspecialchars($dias, ENT_QUOTES, 'UTF-8') ?> días (o sin historial).
                </div>
            </div>
            <form method="get" class="filters">
                <label>Días sin visita
                    <input type="number" name="dias" min="7" max="730" value="<?= htmlspecialchars((string)$dias, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Reservas mínimas
                    <input type="number" name="min_reservas" min="0" max="100" value="<?= htmlspecialchars((string)$minReservas, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label>Estado considerado
                    <select name="estado">
                        <option value="activas" <?= $estadoFiltro === 'activas' ? 'selected' : '' ?>>Pendiente/Confirmada/Completada</option>
                        <option value="completadas" <?= $estadoFiltro === 'completadas' ? 'selected' : '' ?>>Solo completadas</option>
                        <option value="todas" <?= $estadoFiltro === 'todas' ? 'selected' : '' ?>>Todas (incluye canceladas)</option>
                    </select>
                </label>
                <label>Solo activas
                    <select name="solo_activos">
                        <option value="1" <?= $soloActivos ? 'selected' : '' ?>>Sí</option>
                        <option value="0" <?= !$soloActivos ? 'selected' : '' ?>>No</option>
                    </select>
                </label>
                <label>Búsqueda
                    <input type="text" name="q" placeholder="Nombre, celular, RUC" value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <div class="actions">
                    <button type="submit">Actualizar</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">Imprimir (Ctrl+P)</button>
                </div>
            </form>
        </div>
    </header>

    <main>
        <section class="summary">
            <div class="card">
                <h3>Clientes detectadas</h3>
                <div class="value"><?= number_format($totalClientes, 0, ',', '.') ?></div>
            </div>
            <div class="card">
                <h3>Sin historial previo</h3>
                <div class="value"><?= number_format($totalSinHistorial, 0, ',', '.') ?></div>
            </div>
            <div class="card">
                <h3>Promedio de días</h3>
                <div class="value"><?= $promedioDias !== null ? number_format($promedioDias, 1, ',', '.') . ' días' : '—' ?></div>
            </div>
            <div class="card">
                <h3>Ingresos acumulados</h3>
                <div class="value">Gs. <?= number_format($totalImporte, 0, ',', '.') ?></div>
            </div>
            <div class="card">
                <h3>Consultado por</h3>
                <div class="value" style="font-size:1.1rem;"><?= $usuario ?></div>
            </div>
        </section>

        <?php if (empty($clientes)): ?>
            <section class="card empty">
                No se encontraron clientas que cumplan con los filtros actuales.
            </section>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Clienta</th>
                        <th>Contacto</th>
                        <th>Reservas</th>
                        <th>Completadas</th>
                        <th>Última visita</th>
                        <th>Días sin visita</th>
                        <th>Ingreso total (Gs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cli): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($cli['nombre'] . ' ' . ($cli['apellido'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><br>
                                <span style="color:var(--muted); font-size:0.85rem;">RUC/CI: <?= htmlspecialchars($cli['ruc_ci'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($cli['telefono'] ?? '—', ENT_QUOTES, 'UTF-8') ?><br>
                                <span style="color:var(--muted); font-size:0.85rem;"><?= htmlspecialchars($cli['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td><?= number_format($cli['total_reservas'], 0, ',', '.') ?></td>
                            <td><?= number_format($cli['completadas'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars(format_fecha($cli['ultima_visita'], $TZ), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $cli['dias_sin_visita'] !== null ? number_format($cli['dias_sin_visita'], 0, ',', '.') . ' días' : 'Sin historial' ?></td>
                            <td><?= number_format($cli['total_ingreso'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>

</html>
