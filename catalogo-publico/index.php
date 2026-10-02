<?php
// Reporte de errores visible para desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar lógica de la base de datos
require_once __DIR__ . '/back_catalogo_pub.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo - Fragansys</title>
    <!-- Estilos de Bootstrap y fuentes del proyecto -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* Tipografías y colores personalizados basados en tu diseño */
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .font-serif { font-family: 'Georgia', 'Times New Roman', serif; }
        .text-brown { color: #4a3320; }
        .text-gold { color: #b58c5a; }
        .bg-gold { background-color: #b58c5a; }
        .bg-dark-card { background-color: #111111; }
        
        .navbar-nav .nav-link { font-size: 0.85rem; letter-spacing: 1px; color: #555; }
        .navbar-nav .nav-link.active { border-bottom: 2px solid #b58c5a; color: #4a3320 !important; font-weight: 600; }
        
        .hero-section {
            background: linear-gradient(135deg, #fefaf6 0%, #f0e6d6 100%);
            padding: 4rem 1rem;
            color: #4a3320;
        }
        
        .separator-line {
            flex-grow: 1;
            height: 1px;
            background-color: #d8caca;
            margin-left: 15px;
        }
    </style>
</head>
<body class="bg-light">

    <!-- Navbar Minimalista -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <span class="rounded-circle text-white d-flex justify-content-center align-items-center" style="width: 35px; height: 35px; background-color: #4a3320; font-family: Georgia, serif;">F</span>
                <span class="ms-2 fs-4 font-serif text-brown">Fragansys</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav gap-3">
                    <li class="nav-item"><a class="nav-link active text-uppercase" href="#">Catálogo</a></li>
                    <li class="nav-item"><a class="nav-link text-uppercase" href="#">Catálogo Completo</a></li>
                    <li class="nav-item"><a class="nav-link text-uppercase" href="#">Decants</a></li>
                    <li class="nav-item"><a class="nav-link text-uppercase" href="#">Perfumes</a></li>
                    <li class="nav-item"><a class="nav-link text-uppercase" href="#">Combos</a></li>
                    <li class="nav-item"><a class="nav-link text-uppercase text-danger" href="#">• Mujer</a></li>
                    <li class="nav-item"><a class="nav-link text-uppercase text-dark" href="#">• Hombre</a></li>
                </ul>
            </div>
            
           <!-- Botón de carrito superior en la barra de navegación -->
<div class="d-none d-lg-block">
    <a href="carrito/indexC.php" class="btn btn-outline-dark border-0 position-relative text-decoration-none">
        <i class="bi bi-cart3 fs-5"></i> 
        <span id="badgeCarritoCount" class="badge bg-gold rounded-pill">0</span>
    </a>
</div>
        </div>
    </nav>

    <!-- Banner Hero -->
    <div class="hero-section text-center text-brown mb-5">
        <p class="text-uppercase fw-bold mb-2 text-muted" style="letter-spacing: 2px; font-size: 0.8rem;">Fragancias Originales</p>
        <h1 class="display-5 font-serif mb-3">Fragancias que <span class="text-gold" style="font-style: italic;">cuentan historias</span></h1>
        <p class="lead" style="font-size: 1rem;">Descubre tu aroma sin invertir en un frasco completo.</p>
    </div>

    <div class="container px-4 mb-5">
        <!-- Inclusión de la vista del catálogo -->
        <?php include_once __DIR__ . '/modal_catalogo_pub.php'; ?>
    </div>

    <!-- Inclusión del modal emergente con las notas olfativas -->
    <?php include_once __DIR__ . '/modalg_catalogo_pub.php'; ?>

    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Lógica de Frontend -->
<script>
    function actualizarPrecio(select) {
        const opcion = select.options[select.selectedIndex];
        const precio = parseFloat(opcion.getAttribute('data-precio')).toFixed(2);
        
        const tarjeta = select.closest('.card-body');
        if (tarjeta) {
            tarjeta.querySelector('.precio-display').textContent = '$' + precio;
        }
    }

    function agregarAlCarrito(btn) {
        const tarjeta = btn.closest('.card-body');
        const select = tarjeta.querySelector('.selector-variante');
        const productoId = select.value;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Agregando...';

        const formData = new FormData();
        formData.append('accion', 'agregar');
        formData.append('producto_id', productoId);
        formData.append('cantidad', 1);

        fetch('act_catalogo_pub.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const badgeCarrito = document.getElementById('badgeCarritoCount');
                if (badgeCarrito) badgeCarrito.textContent = data.total_items;
                btn.innerHTML = '¡AGREGADO!';
                btn.classList.replace('btn-outline-light', 'btn-success');
                setTimeout(() => {
                    btn.innerHTML = 'AGREGAR AL CARRITO';
                    btn.classList.replace('btn-success', 'btn-outline-light');
                    btn.disabled = false;
                }, 2000);
            } else {
                alert('Atención: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = 'AGREGAR AL CARRITO';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.disabled = false;
            btn.innerHTML = 'AGREGAR AL CARRITO';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        // 1. Sincronizar el badge del carrito al entrar o regresar a la página
        actualizarBadgeCarrito();

        // 2. Filtros de búsqueda y género
        const inputBuscar = document.getElementById('buscarPerfume');
        const selectGenero = document.getElementById('filtroGenero');
        const tarjetas = document.querySelectorAll('.tarjeta-perfume');

        function filtrar() {
            const texto = inputBuscar ? inputBuscar.value.toLowerCase().trim() : '';
            const genero = selectGenero ? selectGenero.value : '';

            tarjetas.forEach(tarjeta => {
                const nombre = tarjeta.getAttribute('data-nombre') || '';
                const marca = tarjeta.getAttribute('data-marca') || '';
                const gen = tarjeta.getAttribute('data-genero') || '';

                const coincideTexto = nombre.includes(texto) || marca.includes(texto);
                const coincideGenero = !genero || gen === genero;

                tarjeta.style.display = (coincideTexto && coincideGenero) ? '' : 'none';
            });
        }

        if (inputBuscar) inputBuscar.addEventListener('input', filtrar);
        if (selectGenero) selectGenero.addEventListener('change', filtrar);
    });

    // Función para consultar la cantidad real de productos en el carrito
    function actualizarBadgeCarrito() {
        const formData = new FormData();
        formData.append('accion', 'obtener_conteo');

        fetch('carrito/act_carrito_pub.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('badgeCarritoCount');
            if (badge && data.total_items !== undefined) {
                badge.textContent = data.total_items;
            }
        })
        .catch(err => console.error('Error al sincronizar el contador del carrito:', err));
    }
</script>
</body>
</html>