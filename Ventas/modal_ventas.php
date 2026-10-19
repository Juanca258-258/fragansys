<?php
require_once("../sesion.php");
require_once("../conexion.php");

$es_admin = $_SESSION['rol'] === 'admin';
$result = mysqli_query($conn,
    "SELECT v.*, CONCAT(u.nombre, ' ', u.apellidos) AS vendedor
     FROM ventas v LEFT JOIN usuarios u ON v.usuario_id = u.id
     ORDER BY v.id DESC");
$metodos = [
    'efectivo'      => ['Efectivo', 'fa-money-bill-wave'],
    'transferencia' => ['Transferencia', 'fa-building-columns'],
    'tarjeta'       => ['Tarjeta', 'fa-credit-card'],
    'paypal'        => ['PayPal', 'fa-brands fa-paypal'],
];
$hoy = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS num, COALESCE(SUM(total), 0) AS total FROM ventas
     WHERE estado = 'completada' AND DATE(fecha_venta) = CURDATE()"));
?>
<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid fa-cart-shopping"></i></div>
    <div>
        <h1>Ventas</h1>
        <div class="sub">Ventas de mostrador con su ticket</div>
    </div>
    <div class="acciones-cab">
        <span class="dato-cab"><i class="fa-solid fa-receipt"></i> <strong><?= (int)$hoy['num'] ?></strong> venta<?= $hoy['num'] == 1 ? '' : 's' ?> hoy</span>
        <span class="dato-cab"><i class="fa-solid fa-sack-dollar"></i> <strong>$<?= number_format($hoy['total'], 2) ?></strong> hoy</span>
        <button class="btn-dorado" onclick="modal_g_ventas()"><i class="fa-solid fa-plus"></i> Nueva venta</button>
    </div>
</div>

<div class="panel panel-modulo">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar folio, cliente o vendedor..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_ventas')){$('#tbl_ventas').DataTable().search(this.value).draw();}">
    </div>
</div>

<div class="table-responsive">
<table id="tbl_ventas" class="table tabla-modulo align-middle">
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
    <td class="fw-semibold">V-<?= str_pad($v['id'], 6, '0', STR_PAD_LEFT) ?></td>
    <td data-order="<?= $v['fecha_venta'] ?>" style="white-space:nowrap">
        <?= date('d/m/Y', strtotime($v['fecha_venta'])) ?>
        <div class="small text-muted"><?= date('h:i A', strtotime($v['fecha_venta'])) ?></div>
    </td>
    <td><?= htmlspecialchars($v['cliente_nombre'] ?? 'Público general') ?></td>
    <td class="small"><?= htmlspecialchars($v['vendedor'] ?? '—') ?></td>
    <td>
        <?php [$m_txt, $m_ico] = $metodos[$v['metodo_pago']] ?? [$v['metodo_pago'], 'fa-money-bill']; ?>
        <span class="estado neutro"><i class="<?= strpos($m_ico, 'fa-brands') === 0 ? $m_ico : 'fa-solid ' . $m_ico ?>"></i> <?= $m_txt ?></span>
    </td>
    <td class="text-end fw-semibold">$<?= number_format($v['total'], 2) ?></td>
    <td>
        <?php if ($cancelada): ?>
        <span class="estado alerta"><i class="fa-solid fa-ban"></i> Cancelada</span>
        <?php else: ?>
        <span class="estado bien"><i class="fa-solid fa-check"></i> Completada</span>
        <?php endif; ?>
    </td>
    <td style="white-space:nowrap">
        <button class="btn-accion" onclick="window.open('Ventas/ticket.php?id=<?= $v['id'] ?>', '_blank')">
            <i class="fa-solid fa-receipt"></i> Ticket
        </button>
        <?php if ($es_admin && !$cancelada): ?>
        <button class="btn-accion peligro" onclick="cancelar_venta(<?= $v['id'] ?>)">
            <i class="fa-solid fa-ban"></i> Cancelar
        </button>
        <?php endif; ?>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
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
