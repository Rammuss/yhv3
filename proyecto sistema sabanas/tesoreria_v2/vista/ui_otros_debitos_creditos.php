<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Otros Débitos y Créditos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.9.4/css/bulma.min.css">
</head>

<body>
    <section class="section">
        <div class="container">
            <h1 class="title">Cargar Otros Débitos y Créditos</h1>

            <form id="formOtrosMovimientos">
                <div class="field">
                    <label class="label">Fecha</label>
                    <div class="control">
                        <input class="input" type="date" name="fecha" required>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Descripción</label>
                    <div class="control">
                        <input class="input" type="text" name="descripcion" required>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Monto</label>
                    <div class="control">
                        <input class="input" type="number" step="0.01" name="monto" required>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Tipo (DEBITO Credito)</label>
                    <div class="control">
                        <div class="select">
                            <select name="tipo_credito_debito" required>
                                <option value="Debito">Débito</option>
                                <option value="Credito">Crédito</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Nuevo select para el tipo de movimiento: Interno o Bancario -->
                <div class="field">
                    <label class="label">Tipo de Movimiento (Interno o Bancario)</label>
                    <div class="control">
                        <div class="select">
                            <select name="tipo_movimiento" required>
                                <option value="INTERNAL">Interno</option>
                                <option value="BANK">Bancario</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Referencia</label>
                    <div class="control">
                        <input class="input" type="text" name="referencia">
                    </div>
                </div>

                <div class="field">
                    <label class="label">Banco</label>
                    <div class="control">
                        <div class="select">
                            <select id="selectBanco" name="banco_id" required>
                                <option value="">Cargando bancos...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="control">
                    <button class="button is-primary" type="submit">Guardar</button>
                </div>
            </form>

            <div id="mensaje" class="notification is-hidden"></div>
        </div>
    </section>

    <script src="../js/otros_deb_cred.js"></script>
</body>

</html>
