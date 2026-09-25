<?php
require_once("../conexion.php");
$id   = (int)$_POST['id'];
$fila = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM catalogo WHERE id=$id"));
?>

<div class="modal-header">
    <h5 class="modal-title">Editar fragancia</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_act_catalogo" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $fila['id'] ?>">
    <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($fila['imagen'] ?? '') ?>">

    <div class="row">
        <div class="col-md-7 mb-3">
            <label>Nombre de la fragancia</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($fila['nombre']) ?>" required>
        </div>
        <div class="col-md-5 mb-3">
            <label>Marca</label>
            <input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($fila['marca']) ?>" required>
        </div>
    </div>

    <div class="mb-3">
        <label>Género</label>
        <select name="genero" class="form-select">
            <option value="unisex" <?= $fila['genero']=='unisex'?'selected':'' ?>>Unisex</option>
            <option value="hombre" <?= $fila['genero']=='hombre'?'selected':'' ?>>Hombre</option>
            <option value="mujer"  <?= $fila['genero']=='mujer'?'selected':'' ?>>Mujer</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Imagen actual</label><br>
        <?php if ($fila['imagen']): ?>
            <img src="../<?= htmlspecialchars($fila['imagen']) ?>" alt="" style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--borde)">
        <?php else: ?>
            <span class="text-muted small">Sin imagen</span>
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
    <button class="btn btn-primary" onclick="actualizar_catalogo()">Actualizar</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function actualizar_catalogo(){
    var nombre = $('#form_act_catalogo [name=nombre]').val().trim();
    var marca  = $('#form_act_catalogo [name=marca]').val().trim();
    if(!nombre || !marca){
        Swal.fire({title:"Campos requeridos",text:"Nombre y marca son obligatorios.",icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        return;
    }
    var datos = new FormData(document.getElementById('form_act_catalogo'));
    datos.append('accion', 'actualizar');

    $.ajax({
        url: "Catalogo/back_catalogo.php",
        type: "POST",
        data: datos,
        processData: false,
        contentType: false,
        success: function(resp){
            var r = JSON.parse(resp);
            if(r.ok){
                $("#modal_general").modal('hide');
                Swal.fire({title:"Fragancia actualizada",text:"Los datos fueron actualizados correctamente.",icon:"success",timer:1500,showConfirmButton:false});
                cargar_catalogo();
            } else {
                Swal.fire({title:"No se pudo actualizar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
            }
        }
    });
}
</script>
