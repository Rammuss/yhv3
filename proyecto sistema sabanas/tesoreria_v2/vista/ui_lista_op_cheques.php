<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Órdenes de Pago</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.3/css/bulma.min.css">
    <style>
        .btn-imprimir {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            cursor: pointer;
            text-align: center;
        }
        .btn-imprimir:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="title is-2">Órdenes de Pago</h2>

        <!-- Tabla para mostrar las órdenes de pago -->
        <table class="table is-striped is-fullwidth">
            <thead>
                <tr>
                    <th>Orden de Pago</th>
                    <th>Proveedor</th>
                    <th>Numero cheque</th>
                    <th>Monto Total</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="ordenesPagoTable">
                <!-- Las filas de las órdenes de pago se llenarán dinámicamente -->
            </tbody>
        </table>
    </div>

    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/js/lista_op_cheque.js"></script>
</body>
</html>
