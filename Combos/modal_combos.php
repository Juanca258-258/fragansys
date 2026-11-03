<?php
require_once("../sesion.php");
require_once("../conexion.php");

$combos = mysqli_fetch_all(mysqli_query($conn,
    "SELECT * FROM combos WHERE activo = 1 ORDER BY nombre"), MYSQLI_ASSOC);

$items = [];
$res = mysqli_query($conn,
    "SELECT cp.combo_id, cp.cantidad, pr.tipo, pr.ml, pr.precio, pr.activo AS pres_activa,
            pf.id AS perfume_id, pf.nombre, pf.activo AS perf_activo, pf.frascos_sellados, pf.ml_disponibles
     FROM combo_presentaciones cp
     INNER JOIN presentaciones pr ON pr.id = cp.presentacion_id
     INNER JOIN perfumes pf ON pf.id = pr.perfume_id
     ORDER BY cp.id");
while ($f = mysqli_fetch_assoc($res)) {
    $items[$f['combo_id']][] = $f;
}

// Cuántos combos completos alcanzan: los decants del mismo perfume comparten los ml del frasco abierto.
function combos_disponibles($lista) {
    $necesita = [];
    $stock = [];
    foreach ($lista as $it) {
        if (!$it['pres_activa'] || !$it['perf_activo']) return 0;
        $pid = $it['perfume_id'];
        $necesita[$pid] = $necesita[$pid] ?? ['frascos' => 0, 'ml' => 0];
        $stock[$pid] = ['frascos' => (int)$it['frascos_sellados'], 'ml' => (float)$it['ml_disponibles']];
        if ($it['tipo'] === 'frasco') {
            $necesita[$pid]['frascos'] += $it['cantidad'];
        } else {
            $necesita[$pid]['ml'] += $it['ml'] * $it['cantidad'];
        }
    }
    $posibles = PHP_INT_MAX;
    foreach ($necesita as $pid => $n) {
        if ($n['frascos'] > 0) $posibles = min($posibles, intdiv($stock[$pid]['frascos'], $n['frascos']));
        if ($n['ml'] > 0)      $posibles = min($posibles, (int)floor($stock[$pid]['ml'] / $n['ml']));
    }
    return $posibles === PHP_INT_MAX ? 0 : $posibles;
}
?>
<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid fa-gift"></i></div>
    <div>
        <h1>Combos</h1>
        <div class="sub">Paquetes de varios productos a un precio especial</div>
    </div>
    <div class="acciones-cab">
        <span class="dato-cab"><i class="fa-solid fa-gift"></i> <strong><?= count($combos) ?></strong> combo<?= count($combos) == 1 ? '' : 's' ?></span>
        <button class="btn-dorado" onclick="modal_g_combos()"><i class="fa-solid fa-plus"></i> Nuevo combo</button>
    </div>
</div>

<div class="panel panel-modulo">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar combo o perfume..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_combos')){$('#tbl_combos').DataTable().search(this.value).draw();}">
    </div>
</div>

<div class="table-responsive">
<table id="tbl_combos" class="table tabla-modulo align-middle">
<thead>
<tr>
    <th></th>
    <th>Combo</th>
    <th>Incluye</th>
    <th>Precio</th>
    <th>Disponibles</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($combos as $c) {
    $lista = $items[$c['id']] ?? [];
    $por_separado = 0;
    foreach ($lista as $it) $por_separado += $it['precio'] * $it['cantidad'];
    $ahorro = $por_separado - $c['precio'];
    $disponibles = combos_disponibles($lista);
?>
<tr>
    <td style="width:56px">
        <?php if ($c['imagen']): ?>
            <img src="<?= htmlspecialchars($c['imagen']) ?>" alt="" class="foto-mini">
        <?php else: ?>
            <div class="foto-mini d-flex align-items-center justify-content-center" style="background:var(--crema-2); color:var(--dorado-oscuro)"><i class="fa-solid fa-gift"></i></div>
        <?php endif; ?>
    </td>
    <td style="max-width:240px">
        <div class="fw-semibold"><?= htmlspecialchars($c['nombre']) ?></div>
        <?php if ($c['descripcion']): ?><div class="small text-muted"><?= htmlspecialchars($c['descripcion']) ?></div><?php endif; ?>
    </td>
    <td>
        <?php foreach ($lista as $it): ?>
        <span class="chip">
            <strong><?= (int)$it['cantidad'] ?> x</strong>
            <?= htmlspecialchars($it['nombre']) ?> · <?= $it['tipo'] === 'frasco' ? 'Frasco' : 'Decant' ?> <?= (float)$it['ml'] ?> ml
        </span>
        <?php endforeach; ?>
    </td>
    <td data-order="<?= (float)$c['precio'] ?>" style="white-space:nowrap">
        <div class="fw-semibold">$<?= number_format($c['precio'], 2) ?></div>
        <div class="small text-muted">Por separado $<?= number_format($por_separado, 2) ?></div>
        <?php if ($ahorro > 0): ?>
        <span class="estado bien mt-1"><i class="fa-solid fa-tag"></i> Ahorra $<?= number_format($ahorro, 2) ?></span>
        <?php elseif ($ahorro < 0): ?>
        <span class="estado aviso mt-1"><i class="fa-solid fa-triangle-exclamation"></i> Más caro que por separado</span>
        <?php endif; ?>
    </td>
    <td data-order="<?= $disponibles ?>">
        <?php if ($disponibles > 0): ?>
        <span class="estado bien"><i class="fa-solid fa-check"></i> <?= $disponibles ?></span>
        <?php else: ?>
        <span class="estado alerta"><i class="fa-solid fa-ban"></i> Sin inventario</span>
        <?php endif; ?>
    </td>
    <td style="white-space:nowrap">
        <button class="btn-accion" onclick="modal_act_combos(<?= $c['id'] ?>)"><i class="fa-solid fa-pen"></i> Editar</button>
        <button class="btn-accion peligro" title="Eliminar" onclick="eliminar_combo(<?= $c['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
    </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>

<script>
function eliminar_combo(id){
    Swal.fire({
        title: "¿Eliminar combo?",
        text: "Dejará de mostrarse en el panel y en la tienda.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if(!result.isConfirmed) return;
        $.post("Combos/back_combos.php", { accion:"eliminar", id:id }, function(resp){
            var r = JSON.parse(resp);
            if(r.ok){
                Swal.fire({title:"Eliminado",icon:"success",timer:1300,showConfirmButton:false});
                cargar_combos();
            } else {
                Swal.fire({title:"No permitido",text:r.error,icon:"error",confirmButtonColor:"#d33"});
            }
        });
    });
}
</script>
