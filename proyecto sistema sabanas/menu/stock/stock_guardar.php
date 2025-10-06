<?php
// guardar_ajuste_inventario.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../conexion/configv2.php';

$response = ['success' => false, 'mensaje' => ''];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido.');
  }

  $modo        = trim($_POST['modo'] ?? 'ajuste');   // 'ajuste' o 'conversion'
  $id_producto = (int)($_POST['id_producto'] ?? 0);  // producto padre o producto a ajustar
  $cantidad    = (float)($_POST['cantidad'] ?? 0);   // envases/unidades
  $observacion = trim($_POST['observacion'] ?? '');

  if ($id_producto <= 0 || $cantidad <= 0) {
    throw new Exception('Producto y cantidad son obligatorios y deben ser válidos.');
  }

  pg_query($conn, 'BEGIN');

  if ($modo === 'conversion') {
    $idProductoHijo = isset($_POST['id_producto_hijo']) ? (int)$_POST['id_producto_hijo'] : null;

    // 1) Traer información del padre
    $sqlPadre = "
      SELECT
        id_producto,
        es_fraccion,
        tipo_iva,
        categoria,
        tipo_item,
        precio_unitario,
        precio_compra
      FROM public.producto
      WHERE id_producto = $1
      LIMIT 1
    ";
    $rPadre = pg_query_params($conn, $sqlPadre, [$id_producto]);
    if (!$rPadre || pg_num_rows($rPadre) === 0) {
      throw new Exception('El producto padre no existe.');
    }
    $padre = pg_fetch_assoc($rPadre);
    $esFraccionPadre = in_array($padre['es_fraccion'], [true, 't', '1', 1], true);
    if ($esFraccionPadre) {
      throw new Exception('El producto seleccionado ya es fraccionado. Elegí el envase entero.');
    }

    $hijo = null;
    $nuevoHijoCreado = false;

    if ($idProductoHijo) {
      // 2a) Traer hijo indicado
      $sqlHijo = "
        SELECT id_producto, factor_equivalencia, unidad_base
        FROM public.producto
        WHERE id_producto = $1
          AND es_fraccion = true
          AND id_producto_padre = $2
        LIMIT 1
      ";
      $rHijo = pg_query_params($conn, $sqlHijo, [$idProductoHijo, $id_producto]);
      if (!$rHijo || pg_num_rows($rHijo) === 0) {
        throw new Exception('El producto fraccionado seleccionado no existe o no pertenece a este envase.');
      }
      $hijo = pg_fetch_assoc($rHijo);
    } else {
      // 2b) Intentar reutilizar hijo único ya configurado
      $sqlHijos = "
        SELECT id_producto, factor_equivalencia, unidad_base
        FROM public.producto
        WHERE es_fraccion = true
          AND id_producto_padre = $1
        LIMIT 2
      ";
      $rHijos = pg_query_params($conn, $sqlHijos, [$id_producto]);
      if ($rHijos && pg_num_rows($rHijos) > 1) {
        throw new Exception('Hay más de un producto fraccionado. Indicá cuál usar (id_producto_hijo).');
      }
      if ($rHijos && pg_num_rows($rHijos) === 1) {
        $hijo = pg_fetch_assoc($rHijos);
      }

      // 2c) Crear nuevo hijo si no existe
      if (!$hijo) {
        $nombreHijo  = trim($_POST['nombre_hijo'] ?? '');
        $unidadHijo  = trim($_POST['unidad_base_hijo'] ?? '');
        $factorHijo  = (float)($_POST['factor_equivalencia_hijo'] ?? 0);
        $precioVenta = $_POST['precio_unitario_hijo'] ?? null;
        $precioComp  = $_POST['precio_compra_hijo'] ?? null;

        if ($nombreHijo === '' || $unidadHijo === '' || $factorHijo <= 0) {
          throw new Exception('Completá nombre, unidad y factor del fraccionado.');
        }

        $precioVenta = ($precioVenta === null || $precioVenta === '') ? (float)$padre['precio_unitario'] : (float)$precioVenta;
        $precioComp  = ($precioComp === null || $precioComp === '') ? (float)$padre['precio_compra']   : (float)$precioComp;

        if ($precioVenta < 0 || $precioComp < 0) {
          throw new Exception('Los precios del fraccionado deben ser mayores o iguales a cero.');
        }

        $sqlNuevo = "
          INSERT INTO public.producto (
            nombre,
            precio_unitario,
            precio_compra,
            estado,
            tipo_iva,
            categoria,
            tipo_item,
            es_fraccion,
            id_producto_padre,
            factor_equivalencia,
            unidad_base
          ) VALUES (
            $1, $2, $3, 'Activo',
            $4, $5, $6,
            true, $7, $8, $9
          )
          RETURNING id_producto
        ";
        $paramsNuevo = [
          $nombreHijo,
          $precioVenta,
          $precioComp,
          $padre['tipo_iva'],
          $padre['categoria'],
          $padre['tipo_item'] ?: 'P',
          $id_producto,
          $factorHijo,
          $unidadHijo
        ];
        $rNuevo = pg_query_params($conn, $sqlNuevo, $paramsNuevo);
        if (!$rNuevo || pg_num_rows($rNuevo) === 0) {
          throw new Exception('No se pudo crear el producto fraccionado.');
        }

        $idProductoHijo = (int)pg_fetch_result($rNuevo, 0, 0);
        $hijo = [
          'id_producto'         => $idProductoHijo,
          'factor_equivalencia' => $factorHijo,
          'unidad_base'         => $unidadHijo
        ];
        $nuevoHijoCreado = true;
      }
    }

    $factor = isset($hijo['factor_equivalencia']) ? (float)$hijo['factor_equivalencia'] : 0;
    if ($factor <= 0) {
      throw new Exception('El producto fraccionado no tiene factor_equivalencia válido.');
    }

    $cantidadEntrada = $cantidad * $factor;
    $unidadHijoBase  = $hijo['unidad_base'] ?? '';
    $obs             = $observacion !== '' ? $observacion : "Conversión a fraccionado (padre {$id_producto})";

    // 3) Registrar salida del padre
    $sqlMovPadre = "
      INSERT INTO public.movimiento_stock (id_producto, tipo_movimiento, cantidad, fecha, observacion)
      VALUES ($1, 'salida', $2, NOW(), $3)
    ";
    $okPadre = pg_query_params($conn, $sqlMovPadre, [$id_producto, $cantidad, $obs]);
    if (!$okPadre) {
      throw new Exception('No se pudo registrar la salida del envase.');
    }

    // 4) Registrar entrada del hijo
    $sqlMovHijo = "
      INSERT INTO public.movimiento_stock (id_producto, tipo_movimiento, cantidad, fecha, observacion)
      VALUES ($1, 'entrada', $2, NOW(), $3)
    ";
    $okHijo = pg_query_params($conn, $sqlMovHijo, [$hijo['id_producto'], $cantidadEntrada, $obs]);
    if (!$okHijo) {
      throw new Exception('No se pudo registrar la entrada del producto fraccionado.');
    }

    pg_query($conn, 'COMMIT');

    $mensaje = "Conversión registrada: -{$cantidad} envase(s)";
    $mensaje .= " +{$cantidadEntrada}" . ($unidadHijoBase ? " {$unidadHijoBase}" : '.');
    if ($nuevoHijoCreado) {
      $mensaje .= " Fraccionado creado (#{$hijo['id_producto']}).";
    }

    $response['success'] = true;
    $response['mensaje'] = $mensaje;

  } else {
    // --- AJUSTE POS/NEG TRADICIONAL ---
    $tipo_movimiento = trim($_POST['tipo_movimiento'] ?? '');
    $validos = ['AJUSTE_POS', 'AJUSTE_NEG'];
    if (!in_array($tipo_movimiento, $validos, true)) {
      throw new Exception('Tipo de ajuste inválido.');
    }
    $tipoReal = ($tipo_movimiento === 'AJUSTE_POS') ? 'entrada' : 'salida';

    $qProd = "SELECT id_producto FROM public.producto WHERE id_producto = $1 LIMIT 1";
    $rProd = pg_query_params($conn, $qProd, [$id_producto]);
    if (!$rProd || pg_num_rows($rProd) === 0) {
      throw new Exception('El producto no existe.');
    }

    $qIns = "
      INSERT INTO public.movimiento_stock (id_producto, tipo_movimiento, cantidad, fecha, observacion)
      VALUES ($1, $2, $3, NOW(), $4)
      RETURNING id
    ";
    $rIns = pg_query_params($conn, $qIns, [
      $id_producto,
      $tipoReal,
      $cantidad,
      $observacion !== '' ? $observacion : 'Ajuste manual'
    ]);

    if (!$rIns || pg_num_rows($rIns) === 0) {
      throw new Exception('No se pudo registrar el ajuste.');
    }
    $mov_id = pg_fetch_result($rIns, 0, 0);

    pg_query($conn, 'COMMIT');
    $response['success'] = true;
    $response['mensaje'] = "Ajuste registrado como $tipoReal (#$mov_id).";
  }

  echo json_encode($response);
  exit;

} catch (Exception $e) {
  pg_query($conn, 'ROLLBACK');
  $response['mensaje'] = $e->getMessage();
  echo json_encode($response);
  exit;
}
