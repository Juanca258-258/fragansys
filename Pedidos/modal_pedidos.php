<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once(__DIR__ . "/flujo_pedidos.php");

// La vista trae cada pedido con el resumen de sus productos ya armado.
$result = mysqli_query($conn, "SELECT * FROM vw_resumen_pedidos ORDER BY fecha_pedido DESC");

// Cuántos pedidos hay en cada estado, para las tarjetas de filtro.
$cuentas = array_fill_keys(array_keys(ETIQUETAS_ESTADO), 0);
$res = mysqli_query($conn, "SELECT estado_pedido, COUNT(*) AS n FROM pedidos GROUP BY estado_pedido");
while ($c = mysqli_fetch_assoc($res)) {
    $cuentas[$c["estado_pedido"]] = (int)$c["n"];
}

$estilo_pedido = [
    'nuevo'          => ['aviso',  'fa-bell'],
    'confirmado'     => ['info',   'fa-thumbs-up'],
    'en_preparacion' => ['info',   'fa-box-open'],
    'enviado'        => ['info',   'fa-truck-fast'],
    'entregado'      => ['bien',   'fa-circle-check'],
    'cancelado'      => ['alerta', 'fa-ban'],
];
$estilo_pago = [
    'pendiente'   => ['aviso',  'fa-hourglass-half', 'Pendiente'],
    'pagado'      => ['bien',   'fa-check',          'Pagado'],
    'fallido'     => ['alerta', 'fa-xmark',          'Fallido'],
    'reembolsado' => ['neutro', 'fa-rotate-left',    'Reembolsado'],
];
?>
<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid fa-truck-fast"></i></div>
    <div>
        <h1>Pedidos</h1>
        <div class="sub">Órdenes de compra realizadas desde la tienda en línea</div>
    </div>
</div>

<div class="panel panel-modulo">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3 flex-wrap barra-pedidos">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar pedido o cliente..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_pedidos')){$('#tbl_pedidos').DataTable().search(this.value).draw();}">
    </div>
    <div class="filtros-estado">
        <button type="button" class="filtro-estado" data-estado="" onclick="filtrar_pedidos('')">
            <i class="fa-solid fa-layer-group"></i><span class="num"><?= array_sum($cuentas) ?></span><span class="txt">Todos</span>
        </button>
        <?php foreach (ETIQUETAS_ESTADO as $clave => $etiqueta): [$clase, $ico] = $estilo_pedido[$clave]; ?>
        <button type="button" class="filtro-estado <?= $clase ?><?= $cuentas[$clave] ? '' : ' vacio-f' ?>" data-estado="<?= $clave ?>" onclick="filtrar_pedidos('<?= $clave ?>')">
            <i class="fa-solid <?= $ico ?>"></i><span class="num"><?= $cuentas[$clave] ?></span><span class="txt"><?= $etiqueta ?></span>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<div class="table-responsive">
<table id="tbl_pedidos" class="table tabla-modulo align-middle">
<thead>
<tr>
    <th>#</th>
    <th>Cliente</th>
    <th>Fecha</th>
    <th>Productos</th>
    <th>Total</th>
    <th>Pago</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php while ($fila = mysqli_fetch_assoc($result)) {
    [$ped_clase, $ped_ico] = $estilo_pedido[$fila['estado_pedido']] ?? ['neutro', 'fa-circle'];
    [$pago_clase, $pago_ico, $pago_txt] = $estilo_pago[$fila['estado_pago']] ?? ['neutro', 'fa-circle', ucfirst($fila['estado_pago'])];
?>
<tr>
    <td class="fw-semibold">#<?= $fila['pedido_id'] ?></td>
    <td>
        <?= htmlspecialchars($fila['cliente_nombre']) ?>
        <div class="small text-muted"><?= htmlspecialchars($fila['cliente_correo']) ?></div>
    </td>
    <td data-order="<?= $fila['fecha_pedido'] ?>" style="white-space:nowrap">
        <?= date('d/m/Y', strtotime($fila['fecha_pedido'])) ?>
        <div class="small text-muted"><?= date('h:i A', strtotime($fila['fecha_pedido'])) ?></div>
    </td>
    <td class="small" style="max-width:280px">
        <?= htmlspecialchars($fila['productos'] ?? '—') ?>
        <div class="text-muted"><?= (int)$fila['total_piezas'] ?> pieza<?= $fila['total_piezas'] == 1 ? '' : 's' ?></div>
    </td>
    <td class="fw-semibold">$<?= number_format($fila['total'], 2) ?></td>
    <td><span class="estado <?= $pago_clase ?>"><i class="fa-solid <?= $pago_ico ?>"></i> <?= $pago_txt ?></span></td>
    <td data-search="<?= $fila['estado_pedido'] ?> <?= ETIQUETAS_ESTADO[$fila['estado_pedido']] ?? '' ?>"><span class="estado <?= $ped_clase ?>"><i class="fa-solid <?= $ped_ico ?>"></i> <?= ETIQUETAS_ESTADO[$fila['estado_pedido']] ?? $fila['estado_pedido'] ?></span></td>
    <td>
        <button class="btn-accion" onclick="modal_detalle_pedido(<?= $fila['pedido_id'] ?>)">
            <i class="fa-solid fa-eye"></i> Ver pedido
        </button>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
