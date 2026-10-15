<?php
require_once("../sesion.php");
require_once("../conexion.php");

$es_admin = $_SESSION['rol'] === 'admin';
$result = mysqli_query($conn,
    "SELECT v.*, CONCAT(u.nombre, ' ', u.apellidos) AS vendedor
     FROM ventas v LEFT JOIN usuarios u ON v.usuario_id = u.id
     ORDER BY v.id DESC");
$metodos = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'paypal' => 'PayPal'];
?>
<div class="page-header">
    <h1>Ventas</h1>
    <div class="sub">Ventas de mostrador con su ticket</div>
</div>

<div class="panel">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_ventas')){$('#tbl_ventas').DataTable().search(this.value).draw();}">
    </div>
    <button class="btn-dorado" onclick="modal_g_ventas()">
        <i class="fa-solid fa-plus"></i> Nueva venta
    </button>
</div>

<table id="tbl_ventas" class="table table-hover align-middle">
<thead>
<tr>
    <th>Folio</th>
    <th>Fecha</th>
    <th>Cliente</th>
    <th>Vendedor</th>
    <th>Pago</th>
    <th class="text-end">Total</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php while ($v = mysqli_fetch_assoc($result)) {
    $cancelada = $v['estado'] === 'cancelada';
?>
<tr class="<?= $cancelada ? 'text-muted' : '' ?>">
    <td>V-<?= str_pad($v['id'], 6, '0', STR_PAD_LEFT) ?></td>
    <td data-order="<?= $v['fecha_venta'] ?>" style="white-space:nowrap"><?= date('d/m/Y h:i A', strtotime($v['fecha_venta'])) ?></td>
    <td><?= htmlspecialchars($v['cliente_nombre'] ?? 'Público general') ?></td>
    <td class="small"><?= htmlspecialchars($v['vendedor'] ?? '—') ?></td>
    <td><?= $metodos[$v['metodo_pago']] ?? $v['metodo_pago'] ?></td>
    <td class="text-end fw-semibold">$<?= number_format($v['total'], 2) ?></td>
    <td><span class="badge bg-<?= $cancelada ? 'secondary' : 'success' ?>"><?= $cancelada ? 'Cancelada' : 'Completada' ?></span></td>
    <td style="white-space:nowrap">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.open('Ventas/ticket.php?id=<?= $v['id'] ?>', '_blank')">
            <i class="fa-solid fa-receipt"></i> Ticket
        </button>
        <?php if ($es_admin && !$cancelada): ?>
        <button class="btn btn-danger btn-sm" onclick="cancelar_venta(<?= $v['id'] ?>)">
            <i class="fa-solid fa-ban"></i> Cancelar
        </button>
        <?php endif; ?>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function cancelar_venta(id){
    Swal.fire({
        title: "¿Cancelar esta venta?",
        text: "Se devolverá el inventario y la venta quedará marcada como cancelada.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, cancelar venta",
        cancelButtonText: "No"
    }).then((result) => {
        if(!result.isConfirmed) return;
        $.post("Ventas/back_ventas.php", { accion:"cancelar", id:id }, function(resp){
            var r = JSON.parse(resp);
            if(r.ok){
                Swal.fire({title:"Venta cancelada",icon:"success",timer:1300,showConfirmButton:false});
                cargar_ventas();
            } else {
                Swal.fire({title:"No se pudo cancelar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
            }
        });
    });
}
</script>
