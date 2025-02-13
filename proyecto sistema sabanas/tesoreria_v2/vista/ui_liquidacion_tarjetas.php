<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cargar Reporte de Tarjetas</title>
  <!-- Incluir Bulma desde CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
</head>
<body>
  <section class="section">
    <div class="container">
      <h1 class="title">Cargar Reporte de Tarjetas</h1>
      <form id="reporteForm" enctype="multipart/form-data">
        <!-- Campo: Seleccionar Procesadora -->
        <div class="field">
          <label class="label">Procesadora</label>
          <div class="control">
            <div class="select is-fullwidth">
              <select name="id_procesadora" id="procesadoraSelect" required>
                <option value="">Seleccione una procesadora</option>
                <!-- Las opciones se cargarán dinámicamente -->
              </select>
            </div>
          </div>
        </div>

        <!-- Campo: Fecha del Reporte -->
        
        <div class="field">
            <label class="label">Fecha del Reporte</label>
            <div class="control">
                <input class="input" type="date" id="fecha_reporte" name="fecha_reporte" required>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var fechaActual = new Date().toISOString().split('T')[0];
            document.getElementById('fecha_reporte').value = fechaActual;
        });
    </script>

        <!-- Campo: Archivo de Reporte -->
        <div class="field">
          <label class="label">Archivo de Reporte</label>
          <div class="control">
            <input class="input" type="file" name="reporte_file" accept=".csv,.xlsx,.xls,.xml" required>
          </div>
        </div>

        <!-- Botón de Envío -->
        <div class="field">
          <div class="control">
            <button class="button is-primary" type="submit">Cargar Reporte</button>
          </div>
        </div>
      </form>

      <!-- Contenedor para mostrar mensajes (éxito o error) -->
      <div id="mensaje"></div>
    </div>
  </section>

  <!-- Archivo JS para cargar procesadoras y enviar el formulario -->
  <script src="../js/liquidacion_tarjetas.js"></script>
</body>
</html>
