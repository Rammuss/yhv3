<?php
session_start();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apertura y Cierre de Caja</title>

    <link rel="stylesheet" href="styles_venta.css"> <!-- Enlace a tu archivo CSS -->
    <script src="navbar.js"></script>

    <style>
        /* General */
        body {
            font-family: Arial, sans-serif;

            background-color: #f4f4f9;
        }

        * {
            box-sizing: border-box;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
        }

        h2 {
            color: #555;
        }

        /* Formularios */
        form {
            margin: 1rem 0;
        }

        form label {
            display: block;
            margin: 0.5rem 0 0.2rem;
            color: #333;
        }

        form input {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        form button {
            padding: 0.7rem 1.5rem;
            background-color: #007BFF;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        form button:hover {
            background-color: #0056b3;
        }

        /* Secciones */
        section {
            margin-bottom: 2rem;
        }



        .modal {
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            text-align: center;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<div id="navbar-container"></div>

<body>
    <div class="container">
        <h1>Gestión de Caja</h1>

        <!-- Formulario de Apertura -->
        <section id="apertura-caja">
            <h2>Apertura de Caja</h2>
            <form id="form-abrir-caja">
                <label for="monto_inicial">Monto Inicial:</label>
                <input type="number" id="monto_inicial" name="monto_inicial" placeholder="Ingrese el monto inicial" required>
                <input type="hidden" name="usuario" value="1"> <!-- ID del usuario logueado -->
                <button type="submit">Abrir Caja</button>
            </form>
        </section>

        <!-- Formulario de Cierre -->
        <section id="cierre-caja">
            <h2>Cierre de Caja</h2>
            <form id="form-cerrar-caja">


                <label for="monto_final">Monto Final:</label>
                <input type="number" id="monto_final" name="monto_final" placeholder="Ingrese el monto final" required>

                <button type="submit">Cerrar Caja</button>
                <!-- Botón para mostrar el Pre-Arqueo -->
                <button type="button" id="btnPreArqueo" class="btn btn-primary">Mostrar Pre-Arqueo</button>
            </form>
        </section>
    </div>




    <!-- Modal para Pre-Arqueo -->
    <div id="modalPreArqueo" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" id="closeModal">&times;</span>
            <h2>Pre-Arqueo de Caja</h2>
            <p>Monto Inicial: <span id="montoInicial"></span></p>
            <p>Total Ventas: <span id="ventasEfectivo"></span></p>
            <p>Monto Esperado: <span id="montoEsperado"></span></p>
            <h3>Detalle de Métodos de Pago</h3>
            <ul id="metodosPagoList"></ul>
            <h3>Notas de Crédito</h3>
            <p>Total Notas de Crédito: <span id="totalNotasCredito"></span></p>
        </div>
    </div>



    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const btnPreArqueo = document.getElementById("btnPreArqueo");
            const modalPreArqueo = document.getElementById("modalPreArqueo");
            const closeModal = document.getElementById("closeModal");

            btnPreArqueo.addEventListener("click", function() {
                fetch("../venta_v2/calcular_pre_arqueo.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                        } else {
                            // Mostrar los datos principales
                            document.getElementById("montoInicial").textContent =
                                data.monto_inicial.toLocaleString('es-ES', {
                                    style: 'currency',
                                    currency: 'PYG'
                                });

                            document.getElementById("ventasEfectivo").textContent =
                                data.total_ventas.toLocaleString('es-ES', {
                                    style: 'currency',
                                    currency: 'PYG'
                                });

                            document.getElementById("montoEsperado").textContent =
                                data.monto_esperado.toLocaleString('es-ES', {
                                    style: 'currency',
                                    currency: 'PYG'
                                });

                            // Construir la lista de métodos de pago
                            const metodosPagoList = document.getElementById("metodosPagoList");
                            metodosPagoList.innerHTML = ""; // Limpiar contenido previo
                            for (const [metodo, total] of Object.entries(data.metodos_pago)) {
                                const li = document.createElement("li");
                                li.textContent = `${metodo.charAt(0).toUpperCase() + metodo.slice(1)}: ${total.toLocaleString('es-ES', {
                        style: 'currency',
                        currency: 'PYG'
                    })}`;
                                metodosPagoList.appendChild(li);
                            }

                            // Mostrar total de notas de crédito
                            document.getElementById("totalNotasCredito").textContent =
                                data.total_notas_credito.toLocaleString('es-ES', {
                                    style: 'currency',
                                    currency: 'PYG'
                                });

                            // Mostrar el modal
                            modalPreArqueo.style.display = "block";
                        }
                    })
                    .catch(error => {
                        console.error("Error al calcular el pre-arqueo:", error);
                        alert("Error al calcular el pre-arqueo. Intente nuevamente.");
                    });
            });

            closeModal.addEventListener("click", function() {
                modalPreArqueo.style.display = "none";
            });
        });
    </script>


    <script src="scripts.js"></script> <!-- Enlace a tu archivo JS -->
</body>
<script>
    // Abrir Caja
    document.getElementById('form-abrir-caja').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);

        const response = await fetch('../venta_v2/abrir_caja.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.text();
        alert(result);
        if (result.includes("correctamente")) {
            e.target.reset(); // Limpiar el formulario si fue exitoso
        }
    });

    // Cerrar Caja
    document.getElementById('form-cerrar-caja').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);

        const response = await fetch('../venta_v2/cerrar_caja.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.text();
        alert(result);
        if (result.includes("correctamente")) {
            e.target.reset(); // Limpiar el formulario si fue exitoso

            // Redirigir a la página de recaudaciones a depositar 
            // 
            window.location.href = '../venta_v2/recaudaciones_a_depositar.php';
        }
    });
</script>

</html>