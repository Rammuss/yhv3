<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Depósitos</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: center; }
        th { background-color: #f4f4f4; }
        input, select, button { padding: 5px; margin: 5px; }
    </style>
</head>
<body>

    <h2>Consulta de Depósitos</h2>

    <!-- Filtros -->
    <form id="filter-form">
        <label>Fecha Inicio: <input type="date" name="fecha_inicio" id="fecha_inicio"></label>
        <label>Fecha Fin: <input type="date" name="fecha_fin" id="fecha_fin"></label>
        <label>Estado:
            <select name="estado" id="estado">
                <option value="">Todos</option>
                <option value="Confirmado">Confirmado</option>
                <option value="Pendiente">Pendiente</option>
            </select>
        </label>
        <button type="submit">Filtrar</button>
    </form>

    <!-- Tabla de resultados -->
    <div id="depositos-table">
        <?php include '../controlador/procesar_filtros_depositos.php'; ?> 
    </div>

    <script>
        $(document).ready(function(){
            $("#filter-form").on("submit", function(event){
                event.preventDefault();
                $.ajax({
                    url: "../controlador/procesar_filtros_depositos.php",
                    type: "GET",
                    data: $(this).serialize(),
                    success: function(response){
                        $("#depositos-table").html(response);
                    }
                });
            });
        });
    </script>

</body>
</html>
