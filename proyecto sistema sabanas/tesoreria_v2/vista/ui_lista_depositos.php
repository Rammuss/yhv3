<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Depósitos</title>
    
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="../css/styles_T.css">
</head>
<body>
    <!-- Navbar -->
    <div id="navbar-container"></div>

    <section class="section">
        <div class="container">
            <h2 class="title is-3 has-text-centered">Consulta de Depósitos</h2>

            <!-- Filtros -->
            <div class="box">
                <form id="filter-form">
                    <div class="columns is-multiline">
                        <div class="column is-4">
                            <label class="label">Fecha Inicio</label>
                            <div class="control">
                                <input class="input" type="date" name="fecha_inicio" id="fecha_inicio">
                            </div>
                        </div>

                        <div class="column is-4">
                            <label class="label">Fecha Fin</label>
                            <div class="control">
                                <input class="input" type="date" name="fecha_fin" id="fecha_fin">
                            </div>
                        </div>

                        <div class="column is-4">
                            <label class="label">Estado</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="estado" id="estado">
                                        <option value="">Todos</option>
                                        <option value="Confirmado">Confirmado</option>
                                        <option value="Pendiente">Pendiente</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="has-text-centered">
                        <button type="submit" class="button is-primary is-medium">
                            <span class="icon">
                                <i class="fas fa-search"></i>
                            </span>
                            <span>Filtrar</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabla de resultados -->
            <div id="depositos-table" class="box">
                <?php include '../controlador/procesar_filtros_depositos.php'; ?> 
            </div>
        </div>
    </section>

    <!-- Script para Filtrado con AJAX -->
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

    <!-- Navbar Script -->
    <script src="../js/navbarT.js"></script>
</body>
</html>
