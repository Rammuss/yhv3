<?php
/* CRUD de chequeras
 * GET    /chequera_api.php              -> listado (filtros id_cuenta, activa, q)
 * GET    /chequera_api.php?id=1         -> detalle
 * POST   /chequera_api.php              -> crear chequera
 * PUT    /chequera_api.php?id=1         -> editar campos
 * PATCH  /chequera_api.php?id=1         -> actualizar estado/proximo_numero
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
function parse_bool($val, bool $default): bool {
    if ($val === null) return $default;
    if (is_bool($val)) return $val;
    $filtered = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $filtered === null ? $default : $filtered;
}

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $sql = "
            SELECT ch.id_chequera,
                   ch.id_cuenta_bancaria,
                   cb.banco,
                   cb.numero_cuenta,
                   cb.moneda,
                   ch.descripcion,
                   ch.prefijo,
                   ch.sufijo,
                   ch.pad_length,
                   ch.numero_inicio,
                   ch.numero_fin,
                   ch.proximo_numero,
                   ch.activa,
                   ch.created_at,
                   ch.updated_at
            FROM public.chequera ch
            JOIN public.cuenta_bancaria cb ON cb.id_cuenta_bancaria = ch.id_cuenta_bancaria
            WHERE ch.id_chequera = $1
        ";
        $stmt = pg_query_params($conn, $sql, [$id]);
        if (!$stmt) bad('Error al obtener chequera', 500);
        $row = pg_fetch_assoc($stmt);
        if (!$row) bad('Chequera no encontrada', 404);
        ok(['chequera' => [
            'id_chequera'      => (int)$row['id_chequera'],
            'id_cuenta_bancaria'=> (int)$row['id_cuenta_bancaria'],
            'banco'            => $row['banco'],
            'numero_cuenta'    => $row['numero_cuenta'],
            'moneda'           => $row['moneda'],
            'descripcion'      => $row['descripcion'],
            'prefijo'          => $row['prefijo'],
            'sufijo'           => $row['sufijo'],
            'pad_length'       => (int)$row['pad_length'],
            'numero_inicio'    => (int)$row['numero_inicio'],
            'numero_fin'       => $row['numero_fin'] !== null ? (int)$row['numero_fin'] : null,
            'proximo_numero'   => (int)$row['proximo_numero'],
            'activa'           => $row['activa'] === 't',
            'created_at'       => $row['created_at'],
            'updated_at'       => $row['updated_at'],
        ]]);
    }

    $where = [];
    $params = [];
    $idx = 1;

    if (!empty($_GET['id_cuenta_bancaria'])) {
        $where[] = "ch.id_cuenta_bancaria = $".$idx;
        $params[] = (int)$_GET['id_cuenta_bancaria'];
        $idx++;
    }
    if (isset($_GET['activa']) && $_GET['activa'] !== '') {
        $where[] = "ch.activa = $".$idx;
        $params[] = parse_bool($_GET['activa'], true);
        $idx++;
    }
    if (!empty($_GET['q'])) {
        $where[] = "(cb.banco ILIKE $".$idx." OR cb.numero_cuenta ILIKE $".$idx." OR COALESCE(ch.descripcion,'') ILIKE $".$idx.")";
        $params[] = '%'.trim($_GET['q']).'%';
        $idx++;
    }

    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
    $sqlList = "
        SELECT ch.id_chequera,
               ch.id_cuenta_bancaria,
               cb.banco,
               cb.numero_cuenta,
               cb.moneda,
               ch.descripcion,
               ch.prefijo,
               ch.sufijo,
               ch.pad_length,
               ch.numero_inicio,
               ch.numero_fin,
               ch.proximo_numero,
               ch.activa,
               COALESCE(cu.activa_count,0) AS otras_activas
        FROM public.chequera ch
        JOIN public.cuenta_bancaria cb ON cb.id_cuenta_bancaria = ch.id_cuenta_bancaria
        LEFT JOIN (
            SELECT id_cuenta_bancaria, COUNT(*) FILTER (WHERE activa) AS activa_count
            FROM public.chequera
            GROUP BY id_cuenta_bancaria
        ) cu ON cu.id_cuenta_bancaria = ch.id_cuenta_bancaria
        $whereSql
        ORDER BY cb.banco, cb.numero_cuenta, ch.id_chequera DESC
        LIMIT 200
    ";

    $stmtList = pg_query_params($conn, $sqlList, $params);
    if (!$stmtList) bad('Error al listar chequeras', 500);

    $data = [];
    while ($row = pg_fetch_assoc($stmtList)) {
        $data[] = [
            'id_chequera'       => (int)$row['id_chequera'],
            'id_cuenta_bancaria'=> (int)$row['id_cuenta_bancaria'],
            'banco'             => $row['banco'],
            'numero_cuenta'     => $row['numero_cuenta'],
            'moneda'            => $row['moneda'],
            'descripcion'       => $row['descripcion'],
            'prefijo'           => $row['prefijo'],
            'sufijo'            => $row['sufijo'],
            'pad_length'        => (int)$row['pad_length'],
            'numero_inicio'     => (int)$row['numero_inicio'],
            'numero_fin'        => $row['numero_fin'] !== null ? (int)$row['numero_fin'] : null,
            'proximo_numero'    => (int)$row['proximo_numero'],
            'activa'            => $row['activa'] === 't',
            'otras_activas'     => (int)$row['otras_activas'],
        ];
    }
    ok(['data' => $data]);
}

if ($method === 'POST') {
    $input = read_json() ?: $_POST;

    $idCuenta = (int)($input['id_cuenta_bancaria'] ?? 0);
    $descripcion = trim($input['descripcion'] ?? '');
    $prefijo = trim($input['prefijo'] ?? '');
    $sufijo = trim($input['sufijo'] ?? '');
    $padLength = (int)($input['pad_length'] ?? 0);
    $numeroInicio = (int)($input['numero_inicio'] ?? 0);
    $numeroFin = isset($input['numero_fin']) && $input['numero_fin'] !== '' ? (int)$input['numero_fin'] : null;
    $proximoNumero = isset($input['proximo_numero']) && $input['proximo_numero'] !== '' ? (int)$input['proximo_numero'] : $numeroInicio;
    $activa = parse_bool($input['activa'] ?? true, true);

    if ($idCuenta <= 0) bad('Cuenta bancaria inválida');
    if ($numeroInicio <= 0) bad('Número de inicio inválido');
    if ($padLength <= 0) $padLength = max(strlen((string)$numeroInicio), 6);
    if ($numeroFin !== null && $numeroFin < $numeroInicio) bad('El número final debe ser mayor o igual al inicio');
    if ($proximoNumero < $numeroInicio) bad('El próximo número no puede ser menor al inicio');
    if ($numeroFin !== null && $proximoNumero > $numeroFin) bad('El próximo número no puede superar el número final');

    pg_query($conn, 'BEGIN');

    if ($activa) {
        $sqlDeactivate = "
            UPDATE public.chequera
            SET activa = false, updated_at = now()
            WHERE id_cuenta_bancaria = $1 AND activa = true
        ";
        if (!pg_query_params($conn, $sqlDeactivate, [$idCuenta])) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo inactivar la chequera activa actual', 500);
        }
    }

    $sql = "
        INSERT INTO public.chequera
            (id_cuenta_bancaria, descripcion, prefijo, sufijo,
             pad_length, numero_inicio, numero_fin, proximo_numero, activa,
             created_at, updated_at)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9, now(), now())
        RETURNING id_chequera
    ";
    $stmt = pg_query_params($conn, $sql, [
        $idCuenta,
        $descripcion !== '' ? $descripcion : null,
        $prefijo !== '' ? $prefijo : null,
        $sufijo !== '' ? $sufijo : null,
        $padLength,
        $numeroInicio,
        $numeroFin,
        $proximoNumero,
        $activa ? 't' : 'f'
    ]);
    if (!$stmt) {
        pg_query($conn, 'ROLLBACK');
        bad('Error al crear la chequera', 500);
    }
    $idNew = (int)pg_fetch_result($stmt, 0, 0);
    pg_query($conn, 'COMMIT');

    ok(['id_chequera' => $idNew]);
}

if ($method === 'PUT') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) bad('Chequera inválida');

    $input = read_json();
    if (!$input) parse_str(file_get_contents('php://input') ?: '', $input);

    $fields = [];
    $params = [];
    $idx = 1;

    foreach (['descripcion','prefijo','sufijo'] as $key) {
        if (array_key_exists($key, $input)) {
            $fields[] = "$key = $" . $idx;
            $params[] = trim((string)$input[$key]) ?: null;
            $idx++;
        }
    }
    if (array_key_exists('pad_length', $input)) {
        $pad = (int)$input['pad_length'];
        if ($pad <= 0) bad('pad_length debe ser mayor a cero');
        $fields[] = "pad_length = $".$idx;
        $params[] = $pad;
        $idx++;
    }
    if (array_key_exists('numero_inicio', $input)) {
        $numIni = (int)$input['numero_inicio'];
        if ($numIni <= 0) bad('numero_inicio inválido');
        $fields[] = "numero_inicio = $".$idx;
        $params[] = $numIni;
        $idx++;
    }
    if (array_key_exists('numero_fin', $input)) {
        $numFin = $input['numero_fin'] !== null && $input['numero_fin'] !== '' ? (int)$input['numero_fin'] : null;
        $fields[] = "numero_fin = $".$idx;
        $params[] = $numFin;
        $idx++;
    }
    if (array_key_exists('proximo_numero', $input)) {
        $fields[] = "proximo_numero = $".$idx;
        $params[] = (int)$input['proximo_numero'];
        $idx++;
    }

    if (!$fields) bad('Nada para actualizar');

    $params[] = $id;
    $sql = "UPDATE public.chequera SET ".implode(', ', $fields).", updated_at = now() WHERE id_chequera = $".$idx;

    $ok = pg_query_params($conn, $sql, $params);
    if (!$ok) bad('Error al actualizar chequera', 500);

    ok(['id_chequera' => $id]);
}

if ($method === 'PATCH') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) bad('Chequera inválida');

    $input = read_json();
    if (!$input) bad('Datos inválidos');

    pg_query($conn, 'BEGIN');

    if (array_key_exists('activa', $input)) {
        $activa = parse_bool($input['activa'], true);
        $sqlInfo = "SELECT id_cuenta_bancaria FROM public.chequera WHERE id_chequera=$1 FOR UPDATE";
        $stmtInfo = pg_query_params($conn, $sqlInfo, [$id]);
        if (!$stmtInfo || !pg_num_rows($stmtInfo)) {
            pg_query($conn, 'ROLLBACK');
            bad('Chequera no encontrada', 404);
        }
        $rowInfo = pg_fetch_assoc($stmtInfo);
        $idCuenta = (int)$rowInfo['id_cuenta_bancaria'];

        if ($activa) {
            $updDeactivate = "
                UPDATE public.chequera
                SET activa = false, updated_at = now()
                WHERE id_cuenta_bancaria = $1 AND activa = true AND id_chequera <> $2
            ";
            if (!pg_query_params($conn, $updDeactivate, [$idCuenta, $id])) {
                pg_query($conn, 'ROLLBACK');
                bad('No se pudo inactivar otras chequeras', 500);
            }
        }

        $upd = pg_query_params(
            $conn,
            "UPDATE public.chequera SET activa=$1, updated_at=now() WHERE id_chequera=$2",
            [$activa ? 't' : 'f', $id]
        );
        if (!$upd) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo actualizar el estado', 500);
        }
    }

    if (array_key_exists('proximo_numero', $input)) {
        $nuevo = (int)$input['proximo_numero'];
        $sqlRanges = "
            SELECT numero_inicio, numero_fin
            FROM public.chequera
            WHERE id_chequera = $1
            FOR UPDATE
        ";
        $stmtRanges = pg_query_params($conn, $sqlRanges, [$id]);
        if (!$stmtRanges || !pg_num_rows($stmtRanges)) {
            pg_query($conn, 'ROLLBACK');
            bad('Chequera inexistente', 404);
        }
        $rowRanges = pg_fetch_assoc($stmtRanges);
        $ini = (int)$rowRanges['numero_inicio'];
        $fin = $rowRanges['numero_fin'] !== null ? (int)$rowRanges['numero_fin'] : null;
        if ($nuevo < $ini) {
            pg_query($conn, 'ROLLBACK');
            bad('El próximo número no puede ser menor al inicio');
        }
        if ($fin !== null && $nuevo > $fin) {
            pg_query($conn, 'ROLLBACK');
            bad('El próximo número no puede superar el final');
        }
        $upd = pg_query_params(
            $conn,
            "UPDATE public.chequera SET proximo_numero = $1, updated_at = now() WHERE id_chequera = $2",
            [$nuevo, $id]
        );
        if (!$upd) {
            pg_query($conn, 'ROLLBACK');
            bad('No se pudo actualizar el próximo número', 500);
        }
    }

    pg_query($conn, 'COMMIT');
    ok(['id_chequera' => $id]);
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
