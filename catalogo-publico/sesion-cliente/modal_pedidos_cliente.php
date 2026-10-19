<?php
if (!isset($pedidosCliente)) {
    require_once __DIR__ . '/back_pedidos_cliente.php';
}
?>

<!-- Encabezado Centrado -->
<div class="text-center mb-5">
    <div class="d-inline-flex align-items-center justify-content-center mb-2">
        <span class="text-gold fs-4 me-2">&#9670;</span>
        <h2 class="mb-0 font-serif text-brown fw-bold" style="letter-spacing: 1px;">Mis Pedidos</h2>
        <span class="text-gold fs-4 ms-2">&#9670;</span>
    </div>
    <p class="text-muted fs-5 mb-0" style="font-style: italic; font-family: Georgia, serif; max-width: 600px; margin: 0 auto;">
        Hola, <?= htmlspecialchars($_SESSION['cliente_nombre']) ?>. Estado de tus pedidos.
    </p>
</div>

<?php if (empty($pedidosCliente)): ?>
    <!-- Estado Vacío -->
    <div class="text-center py-5 bg-white rounded-4 shadow-sm border border-light" style="max-width: 650px; margin: 0 auto;">
        <div class="mb-3">
            <i class="bi bi-bag text-gold" style="font-size: 4.5rem;"></i>
        </div>
        <h3 class="font-serif text-brown fw-bold mb-2">Aún no tienes pedidos</h3>
        <p class="text-muted fs-6 mb-4 px-4">
            Cuando realices un pedido, aparecerá aquí para que puedas darle seguimiento.
        </p>
        <a href="../index.php" class="btn btn-dark rounded-pill px-5 py-3 text-uppercase font-serif shadow-sm" style="font-size: 0.85rem; letter-spacing: 1px; background-color: #4a3320; border: none;">
            <i class="bi bi-arrow-left me-2"></i> Explorar el Catálogo
        </a>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4" style="max-width: 900px; margin: 0 auto;">
        <span class="text-muted small font-serif">
            Pedidos realizados: <strong class="text-brown"><?= count($pedidosCliente) ?></strong>
        </span>
        <span class="text-muted small"><i class="bi bi-arrow-repeat me-1"></i>El seguimiento se actualiza automáticamente</span>
    </div>

    <div class="d-flex flex-column gap-4" style="max-width: 900px; margin: 0 auto;">
        <?php foreach ($pedidosCliente as $pedido): ?>
            <div class="bg-white rounded-4 shadow-sm border border-light p-4 tarjeta-pedido" id="pedido-<?= (int)$pedido['id'] ?>">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3 pb-3 border-bottom">
                    <div>
                        <h5 class="font-serif text-brown fw-bold mb-1">Pedido #<?= (int)$pedido['id'] ?></h5>
                        <div class="small text-muted">
                            <i class="bi bi-calendar3 me-1"></i><?= formatoFecha($pedido['fecha_pedido']) ?>
                            &nbsp;·&nbsp; <?= (int)$pedido['total_items'] ?> artículo<?= (int)$pedido['total_items'] === 1 ? '' : 's' ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted text-uppercase" style="letter-spacing: 1px;">Total</div>
                        <div class="fs-5 fw-bold text-brown">$<?= number_format((float)$pedido['total'], 2) ?></div>
                    </div>
                </div>

                <!-- Seguimiento: se reemplaza automáticamente cuando el estado del pedido cambia -->
                <div class="seguimiento-pedido" id="seguimiento-<?= (int)$pedido['id'] ?>" data-firma="<?= htmlspecialchars(firmaPedido($pedido)) ?>">
                    <?= htmlSeguimientoPedido($pedido) ?>
                </div>

                <div class="mt-3 pt-3 border-top">
                    <button type="button" class="btn btn-outline-dark btn-sm rounded-pill px-4 text-uppercase font-serif" style="font-size: 0.75rem; letter-spacing: 1px;" onclick="verDetallePedido(<?= (int)$pedido['id'] ?>)">
                        <i class="bi bi-eye me-1"></i> Ver detalles
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
