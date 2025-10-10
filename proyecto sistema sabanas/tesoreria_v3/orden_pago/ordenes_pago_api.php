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

/* ----------------- helpers ----------------- */
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
function pg_to_float($v): float { return $v === null ? 0.0 : (float)$v; }
function pg_to_int($v): int     { return $v === null ? 0   : (int)$v; }

/* ===================== GET ===================== */
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    /* ----- detalle por id ----- */
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
                   cb.moneda      AS cuenta_moneda,
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
            LIMIT 1
        ";
        $stmt = pg_query_params($conn, $sql, [$id]);
        if (!$stmt) bad('Error al obtener orden', 500);
        $row = pg_fetch_assoc($stmt);
        if (!$row) bad('Orden de pago no encontrada', 404);

        // Detalle: LEFT JOIN factura para tolerar CxP sin factura (Reposición FF)
        $sqlDet = "
            SELECT opd.id_det,
                   opd.id_cxp,
                   opd.monto_aplicado,
                   opd.moneda,
                   cxp.saldo_actual,
                   cxp.estado,
                   cxp.fecha_venc,
                   cxp.id_factura,
                   f.numero_documento
            FROM public.orden_pago_det opd
            JOIN public.cuenta_pagar cxp ON cxp.id_cxp = opd.id_cxp
            LEFT JOIN public.factura_compra_cab f ON f.id_factura = cxp.id_factura
            WHERE opd.id_orden_pago = $1
            ORDER BY opd.id_det
        ";
        $stmtDet = pg_query_params($conn, $sqlDet, [$id]);
        if (!$stmtDet) bad('Error al obtener detalle', 500);
        $detalles = [];
        while ($d = pg_fetch_assoc($stmtDet)) {
            $detalles[] = [
                'id_det'          => pg_to_int($d['id_det']),
                'id_cxp'          => pg_to_int($d['id_cxp']),
                'monto_aplicado'  => pg_to_float($d['monto_aplicado']),
                'moneda'          => $d['moneda'],
                'saldo_actual'    => pg_to_float($d['saldo_actual']),
                'estado_cxp'      => $d['estado'],
                'fecha_venc'      => $d['fecha_venc'],
                'id_factura'      => $d['id_factura'] !== null ? (int)$d['id_factura'] : null,
                'numero_documento'=> $d['numero_documento'] ?? null,
            ];
        }

        // Cheque (si hay)
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
                    'moneda' => $row['cuenta_moneda'],
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

    /* ----- listado ----- */
    $params = [];
    $filters = [];
    $idx = 1;

    if (!empty($_GET['id_proveedor'])) { $filters[] = 'opc.id_proveedor = $'.$idx; $params[] = (int)$_GET['id_proveedor']; $idx++; }
    if (!empty($_GET['id_cuenta_bancaria'])) { $filters[] = 'opc.id_cuenta_bancaria = $'.$idx; $params[] = (int)$_GET['id_cuenta_bancaria']; $idx++; }
    if (!empty($_GET['estado'])) { $filters[] = 'opc.estado = $'.$idx; $params[] = $_GET['estado']; $idx++; }
    if (!empty($_GET['fecha_desde'])) { $filters[] = 'opc.fecha >= $'.$idx; $params[] = $_GET['fecha_desde']; $idx++; }
    if (!empty($_GET['fecha_hasta'])) { $filters[] = 'opc.fecha <= $'.$idx; $params[] = $_GET['fecha_hasta']; $idx++; }

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

/* ===================== POST (crear OP) ===================== */
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = read_json_body();

$idProveedor       = (int)($input['id_proveedor'] ?? 0);
$idCuentaBancaria  = (int)($input['id_cuenta_bancaria'] ?? 0);
$detalleCxp        = $input['facturas'] ?? []; // array de { id_cxp, monto }
$numeroChequeInput = trim($input['numero_cheque'] ?? '');
$fechaCheque       = $input['fecha_cheque'] ?? date('Y-m-d');
$observacion       = trim($input['observacion'] ?? '');
$permitirSaldoNeg  = parse_bool($input['permitir_saldo_negativo'] ?? false, false);

if ($idProveedor <= 0)      bad('Proveedor inválido');
if ($idCuentaBancaria <= 0) bad('Cuenta bancaria inválida');
if (!is_array($detalleCxp) || !$detalleCxp) bad('Debe indicar al menos una CxP');

$idsCxp = [];
$montos = [];
foreach ($detalleCxp as $item) {
    if (!is_array($item)) bad('Formato de ítems inválido');
    $idCxp = isset($item['id_cxp']) ? (int)$item['id_cxp'] : 0;
    $monto = isset($item['monto'])  ? (float)$item['monto'] : 0;
    if ($idCxp <= 0 || $monto <= 0) bad('CxP o monto inválido');
    $idsCxp[] = $idCxp;
    $montos[$idCxp] = $monto;
}

/* ------- validar CxP (sin exigir factura) ------- */
$placeholders = [];
$params = [];
foreach ($idsCxp as $i => $idc) { $placeholders[] = '$'.($i+1); $params[] = $idc; }

$sqlCxP = "
    SELECT id_cxp, id_proveedor, moneda, saldo_actual, estado
    FROM public.cuenta_pagar
    WHERE id_cxp IN (".implode(',', $placeholders).")
    FOR UPDATE
";
$stCxP = pg_query_params($conn, $sqlCxP, $params);
if (!$stCxP) bad('Error al validar CxP', 500);

$cxpRows = [];
while ($r = pg_fetch_assoc($stCxP)) {
    $cxpRows[(int)$r['id_cxp']] = $r;
}
if (count($cxpRows) !== count($idsCxp)) bad('Alguna CxP no existe');

$monedaRef = null;
$totalOP = 0.0;

foreach ($idsCxp as $idc) {
    $r = $cxpRows[$idc];
    if ((int)$r['id_proveedor'] !== $idProveedor) bad('Todas las CxP deben ser del mismo proveedor');
    if ($monedaRef === null) $monedaRef = $r['moneda'];
    elseif ($r['moneda'] !== $monedaRef) bad('Todas las CxP deben ser de la misma moneda');

    $montoAplicado = $montos[$idc];
    if ($montoAplicado > (float)$r['saldo_actual']) bad("Monto a pagar excede el saldo de la CxP #$idc");
    if (in_array($r['estado'], ['Cancelada','Anulada'], true)) bad("La CxP #$idc no está pendiente");
    $totalOP += $montoAplicado;
}
if ($totalOP <= 0) bad('El total a pagar debe ser mayor a cero');

/* ------- validar cuenta bancaria y chequera ------- */
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

/* chequera activa */
$sqlChequera = "
    SELECT id_chequera, prefijo, sufijo, COALESCE(pad_length,0) AS pad_length,
           numero_inicio, numero_fin, proximo_numero
    FROM public.chequera
    WHERE id_cuenta_bancaria = $1
      AND activa = true
    ORDER BY id_chequera DESC
    LIMIT 1
    FOR UPDATE
";
$stmtChequera = pg_query_params($conn, $sqlChequera, [$idCuentaBancaria]);
if (!$stmtChequera) { pg_query($conn,'ROLLBACK'); bad('Error al obtener chequera',500); }
$chequera = pg_fetch_assoc($stmtChequera);
if (!$chequera) { pg_query($conn,'ROLLBACK'); bad('La cuenta no tiene chequera activa.'); }

$prox = (int)$chequera['proximo_numero'];
$fin  = $chequera['numero_fin'] !== null ? (int)$chequera['numero_fin'] : null;
if ($fin !== null && $prox > $fin) { pg_query($conn,'ROLLBACK'); bad('La chequera no tiene cheques disponibles.'); }

$pad = (int)$chequera['pad_length'];
if ($pad <= 0) {
    $pad = max(strlen((string)$prox), strlen((string)($fin ?? $prox)));
    if ($pad <= 0) $pad = 6;
}
$pref  = $chequera['prefijo'] ?? '';
$suf   = $chequera['sufijo']  ?? '';
$numAuto = $pref . str_pad((string)$prox, $pad, '0', STR_PAD_LEFT) . $suf;

if ($numeroChequeInput !== '' && $numeroChequeInput !== $numAuto) {
    pg_query($conn,'ROLLBACK');
    bad('El número de cheque ingresado no coincide con el próximo disponible en la chequera.', 422);
}
$numeroAsignado = $numeroChequeInput !== '' ? $numeroChequeInput : $numAuto;

/* crear cabecera OP (RESERVADA) */
$sqlOP = "
    INSERT INTO public.orden_pago_cab
        (fecha, id_proveedor, id_cuenta_bancaria, total, moneda, estado, observacion, created_at, created_by)
    VALUES
        ($1, $2, $3, $4, $5, 'Reservada', NULLIF($6,''), now(), $7)
    RETURNING id_orden_pago
";
$stmtOP = pg_query_params($conn, $sqlOP, [
    $fechaCheque,
    $idProveedor,
    $idCuentaBancaria,
    $totalOP,
    $monedaRef,
    $observacion,
    $_SESSION['nombre_usuario']
]);
if (!$stmtOP) { pg_query($conn,'ROLLBACK'); bad('Error al crear orden de pago',500); }
$idOP = (int)pg_fetch_result($stmtOP, 0, 0);

/* detalle por CxP (NO toca cuenta_pagar todavía) */
$sqlDet = "INSERT INTO public.orden_pago_det (id_orden_pago, id_cxp, monto_aplicado, moneda) VALUES ($1,$2,$3,$4)";
foreach ($idsCxp as $idc) {
    $aplicar = $montos[$idc];
    $okDet = pg_query_params($conn, $sqlDet, [$idOP, $idc, $aplicar, $monedaRef]);
    if (!$okDet) { pg_query($conn,'ROLLBACK'); bad("Error al guardar detalle de la OP (CxP #$idc)",500); }
}

/* beneficiario del cheque */
$sqlProv = "SELECT nombre, ruc FROM public.proveedores WHERE id_proveedor = $1";
$stmtProv = pg_query_params($conn, $sqlProv, [$idProveedor]);
$beneficiario = 'Proveedor '.$idProveedor;
if ($stmtProv && pg_num_rows($stmtProv)) {
    $rowProv = pg_fetch_assoc($stmtProv);
    $beneficiario = trim(($rowProv['nombre'] ?? '').' '.($rowProv['ruc'] ?? '')) ?: $beneficiario;
}

/* registrar cheque (RESERVADO) */
$sqlCheque = "
    INSERT INTO public.cheques
        (numero_cheque, beneficiario, monto_cheque, fecha_cheque, estado,
         id_cuenta_bancaria, id_orden_pago, observaciones, id_chequera)
    VALUES
        ($1, $2, $3, $4, 'Reservado', $5, $6, NULLIF($7,''), $8)
    RETURNING id
";
$stmtCheque = pg_query_params($conn, $sqlCheque, [
    $numeroAsignado, $beneficiario, $totalOP, $fechaCheque,
    $idCuentaBancaria, $idOP, $observacion, (int)$chequera['id_chequera']
]);
if (!$stmtCheque) { pg_query($conn,'ROLLBACK'); bad('Error al registrar cheque',500); }
$idCheque = (int)pg_fetch_result($stmtCheque, 0, 0);

/* movimiento de RESERVA en cuenta bancaria */
$sqlMovBanco = "
    INSERT INTO public.cuenta_bancaria_mov
        (id_cuenta_bancaria, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id)
    VALUES
        ($1, $2, 'RESERVA', -1, $3, $4, 'orden_pago', $5)
";
$descReserva = sprintf('OP #%d Cheque #%s', $idOP, $numeroAsignado);
$okMovB = pg_query_params($conn, $sqlMovBanco, [
    $idCuentaBancaria, $fechaCheque, $totalOP, $descReserva, $idOP
]);
if (!$okMovB) { pg_query($conn,'ROLLBACK'); bad('Error al registrar reserva bancaria',500); }

/* actualizar chequera (siguiente número y activa) */
$next = $prox + 1;
$activa = !($fin !== null && $next > $fin);
if ($fin !== null && $next > $fin) $next = $fin + 1;

$updChequera = pg_query_params(
    $conn,
    "UPDATE public.chequera SET proximo_numero=$1, activa=$2, updated_at=now() WHERE id_chequera=$3",
    [$next, $activa ? 't' : 'f', (int)$chequera['id_chequera']]
);
if (!$updChequera) { pg_query($conn,'ROLLBACK'); bad('No se pudo actualizar la chequera',500); }

pg_query($conn, 'COMMIT');

ok([
    'id_orden_pago'   => $idOP,
    'id_cheque'       => $idCheque,
    'numero_cheque'   => $numeroAsignado,
    'total'           => $totalOP,
    'moneda'          => $monedaRef,
    'saldo_disponible'=> $saldoDisponible - $totalOP
]);
