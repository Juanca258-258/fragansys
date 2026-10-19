<?php
if (!isset($datosCarrito)) {
    require_once __DIR__ . '/back_carrito_pub.php';
}
?>

<!-- Encabezado Centrado y Descriptivo -->
<div class="text-center mb-5">
    <div class="d-inline-flex align-items-center justify-content-center mb-2">
        <span class="text-gold fs-4 me-2">&#9670;</span>
        <h2 class="mb-0 font-serif text-brown fw-bold" style="letter-spacing: 1px;">Tu Carrito de Compras</h2>
        <span class="text-gold fs-4 ms-2">&#9670;</span>
    </div>
    <p class="text-muted fs-5 mb-0" style="font-style: italic; font-family: Georgia, serif; max-width: 600px; margin: 0 auto;">
         Procede con tu pedido cuando estés listo.
    </p>
</div>

<?php if (empty($datosCarrito['items'])): ?>
    <!-- Estado Vacío Mejorado -->
    <div class="text-center py-5 bg-white rounded-4 shadow-sm border border-light" style="max-width: 650px; margin: 0 auto;">
        <div class="mb-3">
            <i class="bi bi-bag-x text-gold" style="font-size: 4.5rem;"></i>
        </div>
        <h3 class="font-serif text-brown fw-bold mb-2">Tu carrito está actualmente vacío</h3>
        <p class="text-muted fs-6 mb-4 px-4">
            Parece que aún no has agregado ninguna fragancia a tu colección. Explora nuestro catálogo y descubre tu próximo aroma.
        </p>
        <a href="../index.php" class="btn btn-dark rounded-pill px-5 py-3 text-uppercase font-serif shadow-sm" style="font-size: 0.85rem; letter-spacing: 1px; background-color: #4a3320; border: none;">
            <i class="bi bi-arrow-left me-2"></i> Explorar el Catálogo
        </a>
    </div>
<?php else: ?>
    <!-- Botón Superior para Regresar al Inicio/Catálogo -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="../index.php" class="btn btn-outline-dark rounded-pill px-4 py-2 font-serif text-uppercase d-inline-flex align-items-center" style="font-size: 0.8rem; letter-spacing: 1px;">
            <i class="bi bi-arrow-left me-2 fs-6"></i> Seguir Comprando
        </a>
        <span class="text-muted small font-serif">
            Items en el carrito: <strong class="text-brown"><?= $datosCarrito['total_items'] ?></strong>
        </span>
    </div>

    <div class="row g-4">
        <!-- Tabla / Lista de Productos -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-dark text-white" style="background-color: #111111 !important;">
                                <tr class="font-serif text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">
                                    <th class="ps-4 py-3">Producto</th>
                                    <th class="py-3">Precio</th>
                                    <th class="text-center py-3">Cantidad</th>
                                    <th class="py-3">Subtotal</th>
                                    <th class="text-end pe-4 py-3">Eliminar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datosCarrito['items'] as $item): ?>
                                    <tr id="item-row-<?= $item['producto_id'] ?>">
                                        <!-- Detalles del Producto -->
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light p-2 rounded-3 me-3 text-center" style="width: 65px; height: 65px; flex-shrink: 0;">
                                                    <img src="../../<?= htmlspecialchars($item['imagen']) ?>" class="img-fluid h-100" style="object-fit: contain;" alt="<?= htmlspecialchars($item['nombre']) ?>">
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 font-serif text-brown fw-bold fs-6"><?= htmlspecialchars($item['nombre']) ?></h6>
                                                    <span class="badge bg-light text-dark border border-secondary-subtle rounded-pill" style="font-size: 0.75rem;">
                                                        <?= htmlspecialchars($item['marca']) ?> • <?= $item['contenido_ml'] ?> ml
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Precio Unitario -->
                                        <td class="fw-semibold text-secondary" style="font-size: 0.95rem;">
                                            $<?= number_format($item['precio'], 2) ?>
                                        </td>
                                        
                                        <!-- Formulario de Cantidad Estilizado -->
                                        <td style="width: 150px;">
                                            <div class="input-group input-group-sm rounded-pill overflow-hidden border border-secondary-subtle shadow-sm">
                                                <button class="btn btn-light text-dark px-2 border-0" type="button" onclick="modificarPaso(<?= $item['producto_id'] ?>, -1)">-</button>
                                                <input type="number" 
                                                       id="input-cant-<?= $item['producto_id'] ?>"
                                                       class="form-control text-center border-0 fw-bold bg-white text-brown" 
                                                       value="<?= $item['cantidad'] ?>" 
                                                       min="1" 
                                                       max="<?= $item['stock'] ?>"
                                                       onchange="actualizarCantidad(<?= $item['producto_id'] ?>, this.value)"
                                                       style="box-shadow: none;">
                                                <button class="btn btn-light text-dark px-2 border-0" type="button" onclick="modificarPaso(<?= $item['producto_id'] ?>, 1)">+</button>
                                            </div>
                                        </td>
                                        
                                        <!-- Subtotal por Fila -->
                                        <td class="fw-bold text-brown fs-6" id="subtotal-val-<?= $item['producto_id'] ?>">
                                            $<?= number_format($item['subtotal'], 2) ?>
                                        </td>
                                        
                                        <!-- Botón de Eliminar -->
                                        <td class="text-end pe-4">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger rounded-circle p-2 d-inline-flex align-items-center justify-content-center border-0" 
                                                    style="width: 34px; height: 34px;"
                                                    title="Eliminar producto"
                                                    onclick="eliminarItem(<?= $item['producto_id'] ?>)">
                                                <i class="bi bi-trash3 fs-6"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Resumen del Pedido -->
        <div class="col-lg-4">
            <div class="card border-0 shadow border border-dark rounded-4 bg-dark text-white p-4" style="background-color: #111111 !important;">
                <h4 class="font-serif text-gold mb-3 text-center fw-bold" style="letter-spacing: 1px;">Resumen del Pedido</h4>
                <p class="text-muted text-center small mb-4" style="font-style: italic;">
                    Desglose de costos estimado para tu compra.
                </p>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary font-serif">Subtotal</span>
                    <span class="fw-bold fs-5 text-white" id="cart-subtotal">$<?= number_format($datosCarrito['subtotal'], 2) ?></span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary font-serif">Envío</span>
                    <span class="text-success small fw-bold px-2 py-1 bg-success-subtle rounded-pill">Por calcular</span>
                </div>
                
                <hr class="border-secondary my-3">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="fs-5 font-serif fw-bold">Total Final</span>
                    <span class="fs-3 fw-bold text-gold" id="cart-total">$<?= number_format($datosCarrito['subtotal'], 2) ?></span>
                </div>
                
                <button type="button" class="btn text-white w-100 rounded-pill py-3 text-uppercase font-serif fw-bold shadow" style="background-color: #b58c5a; border: none; letter-spacing: 1px;" data-bs-toggle="modal" data-bs-target="#modalCheckout">
                    Proceder al Pago <i class="bi bi-credit-card ms-2"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Datos de Entrega -->
    <div class="modal fade" id="modalCheckout" tabindex="-1" aria-labelledby="modalCheckoutLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <form id="formCheckout" onsubmit="realizarPedido(event)">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title font-serif text-brown fw-bold" id="modalCheckoutLabel">Datos de Entrega</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <?php
                        // Con sesión de cliente iniciada no se piden correo ni contraseña: se usan los datos de su cuenta
                        $conSesion      = !empty($clienteSesion);
                        $faltaTelefono  = $conSesion && empty($clienteSesion['telefono']);
                        $faltaDireccion = $conSesion && empty($clienteSesion['direccion']);
                        ?>
                        <!-- Paso 1: correo -->
                        <div class="mb-3<?= $conSesion ? ' d-none' : '' ?>">
                            <label class="form-label small text-muted">Correo electrónico *</label>
                            <div class="input-group">
                                <input type="email" name="cliente_correo" id="checkoutCorreo" class="form-control" maxlength="150" <?= $conSesion ? '' : 'required' ?>>
                                <button type="button" id="btnCambiarCorreo" class="btn btn-outline-secondary d-none" onclick="cambiarCorreo()">Cambiar</button>
                            </div>
                            <div class="form-text" id="ayudaCorreo">Si ya compraste antes, usaremos los datos que registraste.</div>
                        </div>

                        <!-- Cliente registrado y verificado -->
                        <div id="bloqueClienteExistente" class="<?= $conSesion ? '' : 'd-none' ?>">
                            <div class="alert alert-light border mb-3">
                                <div class="fw-bold text-brown mb-1">Hola, <span id="resumenNombre"><?= $conSesion ? htmlspecialchars($clienteSesion['nombre']) : '' ?></span></div>
                                <?php if ($conSesion): ?>
                                    <div class="small text-muted">Correo: <?= htmlspecialchars($clienteSesion['correo']) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted<?= $faltaTelefono ? ' d-none' : '' ?>" id="resumenTelefonoFila">Teléfono: <span id="resumenTelefono"><?= $conSesion ? htmlspecialchars((string)$clienteSesion['telefono']) : '' ?></span></div>
                                <div class="small text-muted<?= $faltaDireccion ? ' d-none' : '' ?>" id="resumenDireccionFila">Dirección: <span id="resumenDireccion"><?= $conSesion ? htmlspecialchars((string)$clienteSesion['direccion']) : '' ?></span></div>
                            </div>
                        </div>

                        <!-- Datos a capturar (cliente nuevo, o registrado con datos incompletos) -->
                        <div id="campoNombre" class="mb-3 d-none">
                            <label class="form-label small text-muted">Nombre completo *</label>
                            <input type="text" name="cliente_nombre" class="form-control" maxlength="120">
                        </div>
                        <div id="campoTelefono" class="mb-3<?= $faltaTelefono ? '' : ' d-none' ?>">
                            <label class="form-label small text-muted">Teléfono de contacto *</label>
                            <input type="tel" name="telefono_contacto" class="form-control" maxlength="20" <?= $faltaTelefono ? 'required' : '' ?>>
                        </div>
                        <div id="campoDireccion" class="mb-3<?= $faltaDireccion ? '' : ' d-none' ?>">
                            <label class="form-label small text-muted">Dirección de entrega *</label>
                            <textarea name="direccion_entrega" class="form-control" rows="2" maxlength="255" <?= $faltaDireccion ? 'required' : '' ?>></textarea>
                        </div>

                        <!-- Contraseña: la crea un cliente nuevo o la escribe un cliente registrado -->
                        <div id="campoPassword" class="mb-3 d-none">
                            <label class="form-label small text-muted" id="etiquetaPassword">Contraseña *</label>
                            <input type="password" name="password" id="checkoutPassword" class="form-control" minlength="6">
                            <div class="form-text" id="ayudaPassword"></div>
                        </div>
                        <div id="campoConfirmarPassword" class="mb-3 d-none">
                            <label class="form-label small text-muted">Confirma tu contraseña *</label>
                            <input type="password" name="password_confirmar" class="form-control" minlength="6">
                        </div>

                        <div id="bloqueConfirmacion" class="<?= $conSesion ? '' : 'd-none' ?>">
                            <div class="mb-3">
                                <label class="form-label small text-muted">Notas para este pedido (opcional)</label>
                                <textarea name="notas" class="form-control" rows="2" maxlength="255"></textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                <span class="font-serif fw-bold text-brown">Total a pagar</span>
                                <span class="fs-5 fw-bold text-gold">$<?= number_format($datosCarrito['subtotal'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-dark rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" id="btnConfirmarPedido" class="btn text-white rounded-pill px-4 fw-bold" style="background-color: #b58c5a; border: none;" data-con-sesion="<?= $conSesion ? '1' : '0' ?>">
                            <?= $conSesion ? 'Confirmar Pedido' : 'Continuar' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Script auxiliar para botones +/- -->
<script>
function modificarPaso(productoId, cambio) {
    const input = document.getElementById('input-cant-' + productoId);
    if (input) {
        let actual = parseInt(input.value) || 1;
        let nuevo = actual + cambio;
        let min = parseInt(input.min) || 1;
        let max = parseInt(input.max) || 999;

        if (nuevo >= min && nuevo <= max) {
            input.value = nuevo;
            actualizarCantidad(productoId, nuevo);
        }
    }
}
</script>