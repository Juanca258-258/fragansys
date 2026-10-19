<?php
require_once("../sesion.php");
require_once("../conexion.php");

if ($_SESSION['rol'] !== 'admin') {
    echo '<div class="panel vacio">No tienes permiso para ver el historial.</div>';
    exit;
}

$result = mysqli_query($conn,
    "SELECT m.*, CONCAT(u.nombre, ' ', u.apellidos) AS nombre_completo, u.usuario
     FROM movimientos m
     LEFT JOIN usuarios u ON m.usuario_id = u.id
     ORDER BY m.id DESC
     LIMIT 1000");

$acciones = [
    'Agregar'        => ['bien',   'fa-plus'],
    'Editar'         => ['aviso',  'fa-pen'],
    'Eliminar'       => ['alerta', 'fa-trash'],
    'Cambiar estado' => ['info',   'fa-arrow-right'],
    'Entrada'        => ['bien',   'fa-box'],
    'Ajuste de ml'   => ['aviso',  'fa-sliders'],
    'Anular'         => ['alerta', 'fa-ban'],
    'Cancelar'       => ['alerta', 'fa-ban'],
    'Abrir frasco'   => ['info',   'fa-flask'],
    'Iniciar sesión' => ['neutro', 'fa-right-to-bracket'],
    'Cerrar sesión'  => ['neutro', 'fa-right-from-bracket'],
];
$modulos = [
    'Perfumes'   => 'fa-spray-can-sparkles',
    'Inventario' => 'fa-boxes-stacked',
    'Ventas'     => 'fa-cart-shopping',
    'Pedidos'    => 'fa-truck-fast',
    'Usuarios'   => 'fa-users',
    'Sesión'     => 'fa-key',
];
$hoy = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM movimientos WHERE DATE(fecha) = CURDATE()"))['n'];
?>
<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid fa-clock-rotate-left"></i></div>
    <div>
        <h1>Historial</h1>
        <div class="sub">Movimientos realizados en el panel (últimos 1000)</div>
    </div>
    <div class="acciones-cab">
        <span class="dato-cab"><i class="fa-solid fa-calendar-day"></i> <strong><?= (int)$hoy ?></strong> movimiento<?= $hoy == 1 ? '' : 's' ?> hoy</span>
    </div>
</div>

<div class="panel panel-modulo">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar usuario, módulo o descripción..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_historial')){$('#tbl_historial').DataTable().search(this.value).draw();}">
    </div>
</div>

<div class="table-responsive">
<table id="tbl_historial" class="table tabla-modulo align-middle">
<thead>
<tr>
    <th>Fecha</th>
    <th>Usuario</th>
    <th>Módulo</th>
    <th>Acción</th>
    <th>Descripción</th>
</tr>
</thead>
<tbody>
<?php while ($fila = mysqli_fetch_assoc($result)) {
    [$acc_clase, $acc_ico] = $acciones[$fila['accion']] ?? ['neutro', 'fa-circle'];
?>
<tr>
    <td data-order="<?= $fila['fecha'] ?>" style="white-space:nowrap">
        <?= date('d/m/Y', strtotime($fila['fecha'])) ?>
        <div class="small text-muted"><?= date('h:i A', strtotime($fila['fecha'])) ?></div>
    </td>
    <td class="small"><?= htmlspecialchars($fila['nombre_completo'] ?? 'Usuario eliminado') ?></td>
    <td style="white-space:nowrap">
        <i class="fa-solid <?= $modulos[$fila['modulo']] ?? 'fa-folder' ?> me-1" style="color:var(--dorado-oscuro)"></i>
        <?= htmlspecialchars($fila['modulo']) ?>
    </td>
    <td><span class="estado <?= $acc_clase ?>"><i class="fa-solid <?= $acc_ico ?>"></i> <?= htmlspecialchars($fila['accion']) ?></span></td>
    <td><?= htmlspecialchars($fila['descripcion']) ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
