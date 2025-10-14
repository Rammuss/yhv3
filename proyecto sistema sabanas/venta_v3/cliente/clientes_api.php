<?php
// CRUD + toggle de clientes con verificación estricta y soporte correcto para booleanos de Postgres.
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['nombre_usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
} elseif (!empty($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ensure_boolean($value, string $fieldName) {
    if (is_bool($value)) {
        return $value;
    }
    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($bool === null) {
        bad("El campo {$fieldName} debe ser booleano");
    }
    return $bool;
}

function pg_bool($value): bool {
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null) {
        return false;
    }
    $value = strtolower(trim((string)$value));
    return in_array($value, ['t', 'true', '1', 'on', 'yes'], true);
}

function pg_bool_param($value): string {
    return $value ? 'true' : 'false';
}

function bad(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function ok(array $payload = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => true] + $payload);
    exit;
}

if ($method === 'GET') {
    if ($id > 0) {
        $sql = <<<SQL
            SELECT id_cliente, nombre, apellido, direccion, telefono, ruc_ci, activo
            FROM public.clientes
            WHERE id_cliente = $1
        SQL;
        $stmt = pg_query_params($conn, $sql, [$id]);
        if (!$stmt) {
            bad('Error al obtener cliente');
        }
        $row = pg_fetch_assoc($stmt);
        if (!$row) {
            bad('Cliente no encontrado', 404);
        }

        $row['id_cliente'] = (int)$row['id_cliente'];
        $row['activo'] = pg_bool($row['activo']);

        ok(['cliente' => $row]);
    }

    $q = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = max(1, min(100, (int)($_GET['page_size'] ?? 50)));
    $offset = ($page - 1) * $pageSize;

    $where = [];
    $params = [];
    $idx = 1;

    if ($q !== '') {
        $where[] = "(nombre ILIKE $".$idx." OR apellido ILIKE $".$idx." OR COALESCE(ruc_ci,'') ILIKE $".$idx." OR COALESCE(telefono,'') ILIKE $".$idx.")";
        $params[] = "%{$q}%";
        $idx++;
    }

    if (array_key_exists('activo', $_GET)) {
        $rawActivo = trim((string)$_GET['activo']);
        if ($rawActivo !== '') {
            $activo = filter_var($rawActivo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activo === null) {
                bad('El filtro activo debe ser booleano (true/false/1/0)');
            }
            $where[] = "activo = $".$idx;
            $params[] = pg_bool_param($activo);
            $idx++;
        }
    }

    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

    $sqlCount = "SELECT COUNT(*) FROM public.clientes {$whereSql}";
    $stmtCount = pg_query_params($conn, $sqlCount, $params);
    if (!$stmtCount) {
        bad('Error al contar clientes');
    }
    $total = (int)pg_fetch_result($stmtCount, 0, 0);

    $sqlList = "SELECT id_cliente, nombre, apellido, direccion, telefono, ruc_ci, activo
                FROM public.clientes
                {$whereSql}
                ORDER BY id_cliente DESC
                LIMIT $" . $idx . " OFFSET $" . ($idx + 1);
    $paramsList = $params;
    $paramsList[] = $pageSize;
    $paramsList[] = $offset;

    $stmtList = pg_query_params($conn, $sqlList, $paramsList);
    if (!$stmtList) {
        bad('Error al listar clientes');
    }

    $data = [];
    while ($row = pg_fetch_assoc($stmtList)) {
        $entry = [
            'id_cliente' => (int)$row['id_cliente'],
            'nombre'     => $row['nombre'],
            'apellido'   => $row['apellido'],
            'direccion'  => $row['direccion'],
            'telefono'   => $row['telefono'],
            'ruc_ci'     => $row['ruc_ci'],
            'activo'     => pg_bool($row['activo']),
        ];
        $entry['resumen'] = '#'.$entry['id_cliente'].' | '.$entry['nombre'].' '.$entry['apellido']
            .($entry['ruc_ci'] ? ' | RUC/CI: '.$entry['ruc_ci'] : '');
        $data[] = $entry;
    }

    ok([
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
        'data' => $data,
    ]);
}

if ($method === 'POST' && ($_POST['_action'] ?? '') !== 'toggle') {
    $input = read_json_body();
    if (!$input) {
        $input = $_POST;
    }

    $nombre = trim($input['nombre'] ?? '');
    $apellido = trim($input['apellido'] ?? '');
    $direccion = trim($input['direccion'] ?? '');
    $telefono = trim($input['telefono'] ?? '');
    $rucCi = trim($input['ruc_ci'] ?? '');

    if ($nombre === '' || $apellido === '') {
        bad('nombre y apellido son obligatorios');
    }

    $activo = true;
    if (array_key_exists('activo', $input)) {
        $activo = ensure_boolean($input['activo'], 'activo');
    }

    $sql = <<<SQL
        INSERT INTO public.clientes (nombre, apellido, direccion, telefono, ruc_ci, activo)
        VALUES ($1, $2, $3, $4, $5, $6)
        RETURNING id_cliente
    SQL;
    $stmt = pg_query_params(
        $conn,
        $sql,
        [$nombre, $apellido, $direccion, $telefono, $rucCi, pg_bool_param($activo)]
    );
    if (!$stmt) {
        bad('Error al crear cliente');
    }
    $newId = (int)pg_fetch_result($stmt, 0, 0);
    ok(['id_cliente' => $newId], 201);
}

if ($method === 'PUT') {
    if ($id <= 0) {
        bad('id inválido');
    }

    $input = read_json_body();
    if (!$input) {
        parse_str(file_get_contents('php://input') ?: '', $input);
    }

    $fields = ['nombre','apellido','direccion','telefono','ruc_ci','activo'];
    $set = [];
    $params = [];
    $idx = 1;

    foreach ($fields as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        if ($field === 'activo') {
            $boolValue = ensure_boolean($input[$field], 'activo');
            $params[] = pg_bool_param($boolValue);
        } else {
            $params[] = trim((string)$input[$field]);
        }
        $set[] = "{$field} = $".$idx;
        $idx++;
    }

    if (!$set) {
        bad('Nada para actualizar');
    }

    $params[] = $id;
    $sql = 'UPDATE public.clientes SET '.implode(', ', $set).' WHERE id_cliente = $'.$idx;
    $stmt = pg_query_params($conn, $sql, $params);
    if (!$stmt) {
        bad('Error al actualizar cliente');
    }

    if (pg_affected_rows($stmt) === 0) {
        bad('Cliente no encontrado', 404);
    }

    ok(['id_cliente' => $id]);
}

if ($method === 'PATCH') {
    if ($id <= 0) {
        bad('id inválido');
    }

    $input = read_json_body();
    if (!array_key_exists('activo', $input)) {
        bad('Falta campo activo (boolean)');
    }
    $activo = ensure_boolean($input['activo'], 'activo');

    $stmt = pg_query_params(
        $conn,
        'UPDATE public.clientes SET activo = $1 WHERE id_cliente = $2',
        [pg_bool_param($activo), $id]
    );
    if (!$stmt) {
        bad('Error al cambiar estado');
    }
    if (pg_affected_rows($stmt) === 0) {
        bad('Cliente no encontrado', 404);
    }

    ok(['id_cliente' => $id, 'activo' => $activo]);
}

if ($method === 'POST' && ($_POST['_action'] ?? '') === 'toggle') {
    $idToggle = (int)($_POST['id'] ?? 0);
    if ($idToggle <= 0) {
        bad('id inválido');
    }

    $tieneValor = false;
    $nuevoEstado = null;

    if (array_key_exists('activo', $_POST)) {
        $rawActivo = $_POST['activo'];
        if ($rawActivo !== '' && $rawActivo !== null) {
            $nuevoEstado = ensure_boolean($rawActivo, 'activo');
            $tieneValor = true;
        }
    }

    if (!$tieneValor) {
        $stmt = pg_query_params(
            $conn,
            'SELECT activo FROM public.clientes WHERE id_cliente = $1',
            [$idToggle]
        );
        if (!$stmt) {
            bad('Error al leer estado actual');
        }
        if (!pg_num_rows($stmt)) {
            bad('Cliente no encontrado', 404);
        }
        $actual = pg_fetch_result($stmt, 0, 0);
        $nuevoEstado = !pg_bool($actual);
    }

    $stmtUpdate = pg_query_params(
        $conn,
        'UPDATE public.clientes SET activo = $1 WHERE id_cliente = $2',
        [pg_bool_param($nuevoEstado), $idToggle]
    );
    if (!$stmtUpdate) {
        bad('Error al cambiar estado');
    }
    if (pg_affected_rows($stmtUpdate) === 0) {
        bad('Cliente no encontrado', 404);
    }

    ok(['id_cliente' => $idToggle, 'activo' => $nuevoEstado]);
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
