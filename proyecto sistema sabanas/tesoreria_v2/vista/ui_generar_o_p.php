<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Registrar Orden de Pago</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="../css/styles_T.css">

    <style>
        .required::after { content: "*"; color: red; margin-left: 3px; }
    </style>
</head>
<body>
<div id="navbar-container"></div>

    <section class="section">
        <div class="container">
            <h1 class="title">Registrar Nueva Orden</h1>
            
            <!-- Formulario -->
            <div class="box">
                <div class="columns">
                    <!-- Columna 1: Datos de la Provisión -->
                    <div class="column">
                        <div class="field">
                            <label class="label required">Provisiones</label>
                            <div class="select is-fullwidth">
                                <select id="selectProvision" required>
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label required">Proveedor</label>
                            <input id="inputProveedor" class="input" readonly>
                        </div>

                        <input type="hidden" id="hiddenIdProveedor">

                        <div class="field">
                            <label class="label required">Monto</label>
                            <input id="inputMonto" class="input" type="number" step="0.01" readonly>
                        </div>
                    </div>

                    <!-- Columna 2: Datos del Pago -->
                    <div class="column">
                        <div class="field">
                            <label class="label required">Método de Pago</label>
                            <div class="select is-fullwidth">
                                <select id="selectMetodo" required>
                                    <option value="">Seleccionar</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Efectivo">Efectivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label required">Cuenta Bancaria</label>
                            <div class="select is-fullwidth">
                                <select id="selectCuenta" required>
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Referencia</label>
                            <input id="inputReferencia" class="input" placeholder="Ej: CHK-001">
                        </div>
                    </div>
                </div>

                <button class="button is-primary" onclick="guardarOrden()">Guardar Orden</button>
            </div>

            <!-- Notificación -->
            <div id="notificacion" class="notification is-hidden"></div>
        </div>
    </section>

    <script src="../js/generarOrdenPago.js"></script>
    <script src="../js/navbarT.js"></script>

</body>
</html>