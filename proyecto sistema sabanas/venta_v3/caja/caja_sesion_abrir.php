<?php
// abrir_caja.php — abre sesión de caja SIN saldo inicial (se carga luego por movimientos)
// POST/JSON: { id_caja }
session_start();
if (empty($_SESSION['id_usuario'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'No autenticado']);
  exit;
}

require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

function j($ok, $data=[], $code=200){
  http_response_code($code);
  echo json_encode(['ok'=>$ok]+$data);
  exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$id_caja    = isset($in['id_caja']) ? (int)$in['id_caja'] : 0;
$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);

if ($id_caja <= 0) j(false, ['error'=>'id_caja inválido'], 400);

pg_query($conn, 'BEGIN');

try{
  // (0) Validar que la caja exista (defensivo)
  $rcaja = pg_query_params($conn,
    "SELECT id_caja FROM public.caja WHERE id_caja=$1 LIMIT 1",
    [$id_caja]
  );
  if (!$rcaja || pg_num_rows($rcaja) === 0) {
    throw new Exception("Caja inexistente.");
  }

  // (a) ¿Usuario ya tiene sesión abierta?
  $r = pg_query_params($conn,
    "SELECT id_caja_sesion
       FROM public.caja_sesion
      WHERE id_usuario=$1
        AND estado='Abierta'
      ORDER BY fecha_apertura DESC
      LIMIT 1
      FOR UPDATE",
    [$id_usuario]
  );
  if ($r && pg_num_rows($r) > 0) {
    throw new Exception("El usuario ya posee una sesión de caja Abierta.");
  }

  // (b) ¿Caja libre?
  $r = pg_query_params($conn,
    "SELECT id_caja_sesion
       FROM public.caja_sesion
      WHERE id_caja=$1
        AND estado='Abierta'
      ORDER BY fecha_apertura DESC
      LIMIT 1
      FOR UPDATE",
    [$id_caja]
  );
  if ($r && pg_num_rows($r) > 0) {
    throw new Exception("La caja seleccionada ya tiene una sesión Abierta.");
  }

  // (c) Crear sesión con saldo_inicial = 0
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $r = pg_query_params($conn,
    "INSERT INTO public.caja_sesion
       (id_caja, id_usuario, saldo_inicial, ip_dispositivo)
     VALUES ($1, $2, 0, $3)
     RETURNING id_caja_sesion, id_caja, id_usuario, saldo_inicial, fecha_apertura, estado",
    [$id_caja, $id_usuario, $ip]
  );
  if (!$r) throw new Exception(pg_last_error($conn));

  $sesion = pg_fetch_assoc($r);
  $id_sesion_nueva = (int)$sesion['id_caja_sesion'];

  pg_query($conn, 'COMMIT');

  // (d) Cachear en sesión del usuario para próximos requests
  $_SESSION['id_caja_sesion'] = $id_sesion_nueva;
  $_SESSION['id_caja']        = (int)$sesion['id_caja'];     // ⭐ necesario para reservar_numero()
  // (opcional)
  $_SESSION['caja_estado']    = $sesion['estado'];           // 'Abierta' | 'Cerrada' (referencial)
  if (function_exists('session_write_close')) session_write_close();

  j(true, ['sesion'=>$sesion], 201);

} catch (Exception $e){
  pg_query($conn, 'ROLLBACK');
  j(false, ['error'=>$e->getMessage()], 409);
}
