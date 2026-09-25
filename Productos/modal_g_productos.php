<?php
require_once("../conexion.php");
$catalogo = mysqli_query($conn, "SELECT id, nombre, marca FROM catalogo WHERE activo = 1 ORDER BY marca, nombre");
$hay_catalogo = mysqli_num_rows($catalogo) > 0;
?>
<div class="modal-header">
    <h5 class="modal-title">Nuevo producto</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_g_producto" enctype="multipart/form-data">

    <div class="mb-3">
        <label>Fragancia (catálogo)</label>
        <select name="catalogo_id" id="sel_catalogo_g" class="form-select" <?= $hay_catalogo ? 'required' : 'disabled' ?>>
            <option value="">Selecciona una fragancia...</option>
            <?php while ($c = mysqli_fetch_assoc($catalogo)) { ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['marca']) ?> — <?= htmlspecialchars($c['nombre']) ?></option>
            <?php } ?>
        </select>
        <?php if (!$hay_catalogo): ?>
        <div class="form-text text-danger">No hay fragancias registradas. Agrega una primero en el módulo Catálogo.</div>
        <?php endif; ?>
    </div>

    <div class="mb-3">
        <label>Nombre del producto</label>
        <input type="text" name="nombre" class="form-control" placeholder="Ej. Black Orchid — Decant 5ml" required>
    </div>

    <div class="mb-3">
        <label>Descripción</label>
        <textarea name="descripcion" class="form-control" rows="2"></textarea>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label>Tipo</label>
            <select name="tipo" class="form-select">
                <option value="decant">Decant</option>
                <option value="frasco_completo">Frasco completo</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label>Contenido (ml)</label>
            <input type="number" name="contenido_ml" class="form-control" step="0.01" min="0" required>
        </div>
        <div class="col-md-4 mb-3">
            <label>Stock (unidades)</label>
            <input type="number" name="stock" class="form-control" step="1" min="0" value="0" required>
        </div>
    </div>

    <div class="mb-3">
        <label>Precio</label>
        <input type="number" name="precio" class="form-control" step="0.01" min="0" required>
    </div>

    <div class="mb-3">
        <label>Imagen</label>
        <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        <div class="form-text">JPG, PNG o WEBP. Máx. 3 MB. Si no subes una, se usará la imagen de la fragancia.</div>
    </div>

</form>
</div>

<div class="modal-footer">
    <button class="btn-dorado" onclick="guardar_producto()" <?= $hay_catalogo ? '' : 'disabled' ?>>Guardar</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$('#sel_catalogo_g').select2({
    theme: 'bootstrap-5',
    dropdownParent: $('#modal_general'),
    width: '100%'
});

function guardar_producto(){
    var catalogo_id = $('#form_g_producto [name=catalogo_id]').val();
    var nombre      = $('#form_g_producto [name=nombre]').val().trim();
    var contenido   = $('#form_g_producto [name=contenido_ml]').val();
    var precio      = $('#form_g_producto [name=precio]').val();

    if(!catalogo_id){ Swal.fire({title:"Campo requerido",text:"Selecciona la fragancia del catálogo.",icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"}); return; }
    if(!nombre){ Swal.fire({title:"Campo requerido",text:"El nombre del producto es obligatorio.",icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"}); return; }
    if(contenido === '' || precio === ''){ Swal.fire({title:"Campos requeridos",text:"Contenido y precio son obligatorios.",icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"}); return; }

    var datos = new FormData(document.getElementById('form_g_producto'));
    datos.append('accion', 'guardar');

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
                Swal.fire({title:"Producto creado",text:"El producto fue registrado correctamente.",icon:"success",timer:1500,showConfirmButton:false});
                cargar_productos();
            } else {
                Swal.fire({title:"No se pudo guardar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
            }
        }
    });
}
</script>
