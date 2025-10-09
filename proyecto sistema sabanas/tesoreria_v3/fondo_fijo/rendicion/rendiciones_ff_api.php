<?php
/**
 * Fondo Fijo – Rendiciones
 *
 * GET  /fondo_fijo/rendicion_ff/rendiciones_ff_api.php
 *      -> listado con filtros o detalle (?id_rendicion=)
 *
 * POST /fondo_fijo/rendicion_ff/rendiciones_ff_api.php
 *      body: { accion:"crear", id_ff, observacion? }
 *      -> crea cabecera de rendición (estado = 'En revisión')
 *
 *      body: { accion:"agregar_items", id_rendicion, items:[ {...}, ... ] }
 *      -> inserta ítems en lote con estado_item = 'Pendiente'
 *
 * PATCH /fondo_fijo/rendicion_ff/rendiciones_ff_api.php?id_rendicion=#
 *      body: {
 *        accion: "aprobar_lote",
 *        all_or_nothing?: true|false,
 *        permitir_negativo?: true|false,
 *        items: [
 *          { id_item, aprobar: true|false, imputa_libro?: true|false|"true"|"false"|"", motivo_rechazo?: string }
 *        ]
 *      }
 *      -> aprueba/rechaza en lote; inserta en public.libro_compras si imputa_libro=true
 *         y **DESCUENTA** saldo del Fondo Fijo por los ítems aprobados (idempotente).
 */

session_start();
require_once __DIR__ . '../../../../conexion/configv2.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ------------ Helpers ------------ */
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
function ffloat($v): float { return $v === null ? 0.0 : (float)$v; }
function fint($v): int     { return $v === null ? 0   : (int)$v;   }
function parse_bool($v, bool $default=false): bool {
    if ($v === null) return $default;
    if (is_bool($v)) return $v;
    $r = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $r === null ? $default : $r;
}
/** Normaliza cualquier cosa a 'true'/'false'/null para SET boolean. */
function to_bool_text_or_null($v) {
    if (is_null($v)) return null;
    if (is_bool($v)) return $v ? 'true' : 'false';
    $s = trim((string)$v);
    if ($s === '') return null;
    $s = mb_strtolower($s);
    if (in_array($s, ['t','true','1','on','si','sí','y','yes'], true))  return 'true';
    if (in_array($s, ['f','false','0','off','no','n'], true))           return 'false';
    return null; // desconocido => no tocar
}

/** Recomputa estado de la rendición según items. */
function recompute_rendicion_estado($conn, int $id_rendicion): string {
    $sql = "SELECT
                SUM(CASE WHEN estado_item='Pendiente' THEN 1 ELSE 0 END) AS pend,
                SUM(CASE WHEN estado_item='Aprobado'  THEN 1 ELSE 0 END) AS apr,
                SUM(CASE WHEN estado_item='Rechazado' THEN 1 ELSE 0 END) AS rej
            FROM public.ff_rendiciones_items
            WHERE id_rendicion = $1";
    $st = pg_query_params($conn, $sql, [$id_rendicion]);
    if (!$st) return 'En revisión';
    $r = pg_fetch_assoc($st);
    $pend = (int)($r['pend'] ?? 0);
    $apr  = (int)($r['apr']  ?? 0);
    $rej  = (int)($r['rej']  ?? 0);

    if ($pend > 0) return 'En revisión';
    if ($apr > 0 && $rej === 0) return 'Aprobada';
    if ($apr > 0 && $rej > 0)   return 'Parcial';
    if ($apr === 0 && $rej > 0) return 'Parcial'; // o 'Rechazada'
    return 'En revisión';
}

/** Inserta en libro_compras si no hay duplicado. Devuelve ['ok'=>bool, 'dup'=>bool, 'id_libro'=>?] */
function libro_insert_if_new($conn, array $it): array {
    // Duplicado: mismo id_proveedor + numero_documento + fecha + documento_tipo
    $chk = pg_query_params(
        $conn,
        "SELECT id_libro FROM public.libro_compras
         WHERE id_proveedor = $1 AND numero_documento = $2 AND fecha = $3 AND documento_tipo = $4
         LIMIT 1",
        [
            (int)$it['id_proveedor'],
            $it['numero_documento'],
            $it['fecha'],              // YYYY-MM-DD
            $it['documento_tipo']      // 'FACT' | 'TICKET' | 'RECIBO' | etc.
        ]
    );
    if ($chk && pg_num_rows($chk)) {
        $row = pg_fetch_assoc($chk);
        return ['ok'=>true, 'dup'=>true, 'id_libro'=>(int)$row['id_libro']];
    }

    $ins = pg_query_params(
        $conn,
        "INSERT INTO public.libro_compras
           (id_factura, fecha, id_proveedor, ruc, numero_documento,
            gravada_10, iva_10, gravada_5, iva_5, exentas, total,
            estado, timbrado_numero, documento_tipo, id_nota)
         VALUES
           (NULL, $1::date, $2, $3, $4,
            $5, $6, $7, $8, $9, $10,
            'Vigente', $11, $12, NULL)
         RETURNING id_libro",
        [
            $it['fecha'],
            (int)$it['id_proveedor'],
            $it['ruc'] ?: null,
            $it['numero_documento'],
            ffloat($it['gravada_10'] ?? 0),
            ffloat($it['iva_10'] ?? 0),
            ffloat($it['gravada_5'] ?? 0),
            ffloat($it['iva_5'] ?? 0),
            ffloat($it['exentas'] ?? 0),
            ffloat($it['total'] ?? 0),
            $it['timbrado_numero'] ?: null,
            $it['documento_tipo'] ?: 'FACT'
        ]
    );
    if (!$ins) return ['ok'=>false, 'dup'=>false, 'id_libro'=>null];

    $row = pg_fetch_assoc($ins);
    return ['ok'=>true, 'dup'=>false, 'id_libro'=>(int)$row['id_libro']];
}

/* ------------ GET ------------ */
if ($method === 'GET') {
    // Detalle
    if (!empty($_GET['id_rendicion'])) {
        $id = (int)$_GET['id_rendicion'];

        $h = pg_query_params(
            $conn,
            "SELECT r.id_rendicion, r.id_ff, ff.nombre_caja, r.estado, r.observacion,
                    r.created_at, r.created_by, r.updated_at
             FROM public.ff_rendiciones r
             JOIN public.fondo_fijo ff ON ff.id_ff = r.id_ff
             WHERE r.id_rendicion = $1",
            [$id]
        );
        if (!$h || !pg_num_rows($h)) bad('Rendición no encontrada', 404);
        $head = pg_fetch_assoc($h);

        $it = pg_query_params(
            $conn,
            "SELECT i.*
             FROM public.ff_rendiciones_items i
             WHERE i.id_rendicion = $1
             ORDER BY i.fecha ASC, i.id_item ASC",
            [$id]
        );
        if ($it === false) bad('Error al listar ítems', 500);
        $items = [];
        while ($r = pg_fetch_assoc($it)) {
            $items[] = [
                'id_item'          => fint($r['id_item']),
                'fecha'            => $r['fecha'],
                'id_proveedor'     => $r['id_proveedor'] !== null ? (int)$r['id_proveedor'] : null,
                'ruc'              => $r['ruc'],
                'documento_tipo'   => $r['documento_tipo'],
                'numero_documento' => $r['numero_documento'],
                'timbrado_numero'  => $r['timbrado_numero'],
                'gravada_10'       => ffloat($r['gravada_10']),
                'iva_10'           => ffloat($r['iva_10']),
                'gravada_5'        => ffloat($r['gravada_5']),
                'iva_5'            => ffloat($r['iva_5']),
                'exentas'          => ffloat($r['exentas']),
                'total'            => ffloat($r['total']),
                'estado_item'      => $r['estado_item'],
                'imputa_libro'     => ($r['imputa_libro'] === 't' || $r['imputa_libro'] === true),
                'observacion'      => $r['observacion'],
                'created_at'       => $r['created_at'],
            ];
        }

        ok([
            'rendicion' => [
                'id_rendicion' => fint($head['id_rendicion']),
                'id_ff'        => fint($head['id_ff']),
                'nombre_ff'    => $head['nombre_caja'],
                'estado'       => $head['estado'],
                'observacion'  => $head['observacion'],
                'created_at'   => $head['created_at'],
                'created_by'   => $head['created_by'],
                'updated_at'   => $head['updated_at'],
            ],
            'items' => $items
        ]);
    }

    // Listado con filtros
    $params = [];
    $filters = [];
    $ix = 1;

    if (!empty($_GET['estado'])) {
        $filters[] = "r.estado = $" . $ix;
        $params[] = $_GET['estado']; $ix++;
    }
    if (!empty($_GET['id_ff'])) {
        $filters[] = "r.id_ff = $" . $ix;
        $params[] = (int)$_GET['id_ff']; $ix++;
    }
    if (!empty($_GET['q'])) {
        $filters[] = "(ff.nombre_caja ILIKE $" . $ix . " OR r.observacion ILIKE $" . $ix . ")";
        $params[] = '%' . trim($_GET['q']) . '%'; $ix++;
    }

    $where = $filters ? "WHERE " . implode(' AND ', $filters) : "";

    $st = pg_query_params(
        $conn,
        "SELECT r.id_rendicion, r.id_ff, ff.nombre_caja, r.estado, r.observacion, r.created_at, r.created_by
         FROM public.ff_rendiciones r
         JOIN public.fondo_fijo ff ON ff.id_ff = r.id_ff
         $where
         ORDER BY r.created_at DESC, r.id_rendicion DESC
         LIMIT 300",
        $params
    );
    if (!$st) bad('Error al listar rendiciones', 500);

    $rows = [];
    while ($r = pg_fetch_assoc($st)) {
        $rows[] = [
            'id_rendicion' => fint($r['id_rendicion']),
            'id_ff'        => fint($r['id_ff']),
            'nombre_ff'    => $r['nombre_caja'],
            'estado'       => $r['estado'],
            'observacion'  => $r['observacion'],
            'created_at'   => $r['created_at'],
            'created_by'   => $r['created_by'],
        ];
    }
    ok(['data' => $rows]);
}

/* ------------ POST ------------ */
if ($method === 'POST') {
    $input  = read_json();
    $accion = $input['accion'] ?? '';

    // Crear rendición (cabecera)
    if ($accion === 'crear') {
        $id_ff  = fint($input['id_ff'] ?? 0);
        $obs    = trim($input['observacion'] ?? '');
        $user   = $_SESSION['nombre_usuario'];

        if ($id_ff <= 0) bad('Fondo fijo inválido');

        // Verificar que la caja exista y esté activa
        $ff = pg_query_params(
            $conn,
            "SELECT id_ff, estado FROM public.fondo_fijo WHERE id_ff = $1",
            [$id_ff]
        );
        if (!$ff || !pg_num_rows($ff)) bad('Fondo fijo no encontrado', 404);
        $ffd = pg_fetch_assoc($ff);
        if ($ffd['estado'] !== 'Activo') bad('La caja de fondo fijo no está activa');

        $ins = pg_query_params(
            $conn,
            "INSERT INTO public.ff_rendiciones
               (id_ff, estado, observacion, created_at, created_by, updated_at)
             VALUES
               ($1, 'En revisión', NULLIF($2,''), now(), $3, now())
             RETURNING id_rendicion",
            [$id_ff, $obs, $user]
        );
        if (!$ins) bad('No se pudo crear la rendición', 500);
        $row = pg_fetch_assoc($ins);

        ok(['id_rendicion' => (int)$row['id_rendicion']]);
    }

    // Agregar items en lote
    if ($accion === 'agregar_items') {
        $id_rendicion = fint($input['id_rendicion'] ?? 0);
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        if ($id_rendicion <= 0) bad('Rendición inválida');
        if (empty($items)) bad('Sin ítems para agregar');

        // Check cabecera
        $h = pg_query_params(
            $conn,
            "SELECT id_rendicion, estado FROM public.ff_rendiciones WHERE id_rendicion = $1",
            [$id_rendicion]
        );
        if (!$h || !pg_num_rows($h)) bad('Rendición no existe', 404);
        $rd = pg_fetch_assoc($h);
        if ($rd['estado'] !== 'En revisión') bad('Sólo se pueden agregar ítems en rendiciones en revisión');

        pg_query($conn, 'BEGIN');
        $ok_count = 0;

        foreach ($items as $it) {
            // Valores mínimos
            $fecha  = $it['fecha'] ?? null; // YYYY-MM-DD
            $idp    = ($it['id_proveedor'] ?? null) !== null ? (int)$it['id_proveedor'] : null; // puede ser null
            $ruc    = trim($it['ruc'] ?? '');
            $tipo   = trim($it['documento_tipo'] ?? 'FACT');
            $nro    = trim($it['numero_documento'] ?? '');
            $tim    = trim($it['timbrado_numero'] ?? '');

            $g10    = ffloat($it['gravada_10'] ?? 0);
            $i10    = ffloat($it['iva_10'] ?? 0);
            $g5     = ffloat($it['gravada_5'] ?? 0);
            $i5     = ffloat($it['iva_5'] ?? 0);
            $ex     = ffloat($it['exentas'] ?? 0);
            $total  = ffloat($it['total'] ?? 0);
            $obs    = trim($it['observacion'] ?? '');

            if (!$fecha || !$nro || $total <= 0) {
                pg_query($conn, 'ROLLBACK');
                bad('Ítem inválido: requiere fecha, número y total > 0');
            }

            $ins = pg_query_params(
                $conn,
                "INSERT INTO public.ff_rendiciones_items
                   (id_rendicion, fecha, id_proveedor, ruc, documento_tipo, numero_documento, timbrado_numero,
                    gravada_10, iva_10, gravada_5, iva_5, exentas, total,
                    estado_item, imputa_libro, observacion, created_at)
                 VALUES
                   ($1, $2::date, $3, NULLIF($4,''), $5, $6, NULLIF($7,''),
                    $8, $9, $10, $11, $12, $13,
                    'Pendiente', false, NULLIF($14,''), now())",
                [
                    $id_rendicion, $fecha, $idp, $ruc, $tipo, $nro, $tim,
                    $g10, $i10, $g5, $i5, $ex, $total,
                    $obs
                ]
            );
            if (!$ins) { pg_query($conn, 'ROLLBACK'); bad('No se pudo insertar un ítem', 500); }
            $ok_count++;
        }

        // Recalcular estado
        $nuevo_estado = recompute_rendicion_estado($conn, $id_rendicion);
        $upH = pg_query_params(
            $conn,
            "UPDATE public.ff_rendiciones SET estado = $1, updated_at = now() WHERE id_rendicion = $2",
            [$nuevo_estado, $id_rendicion]
        );
        if (!$upH) { pg_query($conn, 'ROLLBACK'); bad('No se pudo actualizar estado de la rendición', 500); }

        pg_query($conn, 'COMMIT');
        ok(['insertados' => $ok_count, 'estado' => $nuevo_estado]);
    }

    bad('Acción no soportada', 405);
}

/* ------------ PATCH (aprobar_lote) ------------ */
if ($method === 'PATCH') {
    $id_rendicion = isset($_GET['id_rendicion']) ? (int)$_GET['id_rendicion'] : 0;
    if ($id_rendicion <= 0) bad('Rendición inválida');

    $input  = read_json();
    $accion = $input['accion'] ?? '';

    if ($accion !== 'aprobar_lote') {
        bad('Acción no soportada', 405);
    }

    $items = is_array($input['items'] ?? null) ? $input['items'] : [];
    $all_or_nothing   = !empty($input['all_or_nothing']);
    $permitir_negativo= parse_bool($input['permitir_negativo'] ?? false, false);
    $hoy = date('Y-m-d');

    if (empty($items)) bad('No hay selección para aprobar/rechazar');

    // Validar cabecera (debe estar en revisión) y traer id_ff
    $h = pg_query_params(
        $conn,
        "SELECT id_rendicion, id_ff, estado
           FROM public.ff_rendiciones
          WHERE id_rendicion = $1
          FOR UPDATE",
        [$id_rendicion]
    );
    if (!$h || !pg_num_rows($h)) bad('Rendición no existe', 404);
    $rd = pg_fetch_assoc($h);
    if ($rd['estado'] !== 'En revisión') bad('Sólo se puede aprobar/rechazar una rendición en revisión');
    $id_ff = (int)$rd['id_ff'];

    pg_query($conn, 'BEGIN');

    $result = [
        'aprobados' => 0,
        'rechazados'=> 0,
        'libro_insertados' => 0,
        'libro_duplicados' => 0,
        'errores'   => []
    ];

    foreach ($items as $x) {
        $id_item   = (int)($x['id_item'] ?? 0);
        $aprobar   = (bool)($x['aprobar'] ?? false);
        $rawImpta  = $x['imputa_libro'] ?? null;              // "", null, "true", true, etc.
        $imptaTxt  = to_bool_text_or_null($rawImpta);         // 'true'/'false'/null
        $motivo    = trim($x['motivo_rechazo'] ?? '');

        if ($id_item <= 0) {
            if ($all_or_nothing) { pg_query($conn, 'ROLLBACK'); bad('Ítem sin id_item', 422); }
            $result['errores'][] = ['id_item'=>null, 'error'=>'Ítem sin id_item'];
            continue;
        }

        // Traer ítem
        $it = pg_query_params(
            $conn,
            "SELECT * FROM public.ff_rendiciones_items
             WHERE id_item = $1 AND id_rendicion = $2
             FOR UPDATE",
            [$id_item, $id_rendicion]
        );
        if (!$it || !pg_num_rows($it)) {
            if ($all_or_nothing) { pg_query($conn, 'ROLLBACK'); bad("Ítem $id_item no existe", 404); }
            $result['errores'][] = ['id_item'=>$id_item, 'error'=>'Ítem no existe'];
            continue;
        }
        $row = pg_fetch_assoc($it);
        if ($row['estado_item'] !== 'Pendiente') {
            if ($all_or_nothing) { pg_query($conn, 'ROLLBACK'); bad("Ítem $id_item no está pendiente", 409); }
            $result['errores'][] = ['id_item'=>$id_item, 'error'=>'Ítem no está pendiente'];
            continue;
        }

        if ($aprobar === false) {
            // Rechazo
            $upd = pg_query_params(
                $conn,
                "UPDATE public.ff_rendiciones_items
                 SET estado_item='Rechazado', imputa_libro=false, observacion = NULLIF($1,''), updated_at = now()
                 WHERE id_item = $2",
                [$motivo, $id_item]
            );
            if (!$upd) {
                if ($all_or_nothing) { pg_query($conn, 'ROLLBACK'); bad("No se pudo rechazar ítem $id_item", 500); }
                $result['errores'][] = ['id_item'=>$id_item, 'error'=>'Fallo al rechazar'];
                continue;
            }
            $result['rechazados']++;
            continue;
        }

        // Aprobación
        $sqlUpd = "
            UPDATE public.ff_rendiciones_items
               SET estado_item='Aprobado',
                   imputa_libro = CASE
                      WHEN $1::text IS NULL OR $1::text = '' THEN imputa_libro
                      WHEN lower($1::text) IN ('t','true','1','on','sí','si','y','yes') THEN true
                      WHEN lower($1::text) IN ('f','false','0','off','no','n') THEN false
                      ELSE imputa_libro
                   END,
                   updated_at = now()
             WHERE id_item = $2";
        $upd = pg_query_params($conn, $sqlUpd, [$imptaTxt, $id_item]);
        if (!$upd) {
            if ($all_or_nothing) { pg_query($conn, 'ROLLBACK'); bad("No se pudo aprobar ítem $id_item", 500); }
            $result['errores'][] = ['id_item'=>$id_item, 'error'=>'Fallo al aprobar'];
            continue;
        }
        $result['aprobados']++;

        // Determinar valor final de imputa_libro (consulto luego del update)
        $chk = pg_query_params($conn,
            "SELECT imputa_libro FROM public.ff_rendiciones_items WHERE id_item=$1",
            [$id_item]
        );
        $rowChk = $chk ? pg_fetch_assoc($chk) : null;
        $imputaFinal = $rowChk ? ($rowChk['imputa_libro'] === 't' || $rowChk['imputa_libro'] === true) : false;

        // Si va a libro, insertar si no existe
        if ($imputaFinal) {
            $pay = [
                'fecha'            => $row['fecha'],
                'id_proveedor'     => $row['id_proveedor'] !== null ? (int)$row['id_proveedor'] : 0,
                'ruc'              => $row['ruc'],
                'numero_documento' => $row['numero_documento'],
                'documento_tipo'   => $row['documento_tipo'],
                'timbrado_numero'  => $row['timbrado_numero'],
                'gravada_10'       => ffloat($row['gravada_10']),
                'iva_10'           => ffloat($row['iva_10']),
                'gravada_5'        => ffloat($row['gravada_5']),
                'iva_5'            => ffloat($row['iva_5']),
                'exentas'          => ffloat($row['exentas']),
                'total'            => ffloat($row['total']),
            ];
            $ins = libro_insert_if_new($conn, $pay);
            if (!$ins['ok']) {
                if ($all_or_nothing) { pg_query($conn, 'ROLLBACK'); bad("Fallo al insertar en libro para ítem $id_item", 500); }
                $result['errores'][] = ['id_item'=>$id_item, 'error'=>'Fallo al insertar en libro'];
                continue;
            }
            if ($ins['dup']) $result['libro_duplicados']++;
            else             $result['libro_insertados']++;
        }
    }

    // Recalcular estado de la rendición
    $nuevo_estado = recompute_rendicion_estado($conn, $id_rendicion);
    $upH = pg_query_params(
        $conn,
        "UPDATE public.ff_rendiciones SET estado = $1, updated_at = now() WHERE id_rendicion = $2",
        [$nuevo_estado, $id_rendicion]
    );
    if (!$upH) { pg_query($conn, 'ROLLBACK'); bad('No se pudo actualizar estado de la rendición', 500); }

    /* =============================
       DESCUENTO IDPOTENTE DE FF
       =============================
       total_aprob_actual = SUM(total de items Aprobado)
       mov_previos        = SUM(monto en fondo_fijo_mov donde tipo='RENDICION', signo=-1, ref_tabla='rendicion', ref_id=id_rendicion)
       delta = total_aprob_actual - mov_previos
       si delta > 0 => descontar FF por delta e insertar movimiento
    */
    // 1) total aprobado de la rendición
    $stTot = pg_query_params(
        $conn,
        "SELECT COALESCE(SUM(total),0) AS total_aprob
           FROM public.ff_rendiciones_items
          WHERE id_rendicion=$1 AND estado_item='Aprobado'",
        [$id_rendicion]
    );
    if (!$stTot) { pg_query($conn, 'ROLLBACK'); bad('No se pudo calcular total aprobado de la rendición', 500); }
    $total_aprobado = (float)pg_fetch_result($stTot, 0, 0);

    // 2) lo ya descontado en FF por esta rendición
    $stMov = pg_query_params(
        $conn,
        "SELECT COALESCE(SUM(monto),0) AS ya_descontado
           FROM public.fondo_fijo_mov
          WHERE ref_tabla='rendicion' AND ref_id=$1 AND tipo='RENDICION' AND signo=-1",
        [$id_rendicion]
    );
    $ya_descontado = $stMov ? (float)pg_fetch_result($stMov, 0, 0) : 0.0;

    $delta = $total_aprobado - $ya_descontado;

    if ($delta > 0.000001) {
        // 3) Lock y verificar saldo
        $ffLock = pg_query_params(
            $conn,
            "SELECT id_ff, COALESCE(saldo_actual,0) AS saldo_actual
               FROM public.fondo_fijo
              WHERE id_ff=$1
              FOR UPDATE",
            [$id_ff]
        );
        if (!$ffLock || !pg_num_rows($ffLock)) { pg_query($conn, 'ROLLBACK'); bad('Fondo Fijo no encontrado', 404); }
        $FF = pg_fetch_assoc($ffLock);
        $saldo_actual = (float)$FF['saldo_actual'];

        if (!$permitir_negativo && $delta > $saldo_actual + 0.000001) {
            pg_query($conn, 'ROLLBACK');
            bad('Saldo insuficiente en el Fondo Fijo para aprobar. Saldo: '.number_format($saldo_actual,2,'.',',').' / Requiere: '.number_format($delta,2,'.',','));
        }

        // 4) Insertar movimiento de egreso
        $desc = 'Rendición #'.$id_rendicion.' (aprobación lote)';
        $insFFMov = pg_query_params(
            $conn,
            "INSERT INTO public.fondo_fijo_mov
               (id_ff, fecha, tipo, signo, monto, descripcion, ref_tabla, ref_id, created_at, created_by)
             VALUES
               ($1, $2::date, 'RENDICION', -1, $3, $4, 'rendicion', $5, now(), $6)",
            [$id_ff, $hoy, $delta, $desc, $id_rendicion, $_SESSION['nombre_usuario']]
        );
        if (!$insFFMov) { pg_query($conn, 'ROLLBACK'); bad('No se pudo registrar el movimiento de Fondo Fijo', 500); }

        // 5) Actualizar saldo del Fondo Fijo
        if ($permitir_negativo) {
            $updFF = pg_query_params(
                $conn,
                "UPDATE public.fondo_fijo
                    SET saldo_actual = COALESCE(saldo_actual,0) - $1,
                        updated_at = now()
                  WHERE id_ff = $2",
                [$delta, $id_ff]
            );
        } else {
            $updFF = pg_query_params(
                $conn,
                "UPDATE public.fondo_fijo
                    SET saldo_actual = GREATEST(COALESCE(saldo_actual,0) - $1, 0),
                        updated_at = now()
                  WHERE id_ff = $2",
                [$delta, $id_ff]
            );
        }
        if (!$updFF) { pg_query($conn, 'ROLLBACK'); bad('No se pudo actualizar el saldo del Fondo Fijo', 500); }
    }

    pg_query($conn, 'COMMIT');

    ok([
        'id_rendicion'     => $id_rendicion,
        'id_ff'            => $id_ff,
        'estado'           => $nuevo_estado,
        'descuento_delta'  => round(max($delta,0), 2),
        'resumen'          => $result
    ]);
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
