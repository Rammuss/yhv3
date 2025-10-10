<?php
/**
 * API CxP (GET) — con cuotas
 * - Detalle por id_cxp (incluye cuotas, OPs y movimientos)
 * - Listado paginado con KPIs de cuotas (próximo venc., vencido, saldo de cuotas abiertas)
 *
 * Cambios:
 *  - LEFT JOIN a factura_compra_cab (incluye CxP sin factura: reposición FF)
 *  - Búsqueda q incluye c.observacion
 *  - Fechas por COALESCE(f.fecha_emision, c.fecha_emision)
 *  - Filtros nuevos: solo_ff, solo_sin_factura, excluir_ff
 *  - Flag es_ff en la respuesta
 */

session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$idCxp = isset($_GET['id_cxp']) ? (int)$_GET['id_cxp'] : 0;

/* ------------------------- helpers ------------------------- */
function bad(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
function ok(array $payload = []): void {
    echo json_encode(['ok' => true] + $payload);
    exit;
}
function parse_bool($value, bool $default = false): bool {
    if ($value === null) return $default;
    if (is_bool($value)) return $value;
    $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $filtered === null ? $default : $filtered;
}
function parse_date(?string $value): ?string {
    if (!$value) return null;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}
function es_ff_row(array $r): bool {
    // Heurística: id_factura NULL o la observación arranca con “Reposición FF”
    return ($r['id_factura'] === null || (isset($r['observacion']) && stripos($r['observacion'], 'Reposición FF') === 0));
}

/* -------------------- detalle por id_cxp ------------------- */
if ($idCxp > 0) {
    // header CxP (LEFT JOIN para incluir CxP sin factura - reposición FF)
    $sql = "
        SELECT c.id_cxp,
               c.id_factura,
               c.id_proveedor,
               c.fecha_emision,
               c.fecha_venc,
               c.total_cxp,
               c.saldo_actual,
               c.estado,
               c.observacion,
               c.moneda,
               f.numero_documento,
               f.fecha_emision AS factura_fecha,
               f.moneda        AS factura_moneda,
               f.total_factura,
               f.condicion,
               f.cuotas,
               f.dias_plazo,
               f.intervalo_dias,
               f.timbrado_numero,
               f.id_sucursal,
               s.nombre        AS sucursal_nombre,
               p.nombre        AS proveedor_nombre,
               p.ruc           AS proveedor_ruc
        FROM public.cuenta_pagar c
        LEFT JOIN public.factura_compra_cab f ON f.id_factura = c.id_factura
        JOIN public.proveedores p             ON p.id_proveedor = c.id_proveedor
        LEFT JOIN public.sucursales s         ON s.id_sucursal = f.id_sucursal
        WHERE c.id_cxp = $1
        LIMIT 1
    ";
    $stmt = pg_query_params($conn, $sql, [$idCxp]);
    if (!$stmt) bad('Error al obtener la cuenta por pagar', 500);
    $header = pg_fetch_assoc($stmt);
    if (!$header) bad('Cuenta por pagar no encontrada', 404);

    // órdenes de pago vinculadas
    $sqlOps = "
        SELECT opd.id_orden_pago,
               opc.fecha,
               opc.total,
               opc.moneda,
               opc.estado,
               opc.id_cuenta_bancaria,
               cb.banco,
               cb.numero_cuenta,
               cb.moneda AS moneda_cuenta,
               opd.monto_aplicado,
               ch.id            AS id_cheque,
               ch.numero_cheque,
               ch.estado        AS estado_cheque,
               ch.fecha_cheque,
               ch.monto_cheque
        FROM public.orden_pago_det opd
        JOIN public.orden_pago_cab opc   ON opc.id_orden_pago = opd.id_orden_pago
        JOIN public.cuenta_bancaria cb   ON cb.id_cuenta_bancaria = opc.id_cuenta_bancaria
        LEFT JOIN public.cheques ch      ON ch.id_orden_pago = opc.id_orden_pago
        WHERE opd.id_cxp = $1
        ORDER BY opc.fecha DESC, opc.id_orden_pago DESC
    ";
    $stmtOps = pg_query_params($conn, $sqlOps, [$idCxp]);
    if (!$stmtOps) bad('Error al obtener órdenes vinculadas', 500);

    $ordenes = [];
    while ($op = pg_fetch_assoc($stmtOps)) {
        $ordenes[] = [
            'id_orden_pago'   => (int)$op['id_orden_pago'],
            'fecha'           => $op['fecha'],
            'total'           => (float)$op['total'],
            'moneda'          => $op['moneda'],
            'estado'          => $op['estado'],
            'cuenta'          => [
                'id'     => (int)$op['id_cuenta_bancaria'],
                'banco'  => $op['banco'],
                'numero' => $op['numero_cuenta'],
                'moneda' => $op['moneda_cuenta'],
            ],
            'monto_aplicado'  => (float)$op['monto_aplicado'],
            'cheque'          => $op['id_cheque'] ? [
                'id'            => (int)$op['id_cheque'],
                'numero'        => $op['numero_cheque'],
                'estado'        => $op['estado_cheque'],
                'fecha'         => $op['fecha_cheque'],
                'monto'         => (float)$op['monto_cheque'],
            ] : null,
        ];
    }

    // movimientos (opcional)
    $includeMovs = parse_bool($_GET['with_movimientos'] ?? true, true);
    $movs = [];
    if ($includeMovs) {
        $sqlMov = "
            SELECT id_mov, fecha, ref_tipo, ref_id, signo, monto, moneda, concepto, created_at
            FROM public.cuenta_pagar_mov
            WHERE id_cxp = $1
            ORDER BY fecha ASC, id_mov ASC
        ";
        $stmtMov = pg_query_params($conn, $sqlMov, [$idCxp]);
        if (!$stmtMov) bad('Error al obtener movimientos', 500);
        $saldo = 0.0;
        while ($m = pg_fetch_assoc($stmtMov)) {
            $signo = (int)$m['signo'];
            $monto = (float)$m['monto'] * $signo;
            $saldo += $monto;
            $movs[] = [
                'id_mov'        => (int)$m['id_mov'],
                'fecha'         => $m['fecha'],
                'ref_tipo'      => $m['ref_tipo'],
                'ref_id'        => $m['ref_id'] !== null ? (int)$m['ref_id'] : null,
                'signo'         => $signo,
                'monto'         => (float)$m['monto'],
                'moneda'        => $m['moneda'],
                'concepto'      => $m['concepto'],
                'created_at'    => $m['created_at'],
                'saldo_parcial' => $saldo
            ];
        }
    }

    // agregados de cuotas + detalle (opcional)
    $includeCuotas = parse_bool($_GET['with_cuotas'] ?? true, true);

    $sqlAggCuotas = "
      SELECT
        COUNT(*)                                                      AS cuotas_total,
        COUNT(*) FILTER (WHERE estado IN ('Pendiente','Parcial'))     AS cuotas_abiertas,
        COUNT(*) FILTER (WHERE estado = 'Cancelada')                  AS cuotas_canceladas,
        SUM(CASE WHEN estado IN ('Pendiente','Parcial') THEN saldo_cuota ELSE 0 END) AS saldo_cuotas_abiertas,
        MIN(CASE WHEN estado IN ('Pendiente','Parcial') THEN fecha_venc END)         AS proximo_venc,
        SUM(CASE WHEN estado IN ('Pendiente','Parcial') AND fecha_venc < CURRENT_DATE
                 THEN saldo_cuota ELSE 0 END) AS monto_vencido
      FROM public.cuenta_det_x_pagar
      WHERE id_cxp = $1
    ";
    $stmtAgg = pg_query_params($conn, $sqlAggCuotas, [$idCxp]);
    $agg = $stmtAgg ? pg_fetch_assoc($stmtAgg) : null;

    $cuotas = [];
    if ($includeCuotas) {
        $sqlCuotas = "
          SELECT id_cxp_det, id_cxp, nro_cuota, fecha_venc,
                 monto_cuota, saldo_cuota, estado, observacion
          FROM public.cuenta_det_x_pagar
          WHERE id_cxp = $1
          ORDER BY nro_cuota ASC
        ";
        $stmtCuotas = pg_query_params($conn, $sqlCuotas, [$idCxp]);
        if (!$stmtCuotas) bad('Error al obtener cuotas', 500);
        while ($d = pg_fetch_assoc($stmtCuotas)) {
            $cuotas[] = [
                'id_cxp_det'  => (int)$d['id_cxp_det'],
                'nro'         => (int)$d['nro_cuota'],
                'vencimiento' => $d['fecha_venc'],
                'monto'       => (float)$d['monto_cuota'],
                'saldo'       => (float)$d['saldo_cuota'],
                'estado'      => $d['estado'],
                'obs'         => $d['observacion'],
            ];
        }
    }

    $esFF = es_ff_row($header);

    ok([
        'cxp' => [
            'id_cxp'        => (int)$header['id_cxp'],
            'id_factura'    => $header['id_factura'] !== null ? (int)$header['id_factura'] : null,
            'proveedor'     => [
                'id'     => (int)$header['id_proveedor'],
                'nombre' => $header['proveedor_nombre'],
                'ruc'    => $header['proveedor_ruc'],
            ],
            'documento'     => [
                'numero'   => $header['numero_documento'] ?: $header['observacion'],
                'timbrado' => $header['timbrado_numero'],
            ],
            'fecha_emision' => $header['fecha_emision'],
            'fecha_venc'    => $header['fecha_venc'],
            'total_cxp'     => (float)$header['total_cxp'],
            'saldo_actual'  => (float)$header['saldo_actual'],
            'estado'        => $header['estado'],
            'observacion'   => $header['observacion'],
            'moneda'        => $header['moneda'],
            'factura'       => [
                'fecha'          => $header['factura_fecha'],
                'moneda'         => $header['factura_moneda'],
                'total_factura'  => $header['total_factura'] !== null ? (float)$header['total_factura'] : null,
                'condicion'      => $header['condicion'],
                'cuotas'         => $header['cuotas'] !== null ? (int)$header['cuotas'] : null,
                'dias_plazo'     => $header['dias_plazo'] !== null ? (int)$header['dias_plazo'] : null,
                'intervalo_dias' => $header['intervalo_dias'] !== null ? (int)$header['intervalo_dias'] : null,
            ],
            'sucursal'      => [
                'id'     => $header['id_sucursal'] !== null ? (int)$header['id_sucursal'] : null,
                'nombre' => $header['sucursal_nombre'],
            ],
            'es_ff'         => $esFF,
            // agregados de cuotas
            'cuotas_info' => $agg ? [
                'total'            => (int)$agg['cuotas_total'],
                'abiertas'         => (int)$agg['cuotas_abiertas'],
                'canceladas'       => (int)$agg['cuotas_canceladas'],
                'saldo_abiertas'   => (float)$agg['saldo_cuotas_abiertas'],
                'proximo_venc'     => $agg['proximo_venc'],
                'monto_vencido'    => (float)$agg['monto_vencido'],
            ] : null,
            'cuotas' => $includeCuotas ? $cuotas : null,
        ],
        'ordenes_pago' => $ordenes,
        'movimientos'  => $movs,
    ]);
}

/* -------------------- listado paginado --------------------- */
$page      = max(1, (int)($_GET['page'] ?? 1));
$pageSize  = max(1, min(200, (int)($_GET['page_size'] ?? 50)));
$offset    = ($page - 1) * $pageSize;
$includeTotals = parse_bool($_GET['with_totals'] ?? false, false);

$estadoFiltroRaw   = trim($_GET['estado'] ?? '');
$estadoFiltroLista = $estadoFiltroRaw === '' ? [] : array_filter(array_map('trim', explode(',', $estadoFiltroRaw)));
$estadoIncluyeCanceladas = false;
foreach ($estadoFiltroLista as $estadoVal) {
    if (strcasecmp($estadoVal, 'Cancelada') === 0 || strcasecmp($estadoVal, 'Anulada') === 0) {
        $estadoIncluyeCanceladas = true;
        break;
    }
}
$includeCanceladas = parse_bool($_GET['incluir_canceladas'] ?? false, false);

// NUEVOS filtros
$soloFF          = parse_bool($_GET['solo_ff'] ?? null, false);
$soloSinFactura  = parse_bool($_GET['solo_sin_factura'] ?? null, false);
$excluirFF       = parse_bool($_GET['excluir_ff'] ?? null, false);

$where  = [];
$params = [];
$idx    = 1;

// por defecto, solo abiertas (si no se piden canceladas) y con saldo > 0
if (!$includeCanceladas && !$estadoIncluyeCanceladas) {
    $where[] = "(c.estado = ANY(ARRAY['Pendiente','Parcial']) OR c.saldo_actual > 0)";
    $where[] = "c.saldo_actual > 0";
}

// búsqueda libre: incluye c.observacion (para CxP sin factura)
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $where[] = "("
             . "p.nombre ILIKE $" . $idx
             . " OR p.ruc ILIKE $" . $idx
             . " OR COALESCE(f.numero_documento,'') ILIKE $" . $idx
             . " OR COALESCE(f.observacion,'') ILIKE $" . $idx
             . " OR COALESCE(c.observacion,'') ILIKE $" . $idx
             . ")";
    $params[] = '%' . $q . '%'; $idx++;
}

// filtros directos
if (!empty($_GET['id_proveedor'])) { $where[] = "c.id_proveedor = $" . $idx; $params[] = (int)$_GET['id_proveedor']; $idx++; }
if (!empty($_GET['id_sucursal']))  { $where[] = "f.id_sucursal = $" . $idx;   $params[] = (int)$_GET['id_sucursal'];  $idx++; }
if (!empty($_GET['moneda']))        { $where[] = "c.moneda = $" . $idx;        $params[] = $_GET['moneda'];           $idx++; }
if (!empty($_GET['condicion']))     { $where[] = "UPPER(COALESCE(f.condicion,'')) = $" . $idx; $params[] = strtoupper($_GET['condicion']); $idx++; }

// estados
if ($estadoFiltroLista) {
    $placeholders = [];
    foreach ($estadoFiltroLista as $estado) { $placeholders[] = '$' . $idx; $params[] = $estado; $idx++; }
    $where[] = 'c.estado IN (' . implode(',', $placeholders) . ')';
}

// fechas por EMISIÓN: usar COALESCE(f.fecha_emision, c.fecha_emision)
if (($fechaDesde = parse_date($_GET['fecha_desde'] ?? null))) { $where[] = "COALESCE(f.fecha_emision, c.fecha_emision) >= $" . $idx; $params[] = $fechaDesde; $idx++; }
if (($fechaHasta = parse_date($_GET['fecha_hasta'] ?? null))) { $where[] = "COALESCE(f.fecha_emision, c.fecha_emision) <= $" . $idx; $params[] = $fechaHasta; $idx++; }

// vencimiento madre
if (($vencDesde = parse_date($_GET['venc_desde'] ?? null))) { $where[] = "c.fecha_venc >= $" . $idx; $params[] = $vencDesde; $idx++; }
if (($vencHasta = parse_date($_GET['venc_hasta'] ?? null))) { $where[] = "c.fecha_venc <= $" . $idx; $params[] = $vencHasta; $idx++; }
if (parse_bool($_GET['solo_vencidas'] ?? false, false)) { $where[] = "c.fecha_venc < CURRENT_DATE"; }

// ==== filtros basados en cuotas ====
if (parse_bool($_GET['solo_vencidas_cuotas'] ?? false, false)) {
    $where[] = "EXISTS (
        SELECT 1 FROM public.cuenta_det_x_pagar d
        WHERE d.id_cxp = c.id_cxp
          AND d.estado IN ('Pendiente','Parcial')
          AND d.fecha_venc < CURRENT_DATE
    )";
}
if (($vencDesdeCuota = parse_date($_GET['cuota_venc_desde'] ?? null))) {
    $where[] = "EXISTS (SELECT 1 FROM public.cuenta_det_x_pagar d WHERE d.id_cxp = c.id_cxp AND d.fecha_venc >= $" . $idx . ")";
    $params[] = $vencDesdeCuota; $idx++;
}
if (($vencHastaCuota = parse_date($_GET['cuota_venc_hasta'] ?? null))) {
    $where[] = "EXISTS (SELECT 1 FROM public.cuenta_det_x_pagar d WHERE d.id_cxp = c.id_cxp AND d.fecha_venc <= $" . $idx . ")";
    $params[] = $vencHastaCuota; $idx++;
}

/* -------- filtros NUEVOS: FF / sin factura -------- */
$condFF = "(c.id_factura IS NULL OR c.observacion ILIKE 'Reposición FF%')";
if ($soloFF) {
    $where[] = $condFF;
} elseif ($soloSinFactura) {
    $where[] = "c.id_factura IS NULL";
} elseif ($excluirFF) {
    $where[] = "NOT " . $condFF;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// FROM base + joins LATERAL (último mov, OPs y CUOTAS)
// LEFT JOIN a factura para incluir reposiciones
$fromSql = "
    FROM public.cuenta_pagar c
    LEFT JOIN public.factura_compra_cab f ON f.id_factura = c.id_factura
    JOIN public.proveedores p             ON p.id_proveedor = c.id_proveedor
    LEFT JOIN public.sucursales s         ON s.id_sucursal = f.id_sucursal

    LEFT JOIN LATERAL (
        SELECT m.fecha, m.monto, m.signo, m.ref_tipo
        FROM public.cuenta_pagar_mov m
        WHERE m.id_cxp = c.id_cxp
        ORDER BY m.fecha DESC, m.id_mov DESC
        LIMIT 1
    ) lm ON true

    LEFT JOIN LATERAL (
        SELECT
            COUNT(*)                                                      AS total_op,
            COUNT(*) FILTER (WHERE opc.estado IN ('Reservada','Emitida')) AS op_abiertas,
            SUM(opd.monto_aplicado)                                       AS monto_total_op,
            SUM(CASE WHEN opc.estado IN ('Reservada','Emitida') THEN opd.monto_aplicado ELSE 0 END) AS monto_reservado,
            MAX(opc.fecha)                                                AS ultima_fecha,
            (ARRAY_AGG(opc.estado ORDER BY opc.fecha DESC, opc.id_orden_pago DESC))[1] AS ultimo_estado,
            (ARRAY_AGG(opc.id_orden_pago ORDER BY opc.fecha DESC, opc.id_orden_pago DESC))[1] AS ultima_op
        FROM public.orden_pago_det opd
        JOIN public.orden_pago_cab opc ON opc.id_orden_pago = opd.id_orden_pago
        WHERE opd.id_cxp = c.id_cxp
    ) op ON true

    -- Agregados de CUOTAS
    LEFT JOIN LATERAL (
      SELECT
        COUNT(*)                                                      AS cuotas_total,
        COUNT(*) FILTER (WHERE d.estado IN ('Pendiente','Parcial'))   AS cuotas_abiertas,
        SUM(CASE WHEN d.estado IN ('Pendiente','Parcial') THEN d.saldo_cuota ELSE 0 END) AS saldo_cuotas_abiertas,
        MIN(CASE WHEN d.estado IN ('Pendiente','Parcial') THEN d.fecha_venc END)         AS proximo_venc,
        SUM(CASE WHEN d.estado IN ('Pendiente','Parcial') AND d.fecha_venc < CURRENT_DATE THEN d.saldo_cuota ELSE 0 END) AS monto_vencido
      FROM public.cuenta_det_x_pagar d
      WHERE d.id_cxp = c.id_cxp
    ) cq ON true
";

$sqlList = "
    SELECT c.id_cxp,
           c.id_factura,
           c.id_proveedor,
           c.fecha_emision,
           c.fecha_venc,
           c.total_cxp,
           c.saldo_actual,
           c.estado,
           c.observacion,
           c.moneda,
           f.numero_documento,
           COALESCE(f.fecha_emision, c.fecha_emision) AS factura_fecha,
           f.moneda        AS factura_moneda,
           f.total_factura,
           f.condicion,
           f.cuotas,
           f.dias_plazo,
           f.intervalo_dias,
           f.timbrado_numero,
           f.id_sucursal,
           s.nombre        AS sucursal_nombre,
           p.nombre        AS proveedor_nombre,
           p.ruc           AS proveedor_ruc,
           (CURRENT_DATE - c.fecha_venc) AS dias_al_venc,
           CASE WHEN c.fecha_venc < CURRENT_DATE THEN TRUE ELSE FALSE END AS vencida,
           lm.fecha        AS ultimo_mov_fecha,
           lm.monto        AS ultimo_mov_monto,
           lm.signo        AS ultimo_mov_signo,
           lm.ref_tipo     AS ultimo_mov_tipo,

           COALESCE(op.total_op,0)          AS total_op,
           COALESCE(op.op_abiertas,0)       AS op_abiertas,
           COALESCE(op.monto_reservado,0)   AS monto_reservado,
           COALESCE(op.monto_total_op,0)    AS monto_total_op,
           op.ultima_fecha,
           op.ultimo_estado,
           op.ultima_op,

           cq.cuotas_total,
           cq.cuotas_abiertas,
           cq.saldo_cuotas_abiertas,
           cq.proximo_venc    AS cuotas_proximo_venc,
           cq.monto_vencido   AS cuotas_monto_vencido

    $fromSql
    $whereSql
    ORDER BY c.fecha_venc ASC, p.nombre ASC
    LIMIT $" . $idx . " OFFSET $" . ($idx + 1) . "
";
$paramsList = $params;
$paramsList[] = $pageSize;
$paramsList[] = $offset;

$stmtList = pg_query_params($conn, $sqlList, $paramsList);
if (!$stmtList) bad('Error al listar cuentas por pagar', 500);

// Totales (opcional)
$sqlCount = "
    SELECT COUNT(*) AS total_rows,
           COALESCE(SUM(c.saldo_actual), 0) AS saldo_por_pagar,
           COALESCE(SUM(c.total_cxp), 0)    AS total_facturado,
           COALESCE(SUM(CASE WHEN c.estado = 'Pendiente' THEN c.saldo_actual ELSE 0 END), 0) AS saldo_pendiente,
           COALESCE(SUM(CASE WHEN c.estado = 'Parcial'   THEN c.saldo_actual ELSE 0 END), 0) AS saldo_parcial,
           COALESCE(SUM(CASE WHEN c.fecha_venc < CURRENT_DATE AND c.saldo_actual > 0 THEN c.saldo_actual ELSE 0 END), 0) AS saldo_vencido,
           COALESCE(SUM(op_res.monto_reservado_cxp), 0) AS saldo_reservado_op
    $fromSql
    LEFT JOIN LATERAL (
        SELECT SUM(CASE WHEN opc.estado IN ('Reservada','Emitida') THEN opd.monto_aplicado ELSE 0 END) AS monto_reservado_cxp
        FROM public.orden_pago_det opd
        JOIN public.orden_pago_cab opc ON opc.id_orden_pago = opd.id_orden_pago
        WHERE opd.id_cxp = c.id_cxp
    ) op_res ON true
    $whereSql
";
$stmtCount = pg_query_params($conn, $sqlCount, $params);
if (!$stmtCount) bad('Error al calcular totales', 500);
$meta = pg_fetch_assoc($stmtCount);
$totalRows = (int)$meta['total_rows'];

// construir respuesta
$data = [];
while ($row = pg_fetch_assoc($stmtList)) {
    $esFF = ($row['id_factura'] === null) || (isset($row['observacion']) && stripos($row['observacion'], 'Reposición FF') === 0);
    $data[] = [
        'id_cxp'        => (int)$row['id_cxp'],
        'id_factura'    => $row['id_factura'] !== null ? (int)$row['id_factura'] : null,
        'proveedor'     => [
            'id'     => (int)$row['id_proveedor'],
            'nombre' => $row['proveedor_nombre'],
            'ruc'    => $row['proveedor_ruc'],
        ],
        'documento'     => [
            'numero'   => $row['numero_documento'] ?: $row['observacion'],
            'timbrado' => $row['timbrado_numero'],
        ],
        'fecha_emision' => $row['factura_fecha'],
        'fecha_venc'    => $row['fecha_venc'],
        'dias_al_venc'  => (int)$row['dias_al_venc'],
        'vencida'       => $row['vencida'] === 't',
        'condicion'     => $row['condicion'],
        'moneda'        => $row['moneda'],
        'total_factura' => $row['total_factura'] !== null ? (float)$row['total_factura'] : null,
        'total_cxp'     => (float)$row['total_cxp'],
        'saldo_actual'  => (float)$row['saldo_actual'],
        'estado'        => $row['estado'],
        'observacion'   => $row['observacion'],
        'sucursal'      => [
            'id'     => $row['id_sucursal'] !== null ? (int)$row['id_sucursal'] : null,
            'nombre' => $row['sucursal_nombre'],
        ],
        'es_ff'         => $esFF,
        'ultimo_movimiento' => $row['ultimo_mov_fecha'] ? [
            'fecha' => $row['ultimo_mov_fecha'],
            'monto' => (float)$row['ultimo_mov_monto'],
            'signo' => $row['ultimo_mov_signo'] !== null ? (int)$row['ultimo_mov_signo'] : null,
            'tipo'  => $row['ultimo_mov_tipo'],
        ] : null,
        'orden_pago' => [
            'total'           => (int)$row['total_op'],
            'abiertas'        => (int)$row['op_abiertas'],
            'monto_reservado' => (float)$row['monto_reservado'],
            'monto_total'     => (float)$row['monto_total_op'],
            'ultima_fecha'    => $row['ultima_fecha'],
            'ultimo_estado'   => $row['ultimo_estado'],
            'ultima_op'       => $row['ultima_op'] ? (int)$row['ultima_op'] : null,
        ],
        // KPI de cuotas para la grilla
        'cuotas' => [
            'total'          => (int)($row['cuotas_total'] ?? 0),
            'abiertas'       => (int)($row['cuotas_abiertas'] ?? 0),
            'saldo_abiertas' => (float)($row['saldo_cuotas_abiertas'] ?? 0),
            'proximo_venc'   => $row['cuotas_proximo_venc'],
            'monto_vencido'  => (float)($row['cuotas_monto_vencido'] ?? 0),
        ],
    ];
}

$response = [
    'page'      => $page,
    'page_size' => $pageSize,
    'total'     => $totalRows,
    'data'      => $data,
];

if ($includeTotals) {
    $response['summary'] = [
        'saldo_por_pagar'    => (float)$meta['saldo_por_pagar'],
        'total_facturado'    => (float)$meta['total_facturado'],
        'saldo_pendiente'    => (float)$meta['saldo_pendiente'],
        'saldo_parcial'      => (float)$meta['saldo_parcial'],
        'saldo_vencido'      => (float)$meta['saldo_vencido'],
        'saldo_reservado_op' => (float)$meta['saldo_reservado_op'],
    ];
}

ok($response);
