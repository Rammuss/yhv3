<?php
// buscar_cuotas.php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        throw new Exception('Parámetro q requerido');
    }

    // 1) Identificar cliente por RUC/CI o nombre
    $rc = pg_query_params(
        $conn,
        "SELECT id_cliente, (nombre||' '||apellido) AS nombre, ruc_ci
     FROM public.clientes
     WHERE ruc_ci ILIKE $1 OR (nombre||' '||apellido) ILIKE $2
     ORDER BY ruc_ci ILIKE $1 DESC, (nombre||' '||apellido) ILIKE $2 DESC
     LIMIT 1",
        ['%' . $q . '%', '%' . $q . '%']
    );
    if (!$rc || pg_num_rows($rc) === 0) {
        echo json_encode(['success' => true, 'cuotas' => []]);
        exit;
    }
    $cli = pg_fetch_assoc($rc);
    $id_cliente = (int)$cli['id_cliente'];

    // 2) Traer "cuotas" pendientes desde cuenta_cobrar
    //    (una fila por cuota si usás el esquema extendido)
    $sql = "
    SELECT
      cxc.id_cxc                         AS id_cuota,
      f.id_factura,
      f.numero_documento,
      cxc.nro_cuota,
      (SELECT COUNT(*) FROM public.cuenta_cobrar z WHERE z.id_factura = cxc.id_factura) AS cant_cuotas,
      cxc.fecha_vencimiento::text        AS vencimiento,
      cxc.monto_origen::numeric(14,2)    AS total,
      cxc.saldo_actual::numeric(14,2)    AS saldo
    FROM public.cuenta_cobrar cxc
    JOIN public.factura_venta_cab f ON f.id_factura = cxc.id_factura
    WHERE cxc.id_cliente = $1
      AND COALESCE(cxc.saldo_actual,0) > 0
    ORDER BY f.numero_documento ASC,
             COALESCE(cxc.nro_cuota, 999999) ASC,
             cxc.fecha_vencimiento ASC,
             cxc.id_cxc ASC
    LIMIT 200
  ";

    $res = pg_query_params($conn, $sql, [$id_cliente]);
    if (!$res) {
        throw new Exception('Error consultando cuotas');
    }

    $cuotas = [];
    while ($r = pg_fetch_assoc($res)) {
        $cuotas[] = [
            'id_cuota'        => (int)$r['id_cuota'],           // viene de id_cxc
            'numero_documento' => $r['numero_documento'],
            'nro_cuota'       => (int)$r['nro_cuota'],
            'cant_cuotas'     => (int)$r['cant_cuotas'],
            'vencimiento'     => $r['vencimiento'],
            'total'           => (float)$r['total'],
            'saldo'           => (float)$r['saldo'],
        ];
    }

    echo json_encode([
        'success' => true,
        'cliente' => [
            'id_cliente' => $id_cliente,
            'nombre'     => $cli['nombre'],
            'ruc_ci'     => $cli['ruc_ci'],
        ],
        'cuotas'  => $cuotas
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
