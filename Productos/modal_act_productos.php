<?php
require_once("../conexion.php");
$id   = (int)$_POST['id'];
$fila = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM productos WHERE id=$id"));
$catalogo = mysqli_query($conn, "SELECT id, nombre, marca FROM catalogo WHERE activo = 1 ORDER BY marca, nombre");
?>

<div class="modal-header">
    <h5 class="modal-title">Editar producto</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_act_producto" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $fila['id'] ?>">
    <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($fila['imagen'] ?? '') ?>">

    <div class="mb-3">
        <label>Fragancia (catálogo)</label>
        <select name="catalogo_id" id="sel_catalogo_act" class="form-select" required>
            <option value="">Selecciona una fragancia...</option>
            <?php while ($c = mysqli_fetch_assoc($catalogo)) { ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $fila['catalogo_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['marca']) ?> — <?= htmlspecialchars($c['nombre']) ?>
            </option>
            <?php } ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Nombre del producto</label>
        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($fila['nombre']) ?>" required>
    </div>

    <div class="mb-3">
        <label>Descripción</label>
        <textarea name="descripcion" class="form-control" rows="2"><?= htmlspecialchars($fila['descripcion'] ?? '') ?></textarea>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label>Tipo</label>
            <select name="tipo" class="form-select">
                <option value="decant" <?= $fila['tipo']=='decant'?'selected':'' ?>>Decant</option>
                <option value="frasco_completo" <?= $fila['tipo']=='frasco_completo'?'selected':'' ?>>Frasco completo</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label>Contenido (ml)</label>
            <input type="number" name="contenido_ml" class="form-control" step="0.01" min="0" value="<?= $fila['contenido_ml'] ?>" required>
        </div>
        <div class="col-md-4 mb-3">
            <label>Stock (unidades)</label>
            <input type="number" name="stock" class="form-control" step="1" min="0" value="<?= (int)$fila['stock'] ?>" required>
        </div>
    </div>

    <div class="mb-3">
        <label>Precio</label>
        <input type="number" name="precio" class="form-control" step="0.01" min="0" value="<?= $fila['precio'] ?>" required>
    </div>

    <div class="mb-3">
        <label>Imagen actual</label><br>
        <?php if ($fila['imagen']): ?>
            <img src="../<?= htmlspecialchars($fila['imagen']) ?>" alt="" style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--borde)">
        <?php else: ?>
            <span class="text-muted small">Sin imagen propia (usa la del catálogo)</span>
        <?php endif; ?>
    </div>
    <div class="mb-3">
        <label>Reemplazar imagen</label>
        <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        <div class="form-text">Deja vacío para conservar la imagen actual. JPG, PNG o WEBP, máx. 3 MB.</div>
    </div>

</form>
</div>

<div class="modal-footer">
    <button class="btn btn-primary" onclick="actualizar_producto()">Actualizar</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$('#sel_catalogo_act').select2({
    theme: 'bootstrap-5',
    dropdownParent: $('#modal_general'),
    width: '100%'
});

function actualizar_producto(){
    var catalogo_id = $('#form_act_producto [name=catalogo_id]').val();
    var nombre      = $('#form_act_producto [name=nombre]').val().trim();
    if(!catalogo_id){ Swal.fire({title:"Campo requerido",text:"Selecciona la fragancia del catálogo.",icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"}); return; }
    if(!nombre){ Swal.fire({title:"Campo requerido",text:"El nombre del producto es obligatorio.",icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"}); return; }

    var datos = new FormData(document.getElementById('form_act_producto'));
    datos.append('accion', 'actualizar');

    $.ajax({
        url: "Productos/back_productos.php",
        type: "POST",
        data: datos,
        processData: false,
        contentType: false,
        success: function(resp){
            var r = JSON.parse(resp);
            if(r.ok){
                $("#modal_general").modal('hide');
                Swal.fire({title:"Producto actualizado",text:"Los datos fueron actualizados correctamente.",icon:"success",timer:1500,showConfirmButton:false});
                cargar_productos();
            } else {
                Swal.fire({title:"No se pudo actualizar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
            }
        }
    });
}
</script>
