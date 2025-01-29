<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregar Cheques</title>
    <link href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/script.js" defer></script> <!-- Archivo JS -->
</head>
<body>

<section class="section">
    <div class="container">
        <h1 class="title">Lista de Cheques Pendientes</h1>
        <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
            <thead>
                <tr>
                    <th>ID Cheque</th>
                    <th>Beneficiario</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Fecha de Entrega</th>
                </tr>
            </thead>
            <tbody id="chequesPendientesTable">
                <!-- Las filas de cheques se cargarán aquí mediante JavaScript -->
            </tbody>
        </table>
    </div>
</section>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/js/entrega_cheques.js"></script>
</body>
</html>
