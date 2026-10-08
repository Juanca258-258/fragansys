<?php
require_once("../sesion.php");
require_once("../conexion.php");

if ($_SESSION['rol'] !== 'admin') {
    echo '<div class="panel vacio">No tienes permiso para ver el historial.</div>';
    exit;
}

$result = mysqli_query($conn,
    "SELECT m.*, u.nombre_completo, u.usuario
     FROM movimientos m
     LEFT JOIN usuarios u ON m.usuario_id = u.id
     ORDER BY m.id DESC
     LIMIT 1000");

$colores = [
    'Agregar'        => 'success',
    'Editar'         => 'warning text-dark',
    'Eliminar'       => 'danger',
    'Cambiar estado' => 'info text-dark',
    'Iniciar sesión' => 'secondary',
    'Cerrar sesión'  => 'secondary',
];
?>
<div class="page-header">
    <h1>Historial</h1>
    <div class="sub">Movimientos realizados en el panel (últimos 1000)</div>
</div>

<div class="panel">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_historial')){$('#tbl_historial').DataTable().search(this.value).draw();}">
    </div>
</div>

<table id="tbl_historial" class="table table-hover align-middle">
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
<?php while ($fila = mysqli_fetch_assoc($result)) { ?>
<tr>
    <td data-order="<?= $fila['fecha'] ?>" style="white-space:nowrap"><?= date('d/m/Y h:i A', strtotime($fila['fecha'])) ?></td>
    <td><?= htmlspecialchars($fila['nombre_completo'] ?? 'Usuario eliminado') ?></td>
    <td><?= htmlspecialchars($fila['modulo']) ?></td>
    <td><span class="badge bg-<?= $colores[$fila['accion']] ?? 'secondary' ?>"><?= htmlspecialchars($fila['accion']) ?></span></td>
    <td><?= htmlspecialchars($fila['descripcion']) ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
