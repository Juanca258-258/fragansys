<?php
// Activar reporte de errores durante desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar backend de los pedidos del cliente
require_once __DIR__ . '/back_pedidos_cliente.php';

// Solo clientes con sesión iniciada
if (!clienteSesionActiva()) {
    header('Location: indexS.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - Fragansys</title>
    <!-- CSS de Bootstrap 5 e Iconos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .font-serif { font-family: 'Georgia', 'Times New Roman', serif; }
        .text-brown { color: #4a3320 !important; }
        .text-gold { color: #b58c5a !important; }
        .bg-gold { background-color: #b58c5a !important; }

        /* Línea de tiempo del seguimiento */
        .seguimiento-pasos { display: flex; justify-content: space-between; position: relative; }
        .seguimiento-pasos .paso { flex: 1; text-align: center; position: relative; z-index: 1; }
        .seguimiento-pasos .paso::before {
            content: ''; position: absolute; top: 21px; right: 50%; width: 100%; height: 3px;
            background-color: #e6ddd2; z-index: -1;
        }
        .seguimiento-pasos .paso:first-child::before { display: none; }
        .seguimiento-pasos .paso-icono {
            width: 44px; height: 44px; margin: 0 auto 6px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background-color: #fff; border: 3px solid #e6ddd2; color: #c2b6a8; font-size: 1.1rem;
        }
        .seguimiento-pasos .paso-texto { font-size: 0.75rem; color: #9a8f84; text-transform: uppercase; letter-spacing: 0.5px; }
        .seguimiento-pasos .paso.completado::before,
        .seguimiento-pasos .paso.actual::before { background-color: #b58c5a; }
        .seguimiento-pasos .paso.completado .paso-icono { background-color: #b58c5a; border-color: #b58c5a; color: #fff; }
        .seguimiento-pasos .paso.actual .paso-icono { background-color: #4a3320; border-color: #4a3320; color: #fff; box-shadow: 0 0 0 5px rgba(181, 140, 90, 0.25); }
        .seguimiento-pasos .paso.completado .paso-texto { color: #4a3320; }
        .seguimiento-pasos .paso.actual .paso-texto { color: #4a3320; font-weight: 700; }

        @media (max-width: 576px) {
            .seguimiento-pasos .paso-icono { width: 34px; height: 34px; font-size: 0.9rem; border-width: 2px; }
            .seguimiento-pasos .paso::before { top: 16px; }
            .seguimiento-pasos .paso-texto { font-size: 0.6rem; }
        }

        /* Resalta el pedido cuyo seguimiento acaba de cambiar */
        .tarjeta-pedido.actualizado { animation: resaltar 2.5s ease-out; }
        @keyframes resaltar { 0% { box-shadow: 0 0 0 4px rgba(181, 140, 90, 0.6); } 100% { box-shadow: 0 0 0 0 rgba(181, 140, 90, 0); } }
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

            <div class="ms-auto d-flex gap-2">
                <a href="../index.php" class="btn btn-outline-dark rounded-pill px-3 px-md-4 text-uppercase font-serif" style="font-size: 0.8rem; letter-spacing: 1px;">
                    <i class="bi bi-arrow-left me-1"></i><span class="d-none d-md-inline"> Volver al Catálogo</span>
                </a>
                <button type="button" class="btn btn-dark rounded-pill px-3 px-md-4 text-uppercase font-serif" style="font-size: 0.8rem; letter-spacing: 1px; background-color: #4a3320; border: none;" onclick="cerrarSesionCliente()">
                    <i class="bi bi-box-arrow-right me-1"></i><span class="d-none d-md-inline"> Cerrar Sesión</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Vista Principal de los Pedidos -->
    <div class="container py-4">
        <?php include_once __DIR__ . '/modal_pedidos_cliente.php'; ?>
    </div>

    <!-- Modales de gestión -->
    <?php include_once __DIR__ . '/modalg_pedidos_cliente.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Funciones AJAX para interactuar con act_pedidos_cliente.php -->
    <script>
        const INTERVALO_SEGUIMIENTO = 30000; // Cada 30 segundos se revisa si cambió algún pedido
        let pedidoACancelar = null;

        function escaparHTML(texto) {
            const div = document.createElement('div');
            div.textContent = texto == null ? '' : String(texto);
            return div.innerHTML;
        }

        function formatoDinero(valor) {
            return '$' + Number(valor).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function enviarAccion(datos) {
            const formData = new FormData();
            Object.keys(datos).forEach(k => formData.append(k, datos[k]));

            return fetch('act_pedidos_cliente.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.sin_sesion) {
                        window.location.href = 'indexS.php';
                    }
                    return data;
                });
        }

        function mostrarAviso(texto) {
            document.getElementById('toastPedidoTexto').textContent = texto;
            bootstrap.Toast.getOrCreateInstance(document.getElementById('toastPedido')).show();
        }

        // Revisa los pedidos y marca en pantalla los que cambiaron de estado
        function revisarSeguimiento() {
            enviarAccion({ accion: 'estados' })
                .then(data => {
                    if (data.status !== 'success') return;

                    let cambios = 0;
                    data.pedidos.forEach(p => {
                        const contenedor = document.getElementById('seguimiento-' + p.id);
                        if (!contenedor) {
                            cambios++; // Pedido nuevo que no estaba en pantalla
                            return;
                        }
                        if (contenedor.dataset.firma !== p.firma) {
                            contenedor.innerHTML = p.html;
                            contenedor.dataset.firma = p.firma;

                            const tarjeta = document.getElementById('pedido-' + p.id);
                            tarjeta.classList.remove('actualizado');
                            void tarjeta.offsetWidth; // Reinicia la animación
                            tarjeta.classList.add('actualizado');
                            cambios++;
                        }
                    });

                    if (cambios > 0) {
                        mostrarAviso(cambios === 1 ? 'Uno de tus pedidos tiene una actualización.' : 'Algunos de tus pedidos tienen actualizaciones.');
                    }
                    // Si llegó un pedido nuevo, se recarga para mostrar su tarjeta completa
                    if (data.pedidos.some(p => !document.getElementById('seguimiento-' + p.id))) {
                        location.reload();
                    }
                })
                .catch(err => console.error('Error al revisar el seguimiento:', err));
        }

        function verDetallePedido(pedidoId) {
            const body = document.getElementById('bodyDetallePedido');
            document.getElementById('modalDetallePedidoLabel').textContent = 'Pedido #' + pedidoId;
            body.innerHTML = '<p class="text-muted text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Cargando pedido...</p>';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetallePedido')).show();

            enviarAccion({ accion: 'detalle', pedido_id: pedidoId })
                .then(data => {
                    if (data.status !== 'success') {
                        body.innerHTML = '<div class="alert alert-danger mb-0">' + escaparHTML(data.message) + '</div>';
                        return;
                    }

                    const p = data.pedido;
                    const filas = data.items.map(item => `
                        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                            <div class="rounded-3 bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px; overflow: hidden;">
                                ${item.imagen
                                    ? `<img src="../../${escaparHTML(item.imagen)}" class="img-fluid h-100" style="object-fit: contain;" alt="">`
                                    : '<i class="bi bi-droplet text-gold fs-4"></i>'}
                            </div>
                            <div class="flex-grow-1">
                                <div class="small text-muted text-uppercase" style="letter-spacing: 1px;">${escaparHTML(item.marca)}</div>
                                <div class="fw-semibold text-brown">${escaparHTML(item.nombre)}</div>
                                <div class="small text-muted">${item.cantidad} × ${formatoDinero(item.precio_unitario)}</div>
                            </div>
                            <div class="fw-bold text-brown">${formatoDinero(item.subtotal)}</div>
                        </div>`).join('');

                    body.innerHTML = `
                        <div class="small text-muted mb-3"><i class="bi bi-calendar3 me-1"></i>Realizado el ${escaparHTML(p.fecha_pedido)}</div>

                        <div class="p-3 rounded-3 bg-light mb-4">${p.seguimiento_html}</div>

                        <h6 class="font-serif text-brown fw-bold mb-2">Productos</h6>
                        <div class="mb-3">${filas}</div>

                        <div class="ms-auto mb-4" style="max-width: 280px;">
                            <div class="d-flex justify-content-between small text-muted"><span>Subtotal</span><span>${formatoDinero(p.subtotal)}</span></div>
                            <div class="d-flex justify-content-between small text-muted"><span>Envío</span><span>${formatoDinero(p.costo_envio)}</span></div>
                            <div class="d-flex justify-content-between fw-bold text-brown fs-5 mt-1 pt-1 border-top"><span>Total</span><span>${formatoDinero(p.total)}</span></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="small text-muted text-uppercase" style="letter-spacing: 1px;">Dirección de entrega</div>
                                <div>${escaparHTML(p.direccion_entrega)}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted text-uppercase" style="letter-spacing: 1px;">Teléfono de contacto</div>
                                <div>${escaparHTML(p.telefono_contacto)}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted text-uppercase" style="letter-spacing: 1px;">Método de pago</div>
                                <div class="text-capitalize">${escaparHTML(p.metodo_pago)}</div>
                            </div>
                            ${p.notas ? `
                            <div class="col-md-6">
                                <div class="small text-muted text-uppercase" style="letter-spacing: 1px;">Notas</div>
                                <div>${escaparHTML(p.notas)}</div>
                            </div>` : ''}
                        </div>`;
                })
                .catch(err => {
                    console.error('Error:', err);
                    body.innerHTML = '<div class="alert alert-danger mb-0">No se pudo cargar el pedido. Intenta de nuevo.</div>';
                });
        }

        function abrirCancelarPedido(pedidoId) {
            pedidoACancelar = pedidoId;
            document.getElementById('cancelarPedidoNumero').textContent = pedidoId;
            document.getElementById('alertaCancelarPedido').classList.add('d-none');

            // Si se abrió desde el detalle, se cierra ese modal primero
            const modalDetalle = bootstrap.Modal.getInstance(document.getElementById('modalDetallePedido'));
            if (modalDetalle) modalDetalle.hide();

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCancelarPedido')).show();
        }

        document.getElementById('btnConfirmarCancelar').addEventListener('click', function () {
            const btn = this;
            const alerta = document.getElementById('alertaCancelarPedido');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cancelando...';

            enviarAccion({ accion: 'cancelar', pedido_id: pedidoACancelar })
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = 'Sí, cancelar pedido';

                    if (data.status === 'success') {
                        bootstrap.Modal.getInstance(document.getElementById('modalCancelarPedido')).hide();
                        mostrarAviso(data.message);
                        revisarSeguimiento();
                    } else {
                        alerta.textContent = data.message;
                        alerta.classList.remove('d-none');
                        revisarSeguimiento(); // El estado pudo cambiar mientras tanto
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    btn.disabled = false;
                    btn.innerHTML = 'Sí, cancelar pedido';
                    alerta.textContent = 'No se pudo cancelar el pedido. Intenta de nuevo.';
                    alerta.classList.remove('d-none');
                });
        });

        function cerrarSesionCliente() {
            const formData = new FormData();
            formData.append('accion', 'cerrar_sesion');

            fetch('act_login_cliente.php', { method: 'POST', body: formData })
                .then(() => { window.location.href = '../index.php'; })
                .catch(err => console.error('Error al cerrar sesión:', err));
        }

        setInterval(revisarSeguimiento, INTERVALO_SEGUIMIENTO);
        // Al volver a la pestaña se revisa de inmediato
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') revisarSeguimiento();
        });
    </script>
</body>
</html>
