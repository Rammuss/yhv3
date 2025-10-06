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
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$idCuenta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

if ($idCuenta > 0) {
    $sqlCuenta = "
        SELECT cb.id_cuenta_bancaria,
               cb.banco,
               cb.numero_cuenta,
               cb.tipo,
               cb.moneda,
               cb.estado,
               COALESCE(contable.saldo_contable, 0) AS saldo_contable,
               COALESCE(reservado.saldo_reservado, 0) AS saldo_reservado,
               (COALESCE(contable.saldo_contable, 0) + COALESCE(reservado.saldo_reservado, 0)) AS saldo_disponible,
               COALESCE(reservas.reservas_activas, 0) AS reservas_activas,
               COALESCE(reservas.monto_reservado, 0) AS reservas_monto,
               COALESCE(ch.cheques_reservados, 0) AS cheques_reservados,
               COALESCE(ch.cheques_emitidos, 0) AS cheques_emitidos,
               COALESCE(ch.cheques_cobrados, 0) AS cheques_cobrados,
               chq.id_chequera      AS chequera_id,
               chq.prefijo          AS chequera_prefijo,
               chq.sufijo           AS chequera_sufijo,
               chq.pad_length       AS chequera_pad_length,
               chq.proximo_numero   AS chequera_proximo,
               chq.numero_fin       AS chequera_numero_fin
        FROM public.cuenta_bancaria cb
        LEFT JOIN (
            SELECT id_cuenta_bancaria,
                   SUM(signo * monto) AS saldo_contable
            FROM public.cuenta_bancaria_mov
            WHERE tipo NOT IN ('RESERVA','LIBERACION')
            GROUP BY id_cuenta_bancaria
        ) contable ON contable.id_cuenta_bancaria = cb.id_cuenta_bancaria
        LEFT JOIN (
            SELECT id_cuenta_bancaria,
                   SUM(signo * monto) AS saldo_reservado
            FROM public.cuenta_bancaria_mov
            WHERE tipo IN ('RESERVA','LIBERACION')
            GROUP BY id_cuenta_bancaria
        ) reservado ON reservado.id_cuenta_bancaria = cb.id_cuenta_bancaria
        LEFT JOIN (
            SELECT id_cuenta_bancaria,
                   SUM(CASE WHEN balance < 0 THEN 1 ELSE 0 END) AS reservas_activas,
                   SUM(CASE WHEN balance < 0 THEN -balance ELSE 0 END) AS monto_reservado
            FROM (
                SELECT id_cuenta_bancaria,
                       COALESCE(ref_tabla,'') AS ref_tabla,
                       COALESCE(ref_id,0) AS ref_id,
                       SUM(signo * monto) AS balance
                FROM public.cuenta_bancaria_mov
                WHERE tipo IN ('RESERVA','LIBERACION')
                GROUP BY id_cuenta_bancaria, COALESCE(ref_tabla,''), COALESCE(ref_id,0)
            ) agg
            GROUP BY id_cuenta_bancaria
        ) reservas ON reservas.id_cuenta_bancaria = cb.id_cuenta_bancaria
        LEFT JOIN (
            SELECT id_cuenta_bancaria,
                   COUNT(*) FILTER (WHERE estado = 'Reservado') AS cheques_reservados,
                   COUNT(*) FILTER (WHERE estado IN ('Emitido','Entregado')) AS cheques_emitidos,
                   COUNT(*) FILTER (WHERE estado = 'Cobrado') AS cheques_cobrados
            FROM public.cheques
            GROUP BY id_cuenta_bancaria
        ) ch ON ch.id_cuenta_bancaria = cb.id_cuenta_bancaria
        LEFT JOIN LATERAL (
            SELECT id_chequera,
                   prefijo,
                   sufijo,
                   pad_length,
                   proximo_numero,
                   numero_fin
            FROM public.chequera
            WHERE id_cuenta_bancaria = cb.id_cuenta_bancaria
              AND activa = true
            ORDER BY id_chequera DESC
            LIMIT 1
        ) chq ON true
        WHERE cb.id_cuenta_bancaria = $1
    ";
    $stmtCuenta = pg_query_params($conn, $sqlCuenta, [$idCuenta]);
    if (!$stmtCuenta) bad('Error al obtener cuenta bancaria', 500);
    $cuenta = pg_fetch_assoc($stmtCuenta);
    if (!$cuenta) bad('Cuenta bancaria no encontrada', 404);

    $sqlReservas = "
        SELECT COALESCE(ref_tabla,'') AS ref_tabla,
               COALESCE(ref_id,0)     AS ref_id,
               MIN(fecha)             AS fecha_inicio,
               MAX(fecha)             AS fecha_ult,
               SUM(signo * monto)     AS saldo_reserva,
               STRING_AGG(descripcion, ' | ' ORDER BY fecha) AS descripciones
        FROM public.cuenta_bancaria_mov
        WHERE id_cuenta_bancaria = $1
          AND tipo IN ('RESERVA','LIBERACION')
        GROUP BY ref_tabla, ref_id
        HAVING SUM(signo * monto) < 0
        ORDER BY fecha_inicio ASC
    ";
    $stmtReservas = pg_query_params($conn, $sqlReservas, [$idCuenta]);
    if (!$stmtReservas) bad('Error al listar reservas', 500);
    $reservas = [];
    while ($row = pg_fetch_assoc($stmtReservas)) {
        $reservas[] = [
            'ref_tabla'    => $row['ref_tabla'],
            'ref_id'       => (int)$row['ref_id'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_ult'    => $row['fecha_ult'],
            'monto'        => (float)$row['saldo_reserva'] * -1,
            'descripcion'  => $row['descripciones'],
        ];
    }

    $limitMov = max(1, min(200, (int)($_GET['mov_limit'] ?? 25)));
    $sqlMov = "
        SELECT id_mov,
               fecha,
               tipo,
               signo,
               monto,
               descripcion,
               ref_tabla,
               ref_id,
               created_at
        FROM public.cuenta_bancaria_mov
        WHERE id_cuenta_bancaria = $1
        ORDER BY fecha DESC, id_mov DESC
        LIMIT $limitMov
    ";
    $stmtMov = pg_query_params($conn, $sqlMov, [$idCuenta]);
    if (!$stmtMov) bad('Error al listar movimientos', 500);
    $movimientos = [];
    $saldoAcum = 0;
    while ($row = pg_fetch_assoc($stmtMov)) {
        $balance = (float)$row['signo'] * (float)$row['monto'];
        $saldoAcum += $balance;
        $movimientos[] = [
            'id_mov'      => (int)$row['id_mov'],
            'fecha'       => $row['fecha'],
            'tipo'        => $row['tipo'],
            'signo'       => (int)$row['signo'],
            'monto'       => (float)$row['monto'],
            'descripcion' => $row['descripcion'],
            'ref_tabla'   => $row['ref_tabla'],
            'ref_id'      => $row['ref_id'] !== null ? (int)$row['ref_id'] : null,
            'created_at'  => $row['created_at'],
            'balance_item'=> $balance,
            'balance_acum'=> $saldoAcum,
        ];
    }

    $limitCheques = max(1, min(200, (int)($_GET['cheque_limit'] ?? 25)));
    $sqlCheques = "
        SELECT id,
               numero_cheque,
               beneficiario,
               monto_cheque,
               fecha_cheque,
               estado,
               fecha_entrega,
               recibido_por,
               observaciones,
               ci,
               id_orden_pago,
               created_at,
               updated_at
        FROM public.cheques
        WHERE id_cuenta_bancaria = $1
        ORDER BY created_at DESC, id DESC
        LIMIT $limitCheques
    ";
    $stmtCheques = pg_query_params($conn, $sqlCheques, [$idCuenta]);
    if (!$stmtCheques) bad('Error al listar cheques', 500);
    $cheques = [];
    while ($row = pg_fetch_assoc($stmtCheques)) {
        $cheques[] = [
            'id'            => (int)$row['id'],
            'numero'        => $row['numero_cheque'],
            'beneficiario'  => $row['beneficiario'],
            'monto'         => (float)$row['monto_cheque'],
            'fecha'         => $row['fecha_cheque'],
            'estado'        => $row['estado'],
            'fecha_entrega' => $row['fecha_entrega'],
            'recibido_por'  => $row['recibido_por'],
            'observaciones' => $row['observaciones'],
            'id_orden_pago' => $row['id_orden_pago'] !== null ? (int)$row['id_orden_pago'] : null,
            'created_at'    => $row['created_at'],
            'updated_at'    => $row['updated_at'],
        ];
    }

    $chequeraActiva = null;
    if ($cuenta['chequera_id'] !== null) {
        $chequeraActiva = [
            'id_chequera'    => (int)$cuenta['chequera_id'],
            'prefijo'        => $cuenta['chequera_prefijo'],
            'sufijo'         => $cuenta['chequera_sufijo'],
            'pad_length'     => $cuenta['chequera_pad_length'] !== null ? (int)$cuenta['chequera_pad_length'] : null,
            'proximo_numero' => $cuenta['chequera_proximo'] !== null ? (int)$cuenta['chequera_proximo'] : null,
            'numero_fin'     => $cuenta['chequera_numero_fin'] !== null ? (int)$cuenta['chequera_numero_fin'] : null,
        ];
    }

    ok([
        'cuenta' => [
            'id_cuenta_bancaria' => (int)$cuenta['id_cuenta_bancaria'],
            'banco'              => $cuenta['banco'],
            'numero_cuenta'      => $cuenta['numero_cuenta'],
            'tipo'               => $cuenta['tipo'],
            'moneda'             => $cuenta['moneda'],
            'estado'             => $cuenta['estado'],
            'saldo_contable'     => (float)$cuenta['saldo_contable'],
            'saldo_reservado'    => (float)$cuenta['saldo_reservado'],
            'saldo_disponible'   => (float)$cuenta['saldo_disponible'],
            'reservas_activas'   => (int)$cuenta['reservas_activas'],
            'reservas_monto'     => (float)$cuenta['reservas_monto'],
            'cheques_reservados' => (int)$cuenta['cheques_reservados'],
            'cheques_emitidos'   => (int)$cuenta['cheques_emitidos'],
            'cheques_cobrados'   => (int)$cuenta['cheques_cobrados'],
            'chequera'           => $chequeraActiva,
        ],
        'reservas_abiertas' => $reservas,
        'movimientos'       => $movimientos,
        'cheques'           => $cheques,
    ]);
}

$estado = trim($_GET['estado'] ?? '');
$moneda = trim($_GET['moneda'] ?? '');
$buscar = trim($_GET['q'] ?? '');
$withTotals = parse_bool($_GET['with_totals'] ?? false);

$params = [];
$conditions = [];

if ($estado !== '') {
    $conditions[] = 'cb.estado = $' . (count($params) + 1);
    $params[] = $estado;
}
if ($moneda !== '') {
    $conditions[] = 'cb.moneda = $' . (count($params) + 1);
    $params[] = $moneda;
}
if ($buscar !== '') {
    $conditions[] = '(cb.banco ILIKE $' . (count($params) + 1) . ' OR cb.numero_cuenta ILIKE $' . (count($params) + 1) . ')';
    $params[] = '%' . $buscar . '%';
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sqlSummary = "
    SELECT cb.id_cuenta_bancaria,
           cb.banco,
           cb.numero_cuenta,
           cb.tipo,
           cb.moneda,
           cb.estado,
           COALESCE(contable.saldo_contable, 0) AS saldo_contable,
           COALESCE(reservado.saldo_reservado, 0) AS saldo_reservado,
           (COALESCE(contable.saldo_contable, 0) + COALESCE(reservado.saldo_reservado, 0)) AS saldo_disponible,
           COALESCE(reservas.reservas_activas, 0) AS reservas_activas,
           COALESCE(reservas.monto_reservado, 0) AS reservas_monto,
           COALESCE(ch.cheques_reservados, 0) AS cheques_reservados,
           COALESCE(ch.cheques_emitidos, 0) AS cheques_emitidos,
           COALESCE(ch.cheques_cobrados, 0) AS cheques_cobrados,
           chq.id_chequera      AS chequera_id,
           chq.prefijo          AS chequera_prefijo,
           chq.sufijo           AS chequera_sufijo,
           chq.pad_length       AS chequera_pad_length,
           chq.proximo_numero   AS chequera_proximo,
           chq.numero_fin       AS chequera_numero_fin
    FROM public.cuenta_bancaria cb
    LEFT JOIN (
        SELECT id_cuenta_bancaria,
               SUM(signo * monto) AS saldo_contable
        FROM public.cuenta_bancaria_mov
        WHERE tipo NOT IN ('RESERVA','LIBERACION')
        GROUP BY id_cuenta_bancaria
    ) contable ON contable.id_cuenta_bancaria = cb.id_cuenta_bancaria
    LEFT JOIN (
        SELECT id_cuenta_bancaria,
               SUM(signo * monto) AS saldo_reservado
        FROM public.cuenta_bancaria_mov
        WHERE tipo IN ('RESERVA','LIBERACION')
        GROUP BY id_cuenta_bancaria
    ) reservado ON reservado.id_cuenta_bancaria = cb.id_cuenta_bancaria
    LEFT JOIN (
        SELECT id_cuenta_bancaria,
               SUM(CASE WHEN balance < 0 THEN 1 ELSE 0 END) AS reservas_activas,
               SUM(CASE WHEN balance < 0 THEN -balance ELSE 0 END) AS monto_reservado
        FROM (
            SELECT id_cuenta_bancaria,
                   COALESCE(ref_tabla,'') AS ref_tabla,
                   COALESCE(ref_id,0) AS ref_id,
                   SUM(signo * monto) AS balance
            FROM public.cuenta_bancaria_mov
            WHERE tipo IN ('RESERVA','LIBERACION')
            GROUP BY id_cuenta_bancaria, COALESCE(ref_tabla,''), COALESCE(ref_id,0)
        ) agg
        GROUP BY id_cuenta_bancaria
    ) reservas ON reservas.id_cuenta_bancaria = cb.id_cuenta_bancaria
    LEFT JOIN (
        SELECT id_cuenta_bancaria,
               COUNT(*) FILTER (WHERE estado = 'Reservado') AS cheques_reservados,
               COUNT(*) FILTER (WHERE estado IN ('Emitido','Entregado')) AS cheques_emitidos,
               COUNT(*) FILTER (WHERE estado = 'Cobrado') AS cheques_cobrados
        FROM public.cheques
        GROUP BY id_cuenta_bancaria
    ) ch ON ch.id_cuenta_bancaria = cb.id_cuenta_bancaria
    LEFT JOIN LATERAL (
        SELECT id_chequera,
               prefijo,
               sufijo,
               pad_length,
               proximo_numero,
               numero_fin
        FROM public.chequera
        WHERE id_cuenta_bancaria = cb.id_cuenta_bancaria
          AND activa = true
        ORDER BY id_chequera DESC
        LIMIT 1
    ) chq ON true
    $whereSql
    ORDER BY cb.banco, cb.numero_cuenta
";
$stmtSummary = pg_query_params($conn, $sqlSummary, $params);
if (!$stmtSummary) bad('Error al listar cuentas bancarias', 500);

$rows = [];
while ($row = pg_fetch_assoc($stmtSummary)) {
    $rows[] = [
        'id_cuenta_bancaria' => (int)$row['id_cuenta_bancaria'],
        'banco'              => $row['banco'],
        'numero_cuenta'      => $row['numero_cuenta'],
        'tipo'               => $row['tipo'],
        'moneda'             => $row['moneda'],
        'estado'             => $row['estado'],
        'saldo_contable'     => (float)$row['saldo_contable'],
        'saldo_reservado'    => (float)$row['saldo_reservado'],
        'saldo_disponible'   => (float)$row['saldo_disponible'],
        'reservas_activas'   => (int)$row['reservas_activas'],
        'reservas_monto'     => (float)$row['reservas_monto'],
        'cheques_reservados' => (int)$row['cheques_reservados'],
        'cheques_emitidos'   => (int)$row['cheques_emitidos'],
        'cheques_cobrados'   => (int)$row['cheques_cobrados'],
        'chequera'           => $row['chequera_id'] !== null ? [
            'id_chequera'    => (int)$row['chequera_id'],
            'prefijo'        => $row['chequera_prefijo'],
            'sufijo'         => $row['chequera_sufijo'],
            'pad_length'     => $row['chequera_pad_length'] !== null ? (int)$row['chequera_pad_length'] : null,
            'proximo_numero' => $row['chequera_proximo'] !== null ? (int)$row['chequera_proximo'] : null,
            'numero_fin'     => $row['chequera_numero_fin'] !== null ? (int)$row['chequera_numero_fin'] : null,
        ] : null,
    ];
}

$result = [
    'data' => $rows,
];

if ($withTotals) {
    $totals = [
        'saldo_contable'  => 0.0,
        'saldo_reservado' => 0.0,
        'saldo_disponible'=> 0.0,
        'reservas_activas'=> 0,
        'cheques_reservados' => 0,
        'cheques_emitidos'   => 0,
        'cheques_cobrados'   => 0,
    ];
    foreach ($rows as $row) {
        $totals['saldo_contable']   += $row['saldo_contable'];
        $totals['saldo_reservado']  += $row['saldo_reservado'];
        $totals['saldo_disponible'] += $row['saldo_disponible'];
        $totals['reservas_activas'] += $row['reservas_activas'];
        $totals['cheques_reservados'] += $row['cheques_reservados'];
        $totals['cheques_emitidos']   += $row['cheques_emitidos'];
        $totals['cheques_cobrados']   += $row['cheques_cobrados'];
    }
    $result['totals'] = $totals;
}

ok($result);
