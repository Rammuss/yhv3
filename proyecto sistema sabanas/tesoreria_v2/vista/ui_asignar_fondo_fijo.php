<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar Asignación FF</title>
  <!-- Importar Bulma desde CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <link rel="stylesheet" href="../css/styles_T.css">

</head>
<body>
<div id="navbar-container"></div>

  <section class="section">
    <div class="container">
      <h1 class="title">Registrar Asignación FF</h1>
      <!-- Formulario que no se envía de forma tradicional -->
      <form id="registroForm">
        
        <!-- Selección de Proveedor -->
        <div class="field">
          <label class="label">Proveedor tipo ff</label>
          <div class="control">
            <div class="select">
              <select name="proveedor_id" id="proveedorSelect" required>
                <option value="">Seleccione un proveedor</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Monto -->
        <div class="field">
          <label class="label">Monto</label>
          <div class="control">
            <input class="input" type="number" step="0.01" name="monto" placeholder="Ingrese el monto" required>
          </div>
        </div>
        
        <!-- Fecha de Asignación -->
        <div class="field">
          <label class="label">Fecha de Asignación</label>
          <div class="control">
            <input class="input" type="date" name="fecha_asignacion" required>
          </div>
        </div>
        
        <!-- Estado (se establece 'Activa' por defecto) -->
        <div class="field">
          <label class="label">Estado</label>
          <div class="control">
            <div class="select">
              <select name="estado">
                <option value="Activa" selected>Activa</option>
                <option value="Cerrada">Cerrada</option>
              </select>
            </div>
          </div>
        </div>
        
        <!-- Descripción -->
        <div class="field">
          <label class="label">Descripción</label>
          <div class="control">
            <textarea class="textarea" name="descripcion" placeholder="Ingrese una descripción (opcional)"></textarea>
          </div>
        </div>
        
        <!-- Botón de envío -->
        <div class="field">
          <div class="control">
            <button class="button is-primary" type="submit">Registrar</button>
          </div>
        </div>
      </form>

      <!-- Espacio para mostrar mensajes de respuesta -->
      <div id="mensaje"></div>
    </div>
  </section>
  <!-- Incluir el archivo JavaScript -->
  <script src="../js/asignar_ff.js"></script>
  <script src="../js/navbarT.js"></script>

</body>
</html>
