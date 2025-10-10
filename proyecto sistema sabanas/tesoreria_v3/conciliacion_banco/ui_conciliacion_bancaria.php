<?php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Conciliacion Bancaria</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root {
      font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
      color: #1f2933;
      background: #f5f7fa;
      --card: #fff;
      --line: #e5e7eb;
      --muted: #6b7280;
      --primary: #2563eb;
      --danger: #dc2626;
      --ok: #059669;
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); }
    header {
      background: #fff;
      border-bottom: 1px solid var(--line);
      padding: 18px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    header h1 { margin: 0; font-size: 20px; }
    main {
      max-width: 1400px;
      margin: 0 auto;
      padding: 24px;
      display: grid;
      gap: 24px;
    }
    .filters {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 18px;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }
    label { display: grid; gap: 6px; font-size: 13px; color: var(--muted); font-weight: 600; }
    input, select {
      font: inherit;
      padding: 8px 10px;
      border-radius: 8px;
      border: 1px solid var(--line);
      background: #fff;
    }
    .filters-actions { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
    button {
      font: inherit;
      font-weight: 600;
      padding: 9px 14px;
      border-radius: 8px;
      border: 1px solid var(--line);
      background: #fff;
      cursor: pointer;
      transition: transform .1s ease;
    }
    button.primary { background: var(--primary); border-color: var(--primary); color: #fff; }
    button.danger { background: var(--danger); border-color: var(--danger); color: #fff; }
    button:hover { transform: translateY(-1px); }
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
    }
    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 16px;
      display: grid;
      gap: 8px;
    }
    .session-card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 12px 16px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      justify-content: space-between;
      transition: border-color .15s ease, box-shadow .15s ease;
    }
    .session-card.active {
      border-color: var(--primary);
      box-shadow: 0 10px 30px rgba(37,99,235,0.12);
    }
    .session-card .meta {
      display: grid;
      gap: 4px;
    }
    .session-card .meta strong { font-size: 14px; }
    .session-card .meta span { font-size: 12px; color: var(--muted); }
    .card h3 { margin: 0; font-size: 14px; color: var(--muted); }
    .card strong { font-size: 22px; }
    .board {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 18px;
    }
    .panel {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 14px;
      display: flex;
      flex-direction: column;
      min-height: 360px;
      overflow: hidden;
    }
    .panel header {
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
      background: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }
    .panel header h2 { margin: 0; font-size: 16px; }
    .panel header button { font-size: 13px; padding: 7px 10px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { padding: 10px 12px; border-bottom: 1px solid var(--line); text-align: left; }
    th { background: #eef2ff; font-weight: 600; color: #374151; }
    tbody tr:hover { background: #f9fafb; }
    .right { text-align: right; }
    tr.selected { background: #e0f2fe; }
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      color: #1f2933;
      background: #e5e7eb;
    }
    .badge.ok { background: #dcfce7; color: #047857; }
    .badge.warn { background: #fef3c7; color: #b45309; }
    .badge.danger { background: #fee2e2; color: #b91c1c; }

    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.5);
      display: none;
      align-items: flex-start;
      justify-content: center;
      padding: 40px 16px;
      z-index: 50;
    }
    .modal-backdrop.active { display: flex; }
    .modal {
      background: #fff;
      border-radius: 14px;
      border: 1px solid var(--line);
      max-width: 680px;
      width: 100%;
      overflow: hidden;
      box-shadow: 0 24px 60px rgba(15,23,42,0.22);
    }
    .modal header {
      border-bottom: 1px solid var(--line);
      padding: 14px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }
    .modal header h3 { margin: 0; font-size: 16px; }
    .modal header button { border: none; background: none; font-size: 20px; cursor: pointer; color: var(--muted); }
    .modal .body { padding: 18px; display: grid; gap: 12px; }
    .modal footer { padding: 14px 18px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 10px; }
    .empty {
      padding: 18px;
      text-align: center;
      color: var(--muted);
    }
  </style>
</head>
<body>
  <header>
    <h1>Conciliacion Bancaria</h1>
    <div>
      <button id="btn-nueva" class="primary">Nueva conciliacion</button>
      <button id="btn-descargar">Descargar reporte</button>
    </div>
  </header>

  <main>
    <section class="filters" id="filters">
      <label>
        Cuenta bancaria
        <select id="f-cuenta"></select>
      </label>
      <label>
        Estado
        <select id="f-estado">
          <option value="">Todos</option>
          <option value="Abierta">Abiertas</option>
          <option value="Cerrada">Cerradas</option>
          <option value="Anulada">Anuladas</option>
        </select>
      </label>
      <label>
        Fecha desde
        <input type="date" id="f-desde" />
      </label>
      <label>
        Fecha hasta
        <input type="date" id="f-hasta" />
      </label>
      <div class="filters-actions">
        <button id="btn-filtrar" class="primary">Aplicar filtros</button>
        <button id="btn-reset">Limpiar</button>
      </div>
    </section>

    <section id="sesiones-list" style="display:grid; gap:12px;"></section>

    <section class="cards" id="kpis">
      <div class="card">
        <h3>Conciliacion</h3>
        <strong id="kpi-id">--</strong>
        <div id="kpi-periodo" class="muted">Sin seleccion</div>
      </div>
      <div class="card">
        <h3>Diferencia</h3>
        <strong id="kpi-diferencia">--</strong>
        <span class="badge" id="badge-estado">Sin estado</span>
      </div>
      <div class="card">
        <h3>Movimientos pendientes</h3>
        <strong id="kpi-pend-mov">--</strong>
        <div class="muted" id="kpi-pend-mov-desc"></div>
      </div>
      <div class="card">
        <h3>Extracto pendiente</h3>
        <strong id="kpi-pend-ext">--</strong>
        <div class="muted" id="kpi-pend-ext-desc"></div>
      </div>
      <div class="card" style="align-items:flex-start;">
        <h3>Acciones</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button id="btn-accion-cerrar" class="primary" disabled>Cerrar conciliacion</button>
          <button id="btn-accion-reabrir" disabled>Reabrir</button>
        </div>
      </div>
    </section>

    <section class="board">
      <div class="panel" id="panel-mov">
        <header>
          <h2>Movimientos del sistema</h2>
          <div>
            <button id="btn-match-auto">Auto-match</button>
            <button id="btn-match-manual">Match manual</button>
            <button id="btn-ajuste-libro">Ajuste libro</button>
          </div>
        </header>
        <div style="overflow:auto; flex:1;">
          <table>
            <thead>
              <tr>
                <th></th>
                <th>Fecha</th>
                <th>Descripcion</th>
                <th class="right">Monto</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody id="tbl-movimientos">
              <tr><td colspan="5" class="empty">Seleccione una conciliacion para ver movimientos.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel" id="panel-extracto">
        <header>
          <h2>Extracto bancario</h2>
          <div>
            <button id="btn-cargar-extracto">Cargar CSV</button>
            <button id="btn-ajuste-banco">Ajuste banco</button>
          </div>
        </header>
        <div style="overflow:auto; flex:1;">
          <table>
            <thead>
              <tr>
                <th></th>
                <th>Fecha</th>
                <th>Concepto</th>
                <th class="right">Monto</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody id="tbl-extracto">
              <tr><td colspan="5" class="empty">Sin datos de extracto.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <!-- Modal Nueva conciliacion -->
  <div class="modal-backdrop" id="modal-nueva">
    <div class="modal">
      <header>
        <h3>Nueva conciliacion</h3>
        <button type="button" data-close>&times;</button>
      </header>
      <div class="body">
        <form id="form-nueva" class="modal-form">
          <label>Cuenta bancaria
            <select name="id_cuenta_bancaria" required></select>
          </label>
          <label>Fecha desde
            <input type="date" name="fecha_desde" required />
          </label>
          <label>Fecha hasta
            <input type="date" name="fecha_hasta" required />
          </label>
          <label>Saldo libro inicial
            <input type="number" step="0.01" name="saldo_libro_inicial" required />
          </label>
          <label>Saldo libro final
            <input type="number" step="0.01" name="saldo_libro_final" required />
          </label>
          <label>Saldo banco inicial
            <input type="number" step="0.01" name="saldo_banco_inicial" required />
          </label>
          <label>Saldo banco final
            <input type="number" step="0.01" name="saldo_banco_final" required />
          </label>
          <label>Observacion
            <textarea name="observacion" rows="2"></textarea>
          </label>
          <div class="badge danger" id="nueva-error" style="display:none;"></div>
        </form>
      </div>
      <footer>
        <button type="button" data-close>Cancelar</button>
        <button type="button" class="primary" id="btn-crear-conc">Crear</button>
      </footer>
    </div>
  </div>

  <!-- Modal Carga de extracto -->
  <div class="modal-backdrop" id="modal-extracto">
    <div class="modal">
      <header>
        <h3>Cargar extracto bancario</h3>
        <button type="button" data-close>&times;</button>
      </header>
      <div class="body">
        <form id="form-extracto" enctype="multipart/form-data">
          <input type="hidden" name="id_conciliacion" />
          <label>Archivo CSV
            <input type="file" name="archivo" accept=".csv" required />
          </label>
          <label style="display:flex; align-items:center; gap:6px;">
            <input type="checkbox" name="skip_header" value="1" checked /> Saltar encabezado
          </label>
          <div class="badge danger" id="extracto-error" style="display:none;"></div>
        </form>
      </div>
      <footer>
        <button type="button" data-close>Cancelar</button>
        <button type="button" class="primary" id="btn-subir-extracto">Subir</button>
      </footer>
    </div>
  </div>

  <!-- Modal Match manual -->
  <div class="modal-backdrop" id="modal-match">
    <div class="modal">
      <header>
        <h3>Match manual</h3>
        <button type="button" data-close>&times;</button>
      </header>
      <div class="body">
        <div class="muted">Seleccione un movimiento del sistema y un registro del extracto para conciliar.</div>
        <div class="badge danger" id="match-error" style="display:none;"></div>
      </div>
      <footer>
        <button type="button" data-close>Cancelar</button>
        <button type="button" class="primary" id="btn-match-ok">Conciliar</button>
      </footer>
    </div>
  </div>

  <!-- Modal Ajustes -->
  <div class="modal-backdrop" id="modal-ajuste">
    <div class="modal">
      <header>
        <h3 id="titulo-ajuste">Ajuste</h3>
        <button type="button" data-close>&times;</button>
      </header>
      <div class="body">
        <form id="form-ajuste">
          <input type="hidden" name="accion" />
          <input type="hidden" name="id_conciliacion" />
          <label>Monto
            <input type="number" step="0.01" name="monto" required />
          </label>
          <label>Signo
            <select name="signo">
              <option value="1">Ingreso / Credito</option>
              <option value="-1">Egreso / Debito</option>
            </select>
          </label>
          <label>Descripcion
            <textarea name="descripcion" rows="2"></textarea>
          </label>
          <div class="badge danger" id="ajuste-error" style="display:none;"></div>
        </form>
      </div>
      <footer>
        <button type="button" data-close>Cancelar</button>
        <button type="button" class="primary" id="btn-ajuste-ok">Registrar ajuste</button>
      </footer>
    </div>
  </div>

  <script src="js/conciliacion.sesiones.js"></script>
  <script src="js/conciliacion.extracto.js"></script>
  <script src="js/conciliacion.match.js"></script>
  <script src="js/conciliacion.reportes.js"></script>
</body>
</html>
