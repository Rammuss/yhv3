<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Extracto Bancario</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Cargar Extracto Bancario</h1>
            <div class="box">
                <form id="formExtracto">
                    <div class="field">
                        <label class="label">Seleccionar Banco</label>
                        <div class="control">
                            <div class="select">
                                <select id="bancoSelect" name="banco_id" required>
                                    <option value="">Seleccione un banco</option>
                                    <!-- Opciones cargadas dinámicamente -->
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Cargar Archivo CSV</label>
                        <div class="control">
                            <input type="file" id="archivoExtracto" name="archivo" class="input" accept=".csv" required>
                        </div>
                    </div>
                    <div class="field">
                        <div class="control">
                            <button type="submit" class="button is-primary">Subir Extracto</button>
                        </div>
                    </div>
                </form>
                <div id="mensaje" class="notification is-hidden"></div>
            </div>
        </div>
    </section>
    
    <script src="../js/cargar_extracto_banco.js">
        
    </script>
</body>
</html>
