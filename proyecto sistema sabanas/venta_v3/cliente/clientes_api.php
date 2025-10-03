<?php
// clientes_api.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '../../../conexion/configv2.php';

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo conectar a la base de datos']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

function jsonError(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

switch ($method) {
    case 'GET':
        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            $sql = 'SELECT id_cliente, nombre, apellido, direccion, telefono, ruc_ci
                    FROM public.clientes
                    WHERE nombre ILIKE $1
                       OR apellido ILIKE $1
                       OR ruc_ci ILIKE $1
                    ORDER BY apellido, nombre
                    LIMIT 200';
            $param = ['%' . $q . '%'];
            $result = pg_query_params($conn, $sql, $param);
        } else {
            $sql = 'SELECT id_cliente, nombre, apellido, direccion, telefono, ruc_ci
                    FROM public.clientes
                    ORDER BY id_cliente DESC
                    LIMIT 200';
            $result = pg_query($conn, $sql);
        }

        if (!$result) {
            jsonError(500, 'Error al consultar clientes');
        }

        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        echo json_encode($rows);
        break;

    case 'POST':
        $nombre   = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono  = trim($_POST['telefono'] ?? '');
        $ruc_ci    = trim($_POST['ruc_ci'] ?? '');

        if ($nombre === '' || $apellido === '') {
            jsonError(400, 'Nombre y apellido son obligatorios');
        }

        $sql = 'INSERT INTO public.clientes (nombre, apellido, direccion, telefono, ruc_ci)
                VALUES ($1, $2, NULLIF($3, \'\'), NULLIF($4, \'\'), NULLIF($5, \'\'))
                RETURNING id_cliente';
        $params = [$nombre, $apellido, $direccion, $telefono, $ruc_ci];
        $result = pg_query_params($conn, $sql, $params);

        if (!$result) {
            jsonError(500, 'No se pudo crear la clienta');
        }

        $id = pg_fetch_result($result, 0, 0);
        echo json_encode(['success' => true, 'id_cliente' => (int)$id]);
        break;

    case 'PUT':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            jsonError(400, 'ID inválido');
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            jsonError(400, 'JSON inválido');
        }

        $nombre   = trim($payload['nombre'] ?? '');
        $apellido = trim($payload['apellido'] ?? '');
        $direccion = trim($payload['direccion'] ?? '');
        $telefono  = trim($payload['telefono'] ?? '');
        $ruc_ci    = trim($payload['ruc_ci'] ?? '');

        if ($nombre === '' || $apellido === '') {
            jsonError(400, 'Nombre y apellido son obligatorios');
        }

        $sql = 'UPDATE public.clientes
                   SET nombre = $1,
                       apellido = $2,
                       direccion = NULLIF($3, \'\'),
                       telefono = NULLIF($4, \'\'),
                       ruc_ci = NULLIF($5, \'\')
                 WHERE id_cliente = $6';
        $params = [$nombre, $apellido, $direccion, $telefono, $ruc_ci, $id];
        $result = pg_query_params($conn, $sql, $params);

        if (!$result || pg_affected_rows($result) === 0) {
            jsonError(404, 'Clienta no encontrada o sin cambios');
        }

        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            jsonError(400, 'ID inválido');
        }

        $sql = 'DELETE FROM public.clientes WHERE id_cliente = $1';
        $result = pg_query_params($conn, $sql, [$id]);

        if (!$result || pg_affected_rows($result) === 0) {
            jsonError(404, 'Clienta no encontrada');
        }

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        header('Allow: GET, POST, PUT, DELETE');
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

pg_close($conn);
