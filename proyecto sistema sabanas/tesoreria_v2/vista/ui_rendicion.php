<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Rendición</title>
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>

<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Registrar Rendición de Fondo Fijo</h1>
            <form id="rendicionForm" enctype="multipart/form-data">
                <!-- Selección de Asignación (mostrar proveedor y RUC) -->
                <div class="field">
                    <label class="label">Asignación</label>
                    <div class="control">
                        <div class="select">
                            <select id="asignacion" name="asignacion" required>
                                <option value="">Seleccione una asignación</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fecha de Rendición -->
                <div class="field">
                    <label class="label">Fecha de Rendición</label>
                    <div class="control">
                        <input class="input" type="date" name="fecha_rendicion" required>
                    </div>
                </div>

                <!-- Total Rendido (se calcula automáticamente a partir del detalle) -->
                <div class="field">
                    <label class="label">Total Rendido</label>
                    <div class="control">
                        <input class="input" type="number" step="0.01" name="total_rendido" id="total_rendido" placeholder="Se calculará automáticamente" readonly>
                    </div>
                </div>


                <!-- Documento adjunto (opcional) -->
                <div class="field">
                    <label class="label">Subir Comprobante</label>
                    <div class="control">
                        <input class="input" type="file" name="documento" accept=".pdf, .jpg, .png">
                    </div>
                </div>

                <!-- Detalles de la Rendición -->
                <h2 class="subtitle">Detalles de Gastos</h2>
                <table class="table is-fullwidth" id="detalleTable">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Fecha Gasto</th>
                            <th>Documento Asociado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Se agregarán filas dinámicamente -->
                    </tbody>
                </table>
                <div class="field">
                    <div class="control">
                        <button type="button" id="agregarDetalle" class="button is-info">Agregar Gasto</button>
                    </div>
                </div>

                <!-- Botón de envío -->
                <div class="field">
                    <div class="control">
                        <button type="submit" class="button is-primary">Registrar Rendición</button>
                    </div>
                </div>
            </form>

            <!-- Espacio para mensajes -->
            <div id="mensaje"></div>
        </div>
    </section>

    <script src="../js/rendicion.js"></script>
</body>

</html>