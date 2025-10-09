<?php
/**
 * API Proveedores
 * GET    /proveedores_api.php                -> listar (filtros)
 * GET    /proveedores_api.php?id=123         -> detalle
 * POST   /proveedores_api.php                -> crear
 * PATCH  /proveedores_api.php?id=123         -> actualizar
 * DELETE /proveedores_api.php?id=123         -> baja lógica (deleted_at, estado=Inactivo)
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
function norm_email(?string $e): ?string {
    $e = trim((string)$e);
    return $e === '' ? null : strtolower($e);
}
function required_str($v, $label, $max = 255): string {
    $s = trim((string)$v);
    if ($s === '') bad("$label es requerido");
    if (mb_strlen($s) > $max) bad("$label excede $max caracteres");
    return $s;
}
function optional_str($v, $label, $max = 255): ?string {
    if ($v === null) return null;
    $s = trim((string)$v);
    if ($s === '') return null;
    if (mb_strlen($s) > $max) bad("$label excede $max caracteres");
    return $s;
}

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $sql = "
            SELECT id_proveedor, nombre, direccion, telefono, email, ruc,
                   id_pais, id_ciudad, tipo, estado, deleted_at
            FROM public.proveedores
            WHERE id_proveedor = $1
        ";
        $st = pg_query_params($conn, $sql, [$id]);
        if (!$st) bad('Error al obtener proveedor', 500);
        if (!pg_num_rows($st)) bad('Proveedor no encontrado', 404);
        $row = pg_fetch_assoc($st);
        ok(['proveedor' => [
            'id_proveedor' => (int)$row['id_proveedor'],
            'nombre'       => $row['nombre'],
            'direccion'    => $row['direccion'],
            'telefono'     => $row['telefono'],
            'email'        => $row['email'],
            'ruc'          => $row['ruc'],
            'id_pais'      => $row['id_pais'] !== null ? (int)$row['id_pais'] : null,
            'id_ciudad'    => $row['id_ciudad'] !== null ? (int)$row['id_ciudad'] : null,
            'tipo'         => $row['tipo'],
            'estado'       => $row['estado'],
            'deleted_at'   => $row['deleted_at'],
        ]]);
    }

    // listado con filtros
    $params = [];
    $filters = [];
    $i = 1;

    // ocultar borrados por defecto (si querés ver borrados, pasá include_deleted=1)
    $includeDeleted = isset($_GET['include_deleted']) && $_GET['include_deleted'] === '1';
    if (!$includeDeleted) {
        $filters[] = 'deleted_at IS NULL';
    }

    if (!empty($_GET['q'])) {
        $filters[] = "(lower(nombre) LIKE $" . $i . " OR lower(email) LIKE $" . $i . " OR ruc ILIKE $" . $i . ")";
        $params[] = '%' . strtolower(trim($_GET['q'])) . '%';
        $i++;
    }
    if (!empty($_GET['tipo'])) {
        $filters[] = "tipo = $" . $i;
        $params[] = $_GET['tipo'];
        $i++;
    }
    if (!empty($_GET['estado'])) {
        $filters[] = "estado = $" . $i;
        $params[] = $_GET['estado'];
        $i++;
    }

    $where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
    $sql = "
        SELECT id_proveedor, nombre, ruc, email, telefono, tipo, estado, deleted_at
        FROM public.proveedores
        $where
        ORDER BY nombre
        LIMIT 300
    ";
    $st = pg_query_params($conn, $sql, $params);
    if (!$st) bad('Error al listar proveedores', 500);

    $data = [];
    while ($r = pg_fetch_assoc($st)) {
        $data[] = [
            'id_proveedor' => (int)$r['id_proveedor'],
            'nombre'       => $r['nombre'],
            'ruc'          => $r['ruc'],
            'email'        => $r['email'],
            'telefono'     => $r['telefono'],
            'tipo'         => $r['tipo'],
            'estado'       => $r['estado'],
            'deleted_at'   => $r['deleted_at'],
        ];
    }
    ok(['data' => $data]);
}

if ($method === 'POST') {
    $in = read_json();

    $nombre    = required_str($in['nombre'] ?? null, 'Nombre', 255);
    $ruc       = required_str($in['ruc'] ?? null, 'RUC', 15);
    $direccion = required_str($in['direccion'] ?? null, 'Dirección', 255);
    $telefono  = required_str($in['telefono'] ?? null, 'Teléfono', 25);
    $email     = norm_email($in['email'] ?? null);
    if ($email === null) bad('Email es requerido');
    if (mb_strlen($email) > 100) bad('Email excede 100 caracteres');
    $tipo      = $in['tipo'] ?? 'PROVEEDOR';
    $estado    = $in['estado'] ?? 'Activo';
    $id_pais   = isset($in['id_pais']) ? (int)$in['id_pais'] : null;
    $id_ciudad = isset($in['id_ciudad']) ? (int)$in['id_ciudad'] : null;

    // validaciones de dominio
    if (!in_array($tipo, ['PROVEEDOR','FONDO_FIJO','SERVICIO','TRANSPORTISTA','OTRO'], true)) bad('Tipo inválido');
    if (!in_array($estado, ['Activo','Inactivo'], true)) bad('Estado inválido');

    // unicidad ruc y email (ignorando borrados para email por el unique existente)
    $stRuc = pg_query_params($conn,
        "SELECT 1 FROM public.proveedores WHERE ruc = $1 AND deleted_at IS NULL LIMIT 1",
        [$ruc]
    );
    if ($stRuc && pg_num_rows($stRuc)) bad('RUC ya existe');

    $stEmail = pg_query_params($conn,
        "SELECT 1 FROM public.proveedores WHERE lower(email)=lower($1) LIMIT 1",
        [$email]
    );
    if ($stEmail && pg_num_rows($stEmail)) bad('Email ya existe');

    $st = pg_query_params($conn,
        "INSERT INTO public.proveedores
         (nombre, direccion, telefono, email, ruc, id_pais, id_ciudad, tipo, estado)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)
         RETURNING id_proveedor",
        [$nombre, $direccion, $telefono, $email, $ruc, $id_pais, $id_ciudad, $tipo, $estado]
    );
    if (!$st) bad('No se pudo crear proveedor', 500);
    $id = (int)pg_fetch_result($st, 0, 0);
    ok(['id_proveedor' => $id]);
}

if ($method === 'PATCH') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) bad('ID inválido');

    $in = read_json();

    // Traer actual
    $st0 = pg_query_params($conn, "SELECT * FROM public.proveedores WHERE id_proveedor=$1", [$id]);
    if (!$st0 || !pg_num_rows($st0)) bad('Proveedor no encontrado', 404);
    $cur = pg_fetch_assoc($st0);

    // Campos
    $nombre    = isset($in['nombre'])    ? required_str($in['nombre'], 'Nombre', 255)      : $cur['nombre'];
    $ruc       = isset($in['ruc'])       ? required_str($in['ruc'], 'RUC', 15)             : $cur['ruc'];
    $direccion = isset($in['direccion']) ? required_str($in['direccion'], 'Dirección', 255): $cur['direccion'];
    $telefono  = isset($in['telefono'])  ? required_str($in['telefono'], 'Teléfono', 25)   : $cur['telefono'];
    $email     = array_key_exists('email', $in) ? norm_email($in['email']) : $cur['email'];
    if ($email === null) bad('Email es requerido');
    if (mb_strlen($email) > 100) bad('Email excede 100 caracteres');

    $tipo      = $in['tipo']   ?? $cur['tipo'];
    $estado    = $in['estado'] ?? $cur['estado'];
    $id_pais   = array_key_exists('id_pais', $in)   ? ($in['id_pais'] !== null ? (int)$in['id_pais'] : null) : ($cur['id_pais'] !== null ? (int)$cur['id_pais'] : null);
    $id_ciudad = array_key_exists('id_ciudad', $in) ? ($in['id_ciudad'] !== null ? (int)$in['id_ciudad'] : null) : ($cur['id_ciudad'] !== null ? (int)$cur['id_ciudad'] : null);

    if (!in_array($tipo, ['PROVEEDOR','FONDO_FIJO','SERVICIO','TRANSPORTISTA','OTRO'], true)) bad('Tipo inválido');
    if (!in_array($estado, ['Activo','Inactivo'], true)) bad('Estado inválido');

    // unicidad ruc/email (excluyendo el mismo id)
    $stRuc = pg_query_params($conn,
        "SELECT 1 FROM public.proveedores
         WHERE ruc = $1 AND id_proveedor <> $2 AND deleted_at IS NULL LIMIT 1",
        [$ruc, $id]
    );
    if ($stRuc && pg_num_rows($stRuc)) bad('RUC ya existe');

    $stEmail = pg_query_params($conn,
        "SELECT 1 FROM public.proveedores
         WHERE lower(email)=lower($1) AND id_proveedor <> $2 LIMIT 1",
        [$email, $id]
    );
    if ($stEmail && pg_num_rows($stEmail)) bad('Email ya existe');

    $st = pg_query_params($conn,
        "UPDATE public.proveedores
         SET nombre=$1, direccion=$2, telefono=$3, email=$4, ruc=$5,
             id_pais=$6, id_ciudad=$7, tipo=$8, estado=$9
         WHERE id_proveedor=$10",
        [$nombre,$direccion,$telefono,$email,$ruc,$id_pais,$id_ciudad,$tipo,$estado,$id]
    );
    if (!$st) bad('No se pudo actualizar proveedor', 500);

    ok(['id_proveedor' => $id, 'updated' => true]);
}

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) bad('ID inválido');

    $st = pg_query_params($conn,
        "UPDATE public.proveedores
         SET deleted_at = now(), estado = 'Inactivo'
         WHERE id_proveedor = $1",
        [$id]
    );
    if (!$st) bad('No se pudo eliminar proveedor', 500);
    ok(['id_proveedor' => $id, 'deleted' => true]);
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
