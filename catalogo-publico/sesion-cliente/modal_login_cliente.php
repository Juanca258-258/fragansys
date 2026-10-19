<?php
if (!function_exists('validarLoginCliente')) {
    require_once __DIR__ . '/back_login_cliente.php';
}
?>

<!-- Encabezado Centrado -->
<div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center mb-2">
        <span class="text-gold fs-4 me-2">&#9670;</span>
        <h2 class="mb-0 font-serif text-brown fw-bold" style="letter-spacing: 1px;">Iniciar Sesión</h2>
        <span class="text-gold fs-4 ms-2">&#9670;</span>
    </div>
    <p class="text-muted fs-6 mb-0" style="font-style: italic; font-family: Georgia, serif;">
        Accede para dar seguimiento a tus pedidos.
    </p>
</div>

<!-- Tarjeta de Login -->
<div class="bg-white rounded-4 shadow-sm border border-light p-4 p-md-5 mx-auto" style="max-width: 460px;">
    <div class="text-center mb-4">
        <i class="bi bi-person-circle text-gold" style="font-size: 3.5rem;"></i>
    </div>

    <div id="alertaLoginCliente" class="alert alert-danger small d-none" role="alert"></div>

    <form id="formLoginCliente" novalidate>
        <div class="mb-3">
            <label for="loginCorreo" class="form-label small text-uppercase text-muted fw-bold" style="letter-spacing: 1px;">Correo electrónico</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-gold"></i></span>
                <input type="email" class="form-control border-start-0" id="loginCorreo" name="correo" placeholder="tucorreo@ejemplo.com" maxlength="150" required autocomplete="email">
            </div>
        </div>

        <div class="mb-4">
            <label for="loginPassword" class="form-label small text-uppercase text-muted fw-bold" style="letter-spacing: 1px;">Contraseña</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-gold"></i></span>
                <input type="password" class="form-control border-start-0 border-end-0" id="loginPassword" name="password" placeholder="Tu contraseña" required autocomplete="current-password">
                <button type="button" class="input-group-text bg-light border-start-0" id="btnVerPassword" title="Mostrar contraseña">
                    <i class="bi bi-eye text-muted"></i>
                </button>
            </div>
        </div>

        <button type="submit" id="btnLoginCliente" class="btn btn-dark w-100 rounded-pill py-3 text-uppercase font-serif shadow-sm" style="font-size: 0.85rem; letter-spacing: 1px; background-color: #4a3320; border: none;">
            Iniciar Sesión
        </button>
    </form>

    <div class="text-center mt-4">
        <a href="#" class="small text-gold text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalSinCuenta">
            ¿Aún no tienes cuenta?
        </a>
    </div>
</div>
