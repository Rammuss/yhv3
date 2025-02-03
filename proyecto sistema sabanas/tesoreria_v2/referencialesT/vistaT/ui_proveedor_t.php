<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Registrar Proveedor</title>
  <!-- Importar Bulma desde CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>

<body>
  <section class="section">
    <div class="container">
      <h1 class="title">Registrar Proveedor</h1>
      <!-- Formulario para registrar un proveedor -->
      <form id="proveedorForm">
        <!-- Nombre -->
        <div class="field">
          <label class="label">Nombre</label>
          <div class="control">
            <input class="input" type="text" name="nombre" placeholder="Nombre del proveedor" required>
          </div>
        </div>

        <!-- Dirección -->
        <div class="field">
          <label class="label">Dirección</label>
          <div class="control">
            <input class="input" type="text" name="direccion" placeholder="Dirección" required>
          </div>
        </div>

        <!-- Teléfono -->
        <div class="field">
          <label class="label">Teléfono</label>
          <div class="control">
            <input class="input" type="text" name="telefono" placeholder="Teléfono" required>
          </div>
        </div>

        <!-- Email -->
        <div class="field">
          <label class="label">Email</label>
          <div class="control">
            <input class="input" type="email" name="email" placeholder="Email" required>
          </div>
        </div>

        <!-- RUC -->
        <div class="field">
          <label class="label">RUC</label>
          <div class="control">
            <input class="input" type="text" name="ruc" placeholder="RUC" required>
          </div>
        </div>

        <!-- País: Cambiado a un select -->
        <div class="field">
          <label class="label">País</label>
          <div class="control">
            <div class="select">
              <select name="id_pais" id="paisSelect">
                <option value="">Seleccione un país</option>
              </select>
            </div>
          </div>
        </div>

        <div class="field">
          <label class="label">Ciudad</label>
          <div class="control">
            <div class="select">
              <select id="selectCiudad" required>
                <option value="">Seleccione una ciudad</option>
                <!-- Las ciudades se cargarán aquí -->
              </select>
            </div>
          </div>
        </div>

        <!-- Tipo de Proveedor -->
        <div class="field">
          <label class="label">Tipo</label>
          <div class="control">
            <div class="select">
              <select name="tipo" id="tipoProveedor">
                <option value="">Seleccione un tipo</option>
                <option value="comercial">Comercial</option>
                <option value="fondo_fijo">Fondo Fijo</option>
              </select>
            </div>
          </div>
        </div>


        <!-- Botón de envío -->
        <div class="field">
          <div class="control">
            <button class="button is-primary" type="submit">Registrar Proveedor</button>
          </div>
        </div>
      </form>

      <!-- Espacio para mostrar mensajes de respuesta -->
      <div id="mensaje"></div>
    </div>
  </section>

  <!-- Incluir el archivo JavaScript -->
  <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/referencialesT/jsT/proveedor_t.js"></script>
</body>

</html>