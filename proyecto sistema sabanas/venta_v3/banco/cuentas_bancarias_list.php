<?php
// cuentas_bancarias_list.php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try {
  // (Opcional) Validar sesión
  if (empty($_SESSION['nombre_usuario'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'No autorizado']);
    exit;
  }

  $sql = "
    SELECT id_cuenta_bancaria, banco, numero_cuenta, tipo, moneda
    FROM public.cuenta_bancaria
    WHERE estado = 'Activa'
    ORDER BY banco ASC, numero_cuenta ASC
  ";

  $res = pg_query($conn, $sql);
  if (!$res) throw new Exception('Error consultando cuentas bancarias');

  $cuentas = [];
  while ($r = pg_fetch_assoc($res)) {
    $cuentas[] = [
      'id_cuenta_bancaria' => (int)$r['id_cuenta_bancaria'],
      'banco' => $r['banco'],
      'numero_cuenta' => $r['numero_cuenta'],
      'tipo' => $r['tipo'],
      'moneda' => $r['moneda'],
      'label' => $r['banco'].' - '.$r['numero_cuenta'].' ('.$r['tipo'].' '.$r['moneda'].')'
    ];
  }

  echo json_encode(['success'=>true,'cuentas'=>$cuentas]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
