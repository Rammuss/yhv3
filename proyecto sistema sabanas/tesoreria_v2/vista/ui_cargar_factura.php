<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Factura</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Cargar Factura</h1>
            <h2 class="subtitle">Ingresa los datos de la factura y los productos.</h2>

            <!-- Formulario de Factura -->
            <form id="formulario-factura">
                <!-- Cabecera: Datos del Proveedor, Factura, Referencia, Orden de Compra y Método de Pago -->
                <div class="columns">
                    <!-- Columna Izquierda -->
                    <div class="column">
                        <!-- Datos del Proveedor -->
                        <div class="field">
                            <label class="label">Proveedor</label>
                            <div class="control">
                                <input class="input" type="text" id="proveedor" placeholder="Nombre del proveedor" required>
                            </div>
                        </div>

                        <!-- Número de Factura -->
                        <div class="field">
                            <label class="label">Número de Factura</label>
                            <div class="control">
                                <input class="input" type="text" id="numero-factura" placeholder="Número de factura" required>
                            </div>
                        </div>

                        <!-- Fecha de Emisión -->
                        <div class="field">
                            <label class="label">Fecha de Emisión</label>
                            <div class="control">
                                <input class="input" type="date" id="fecha-emision" required>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha -->
                    <div class="column">
                        

                        <!-- Orden de Compra -->
                        <div class="field">
                            <label class="label">Orden de Compra (Opcional)</label>
                            <div class="control">
                                <input class="input" type="text" id="orden-compra" placeholder="Número de orden de compra">
                            </div>
                        </div>

                        <!-- Método de Pago -->
                        <div class="field">
                            <label class="label">Método de Pago</label>
                            <div class="control">
                                <div class="select">
                                    <select id="forma-pago">
                                        <option value="transferencia">Transferencia Bancaria</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="efectivo">Efectivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle de Productos -->
                <div class="columns">
                    <div class="column">
                        <div class="field">
                            <label class="label">Productos</label>
                            <div class="control">
                                <div class="select">
                                    <select id="producto" onchange="cargarProducto()">
                                        <option value="">Seleccione un producto</option>
                                        <option value="1" data-precio="1000" data-iva="21">Producto A</option>
                                        <option value="2" data-precio="2000" data-iva="10.5">Producto B</option>
                                        <option value="3" data-precio="1500" data-iva="0">Producto C</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label">Cantidad</label>
                            <div class="control">
                                <input class="input" type="number" id="cantidad" placeholder="Cantidad" min="1" required>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label">Descuento</label>
                            <div class="control">
                                <input class="input" type="number" id="descuento" placeholder="Descuento" min="0">
                            </div>
                        </div>

                        <div class="field">
                            <div class="control">
                                <button type="button" class="button is-primary" onclick="agregarProducto()">Agregar Producto</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Productos Agregados -->
                <table class="table is-fullwidth">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio Unitario</th>
                            <th>Cantidad</th>
                            <th>IVA</th>
                            <th>Descuento</th>
                            <th>Subtotal</th>
                            <th>Total por Ítem</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-productos">
                        <!-- Aquí se agregarán los productos dinámicamente -->
                    </tbody>
                </table>

                <!-- Totales -->
                <div class="columns">
                    <div class="column">
                        <div class="field">
                            <label class="label">Monto Neto</label>
                            <div class="control">
                                <input class="input" type="text" id="monto-neto" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label">IVA</label>
                            <div class="control">
                                <input class="input" type="text" id="iva-total" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label">Monto Total</label>
                            <div class="control">
                                <input class="input" type="text" id="monto-total" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botón de Envío -->
                <div class="field">
                    <div class="control">
                        <button type="submit" class="button is-success">Guardar Factura</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <script src="../js/cargarfacturas.js"></script>
</body>
</html>