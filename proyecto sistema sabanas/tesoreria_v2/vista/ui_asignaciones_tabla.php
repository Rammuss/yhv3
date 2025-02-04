<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Visualizar Asignaciones</title>
  <!-- Bulma CSS para el diseño -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
  <section class="section">
    <div class="container">
      <h1 class="title">Asignaciones</h1>
      <table class="table is-fullwidth is-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Proveedor</th>
            <th>Monto</th>
            <th>Fecha de Asignación</th>
            <th>Estado</th>
            <th>Descripción</th>
          </tr>
        </thead>
        <tbody id="tablaAsignaciones">
          <!-- Se cargarán las asignaciones dinámicamente -->
        </tbody>
      </table>
    </div>
  </section>
  <script src="../js/asignaciones_lista.js"></script>
</body>
</html>
