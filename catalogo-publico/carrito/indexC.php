<?php
// Activar reporte de errores durante desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar backend del carrito
require_once __DIR__ . '/back_carrito_pub.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - Fragansys</title>
    <!-- CSS de Bootstrap 5 e Iconos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .font-serif { font-family: 'Georgia', 'Times New Roman', serif; }
        .text-brown { color: #4a3320 !important; }
        .text-gold { color: #b58c5a !important; }
        .bg-gold { background-color: #b58c5a !important; }
        .btn-gold { background-color: #b58c5a !important; border-color: #b58c5a !important; }
        .btn-gold:hover { background-color: #9a7346 !important; }
        .bg-dark-card { background-color: #111111 !important; }
        
        .navbar-nav .nav-link { font-size: 0.85rem; letter-spacing: 1px; color: #555; }
        .separator-line {
            flex-grow: 1;
            height: 1px;
            background-color: #d8caca;
            margin-left: 15px;
        }
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

    <!-- Vista Principal del Carrito -->
    <div class="container py-4">
        <?php include_once __DIR__ . '/modal_carrito_pub.php'; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Funciones AJAX para interactuar con act_carrito_pub.php -->
    <script>
        function actualizarCantidad(productoId, cantidad) {
            const formData = new FormData();
            formData.append('accion', 'actualizar');
            formData.append('producto_id', productoId);
            formData.append('cantidad', cantidad);

            fetch('act_carrito_pub.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload(); // Recargar para reflejar totales
                } else {
                    alert(data.message);
                }
            })
            .catch(err => console.error('Error:', err));
        }

        function eliminarItem(productoId) {
            if (!confirm('¿Deseas eliminar esta fragancia de tu carrito?')) return;

            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('producto_id', productoId);

            fetch('act_carrito_pub.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(err => console.error('Error:', err));
        }

        // Checkout por pasos:
        //   'correo'    -> se busca si el correo ya está registrado
        //   'password'  -> cliente registrado: escribe su contraseña para usar sus datos
        //   'confirmar' -> se envía el pedido (cliente nuevo captura aquí sus datos y crea su contraseña)
        // Con sesión de cliente iniciada se empieza directo en 'confirmar' (sin correo ni contraseña)
        const btnCheckout = document.getElementById('btnConfirmarPedido');
        let pasoCheckout = (btnCheckout && btnCheckout.dataset.conSesion === '1') ? 'confirmar' : 'correo';

        function mostrarCampo(id, visible) {
            const bloque = document.getElementById(id);
            bloque.classList.toggle('d-none', !visible);
            bloque.querySelectorAll('input, textarea').forEach(el => el.required = visible);
        }

        function ocultarPasos() {
            document.getElementById('bloqueClienteExistente').classList.add('d-none');
            document.getElementById('bloqueConfirmacion').classList.add('d-none');
            ['campoNombre', 'campoTelefono', 'campoDireccion', 'campoPassword', 'campoConfirmarPassword']
                .forEach(id => mostrarCampo(id, false));
        }

        function cambiarCorreo() {
            pasoCheckout = 'correo';
            const correo = document.getElementById('checkoutCorreo');
            correo.readOnly = false;
            correo.focus();
            document.getElementById('btnCambiarCorreo').classList.add('d-none');
            document.getElementById('ayudaCorreo').classList.remove('d-none');
            document.getElementById('checkoutPassword').value = '';
            document.querySelector('[name="password_confirmar"]').value = '';
            ocultarPasos();
            document.getElementById('btnConfirmarPedido').textContent = 'Continuar';
        }

        function enviarCheckout(accion, formData, mensajeError) {
            formData.append('accion', accion);
            return fetch('act_carrito_pub.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .catch(err => {
                console.error('Error:', err);
                return { status: 'error', message: mensajeError };
            });
        }

        function buscarCliente(btn) {
            const formData = new FormData();
            formData.append('cliente_correo', document.getElementById('checkoutCorreo').value);

            enviarCheckout('buscar_cliente', formData, 'Ocurrió un error al verificar el correo.')
            .then(data => {
                btn.disabled = false;
                if (data.status !== 'success') {
                    alert(data.message);
                    return;
                }

                document.getElementById('checkoutCorreo').readOnly = true;
                document.getElementById('btnCambiarCorreo').classList.remove('d-none');
                document.getElementById('ayudaCorreo').classList.add('d-none');

                if (data.existe) {
                    pasoCheckout = 'password';
                    if (data.sin_password) {
                        document.getElementById('etiquetaPassword').textContent = 'Crea una contraseña *';
                        document.getElementById('ayudaPassword').textContent = 'Tu cuenta aún no tiene contraseña. Crea una (mínimo 6 caracteres) para usar tus datos guardados.';
                    } else {
                        document.getElementById('etiquetaPassword').textContent = 'Contraseña *';
                        document.getElementById('ayudaPassword').textContent = 'Este correo ya está registrado. Ingresa tu contraseña para usar tus datos.';
                    }
                    mostrarCampo('campoPassword', true);
                    mostrarCampo('campoConfirmarPassword', data.sin_password);
                    btn.textContent = 'Continuar';
                } else {
                    pasoCheckout = 'confirmar';
                    document.getElementById('etiquetaPassword').textContent = 'Crea una contraseña *';
                    document.getElementById('ayudaPassword').textContent = 'Mínimo 6 caracteres. La usarás en tus próximas compras.';
                    ['campoNombre', 'campoTelefono', 'campoDireccion', 'campoPassword', 'campoConfirmarPassword']
                        .forEach(id => mostrarCampo(id, true));
                    document.getElementById('bloqueConfirmacion').classList.remove('d-none');
                    btn.textContent = 'Confirmar Pedido';
                }
            });
        }

        function verificarCliente(btn) {
            const formData = new FormData();
            formData.append('cliente_correo', document.getElementById('checkoutCorreo').value);
            formData.append('password', document.getElementById('checkoutPassword').value);
            formData.append('password_confirmar', document.querySelector('[name="password_confirmar"]').value);

            enviarCheckout('verificar_cliente', formData, 'Ocurrió un error al verificar la contraseña.')
            .then(data => {
                btn.disabled = false;
                if (data.status !== 'success') {
                    alert(data.message);
                    return;
                }

                pasoCheckout = 'confirmar';
                mostrarCampo('campoPassword', false);
                mostrarCampo('campoConfirmarPassword', false);
                document.getElementById('resumenNombre').textContent = data.nombre;
                document.getElementById('resumenTelefono').textContent = data.telefono;
                document.getElementById('resumenDireccion').textContent = data.direccion;
                document.getElementById('resumenTelefonoFila').classList.toggle('d-none', data.falta_telefono);
                document.getElementById('resumenDireccionFila').classList.toggle('d-none', data.falta_direccion);
                document.getElementById('bloqueClienteExistente').classList.remove('d-none');
                mostrarCampo('campoTelefono', data.falta_telefono);
                mostrarCampo('campoDireccion', data.falta_direccion);
                document.getElementById('bloqueConfirmacion').classList.remove('d-none');
                btn.textContent = 'Confirmar Pedido';
            });
        }

        function realizarPedido(e) {
            e.preventDefault();
            const btn = document.getElementById('btnConfirmarPedido');
            btn.disabled = true;

            if (pasoCheckout === 'correo') {
                buscarCliente(btn);
                return;
            }
            if (pasoCheckout === 'password') {
                verificarCliente(btn);
                return;
            }

            enviarCheckout('realizar_pedido', new FormData(e.target), 'Ocurrió un error al registrar el pedido.')
            .then(data => {
                alert(data.message);
                if (data.status === 'success') {
                    // Con sesión, el cliente va a sus pedidos para darle seguimiento
                    location.href = btn.dataset.conSesion === '1' ? '../sesion-cliente/indexP.php' : '../index.php';
                } else {
                    btn.disabled = false;
                }
            });
        }
    </script>
</body>
</html>