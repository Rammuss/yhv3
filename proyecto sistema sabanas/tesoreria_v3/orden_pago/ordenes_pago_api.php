<?php
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

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function parse_bool($value, bool $default = false): bool {
    if ($value === null) return $default;
    if (is_bool($value)) return $value;
    $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $result === null ? $default : $result;
}

function pg_to_float($value): float {
    return $value === null ? 0.0 : (float)$value;
}

function pg_to_int($value): int {
    return $value === null ? 0 : (int)$value;
}

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $sql = "
            SELECT opc.id_orden_pago,
                   opc.fecha,
                   opc.id_proveedor,
                   p.nombre       AS proveedor_nombre,
                   p.ruc          AS proveedor_ruc,
                   opc.id_cuenta_bancaria,
                   cb.banco,
                   cb.numero_cuenta,
                   cb.moneda,
                   opc.total,
                   opc.moneda     AS moneda_op,
                   opc.estado,
                   opc.observacion,
                   opc.created_at,
                   opc.created_by
            FROM public.orden_pago_cab opc
            JOIN public.proveedores p       ON p.id_proveedor = opc.id_proveedor
            JOIN public.cuenta_bancaria cb  ON cb.id_cuenta_bancaria = opc.id_cuenta_bancaria
            WHERE opc.id_orden_pago = $1
        ";
        $stmt = pg_query_params($conn, $sql, [$id]);
        if (!$stmt) bad('Error al obtener orden', 500);
        $row = pg_fetch_assoc($stmt);
        if (!$row) bad('Orden de pago no encontrada', 404);

        $sqlDet = "
            SELECT opd.id_det,
                   opd.id_cxp,
                   opd.monto_aplicado,
                   opd.moneda,
                   cxp.saldo_actual,
                   cxp.estado,
                   cxp.fecha_venc,
                   f.numero_documento
            FROM public.orden_pago_det opd
            JOIN public.cuenta_pagar cxp ON cxp.id_cxp = opd.id_cxp
            JOIN public.factura_compra_cab f ON f.id_factura = cxp.id_factura
            WHERE opd.id_orden_pago = $1
            ORDER BY opd.id_det
        ";
        $stmtDet = pg_query_params($conn, $sqlDet, [$id]);
        if (!$stmtDet) bad('Error al obtener detalle', 500);
        $detalles = [];
        while ($d = pg_fetch_assoc($stmtDet)) {
            $detalles[] = [
                'id_det'        => pg_to_int($d['id_det']),
                'id_cxp'        => pg_to_int($d['id_cxp']),
                'monto_aplicado'=> pg_to_float($d['monto_aplicado']),
                'moneda'        => $d['moneda'],
                'saldo_actual'  => pg_to_float($d['saldo_actual']),
                'estado_cxp'    => $d['estado'],
                'fecha_venc'    => $d['fecha_venc'],
                'numero_documento' => $d['numero_documento'],
            ];
        }

        $sqlCheque = "
            SELECT ch.id,
                   ch.numero_cheque,
                   ch.beneficiario,
                   ch.monto_cheque,
                   ch.fecha_cheque,
                   ch.estado,
                   ch.fecha_entrega,
                   ch.recibido_por,
                   ch.observaciones,
                   ch.ci,
                   ch.id_cuenta_bancaria,
                   ch.id_chequera
            FROM public.cheques ch
            WHERE ch.id_orden_pago = $1
            ORDER BY ch.id DESC
            LIMIT 1
        ";
        $stmtCheque = pg_query_params($conn, $sqlCheque, [$id]);
        if (!$stmtCheque) bad('Error al obtener cheque', 500);
        $cheque = pg_fetch_assoc($stmtCheque);

        ok([
            'orden' => [
                'id_orden_pago' => pg_to_int($row['id_orden_pago']),
                'fecha'         => $row['fecha'],
                'proveedor'     => [
                    'id'     => pg_to_int($row['id_proveedor']),
                    'nombre' => $row['proveedor_nombre'],
                    'ruc'    => $row['proveedor_ruc'],
                ],
                'cuenta' => [
                    'id'     => pg_to_int($row['id_cuenta_bancaria']),
                    'banco'  => $row['banco'],
                    'numero' => $row['numero_cuenta'],
                    'moneda' => $row['moneda'],
                ],
                'total'        => pg_to_float($row['total']),
                'moneda'       => $row['moneda_op'],
                'estado'       => $row['estado'],
                'observacion'  => $row['observacion'],
                'created_at'   => $row['created_at'],
                'created_by'   => $row['created_by'],
            ],
            'detalles' => $detalles,
            'cheque'   => $cheque ? [
                'id'            => pg_to_int($cheque['id']),
                'numero'        => $cheque['numero_cheque'],
                'beneficiario'  => $cheque['beneficiario'],
                'monto'         => pg_to_float($cheque['monto_cheque']),
                'fecha'         => $cheque['fecha_cheque'],
                'estado'        => $cheque['estado'],
                'fecha_entrega' => $cheque['fecha_entrega'],
                'recibido_por'  => $cheque['recibido_por'],
                'observaciones' => $cheque['observaciones'],
                'ci'            => $cheque['ci'],
                'id_cuenta_bancaria' => pg_to_int($cheque['id_cuenta_bancaria']),
                'id_chequera'        => $cheque['id_chequera'] !== null ? (int)$cheque['id_chequera'] : null,
            ] : null
        ]);
    }

    // Listado
    $params = [];
    $filters = [];
    $idx = 1;

    if (!empty($_GET['id_proveedor'])) {
        $filters[] = 'opc.id_proveedor = $'.$idx;
        $params[] = (int)$_GET['id_proveedor'];
        $idx++;
    }
    if (!empty($_GET['id_cuenta_bancaria'])) {
        $filters[] = 'opc.id_cuenta_bancaria = $'.$idx;
        $params[] = (int)$_GET['id_cuenta_bancaria'];
        $idx++;
    }
    if (!empty($_GET['estado'])) {
        $filters[] = 'opc.estado = $'.$idx;
        $params[] = $_GET['estado'];
        $idx++;
    }
    if (!empty($_GET['fecha_desde'])) {
        $filters[] = 'opc.fecha >= $'.$idx;
        $params[] = $_GET['fecha_desde'];
        $idx++;
    }
    if (!empty($_GET['fecha_hasta'])) {
        $filters[] = 'opc.fecha <= $'.$idx;
        $params[] = $_GET['fecha_hasta'];
        $idx++;
    }

    $where = $filters ? 'WHERE '.implode(' AND ', $filters) : '';

    $sqlList = "
        SELECT opc.id_orden_pago,
               opc.fecha,
               opc.total,
               opc.moneda,
               opc.estado,
               p.nombre AS proveedor_nombre,
               cb.banco,
               cb.numero_cuenta
        FROM public.orden_pago_cab opc
        JOIN public.proveedores p      ON p.id_proveedor = opc.id_proveedor
        JOIN public.cuenta_bancaria cb ON cb.id_cuenta_bancaria = opc.id_cuenta_bancaria
        $where
        ORDER BY opc.fecha DESC, opc.id_orden_pago DESC
        LIMIT 200
    ";
    $stmtList = pg_query_params($conn, $sqlList, $params);
    if (!$stmtList) bad('Error al listar órdenes', 500);
    $data = [];
    while ($row = pg_fetch_assoc($stmtList)) {
        $data[] = [
            'id_orden_pago' => pg_to_int($row['id_orden_pago']),
            'fecha'         => $row['fecha'],
            'total'         => pg_to_float($row['total']),
            'moneda'        => $row['moneda'],
            'estado'        => $row['estado'],
            'proveedor'     => $row['proveedor_nombre'],
            'cuenta'        => $row['banco'].' · '.$row['numero_cuenta'],
        ];
    }
    ok(['data' => $data]);
}

// POST = crear
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = read_json_body();

$idProveedor       = (int)($input['id_proveedor'] ?? 0);
$idCuentaBancaria  = (int)($input['id_cuenta_bancaria'] ?? 0);
$detalleFacturas   = $input['facturas'] ?? [];
$numeroChequeInput = trim($input['numero_cheque'] ?? '');
$fechaCheque       = $input['fecha_cheque'] ?? date('Y-m-d');
$observacion       = trim($input['observacion'] ?? '');
$permitirSaldoNeg  = parse_bool($input['permitir_saldo_negativo'] ?? false, false);

if ($idProveedor <= 0)      bad('Proveedor inválido');
if ($idCuentaBancaria <= 0) bad('Cuenta bancaria inválida');
if (!is_array($detalleFacturas) || !$detalleFacturas) bad('Debe indicar al menos una factura');

$idsCxp = [];
$montos = [];
foreach ($detalleFacturas as $item) {
    if (!is_array($item)) bad('Formato de facturas inválido');
    $idCxp = isset($item['id_cxp']) ? (int)$item['id_cxp'] : 0;
    $monto = isset($item['monto']) ? (float)$item['monto'] : 0;
    if ($idCxp <= 0 || $monto <= 0) bad('Factura o monto inválido');
    $idsCxp[] = $idCxp;
    $montos[$idCxp] = $monto;
}

// Validaciones: facturas
$placeholders = [];
$params = [];
foreach ($idsCxp as $idx => $idc) {
    $placeholders[] = '$'.($idx+1);
    $params[] = $idc;
}
$sqlCxP = "
    SELECT cxp.id_cxp,
           cxp.id_proveedor,
           cxp.saldo_actual,
           cxp.estado,
           cxp.moneda,
           f.numero_documento
    FROM public.cuenta_pagar cxp
    JOIN public.factura_compra_cab f ON f.id_factura = cxp.id_factura
    WHERE cxp.id_cxp IN (".implode(',', $placeholders).")
";
$stmtCxP = pg_query_params($conn, $sqlCxP, $params);
if (!$stmtCxP) bad('Error al validar facturas', 500);
$facturas = [];
while ($row = pg_fetch_assoc($stmtCxP)) {
    $facturas[$row['id_cxp']] = $row;
}
if (count($facturas) !== count($idsCxp)) bad('Alguna factura no existe');

$monedaRef = null;
$totalOP = 0.0;
foreach ($idsCxp as $idc) {
    $row = $facturas[$idc];
    if ((int)$row['id_proveedor'] !== $idProveedor) bad('Todas las facturas deben ser del mismo proveedor');
    if ($monedaRef === null) $monedaRef = $row['moneda'];
    elseif ($row['moneda'] !== $monedaRef) bad('Todas las facturas deben ser de la misma moneda');
    $montoAplicado = $montos[$idc];
    if ($montoAplicado > (float)$row['saldo_actual']) bad('Monto a pagar excede el saldo de la factura '.$row['numero_documento']);
    if (in_array($row['estado'], ['Cancelada','Anulada'], true)) bad('Factura '.$row['numero_documento'].' no está pendiente');
    $totalOP += $montoAplicado;
}
if ($totalOP <= 0) bad('El total a pagar debe ser mayor a cero');

// Validar cuenta
$sqlCuenta = "
    SELECT cb.id_cuenta_bancaria,
           cb.banco,
           cb.numero_cuenta,
           COALESCE(contable.saldo_contable, 0) AS saldo_contable,
           COALESCE(reservado.saldo_reservado, 0) AS saldo_reservado
    FROM public.cuenta_bancaria cb
    LEFT JOIN (
        SELECT id_cuenta_bancaria, SUM(signo * monto) AS saldo_contable
        FROM public.cuenta_bancaria_mov
        WHERE tipo NOT IN ('RESERVA','LIBERACION')
        GROUP BY id_cuenta_bancaria
    ) contable ON contable.id_cuenta_bancaria = cb.id_cuenta_bancaria
    LEFT JOIN (
        SELECT id_cuenta_bancaria, SUM(signo * monto) AS saldo_reservado
        FROM public.cuenta_bancaria_mov
        WHERE tipo IN ('RESERVA','LIBERACION')
        GROUP BY id_cuenta_bancaria
    ) reservado ON reservado.id_cuenta_bancaria = cb.id_cuenta_bancaria
    WHERE cb.id_cuenta_bancaria = $1
      AND cb.estado = 'Activa'
";
$stmtCuenta = pg_query_params($conn, $sqlCuenta, [$idCuentaBancaria]);
if (!$stmtCuenta) bad('Error al validar cuenta bancaria', 500);
$cuenta = pg_fetch_assoc($stmtCuenta);
if (!$cuenta) bad('Cuenta bancaria no encontrada o inactiva', 404);

$saldoContable  = pg_to_float($cuenta['saldo_contable']);
$saldoReservado = pg_to_float($cuenta['saldo_reservado']);
$saldoDisponible = $saldoContable + $saldoReservado;

if (!$permitirSaldoNeg && $totalOP > $saldoDisponible) {
    bad('Fondos insuficientes: disponible '.number_format($saldoDisponible,2,'.',','));
}

pg_query($conn, 'BEGIN');

// Obtener chequera activa y bloquearla
$sqlChequera = "
    SELECT id_chequera,
           prefijo,
           sufijo,
           COALESCE(pad_length, 0)  AS pad_length,
           numero_inicio,
           numero_fin,
           proximo_numero
    FROM public.chequera
    WHERE id_cuenta_bancaria = $1
      AND activa = true
    ORDER BY id_chequera DESC
    LIMIT 1
    FOR UPDATE
";
$stmtChequera = pg_query_params($conn, $sqlChequera, [$idCuentaBancaria]);
if (!$stmtChequera) {
    pg_query($conn, 'ROLLBACK');
    bad('Error al obtener chequera de la cuenta', 500);
}
$chequera = pg_fetch_assoc($stmtChequera);
if (!$chequera) {
    pg_query($conn, 'ROLLBACK');
    bad('La cuenta seleccionada no tiene una chequera activa con cheques disponibles.');
}

$proximoNumero = (int)$chequera['proximo_numero'];
$numeroFin = $chequera['numero_fin'] !== null ? (int)$chequera['numero_fin'] : null;

if ($numeroFin !== null && $proximoNumero > $numeroFin) {
    pg_query($conn, 'ROLLBACK');
    bad('La chequera activa no tiene cheques disponibles.');
}

$padLength = (int)$chequera['pad_length'];
if ($padLength <= 0) {
    $padLength = max(strlen((string)$proximoNumero), strlen((string)($numeroFin ?? $proximoNumero)));
    if ($padLength <= 0) $padLength = 6;
}
$prefijo = $chequera['prefijo'] ?? '';
$sufijo  = $chequera['sufijo']  ?? '';
$numeroAuto = $prefijo . str_pad((string)$proximoNumero, $padLength, '0', STR_PAD_LEFT) . $sufijo;

if ($numeroChequeInput !== '' && $numeroChequeInput !== $numeroAuto) {
    pg_query($conn, 'ROLLBACK');
    bad('El número de cheque ingresado no coincide con el próximo disponible en la chequera.', 422);
}

$numeroAsignado = $numeroChequeInput !== '' ? $numeroChequeInput : $numeroAuto;

// Crear orden
$sqlOP = "
    INSERT INTO public.orden_pago_cab
        (fecha, id_proveedor, id_cuenta_bancaria, total, moneda, estado, observacion, created_at, created_by)
    VALUES
        ($1, $2, $3, $4, $5, 'Reservada', $6, now(), $7)
    RETURNING id_orden_pago
";
$stmtOP = pg_query_params($conn, $sqlOP, [
    $fechaCheque,
    $idProveedor,
    $idCuentaBancaria,
    $totalOP,
    $monedaRef,
    $observacion !== '' ? $observacion : null,
    $_SESSION['nombre_usuario']
]);
if (!$stmtOP) {
    pg_query($conn, 'ROLLBACK');
    bad('Error al crear orden de pago', 500);
}
$idOP = (int)pg_fetch_result($stmtOP, 0, 0);

// Detalle
$sqlDet = "
    INSERT INTO public.orden_pago_det
        (id_orden_pago, id_cxp, monto_aplicado, moneda)
    VALUES ($1, $2, $3, $4)
";
foreach ($idsCxp as $idc) {
    $ok = pg_query_params($conn, $sqlDet, [
        $idOP,
        $idc,
        $montos[$idc],
        $monedaRef
    ]);
    if (!$ok) {
        pg_query($conn, 'ROLLBACK');
        bad('Error al guardar detalle de la orden', 500);
    }
}

// Datos del proveedor para el cheque
$sqlProv = "SELECT nombre, ruc FROM public.proveedores WHERE id_proveedor = $1";
$stmtProv = pg_query_params($conn, $sqlProv, [$idProveedor]);
$beneficiario = 'Proveedor '.$idProveedor;
if ($stmtProv && pg_num_rows($stmtProv)) {
    $rowProv = pg_fetch_assoc($stmtProv);
    $beneficiario = trim(($rowProv['nombre'] ?? '').' '.($rowProv['ruc'] ?? '')) ?: $beneficiario;
}

// Registrar cheque
$sqlCheque = "
    INSERT INTO public.cheques
        (numero_cheque, beneficiario, monto_cheque, fecha_cheque, estado,
         id_cuenta_bancaria, id_orden_pago, observaciones, id_chequera)
    VALUES
        ($1, $2, $3, $4, 'Reservado', $5, $6, $7, $8)
    RETURNING id
";
$stmtCheque = pg_query_params($conn, $sqlCheque, [
    $numeroAsignado,
    $beneficiario,
    $totalOP,
    $fechaCheque,
    $idCuentaBancaria,
    $idOP,
    $observacion !== '' ? $observacion : null,
    (int)$chequera['id_chequera']
]);
if (!$stmtCheque) {
    pg_query($conn, 'ROLLBACK');
    bad('Error al registrar cheque', 500);
}
$idCheque = (int)pg_fetch_result($stmtCheque, 0, 0);

// Movimiento de reserva
$sqlMov = "
    INSERT INTO public.cuenta_bancaria_mov
        (id_cuenta_bancaria, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id)
    VALUES
        ($1, $2, 'RESERVA', -1, $3, $4, 'orden_pago', $5)
";
$descripcionReserva = sprintf('OP #%d Cheque #%s', $idOP, $numeroAsignado);
$okMov = pg_query_params($conn, $sqlMov, [
    $idCuentaBancaria,
    $fechaCheque,
    $totalOP,
    $descripcionReserva,
    $idOP
]);
if (!$okMov) {
    pg_query($conn, 'ROLLBACK');
    bad('Error al registrar reserva bancaria', 500);
}

// Actualizar chequera
$nextNumero = $proximoNumero + 1;
$activa = !($numeroFin !== null && $nextNumero > $numeroFin);
if ($numeroFin !== null && $nextNumero > $numeroFin) {
    $nextNumero = $numeroFin + 1;
}
$updChequera = pg_query_params(
    $conn,
    "UPDATE public.chequera SET proximo_numero = $1, activa = $2, updated_at = now() WHERE id_chequera = $3",
    [$nextNumero, $activa ? 't' : 'f', (int)$chequera['id_chequera']]
);
if (!$updChequera) {
    pg_query($conn, 'ROLLBACK');
    bad('No se pudo actualizar la chequera', 500);
}

pg_query($conn, 'COMMIT');

ok([
    'id_orden_pago' => $idOP,
    'id_cheque'     => $idCheque,
    'numero_cheque' => $numeroAsignado,
    'total'         => $totalOP,
    'moneda'        => $monedaRef,
    'saldo_disponible' => $saldoDisponible - $totalOP
]);
