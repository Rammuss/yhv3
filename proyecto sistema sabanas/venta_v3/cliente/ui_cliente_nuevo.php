<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header("Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html");
    exit;
}

$nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Beauty Creations | Gestión de Clientas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: linear-gradient(135deg, #fff0f6 0%, #ffe3f3 40%, #fef9ff 100%);
            --navbar-bg: rgba(255, 255, 255, 0.88);
            --navbar-border: rgba(214, 51, 132, 0.22);
            --card-bg: rgba(255, 255, 255, 0.92);
            --shadow: 0 28px 48px rgba(188, 70, 137, 0.18);
            --text: #411f31;
            --muted: #9d6f8b;
            --accent: #d63384;
            --accent-soft: rgba(214, 51, 132, 0.12);
            --accent-alt: #7f5dff;
            --radius: 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px 24px 40px;
            min-height: 100vh;
            font-family: "Poppins", system-ui, -apple-system, Segoe UI, sans-serif;
            color: var(--text);
            background: var(--bg);
            position: relative;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            filter: blur(150px);
            z-index: 0;
            opacity: .55;
        }

        body::before {
            top: -160px;
            left: -160px;
            background: rgba(214, 51, 132, 0.32);
        }

        body::after {
            bottom: -200px;
            right: -140px;
            background: rgba(127, 93, 255, 0.26);
        }

        .app {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding: 16px 24px;
            background: var(--navbar-bg);
            border-radius: 999px;
            border: 1px solid var(--navbar-border);
            box-shadow: 0 18px 32px rgba(64, 21, 53, 0.16);
            backdrop-filter: blur(12px);
            margin-bottom: 32px;
        }

        .navbar__brand {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .navbar__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: .78rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--accent);
            font-weight: 600;
        }

        .navbar__badge::before,
        .navbar__badge::after {
            content: "";
            width: 16px;
            height: 1px;
            background: currentColor;
            opacity: .6;
        }

        .navbar__title {
            margin: 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 2rem;
        }

        .navbar__actions {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 600;
            letter-spacing: .4px;
            cursor: pointer;
            background: linear-gradient(135deg, var(--accent), var(--accent-alt));
            color: #fff;
            box-shadow: 0 12px 24px rgba(214, 51, 132, 0.26);
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(214, 51, 132, 0.28);
        }

        .btn-outline {
            background: transparent;
            color: var(--text);
            border: 1px solid rgba(214, 51, 132, 0.18);
            box-shadow: none;
        }

        main {
            display: grid;
            gap: 22px;
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid rgba(214, 51, 132, 0.16);
            box-shadow: var(--shadow);
            padding: 26px 30px;
        }

        .card h2 {
            margin: 0;
            font-family: "Playfair Display", "Poppins", serif;
            font-size: 1.8rem;
        }

        .card p {
            margin: 8px 0 20px;
            color: var(--muted);
        }

        form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(214, 51, 132, 0.22);
            background: rgba(255, 255, 255, 0.95);
            font-size: .98rem;
            transition: border-color .18s ease, box-shadow .18s ease;
            color: var(--text);
        }

        input:focus {
            outline: none;
            border-color: rgba(214, 51, 132, 0.6);
            box-shadow: 0 0 0 4px rgba(214, 51, 132, 0.16);
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .table-card {
            padding: 26px 24px 32px;
        }

        .table-header {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .search {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .search input {
            padding-left: 42px;
        }

        .search::before {
            content: "🔍";
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            opacity: .6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 18px;
        }

        th,
        td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(214, 51, 132, 0.12);
        }

        th {
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .6px;
            font-size: .9rem;
            background: rgba(214, 51, 132, 0.12);
        }

        tbody tr {
            background: rgba(255, 255, 255, 0.82);
        }

        tbody tr:hover {
            background: rgba(214, 51, 132, 0.08);
        }

        .actions {
            display: inline-flex;
            gap: 8px;
        }

        .btn-icon {
            border: none;
            border-radius: 12px;
            padding: 8px 12px;
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            background: var(--accent-soft);
            color: var(--accent);
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(214, 51, 132, 0.22);
        }

        .btn-danger {
            background: rgba(255, 84, 110, 0.15);
            color: #d22c54;
        }

        .empty-state {
            text-align: center;
            padding: 32px 10px;
            color: var(--muted);
            font-size: 1rem;
        }

        /* Modal */
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(20, 10, 26, 0.45);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 999;
        }

        .modal.open {
            display: flex;
        }

        .modal__content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 22px;
            padding: 28px;
            width: min(520px, 100%);
            box-shadow: 0 28px 54px rgba(64, 21, 53, 0.32);
            border: 1px solid rgba(214, 51, 132, 0.18);
        }

        .modal__content h3 {
            margin: 0 0 18px;
            font-family: "Playfair Display", "Poppins", serif;
        }

        .modal__actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 18px;
        }

        /* Toast */
        .toast {
            position: fixed;
            top: 22px;
            right: 22px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text);
            padding: 14px 20px;
            border-radius: 18px;
            box-shadow: 0 24px 48px rgba(64, 21, 53, 0.26);
            font-size: .95rem;
            opacity: 0;
            transform: translateY(-16px);
            transition: opacity .3s ease, transform .3s ease;
            z-index: 1000;
            border-left: 5px solid var(--accent);
            max-width: min(380px, 90vw);
            pointer-events: none;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast.error {
            border-left-color: #e74c3c;
        }

        @media (max-width: 720px) {
            body {
                padding: 24px 16px 32px;
            }

            .navbar {
                flex-direction: column;
                align-items: flex-start;
                border-radius: 26px;
            }

            .navbar__actions {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            form {
                grid-template-columns: 1fr;
            }

            table {
                font-size: .9rem;
            }

            th,
            td {
                padding: 12px 14px;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="app">
        <header class="navbar">
            <div class="navbar__brand">
                <span class="navbar__badge">Beauty Creations</span>
                <h1 class="navbar__title">Gestión de clientas</h1>
                <span style="color:var(--muted); font-size:.95rem;">Alta rápida, edición y búsqueda de clientas del salón.</span>
            </div>
            <div class="navbar__actions">
                <span style="font-weight:500; color:var(--muted);">Hola, <?= htmlspecialchars($nombre) ?> ✨</span>
                <a class="btn btn-outline" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/dashboard/dashboardv1.php">Dashboard</a>
                <a class="btn btn-outline" href="javascript:history.back()">Volver</a>
                <form method="post" action="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/logout.php" style="margin:0;">
                    <button class="btn" type="submit">Cerrar sesión</button>
                </form>
            </div>
        </header>

        <main>
            <section class="card">
                <h2>Alta de clienta</h2>
                <p>Completá los datos para registrar una nueva clienta en el sistema.</p>
                <form id="form-create" autocomplete="off">
                    <div>
                        <label for="nombre">Nombre</label>
                        <input id="nombre" name="nombre" maxlength="50" required placeholder="María">
                    </div>
                    <div>
                        <label for="apellido">Apellido</label>
                        <input id="apellido" name="apellido" maxlength="50" required placeholder="Gómez">
                    </div>
                    <div>
                        <label for="ruc_ci">RUC / CI</label>
                        <input id="ruc_ci" name="ruc_ci" maxlength="15" placeholder="1234567-0">
                    </div>
                    <div>
                        <label for="telefono">Teléfono</label>
                        <input id="telefono" name="telefono" maxlength="20" placeholder="+595 98x xxx xxx">
                    </div>
                    <div style="grid-column:1 / -1;">
                        <label for="direccion">Dirección</label>
                        <input id="direccion" name="direccion" maxlength="100" placeholder="Barrio Carmelitas, Asunción">
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-outline" type="reset">Limpiar</button>
                        <button class="btn" type="submit">Guardar clienta</button>
                    </div>
                </form>
            </section>

            <section class="card table-card">
                <div class="table-header">
                    <h2 style="margin:0; font-size:1.5rem;">Listado de clientas</h2>
                    <div class="search">
                        <input id="search" type="search" placeholder="Buscar por nombre, apellido o RUC…">
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Apellido</th>
                                <th>RUC / CI</th>
                                <th>Teléfono</th>
                                <th>Dirección</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="clientes-body">
                            <tr>
                                <td colspan="7" class="empty-state">Cargando clientas…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <div class="modal" id="modal-edit" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal__content">
            <h3 id="modal-title">Editar clienta</h3>
            <form id="form-edit">
                <input type="hidden" id="edit-id">
                <div>
                    <label for="edit-nombre">Nombre</label>
                    <input id="edit-nombre" maxlength="50" required>
                </div>
                <div>
                    <label for="edit-apellido">Apellido</label>
                    <input id="edit-apellido" maxlength="50" required>
                </div>
                <div>
                    <label for="edit-ruc_ci">RUC / CI</label>
                    <input id="edit-ruc_ci" maxlength="15">
                </div>
                <div>
                    <label for="edit-telefono">Teléfono</label>
                    <input id="edit-telefono" maxlength="20">
                </div>
                <div style="grid-column:1 / -1;">
                    <label for="edit-direccion">Dirección</label>
                    <input id="edit-direccion" maxlength="100">
                </div>
                <div class="modal__actions">
                    <button type="button" class="btn btn-outline" data-close>Cancelar</button>
                    <button type="submit" class="btn">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        const API_URL = 'clientes_api.php';
        const tbody = document.getElementById('clientes-body');
        const toast = document.getElementById('toast');
        const modal = document.getElementById('modal-edit');

        let clientes = [];
        let filtro = '';

        function showToast(message, type = 'ok') {
            toast.textContent = message;
            toast.className = `toast show ${type === 'error' ? 'error' : ''}`;
            setTimeout(() => toast.classList.remove('show'), 3200);
        }

        async function fetchClientes() {
            try {
                const res = await fetch(`${API_URL}?q=${encodeURIComponent(filtro)}`);
                if (!res.ok) throw new Error('No se pudieron obtener las clientas');
                clientes = await res.json();
                renderTabla();
            } catch (err) {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="7" class="empty-state">Error al cargar: ${err.message}</td></tr>`;
            }
        }

        function renderTabla() {
            if (!clientes.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="empty-state">No hay clientas registradas.</td></tr>`;
                return;
            }

            tbody.innerHTML = clientes.map(cli => `
                <tr>
                    <td>${cli.id_cliente}</td>
                    <td>${cli.nombre ?? ''}</td>
                    <td>${cli.apellido ?? ''}</td>
                    <td>${cli.ruc_ci ?? ''}</td>
                    <td>${cli.telefono ?? ''}</td>
                    <td>${cli.direccion ?? ''}</td>
                    <td style="text-align:center;">
                        <div class="actions">
                            <button class="btn-icon" data-action="edit" data-id="${cli.id_cliente}">Editar</button>
                            <button class="btn-icon btn-danger" data-action="delete" data-id="${cli.id_cliente}">Eliminar</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        document.getElementById('form-create').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.currentTarget;
            const formData = new FormData(form);

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo guardar');
                form.reset();
                showToast('Clienta creada correctamente.');
                fetchClientes();
            } catch (err) {
                showToast(err.message, 'error');
            }
        });

        tbody.addEventListener('click', async (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;

            const id = btn.dataset.id;
            const cliente = clientes.find(c => String(c.id_cliente) === String(id));

            if (!cliente) return;

            if (btn.dataset.action === 'edit') {
                openModal(cliente);
            } else if (btn.dataset.action === 'delete') {
                const confirmar = confirm(`¿Eliminar a ${cliente.nombre} ${cliente.apellido}?`);
                if (!confirmar) return;
                try {
                    const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo eliminar');
                    showToast('Clienta eliminada.');
                    fetchClientes();
                } catch (err) {
                    showToast(err.message, 'error');
                }
            }
        });

        function openModal(cliente) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            document.getElementById('edit-id').value = cliente.id_cliente;
            document.getElementById('edit-nombre').value = cliente.nombre ?? '';
            document.getElementById('edit-apellido').value = cliente.apellido ?? '';
            document.getElementById('edit-ruc_ci').value = cliente.ruc_ci ?? '';
            document.getElementById('edit-telefono').value = cliente.telefono ?? '';
            document.getElementById('edit-direccion').value = cliente.direccion ?? '';
        }

        function closeModal() {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }

        modal.addEventListener('click', (e) => {
            if (e.target.dataset.close !== undefined || e.target === modal) {
                closeModal();
            }
        });

        document.getElementById('form-edit').addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('edit-id').value;
            const payload = {
                nombre: document.getElementById('edit-nombre').value,
                apellido: document.getElementById('edit-apellido').value,
                ruc_ci: document.getElementById('edit-ruc_ci').value,
                telefono: document.getElementById('edit-telefono').value,
                direccion: document.getElementById('edit-direccion').value
            };

            try {
                const res = await fetch(`${API_URL}?id=${id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo actualizar');
                closeModal();
                showToast('Datos actualizados.');
                fetchClientes();
            } catch (err) {
                showToast(err.message, 'error');
            }
        });

        let searchTimeout;
        document.getElementById('search').addEventListener('input', (e) => {
            const value = e.target.value.trim();
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filtro = value;
                fetchClientes();
            }, 350);
        });

        fetchClientes();
    </script>
</body>

</html>
