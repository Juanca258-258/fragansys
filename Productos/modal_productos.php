<?php
require_once("../sesion.php");
require_once("../conexion.php");
$result = mysqli_query($conn,
    "SELECT p.*, c.nombre AS catalogo_nombre, c.marca AS catalogo_marca
     FROM productos p
     LEFT JOIN catalogo c ON p.catalogo_id = c.id
     WHERE p.activo = 1
     ORDER BY p.fecha_registro DESC");
?>
<div class="page-header">
    <h1>Productos</h1>
    <div class="sub">Decants y frascos disponibles para la venta</div>
</div>

<div class="panel">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_productos')){$('#tbl_productos').DataTable().search(this.value).draw();}">
    </div>
    <button class="btn-dorado" onclick="modal_g_productos()">
        <i class="fa-solid fa-plus"></i> Nuevo producto
    </button>
</div>

<table id="tbl_productos" class="table table-hover align-middle">
<thead>
<tr>
    <th></th>
    <th>Producto</th>
    <th>Fragancia</th>
    <th>Tipo</th>
    <th>Contenido</th>
    <th>Precio</th>
    <th>Stock</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php while ($fila = mysqli_fetch_assoc($result)) { ?>
<tr>
    <td style="width:48px">
        <?php if ($fila['imagen']): ?>
            <img src="../<?= htmlspecialchars($fila['imagen']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--borde)">
        <?php else: ?>
            <div style="width:40px;height:40px;border-radius:8px;background:var(--crema-2);display:flex;align-items:center;justify-content:center;color:var(--texto-suave)">
                <i class="fa-solid fa-flask"></i>
            </div>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($fila['nombre']) ?></td>
    <td>
        <?php if ($fila['catalogo_nombre']): ?>
            <?= htmlspecialchars($fila['catalogo_marca']) ?> — <?= htmlspecialchars($fila['catalogo_nombre']) ?>
        <?php else: ?>
            <span class="text-muted">Sin asignar</span>
        <?php endif; ?>
    </td>
    <td><span class="badge bg-secondary"><?= $fila['tipo'] == 'decant' ? 'Decant' : 'Frasco completo' ?></span></td>
    <td><?= number_format($fila['contenido_ml'], 2) ?> ml</td>
    <td>$<?= number_format($fila['precio'], 2) ?></td>
    <td>
        <span class="badge bg-<?= $fila['stock'] <= 0 ? 'danger' : ($fila['stock'] <= 3 ? 'warning text-dark' : 'success') ?>">
            <?= (int)$fila['stock'] ?>
        </span>
    </td>
    <td>
        <button class="btn btn-warning btn-sm" onclick="modal_act_productos(<?= $fila['id'] ?>)">
            <i class="fa-solid fa-pen"></i> Editar
        </button>
        <button class="btn btn-danger btn-sm" onclick="eliminar_producto(<?= $fila['id'] ?>)">
            <i class="fa-solid fa-trash"></i> Eliminar
        </button>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function eliminar_producto(id){
    Swal.fire({
        title: "¿Eliminar producto?",
        text: "No podrás revertir esta acción",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if(result.isConfirmed){
            $.post("Productos/back_productos.php", { accion:"eliminar", id:id }, function(resp){
                var r = JSON.parse(resp);
                if(r.ok){
                    Swal.fire({title:"Eliminado",text:"El producto fue eliminado correctamente",icon:"success",timer:1500,showConfirmButton:false});
                    cargar_productos();
                } else {
                    Swal.fire({title:"No permitido",text:r.error,icon:"error",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
                }
            });
        }
    });
}
</script>
