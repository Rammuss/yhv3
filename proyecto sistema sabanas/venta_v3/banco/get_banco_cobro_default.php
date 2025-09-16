<?php
// get_banco_cobro_default.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try{
  // 1) Leer ID desde config
  $r = pg_query_params($conn, "
    SELECT (valor)::int AS id_cuenta_bancaria
    FROM public.config_sistema
    WHERE clave='banco_cobro_default'
    LIMIT 1
  ", []);
  $id = ($r && pg_num_rows($r)>0) ? (int)pg_fetch_result($r,0,0) : null;

  // 2) Si no hay ID, devolver success con cuenta null
  if (!$id || $id <= 0) {
    echo json_encode(['success'=>true,'id_cuenta_bancaria'=>null,'cuenta'=>null]);
    exit;
  }

  // 3) Traer la cuenta
  $r2 = pg_query_params($conn, "
    SELECT id_cuenta_bancaria,
           banco,
           numero_cuenta,
           COALESCE(tipo,'')   AS tipo,
           COALESCE(moneda,'') AS moneda,
           COALESCE(estado,'') AS estado
    FROM public.cuenta_bancaria
    WHERE id_cuenta_bancaria = $1
    LIMIT 1
  ", [$id]);

  if (!$r2 || pg_num_rows($r2)===0) {
    echo json_encode(['success'=>true,'id_cuenta_bancaria'=>$id,'cuenta'=>null]);
    exit;
  }

  $cuenta = pg_fetch_assoc($r2);
  echo json_encode(['success'=>true,'id_cuenta_bancaria'=>$id,'cuenta'=>$cuenta]);

}catch(Throwable $e){
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
