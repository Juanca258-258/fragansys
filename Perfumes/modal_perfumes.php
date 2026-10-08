<?php
require_once("../sesion.php");
require_once("../conexion.php");

$perfumes = mysqli_fetch_all(mysqli_query($conn,
    "SELECT * FROM perfumes WHERE activo = 1 ORDER BY marca, nombre"), MYSQLI_ASSOC);

$presentaciones = [];
$res = mysqli_query($conn,
    "SELECT perfume_id, tipo, ml, precio FROM presentaciones
     WHERE activo = 1 ORDER BY tipo = 'frasco', ml");
while ($pr = mysqli_fetch_assoc($res)) {
    $presentaciones[$pr['perfume_id']][] = $pr;
}
?>
<div class="page-header">
    <h1>Perfumes</h1>
    <div class="sub">Inventario de frascos y decants, con sus precios de venta</div>
</div>

<div class="panel">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_perfumes')){$('#tbl_perfumes').DataTable().search(this.value).draw();}">
    </div>
    <button class="btn-dorado" onclick="modal_g_perfumes()">
        <i class="fa-solid fa-plus"></i> Nuevo perfume
    </button>
</div>

<table id="tbl_perfumes" class="table table-hover align-middle">
<thead>
<tr>
    <th></th>
    <th>Perfume</th>
    <th>Género</th>
    <th>Frascos sellados</th>
    <th>Para decants</th>
    <th>Presentaciones</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($perfumes as $p) {
    $ml_bajo = $p['ml_disponibles'] <= 15;
?>
<tr>
    <td style="width:48px">
        <?php if ($p['imagen']): ?>
            <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--borde)">
        <?php endif; ?>
    </td>
    <td>
        <div class="fw-semibold"><?= htmlspecialchars($p['nombre']) ?></div>
        <div class="small text-muted"><?= htmlspecialchars($p['marca']) ?></div>
    </td>
    <td><span class="badge bg-secondary"><?= ucfirst($p['genero']) ?></span></td>
    <td>
        <span class="badge bg-<?= $p['frascos_sellados'] > 0 ? 'success' : 'secondary' ?>"><?= (int)$p['frascos_sellados'] ?></span>
        <span class="small text-muted">de <?= (float)$p['ml_por_frasco'] ?> ml</span>
    </td>
    <td class="<?= $ml_bajo ? 'dato-alerta' : '' ?>"><?= (float)$p['ml_disponibles'] ?> ml</td>
    <td class="small">
        <?php foreach ($presentaciones[$p['id']] ?? [] as $pr): ?>
            <div>
                <?= $pr['tipo'] == 'frasco' ? 'Frasco' : 'Decant' ?> <?= (float)$pr['ml'] ?> ml
                · <strong>$<?= number_format($pr['precio'], 2) ?></strong>
            </div>
        <?php endforeach; ?>
    </td>
    <td style="white-space:nowrap">
        <button class="btn btn-warning btn-sm" onclick="modal_act_perfumes(<?= $p['id'] ?>)">
            <i class="fa-solid fa-pen"></i> Editar
        </button>
        <button class="btn btn-info btn-sm" onclick="abrir_frasco(<?= $p['id'] ?>, <?= htmlspecialchars(json_encode($p['nombre'])) ?>, <?= (float)$p['ml_por_frasco'] ?>)"
                <?= $p['frascos_sellados'] > 0 ? '' : 'disabled title="No hay frascos sellados"' ?>>
            <i class="fa-solid fa-flask"></i> Abrir frasco
        </button>
        <button class="btn btn-danger btn-sm" onclick="eliminar_perfume(<?= $p['id'] ?>)">
            <i class="fa-solid fa-trash"></i>
        </button>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function abrir_frasco(id, nombre, ml){
    Swal.fire({
        title: "¿Abrir un frasco?",
        text: "Se descontará 1 frasco sellado de " + nombre + " y se sumarán " + ml + " ml para decants.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#c69a4c",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, abrir",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if(!result.isConfirmed) return;
        $.post("Perfumes/back_perfumes.php", { accion:"abrir_frasco", id:id }, function(resp){
            var r = JSON.parse(resp);
            if(r.ok){
                Swal.fire({title:"Frasco abierto",icon:"success",timer:1300,showConfirmButton:false});
                cargar_perfumes();
            } else {
                Swal.fire({title:"No se pudo abrir",text:r.error,icon:"warning",confirmButtonColor:"#d33"});
            }
        });
    });
}

function eliminar_perfume(id){
    Swal.fire({
        title: "¿Eliminar perfume?",
        text: "Dejará de mostrarse en el panel y en la tienda.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if(!result.isConfirmed) return;
        $.post("Perfumes/back_perfumes.php", { accion:"eliminar", id:id }, function(resp){
            var r = JSON.parse(resp);
            if(r.ok){
                Swal.fire({title:"Eliminado",icon:"success",timer:1300,showConfirmButton:false});
                cargar_perfumes();
            } else {
                Swal.fire({title:"No permitido",text:r.error,icon:"error",confirmButtonColor:"#d33"});
            }
        });
    });
}
</script>
