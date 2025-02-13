<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Aprobar / Rechazar Rendiciones</title>
  <!-- Bulma CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <link rel="stylesheet" href="../css/styles_T.css">

</head>
<body>
<div id="navbar-container"></div>

  <section class="section">
    <div class="container">
      <h1 class="title">Aprobar / Rechazar Rendiciones</h1>
      <table class="table is-fullwidth">
        <thead>
          <tr>
            <th>ID</th>
            <th>Asignación</th>
            <th>Fecha Rendición</th>
            <th>Total Rendido</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="rendicionesTable">
          <!-- Las rendiciones se cargarán dinámicamente -->
        </tbody>
      </table>
    </div>
  </section>
  <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/js/rendicion_gestionar.js"></script>
  <script src="../js/navbarT.js"></script>

</body>
</html>
