<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once(__DIR__ . "/flujo_pedidos.php");
$id = (int)$_POST['id'];

$pedido = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pedidos WHERE id=$id"));

$detalle = mysqli_query($conn,
    "SELECT pd.*,
            CASE WHEN pd.tipo='presentacion'
                 THEN CONCAT(pf.nombre, ' — ', IF(pr.tipo='frasco', 'Frasco ', 'Decant '),
                             TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.ml)), ' ml')
                 ELSE c.nombre END AS nombre_item
     FROM pedido_detalle pd
     LEFT JOIN presentaciones pr ON pd.presentacion_id = pr.id
     LEFT JOIN perfumes pf ON pr.perfume_id = pf.id
     LEFT JOIN combos c ON pd.combo_id = c.id
     WHERE pd.pedido_id = $id");

$estado     = $pedido['estado_pedido'];
$cancelado  = $estado === 'cancelado';
$siguiente  = siguiente_estado($estado);
$paso_actual = array_search($estado, FLUJO_PEDIDO, true);
?>
<style>
.pasos-pedido { display:flex; margin:6px 0 4px; }
.paso-pedido { flex:1; text-align:center; position:relative; font-size:.72rem; color:var(--texto-suave); }
.paso-pedido::before {
    content:''; position:absolute; top:13px; left:-50%; width:100%; height:2px; background:var(--borde); z-index:0;
}
.paso-pedido:first-child::before { display:none; }
.paso-punto {
    width:28px; height:28px; border-radius:50%; margin:0 auto 6px; position:relative; z-index:1;
    display:flex; align-items:center; justify-content:center; font-size:.75rem;
    background:#fff; border:2px solid var(--borde); color:var(--texto-suave);
}
.paso-pedido.hecho .paso-punto { background:var(--dorado-oscuro); border-color:var(--dorado-oscuro); color:#fff; }
.paso-pedido.hecho::before, .paso-pedido.actual::before { background:var(--dorado-oscuro); }
.paso-pedido.actual .paso-punto { background:var(--negro); border-color:var(--negro); color:var(--dorado); }
.paso-pedido.actual { color:var(--texto); font-weight:600; }
</style>
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

    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="mb-0">Estado del pedido</label>
        <span class="small text-muted">Pago: <strong><?= ucfirst($pedido['estado_pago']) ?></strong></span>
    </div>

    <?php if ($cancelado): ?>
    <div class="p-3 rounded text-center" style="background:#fbe7e0; color:#9b3d1e">
        <i class="fa-solid fa-ban me-1"></i> <strong>Pedido cancelado por el cliente.</strong> Ya no se puede avanzar.
    </div>
    <?php else: ?>
    <div class="pasos-pedido">
        <?php foreach (FLUJO_PEDIDO as $i => $paso):
            $clase = $i < $paso_actual ? 'hecho' : ($i === $paso_actual ? 'actual' : ''); ?>
        <div class="paso-pedido <?= $clase ?>">
            <div class="paso-punto"><?= $i < $paso_actual ? '<i class="fa-solid fa-check"></i>' : $i + 1 ?></div>
            <?= ETIQUETAS_ESTADO[$paso] ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($pedido['estado_pago'] !== 'pagado' && $estado === 'nuevo'): ?>
    <div class="small mt-2" style="color:#9b3d1e">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> El pago de este pedido está <strong><?= $pedido['estado_pago'] ?></strong>. Revisa que esté pagado antes de confirmarlo.
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<div class="modal-footer">
    <?php if ($siguiente !== null && !$cancelado): ?>
    <button class="btn-dorado" id="btn_avanzar_pedido"
            onclick="avanzar_estado_pedido(<?= $pedido['id'] ?>, '<?= $estado ?>', <?= htmlspecialchars(json_encode(ETIQUETAS_ESTADO[$siguiente])) ?>)">
        <i class="fa-solid fa-arrow-right me-1"></i> <?= ACCION_SIGUIENTE[$estado] ?>
    </button>
    <?php elseif ($estado === 'entregado'): ?>
    <span class="small text-muted"><i class="fa-solid fa-circle-check me-1" style="color:#2f6b3b"></i> Pedido entregado. Proceso terminado.</span>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function avanzar_estado_pedido(id, estado_actual, etiqueta_siguiente){
    var btn = $('#btn_avanzar_pedido').prop('disabled', true);
    $.post("Pedidos/back_pedidos.php", { accion:"avanzar_estado", id:id, estado_actual:estado_actual }, function(resp){
        var r = JSON.parse(resp);
        if(r.ok){
            $("#modal_general").modal('hide');
            Swal.fire({title:"Pedido " + etiqueta_siguiente.toLowerCase(),icon:"success",timer:1500,showConfirmButton:false});
            cargar_pedidos();
        } else {
            btn.prop('disabled', false);
            Swal.fire({title:"No se pudo actualizar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        }
    });
}
</script>
