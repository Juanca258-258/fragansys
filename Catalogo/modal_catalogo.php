<?php
require_once("../sesion.php");
require_once("../conexion.php");
$result = mysqli_query($conn, "SELECT * FROM catalogo WHERE activo = 1 ORDER BY marca, nombre");
?>
<div class="page-header">
    <h1>Catálogo</h1>
    <div class="sub">Fragancias registradas (marca, género y notas olfativas)</div>
</div>

<div class="panel">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_catalogo')){$('#tbl_catalogo').DataTable().search(this.value).draw();}">
    </div>
    <button class="btn-dorado" onclick="modal_g_catalogo()">
        <i class="fa-solid fa-plus"></i> Nueva fragancia
    </button>
</div>

<table id="tbl_catalogo" class="table table-hover align-middle">
<thead>
<tr>
    <th></th>
    <th>Nombre</th>
    <th>Marca</th>
    <th>Género</th>
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
    <td><?= htmlspecialchars($fila['marca']) ?></td>
    <td><span class="badge bg-secondary"><?= ucfirst($fila['genero']) ?></span></td>
    <td>
        <button class="btn btn-warning btn-sm" onclick="modal_act_catalogo(<?= $fila['id'] ?>)">
            <i class="fa-solid fa-pen"></i> Editar
        </button>
        <button class="btn btn-danger btn-sm" onclick="eliminar_catalogo(<?= $fila['id'] ?>)">
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
function eliminar_catalogo(id){
    Swal.fire({
        title: "¿Eliminar fragancia?",
        text: "Los productos ya creados con esta fragancia conservarán su historial.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if(result.isConfirmed){
            $.post("Catalogo/back_catalogo.php", { accion:"eliminar", id:id }, function(resp){
                var r = JSON.parse(resp);
                if(r.ok){
                    Swal.fire({title:"Eliminada",text:"La fragancia fue eliminada correctamente",icon:"success",timer:1500,showConfirmButton:false});
                    cargar_catalogo();
                } else {
                    Swal.fire({title:"No permitido",text:r.error,icon:"error",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
                }
            });
        }
    });
}
</script>
