<?php
require_once("../sesion.php");
require_once("../conexion.php");
$result = mysqli_query($conn, "SELECT * FROM pedidos ORDER BY fecha_pedido DESC");

$badges_pedido = [
    'nuevo'          => 'secondary',
    'confirmado'     => 'info text-dark',
    'en_preparacion' => 'warning text-dark',
    'enviado'        => 'primary',
    'entregado'      => 'success',
    'cancelado'      => 'danger',
];
$badges_pago = [
    'pendiente'   => 'secondary',
    'pagado'      => 'success',
    'fallido'     => 'danger',
    'reembolsado' => 'dark',
];
$etiquetas_estado = [
    'nuevo'          => 'Nuevo',
    'confirmado'     => 'Confirmado',
    'en_preparacion' => 'En preparación',
    'enviado'        => 'Enviado',
    'entregado'      => 'Entregado',
    'cancelado'      => 'Cancelado',
];
?>
<div class="page-header">
    <h1>Pedidos</h1>
    <div class="sub">Órdenes de compra realizadas desde la tienda en línea</div>
</div>

<div class="panel">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_pedidos')){$('#tbl_pedidos').DataTable().search(this.value).draw();}">
    </div>
</div>

<table id="tbl_pedidos" class="table table-hover align-middle">
<thead>
<tr>
    <th>#</th>
    <th>Cliente</th>
    <th>Fecha</th>
    <th>Total</th>
    <th>Pago</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php while ($fila = mysqli_fetch_assoc($result)) { ?>
<tr>
    <td>#<?= $fila['id'] ?></td>
    <td><?= htmlspecialchars($fila['cliente_nombre']) ?></td>
    <td><?= date('d/m/Y h:i A', strtotime($fila['fecha_pedido'])) ?></td>
    <td class="fw-bold">$<?= number_format($fila['total'], 2) ?></td>
    <td><span class="badge bg-<?= $badges_pago[$fila['estado_pago']] ?>"><?= ucfirst($fila['estado_pago']) ?></span></td>
    <td><span class="badge bg-<?= $badges_pedido[$fila['estado_pedido']] ?>"><?= $etiquetas_estado[$fila['estado_pedido']] ?></span></td>
    <td>
        <button class="btn btn-warning btn-sm" onclick="modal_detalle_pedido(<?= $fila['id'] ?>)">
            <i class="fa-solid fa-eye"></i> Ver / Actualizar
        </button>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
