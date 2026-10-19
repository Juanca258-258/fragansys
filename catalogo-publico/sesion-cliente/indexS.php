<?php
// Activar reporte de errores durante desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar backend de la sesión del cliente
require_once __DIR__ . '/back_login_cliente.php';

// Si el cliente ya inició sesión, pasa directo a sus pedidos
if (clienteSesionActiva()) {
    header('Location: indexP.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Fragansys</title>
    <!-- CSS de Bootstrap 5 e Iconos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .font-serif { font-family: 'Georgia', 'Times New Roman', serif; }
        .text-brown { color: #4a3320 !important; }
        .text-gold { color: #b58c5a !important; }
        .bg-gold { background-color: #b58c5a !important; }
        .form-control:focus { border-color: #b58c5a; box-shadow: 0 0 0 0.2rem rgba(181, 140, 90, 0.2); }
    </style>
</head>
<body class="bg-light">

    <!-- Navbar Elegante -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <span class="rounded-circle text-white d-flex justify-content-center align-items-center" style="width: 35px; height: 35px; background-color: #4a3320; font-family: Georgia, serif;">F</span>
                <span class="ms-2 fs-4 font-serif text-brown">Fragansys</span>
            </a>

            <div class="ms-auto">
                <a href="../index.php" class="btn btn-outline-dark rounded-pill px-4 text-uppercase font-serif" style="font-size: 0.8rem; letter-spacing: 1px;">
                    <i class="bi bi-arrow-left me-1"></i> Volver al Catálogo
                </a>
            </div>
        </div>
    </nav>

    <!-- Vista Principal del Login -->
    <div class="container py-4">
        <?php include_once __DIR__ . '/modal_login_cliente.php'; ?>
    </div>

    <!-- Modales de gestión -->
    <?php include_once __DIR__ . '/modalg_login_cliente.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Funciones AJAX para interactuar con act_login_cliente.php -->
    <script>
        document.getElementById('btnVerPassword').addEventListener('click', function () {
            const input = document.getElementById('loginPassword');
            const icono = this.querySelector('i');
            const oculto = input.type === 'password';
            input.type = oculto ? 'text' : 'password';
            icono.classList.toggle('bi-eye', !oculto);
            icono.classList.toggle('bi-eye-slash', oculto);
        });

        document.getElementById('formLoginCliente').addEventListener('submit', function (e) {
            e.preventDefault();

            const alerta = document.getElementById('alertaLoginCliente');
            const btn = document.getElementById('btnLoginCliente');
            alerta.classList.add('d-none');

            const formData = new FormData(this);
            formData.append('accion', 'iniciar_sesion');

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Validando...';

            fetch('act_login_cliente.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = 'indexP.php';
                } else {
                    alerta.textContent = data.message;
                    alerta.classList.remove('d-none');
                    btn.disabled = false;
                    btn.innerHTML = 'Iniciar Sesión';
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alerta.textContent = 'Ocurrió un error al iniciar sesión. Intenta de nuevo.';
                alerta.classList.remove('d-none');
                btn.disabled = false;
                btn.innerHTML = 'Iniciar Sesión';
            });
        });
    </script>
</body>
</html>
