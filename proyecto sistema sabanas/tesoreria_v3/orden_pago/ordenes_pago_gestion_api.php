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

function bad(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
function ok(array $payload = []): void
{
    echo json_encode(['ok' => true] + $payload);
    exit;
}
function read_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function parse_bool($val, bool $default): bool
{
    if ($val === null) return $default;
    if (is_bool($val)) return $val;
    $filtered = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $filtered === null ? $default : $filtered;
}

/**
 * Obtiene la cabecera de la OP y su cheque (cuando existe).
 * Para actualizaciones se ejecuta con FOR UPDATE sobre la cabecera y el cheque.
 */
function fetch_op($conn, int $id, bool $forUpdate = false)
{
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
    if (!$stmtCab || !pg_num_rows($stmtCab)) {
        return null;
    }
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
                'id_cxp'        => (int)$row['id_cxp'],
                'numero'        => $row['numero_documento'],
                'fecha_venc'    => $row['fecha_venc'],
                'saldo_actual'  => (float)$row['saldo_actual'],
                'monto_aplicado' => (float)$row['monto_aplicado'],
                'moneda'        => $row['moneda'],
            ];
        }

        $sqlMov = "
            SELECT id_mov,
                   fecha,
                   tipo,
                   monto,
                   signo,
                   descripcion,
                   created_at
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
                'descripcion' => $m['descripcion'],
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

    $params = [];
    $filters = [];
    $idx = 1;

    if (!empty($_GET['id_proveedor'])) {
        $filters[] = 'opc.id_proveedor = $' . $idx;
        $params[] = (int)$_GET['id_proveedor'];
        $idx++;
    }
    if (!empty($_GET['id_cuenta_bancaria'])) {
        $filters[] = 'opc.id_cuenta_bancaria = $' . $idx;
        $params[] = (int)$_GET['id_cuenta_bancaria'];
        $idx++;
    }
    if (!empty($_GET['estado'])) {
        $filters[] = 'opc.estado = $' . $idx;
        $params[] = $_GET['estado'];
        $idx++;
    }
    if (!empty($_GET['fecha_desde'])) {
        $filters[] = 'opc.fecha >= $' . $idx;
        $params[] = $_GET['fecha_desde'];
        $idx++;
    }
    if (!empty($_GET['fecha_hasta'])) {
        $filters[] = 'opc.fecha <= $' . $idx;
        $params[] = $_GET['fecha_hasta'];
        $idx++;
    }
    if (!empty($_GET['q'])) {
        $filters[] = "(p.nombre ILIKE $" . $idx . " OR cb.numero_cuenta ILIKE $" . $idx . " OR ch.numero_cheque ILIKE $" . $idx . ")";
        $params[] = '%' . trim($_GET['q']) . '%';
        $idx++;
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

if ($method === 'PATCH') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) bad('Orden inválida');
    $input = read_json();
    $accion = $input['accion'] ?? '';

    if ($accion === 'entrega') {
        $fecha      = $input['fecha_entrega'] ?? date('Y-m-d');
        $recibido   = trim($input['recibido_por'] ?? '');
        $ciRecibido = trim($input['ci_recibido'] ?? '');
        $obs        = trim($input['observaciones'] ?? '');

        if ($recibido === '') bad('Debe indicar quién recibe el cheque');

        pg_query($conn, 'BEGIN');
        $op = fetch_op($conn, $id, true);
        if (!$op) {
            pg_query($conn, 'ROLLBACK');
            bad('Orden no encontrada', 404);
        }
        if (!$op['cheque'] || $op['cheque']['estado'] !== 'Reservado') {
            pg_query($conn, 'ROLLBACK');
            bad('Sólo se puede registrar la entrega si el cheque está reservado.', 409);
        }

        $updCheque = pg_query_params(
            $conn,
            "UPDATE public.cheques
             SET estado='Entregado',
                 fecha_entrega=$1,
                 recibido_por=$2,
                 ci = NULLIF($3,''),
                 observaciones = $4,
                 updated_at = now()
             WHERE id=$5",
            [$fecha, $recibido, $ciRecibido, $obs !== '' ? $obs : null, (int)$op['cheque']['id']]
        );
        if (!$updCheque) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo actualizar el cheque', 500);
        }

        $updOP = pg_query_params(
            $conn,
            "UPDATE public.orden_pago_cab SET estado='Entregada' WHERE id_orden_pago=$1",
            [$id]
        );
        if (!$updOP) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo actualizar la orden', 500);
        }

        $sqlPagos = "
            SELECT opd.id_cxp,
                   opd.monto_aplicado,
                   cxp.moneda,
                   cxp.id_proveedor
            FROM public.orden_pago_det opd
            JOIN public.cuenta_pagar cxp ON cxp.id_cxp = opd.id_cxp
            WHERE opd.id_orden_pago = $1
        ";
        $stmtPagos = pg_query_params($conn, $sqlPagos, [$id]);
        if (!$stmtPagos) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo preparar la actualización de cuentas por pagar', 500);
        }
        while ($det = pg_fetch_assoc($stmtPagos)) {
            $idCxp   = (int)$det['id_cxp'];
            $monto   = (float)$det['monto_aplicado'];
            $moneda  = $det['moneda'];
            $idProv  = (int)$det['id_proveedor'];

            $insMov = pg_query_params(
                $conn,
                "INSERT INTO public.cuenta_pagar_mov
       (id_proveedor, fecha, ref_tipo, ref_id, id_cxp,
        concepto, signo, monto, moneda, created_at)
     VALUES (
       $1,
       $2::date,
       'PAGO',
       $3,
       $4,
       CONCAT('Cheque entregado OP #', CAST($7 AS text)),
       -1,
       $5,
       $6,
       now()
     )",
                [$idProv, $fecha, $id, $idCxp, $monto, $moneda, $id]
            );

            // Removed extraneous parenthesis and array

            if (!$insMov) {
                pg_query($conn, 'ROLLBACK');
                bad('No se pudo registrar el movimiento en la cuenta por pagar', 500);
            }

            $updCxp = pg_query_params(
                $conn,
                "UPDATE public.cuenta_pagar
                 SET saldo_actual = GREATEST(saldo_actual - $1, 0),
                     estado = CASE WHEN (saldo_actual - $1) <= 0 THEN 'Cancelada' ELSE 'Parcial' END
                 WHERE id_cxp = $2",
                [$monto, $idCxp]
            );
            if (!$updCxp) {
                pg_query($conn, 'ROLLBACK');
                bad('No se pudo actualizar la cuenta por pagar', 500);
            }
        }

        pg_query($conn, 'COMMIT');
        ok(['id_orden_pago' => $id, 'estado' => 'Entregada']);
    }

    if ($accion === 'anular') {
        $motivo = trim($input['motivo'] ?? '');

        pg_query($conn, 'BEGIN');
        $op = fetch_op($conn, $id, true);
        if (!$op) {
            pg_query($conn, 'ROLLBACK');
            bad('Orden no encontrada', 404);
        }
        if ($op['estado'] !== 'Reservada' || !$op['cheque'] || $op['cheque']['estado'] !== 'Reservado') {
            pg_query($conn, 'ROLLBACK');
            bad('Sólo se puede anular una orden con cheque reservado y no entregado.', 409);
        }

        $desc = sprintf('Anulación OP #%d Cheque #%s', $op['id_orden_pago'], $op['cheque']['numero_cheque']);

        $insMov = pg_query_params(
            $conn,
            "INSERT INTO public.cuenta_bancaria_mov
                (id_cuenta_bancaria, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at)
             VALUES ($1, current_date, 'LIBERACION', 1, $2, $3, 'orden_pago', $4, now())",
            [(int)$op['id_cuenta_bancaria'], (float)$op['total'], $desc, $id]
        );
        if (!$insMov) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo liberar la reserva bancaria', 500);
        }

        $updCheque = pg_query_params(
            $conn,
            "UPDATE public.cheques
             SET estado='Anulado',
                 observaciones = CASE WHEN $1 <> '' THEN CONCAT(COALESCE(observaciones,''),' | ', $1) ELSE observaciones END,
                 fecha_entrega = NULL,
                 recibido_por = NULL,
                 ci = NULL,
                 updated_at = now()
             WHERE id=$2",
            [$motivo, (int)$op['cheque']['id']]
        );
        if (!$updCheque) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo actualizar el cheque', 500);
        }

        $updOP = pg_query_params(
            $conn,
            "UPDATE public.orden_pago_cab
             SET estado='Anulada',
                 observacion = CASE WHEN $1 <> '' THEN CONCAT(COALESCE(observacion,''),' | Anulación: ',$1) ELSE observacion END
             WHERE id_orden_pago=$2",
            [$motivo, $id]
        );
        if (!$updOP) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo actualizar la orden', 500);
        }

        pg_query($conn, 'COMMIT');
        ok(['id_orden_pago' => $id, 'estado' => 'Anulada']);
    }

    if ($accion === 'imprimir') {
        pg_query($conn, 'BEGIN');
        $op = fetch_op($conn, $id, true);
        if (!$op) {
            pg_query($conn, 'ROLLBACK');
            bad('Orden no encontrada', 404);
        }
        if (!$op['cheque']) {
            pg_query($conn, 'ROLLBACK');
            bad('La orden no tiene cheque asociado', 409);
        }

        $upd = pg_query_params(
            $conn,
            "UPDATE public.cheques
             SET impreso_at = now(),
                 impreso_por = $1
             WHERE id = $2",
            [$_SESSION['nombre_usuario'], (int)$op['cheque']['id']]
        );
        if (!$upd) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo registrar la impresión del cheque', 500);
        }

        pg_query($conn, 'COMMIT');
        ok(['id_orden_pago' => $id, 'impreso' => true]);
    }

    bad('Acción no soportada', 405);
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
