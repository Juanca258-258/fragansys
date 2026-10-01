<?php
require_once("../conexion.php");
$id = (int)$_POST['id'];

$pedido = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pedidos WHERE id=$id"));

$detalle = mysqli_query($conn,
    "SELECT pd.*,
            CASE WHEN pd.tipo='producto' THEN p.nombre ELSE c.nombre END AS nombre_item
     FROM pedido_detalle pd
     LEFT JOIN productos p ON pd.producto_id = p.id
     LEFT JOIN combos c ON pd.combo_id = c.id
     WHERE pd.pedido_id = $id");

$etiquetas_estado = [
    'nuevo'          => 'Nuevo',
    'confirmado'     => 'Confirmado',
    'en_preparacion' => 'En preparación',
    'enviado'        => 'Enviado',
    'entregado'      => 'Entregado',
    'cancelado'      => 'Cancelado',
];
?>
<div class="modal-header">
    <h5 class="modal-title">Pedido #<?= $pedido['id'] ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="small text-muted">Cliente</div>
            <div class="fw-bold"><?= htmlspecialchars($pedido['cliente_nombre']) ?></div>
            <div class="small"><?= htmlspecialchars($pedido['cliente_correo']) ?></div>
            <div class="small"><?= htmlspecialchars($pedido['telefono_contacto']) ?></div>
        </div>
        <div class="col-md-6">
            <div class="small text-muted">Dirección de entrega</div>
            <div><?= htmlspecialchars($pedido['direccion_entrega']) ?></div>
        </div>
    </div>

    <?php if (!empty($pedido['notas'])): ?>
    <div class="mb-3">
        <div class="small text-muted">Notas del cliente</div>
        <div><?= htmlspecialchars($pedido['notas']) ?></div>
    </div>
    <?php endif; ?>

    <table class="tabla-simple mb-3">
        <thead>
        <tr><th>Producto</th><th class="text-end">Cant.</th><th class="text-end">Precio</th><th class="text-end">Subtotal</th></tr>
        </thead>
        <tbody>
        <?php while ($d = mysqli_fetch_assoc($detalle)) { ?>
        <tr>
            <td><?= htmlspecialchars($d['nombre_item'] ?? 'Producto eliminado') ?></td>
            <td class="text-end"><?= (int)$d['cantidad'] ?></td>
            <td class="text-end">$<?= number_format($d['precio_unitario'], 2) ?></td>
            <td class="text-end">$<?= number_format($d['subtotal'], 2) ?></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>

    <div class="d-flex justify-content-end gap-4 mb-3">
        <div class="text-muted small">Envío: $<?= number_format($pedido['costo_envio'], 2) ?></div>
        <div class="fw-bold">Total: $<?= number_format($pedido['total'], 2) ?></div>
    </div>

    <hr>

    <div class="row align-items-end">
        <div class="col-md-7">
            <label>Estado del pedido</label>
            <select id="sel_estado_pedido" class="form-select">
                <?php foreach ($etiquetas_estado as $valor => $etiqueta) { ?>
                <option value="<?= $valor ?>" <?= $pedido['estado_pedido'] == $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-5">
            <span class="small text-muted">Pago: <strong><?= ucfirst($pedido['estado_pago']) ?></strong></span>
        </div>
    </div>

</div>

<div class="modal-footer">
    <button class="btn-dorado" onclick="actualizar_estado_pedido(<?= $pedido['id'] ?>)">Actualizar estado</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function actualizar_estado_pedido(id){
    var estado = $('#sel_estado_pedido').val();
    $.post("Pedidos/back_pedidos.php", { accion:"actualizar_estado", id:id, estado_pedido:estado }, function(resp){
        var r = JSON.parse(resp);
        if(r.ok){
            $("#modal_general").modal('hide');
            Swal.fire({title:"Estado actualizado",text:"El pedido fue actualizado correctamente.",icon:"success",timer:1500,showConfirmButton:false});
            cargar_pedidos();
        } else {
            Swal.fire({title:"No se pudo actualizar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        }
    });
}
</script>
