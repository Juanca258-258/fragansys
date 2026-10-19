<!-- Modal: detalle del pedido (se rellena con JS desde act_pedidos_cliente.php) -->
<div class="modal fade" id="modalDetallePedido" tabindex="-1" aria-labelledby="modalDetallePedidoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-serif text-brown fw-bold" id="modalDetallePedidoLabel">Detalle del pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="bodyDetallePedido">
                <p class="text-muted text-center py-4">Cargando pedido...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: confirmar cancelación -->
<div class="modal fade" id="modalCancelarPedido" tabindex="-1" aria-labelledby="modalCancelarPedidoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-serif text-brown fw-bold" id="modalCancelarPedidoLabel">Cancelar pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-muted">
                <div id="alertaCancelarPedido" class="alert alert-danger small d-none" role="alert"></div>
                <p class="mb-2">¿Seguro que quieres cancelar el <strong class="text-brown">pedido #<span id="cancelarPedidoNumero"></span></strong>?</p>
                <p class="small mb-0">Esta acción no se puede deshacer. Solo puedes cancelar mientras el pedido no haya sido enviado.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-dark rounded-pill px-4" data-bs-dismiss="modal">Volver</button>
                <button type="button" class="btn btn-danger rounded-pill px-4" id="btnConfirmarCancelar">Sí, cancelar pedido</button>
            </div>
        </div>
    </div>
</div>

<!-- Aviso cuando el seguimiento de un pedido cambia -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toastPedido" class="toast align-items-center text-white border-0" style="background-color: #4a3320;" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastPedidoTexto"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
    </div>
</div>
