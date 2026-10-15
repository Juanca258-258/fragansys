<?php
require_once("../sesion.php");
if ($_SESSION['rol'] !== 'admin') exit;
?>
<div class="modal-header">
    <h5 class="modal-title">Nuevo usuario</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_g_usuario">
    <div class="row">
        <div class="col-md-5 mb-3">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="col-md-7 mb-3">
            <label>Apellidos</label>
            <input type="text" name="apellidos" class="form-control" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Usuario</label>
            <input type="text" name="usuario" class="form-control" required autocomplete="off">
        </div>
        <div class="col-md-6 mb-3">
            <label>Rol</label>
            <select name="rol" class="form-select">
                <option value="vendedor">Vendedor</option>
                <option value="admin">Administrador</option>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Contraseña</label>
            <div class="input-group">
                <input type="password" name="password" id="inp_pass_g" class="form-control" required autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary" onclick="toggle_pass('inp_pass_g', this)"><i class="fa-solid fa-eye"></i></button>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <label>Confirmar contraseña</label>
            <div class="input-group">
                <input type="password" name="password_confirm" id="inp_pass_gc" class="form-control" required autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary" onclick="toggle_pass('inp_pass_gc', this)"><i class="fa-solid fa-eye"></i></button>
            </div>
        </div>
    </div>
</form>
</div>

<div class="modal-footer">
    <button class="btn-dorado" onclick="guardar_usuario()">Guardar</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggle_pass(id, btn){
    var inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.innerHTML = inp.type === 'password' ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
}

function guardar_usuario(){
    var form = document.getElementById('form_g_usuario');
    if(!form.reportValidity()) return;
    if($('#inp_pass_g').val() !== $('#inp_pass_gc').val()){
        Swal.fire({title:"Contraseñas no coinciden",text:"Las contraseñas ingresadas no son iguales.",icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        return;
    }
    $.post("Usuarios/back_usuarios.php", $(form).serialize() + "&accion=guardar", function(resp){
        var r = JSON.parse(resp);
        if(r.ok){
            $("#modal_general").modal('hide');
            Swal.fire({title:"Usuario creado",text:"El usuario fue registrado correctamente.",icon:"success",timer:1500,showConfirmButton:false});
            cargar_usuarios();
        } else {
            Swal.fire({title:"No se pudo guardar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        }
    });
}
</script>
