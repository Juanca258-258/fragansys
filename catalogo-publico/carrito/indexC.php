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
    </script>
</body>
</html>