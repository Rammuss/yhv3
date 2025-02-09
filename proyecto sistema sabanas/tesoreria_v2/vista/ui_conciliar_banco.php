<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conciliar Extracto Bancario</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Conciliar Extracto Bancario</h1>
            <form id="formConciliacion">
                <div class="field">
                    <label class="label">Fecha Inicio</label>
                    <div class="control">
                        <input type="date" name="fecha_inicio" class="input" required>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Fecha Fin</label>
                    <div class="control">
                        <input type="date" name="fecha_fin" class="input" required>
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <button type="submit" class="button is-primary">Conciliar</button>
                    </div>
                </div>
            </form>
            <div id="resultadosConciliacion" class="box is-hidden">
                <h2 class="subtitle">Transacciones Conciliadas</h2>
                <table class="table is-fullwidth">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody id="transaccionesConciliadas">
                        <!-- Transacciones conciliadas se mostrarán aquí -->
                    </tbody>
                </table>
                <h2 class="subtitle">Transacciones No Conciliadas</h2>
                <table class="table is-fullwidth">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody id="transaccionesNoConciliadas">
                        <!-- Transacciones no conciliadas se mostrarán aquí -->
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <script src="../js/conciliar_banco.js"></script>
</body>
</html>
