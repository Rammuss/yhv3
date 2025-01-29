<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Orden de Pago</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <style>
        #campos_cheque {
            display: none;
            /* Oculto por defecto */
        }

        #factura_seleccionada {
            display: none;
            /* Oculto hasta que se seleccione una factura */
        }
    </style>
</head>

<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Generar Orden de Pago</h1>
            <form id="ordenPagoForm" class="box">
                <!-- Campos de búsqueda -->
                <div class="field">
                    <label class="label" for="numero_factura">Número de Factura</label>
                    <div class="control">
                        <input class="input" type="text" id="numero_factura" name="numero_factura" placeholder="Número de factura">
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="ruc">RUC</label>
                    <div class="control">
                        <input class="input" type="text" id="ruc" name="ruc" placeholder="RUC de la empresa">
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="fecha">Fecha de Factura</label>
                    <div class="control">
                        <input class="input" type="date" id="fecha" name="fecha">
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="estado">Estado</label>
                    <div class="control">
                        <div class="select">
                            <select id="estado" name="estado">
                                <option value="">Todos</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="generado">Generado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <div class="control">
                        <button type="button" id="buscar" class="button is-primary">Buscar Facturas</button>
                    </div>
                </div>

                <!-- Resultados de las facturas -->
                <div id="resultados_facturas" class="box">
                    <p class="has-text-centered">Aquí se mostrarán las facturas encontradas.</p>
                </div>
                <input type="hidden" id="factura_id" name="factura_id">

                <!-- Factura seleccionada -->
                <div id="factura_seleccionada" class="box">
                    <h2 class="subtitle">Factura Seleccionada</h2>
                    <p><strong>Número de Factura:</strong> <span id="factura_numero"></span></p>
                    <p><strong>RUC:</strong> <span id="factura_ruc"></span></p>
                    <p><strong>Fecha:</strong> <span id="factura_fecha"></span></p>
                    <p><strong>Estado:</strong> <span id="factura_estado"></span></p>
                    <p><strong>Total:</strong> <span id="factura_total"></span></p>
                </div>




                <!-- Contenedor para mostrar las facturas seleccionadas -->
                <div id="facturas_seleccionadas" class="box">
                    <h4 class="title is-4">Facturas Seleccionadas</h4>
                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr>
                                <th>Id factura</th>
                                <th>Número de Factura</th>
                                <th>Id Porveedor</th>
                                <th>RUC</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Total</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="lista_facturas_seleccionadas">
                            <!-- Aquí se agregarán dinámicamente las facturas seleccionadas -->
                        </tbody>
                    </table>
                </div>

                <!-- Campo oculto para enviar los datos al backend -->
                <input type="hidden" id="facturas_json" name="facturas_json">

                <hr>

                <!-- Método de pago -->
                <div class="field">
                    <label class="label" for="metodo_pago">Método de Pago</label>
                    <div class="control">
                        <div class="select">
                            <select id="metodo_pago" name="metodo_pago">
                                <option value="" selected disabled>Selecciona un método de pago</option> <!-- Opción vacía por defecto -->
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                </div>


                <!-- Campos de cheque -->
                <div id="campos_cheque" class="box">
                    <div class="field">
                        <label class="label" for="numero_cheque">Número de Cheque</label>
                        <div class="control">
                            <input class="input" type="text" id="numero_cheque" name="numero_cheque" placeholder="Número de cheque">
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="beneficiario">Beneficiario/Nombre/Empresa</label>
                        <div class="control">
                            <input class="input" type="text" id="beneficiario" name="beneficiario" placeholder="Beneficiario">
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="monto_cheque">Monto del Cheque</label>
                        <div class="control">
                            <input class="input" type="number" id="monto_cheque" name="monto_cheque" placeholder="Monto del cheque" step="0.01">
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="fecha_cheque">Fecha del Cheque</label>
                        <div class="control">
                            <input class="input" type="date" id="fecha_cheque" name="fecha_cheque">
                        </div>
                    </div>
                </div>

                <div class="field">
        <div class="control">
            <button type="button" class="button is-success" id="generarOrdenPago">Generar Orden de Pago</button>
        </div>
    </div>
            </form>
        </div>
    </section>

    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/js/generarOrdenPago.js"></script>
</body>

</html>