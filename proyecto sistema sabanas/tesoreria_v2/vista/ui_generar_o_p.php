<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Órdenes de Pago</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Generar Órdenes de Pago</h1>
            <h2 class="subtitle">Selecciona las facturas pendientes de pago y genera una orden de pago.</h2>

            <!-- Formulario para seleccionar facturas y método de pago -->
            <form id="formulario-orden-pago">
                <!-- Lista de facturas pendientes -->
                <div class="field">
                    <label class="label">Facturas Pendientes</label>
                    <div class="control">
                        <div class="select is-multiple">
                            <select id="facturas-pendientes" multiple>
                                <!-- Las facturas se cargarán dinámicamente desde JavaScript -->
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Selección de método de pago -->
                <div class="field">
                    <label class="label">Método de Pago</label>
                    <div class="control">
                        <div class="select">
                            <select id="metodo-pago" required onchange="mostrarCamposCheque()">
                                <option value="transferencia">Transferencia Bancaria</option>
                                <option value="cheque">Cheque</option>
                                <option value="efectivo">Efectivo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Campos adicionales para cheques (ocultos por defecto) -->
                <div id="campos-cheque" style="display: none;">
                    <div class="field">
                        <label class="label">Fecha del Cheque</label>
                        <div class="control">
                            <input class="input" type="date" id="fecha-cheque">
                        </div>
                    </div>

                    <div class="field">
                        <label class="label">Monto del Cheque</label>
                        <div class="control">
                            <input class="input" type="number" id="monto-cheque" placeholder="Monto del cheque">
                        </div>
                    </div>

                    <div class="field">
                        <label class="label">Número del Cheque</label>
                        <div class="control">
                            <input class="input" type="text" id="numero-cheque" placeholder="Número del cheque">
                        </div>
                    </div>
                </div>

                <!-- Botón para generar la orden de pago -->
                <div class="field">
                    <div class="control">
                        <button type="button" class="button is-primary" onclick="generarOrdenPago()">Generar Orden de Pago</button>
                    </div>
                </div>
            </form>

            <!-- Botón para generar cheque en PDF (simulado) -->
            <div class="field">
                <div class="control">
                    <button type="button" class="button is-success" onclick="generarChequePDF()">Generar Cheque en PDF</button>
                </div>
            </div>
        </div>
    </section>

    <script src="../js/generarOrdenPago.js"></script>
</body>
</html>