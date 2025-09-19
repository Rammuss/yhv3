<?php
// /caja/recaudaciones_historial.php — Listado/Historial de recaudaciones
session_start();
if (empty($_SESSION['id_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}
require_once __DIR__ . '/../../conexion/configv2.php';

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function n($x, $d = 0)
{
    return number_format((float)$x, $d, ',', '.');
}

// Filtros
$hoy   = date('Y-m-d');
$desde = trim($_GET['desde'] ?? $hoy);
$hasta = trim($_GET['hasta'] ?? $hoy);
$estado = trim($_GET['estado'] ?? ''); // Pendiente | Depositada (o vacío = todos)
$suc   = trim($_GET['suc']   ?? ''); // id_sucursal opcional
$user  = trim($_GET['user']  ?? ''); // id_usuario opcional
$q     = trim($_GET['q']     ?? ''); // búsqueda en observación

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// WHERE dinámico
$w = ['1=1'];
$params = [];
$pi = 1;

if ($desde !== '') {
    $w[] = "r.fecha >= $" . $pi;
    $params[] = $desde . ' 00:00:00';
    $pi++;
}
if ($hasta !== '') {
    $w[] = "r.fecha <= $" . $pi;
    $params[] = $hasta . ' 23:59:59';
    $pi++;
}
if ($estado !== '') {
    $w[] = "r.estado = $" . $pi;
    $params[] = $estado;
    $pi++;
}
if ($suc !== '') {
    $w[] = "r.id_sucursal = $" . $pi;
    $params[] = (int)$suc;
    $pi++;
}
if ($user !== '') {
    $w[] = "r.id_usuario = $" . $pi;
    $params[] = (int)$user;
    $pi++;
}
if ($q !== '') {
    $w[] = "unaccent(lower(coalesce(r.observacion,''))) LIKE unaccent(lower($" . $pi . "))";
    $params[] = '%' . $q . '%';
    $pi++;
}

$whereSql = implode(' AND ', $w);

// Conteo para paginación
$sqlCount = "SELECT COUNT(*)::int FROM public.recaudacion_deposito r WHERE $whereSql";
$rc = pg_query_params($conn, $sqlCount, $params);
$totalRows = $rc ? (int)pg_fetch_result($rc, 0, 0) : 0;
$totalPages = max(1, (int)ceil($totalRows / $limit));

// Listado con totales por medio (suma de detalle)
$sqlList = "
SELECT
  r.id_recaudacion, r.fecha, r.estado, r.id_sucursal, r.monto_total, r.observacion,
  r.creado_en, r.actualizado_en, r.id_usuario,
  u.nombre_usuario AS creado_por,
  -- Totales por medio desde detalle
  COALESCE(SUM(d.monto_efectivo),0)      AS t_efectivo,
  COALESCE(SUM(d.monto_tarjeta),0)       AS t_tarjeta,
  COALESCE(SUM(d.monto_transferencia),0) AS t_transferencia,
  COALESCE(SUM(d.monto_otros),0)         AS t_otros
FROM public.recaudacion_deposito r
LEFT JOIN public.recaudacion_detalle d ON d.id_recaudacion = r.id_recaudacion
LEFT JOIN public.usuarios u ON u.id = r.id_usuario
WHERE $whereSql
GROUP BY r.id_recaudacion, r.fecha, r.estado, r.id_sucursal, r.monto_total, r.observacion,
         r.creado_en, r.actualizado_en, r.id_usuario, u.nombre_usuario
ORDER BY r.fecha DESC, r.id_recaudacion DESC
LIMIT $limit OFFSET $offset
";
$rl = pg_query_params($conn, $sqlList, $params);
$rows = [];
$agg = ['ef' => 0, 'ta' => 0, 'tr' => 0, 'ot' => 0, 'tt' => 0];
if ($rl) {
    while ($x = pg_fetch_assoc($rl)) {
        $x['t_total'] = (float)$x['t_efectivo'] + (float)$x['t_tarjeta'] + (float)$x['t_transferencia'] + (float)$x['t_otros'];
        $agg['ef'] += (float)$x['t_efectivo'];
        $agg['ta'] += (float)$x['t_tarjeta'];
        $agg['tr'] += (float)$x['t_transferencia'];
        $agg['ot'] += (float)$x['t_otros'];
        $agg['tt'] += (float)$x['t_total'];
        $rows[] = $x;
    }
}

// Selects rápidos
$estados = ['' => '(Todos)', 'Pendiente' => 'Pendiente', 'Depositada' => 'Depositada'];
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Historial de Recaudaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
    <style>
        :root {
            --text: #111827;
            --muted: #6b7280;
            --em: #2563eb;
            --danger: #b91c1c;
            --ok: #166534;
            --warn: #9a6700;
        }

        body {
            margin: 0;
            color: var(--text);
            font: 14px/1.45 system-ui, -apple-system, Segoe UI, Roboto;
            background: #fff;
        }

        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 14px;
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 10px 0 14px;
        }

        .head h1 {
            margin: 0;
            font-size: 22px;
        }

        .muted {
            color: var(--muted);
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 12px;
        }

        .grid {
            display: grid;
            gap: 12px;
        }

        @media(min-width:1000px) {
            .grid.cols-6 {
                grid-template-columns: repeat(6, 1fr);
            }

            .grid.cols-4 {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
        }

        input,
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
        }

        .right {
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid #e5e7eb;
        }

        .badge.p {
            color: #9a6700;
            background: #fef9c3;
            border-color: #fde68a;
        }

        /* Pendiente */
        .badge.d {
            color: #166534;
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        /* Depositada */
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            text-decoration: none;
            color: #111;
            cursor: pointer;
        }

        .btn.primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        .btn.ghost {
            background: #fff;
        }

        .pager {
            display: flex;
            gap: 6px;
            align-items: center;
            justify-content: flex-end;
            margin-top: 8px;
        }
    </style>
</head>

<body>
    <div id="navbar-container" class="no-print"></div>

    <div class="container">
        <div class="head">
            <div>
                <h1>Historial de Recaudaciones</h1>
                <div class="muted">Filtrá por fecha, estado o usuario y abrí el detalle para imprimir.</div>
            </div>
            <div class="actions">
                <a href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_recaudaciones.php" class="btn">Nueva Recaudación</a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <form method="get" class="grid cols-6" style="align-items:end;">
                <div>
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?= e($desde) ?>">
                </div>
                <div>
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?= e($hasta) ?>">
                </div>
                <div>
                    <label>Estado</label>
                    <select name="estado">
                        <?php foreach ($estados as $k => $v): ?>
                            <option value="<?= e($k) ?>" <?= $estado === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Sucursal (ID)</label>
                    <input type="number" name="suc" value="<?= e($suc) ?>" placeholder="ID">
                </div>
                <div>
                    <label>Usuario (ID)</label>
                    <input type="number" name="user" value="<?= e($user) ?>" placeholder="ID">
                </div>
                <div>
                    <label>Búsqueda</label>
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Observación...">
                </div>
                <div>
                    <button class="btn primary" type="submit">Aplicar</button>
                </div>
                <div>
                    <a class="btn ghost"
                        href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/caja/ui_recaudacion_historial.php?desde=<?= date('Y-m-d') ?>&hasta=<?= date('Y-m-d') ?>">
                        Hoy
                    </a>

                </div>


            </form>
        </div>

        <!-- Totales del período listado -->
        <div class="card">
            <div class="grid cols-4">
                <div><strong>Efectivo:</strong> Gs <?= n($agg['ef'], 0) ?></div>
                <div><strong>Tarjeta:</strong> Gs <?= n($agg['ta'], 0) ?></div>
                <div><strong>Transferencia:</strong> Gs <?= n($agg['tr'], 0) ?></div>
                <div><strong>Total listado:</strong> Gs <?= n($agg['tt'], 0) ?></div>
            </div>
            <div class="muted" style="margin-top:6px;">Mostrando <?= n($totalRows, 0) ?> recaudación(es).</div>
        </div>

        <!-- Tabla -->
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th style="width:86px">#</th>
                        <th style="width:160px">Fecha</th>
                        <th>Usuario</th>
                        <th style="width:100px">Estado</th>
                        <th class="right" style="width:120px">Efectivo</th>
                        <th class="right" style="width:120px">Tarjeta</th>
                        <th class="right" style="width:120px">Transf.</th>
                        <th class="right" style="width:120px">Otros</th>
                        <th class="right" style="width:140px">Total</th>
                        <th>Obs.</th>
                        <th style="width:120px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="11" class="muted">Sin resultados.</td>
                        </tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr>
                                <td><?= (int)$r['id_recaudacion'] ?></td>
                                <td><?= e($r['fecha']) ?></td>
                                <td><?= e($r['creado_por'] ?? ('#' . ($r['id_usuario'] ?? ''))) ?></td>
                                <td>
                                    <?php if (strcasecmp($r['estado'], 'Depositada') === 0): ?>
                                        <span class="badge d">Depositada</span>
                                    <?php else: ?>
                                        <span class="badge p"><?= e($r['estado']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="right"><?= n($r['t_efectivo'], 0) ?></td>
                                <td class="right"><?= n($r['t_tarjeta'], 0) ?></td>
                                <td class="right"><?= n($r['t_transferencia'], 0) ?></td>
                                <td class="right"><?= n($r['t_otros'], 0) ?></td>
                                <td class="right"><strong><?= n($r['t_total'], 0) ?></strong></td>
                                <td class="muted"><?= e($r['observacion'] ?? '') ?></td>
                                <td>
                                    <a class="btn" href="recaudacion_detalle.php?id=<?= (int)$r['id_recaudacion'] ?>">Ver</a>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>

            <!-- Paginación -->
            <div class="pager">
                <?php if ($page > 1): ?>
                    <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">« Anterior</a>
                <?php endif; ?>
                <span class="muted">Página <?= (int)$page ?> de <?= (int)$totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Siguiente »</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
</body>

</html>