<?php
// Espera $p (perfume o null si es nuevo), $decants (array) y $precio_frasco (string).
$es_nuevo = $p === null;
$v = function ($campo, $defecto = '') use ($p) {
    return htmlspecialchars($p[$campo] ?? $defecto);
};
?>
<div class="modal-header">
    <h5 class="modal-title"><?= $es_nuevo ? 'Nuevo perfume' : 'Editar perfume' ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_perfume" enctype="multipart/form-data">
    <input type="hidden" name="accion" value="<?= $es_nuevo ? 'guardar' : 'actualizar' ?>">
    <?php if (!$es_nuevo): ?>
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-5 mb-3">
            <label>Marca</label>
            <input type="text" name="marca" class="form-control" value="<?= $v('marca') ?>" required>
        </div>
        <div class="col-md-7 mb-3">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" value="<?= $v('nombre') ?>" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5 mb-3">
            <label>Género</label>
            <select name="genero" class="form-select">
                <?php foreach (['unisex' => 'Unisex', 'hombre' => 'Hombre', 'mujer' => 'Mujer'] as $val => $txt): ?>
                <option value="<?= $val ?>" <?= ($p['genero'] ?? 'unisex') == $val ? 'selected' : '' ?>><?= $txt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-7 mb-3">
            <label>Foto <?= $es_nuevo ? '' : '(deja vacío para conservar la actual)' ?></label>
            <div class="d-flex align-items-center gap-2">
                <?php if (!$es_nuevo && $p['imagen']): ?>
                <img src="<?= $v('imagen') ?>" alt="" style="width:38px;height:38px;object-fit:cover;border-radius:8px;border:1px solid var(--borde)">
                <?php endif; ?>
                <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp" <?= $es_nuevo ? 'required' : '' ?>>
            </div>
            <div class="form-text">JPG, PNG o WEBP. Máx. 2 MB.</div>
        </div>
    </div>

    <h6 class="fw-bold mt-2 mb-2" style="font-size:.85rem">Inventario</h6>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label>Tamaño del frasco (ml)</label>
            <input type="number" name="ml_por_frasco" class="form-control" step="0.01" min="1" value="<?= $v('ml_por_frasco', '100') ?>" required>
        </div>
        <div class="col-md-4 mb-3">
            <label>Frascos sellados</label>
            <input type="number" name="frascos_sellados" class="form-control" step="1" min="0" value="<?= $v('frascos_sellados', '0') ?>" required>
        </div>
        <div class="col-md-4 mb-3">
            <label>ml para decants</label>
            <input type="number" name="ml_disponibles" class="form-control" step="0.01" min="0" value="<?= $v('ml_disponibles', '0') ?>" required>
            <div class="form-text">Lo que queda del frasco abierto.</div>
        </div>
    </div>

    <h6 class="fw-bold mt-2 mb-2" style="font-size:.85rem">Presentaciones a la venta</h6>
    <div class="mb-3">
        <label>Precio del frasco completo</label>
        <input type="number" name="precio_frasco" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($precio_frasco) ?>" placeholder="Vacío si no se vende el frasco completo">
    </div>

    <label>Decants</label>
    <div id="filas_decants">
        <?php foreach ($decants ?: [['ml' => '', 'precio' => '']] as $dec): ?>
        <div class="row g-2 mb-2 fila-decant">
            <div class="col-5"><input type="number" name="decant_ml[]" class="form-control" step="0.01" min="0" placeholder="ml" value="<?= $dec['ml'] !== '' ? (float)$dec['ml'] : '' ?>"></div>
            <div class="col-5"><input type="number" name="decant_precio[]" class="form-control" step="0.01" min="0" placeholder="Precio" value="<?= htmlspecialchars($dec['precio']) ?>"></div>
            <div class="col-2"><button type="button" class="btn btn-outline-danger w-100" onclick="quitar_fila_decant(this)"><i class="fa-solid fa-xmark"></i></button></div>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-dorado-outline" onclick="agregar_fila_decant()">
        <i class="fa-solid fa-plus"></i> Agregar tamaño de decant
    </button>
</form>
</div>

<div class="modal-footer">
    <button class="btn-dorado" onclick="guardar_perfume()"><?= $es_nuevo ? 'Guardar' : 'Actualizar' ?></button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function agregar_fila_decant(){
    var fila = $('#filas_decants .fila-decant').first().clone();
    fila.find('input').val('');
    $('#filas_decants').append(fila);
}

function quitar_fila_decant(btn){
    if($('#filas_decants .fila-decant').length > 1){
        $(btn).closest('.fila-decant').remove();
    } else {
        $(btn).closest('.fila-decant').find('input').val('');
    }
}

function guardar_perfume(){
    var form = document.getElementById('form_perfume');
    if(!form.reportValidity()) return;

    $.ajax({
        url: "Perfumes/back_perfumes.php",
        type: "POST",
        data: new FormData(form),
        processData: false,
        contentType: false,
        success: function(resp){
            var r = JSON.parse(resp);
            if(r.ok){
                $("#modal_general").modal('hide');
                Swal.fire({title:"Guardado",icon:"success",timer:1300,showConfirmButton:false});
                cargar_perfumes();
            } else {
                Swal.fire({title:"No se pudo guardar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
            }
        }
    });
}
</script>
