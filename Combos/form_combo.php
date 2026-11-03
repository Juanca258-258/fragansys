<?php
// Espera $c (combo o null si es nuevo) y $productos ([['presentacion_id'=>, 'cantidad'=>], ...]).
$es_nuevo = $c === null;

$opciones = mysqli_fetch_all(mysqli_query($conn,
    "SELECT presentacion_id, marca, nombre, presentacion, precio, unidades_disponibles
     FROM vw_catalogo_presentaciones ORDER BY marca, nombre, tipo = 'frasco', ml"), MYSQLI_ASSOC);

$select_producto = function ($seleccionado) use ($opciones) {
    $html = '<select name="presentacion_id[]" class="form-select sel-producto-combo" onchange="calcular_combo()">'
          . '<option value="" data-precio="0">Selecciona un producto...</option>';
    foreach ($opciones as $o) {
        $html .= '<option value="' . $o['presentacion_id'] . '" data-precio="' . $o['precio'] . '"'
               . ((int)$seleccionado === (int)$o['presentacion_id'] ? ' selected' : '') . '>'
               . htmlspecialchars($o['marca'] . ' · ' . $o['nombre'] . ' — ' . $o['presentacion'])
               . ' · $' . number_format($o['precio'], 2) . ' (disp. ' . (int)$o['unidades_disponibles'] . ')</option>';
    }
    return $html . '</select>';
};
$filas = $productos ?: [['presentacion_id' => 0, 'cantidad' => 1], ['presentacion_id' => 0, 'cantidad' => 1]];
?>
<div class="modal-header">
    <h5 class="modal-title"><?= $es_nuevo ? 'Nuevo combo' : 'Editar combo' ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_combo" enctype="multipart/form-data" onsubmit="return false;">
    <input type="hidden" name="accion" value="<?= $es_nuevo ? 'guardar' : 'actualizar' ?>">
    <?php if (!$es_nuevo): ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><?php endif; ?>

    <div class="row">
        <div class="col-md-8 mb-3">
            <label>Nombre del combo</label>
            <input type="text" name="nombre" class="form-control" maxlength="150" value="<?= htmlspecialchars($c['nombre'] ?? '') ?>"
                   placeholder="Ej. Kit Descubrimiento Hombre" required>
        </div>
        <div class="col-md-4 mb-3">
            <label>Precio del combo</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" name="precio" id="combo_precio" class="form-control" min="0.01" step="0.01"
                       value="<?= htmlspecialchars($c['precio'] ?? '') ?>" oninput="calcular_combo()" required>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label>Descripción <span class="text-muted">(opcional)</span></label>
        <textarea name="descripcion" class="form-control" rows="2" placeholder="Ej. Tres decants de 5 ml para probar nuestras fragancias más vendidas"><?= htmlspecialchars($c['descripcion'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label>Foto <span class="text-muted">(opcional<?= $es_nuevo ? '' : '; deja vacío para conservar la actual' ?>)</span></label>
        <div class="d-flex align-items-center gap-2">
            <?php if (!$es_nuevo && $c['imagen']): ?>
            <img src="<?= htmlspecialchars($c['imagen']) ?>" alt="" class="foto-mini">
            <?php endif; ?>
            <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        </div>
        <?php if (!$es_nuevo && $c['imagen']): ?>
        <label class="mt-1 small"><input type="checkbox" name="quitar_imagen" value="1"> Quitar la foto actual</label>
        <?php endif; ?>
        <div class="form-text">JPG, PNG o WEBP. Máx. 2 MB.</div>
    </div>

    <label>Productos que incluye</label>
    <div id="filas_combo">
        <?php foreach ($filas as $f): ?>
        <div class="row g-2 mb-2 fila-combo">
            <div class="col-8 col-md-9"><?= $select_producto($f['presentacion_id']) ?></div>
            <div class="col-2"><input type="number" name="cantidad[]" class="form-control" min="1" step="1" value="<?= (int)$f['cantidad'] ?>" oninput="calcular_combo()" title="Cantidad"></div>
            <div class="col-2 col-md-1"><button type="button" class="btn btn-outline-danger w-100" onclick="quitar_fila_combo(this)" title="Quitar"><i class="fa-solid fa-xmark"></i></button></div>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-dorado-outline" onclick="agregar_fila_combo()"><i class="fa-solid fa-plus"></i> Agregar producto</button>

    <div class="mt-3 p-2 rounded small d-flex flex-wrap gap-3 justify-content-end" style="background:var(--crema-2)">
        <span>Por separado: <strong id="combo_separado">$0.00</strong></span>
        <span>Precio del combo: <strong id="combo_total">$0.00</strong></span>
        <span id="combo_ahorro"></span>
    </div>
</form>
</div>

<div class="modal-footer">
    <button class="btn-dorado" id="btn_guardar_combo" onclick="guardar_combo()"><?= $es_nuevo ? 'Guardar' : 'Actualizar' ?></button>
</div>

<script>
function dinero_combo(n){
    return '$' + Number(n).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function calcular_combo(){
    var separado = 0;
    $('#filas_combo .fila-combo').each(function(){
        var precio = parseFloat($(this).find('select option:selected').data('precio')) || 0;
        var cant = parseInt($(this).find('input[name="cantidad[]"]').val()) || 0;
        separado += precio * cant;
    });
    var precio_combo = parseFloat($('#combo_precio').val()) || 0;
    var ahorro = separado - precio_combo;
    $('#combo_separado').text(dinero_combo(separado));
    $('#combo_total').text(dinero_combo(precio_combo));
    if(precio_combo > 0 && separado > 0){
        $('#combo_ahorro').html(ahorro >= 0
            ? '<span class="estado bien"><i class="fa-solid fa-tag"></i> El cliente ahorra ' + dinero_combo(ahorro) + '</span>'
            : '<span class="estado aviso"><i class="fa-solid fa-triangle-exclamation"></i> Cuesta ' + dinero_combo(-ahorro) + ' más que por separado</span>');
    } else {
        $('#combo_ahorro').empty();
    }
}

function agregar_fila_combo(){
    var fila = $('#filas_combo .fila-combo').first().clone();
    fila.find('select').val('');
    fila.find('input').val(1);
    $('#filas_combo').append(fila);
    calcular_combo();
}

function quitar_fila_combo(btn){
    if($('#filas_combo .fila-combo').length > 1){
        $(btn).closest('.fila-combo').remove();
    } else {
        $(btn).closest('.fila-combo').find('select').val('');
    }
    calcular_combo();
}

function guardar_combo(){
    var form = document.getElementById('form_combo');
    if(!form.reportValidity()) return;
    var btn = $('#btn_guardar_combo').prop('disabled', true);
    $.ajax({
        url: "Combos/back_combos.php",
        type: "POST",
        data: new FormData(form),
        processData: false,
        contentType: false,
        dataType: "json",
        success: function(r){
            if(r.ok){
                $("#modal_general").modal('hide');
                Swal.fire({title:"Combo guardado",icon:"success",timer:1300,showConfirmButton:false});
                cargar_combos();
            } else {
                Swal.fire({title:"No se pudo guardar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
            }
        },
        error: function(){
            Swal.fire({title:"Error del servidor",text:"No se pudo guardar el combo.",icon:"error",confirmButtonColor:"#d33"});
        },
        complete: function(){ btn.prop('disabled', false); }
    });
}

calcular_combo();
</script>
