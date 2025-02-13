<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Registro de Pagos</title>
  <!-- Incluir Bulma CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <link rel="stylesheet" href="../css/styles_T.css">

</head>

<body>
<div id="navbar-container"></div>

  <section class="section">
    <div class="container">
      <h1 class="title">Registrar Pago</h1>
      <form id="registroPagoForm">
        <!-- Campo: Orden de Pago (select) -->
        <div class="field">
          <label class="label" for="ordenPagoSelect">Orden de Pago</label>
          <div class="control">
            <div class="select is-fullwidth">
              <select id="ordenPagoSelect" name="id_orden_pago" required>
                <option value="">Seleccione una orden de pago</option>
                <!-- Las opciones se cargarán dinámicamente -->
              </select>
            </div>
          </div>
        </div>

        <!-- Campo: Cuenta Bancaria (rellenado automáticamente) -->
        <div class="field">
          <label class="label" for="idCuentaBancaria">ID Cuenta Bancaria</label>
          <div class="control">
            <input class="input" type="number" id="idCuentaBancaria" name="id_cuenta_bancaria" placeholder="Se completará automáticamente" readonly>
          </div>
        </div>

        <!-- Campo: Monto (rellenado automáticamente) -->
        <div class="field">
          <label class="label" for="monto">Monto</label>
          <div class="control">
            <input class="input" type="number" step="0.01" id="monto" name="monto" placeholder="Se completará automáticamente" readonly>
          </div>
        </div>

        

        <!-- Campo: Referencia Bancaria (rellenado automáticamente) -->
        <div class="field">
          <label class="label" for="referenciaBancaria">Referencia Bancaria</label>
          <div class="control">
            <input class="input" type="text" id="referenciaBancaria" name="referencia_bancaria" placeholder="Se completará automáticamente" readonly>
          </div>
        </div>

        <div class="field">
          <label class="label" for="metodoPago">Método de Pago:</label>
          <div class="control"><input class="input" type="text" id="metodoPago" placeholder="Se completará automáticamente" name="metodo_pago" readonly></div>
        </div>


        <!-- Campo: Nombre del Beneficiario/Empresa (nuevo y rellenado automáticamente) -->
        <div class="field">
          <label class="label" for="nombreBeneficiario">Nombre del Beneficiario/Empresa</label>
          <div class="control">
            <input class="input" type="text" id="nombreBeneficiario" name="nombre_beneficiario" placeholder="Se completará automáticamente" readonly>
          </div>
        </div>

        <!-- Botón de envío -->
        <div class="field">
          <div class="control">
            <button type="submit" class="button is-primary">Registrar Pago</button>
          </div>
        </div>
      </form>
    </div>
  </section>

  <!-- Incluir el archivo JS -->
  <script src="../js/pagos.js"></script>
  <script src="../js/navbarT.js"></script>

</body>

</html>