<?php
require_once("../sesion.php");
require_once("../conexion.php");

if ($_SESSION['rol'] !== 'admin') {
    echo '<div class="panel vacio">No tienes permiso para ver el inventario.</div>';
    exit;
}

$result = mysqli_query($conn,
    "SELECT e.*, p.nombre AS perfume, p.marca, CONCAT(u.nombre, ' ', u.apellidos) AS registro
     FROM entradas_inventario e
     INNER JOIN perfumes p ON e.perfume_id = p.id
     LEFT JOIN usuarios u ON e.usuario_id = u.id
     ORDER BY e.fecha_entrada DESC, e.id DESC");
?>
<div class="page-header">
    <h1>Inventario</h1>
    <div class="sub">Entradas de mercancía: frascos sellados que llegan y su costo</div>
</div>

<div class="panel">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_inventario')){$('#tbl_inventario').DataTable().search(this.value).draw();}">
    </div>
    <div class="d-flex gap-2">
        <button class="btn-dorado-outline" onclick="modal_ajuste_inventario()">
            <i class="fa-solid fa-sliders"></i> Ajuste de ml
        </button>
        <button class="btn-dorado" onclick="modal_g_inventario()">
            <i class="fa-solid fa-plus"></i> Registrar entrada
        </button>
    </div>
</div>

<table id="tbl_inventario" class="table table-hover align-middle">
<thead>
<tr>
    <th>Fecha</th>
    <th>Perfume</th>
    <th class="text-end">Frascos</th>
    <th class="text-end">Costo c/u</th>
    <th class="text-end">Total</th>
    <th>Proveedor</th>
    <th>Registró</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php while ($e = mysqli_fetch_assoc($result)) {
    $anulada = $e['estado'] === 'anulada';
?>
<tr class="<?= $anulada ? 'text-muted' : '' ?>">
    <td data-order="<?= $e['fecha_entrada'] ?>" style="white-space:nowrap"><?= date('d/m/Y', strtotime($e['fecha_entrada'])) ?></td>
    <td>
        <div class="fw-semibold"><?= htmlspecialchars($e['perfume']) ?></div>
        <div class="small text-muted"><?= htmlspecialchars($e['marca']) ?></div>
    </td>
    <td class="text-end"><?= (int)$e['frascos'] ?></td>
    <td class="text-end">$<?= number_format($e['costo_unitario'], 2) ?></td>
    <td class="text-end fw-semibold">$<?= number_format($e['frascos'] * $e['costo_unitario'], 2) ?></td>
    <td>
        <?= htmlspecialchars($e['proveedor'] ?? '—') ?>
        <?php if ($e['notas']): ?><div class="small text-muted"><?= htmlspecialchars($e['notas']) ?></div><?php endif; ?>
    </td>
    <td class="small"><?= htmlspecialchars($e['registro'] ?? '—') ?></td>
    <td><span class="badge bg-<?= $anulada ? 'secondary' : 'success' ?>"><?= $anulada ? 'Anulada' : 'Registrada' ?></span></td>
    <td>
        <?php if (!$anulada): ?>
        <button class="btn btn-danger btn-sm" onclick="anular_entrada(<?= $e['id'] ?>, <?= (int)$e['frascos'] ?>)">
            <i class="fa-solid fa-ban"></i> Anular
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
function anular_entrada(id, frascos){
    Swal.fire({
        title: "¿Anular esta entrada?",
        text: "Se restarán " + frascos + " frasco(s) sellados del perfume. La entrada quedará marcada como anulada.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, anular",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if(!result.isConfirmed) return;
        $.post("Inventario/back_inventario.php", { accion:"anular", id:id }, function(resp){
            var r = JSON.parse(resp);
            if(r.ok){
                Swal.fire({title:"Entrada anulada",icon:"success",timer:1300,showConfirmButton:false});
                cargar_inventario();
            } else {
                Swal.fire({title:"No se pudo anular",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
            }
        });
    });
}
</script>
