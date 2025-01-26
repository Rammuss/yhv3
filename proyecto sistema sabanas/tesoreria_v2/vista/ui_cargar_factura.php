<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Factura</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <style>
        /* Estilos para la lista de resultados */
        #lista-proveedores {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #ddd;
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        #lista-proveedores div {
            padding: 10px;
            cursor: pointer;
        }
        #lista-proveedores div:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Cargar Factura</h1>
            <h2 class="subtitle">Ingresa los datos de la factura y los productos.</h2>

            <!-- Formulario de Factura -->
            <form id="formulario-factura">
                <!-- Sección 1: Datos de la Factura -->
                <div class="box">
                    <h3 class="title is-4">Datos de la Factura</h3>
                    <div class="columns">
                        <!-- Columna Izquierda -->
                        <div class="column">
                            <!-- Buscar Proveedor -->
                            <div class="field">
                                <label class="label">Buscar Proveedor (RUC o Nombre)</label>
                                <div class="control">
                                    <input class="input" type="text" id="buscar-proveedor" placeholder="Ingrese RUC o nombre" oninput="buscarProveedor()">
                                </div>
                                <!-- Lista de resultados -->
                                <div id="lista-proveedores" style="display: none;"></div>
                            </div>

                            <input type="hidden" id="id_proveedor" name="id_proveedor">

                            <!-- Nombre del Proveedor -->
                            <div class="field">
                                <label class="label">Nombre del Proveedor</label>
                                <div class="control">
                                    <input class="input" type="text" id="proveedor" placeholder="Nombre del proveedor" readonly>
                                </div>
                            </div>

                            <!-- RUC del Proveedor -->
                            <div class="field">
                                <label class="label">RUC del Proveedor</label>
                                <div class="control">
                                    <input class="input" type="text" id="ruc-proveedor" placeholder="RUC del proveedor" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Derecha -->
                        <div class="column">
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

                            <!-- Orden de Compra -->
                            <div class="field">
                                <label class="label">Orden de Compra (Opcional)</label>
                                <div class="control">
                                    <input class="input" type="text" id="orden-compra" placeholder="Número de orden de compra">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Agregar Productos -->
                <div class="box">
                    <h3 class="title is-4">Agregar Productos</h3>
                    <div class="columns">
                        <!-- Columna Izquierda -->
                        <div class="column">
                            <!-- Nombre del Producto -->
                            <div class="field">
                                <label class="label">Nombre del Producto</label>
                                <div class="control">
                                    <input class="input" type="text" id="nombre-producto" placeholder="Nombre del producto" >
                                </div>
                            </div>

                            <!-- Precio Unitario -->
                            <div class="field">
                                <label class="label">Precio Unitario</label>
                                <div class="control">
                                    <input class="input" type="number" id="precio-unitario" placeholder="Precio unitario" min="0" >
                                </div>
                            </div>
                        </div>

                        <!-- Columna Derecha -->
                        <div class="column">
                            <!-- Cantidad -->
                            <div class="field">
                                <label class="label">Cantidad</label>
                                <div class="control">
                                    <input class="input" type="number" id="cantidad" placeholder="Cantidad" min="1" >
                                </div>
                            </div>

                            <!-- Tipo de IVA -->
                            <div class="field">
                                <label class="label">Tipo de IVA</label>
                                <div class="control">
                                    <div class="select">
                                        <select id="tipo-iva">
                                            <option value="5">IVA 5%</option>
                                            <option value="10">IVA 10%</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Descuento y Botón -->
                        <div class="column">
                            <!-- Descuento -->
                            <div class="field">
                                <label class="label">Descuento (Opcional)</label>
                                <div class="control">
                                    <input class="input" type="number" id="descuento" placeholder="Descuento" min="0">
                                </div>
                            </div>

                            <!-- Botón para agregar producto -->
                            <div class="field">
                                <div class="control">
                                    <button type="button" class="button is-primary" onclick="agregarProducto()">Agregar Producto</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección 3: Tabla de Productos Agregados -->
                <div class="box">
                    <h3 class="title is-4">Productos Agregados</h3>
                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio Unitario</th>
                                <th>Cantidad</th>
                                <th>IVA 5%</th>
                                <th>IVA 10%</th>
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
                </div>

                <!-- Sección 4: Totales -->
                <div class="box">
                    <h3 class="title is-4">Totales</h3>
                    <div class="columns">
                        <div class="column">
                            <div class="field">
                                <label class="label">IVA 5%</label>
                                <div class="control">
                                    <input class="input" type="text" id="iva-5" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="column">
                            <div class="field">
                                <label class="label">IVA 10%</label>
                                <div class="control">
                                    <input class="input" type="text" id="iva-10" readonly>
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
                </div>

                <!-- Botón de Envío -->
                <div class="field">
                    <div class="control">
                        <button type="submit" class="button is-success is-large is-fullwidth">Guardar Factura</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

<!-- Contenedor para el toast -->
<div id="toast" class="is-hidden" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
    <div class="notification is-success">
        <button class="delete" onclick="cerrarToast()"></button>
        <p id="mensaje-toast"></p>
    </div>
</div>

    <!-- Vincula el archivo JavaScript -->
    <script src="../js/cargarfacturas.js"></script>
</body>
</html>