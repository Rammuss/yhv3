<?php
/**
 * Gestión de Órdenes de Pago (lectura, entrega, impresión, anulación)
 * GET   /ordenes_pago_gestion_api.php           -> listar órdenes (filtros)
 * GET   /ordenes_pago_gestion_api.php?id=123    -> detalle (incluye cuotas abiertas por CxP)
 * PATCH /ordenes_pago_gestion_api.php?id=123    -> acciones: entrega, anular, imprimir
 *
 * Requiere en public.cuenta_pagar:
 *   es_ff boolean DEFAULT false
 *   id_ff integer
 *   id_rendicion integer
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

function bad(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
function ok(array $payload = []): void {
    echo json_encode(['ok' => true] + $payload);
    exit;
}
function read_json(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function parse_bool($val, bool $default = false): bool {
    if ($val === null) return $default;
    if (is_bool($val)) return $val;
    $filtered = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $filtered === null ? $default : $filtered;
}

/** Obtiene la cabecera de la OP y su cheque (opcional). Con FOR UPDATE si $forUpdate=true. */
function fetch_op($conn, int $id, bool $forUpdate = false) {
    $lock = $forUpdate ? 'FOR UPDATE' : '';
    $sqlCab = "
        SELECT opc.id_orden_pago,
               opc.fecha,
               opc.id_proveedor,
               p.nombre AS proveedor_nombre,
               p.ruc    AS proveedor_ruc,
               opc.id_cuenta_bancaria,
               cb.banco,
               cb.numero_cuenta,
               cb.moneda AS cuenta_moneda,
               opc.total,
               opc.moneda AS moneda_op,
               opc.estado,
               opc.observacion,
               opc.created_at
        FROM public.orden_pago_cab opc
        JOIN public.proveedores p      ON p.id_proveedor = opc.id_proveedor
        JOIN public.cuenta_bancaria cb ON cb.id_cuenta_bancaria = opc.id_cuenta_bancaria
        WHERE opc.id_orden_pago = $1
        $lock
    ";
    $stmtCab = pg_query_params($conn, $sqlCab, [$id]);
    if (!$stmtCab || !pg_num_rows($stmtCab)) return null;
    $cab = pg_fetch_assoc($stmtCab);

    $lockCheque = $forUpdate ? 'FOR UPDATE' : '';
    $sqlCheque = "
        SELECT id,
               numero_cheque,
               estado,
               fecha_cheque,
               fecha_entrega,
               recibido_por,
               observaciones,
               ci,
               impreso_at,
               impreso_por,
               id_chequera
        FROM public.cheques
        WHERE id_orden_pago = $1
        ORDER BY id DESC
        LIMIT 1
        $lockCheque
    ";
    $stmtCheque = pg_query_params($conn, $sqlCheque, [$id]);
    $cheque = $stmtCheque && pg_num_rows($stmtCheque) ? pg_fetch_assoc($stmtCheque) : null;

    $cab['cheque'] = $cheque;
    return $cab;
}

/** === Helpers para cuotas (detecta tabla/columnas) === */
function find_cuotas_table($conn): array {
    static $meta = null;
    if ($meta !== null) return $meta;

    $q1 = @pg_query($conn, "SELECT id_cxp_det, id_cxp, COALESCE(saldo_cuota,0) AS saldo, estado, observacion FROM public.cuenta_det_x_pagar LIMIT 1");
    if ($q1 !== false) {
        $meta = ['table' => 'public.cuenta_det_x_pagar', 'saldo_col' => 'saldo_cuota', 'obs_col' => 'observacion', 'estado_col' => 'estado'];
        return $meta;
    }
    $q2 = @pg_query($conn, "SELECT id_cxp_det, id_cxp, COALESCE(saldo,0) AS saldo, estado, observacion FROM public.cuenta_pagar_det LIMIT 1");
    if ($q2 !== false) {
        $meta = ['table' => 'public.cuenta_pagar_det', 'saldo_col' => 'saldo', 'obs_col' => 'observacion', 'estado_col' => 'estado'];
        return $meta;
    }
    bad('No se encontró la tabla de cuotas (cuenta_det_x_pagar / cuenta_pagar_det).', 500);
    return [];
}

/** Lista cuotas abiertas de una CxP ordenadas FIFO (vencimiento, nro). */
function fetch_cuotas_abiertas_by_cxp($conn, int $id_cxp): array {
    $m = find_cuotas_table($conn);
    $sql = "
      SELECT id_cxp_det,
             id_cxp,
             COALESCE({$m['saldo_col']},0) AS saldo,
             {$m['estado_col']} AS estado,
             COALESCE({$m['obs_col']},'') AS obs,
             CASE WHEN to_regclass('public.cuenta_det_x_pagar') IS NOT NULL THEN fecha_venc ELSE NULL END AS fecha_venc,
             CASE WHEN to_regclass('public.cuenta_det_x_pagar') IS NOT NULL THEN nro_cuota ELSE NULL END AS nro_cuota
      FROM {$m['table']}
      WHERE id_cxp = $1
        AND {$m['estado_col']} IN ('Pendiente','Parcial')
        AND COALESCE({$m['saldo_col']},0) > 0
      ORDER BY fecha_venc NULLS LAST, nro_cuota NULLS LAST, id_cxp_det ASC
    ";
    $st = pg_query_params($conn, $sql, [$id_cxp]);
    if (!$st) return [];
    $out = [];
    while ($r = pg_fetch_assoc($st)) {
        $out[] = [
            'id_cxp_det' => (int)$r['id_cxp_det'],
            'id_cxp'     => (int)$r['id_cxp'],
            'nro'        => $r['nro_cuota'] !== null ? (int)$r['nro_cuota'] : null,
            'fecha_venc' => $r['fecha_venc'],
            'saldo'      => (float)$r['saldo'],
            'estado'     => $r['estado'],
            'obs'        => $r['obs'],
        ];
    }
    return $out;
}

/** Actualiza observación y/o estado de una cuota. */
function update_cuota_obs_estado($conn, int $id_cxp_det, ?string $newObs, ?string $newEstado): bool {
    $m = find_cuotas_table($conn);
    if ($newObs !== null && $newEstado !== null) {
        $sql = "UPDATE {$m['table']} SET {$m['obs_col']}=$1, {$m['estado_col']}=$2 WHERE id_cxp_det=$3";
        return (bool) pg_query_params($conn, $sql, [$newObs, $newEstado, $id_cxp_det]);
    } elseif ($newObs !== null) {
        $sql = "UPDATE {$m['table']} SET {$m['obs_col']}=$1 WHERE id_cxp_det=$2";
        return (bool) pg_query_params($conn, $sql, [$newObs, $id_cxp_det]);
    } else {
        $sql = "UPDATE {$m['table']} SET {$m['estado_col']}=$1 WHERE id_cxp_det=$2";
        return (bool) pg_query_params($conn, $sql, [$newEstado, $id_cxp_det]);
    }
}

/** Descuenta/repone saldo de la cuota y setea estado según saldo (>0 Parcial/Pendiente, 0 Cancelada). */
function apply_cuota_saldo($conn, int $id_cxp_det, float $deltaSigno): array {
    $m = find_cuotas_table($conn);
    $st = pg_query_params($conn, "
        SELECT id_cxp_det, id_cxp, COALESCE({$m['saldo_col']},0) AS saldo, {$m['estado_col']} AS estado,
               COALESCE({$m['obs_col']},'') AS obs
        FROM {$m['table']} WHERE id_cxp_det=$1 FOR UPDATE", [$id_cxp_det]);
    if (!$st || !pg_num_rows($st)) bad("Cuota $id_cxp_det no encontrada", 404);
    $c = pg_fetch_assoc($st);

    $newSaldo = max(((float)$c['saldo']) + $deltaSigno, 0);
    $newEstado = $deltaSigno < 0
        ? ($newSaldo <= 0 ? 'Cancelada' : 'Entregada')
        : ($newSaldo > 0 ? 'Pendiente' : 'Cancelada');

    $ok  = pg_query_params($conn, "UPDATE {$m['table']} SET {$m['saldo_col']}=$1, {$m['estado_col']}=$2 WHERE id_cxp_det=$3",
        [$newSaldo, $newEstado, $id_cxp_det]);
    if (!$ok) bad("No se pudo actualizar saldo de la cuota $id_cxp_det", 500);

    return ['id_cxp' => (int)$c['id_cxp'], 'old_saldo' => (float)$c['saldo'], 'new_saldo' => $newSaldo, 'estado' => $newEstado, 'obs' => $c['obs']];
}

/** Recalcula estado de la cabecera CxP; si saldo_actual = 0, fuerza Cancelada. */
function recalc_cxp_estado($conn, int $id_cxp): void {
    $m = find_cuotas_table($conn);

    $stCab = pg_query_params($conn, "SELECT saldo_actual FROM public.cuenta_pagar WHERE id_cxp=$1 FOR UPDATE", [$id_cxp]);
    if (!$stCab || !pg_num_rows($stCab)) bad('CxP no encontrada', 500);
    $saldoActual = (float)pg_fetch_result($stCab, 0, 0);

    if ($saldoActual <= 0.0000001) {
        pg_query_params($conn, "UPDATE public.cuenta_pagar SET saldo_actual=0, estado='Cancelada' WHERE id_cxp=$1", [$id_cxp]);
        return;
    }

    $sqlOpen = "
        SELECT COUNT(*)::int AS abiertas
        FROM {$m['table']}
        WHERE id_cxp = $1
          AND {$m['estado_col']} IN ('Pendiente','Parcial')
          AND COALESCE({$m['saldo_col']},0) > 0
    ";
    $st = pg_query_params($conn, $sqlOpen, [$id_cxp]);
    if (!$st) bad('No se pudo evaluar el estado de la CxP', 500);
    $abiertas = (int)pg_fetch_result($st, 0, 0);

    $nuevo = $abiertas === 0 ? 'Cancelada' : 'Parcial';
    pg_query_params($conn, "UPDATE public.cuenta_pagar SET estado=$1 WHERE id_cxp=$2", [$nuevo, $id_cxp]);
}

/** -------- GET -------- */
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $head = fetch_op($conn, $id, false);
        if (!$head) bad('Orden de pago no encontrada', 404);

        // Detalle de CxP de la OP (soporta con/sin factura)
        $sqlDet = "
          SELECT
            opd.id_cxp,
            COALESCE(f.numero_documento, cxp.observacion, '—') AS numero_documento,
            cxp.fecha_venc,
            cxp.saldo_actual,
            opd.monto_aplicado,
            opd.moneda
          FROM public.orden_pago_det opd
          JOIN public.cuenta_pagar cxp
            ON cxp.id_cxp = opd.id_cxp
          LEFT JOIN public.factura_compra_cab f
            ON f.id_factura = cxp.id_factura
          WHERE opd.id_orden_pago = $1
          ORDER BY opd.id_det
        ";
        $stmtDet = pg_query_params($conn, $sqlDet, [$id]);
        if (!$stmtDet) bad('Error al obtener detalle', 500);

        $detalles = [];
        while ($row = pg_fetch_assoc($stmtDet)) {
            $idCxp = (int)$row['id_cxp'];

            // cuotas abiertas de esa CxP
            $q = pg_query_params(
              $conn,
              "SELECT id_cxp_det, id_cxp, nro_cuota, fecha_venc,
                      monto_cuota, saldo_cuota AS saldo, estado
               FROM public.cuenta_det_x_pagar
               WHERE id_cxp = $1
                 AND estado IN ('Pendiente','Parcial')
                 AND saldo_cuota > 0
               ORDER BY fecha_venc ASC, nro_cuota ASC",
              [$idCxp]
            );
            $cuotas = [];
            $restante = (float)$row['monto_aplicado'];
            $sugerido = [];

            if ($q) {
              while ($c = pg_fetch_assoc($q)) {
                $cuotas[] = [
                  'id_cxp_det' => (int)$c['id_cxp_det'],
                  'nro'        => (int)$c['nro_cuota'],
                  'venc'       => $c['fecha_venc'],
                  'monto'      => (float)$c['monto_cuota'],
                  'saldo'      => (float)$c['saldo'],
                  'estado'     => $c['estado'],
                ];
                // sugerencia FIFO hasta cubrir el monto aplicado
                if ($restante > 0) {
                  $aplica = min($restante, (float)$c['saldo']);
                  if ($aplica > 0) {
                    $sugerido[] = [
                      'id_cxp'     => $idCxp,
                      'id_cxp_det' => (int)$c['id_cxp_det'],
                      'monto'      => $aplica,
                    ];
                    $restante -= $aplica;
                  }
                }
              }
            }

            $detalles[] = [
              'id_cxp'         => $idCxp,
              'numero'         => $row['numero_documento'],
              'fecha_venc'     => $row['fecha_venc'],
              'saldo_actual'   => (float)$row['saldo_actual'],
              'monto_aplicado' => (float)$row['monto_aplicado'],
              'moneda'         => $row['moneda'],
              'cuotas_abiertas'=> $cuotas,
              'sugerido'       => $sugerido,
            ];
        }

        // Movimientos bancarios asociados a la OP
        $sqlMov = "
            SELECT id_mov, fecha, tipo, monto, signo, descripcion, created_at
            FROM public.cuenta_bancaria_mov
            WHERE ref_tabla = 'orden_pago' AND ref_id = $1
            ORDER BY fecha DESC, id_mov DESC
        ";
        $stmtMov = pg_query_params($conn, $sqlMov, [$id]);
        if (!$stmtMov) bad('Error al obtener movimientos', 500);
        $movimientos = [];
        while ($m = pg_fetch_assoc($stmtMov)) {
            $movimientos[] = [
                'id_mov'     => (int)$m['id_mov'],
                'fecha'      => $m['fecha'],
                'tipo'       => $m['tipo'],
                'monto'      => (float)$m['monto'],
                'signo'      => (int)$m['signo'],
                'descripcion'=> $m['descripcion'],
                'created_at' => $m['created_at'],
            ];
        }

        $cheque = $head['cheque'];

        ok([
            'orden' => [
                'id_orden_pago'    => (int)$head['id_orden_pago'],
                'fecha'            => $head['fecha'],
                'proveedor'        => [
                    'id'     => (int)$head['id_proveedor'],
                    'nombre' => $head['proveedor_nombre'],
                    'ruc'    => $head['proveedor_ruc'],
                ],
                'cuenta'           => [
                    'id'     => (int)$head['id_cuenta_bancaria'],
                    'banco'  => $head['banco'],
                    'numero' => $head['numero_cuenta'],
                    'moneda' => $head['cuenta_moneda'],
                ],
                'total'            => (float)$head['total'],
                'moneda'           => $head['moneda_op'],
                'estado'           => $head['estado'],
                'observacion'      => $head['observacion'],
                'created_at'       => $head['created_at'],
            ],
            'cheque' => $cheque ? [
                'id'             => (int)$cheque['id'],
                'numero'         => $cheque['numero_cheque'],
                'estado'         => $cheque['estado'],
                'fecha_cheque'   => $cheque['fecha_cheque'],
                'fecha_entrega'  => $cheque['fecha_entrega'],
                'recibido_por'   => $cheque['recibido_por'],
                'observaciones'  => $cheque['observaciones'],
                'ci'             => $cheque['ci'],
                'impreso_at'     => $cheque['impreso_at'],
                'impreso_por'    => $cheque['impreso_por'],
                'id_chequera'    => $cheque['id_chequera'] !== null ? (int)$cheque['id_chequera'] : null,
            ] : null,
            'detalles'    => $detalles,
            'movimientos' => $movimientos,
        ]);
    }

    // ---- listado con filtros ----
    $params = [];
    $filters = [];
    $idx = 1;

    if (!empty($_GET['id_proveedor']))        { $filters[] = 'opc.id_proveedor = $' . $idx;        $params[] = (int)$_GET['id_proveedor'];        $idx++; }
    if (!empty($_GET['id_cuenta_bancaria']))  { $filters[] = 'opc.id_cuenta_bancaria = $' . $idx;  $params[] = (int)$_GET['id_cuenta_bancaria'];  $idx++; }
    if (!empty($_GET['estado']))              { $filters[] = 'opc.estado = $' . $idx;              $params[] = $_GET['estado'];                   $idx++; }
    if (!empty($_GET['fecha_desde']))         { $filters[] = 'opc.fecha >= $' . $idx;              $params[] = $_GET['fecha_desde'];              $idx++; }
    if (!empty($_GET['fecha_hasta']))         { $filters[] = 'opc.fecha <= $' . $idx;              $params[] = $_GET['fecha_hasta'];              $idx++; }
    if (!empty($_GET['q'])) {
        $filters[] = "(p.nombre ILIKE $" . $idx . " OR cb.numero_cuenta ILIKE $" . $idx . " OR ch.numero_cheque ILIKE $" . $idx . ")";
        $params[] = '%' . trim($_GET['q']) . '%'; $idx++;
    }

    $where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

    $sqlList = "
        SELECT opc.id_orden_pago,
               opc.fecha,
               opc.total,
               opc.moneda,
               opc.estado,
               p.nombre AS proveedor_nombre,
               cb.banco,
               cb.numero_cuenta,
               ch.numero_cheque,
               ch.estado   AS cheque_estado,
               ch.fecha_entrega,
               ch.recibido_por
        FROM public.orden_pago_cab opc
        JOIN public.proveedores p      ON p.id_proveedor = opc.id_proveedor
        JOIN public.cuenta_bancaria cb ON cb.id_cuenta_bancaria = opc.id_cuenta_bancaria
        LEFT JOIN public.cheques ch    ON ch.id_orden_pago = opc.id_orden_pago
        $where
        ORDER BY opc.fecha DESC, opc.id_orden_pago DESC
        LIMIT 200
    ";
    $stmtList = pg_query_params($conn, $sqlList, $params);
    if (!$stmtList) bad('Error al listar órdenes', 500);

    $data = [];
    while ($row = pg_fetch_assoc($stmtList)) {
        $data[] = [
            'id_orden_pago' => (int)$row['id_orden_pago'],
            'fecha'         => $row['fecha'],
            'total'         => (float)$row['total'],
            'moneda'        => $row['moneda'],
            'estado'        => $row['estado'],
            'proveedor'     => $row['proveedor_nombre'],
            'cuenta'        => $row['banco'] . ' · ' . $row['numero_cuenta'],
            'cheque_numero' => $row['numero_cheque'],
            'cheque_estado' => $row['cheque_estado'],
            'fecha_entrega' => $row['fecha_entrega'],
            'recibido_por'  => $row['recibido_por'],
        ];
    }
    ok(['data' => $data]);
}

/** -------- PATCH -------- */
if ($method === 'PATCH') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) bad('Orden inválida');
    $input  = read_json();
    $accion = $input['accion'] ?? '';

    /** ---------- ENTREGA (descuenta saldos + marca cuotas Entregada + acredita FF si corresponde) ---------- */
    if ($accion === 'entrega') {
        $fecha      = $input['fecha_entrega'] ?? date('Y-m-d');
        $recibido   = trim($input['recibido_por'] ?? '');
        $ciRecibido = trim($input['ci_recibido'] ?? '');
        $obs        = trim($input['observaciones'] ?? '');
        // cuotas: [{id_cxp, id_cxp_det, monto}]
        $cuotasEnt  = is_array($input['cuotas'] ?? null) ? $input['cuotas'] : [];

        if ($recibido === '') bad('Debe indicar quién recibe el cheque');
        if (empty($cuotasEnt)) bad('Debe indicar las cuotas a entregar con sus montos.');

        pg_query($conn, 'BEGIN');
        $op = fetch_op($conn, $id, true);
        if (!$op) { pg_query($conn, 'ROLLBACK'); bad('Orden no encontrada', 404); }
        if (!$op['cheque'] || $op['cheque']['estado'] !== 'Reservado') {
            pg_query($conn, 'ROLLBACK');
            bad('Sólo se puede registrar la entrega si el cheque está reservado.', 409);
        }

        // Evitar duplicar movimientos bancarios si la entrega ya fue registrada
        $chkMov = pg_query_params(
            $conn,
            "SELECT COUNT(*) 
               FROM public.cuenta_bancaria_mov 
              WHERE ref_tabla = 'orden_pago' AND ref_id = $1 AND tipo = 'PAGO_OP'",
            [$id]
        );
        if (!$chkMov) { pg_query($conn, 'ROLLBACK'); bad('No se pudo validar movimientos existentes', 500); }
        if ((int)pg_fetch_result($chkMov, 0, 0) > 0) {
            pg_query($conn, 'ROLLBACK');
            bad('La orden ya posee un movimiento de pago registrado.', 409);
        }

        // 1) Cheque → Entregado
        $updCheque = pg_query_params(
            $conn,
            "UPDATE public.cheques
             SET estado='Entregado',
                 fecha_entrega=$1,
                 recibido_por=$2,
                 ci = NULLIF($3,'' ),
                 observaciones = $4,
                 updated_at = now()
             WHERE id=$5",
            [$fecha, $recibido, $ciRecibido, ($obs !== '' ? $obs : null), (int)$op['cheque']['id']]
        );
        if (!$updCheque) { pg_query($conn, 'ROLLBACK'); bad('No se pudo actualizar el cheque', 500); }

        // 2) OP → Entregada
        $updOP = pg_query_params($conn, "UPDATE public.orden_pago_cab SET estado='Entregada' WHERE id_orden_pago=$1", [$id]);
        if (!$updOP) { pg_query($conn, 'ROLLBACK'); bad('No se pudo actualizar la orden', 500); }

        // 3) Validar CxP involucradas en la OP
        $cxpIds = [];
        $stmtCxp = pg_query_params($conn, "SELECT DISTINCT id_cxp FROM public.orden_pago_det WHERE id_orden_pago = $1", [$id]);
        if ($stmtCxp) { while ($r = pg_fetch_assoc($stmtCxp)) $cxpIds[(int)$r['id_cxp']] = true; }

        // 4) Para cada cuota: tag, estado y descuento de saldos + MOV PAGO
        $marcadas = [];
        foreach ($cuotasEnt as $q) {
            $idCxp    = (int)($q['id_cxp'] ?? 0);
            $idCxpDet = (int)($q['id_cxp_det'] ?? 0);
            $monto    = (float)($q['monto'] ?? 0);
            if ($idCxp <= 0 || $idCxpDet <= 0 || $monto <= 0) { pg_query($conn, 'ROLLBACK'); bad('Cuota inválida en la entrega.', 422); }
            if (empty($cxpIds[$idCxp])) { pg_query($conn, 'ROLLBACK'); bad("La cuota $idCxpDet no pertenece a una CxP de esta OP.", 422); }

            $m = find_cuotas_table($conn);
            $st = pg_query_params($conn, "
                SELECT {$m['estado_col']} AS estado, COALESCE({$m['saldo_col']},0) AS saldo
                FROM {$m['table']} WHERE id_cxp_det=$1 FOR UPDATE", [$idCxpDet]);
            if (!$st || !pg_num_rows($st)) { pg_query($conn, 'ROLLBACK'); bad("Cuota $idCxpDet no encontrada.", 404); }
            $cRow = pg_fetch_assoc($st);
            if (!in_array($cRow['estado'], ['Pendiente','Parcial'], true) || (float)$cRow['saldo'] <= 0) {
                pg_query($conn, 'ROLLBACK'); bad("La cuota $idCxpDet no está abierta o no tiene saldo.", 409);
            }
            if ($monto > (float)$cRow['saldo'] + 0.000001) {
                pg_query($conn, 'ROLLBACK'); bad("El monto excede el saldo de la cuota $idCxpDet.", 422);
            }

            $tag = sprintf('[ENTREGADA OP #%d %s FE:%s M:%s]',
                $id,
                $op['cheque']['numero_cheque'] ? ('CH:' . $op['cheque']['numero_cheque']) : 'CH:-',
                $fecha,
                number_format($monto, 2, '.', '')
            );

            $resCuota = apply_cuota_saldo($conn, $idCxpDet, -$monto);
            $newObs = trim(($resCuota['obs'] ?? '').' '.$tag);
            if (!update_cuota_obs_estado($conn, $idCxpDet, $newObs, $resCuota['estado'])) {
                pg_query($conn, 'ROLLBACK'); bad("No se pudo actualizar la cuota $idCxpDet.", 500);
            }

            $uCxp = pg_query_params($conn,
                "UPDATE public.cuenta_pagar
                 SET saldo_actual = GREATEST(saldo_actual - $1, 0)
                 WHERE id_cxp = $2",
                [$monto, $idCxp]
            );
            if (!$uCxp) { pg_query($conn, 'ROLLBACK'); bad('No se pudo actualizar el saldo de la CxP', 500); }

            $insMov = pg_query_params(
                $conn,
                "INSERT INTO public.cuenta_pagar_mov
                   (id_proveedor, fecha, ref_tipo, ref_id, id_cxp,
                    concepto, signo, monto, moneda, created_at)
                 SELECT cxp.id_proveedor,
                        $1::date,
                        'PAGO',
                        $2::int,
                        cxp.id_cxp,
                        CONCAT('Cheque entregado OP #', $2::text, ' cuota ', $3::int),
                        -1,
                        $4::numeric,
                        cxp.moneda,
                        now()
                 FROM public.cuenta_pagar cxp
                 WHERE cxp.id_cxp = $5",
                [$fecha, $id, $idCxpDet, $monto, $idCxp]
            );
            if (!$insMov) { pg_query($conn, 'ROLLBACK'); bad('No se pudo registrar el movimiento en la cuenta por pagar', 500); }

            recalc_cxp_estado($conn, $idCxp);

            $marcadas[] = ['id_cxp'=>$idCxp, 'id_cxp_det'=>$idCxpDet, 'monto'=>$monto, 'estado'=>$resCuota['estado']];
        }

        /* =======================
           5) MOVIMIENTOS BANCARIOS
           ======================= */
        $totalOperacion = (float)$op['total'];
        if ($totalOperacion <= 0) {
            pg_query($conn, 'ROLLBACK');
            bad('El total de la orden es inválido.');
        }
        $numeroCheque = $op['cheque']['numero_cheque'] ?? '-';

        $descLiberacion = sprintf('Liberación por entrega OP #%d Cheque #%s', $id, $numeroCheque);
        $insLib = pg_query_params(
            $conn,
            "INSERT INTO public.cuenta_bancaria_mov
               (id_cuenta_bancaria, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at)
             VALUES ($1, $2::date, 'LIBERACION', 1, $3, $4, 'orden_pago', $5, now())",
            [(int)$op['id_cuenta_bancaria'], $fecha, $totalOperacion, $descLiberacion, $id]
        );
        if (!$insLib) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo registrar la liberación bancaria', 500);
        }

        $descPago = sprintf('Cheque emitido OP #%d Cheque #%s', $id, $numeroCheque);
        $insPago = pg_query_params(
            $conn,
            "INSERT INTO public.cuenta_bancaria_mov
               (id_cuenta_bancaria, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at)
             VALUES ($1, $2::date, 'PAGO_OP', -1, $3, $4, 'orden_pago', $5, now())",
            [(int)$op['id_cuenta_bancaria'], $fecha, $totalOperacion, $descPago, $id]
        );
        if (!$insPago) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo registrar el movimiento de pago en banco', 500);
        }

        /* =======================
           6) ACREDITAR FONDO FIJO
           ======================= */
        // total aplicado por CxP
        $sumPorCxp = [];
        foreach ($marcadas as $m) {
            $sumPorCxp[$m['id_cxp']] = ($sumPorCxp[$m['id_cxp']] ?? 0) + (float)$m['monto'];
        }

        if ($sumPorCxp) {
            // Traer id_ff solamente de CxP marcadas como FF (es_ff=true)
            $placeholders = [];
            $params = [];
            $i = 1;
            foreach (array_keys($sumPorCxp) as $idc) { $placeholders[] = '$'.$i; $params[] = (int)$idc; $i++; }

            $sqlCxps = "
              SELECT cxp.id_cxp, cxp.id_ff
                FROM public.cuenta_pagar cxp
               WHERE cxp.id_cxp IN (".implode(',', $placeholders).")
                 AND cxp.es_ff = true
                 AND cxp.id_ff IS NOT NULL
            ";
            $stCxps = pg_query_params($conn, $sqlCxps, $params);
            if (!$stCxps) { pg_query($conn,'ROLLBACK'); bad('No se pudieron leer CxP FF para aplicar al Fondo Fijo',500); }

            $porFF = []; // id_ff => monto
            while ($cx = pg_fetch_assoc($stCxps)) {
                $idc     = (int)$cx['id_cxp'];
                $idFf    = (int)$cx['id_ff'];
                $montoAp = (float)($sumPorCxp[$idc] ?? 0);
                if ($idFf > 0 && $montoAp > 0) {
                    $porFF[$idFf] = ($porFF[$idFf] ?? 0) + $montoAp;
                }
            }

            // acreditar FF + movimiento
            if ($porFF) {
                $numCheque = ($op['cheque']['numero_cheque'] ?? '');
                foreach ($porFF as $idFf => $montoRepo) {
                    // actualizar saldo_actual
                    $okFf = pg_query_params(
                        $conn,
                        "UPDATE public.fondo_fijo
                           SET saldo_actual = COALESCE(saldo_actual,0) + $1,
                               updated_at = now()
                         WHERE id_ff = $2",
                        [$montoRepo, $idFf]
                    );
                    if (!$okFf) { pg_query($conn,'ROLLBACK'); bad('No se pudo actualizar saldo del Fondo Fijo',500); }

                    // movimiento de reposición
                    $desc = 'Reposición FF via OP #'.$id.( $numCheque ? ' · Cheque #'.$numCheque : '' );
                    $insFFMov = pg_query_params(
                        $conn,
                        "INSERT INTO public.fondo_fijo_mov
                           (id_ff, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at, created_by)
                         VALUES
                           ($1, $2::date, 'REPOSICION', 1, $3, $4, 'orden_pago', $5, now(), $6)",
                        [$idFf, $fecha, $montoRepo, $desc, $id, $_SESSION['nombre_usuario']]
                    );
                    if (!$insFFMov) { pg_query($conn,'ROLLBACK'); bad('No se pudo registrar el movimiento en Fondo Fijo',500); }
                }
            }
        }

        pg_query($conn, 'COMMIT');
        ok([
            'id_orden_pago'  => $id,
            'estado'         => 'Entregada',
            'cuotas_marcadas'=> $marcadas
        ]);
    }

    /** ---------- ANULAR (reversa banco, cuotas y también Fondo Fijo si se había acreditado) ---------- */
    if ($accion === 'anular') {
        $motivo = trim($input['motivo'] ?? '');

        pg_query($conn, 'BEGIN');
        $op = fetch_op($conn, $id, true);
        if (!$op) { pg_query($conn, 'ROLLBACK'); bad('Orden no encontrada', 404); }
        if (!$op['cheque']) { pg_query($conn, 'ROLLBACK'); bad('La orden no tiene cheque asociado', 409); }

        $cheqEstado = $op['cheque']['estado'];
        $opEstado   = $op['estado'];
        if (!in_array($cheqEstado, ['Reservado','Entregado'], true) || !in_array($opEstado, ['Reservada','Entregada'], true)) {
            pg_query($conn, 'ROLLBACK');
            bad('Sólo se puede anular una orden con cheque no compensado (Reservado/Entregado).', 409);
        }

        // 0) Revertir Fondo Fijo si hubo reposición por esta OP (busco por ref_tabla/ref_id)
        $stFFMov = pg_query_params(
            $conn,
            "SELECT id_ff, SUM(monto) AS total
               FROM public.fondo_fijo_mov
              WHERE ref_tabla='orden_pago' AND ref_id=$1 AND tipo='REPOSICION'
              GROUP BY id_ff",
            [$id]
        );
        if ($stFFMov && pg_num_rows($stFFMov) > 0) {
            while ($r = pg_fetch_assoc($stFFMov)) {
                $idFf = (int)$r['id_ff'];
                $tot  = (float)$r['total'];
                if ($idFf > 0 && $tot > 0) {
                    // bajar saldo
                    $okFf = pg_query_params(
                        $conn,
                        "UPDATE public.fondo_fijo
                           SET saldo_actual = GREATEST(COALESCE(saldo_actual,0) - $1, 0),
                               updated_at = now()
                         WHERE id_ff = $2",
                        [$tot, $idFf]
                    );
                    if (!$okFf) { pg_query($conn,'ROLLBACK'); bad('No se pudo revertir saldo del Fondo Fijo',500); }
                    // movimiento de anulación
                    $desc = 'Anulación reposición FF de OP #'.$id;
                    $insFFMov = pg_query_params(
                        $conn,
                        "INSERT INTO public.fondo_fijo_mov
                           (id_ff, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at, created_by)
                         VALUES
                           ($1, current_date, 'ANULACION', -1, $2, $3, 'orden_pago', $4, now(), $5)",
                        [$idFf, $tot, $desc, $id, $_SESSION['nombre_usuario']]
                    );
                    if (!$insFFMov) { pg_query($conn,'ROLLBACK'); bad('No se pudo registrar movimiento de anulación FF',500); }
                }
            }
        }

        $totalOp = (float)$op['total'];
        $numeroCheque = $op['cheque']['numero_cheque'] ?? '-';

        // 1) Movimientos bancarios según estado del cheque
        if ($cheqEstado === 'Reservado') {
            $desc = sprintf('Liberación reserva OP #%d Cheque #%s', $id, $numeroCheque);
            $insMov = pg_query_params(
                $conn,
                "INSERT INTO public.cuenta_bancaria_mov
                    (id_cuenta_bancaria, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at)
                 VALUES ($1, current_date, 'LIBERACION', 1, $2, $3, 'orden_pago', $4, now())",
                [(int)$op['id_cuenta_bancaria'], $totalOp, $desc, $id]
            );
            if (!$insMov) { pg_query($conn, 'ROLLBACK'); bad('No se pudo liberar la reserva bancaria', 500); }
        } else { // Entregado
            $desc = sprintf('Reverso pago OP #%d Cheque #%s', $id, $numeroCheque);
            $insMov = pg_query_params(
                $conn,
                "INSERT INTO public.cuenta_bancaria_mov
                    (id_cuenta_bancaria, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at)
                 VALUES ($1, current_date, 'REVERSO_PAGO_OP', 1, $2, $3, 'orden_pago', $4, now())",
                [(int)$op['id_cuenta_bancaria'], $totalOp, $desc, $id]
            );
            if (!$insMov) { pg_query($conn, 'ROLLBACK'); bad('No se pudo revertir el movimiento de pago', 500); }
        }

        // 2) Cheque → Anulado
        $updCheque = pg_query_params(
            $conn,
            "UPDATE public.cheques
             SET estado='Anulado',
                 observaciones = CASE WHEN $1 <> '' THEN CONCAT(COALESCE(observaciones,''),' | ', $1) ELSE observaciones END,
                 updated_at = now()
             WHERE id=$2",
            [$motivo, (int)$op['cheque']['id']]
        );
        if (!$updCheque) { pg_query($conn, 'ROLLBACK'); bad('No se pudo actualizar el cheque', 500); }

        // 3) OP → Anulada
        $updOP = pg_query_params(
            $conn,
            "UPDATE public.orden_pago_cab
             SET estado='Anulada',
                 observacion = CASE WHEN $1 <> '' THEN CONCAT(COALESCE(observacion,''),' | Anulación: ',$1) ELSE observacion END
             WHERE id_orden_pago=$2",
            [$motivo, $id]
        );
        if (!$updOP) { pg_query($conn, 'ROLLBACK'); bad('No se pudo actualizar la orden', 500); }

        // 4) Revertir cuotas marcadas por esta OP (se busca por TAG en observación de cuota)
        $pattern = '\\[ENTREGADA OP #' . (int)$id . '[^\\]]*\\]';
        $stC = pg_query_params($conn, "
            SELECT id_cxp_det, id_cxp, COALESCE(saldo_cuota,0) AS saldo_cuota, COALESCE(observacion,'') AS obs
            FROM public.cuenta_det_x_pagar
            WHERE observacion ~ $1
            FOR UPDATE
        ", [$pattern]);
        if ($stC === false) { pg_query($conn, 'ROLLBACK'); bad('No se pudo localizar cuotas comprometidas', 500); }

        while ($c = pg_fetch_assoc($stC)) {
            $idCxpDet = (int)$c['id_cxp_det'];
            $idCxp    = (int)$c['id_cxp'];
            $obsOld   = $c['obs'];

            $monto = 0.0;
            if (preg_match('/M:([0-9]+(?:\.[0-9]{1,2})?)/', $obsOld, $mm)) $monto = (float)$mm[1];

            $cleanObs = trim(preg_replace("/$pattern/", '', $obsOld));
            $obsToSave = ($cleanObs === '') ? null : $cleanObs;

            if ($monto > 0) {
                $uCuota = pg_query_params($conn, "UPDATE public.cuenta_det_x_pagar SET saldo_cuota = saldo_cuota + $1 WHERE id_cxp_det = $2", [$monto, $idCxpDet]);
                if (!$uCuota) { pg_query($conn, 'ROLLBACK'); bad('No se pudo restaurar saldo de la cuota '.$idCxpDet, 500); }

                $uCxp = pg_query_params($conn, "UPDATE public.cuenta_pagar SET saldo_actual = saldo_actual + $1 WHERE id_cxp = $2", [$monto, $idCxp]);
                if (!$uCxp) { pg_query($conn, 'ROLLBACK'); bad('No se pudo restaurar saldo de la CxP', 500); }

                $insMovRv = pg_query_params(
                  $conn,
                  "INSERT INTO public.cuenta_pagar_mov
                     (id_proveedor, fecha, ref_tipo, ref_id, id_cxp,
                      concepto, signo, monto, moneda, created_at)
                   SELECT cxp.id_proveedor,
                          current_date,
                          'PAGO',
                          $1::int,
                          cxp.id_cxp,
                          CONCAT('Reverso OP #', $2::text, ' cuota ', $3::int),
                          1,
                          $4::numeric,
                          cxp.moneda,
                          now()
                   FROM public.cuenta_pagar cxp
                   WHERE cxp.id_cxp = $5",
                  [$id, $id, $idCxpDet, $monto, $idCxp]
                );
                if (!$insMovRv) { pg_query($conn, 'ROLLBACK'); bad('No se pudo registrar reverso en CxP', 500); }
            }

            $updCuotaObs = pg_query_params(
                $conn,
                "UPDATE public.cuenta_det_x_pagar
                   SET observacion = $1,
                       estado = CASE WHEN saldo_cuota > 0 THEN 'Pendiente' ELSE 'Cancelada' END
                 WHERE id_cxp_det = $2",
                [$obsToSave, $idCxpDet]
            );
            if (!$updCuotaObs) { pg_query($conn, 'ROLLBACK'); bad('No se pudo limpiar la observación de la cuota '.$idCxpDet, 500); }

            $updEstadoCxp = pg_query_params(
                $conn,
                "UPDATE public.cuenta_pagar
                   SET estado = CASE WHEN saldo_actual > 0 THEN 'Parcial' ELSE 'Cancelada' END
                 WHERE id_cxp = $1",
                [$idCxp]
            );
            if (!$updEstadoCxp) { pg_query($conn, 'ROLLBACK'); bad('No se pudo recalcular estado de la CxP', 500); }
        }

        pg_query($conn, 'COMMIT');
        ok(['id_orden_pago' => $id, 'estado' => 'Anulada', 'cuotas_revertidas' => true]);
    }

    /** ---------- IMPRIMIR CHEQUE ---------- */
    if ($accion === 'imprimir') {
        pg_query($conn, 'BEGIN');
        $op = fetch_op($conn, $id, true);
        if (!$op) { pg_query($conn, 'ROLLBACK'); bad('Orden no encontrada', 404); }
        if (!$op['cheque']) { pg_query($conn, 'ROLLBACK'); bad('La orden no tiene cheque asociado', 409); }

        $upd = pg_query_params(
            $conn,
            "UPDATE public.cheques
             SET impreso_at = now(),
                 impreso_por = $1
             WHERE id = $2",
            [$_SESSION['nombre_usuario'], (int)$op['cheque']['id']]
        );
        if (!$upd) { pg_query($conn, 'ROLLBACK'); bad('No se pudo registrar la impresión del cheque', 500); }

        pg_query($conn, 'COMMIT');
        ok(['id_orden_pago' => $id, 'impreso' => true]);
    }

    bad('Acción no soportada', 405);
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
