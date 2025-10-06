<?php
// oc_print.php — versión formal con datos de proveedor + condición y sucursal
require_once("../conexion/config.php");
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$c) {
    die("DB error");
}

$id_oc = isset($_GET['id_oc']) ? (int)$_GET['id_oc'] : 0;
if ($id_oc <= 0) {
    die("id_oc requerido");
}

/* === Configuración encabezado de tu empresa (editar a gusto) === */
$empresa_nombre   = "Beauty Creations S.R.L.";
$empresa_ruc      = "80000000-1";
$empresa_dir      = "Av. Belleza 123, Asunción";
$empresa_tel      = "+595 21 000 000";
$empresa_email    = "compras@beautycreations.com";
$empresa_logo_url = ""; // si tenés un logo accesible por URL, ponelo aquí

/* === CABECERA OC + PROVEEDOR + CIUDAD/PAÍS + CONDICIÓN + SUCURSAL === */
$sqlCab = "
  SELECT
    oc.id_oc, oc.numero_pedido, oc.id_proveedor, oc.fecha_emision, oc.estado, oc.observacion,
    oc.condicion_pago,
    oc.id_sucursal,
    s.nombre AS sucursal_nombre,

    prov.nombre      AS proveedor,
    prov.ruc         AS proveedor_ruc,
    prov.direccion   AS proveedor_direccion,
    prov.telefono    AS proveedor_telefono,
    prov.email       AS proveedor_email,
    pa.nombre        AS proveedor_pais,
    ci.nombre        AS proveedor_ciudad
  FROM public.orden_compra_cab oc
  LEFT JOIN public.proveedores prov ON prov.id_proveedor = oc.id_proveedor
  LEFT JOIN public.paises pa       ON pa.id_pais = prov.id_pais
  LEFT JOIN public.ciudades ci     ON ci.id_ciudad = prov.id_ciudad
  LEFT JOIN public.sucursales s    ON s.id_sucursal = oc.id_sucursal
  WHERE oc.id_oc = $1
  LIMIT 1
";
$rc = pg_query_params($c, $sqlCab, [$id_oc]);
if (!$rc || pg_num_rows($rc) == 0) {
    die("OC no encontrada");
}
$cab = pg_fetch_assoc($rc);

/* === DETALLE === */
$sqlDet = "
  SELECT d.id_oc_det, d.id_producto, p.nombre AS producto,
         d.cantidad, d.precio_unit
  FROM public.orden_compra_det d
  JOIN public.producto p ON p.id_producto = d.id_producto
  WHERE d.id_oc = $1
  ORDER BY d.id_oc_det
";
$rd = pg_query_params($c, $sqlDet, [$id_oc]);
$det = [];
$total = 0.0;
if ($rd) {
    while ($d = pg_fetch_assoc($rd)) {
        $det[] = $d;
        $total += (float)$d['cantidad'] * (float)$d['precio_unit'];
    }
}

/* === Helpers === */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function n2($x)
{
    return number_format((float)$x, 2, ',', '.');
}
function n0($x)
{
    return number_format((float)$x, 0, ',', '.');
}

// Armado proveedor (evita " - , ," si falta algo)
$proveedor_linea1 = trim(($cab['proveedor'] ?: $cab['id_proveedor']));
$proveedor_linea2 = trim(($cab['proveedor_direccion'] ?? ''));
$proveedor_linea3 = trim(implode(' - ', array_filter([
    $cab['proveedor_ciudad'] ?? '',
    $cab['proveedor_pais'] ?? ''
])));
$proveedor_linea4 = trim(implode(' • ', array_filter([
    $cab['proveedor_telefono'] ? ('Tel: ' . $cab['proveedor_telefono']) : '',
    $cab['proveedor_email'] ? ('Email: ' . $cab['proveedor_email']) : ''
])));
$proveedor_ruc = $cab['proveedor_ruc'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>OC #<?php echo h($cab['id_oc']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(135deg, #ffe5f4 0%, #f6f0ff 48%, #fef9ff 100%);
            --surface: rgba(255, 255, 255, 0.92);
            --border: rgba(214, 51, 132, 0.16);
            --border-strong: rgba(214, 51, 132, 0.28);
            --shadow: 0 32px 58px rgba(64, 21, 53, 0.18);
            --muted: #9d6f8b;
            --text: #411f31;
            --accent: #d63384;
            --accent-soft: rgba(214, 51, 132, 0.12);
            --accent-alt: #7f5dff;
            --radius: 18px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 34px 24px 50px;
            min-height: 100vh;
            font-family: "Poppins", system-ui, -apple-system, Segoe UI, sans-serif;
            color: var(--text);
            background: var(--bg);
            position: relative;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            width: 440px;
            height: 440px;
            border-radius: 50%;
            filter: blur(160px);
            z-index: 0;
            opacity: 0.55;
        }

        body::before {
            top: -180px;
            left: -160px;
            background: rgba(214, 51, 132, 0.32);
        }

        body::after {
            bottom: -220px;
            right: -140px;
            background: rgba(127, 93, 255, 0.26);
        }

        .document {
            position: relative;
            z-index: 1;
            max-width: 920px;
            margin: 0 auto;
            background: var(--surface);
            border-radius: 26px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            padding: 40px 48px 46px;
        }

        .header {
            display: flex;
            gap: 20px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 18px;
            margin-bottom: 26px;
            align-items: center;
        }

        .logo {
            width: 110px;
            height: 110px;
            border-radius: 26px;
            background: rgba(214, 51, 132, 0.08);
            border: 1px dashed var(--border-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 12px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            overflow: hidden;
        }

        .logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .empresa h2 {
            margin: 0 0 6px;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2rem;
            letter-spacing: 0.8px;
        }

        .empresa .muted {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .title {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 18px;
            margin-bottom: 24px;
        }

        .title h1 {
            margin: 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2.2rem;
            letter-spacing: 0.8px;
        }

        .badge {
            border-radius: 999px;
            padding: 12px 18px;
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid var(--border-strong);
            font-size: 0.95rem;
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 12px 26px rgba(214, 51, 132, 0.18);
        }

        .badge b {
            color: var(--text);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .box {
            border-radius: 20px;
            border: 1px solid var(--border);
            padding: 18px;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: inset 0 0 0 1px rgba(214, 51, 132, 0.08);
        }

        .box h3 {
            margin: 0 0 10px;
            font-size: 1.05rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent);
        }

        .box b {
            font-weight: 600;
        }

        .muted {
            color: var(--muted);
        }

        .notes-text {
            font-size: 0.92rem;
            line-height: 1.6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 28px rgba(64, 21, 53, 0.12);
        }

        th,
        td {
            padding: 12px 16px;
            border: 1px solid rgba(214, 51, 132, 0.1);
        }

        th {
            background: rgba(214, 51, 132, 0.14);
            font-weight: 600;
            font-size: 0.96rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text);
        }

        tbody tr:nth-child(every) {
            background: rgba(255, 255, 255, 0.6);
        }

        tbody tr:hover {
            background: rgba(214, 51, 132, 0.08);
        }

        .right {
            text-align: right;
        }

        .totals {
            margin-top: 26px;
            display: flex;
            justify-content: flex-end;
        }

        .sum {
            min-width: 280px;
            border-radius: 20px;
            border: 1px solid var(--border);
            padding: 18px 20px;
            background: rgba(255, 255, 255, 0.85);
            box-shadow: 0 12px 30px rgba(64, 21, 53, 0.16);
        }

        .sum .row {
            display: flex;
            justify-content: space-between;
            margin: 6px 0;
            font-size: 0.98rem;
        }

        .sum .row.total {
            font-weight: 600;
            font-size: 1.05rem;
            border-top: 1px solid rgba(214, 51, 132, 0.2);
            padding-top: 8px;
            margin-top: 10px;
        }

        .footer {
            margin-top: 34px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .sign {
            border: 1px dashed rgba(214, 51, 132, 0.32);
            border-radius: 20px;
            padding: 20px;
            min-height: 160px;
            background: rgba(255, 255, 255, 0.84);
            position: relative;
        }

        .sign .line {
            margin-top: 70px;
            border-top: 1px solid rgba(214, 51, 132, 0.26);
            padding-top: 8px;
            text-align: center;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .print {
            text-align: right;
            margin-top: 30px;
        }

        .print button {
            border: none;
            border-radius: 999px;
            padding: 12px 24px;
            font-size: 0.98rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-alt) 100%);
            box-shadow: 0 16px 32px rgba(127, 93, 255, 0.24);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .print button:hover {
            transform: translateY(-1px);
            box-shadow: 0 22px 40px rgba(127, 93, 255, 0.28);
        }

        .print button:active {
            transform: translateY(0);
            box-shadow: 0 14px 24px rgba(127, 93, 255, 0.26);
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            body::before,
            body::after {
                display: none;
            }

            .document {
                border: none;
                border-radius: 0;
                box-shadow: none;
                padding: 24px 18px;
            }

            .print {
                display: none;
            }
        }

        @media (max-width: 720px) {
            body {
                padding: 20px 12px 32px;
            }

            .document {
                padding: 30px 24px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .title {
                flex-direction: column;
                align-items: flex-start;
            }

            .grid,
            .footer {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="document">
        <!-- Encabezado -->
        <div class="header">
            <div class="logo">
                <?php if ($empresa_logo_url): ?>
                    <img src="<?php echo h($empresa_logo_url); ?>" alt="Logo">
                <?php else: ?>
                    LOGO
                <?php endif; ?>
            </div>
            <div class="empresa">
                <h2><?php echo h($empresa_nombre); ?></h2>
                <div class="muted">RUC: <?php echo h($empresa_ruc); ?></div>
                <div class="muted"><?php echo h($empresa_dir); ?></div>
                <div class="muted">Tel: <?php echo h($empresa_tel); ?> • <?php echo h($empresa_email); ?></div>
            </div>
        </div>

        <div class="title">
            <h1>Orden de Compra #<?php echo (int)$cab['id_oc']; ?></h1>
            <div class="badge">
                Fecha emisión: <b><?php echo h($cab['fecha_emision']); ?></b>
                <span>•</span>
                Estado: <b><?php echo h($cab['estado']); ?></b>
            </div>
        </div>

        <div class="grid">
            <div class="box">
                <h3>Proveedor</h3>
                <div><b><?php echo h($proveedor_linea1); ?></b></div>
                <?php if ($proveedor_ruc): ?><div>RUC: <?php echo h($proveedor_ruc); ?></div><?php endif; ?>
                <?php if ($proveedor_linea2): ?><div><?php echo h($proveedor_linea2); ?></div><?php endif; ?>
                <?php if ($proveedor_linea3): ?><div><?php echo h($proveedor_linea3); ?></div><?php endif; ?>
                <?php if ($proveedor_linea4): ?><div class="muted"><?php echo h($proveedor_linea4); ?></div><?php endif; ?>
            </div>

            <div class="box">
                <h3>Pedido relacionado</h3>
                <div>Pedido #: <b><?php echo (int)$cab['numero_pedido']; ?></b></div>
                <?php if (!empty($cab['observacion'])): ?>
                    <div style="margin-top:8px"><span class="muted">Obs:</span> <?php echo nl2br(h($cab['observacion'])); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid" style="margin-top: 20px;">
            <div class="box">
                <h3>Condición y sucursal</h3>
                <div>Condición de pago: <b><?php echo h($cab['condicion_pago'] ?: 'CONTADO'); ?></b></div>
                <div>Sucursal: <b><?php echo h($cab['sucursal_nombre'] ?: ($cab['id_sucursal'] ? 'ID ' . $cab['id_sucursal'] : '—')); ?></b></div>
            </div>
            <div class="box">
                <h3>Notas</h3>
                <div class="muted notes-text">
                    Indicar número de OC en factura y remisión. Cualquier diferencia en cantidades o precios debe informarse
                    antes del despacho. La entrega debe coordinarse con el área de recepción de la sucursal indicada.
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ID Producto</th>
                    <th>Descripción</th>
                    <th class="right">Cantidad</th>
                    <th class="right">Precio Unit.</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($det as $i => $d):
                    $lt = (float)$d['cantidad'] * (float)$d['precio_unit']; ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo (int)$d['id_producto']; ?></td>
                        <td><?php echo h($d['producto']); ?></td>
                        <td class="right"><?php echo n0($d['cantidad']); ?></td>
                        <td class="right"><?php echo n2($d['precio_unit']); ?></td>
                        <td class="right"><?php echo n2($lt); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="sum">
                <div class="row"><span>Subtotal</span><span><?php echo n2($total); ?></span></div>
                <!-- <div class="row"><span>IVA (10%)</span><span><?php // echo n2($total*0.10); ?></span></div> -->
                <div class="row total"><span>Total</span><span><?php echo n2($total); ?></span></div>
            </div>
        </div>

        <div class="footer">
            <div class="sign">
                <div><b>Condiciones de compra</b></div>
                <div class="muted notes-text" style="margin-top:8px">
                    Plazo de entrega, garantía y condiciones de pago según acuerdo. En caso de discrepancias,
                    prevalece esta OC. Indicar número de OC en la factura y remisión.
                </div>
                <div class="line">Firma y sello del proveedor</div>
            </div>
            <div class="sign">
                <div><b>Autorización</b></div>
                <div class="muted notes-text" style="margin-top:8px">
                    Aprobado por: ___________________________<br>
                    Fecha: ________________________
                </div>
                <div class="line">Firma autorizado por Beauty Creations</div>
            </div>
        </div>

        <div class="print">
            <button onclick="window.print()">Imprimir / Guardar PDF</button>
        </div>
    </div>
</body>

</html>
