<?php
/**
 * Gestión de Órdenes de Pago (lectura, entrega, impresión, anulación)
 * GET   /ordenes_pago_gestion_api.php           -> listar órdenes (filtros)
 * GET   /ordenes_pago_gestion_api.php?id=123    -> detalle
 * PATCH /ordenes_pago_gestion_api.php?id=123    -> acciones: entrega, anular, imprimir
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
    // Ensure a return value for all paths
    bad('No se encontró la tabla de cuotas (cuenta_det_x_pagar / cuenta_pagar_det).', 500);
    return []; // This line ensures all paths return a value (unreachable, but required for strict checks)
}

function fetch_cuota($conn, int $id_cxp_det, bool $forUpdate = false): array {
    $m = find_cuotas_table($conn);
    $lock = $forUpdate ? 'FOR UPDATE' : '';
    $sql = "
        SELECT id_cxp_det, id_cxp,
               COALESCE({$m['saldo_col']},0) AS saldo,
               {$m['estado_col']} AS estado,
               COALESCE({$m['obs_col']},'') AS obs
        FROM {$m['table']}
        WHERE id_cxp_det = $1
        $lock
    ";
    $st = pg_query_params($conn, $sql, [$id_cxp_det]);
    if (!$st || !pg_num_rows($st)) bad("Cuota $id_cxp_det no encontrada", 404);
    $row = pg_fetch_assoc($st);
    $row['_meta'] = $m;
    return $row;
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
    $c = fetch_cuota($conn, $id_cxp_det, true);
    $m = $c['_meta'];

    $newSaldo = max(((float)$c['saldo']) + $deltaSigno, 0); // deltaSigno negativo descuenta; positivo repone
    // Si descontamos (delta < 0): estado Entregada/Cancelada. Si reponemos (delta > 0): Pendiente/Parcial.
    if ($deltaSigno < 0) {
        $newEstado = $newSaldo <= 0 ? 'Cancelada' : 'Entregada';
    } else {
        $newEstado = $newSaldo > 0 ? 'Pendiente' : 'Cancelada';
    }

    $sql = "UPDATE {$m['table']} SET {$m['saldo_col']}=$1, {$m['estado_col']}=$2 WHERE id_cxp_det=$3";
    $ok  = pg_query_params($conn, $sql, [$newSaldo, $newEstado, $id_cxp_det]);
    if (!$ok) bad("No se pudo actualizar saldo de la cuota $id_cxp_det", 500);

    return ['id_cxp' => (int)$c['id_cxp'], 'old_saldo' => (float)$c['saldo'], 'new_saldo' => $newSaldo, 'estado' => $newEstado];
}

/** Recalcula estado de la cabecera CxP; si saldo_actual = 0, fuerza Cancelada. */
function recalc_cxp_estado($conn, int $id_cxp): void {
    $m = find_cuotas_table($conn);

    // saldo_actual de la cabecera
    $stCab = pg_query_params($conn, "SELECT saldo_actual FROM public.cuenta_pagar WHERE id_cxp=$1 FOR UPDATE", [$id_cxp]);
    if (!$stCab || !pg_num_rows($stCab)) bad('CxP no encontrada', 500);
    $saldoActual = (float)pg_fetch_result($stCab, 0, 0);

    if ($saldoActual <= 0.0000001) {
        pg_query_params($conn, "UPDATE public.cuenta_pagar SET saldo_actual=0, estado='Cancelada' WHERE id_cxp=$1", [$id_cxp]);
        return;
    }

    // Cuotas abiertas
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

        $sqlDet = "
            SELECT opd.id_cxp,
                   f.numero_documento,
                   cxp.fecha_venc,
                   cxp.saldo_actual,
                   opd.monto_aplicado,
                   opd.moneda
            FROM public.orden_pago_det opd
            JOIN public.cuenta_pagar cxp ON cxp.id_cxp = opd.id_cxp
            JOIN public.factura_compra_cab f ON f.id_factura = cxp.id_factura
            WHERE opd.id_orden_pago = $1
            ORDER BY opd.id_det
        ";
        $stmtDet = pg_query_params($conn, $sqlDet, [$id]);
        if (!$stmtDet) bad('Error al obtener detalle', 500);
        $detalles = [];
        while ($row = pg_fetch_assoc($stmtDet)) {
            $detalles[] = [
                'id_cxp'         => (int)$row['id_cxp'],
                'numero'         => $row['numero_documento'],
                'fecha_venc'     => $row['fecha_venc'],
                'saldo_actual'   => (float)$row['saldo_actual'],
                'monto_aplicado' => (float)$row['monto_aplicado'],
                'moneda'         => $row['moneda'],
            ];
        }

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
                'ci'             => $cheque['ci'],
                'observaciones'  => $cheque['observaciones'],
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

    /** ---------- ENTREGA (descuenta saldos + marca cuotas Entregada) ---------- */
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

        // 4) Para cada cuota: tag, estado=Entregada y DESCONTAR saldos cuota + cabecera CxP. Inserta mov PAGO (-1)
        $marcadas = [];
        foreach ($cuotasEnt as $q) {
            $idCxp    = (int)($q['id_cxp'] ?? 0);
            $idCxpDet = (int)($q['id_cxp_det'] ?? 0);
            $monto    = (float)($q['monto'] ?? 0);
            if ($idCxp <= 0 || $idCxpDet <= 0 || $monto <= 0) { pg_query($conn, 'ROLLBACK'); bad('Cuota inválida en la entrega.', 422); }
            if (empty($cxpIds[$idCxp])) { pg_query($conn, 'ROLLBACK'); bad("La cuota $idCxpDet no pertenece a una CxP de esta OP.", 422); }

            $c = fetch_cuota($conn, $idCxpDet, true);
            if (!in_array($c['estado'], ['Pendiente','Parcial'], true) || (float)$c['saldo'] <= 0) {
                pg_query($conn, 'ROLLBACK'); bad("La cuota $idCxpDet no está abierta o no tiene saldo.", 409);
            }
            if ($monto > (float)$c['saldo'] + 0.000001) {
                pg_query($conn, 'ROLLBACK'); bad("El monto excede el saldo de la cuota $idCxpDet.", 422);
            }

            // Tag
            $tag = sprintf('[ENTREGADA OP #%d %s FE:%s M:%s]',
                $id,
                $op['cheque']['numero_cheque'] ? ('CH:' . $op['cheque']['numero_cheque']) : 'CH:-',
                $fecha,
                number_format($monto, 2, '.', '')
            );
            $newObs = trim(($c['obs'] ?? '').' '.$tag);

            // Descontar saldo de la cuota (delta negativo) y fijar estado (Entregada/Cancelada)
            $resCuota = apply_cuota_saldo($conn, $idCxpDet, -$monto);
            if (!update_cuota_obs_estado($conn, $idCxpDet, $newObs, $resCuota['estado'])) {
                pg_query($conn, 'ROLLBACK'); bad("No se pudo actualizar la cuota $idCxpDet.", 500);
            }

            // Descontar saldo_actual de la CxP
            $uCxp = pg_query_params($conn,
                "UPDATE public.cuenta_pagar
                 SET saldo_actual = GREATEST(saldo_actual - $1, 0)
                 WHERE id_cxp = $2",
                [$monto, $idCxp]
            );
            if (!$uCxp) { pg_query($conn, 'ROLLBACK'); bad('No se pudo actualizar el saldo de la CxP', 500); }

            // Movimiento en CxP (PAGO, signo -1)
            $insMov = pg_query_params(
    $conn,
    "INSERT INTO public.cuenta_pagar_mov
       (id_proveedor, fecha, ref_tipo, ref_id, id_cxp,
        concepto, signo, monto, moneda, created_at)
     SELECT cxp.id_proveedor,
            $1::date,              -- fecha
            'PAGO',
            $2::int,               -- ref_id = id OP
            cxp.id_cxp,
            CONCAT('Cheque entregado OP #', $3::text, ' cuota ', $4::int),
            -1,
            $5::numeric,           -- monto
            cxp.moneda,
            now()
     FROM public.cuenta_pagar cxp
     WHERE cxp.id_cxp = $6",
    [
      $fecha,         // $1
      $id,            // $2  (ref_id INT)
      $id,            // $3  (para texto en CONCAT)
      $idCxpDet,      // $4
      $monto,         // $5
      $idCxp          // $6
    ]
);

            if (!$insMov) { pg_query($conn, 'ROLLBACK'); bad('No se pudo registrar el movimiento en la cuenta por pagar', 500); }

            // Recalcular estado de la CxP
            recalc_cxp_estado($conn, $idCxp);

            $marcadas[] = ['id_cxp'=>$idCxp, 'id_cxp_det'=>$idCxpDet, 'monto'=>$monto, 'estado'=>$resCuota['estado']];
        }

        pg_query($conn, 'COMMIT');
        ok([
            'id_orden_pago'  => $id,
            'estado'         => 'Entregada',
            'cuotas_marcadas'=> $marcadas
        ]);
    }

    /** ---------- ANULAR (libera reserva + revierte saldos/estados/etiquetas) ---------- */
    if ($accion === 'anular') {
    $motivo = trim($input['motivo'] ?? '');

    pg_query($conn, 'BEGIN');
    $op = fetch_op($conn, $id, true);
    if (!$op) { pg_query($conn, 'ROLLBACK'); bad('Orden no encontrada', 404); }
    if (!$op['cheque']) { pg_query($conn, 'ROLLBACK'); bad('La orden no tiene cheque asociado', 409); }

    // Permitimos anular si NO está compensado (estados esperados)
    $cheqEstado = $op['cheque']['estado'];
    $opEstado   = $op['estado'];
    if (!in_array($cheqEstado, ['Reservado','Entregado'], true) || !in_array($opEstado, ['Reservada','Entregada'], true)) {
        pg_query($conn, 'ROLLBACK');
        bad('Sólo se puede anular una orden con cheque no compensado (Reservado/Entregado).', 409);
    }

    // 1) Movimiento de LIBERACIÓN en banco
    $desc = sprintf('Liberación reserva OP #%d Cheque #%s', $op['id_orden_pago'], $op['cheque']['numero_cheque']);
    $insMov = pg_query_params(
        $conn,
        "INSERT INTO public.cuenta_bancaria_mov
            (id_cuenta_bancaria, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at)
         VALUES ($1, current_date, 'LIBERACION', 1, $2, $3, 'orden_pago', $4, now())",
        [(int)$op['id_cuenta_bancaria'], (float)$op['total'], $desc, $id]
    );
    if (!$insMov) { pg_query($conn, 'ROLLBACK'); bad('No se pudo liberar la reserva bancaria', 500); }

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

    // 4) Revertir cuotas marcadas con el tag [ENTREGADA OP #id ... M:xxxxx]
    //    Localizamos por patrón en la observación y revertimos saldos.
    $pattern = '\\[ENTREGADA OP #' . (int)$id . '[^\\]]*\\]';
    $sqlSel = "
        SELECT id_cxp_det, id_cxp,
               COALESCE(saldo_cuota,0) AS saldo_cuota,
                COALESCE(observacion,'') AS obs
        FROM public.cuenta_det_x_pagar
        WHERE observacion ~ $1
        FOR UPDATE
    ";
    $stC = pg_query_params($conn, $sqlSel, [$pattern]);
    if ($stC === false) { pg_query($conn, 'ROLLBACK'); bad('No se pudo localizar cuotas comprometidas', 500); }

    while ($c = pg_fetch_assoc($stC)) {
        $idCxpDet = (int)$c['id_cxp_det'];
        $idCxp    = (int)$c['id_cxp'];
        $obsOld   = $c['obs'];

        // Extraer monto del tag (M:###.##)
        $monto = 0.0;
        if (preg_match('/M:([0-9]+(?:\.[0-9]{1,2})?)/', $obsOld, $mm)) {
            $monto = (float)$mm[1];
        }

        // Limpiar tag en obs
        $cleanObs = trim(preg_replace("/$pattern/", '', $obsOld));
        $obsToSave = ($cleanObs === '') ? null : $cleanObs;

        if ($monto > 0) {
            // 4.1) Reponer saldo de la cuota
            $uCuota = pg_query_params(
                $conn,
                "UPDATE public.cuenta_det_x_pagar
                 SET saldo_cuota = saldo_cuota + $1
                 WHERE id_cxp_det = $2",
                [$monto, $idCxpDet]
            );
            if (!$uCuota) { pg_query($conn, 'ROLLBACK'); bad('No se pudo restaurar saldo de la cuota '.$idCxpDet, 500); }

            // 4.2) Devolver saldo_actual en CxP
            $uCxp = pg_query_params(
                $conn,
                "UPDATE public.cuenta_pagar
                 SET saldo_actual = saldo_actual + $1
                 WHERE id_cxp = $2",
                [$monto, $idCxp]
            );
            if (!$uCxp) { pg_query($conn, 'ROLLBACK'); bad('No se pudo restaurar saldo de la CxP', 500); }

            // 4.3) Movimiento reverso en CxP (ref_tipo='PAGO', signo=+1) para cumplir el CHECK
            $insMovRv = pg_query_params(
              $conn,
              "INSERT INTO public.cuenta_pagar_mov
                 (id_proveedor, fecha, ref_tipo, ref_id, id_cxp,
                  concepto, signo, monto, moneda, created_at)
               SELECT cxp.id_proveedor,
                      current_date,
                      'PAGO',               -- reverso usando tipo PAGO
                      $1::int,              -- ref_id = id OP
                      cxp.id_cxp,
                      CONCAT('Reverso OP #', $2::text, ' cuota ', $3::int),
                      1,                    -- +1 devuelve saldo
                      $4::numeric,
                      cxp.moneda,
                      now()
               FROM public.cuenta_pagar cxp
               WHERE cxp.id_cxp = $5",
              [$id, $id, $idCxpDet, $monto, $idCxp]
            );
            if (!$insMovRv) { pg_query($conn, 'ROLLBACK'); bad('No se pudo registrar reverso en CxP', 500); }
        }

        // 4.4) Guardar observación limpia y asegurar estado NO nulo
        $updCuotaObs = pg_query_params(
            $conn,
            "UPDATE public.cuenta_det_x_pagar
               SET observacion = $1,
                   estado = CASE
                              WHEN saldo_cuota > 0 THEN 'Pendiente'
                              ELSE 'Cancelada'
                            END
             WHERE id_cxp_det = $2",
            [$obsToSave, $idCxpDet]
        );
        if (!$updCuotaObs) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo limpiar la observación de la cuota '.$idCxpDet, 500);
        }

        // 4.5) Recalcular estado de la CxP (simple: si queda saldo > 0 => Parcial, si 0 => Cancelada)
        $updEstadoCxp = pg_query_params(
            $conn,
            "UPDATE public.cuenta_pagar
               SET estado = CASE WHEN saldo_actual > 0 THEN 'Parcial' ELSE 'Cancelada' END
             WHERE id_cxp = $1",
            [$idCxp]
        );
        if (!$updEstadoCxp) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo recalcular estado de la CxP', 500);
        }
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
