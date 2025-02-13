<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Depósito</title>
    <!-- Incluir Bulma desde CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    <link rel="stylesheet" href="../css/styles_T.css">

</head>
<body>
<div id="navbar-container"></div>

    <section class="section">
        <div class="container">
            <h1 class="title">Registrar Depósito</h1>
            <!-- El formulario no lleva atributo "action" para que lo maneje JS -->
            <form id="depositoForm" method="POST">
                <!-- Campo para seleccionar la cuenta bancaria -->
                <div class="field">
                    <label class="label">Cuenta Bancaria</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="cuenta_bancaria" id="cuentaBancariaSelect" required>
                                <option value="">Seleccione una cuenta bancaria</option>
                                <!-- Las opciones se cargarán dinámicamente con JS -->
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Campo para el número de boleta -->
                <div class="field">
                    <label class="label">Número de Boleta</label>
                    <div class="control">
                        <input class="input" type="text" name="numero_boleta" placeholder="Ejemplo: 001" required>
                    </div>
                </div>

                <!-- Campo para la fecha de depósito (con fecha actual por defecto) -->
                <div class="field">
                    <label class="label">Fecha de Depósito</label>
                    <div class="control">
                        <input class="input" type="date" name="fecha" id="fecha" required>
                    </div>
                </div>
                <script>
                    // Establecer la fecha actual por defecto en el campo de fecha
                    document.addEventListener('DOMContentLoaded', () => {
                        const fechaInput = document.getElementById('fecha');
                        const today = new Date().toISOString().split('T')[0];
                        fechaInput.value = today;
                    });
                </script>

                <!-- Campo para el monto depositado -->
                <div class="field">
                    <label class="label">Monto</label>
                    <div class="control">
                        <input class="input" type="number" step="0.01" name="monto" placeholder="Monto depositado" required>
                    </div>
                </div>

                <!-- (Opcional) Campo para "Nombre del Cliente"
                     Comenta o elimina este bloque si no es necesario -->
                <!--
                <div class="field">
                    <label class="label">Nombre del Cliente</label>
                    <div class="control">
                        <input class="input" type="text" name="nombre_cliente" placeholder="Nombre del Cliente">
                    </div>
                </div>
                -->

                <!-- Campo para el concepto del depósito -->
                <div class="field">
                    <label class="label">Concepto</label>
                    <div class="control">
                        <textarea class="textarea" name="concepto" placeholder="Descripción del depósito"></textarea>
                    </div>
                </div>

                <!-- Campo para el estado del depósito -->
                <div class="field">
                    <label class="label">Estado</label>
                    <div class="control">
                        <div class="select">
                            <select name="estado">
                                <option value="Confirmado" selected>Confirmado</option>
                                <option value="Pendiente">Pendiente</option>
                                <option value="Rechazado">Rechazado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Botón para enviar el formulario -->
                <div class="field">
                    <div class="control">
                        <button class="button is-primary" type="submit">Registrar Depósito</button>
                    </div>
                </div>
            </form>

            <!-- Contenedor para mostrar mensajes de éxito o error -->
            <div id="mensaje"></div>
        </div>
    </section>

    <!-- Incluir el archivo JS para cargar cuentas y enviar el formulario vía fetch -->
    <script src="../js/boletas_depositos.js"></script>
    <script src="../js/navbarT.js"></script>

</body>
</html>
