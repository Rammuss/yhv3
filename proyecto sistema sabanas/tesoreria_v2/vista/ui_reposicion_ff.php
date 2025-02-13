<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registrar Reposición de Fondo Fijo</title>
    <!-- Usamos Bulma para estilos -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="../css/styles_T.css">

</head>

<body>
<div id="navbar-container"></div>

    <section class="section">
        <div class="container">
            <h1 class="title">Registrar Reposición de Fondo Fijo</h1>

            <form id="reposicionForm" enctype="multipart/form-data">
                <!-- Seleccionar Rendición (se mostrará información relevante: total rendido, proveedor, etc.) -->
                <div class="field">
                    <label class="label">Rendición</label>
                    <div class="control">
                        <div class="select">
                            <select id="rendicion" name="rendicion_id" required>
                                <option value="">Seleccione una rendición</option>
                            </select>
                        </div>
                    </div>
                    <!-- Etiqueta para mostrar la diferencia -->
                    <p id="diferenciaLabel" class="help"></p>
                </div>

                <!-- Monto Repuesto -->
                <div class="field">
                    <label class="label">Monto Repuesto</label>
                    <div class="control">
                        <input class="input" type="number" step="0.01" name="monto_repuesto" placeholder="Ingrese el monto repuesto" required>
                    </div>
                </div>

                <!-- Fecha de Reposición -->
                <div class="field">
                    <label class="label">Fecha de Reposición</label>
                    <div class="control">
                        <input class="input" type="date" name="fecha_reposicion" required>
                    </div>
                </div>

                <!-- (Opcional) Subir comprobante de reposición -->
                <div class="field">
                    <label class="label">Comprobante (opcional)</label>
                    <div class="control">
                        <input class="input" type="file" name="comprobante" accept=".pdf, .jpg, .png">
                    </div>
                </div>

                <!-- Botón de envío -->
                <div class="field">
                    <div class="control">
                        <button class="button is-primary" type="submit">Registrar Reposición</button>
                    </div>
                </div>
            </form>

            <!-- Espacio para mensajes -->
            <div id="mensaje"></div>
        </div>
    </section>

    <script src="../js/reposicion.js"></script>
    <script src="../js/navbarT.js"></script>

</body>

</html>