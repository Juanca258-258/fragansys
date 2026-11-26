<!-- Modal informativo: cómo obtener una cuenta de cliente -->
<div class="modal fade" id="modalSinCuenta" tabindex="-1" aria-labelledby="modalSinCuentaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-serif text-brown fw-bold" id="modalSinCuentaLabel">¿Aún no tienes cuenta?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-muted">
                <p class="mb-2">Tu cuenta se crea automáticamente al realizar tu primer pedido: en el carrito te pediremos tu correo y una contraseña.</p>
                <p class="mb-0">Después podrás iniciar sesión aquí con esos mismos datos para dar seguimiento a tus pedidos.</p>
            </div>
            <div class="modal-footer border-0">
                <a href="../index.php" class="btn btn-dark rounded-pill px-4 text-uppercase font-serif" style="font-size: 0.8rem; letter-spacing: 1px; background-color: #4a3320; border: none;">
                    Ir al Catálogo
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal: recuperar contraseña (paso 1 correo, paso 2 código y nueva contraseña) -->
<div class="modal fade" id="modalRecuperarPassword" tabindex="-1" aria-labelledby="modalRecuperarPasswordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-serif text-brown fw-bold" id="modalRecuperarPasswordLabel">Cambiar contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="alertaRecuperar" class="alert small d-none" role="alert"></div>

                <!-- Paso 1: correo -->
                <form id="formRecuperarCorreo" novalidate>
                    <p class="text-muted small">Escribe el correo de tu cuenta y te enviaremos un código para crear una contraseña nueva.</p>
                    <div class="mb-3">
                        <label for="recuperarCorreo" class="form-label small text-uppercase text-muted fw-bold" style="letter-spacing: 1px;">Correo electrónico</label>
                        <input type="email" class="form-control" id="recuperarCorreo" name="correo" placeholder="tucorreo@ejemplo.com" maxlength="150" required autocomplete="email">
                    </div>
                    <button type="submit" id="btnRecuperarCorreo" class="btn btn-dark w-100 rounded-pill py-2 text-uppercase font-serif" style="font-size: 0.85rem; letter-spacing: 1px; background-color: #4a3320; border: none;">
                        Enviar código
                    </button>
                </form>

                <!-- Paso 2: código y nueva contraseña -->
                <form id="formRecuperarPassword" class="d-none" novalidate>
                    <div class="mb-3">
                        <label for="recuperarCodigo" class="form-label small text-uppercase text-muted fw-bold" style="letter-spacing: 1px;">Código recibido</label>
                        <input type="text" class="form-control text-center fs-5" id="recuperarCodigo" name="codigo" placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required style="letter-spacing: 6px;">
                    </div>
                    <div class="mb-3">
                        <label for="recuperarNueva" class="form-label small text-uppercase text-muted fw-bold" style="letter-spacing: 1px;">Nueva contraseña</label>
                        <input type="password" class="form-control" id="recuperarNueva" name="password" placeholder="Mínimo 6 caracteres" minlength="6" required autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label for="recuperarConfirmar" class="form-label small text-uppercase text-muted fw-bold" style="letter-spacing: 1px;">Confirmar contraseña</label>
                        <input type="password" class="form-control" id="recuperarConfirmar" name="password_confirmar" placeholder="Repite la contraseña" minlength="6" required autocomplete="new-password">
                    </div>
                    <button type="submit" id="btnRecuperarPassword" class="btn btn-dark w-100 rounded-pill py-2 text-uppercase font-serif" style="font-size: 0.85rem; letter-spacing: 1px; background-color: #4a3320; border: none;">
                        Guardar nueva contraseña
                    </button>
                    <div class="text-center mt-3">
                        <a href="#" id="lnkReenviarCodigo" class="small text-gold text-decoration-none">¿No te llegó? Enviar otro código</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
