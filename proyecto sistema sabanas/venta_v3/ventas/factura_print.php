<?php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}
require_once __DIR__ . '/../../conexion/configv2.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x, $d = 0) { return number_format((float)$x, $d, ',', '.'); }
function normaIva($tipo)
{
    $tipo = strtoupper(trim((string)$tipo));
    if ($tipo === 'IVA10' || $tipo === 'IVA 10' || $tipo === '10%' || $tipo === '10' || strpos($tipo, '10') !== false) return 'IVA10';
    if ($tipo === 'IVA5'  || $tipo === 'IVA 5'  || $tipo === '5%'  || $tipo === '5'  || strpos($tipo, '5') !== false)  return 'IVA5';
    return 'EXE';
}
function ivaLabel($tipo)
{
    $cod = normaIva($tipo);
    if ($cod === 'IVA10') return '10%';
    if ($cod === 'IVA5')  return '5%';
    return 'Exento';
}
function ivaRate($tipo)
{
    $cod = normaIva($tipo);
    if ($cod === 'IVA10') return 0.10;
    if ($cod === 'IVA5')  return 0.05;
    return 0.0;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('ID inválido');
}

$sql = "
  SELECT
    f.*,
    c.nombre, c.apellido, c.ruc_ci,
    COALESCE(c.direccion,'') AS direccion,
    t.establecimiento, t.punto_expedicion, t.numero_timbrado, t.fecha_fin
  FROM public.factura_venta_cab f
  JOIN public.clientes c ON c.id_cliente = f.id_cliente
  LEFT JOIN public.timbrado t ON t.id_timbrado = f.id_timbrado
  WHERE f.id_factura = $1
  LIMIT 1
";
$r = pg_query_params($conn, $sql, [$id]);
if (!$r || pg_num_rows($r) === 0) {
    http_response_code(404);
    die('Factura no encontrada');
}
$F = pg_fetch_assoc($r);

$sqlD = "
  SELECT descripcion, unidad, cantidad, precio_unitario, tipo_iva, iva_monto, subtotal_neto
  FROM public.factura_venta_det
  WHERE id_factura = $1
  ORDER BY descripcion
";
$rd = pg_query_params($conn, $sqlD, [$id]);

$rows = [];
$g10 = 0.0; $i10 = 0.0; $g5 = 0.0; $i5 = 0.0; $ex = 0.0; $total_visible = 0.0;

if ($rd) {
    while ($d = pg_fetch_assoc($rd)) {
        $qty     = (float)$d['cantidad'];
        $precio  = (float)$d['precio_unitario'];   // precio con IVA
        $tipoIva = normaIva($d['tipo_iva']);
        $rate    = ivaRate($tipoIva);

        $importe = (float)$d['subtotal_neto'];     // con IVA (puede ser negativo)
        $iva     = (float)$d['iva_monto'];

        if ($rate > 0) {
            if ($iva == 0.0) {
                $base = round($importe / (1 + $rate), 2);
                $iva  = round($importe - $base, 2);
            } else {
                $base = round($importe - $iva, 2);
            }
        } else {
            $base = round($importe, 2);
            $iva  = 0.0;
        }

        if ($tipoIva === 'IVA10') { $g10 += $base; $i10 += $iva; }
        elseif ($tipoIva === 'IVA5') { $g5 += $base; $i5 += $iva; }
        else { $ex += $base; }

        $total_visible += $importe;
        $rows[] = [
            'cantidad' => $qty,
            'descripcion' => $d['descripcion'],
            'unidad' => $d['unidad'],
            'precio' => $precio,
            'tipo_iva_label' => ivaLabel($tipoIva),
            'importe' => $importe
        ];
    }
}

$g10 = round($g10, 2); $i10 = round($i10, 2);
$g5  = round($g5 , 2); $i5  = round($i5 , 2);
$ex  = round($ex , 2);
$total_visible = round($total_visible, 2);

$estado = strtolower($F['estado'] ?? '');
$esAnulada = ($estado === 'anulada');
$cliente = trim(($F['nombre'] ?? '') . ' ' . ($F['apellido'] ?? ''));
$esCredito = (strcasecmp($F['condicion_venta'] ?? '', 'Credito') === 0);

$header_total = isset($F['total_neto']) ? (float)$F['total_neto'] : (float)($F['total_factura'] ?? 0.0);
$diffTotal = round($total_visible - $header_total, 2);
$hayDiferencia = (abs($diffTotal) > 0.01);

$pendienteContado = null;
if (!$esCredito) {
    $rp = pg_query_params($conn, "
    WITH aplic AS (
      SELECT SUM(monto_aplicado)::numeric(14,2) AS aplicado
      FROM public.recibo_cobranza_det_aplic
      WHERE id_factura = $1
    )
    SELECT (f.total_neto - COALESCE(ap.aplicado,0))::numeric(14,2) AS pendiente
    FROM public.factura_venta_cab f
    LEFT JOIN aplic ap ON TRUE
    WHERE f.id_factura = $1
  ", [$id]);
    if ($rp) {
        $pendienteContado = (float)pg_fetch_result($rp, 0, 0);
    }
}

$cuotas = [];
if ($esCredito) {
    $rCxc = pg_query_params($conn, "
    SELECT
      COALESCE(nro_cuota,1) AS nro_cuota,
      fecha_vencimiento,
      COALESCE(capital,0)::numeric(14,2)   AS capital,
      COALESCE(interes,0)::numeric(14,2)   AS interes,
      monto_origen::numeric(14,2)          AS total_cuota,
      saldo_actual::numeric(14,2)          AS saldo,
      estado
    FROM public.cuenta_cobrar
    WHERE id_factura = $1
    ORDER BY COALESCE(nro_cuota,1)
  ", [$id]);
    if ($rCxc) {
        while ($x = pg_fetch_assoc($rCxc)) { $cuotas[] = $x; }
    }
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Factura <?= e($F['numero_documento']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(135deg, #ffe5f4 0%, #f9e8ff 45%, #fef9ff 100%);
            --surface: rgba(255, 255, 255, 0.94);
            --surface-soft: rgba(255, 255, 255, 0.85);
            --border: rgba(214, 51, 132, 0.18);
            --border-strong: rgba(214, 51, 132, 0.3);
            --shadow: 0 32px 60px rgba(188, 70, 137, 0.22);
            --text: #411f31;
            --muted: #9d6f8b;
            --accent: #d63384;
            --accent-alt: #7f5dff;
            --warn: #c26a07;
            --ok: #1f7a4d;
            --danger: #c5304a;
            --radius: 24px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font: 14px/1.55 "Poppins", system-ui, -apple-system, Segoe UI, sans-serif;
            color: var(--text);
            background: var(--bg);
            position: relative;
            padding: 32px 18px 42px;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            filter: blur(150px);
            z-index: 0;
            opacity: .55;
        }

        body::before {
            top: -160px;
            left: -140px;
            background: rgba(214, 51, 132, 0.32);
        }

        body::after {
            bottom: -200px;
            right: -140px;
            background: rgba(127, 93, 255, 0.26);
        }

        .actions {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            padding: 14px 18px;
            margin: -32px -18px 24px;
            background: rgba(255, 255, 255, 0.72);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
            box-shadow: 0 18px 36px rgba(64, 21, 53, 0.16);
            border-radius: 0 0 24px 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 999px;
            border: 1px solid rgba(214, 51, 132, 0.2);
            background: rgba(255, 255, 255, 0.85);
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 24px rgba(64, 21, 53, 0.16);
            background: rgba(255, 255, 255, 0.95);
        }

        .btn.primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-alt));
            border-color: transparent;
            color: #fff;
            box-shadow: 0 16px 32px rgba(188, 70, 137, 0.26);
        }

        .btn.primary:hover {
            box-shadow: 0 22px 38px rgba(188, 70, 137, 0.32);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .4px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(214, 51, 132, 0.18);
            color: var(--muted);
        }

        .badge.ok {
            background: rgba(76, 201, 145, 0.16);
            border-color: rgba(76, 201, 145, 0.26);
            color: var(--ok);
        }

        .badge.warn {
            background: rgba(255, 193, 7, 0.18);
            border-color: rgba(255, 193, 7, 0.26);
            color: var(--warn);
        }

        .badge.anulada {
            background: rgba(245, 101, 101, 0.18);
            border-color: rgba(245, 101, 101, 0.28);
            color: var(--danger);
        }

        .sheet {
            position: relative;
            z-index: 1;
            width: min(1020px, 100%);
            margin: 0 auto;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 38px 42px 46px;
        }

        .watermark {
            position: absolute;
            inset: 0;
            pointer-events: none;
            display: <?= $esAnulada ? 'block' : 'none' ?>;
        }

        .watermark::after {
            content: 'ANULADA';
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-20deg);
            font-size: 140px;
            color: rgba(197, 48, 74, 0.14);
            font-weight: 800;
            letter-spacing: 10px;
        }

        .head {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 24px;
            margin-bottom: 26px;
        }

        .brand h2 {
            margin: 0 0 8px;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2.1rem;
            letter-spacing: .6px;
        }

        .brand small {
            color: var(--muted);
            font-size: .92rem;
            line-height: 1.6;
        }

        .docbox {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-end;
        }

        .docbox h1 {
            margin: 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2.4rem;
            letter-spacing: .8px;
        }

        .docbox .num {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 14px;
            background: rgba(214, 51, 132, 0.12);
            border: 1px solid rgba(214, 51, 132, 0.2);
            color: var(--accent);
            font-weight: 600;
        }

        .docbox div {
            font-size: .95rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
        }

        .box {
            background: var(--surface-soft);
            border-radius: 18px;
            padding: 18px;
            border: 1px solid var(--border);
            box-shadow: inset 0 0 0 1px rgba(214, 51, 132, 0.08);
        }

        .box h3 {
            margin: 0 0 10px;
            font-size: 1.05rem;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--accent);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 26px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 16px 38px rgba(64, 21, 53, 0.16);
        }

        th,
        td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(214, 51, 132, 0.12);
        }

        th {
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .6px;
            font-size: .92rem;
            background: rgba(214, 51, 132, 0.14);
            color: var(--text);
        }

        tbody tr {
            background: rgba(255, 255, 255, 0.86);
        }

        tbody tr:hover {
            background: rgba(214, 51, 132, 0.08);
        }

        .right {
            text-align: right;
        }

        .muted {
            color: var(--muted);
        }

        tfoot td {
            border: none;
            font-size: .95rem;
        }

        tfoot td.right strong {
            font-size: 1.05rem;
        }

        table.plan-cuotas {
            margin-top: 20px;
            border-radius: 18px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.9);
        }

        table.plan-cuotas th {
            background: rgba(127, 93, 255, 0.18);
            color: #3c2da8;
        }

        table.plan-cuotas td,
        table.plan-cuotas th {
            border: 1px solid rgba(127, 93, 255, 0.16);
        }

        table.plan-cuotas tfoot td {
            font-weight: 600;
        }

        @media (max-width: 720px) {
            body {
                padding: 20px 12px 32px;
            }

            .actions {
                margin: -20px -12px 18px;
                border-radius: 0 0 18px 18px;
            }

            .sheet {
                padding: 28px 22px 36px;
                border-radius: 20px;
            }

            .head {
                flex-direction: column;
                align-items: flex-start;
            }

            .docbox {
                text-align: left;
                align-items: flex-start;
            }

            table,
            table.plan-cuotas {
                font-size: .9rem;
            }

            th,
            td {
                padding: 10px 12px;
            }
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            body::before,
            body::after,
            .actions,
            .no-print {
                display: none !important;
            }

            .sheet {
                width: 100%;
                margin: 0;
                border-radius: 0;
                border: none;
                box-shadow: none;
                padding: 14mm 16mm;
            }

            table,
            table.plan-cuotas {
                box-shadow: none;
            }

            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>

<body>
    <div id="navbar-container" class="no-print"></div>

    <div class="actions no-print">
        <a href="javascript:window.print()" class="btn primary">Imprimir</a>
        <a href="ui_facturas.php" class="btn">Volver</a>
        <?php if ($esAnulada): ?>
            <span class="badge anulada">Estado: ANULADA</span>
        <?php else: ?>
            <span class="badge">Estado: <?= e($F['estado']) ?></span>
        <?php endif; ?>
        <?php if (!$esCredito && !$esAnulada && $pendienteContado !== null): ?>
            <?php if ($pendienteContado <= 0.01): ?>
                <span class="badge ok">Contado: PAGADA</span>
            <?php else: ?>
                <span class="badge warn">Contado: Saldo pendiente Gs <?= n($pendienteContado, 0) ?></span>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($hayDiferencia): ?>
            <span class="badge warn">Ajuste totales · cab <?= n($header_total, 0) ?> · calc <?= n($total_visible, 0) ?></span>
        <?php endif; ?>
    </div>

    <div class="sheet">
        <div class="watermark"></div>

        <div class="head">
            <div class="brand">
                <h2>Beauty Creations</h2>
                <small>
                    RUC: 80000000-1 · Tel: (021) 000-000 · Asunción, PY<br>
                    Email: ventas@beautycreations.com
                </small>
            </div>
            <div class="docbox">
                <h1>Factura</h1>
                <div>N° <span class="num"><?= e($F['numero_documento']) ?></span></div>
                <?php if (!empty($F['numero_timbrado'])): ?>
                    <div>Timbrado: <strong><?= e($F['numero_timbrado']) ?></strong>
                        <?php if (!empty($F['fecha_fin'])): ?>
                            <span class="muted"> (Vence: <?= e($F['fecha_fin']) ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($F['establecimiento']) || !empty($F['punto_expedicion'])): ?>
                    <div class="muted">Est.: <?= e($F['establecimiento']) ?> · Pto.: <?= e($F['punto_expedicion']) ?></div>
                <?php endif; ?>
                <div>Emisión: <strong><?= e($F['fecha_emision']) ?></strong></div>
                <div>Condición: <strong><?= e($F['condicion_venta']) ?></strong></div>
            </div>
        </div>

        <div class="grid">
            <div class="box">
                <h3>Clienta</h3>
                <div><strong><?= e($cliente) ?></strong></div>
                <div>RUC/CI: <?= e($F['ruc_ci']) ?></div>
                <?php if (!empty($F['direccion'])): ?>
                    <div class="muted">Dirección: <?= e($F['direccion']) ?></div>
                <?php endif; ?>
            </div>
            <div class="box">
                <h3>Referencia</h3>
                <div>Pedido: <?= $F['id_pedido'] ? '#' . e($F['id_pedido']) : '-' ?></div>
                <?php if (!empty($F['observacion'])): ?>
                    <div class="muted">Obs.: <?= e($F['observacion']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:8%">Cant.</th>
                    <th>Descripción</th>
                    <th style="width:10%">Unidad</th>
                    <th class="right" style="width:16%">Precio c/IVA</th>
                    <th class="right" style="width:10%">IVA</th>
                    <th class="right" style="width:16%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $d): ?>
                        <tr>
                            <td><?= e(n($d['cantidad'], 0)) ?></td>
                            <td><?= e($d['descripcion']) ?></td>
                            <td><?= e($d['unidad']) ?></td>
                            <td class="right"><?= e(n($d['precio'], 0)) ?></td>
                            <td class="right"><?= e($d['tipo_iva_label']) ?></td>
                            <td class="right"><?= e(n($d['importe'], 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="muted">Sin ítems cargados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="6">&nbsp;</td></tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="right">Grav. 10%:</td>
                    <td class="right"><?= e(n($g10, 0)) ?></td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="right">IVA 10%:</td>
                    <td class="right"><?= e(n($i10, 0)) ?></td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="right">Grav. 5%:</td>
                    <td class="right"><?= e(n($g5, 0)) ?></td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="right">IVA 5%:</td>
                    <td class="right"><?= e(n($i5, 0)) ?></td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="right">Exentas:</td>
                    <td class="right"><?= e(n($ex, 0)) ?></td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td class="right"><strong>Total:</strong></td>
                    <td class="right"><strong><?= e(n($total_visible, 0)) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <?php if ($esCredito && count($cuotas) > 1): ?>
            <?php
            $sumCap = 0; $sumInt = 0; $sumTot = 0;
            foreach ($cuotas as $c) { $sumCap += (float)$c['capital']; $sumInt += (float)$c['interes']; $sumTot += (float)$c['total_cuota']; }
            ?>
            <h3 style="margin: 20px 0 10px; font-family: 'Playfair Display', 'Poppins', serif;">Plan de cuotas</h3>
            <table class="plan-cuotas" cellpadding="6">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Vencimiento</th>
                        <th style="text-align:right">Capital</th>
                        <th style="text-align:right">Interés</th>
                        <th style="text-align:right">Total cuota</th>
                        <th style="text-align:right">Saldo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cuotas as $c): ?>
                        <tr>
                            <td><?= e($c['nro_cuota']) ?></td>
                            <td><?= e($c['fecha_vencimiento']) ?></td>
                            <td style="text-align:right"><?= n($c['capital'], 0) ?></td>
                            <td style="text-align:right"><?= n($c['interes'], 0) ?></td>
                            <td style="text-align:right"><?= n($c['total_cuota'], 0) ?></td>
                            <td style="text-align:right"><?= n($c['saldo'], 0) ?></td>
                            <td><?= e($c['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:right"><strong>Totales</strong></td>
                        <td style="text-align:right"><strong><?= n($sumCap, 0) ?></strong></td>
                        <td style="text-align:right"><strong><?= n($sumInt, 0) ?></strong></td>
                        <td style="text-align:right"><strong><?= n($sumTot, 0) ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>

        <p class="muted" style="margin-top: 14px;">
            *Documento generado por sistema — Usuario: <?= e($_SESSION['nombre_usuario'] ?? '') ?>.
            <?php if ($esAnulada): ?>
                <strong style="color: var(--danger);">Factura ANULADA.</strong>
            <?php endif; ?>
        </p>
    </div>

    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
    <script>
        (function () {
            try {
                const p = new URLSearchParams(location.search);
                if (p.get('auto') === '1') {
                    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
                }
            } catch (e) { }
        })();
    </script>
</body>

</html>
