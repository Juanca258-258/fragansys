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

$mes = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(frascos), 0) AS frascos, COALESCE(SUM(frascos * costo_unitario), 0) AS gasto
     FROM entradas_inventario
     WHERE estado = 'registrada' AND fecha_entrada >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"));
?>
<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid fa-boxes-stacked"></i></div>
    <div>
        <h1>Inventario</h1>
        <div class="sub">Entradas de mercancía: frascos sellados que llegan y su costo</div>
    </div>
    <div class="acciones-cab">
        <span class="dato-cab" title="Frascos registrados este mes"><i class="fa-solid fa-box"></i> <strong><?= (int)$mes['frascos'] ?></strong> frascos este mes</span>
        <span class="dato-cab" title="Gasto en entradas este mes"><i class="fa-solid fa-money-bill-wave"></i> <strong>$<?= number_format($mes['gasto'], 2) ?></strong> este mes</span>
        <button class="btn-dorado-outline" onclick="modal_ajuste_inventario()"><i class="fa-solid fa-sliders"></i> Ajuste de ml</button>
        <button class="btn-dorado" onclick="modal_g_inventario()"><i class="fa-solid fa-plus"></i> Registrar entrada</button>
    </div>
</div>

<div class="panel panel-modulo">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar perfume o proveedor..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_inventario')){$('#tbl_inventario').DataTable().search(this.value).draw();}">
    </div>
</div>

<div class="table-responsive">
<table id="tbl_inventario" class="table tabla-modulo align-middle">
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
    <td>
        <?php if ($anulada): ?>
        <span class="estado neutro"><i class="fa-solid fa-ban"></i> Anulada</span>
        <?php else: ?>
        <span class="estado bien"><i class="fa-solid fa-check"></i> Registrada</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if (!$anulada): ?>
        <button class="btn-accion peligro" onclick="anular_entrada(<?= $e['id'] ?>, <?= (int)$e['frascos'] ?>)">
            <i class="fa-solid fa-ban"></i> Anular
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
