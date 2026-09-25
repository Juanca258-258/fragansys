<div class="modal-header">
    <h5 class="modal-title">Nueva fragancia</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_g_catalogo" enctype="multipart/form-data">

    <div class="row">
        <div class="col-md-7 mb-3">
            <label>Nombre de la fragancia</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="col-md-5 mb-3">
            <label>Marca</label>
            <input type="text" name="marca" class="form-control" required>
        </div>
    </div>

    <div class="mb-3">
        <label>Género</label>
        <select name="genero" class="form-select">
            <option value="unisex">Unisex</option>
            <option value="hombre">Hombre</option>
            <option value="mujer">Mujer</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Imagen</label>
        <input type="file" name="imagen" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        <div class="form-text">JPG, PNG o WEBP. Máx. 3 MB.</div>
    </div>

</form>
</div>

<div class="modal-footer">
    <button class="btn-dorado" onclick="guardar_catalogo()">Guardar</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function guardar_catalogo(){
    var nombre = $('#form_g_catalogo [name=nombre]').val().trim();
    var marca  = $('#form_g_catalogo [name=marca]').val().trim();
    if(!nombre || !marca){
        Swal.fire({title:"Campos requeridos",text:"Nombre y marca son obligatorios.",icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        return;
    }
    var datos = new FormData(document.getElementById('form_g_catalogo'));
    datos.append('accion', 'guardar');

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
                Swal.fire({title:"Fragancia registrada",text:"Se agregó correctamente al catálogo.",icon:"success",timer:1500,showConfirmButton:false});
                cargar_catalogo();
            } else {
                Swal.fire({title:"No se pudo guardar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
            }
        }
    });
}
</script>
