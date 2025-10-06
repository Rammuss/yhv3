<?php
// ui_ajuste_inventario.php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

$busca = $_GET['q'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

$prodSql = "
  SELECT
    id_producto,
    nombre,
    COALESCE(es_fraccion, false)            AS es_fraccion,
    id_producto_padre,
    COALESCE(factor_equivalencia, 0)::float AS factor_equivalencia,
    COALESCE(unidad_base, '')               AS unidad_base
  FROM public.producto
  ORDER BY nombre
  LIMIT 500
";
$prodRes = pg_query($conn, $prodSql);
$productos = [];
$padres    = [];
$hijosMap  = [];

if ($prodRes) {
  while ($row = pg_fetch_assoc($prodRes)) {
    $row['es_fraccion'] = ($row['es_fraccion'] === true || $row['es_fraccion'] === 't' || $row['es_fraccion'] === '1' || $row['es_fraccion'] == 1);
    $row['id_producto'] = (int)$row['id_producto'];
    $row['id_producto_padre'] = $row['id_producto_padre'] !== null ? (int)$row['id_producto_padre'] : null;
    $row['factor_equivalencia'] = (float)$row['factor_equivalencia'];

    $productos[] = $row;
    if (!$row['es_fraccion']) {
      $padres[] = $row;
    } elseif ($row['id_producto_padre']) {
      $padreId = $row['id_producto_padre'];
      if (!isset($hijosMap[$padreId])) {
        $hijosMap[$padreId] = [];
      }
      $hijosMap[$padreId][] = [
        'id_producto'         => $row['id_producto'],
        'nombre'              => $row['nombre'],
        'factor_equivalencia' => $row['factor_equivalencia'],
        'unidad_base'         => $row['unidad_base']
      ];
    }
  }
}

$params = [];
$w      = [];
if ($busca !== '') {
  $w[] = "(LOWER(p.nombre) LIKE LOWER($".(count($params)+1)."))";
  $params[] = "%$busca%";
}
if ($desde !== '') {
  $w[] = "m.fecha >= $".(count($params)+1);
  $params[] = $desde . " 00:00:00";
}
if ($hasta !== '') {
  $w[] = "m.fecha <= $".(count($params)+1);
  $params[] = $hasta . " 23:59:59";
}
$where = $w ? "WHERE " . implode(" AND ", $w) : "";

$listSql = "
  SELECT m.id, m.id_producto, p.nombre AS producto, m.tipo_movimiento, m.cantidad, m.fecha
  FROM public.movimiento_stock m
  JOIN public.producto p ON p.id_producto = m.id_producto
  $where
  ORDER BY m.fecha DESC
  LIMIT 100
";
$listRes = pg_query_params($conn, $listSql, $params);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Movimientos de Inventario</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../../menu/styles.css" />
  <style>
    body{font-family:Arial, sans-serif; margin:20px; background:#f7f7f7;}
    .card{background:#fff; padding:16px; border-radius:10px; box-shadow:0 1px 6px rgba(0,0,0,.08); margin-bottom:16px;}
    h2{margin:0 0 12px;}
    label{display:block; font-size:12px; margin-bottom:6px; color:#333;}
    input,select,button{width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; box-sizing:border-box;}
    .row{display:flex; gap:12px; flex-wrap:wrap;}
    .row>div{flex:1 1 0}
    table{width:100%; border-collapse:collapse; background:#fff}
    th,td{padding:10px; border-bottom:1px solid #eee; font-size:14px; text-align:left;}
    th{background:#fafafa}
    .actions{display:flex; gap:8px;}
    .ok{color:#137333; font-weight:bold;}
    .err{color:#b00020; font-weight:bold;}
    .mode-switch{display:flex; gap:16px; margin-bottom:14px;}
    .mode-switch label{display:flex; align-items:center; gap:6px; font-size:13px; margin-bottom:0; cursor:pointer;}
    .label-row{display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px;}
    .stock-badge{display:inline-flex; align-items:center; gap:6px; padding:2px 8px; border-radius:999px; font-size:12px; line-height:1.6; border:1px solid #e5e7eb; background:#f9fafb; color:#111827; white-space:nowrap;}
    .stock-dot{width:8px; height:8px; border-radius:999px; background:#6b7280;}
    .stock--ok .stock-dot{background:#16a34a;}
    .stock--warn .stock-dot{background:#ca8a04;}
    .stock--zero .stock-dot{background:#ef4444;}
    .muted{color:#6b7280; font-size:12px;}
    #nuevo-hijo{margin-top:14px; padding:14px; border:1px dashed #d1d5db; border-radius:10px; background:#f9fafb;}
    #nuevo-hijo h3{margin:0 0 10px; font-size:15px; color:#1f2937;}
  </style>
</head>
<body>
  <div id="navbar-container"></div>

  <div class="card">
    <h2>Nuevo movimiento de stock</h2>

    <form id="form-ajuste" autocomplete="off">
      <div class="mode-switch">
        <label><input type="radio" name="modo" value="ajuste" checked> Ajuste de stock</label>
        <label><input type="radio" name="modo" value="conversion"> Abrir envase (convertir)</label>
      </div>

      <div id="ajuste-fields">
        <div class="row">
          <div>
            <div class="label-row">
              <label for="sel-producto-ajuste">Producto</label>
              <span class="stock-badge" id="badge-ajuste">
                <span class="stock-dot"></span>
                <span>Stock: <b id="stock-ajuste">—</b></span>
              </span>
            </div>
            <select id="sel-producto-ajuste" required>
              <option value="">-- Seleccione --</option>
              <?php foreach ($productos as $p): ?>
                <option value="<?= htmlspecialchars($p['id_producto']) ?>">
                  <?= htmlspecialchars($p['nombre']) . ($p['es_fraccion'] ? ' (fraccionado)' : '') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label for="tipo-mov">Tipo de ajuste</label>
            <select id="tipo-mov" required>
              <option value="">-- Seleccione --</option>
              <option value="AJUSTE_POS">Incrementar stock (AJUSTE_POS)</option>
              <option value="AJUSTE_NEG">Disminuir stock (AJUSTE_NEG)</option>
            </select>
          </div>

          <div>
            <label for="cant-ajuste">Cantidad</label>
            <input id="cant-ajuste" type="number" min="0.0001" step="0.0001" required />
          </div>
        </div>
      </div>

      <div id="conversion-fields" style="display:none;">
        <div class="row">
          <div>
            <div class="label-row">
              <label for="sel-producto-padre">Envase (producto padre)</label>
              <span class="stock-badge" id="badge-padre">
                <span class="stock-dot"></span>
                <span>Stock: <b id="stock-padre">—</b></span>
              </span>
            </div>
            <select id="sel-producto-padre">
              <option value="">-- Seleccione --</option>
              <?php foreach ($padres as $p): ?>
                <option value="<?= htmlspecialchars($p['id_producto']) ?>">
                  <?= htmlspecialchars($p['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <div class="label-row">
              <label for="sel-producto-hijo">Producto fraccionado</label>
              <span class="stock-badge" id="badge-hijo">
                <span class="stock-dot"></span>
                <span>Stock: <b id="stock-hijo">—</b></span>
              </span>
            </div>
            <select id="sel-producto-hijo" disabled>
              <option value="">Seleccione un envase</option>
            </select>
          </div>

          <div>
            <label for="cant-conv">Envases a abrir</label>
            <input id="cant-conv" type="number" min="0.0001" step="0.0001" />
            <div class="muted" id="conv-hint"></div>
          </div>
        </div>

        <div id="nuevo-hijo" data-active="0" style="display:none;">
          <h3>Crear producto fraccionado</h3>
          <div class="row">
            <div>
              <label for="nombre-hijo">Nombre del fraccionado</label>
              <input id="nombre-hijo" type="text" maxlength="255" data-name="nombre_hijo" placeholder="Ej. Shampoo (ml)" />
            </div>
            <div>
              <label for="unidad-hijo">Unidad base</label>
              <input id="unidad-hijo" type="text" maxlength="20" data-name="unidad_base_hijo" placeholder="ml, gr, unidad..." />
            </div>
            <div>
              <label for="factor-hijo">Factor equivalencia</label>
              <input id="factor-hijo" type="number" min="0.0001" step="0.0001" data-name="factor_equivalencia_hijo" placeholder="Ej. 5000" />
            </div>
          </div>
          <div class="row">
            <div>
              <label for="precio-venta-hijo">Precio de venta (opcional)</label>
              <input id="precio-venta-hijo" type="number" min="0" step="0.01" data-name="precio_unitario_hijo" placeholder="Hereda del envase si se deja vacío" />
            </div>
            <div>
              <label for="precio-compra-hijo">Precio de compra (opcional)</label>
              <input id="precio-compra-hijo" type="number" min="0" step="0.01" data-name="precio_compra_hijo" placeholder="Hereda del envase si se deja vacío" />
            </div>
          </div>
          <p class="muted">
            No hay fraccionado configurado para este envase. Completar los datos para crearlo automáticamente.
          </p>
        </div>
      </div>

      <div class="row" style="margin-top:12px;">
        <div>
          <label for="obs">Observación (opcional)</label>
          <input id="obs" type="text" name="observacion" maxlength="255" placeholder="Motivo del ajuste, lote, etc." />
        </div>
        <div style="align-self:end">
          <button type="submit">Guardar</button>
        </div>
      </div>
      <div id="msg" style="margin-top:10px;"></div>
    </form>
  </div>

  <div class="card">
    <h2>Movimientos recientes</h2>
    <form method="get" class="row" style="margin-bottom:12px;">
      <div><label>Buscar producto</label><input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Nombre contiene..." /></div>
      <div><label>Desde</label><input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" /></div>
      <div><label>Hasta</label><input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" /></div>
      <div style="align-self:end"><button type="submit">Filtrar</button></div>
    </form>
    <table>
      <thead>
        <tr>
          <th>ID</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Fecha</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($m = pg_fetch_assoc($listRes)): ?>
          <tr>
            <td><?= htmlspecialchars($m['id']) ?></td>
            <td><?= htmlspecialchars($m['producto']) ?></td>
            <td><?= htmlspecialchars($m['tipo_movimiento']) ?></td>
            <td><?= htmlspecialchars($m['cantidad']) ?></td>
            <td><?= htmlspecialchars($m['fecha']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <script>
    const hijosPorPadre = <?= json_encode($hijosMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>

  <script>
    const form          = document.getElementById('form-ajuste');
    const msg           = document.getElementById('msg');
    const modeRadios    = document.querySelectorAll('input[name="modo"]');
    const ajusteFields  = document.getElementById('ajuste-fields');
    const convFields    = document.getElementById('conversion-fields');

    const selAjuste     = document.getElementById('sel-producto-ajuste');
    const tipoMov       = document.getElementById('tipo-mov');
    const cantAjuste    = document.getElementById('cant-ajuste');

    const selPadre      = document.getElementById('sel-producto-padre');
    const selHijo       = document.getElementById('sel-producto-hijo');
    const cantConv      = document.getElementById('cant-conv');
    const convHint      = document.getElementById('conv-hint');

    const badgeAjuste   = document.getElementById('badge-ajuste');
    const stockAjuste   = document.getElementById('stock-ajuste');
    const badgePadre    = document.getElementById('badge-padre');
    const stockPadre    = document.getElementById('stock-padre');
    const badgeHijo     = document.getElementById('badge-hijo');
    const stockHijo     = document.getElementById('stock-hijo');

    const nuevoHijoBox       = document.getElementById('nuevo-hijo');
    const nombreHijoInput    = document.getElementById('nombre-hijo');
    const unidadHijoInput    = document.getElementById('unidad-hijo');
    const factorHijoInput    = document.getElementById('factor-hijo');
    const precioVentaInput   = document.getElementById('precio-venta-hijo');
    const precioCompraInput  = document.getElementById('precio-compra-hijo');

    const numberPY = new Intl.NumberFormat('es-PY', { maximumFractionDigits: 4 });

    modeRadios.forEach(radio => radio.addEventListener('change', toggleMode));
    selPadre.addEventListener('change', () => {
      actualizarHijos();
      cargarStockSelect(selPadre, stockPadre, badgePadre);
    });
    selHijo.addEventListener('change', () => {
      actualizarConversionInfo();
      cargarStockSelect(selHijo, stockHijo, badgeHijo);
    });
    cantConv.addEventListener('input', actualizarConversionInfo);
    selAjuste.addEventListener('change', () => cargarStockSelect(selAjuste, stockAjuste, badgeAjuste));

    if (factorHijoInput) {
      factorHijoInput.addEventListener('input', actualizarConversionInfo);
    }
    if (unidadHijoInput) {
      unidadHijoInput.addEventListener('input', actualizarConversionInfo);
    }
    if (nombreHijoInput) {
      nombreHijoInput.addEventListener('input', () => {
        if (!nombreHijoInput.value && selPadre.selectedIndex > 0) {
          nombreHijoInput.value = generarNombreFraccionado();
        }
      });
    }

    function toggleMode() {
      const modo = document.querySelector('input[name="modo"]:checked').value;

      if (modo === 'conversion') {
        ajusteFields.style.display = 'none';
        convFields.style.display   = '';

        selAjuste.removeAttribute('name');
        selAjuste.removeAttribute('required');
        tipoMov.removeAttribute('name');
        tipoMov.removeAttribute('required');
        cantAjuste.removeAttribute('name');
        cantAjuste.removeAttribute('required');

        selPadre.setAttribute('name', 'id_producto');
        selPadre.setAttribute('required', 'required');
        selHijo.setAttribute('name', 'id_producto_hijo');
        selHijo.setAttribute('required', 'required');
        cantConv.setAttribute('name', 'cantidad');
        cantConv.setAttribute('required', 'required');

        actualizarHijos();
        cargarStockSelect(selPadre, stockPadre, badgePadre);
        cargarStockSelect(selHijo, stockHijo, badgeHijo);
      } else {
        ajusteFields.style.display = '';
        convFields.style.display   = 'none';

        selAjuste.setAttribute('name', 'id_producto');
        selAjuste.setAttribute('required', 'required');
        tipoMov.setAttribute('name', 'tipo_movimiento');
        tipoMov.setAttribute('required', 'required');
        cantAjuste.setAttribute('name', 'cantidad');
        cantAjuste.setAttribute('required', 'required');

        selPadre.removeAttribute('name');
        selPadre.removeAttribute('required');
        selHijo.removeAttribute('name');
        selHijo.removeAttribute('required');
        cantConv.removeAttribute('name');
        cantConv.removeAttribute('required');

        activarNuevoHijo(false);
      }
    }

    async function cargarStockSelect(selectEl, labelEl, badgeEl) {
      if (!selectEl || !labelEl || !badgeEl) return;
      if (selectEl === selHijo && esNuevoHijoActivo()) {
        labelEl.textContent = '—';
        setBadgeTone(badgeEl, null);
        return;
      }
      const id = selectEl.value;
      if (!id) {
        labelEl.textContent = '—';
        setBadgeTone(badgeEl, null);
        return;
      }
      try {
        const res  = await fetch('api_stock_producto.php?id_producto=' + encodeURIComponent(id));
        const json = await res.json();
        const val  = Number(json?.stock_actual ?? 0);
        labelEl.textContent = Number.isFinite(val) ? numberPY.format(val) : '—';
        setBadgeTone(badgeEl, Number.isFinite(val) ? val : null);
      } catch {
        labelEl.textContent = '—';
        setBadgeTone(badgeEl, null);
      }
    }

    function setBadgeTone(badgeEl, value) {
      if (!badgeEl) return;
      badgeEl.classList.remove('stock--ok','stock--warn','stock--zero');
      if (value === null || value === undefined) return;
      if (value <= 0)         badgeEl.classList.add('stock--zero');
      else if (value <= 5)    badgeEl.classList.add('stock--warn');
      else                    badgeEl.classList.add('stock--ok');
    }

    function actualizarHijos() {
      const parentId = selPadre.value;
      const hijos = hijosPorPadre[parentId] || [];
      selHijo.innerHTML = '';
      if (!parentId) {
        selHijo.disabled = true;
        selHijo.innerHTML = '<option value="">Seleccione un envase</option>';
        convHint.textContent = '';
        activarNuevoHijo(false);
        return;
      }
      if (hijos.length === 0) {
        selHijo.disabled = true;
        selHijo.innerHTML = '<option value="">Se creará un fraccionado</option>';
        convHint.textContent = 'Completá los datos para generar el fraccionado.';
        activarNuevoHijo(true);
        if (!nombreHijoInput.value) {
          nombreHijoInput.value = generarNombreFraccionado();
        }
        return;
      }
      activarNuevoHijo(false);
      selHijo.disabled = false;
      hijos.forEach(hijo => {
        const opt = document.createElement('option');
        opt.value = hijo.id_producto;
        opt.textContent = `${hijo.nombre}${hijo.unidad_base ? ' (' + hijo.unidad_base + ')' : ''}`;
        opt.dataset.factor = hijo.factor_equivalencia;
        opt.dataset.unidad = hijo.unidad_base;
        selHijo.appendChild(opt);
      });
      selHijo.selectedIndex = 0;
      actualizarConversionInfo();
    }

    function activarNuevoHijo(flag) {
      if (!nuevoHijoBox) return;
      nuevoHijoBox.style.display = flag ? '' : 'none';
      nuevoHijoBox.dataset.active = flag ? '1' : '0';

      if (flag) {
        selHijo.removeAttribute('required');
        selHijo.value = '';
        asignarNombreCampo(nombreHijoInput, true);
        asignarNombreCampo(unidadHijoInput, true);
        asignarNombreCampo(factorHijoInput, true);
        asignarNombreCampo(precioVentaInput, true);
        asignarNombreCampo(precioCompraInput, true);
        if (nombreHijoInput) nombreHijoInput.required = true;
        if (unidadHijoInput) unidadHijoInput.required = true;
        if (factorHijoInput) factorHijoInput.required = true;
      } else {
        selHijo.setAttribute('required', 'required');
        limpiarCampoNuevo(nombreHijoInput);
        limpiarCampoNuevo(unidadHijoInput);
        limpiarCampoNuevo(factorHijoInput);
        limpiarCampoNuevo(precioVentaInput);
        limpiarCampoNuevo(precioCompraInput);
      }
      actualizarConversionInfo();
    }

    function asignarNombreCampo(input, mantenerNombre = false) {
      if (!input) return;
      const campo = input.dataset.name;
      if (!campo) return;
      input.name = campo;
      if (!mantenerNombre && !input.value) {
        input.value = '';
      }
    }

    function limpiarCampoNuevo(input) {
      if (!input) return;
      input.removeAttribute('name');
      input.removeAttribute('required');
      input.value = '';
    }

    function esNuevoHijoActivo() {
      return nuevoHijoBox && nuevoHijoBox.dataset.active === '1';
    }

    function obtenerFactorActual() {
      if (esNuevoHijoActivo()) {
        return parseFloat(factorHijoInput?.value ?? '0') || 0;
      }
      const opt = selHijo.selectedOptions[0];
      return opt ? parseFloat(opt.dataset.factor || '0') || 0 : 0;
    }

    function obtenerUnidadActual() {
      if (esNuevoHijoActivo()) {
        return (unidadHijoInput?.value || '').trim();
      }
      const opt = selHijo.selectedOptions[0];
      return opt ? (opt.dataset.unidad || '') : '';
    }

    function actualizarConversionInfo() {
      const factor = obtenerFactorActual();
      const unidad = obtenerUnidadActual();
      const qty    = parseFloat(cantConv.value) || 0;

      if (esNuevoHijoActivo()) {
        if (!factor || !unidad) {
          convHint.textContent = 'Ingresá unidad y factor para calcular la conversión.';
          return;
        }
      }

      if (factor <= 0) {
        convHint.textContent = 'Configurá el factor_equivalencia del producto fraccionado.';
        return;
      }
      if (qty > 0) {
        const total = qty * factor;
        const unidadTexto = unidad ? ` ${unidad}` : '';
        convHint.textContent = `${qty} envase(s) ⇒ ${numberPY.format(total)}${unidadTexto}`;
      } else {
        const unidadTexto = unidad ? ` ${unidad}` : '';
        convHint.textContent = `1 envase equivale a ${numberPY.format(factor)}${unidadTexto}`;
      }
    }

    function generarNombreFraccionado() {
      const padreSeleccionado = selPadre.selectedOptions[0];
      const base = padreSeleccionado ? padreSeleccionado.textContent.trim() : 'Producto fraccionado';
      return `${base} (fraccionado)`;
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      msg.textContent = 'Guardando...';
      msg.className = '';
      const data = new FormData(form);
      const modo = data.get('modo');

      if (modo === 'conversion') {
        if (!data.get('id_producto')) {
          msg.textContent = 'Seleccioná el envase a abrir.';
          msg.className = 'err';
          return;
        }
        const cantidad = parseFloat(data.get('cantidad') || '0');
        if (!cantidad || cantidad <= 0) {
          msg.textContent = 'Indicá la cantidad de envases a abrir.';
          msg.className = 'err';
          return;
        }
        const idHijo = data.get('id_producto_hijo');
        if (!idHijo) {
          const nombreNuevo = (data.get('nombre_hijo') || '').trim();
          const unidadNueva = (data.get('unidad_base_hijo') || '').trim();
          const factorNuevo = parseFloat(data.get('factor_equivalencia_hijo') || '0');

          if (!nombreNuevo || !unidadNueva || !factorNuevo || factorNuevo <= 0) {
            msg.textContent = 'Ingresá nombre, unidad y factor para crear el fraccionado.';
            msg.className = 'err';
            return;
          }
        }
      }

      try {
        const res  = await fetch('../../menu/stock/stock_guardar.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
          msg.textContent = json.mensaje || 'Movimiento registrado.';
          msg.className   = 'ok';
          form.reset();
          activarNuevoHijo(false);
          toggleMode();
          setTimeout(()=> location.reload(), 700);
        } else {
          msg.textContent = json.mensaje || 'No se pudo guardar.';
          msg.className   = 'err';
        }
      } catch (err) {
        msg.textContent = 'Error de red o servidor.';
        msg.className   = 'err';
      }
    });

    window.addEventListener('DOMContentLoaded', () => {
      toggleMode();
      cargarStockSelect(selAjuste, stockAjuste, badgeAjuste);
    });
  </script>

  <script src="../../menu/navbar.js"></script>
</body>
</html>
