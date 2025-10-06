<?php
// recibo_print.php — Vista A4 lista para imprimir
session_start();
if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
    exit;
}
require_once __DIR__ . '/../../conexion/configv2.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function n($x, $d = 0) { return number_format((float)$x, $d, ',', '.'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); die('ID de recibo inválido'); }

// CABECERA
$sqlCab = "
  SELECT r.id_recibo, r.fecha, r.total_recibo, r.estado, COALESCE(r.observacion,'') AS observacion,
         r.id_cliente,
         c.nombre, c.apellido, c.ruc_ci, COALESCE(c.direccion,'') AS direccion
  FROM public.recibo_cobranza_cab r
  JOIN public.clientes c ON c.id_cliente = r.id_cliente
  WHERE r.id_recibo = $1
  LIMIT 1
";
$rc = pg_query_params($conn, $sqlCab, [$id]);
if (!$rc || pg_num_rows($rc) === 0) { http_response_code(404); die('Recibo no encontrado'); }
$R = pg_fetch_assoc($rc);
$cliente = trim(($R['nombre'] ?? '') . ' ' . ($R['apellido'] ?? ''));
$estado  = strtolower($R['estado'] ?? '');
$esAnulado = ($estado === 'anulado');

// MEDIOS DE PAGO
$sqlPag = "
  SELECT
    p.medio_pago,
    COALESCE(p.referencia,'') AS referencia,
    COALESCE(p.importe_bruto,0)::numeric(14,2) AS importe,
    COALESCE(p.comision,0)::numeric(14,2)      AS comision,
    COALESCE(p.fecha_acredit, r.fecha)         AS fecha_acredit,
    CASE
      WHEN p.id_cuenta_bancaria IS NOT NULL THEN
        COALESCE(b.banco,'Banco')||' · '||COALESCE(b.numero_cuenta,'s/n')||
        ' ('||COALESCE(b.moneda,'')||' '||COALESCE(b.tipo,'')||')'
      WHEN LOWER(p.medio_pago)='efectivo' THEN 'Caja'
      ELSE '—'
    END AS cuenta_label
  FROM public.recibo_cobranza_det_pago p
  JOIN public.recibo_cobranza_cab r ON r.id_recibo = p.id_recibo
  LEFT JOIN public.cuenta_bancaria b ON b.id_cuenta_bancaria = p.id_cuenta_bancaria
  WHERE p.id_recibo = $1
  ORDER BY p.id_recibo, p.medio_pago
";
$rp = pg_query_params($conn, $sqlPag, [$id]);

// APLICACIONES A FACTURAS
$sqlApl = "
  SELECT a.id_factura,
         a.monto_aplicado::numeric(14,2) AS monto,
         COALESCE(f.numero_documento,'(s/n)') AS numero_documento
  FROM public.recibo_cobranza_det_aplic a
  LEFT JOIN public.factura_venta_cab f ON f.id_factura = a.id_factura
  WHERE a.id_recibo = $1
  ORDER BY a.id_factura
";
$ra = pg_query_params($conn, $sqlApl, [$id]);

// DETALLE DE CUOTAS
$sqlCuotas = "
  SELECT
    f.numero_documento,
    cxc.nro_cuota,
    COALESCE(cxc.cant_cuotas,1) AS cant_cuotas,
    cxc.fecha_vencimiento AS vencimiento,
    mc.monto::numeric(14,2) AS pagado
  FROM public.movimiento_cxc mc
  JOIN public.cuenta_cobrar cxc ON cxc.id_cxc = mc.id_cxc
  JOIN public.factura_venta_cab f ON f.id_factura = cxc.id_factura
  WHERE mc.tipo='Pago' AND mc.referencia = $1
  ORDER BY f.numero_documento, cxc.nro_cuota
";
$refRec = 'Recibo #' . $id;
$rcu = pg_query_params($conn, $sqlCuotas, [$refRec]);

// Fallback de aplicaciones si corresponde
$aplFallback = [];
if ($ra && pg_num_rows($ra) === 0 && $rcu && pg_num_rows($rcu) > 0) {
    while ($row = pg_fetch_assoc($rcu)) {
        $aplFallback[$row['numero_documento']] = ($aplFallback[$row['numero_documento']] ?? 0) + (float)$row['pagado'];
    }
    pg_free_result($rcu);
    $rcu = pg_query_params($conn, $sqlCuotas, [$refRec]);
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Recibo #<?= e($R['id_recibo']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(135deg, #ffe6f6 0%, #f8e8ff 45%, #fef9ff 100%);
            --surface: rgba(255, 255, 255, 0.94);
            --surface-soft: rgba(255, 255, 255, 0.86);
            --border: rgba(214, 51, 132, 0.18);
            --border-strong: rgba(214, 51, 132, 0.32);
            --shadow: 0 32px 58px rgba(188, 70, 137, 0.22);
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
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding: 14px 18px;
            margin: -32px -18px 24px;
            background: rgba(255, 255, 255, 0.75);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
            box-shadow: 0 18px 36px rgba(64, 21, 53, 0.16);
            border-radius: 0 0 24px 24px;
            z-index: 2;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 999px;
            border: 1px solid rgba(214, 51, 132, 0.18);
            background: rgba(255, 255, 255, 0.85);
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 24px rgba(64, 21, 53, 0.18);
            background: rgba(255, 255, 255, 0.96);
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
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(214, 51, 132, 0.18);
            color: var(--muted);
        }

        .badge.anulado {
            background: rgba(245, 101, 101, 0.18);
            border-color: rgba(245, 101, 101, 0.28);
            color: var(--danger);
        }

        .sheet {
            position: relative;
            z-index: 1;
            width: min(960px, 100%);
            margin: 0 auto;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 36px 40px 44px;
        }

        .watermark {
            position: absolute;
            inset: 0;
            pointer-events: none;
            display: <?= $esAnulado ? 'block' : 'none' ?>;
        }

        .watermark::after {
            content: 'ANULADO';
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
            padding-bottom: 22px;
            margin-bottom: 26px;
        }

        .brand h2 {
            margin: 0 0 8px;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2.05rem;
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
            font-size: 2.3rem;
            letter-spacing: .7px;
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

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
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
            font-size: 1.04rem;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: var(--accent);
        }

        .section-title {
            margin: 24px 0 10px;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 1.35rem;
            color: var(--accent);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 12px;
            box-shadow: 0 16px 34px rgba(64, 21, 53, 0.16);
        }

        th,
        td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(214, 51, 132, 0.12);
            background: rgba(255, 255, 255, 0.86);
        }

        th {
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .6px;
            font-size: .92rem;
            font-weight: 600;
            background: rgba(214, 51, 132, 0.14);
        }

        tbody tr:hover td {
            background: rgba(214, 51, 132, 0.08);
        }

        .right {
            text-align: right;
        }

        .muted {
            color: var(--muted);
        }

        tfoot td {
            font-weight: 600;
            background: rgba(214, 51, 132, 0.1);
        }

        @media (max-width: 720px) {
            body {
                padding: 22px 12px 32px;
            }

            .actions {
                margin: -22px -12px 18px;
                border-radius: 0 0 18px 18px;
            }

            .sheet {
                padding: 28px 22px 34px;
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

            table {
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

            table {
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
        <a href="javascript:window.close()" class="btn">Cerrar</a>
        <?php if ($esAnulado): ?>
            <span class="badge anulado">Estado: ANULADO</span>
        <?php else: ?>
            <span class="badge">Estado: <?= e($R['estado']) ?></span>
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
                <h1>Recibo</h1>
                <div>N° <span class="num"><?= e($R['id_recibo']) ?></span></div>
                <div>Fecha: <strong><?= e($R['fecha']) ?></strong></div>
                <div>Total recibido: <strong><?= n($R['total_recibo'], 0) ?></strong></div>
            </div>
        </div>

        <div class="grid">
            <div class="box">
                <h3>Clienta</h3>
                <div><strong><?= e($cliente) ?></strong></div>
                <div>RUC/CI: <?= e($R['ruc_ci']) ?></div>
                <?php if (!empty($R['direccion'])): ?>
                    <div class="muted">Dirección: <?= e($R['direccion']) ?></div>
                <?php endif; ?>
            </div>
            <div class="box">
                <h3>Observación</h3>
                <div class="muted"><?= nl2br(e($R['observacion'])) ?></div>
            </div>
        </div>

        <h3 class="section-title">Medios de pago</h3>
        <table>
            <thead>
                <tr>
                    <th>Medio</th>
                    <th>Referencia</th>
                    <th>Cuenta</th>
                    <th>Fecha acredit.</th>
                    <th class="right">Importe</th>
                    <th class="right">Comisión</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sumPagos = 0; $sumCom = 0;
                if ($rp && pg_num_rows($rp) > 0):
                    while ($p = pg_fetch_assoc($rp)):
                        $sumPagos += (float)$p['importe'];
                        $sumCom   += (float)$p['comision']; ?>
                        <tr>
                            <td><?= e($p['medio_pago']) ?></td>
                            <td><?= e($p['referencia']) ?></td>
                            <td><?= e($p['cuenta_label']) ?></td>
                            <td><?= e($p['fecha_acredit']) ?></td>
                            <td class="right"><?= n($p['importe'], 0) ?></td>
                            <td class="right"><?= n($p['comision'], 0) ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="6" class="muted">Sin medios de pago cargados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="right">Totales</td>
                    <td class="right"><?= n($sumPagos, 0) ?></td>
                    <td class="right"><?= n($sumCom, 0) ?></td>
                </tr>
            </tfoot>
        </table>

        <h3 class="section-title">Aplicado a documentos</h3>
        <table>
            <thead>
                <tr>
                    <th>Documento</th>
                    <th class="right">Monto aplicado</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sumApl = 0;
                if ($ra && pg_num_rows($ra) > 0):
                    while ($a = pg_fetch_assoc($ra)):
                        $sumApl += (float)$a['monto']; ?>
                        <tr>
                            <td>Factura <?= e($a['numero_documento']) ?> (ID <?= (int)$a['id_factura'] ?>)</td>
                            <td class="right"><?= n($a['monto'], 0) ?></td>
                        </tr>
                    <?php endwhile;
                elseif (!empty($aplFallback)):
                    foreach ($aplFallback as $doc => $monto):
                        $sumApl += (float)$monto; ?>
                        <tr>
                            <td>Factura <?= e($doc) ?></td>
                            <td class="right"><?= n($monto, 0) ?></td>
                        </tr>
                <?php endforeach;
                else: ?>
                    <tr><td colspan="2" class="muted">Este recibo aún no tiene aplicaciones registradas.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="right">Total aplic.</td>
                    <td class="right"><?= n($sumApl, 0) ?></td>
                </tr>
            </tfoot>
        </table>

        <h3 class="section-title">Detalle de cuotas cobradas</h3>
        <table>
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Cuota</th>
                    <th>Vencimiento</th>
                    <th class="right">Pagado</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sumCuotas = 0;
                if ($rcu && pg_num_rows($rcu) > 0):
                    while ($c = pg_fetch_assoc($rcu)):
                        $sumCuotas += (float)$c['pagado']; ?>
                        <tr>
                            <td><?= e($c['numero_documento']) ?></td>
                            <td><?= (int)$c['nro_cuota'] ?>/<?= (int)$c['cant_cuotas'] ?></td>
                            <td><?= $c['vencimiento'] ? e($c['vencimiento']) : '—' ?></td>
                            <td class="right"><?= n($c['pagado'], 0) ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" class="muted">Sin cuotas asociadas a este recibo.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="right">Total cuotas</td>
                    <td class="right"><?= n($sumCuotas, 0) ?></td>
                </tr>
            </tfoot>
        </table>

        <p class="muted" style="margin-top: 12px;">
            *Documento generado por sistema — Usuario: <?= e($_SESSION['nombre_usuario'] ?? '') ?>.
            <?php if ($esAnulado): ?>
                <strong style="color: var(--danger);">Recibo ANULADO.</strong>
            <?php endif; ?>
        </p>
    </div>

    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js" class="no-print"></script>
    <?php if (!empty($_GET['auto'])): ?>
        <script>
            window.addEventListener('load', () => setTimeout(() => window.print(), 150));
        </script>
    <?php endif; ?>
</body>

</html>
