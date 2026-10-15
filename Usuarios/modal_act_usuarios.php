<?php
require_once("../sesion.php");
require_once("../conexion.php");
if ($_SESSION['rol'] !== 'admin') exit;

$id = (int)$_POST['id'];
$u  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, usuario, nombre, apellidos, rol FROM usuarios WHERE id=$id AND activo=1"));
if (!$u) {
    echo '<div class="modal-body vacio">El usuario no existe.</div>';
    exit;
}
$es_yo = $id === (int)$_SESSION['id_usuario'];
?>
<div class="modal-header">
    <h5 class="modal-title">Editar usuario</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="form_act_usuario">
    <input type="hidden" name="id" value="<?= $u['id'] ?>">

    <div class="row">
        <div class="col-md-5 mb-3">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($u['nombre']) ?>" required>
        </div>
        <div class="col-md-7 mb-3">
            <label>Apellidos</label>
            <input type="text" name="apellidos" class="form-control" value="<?= htmlspecialchars($u['apellidos']) ?>" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Usuario</label>
            <input type="text" name="usuario" class="form-control" value="<?= htmlspecialchars($u['usuario']) ?>" required autocomplete="off">
        </div>
        <div class="col-md-6 mb-3">
            <label>Rol</label>
            <?php if ($es_yo): ?>
                <input type="hidden" name="rol" value="<?= $u['rol'] ?>">
                <input type="text" class="form-control" value="<?= $u['rol'] == 'admin' ? 'Administrador' : 'Vendedor' ?>" disabled>
                <div class="form-text">No puedes cambiar tu propio rol.</div>
            <?php else: ?>
                <select name="rol" class="form-select">
                    <option value="vendedor" <?= $u['rol'] == 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
                    <option value="admin" <?= $u['rol'] == 'admin' ? 'selected' : '' ?>>Administrador</option>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-muted small mb-2"><i class="fa-solid fa-lock me-1"></i>Deja la contraseña en blanco si no quieres cambiarla.</p>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Nueva contraseña</label>
            <div class="input-group">
                <input type="password" name="password" id="inp_pass_act" class="form-control" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary" onclick="toggle_pass('inp_pass_act', this)"><i class="fa-solid fa-eye"></i></button>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <label>Confirmar nueva contraseña</label>
            <div class="input-group">
                <input type="password" name="password_confirm" id="inp_pass_actc" class="form-control" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary" onclick="toggle_pass('inp_pass_actc', this)"><i class="fa-solid fa-eye"></i></button>
            </div>
        </div>
    </div>
</form>
</div>

<div class="modal-footer">
    <button class="btn-dorado" onclick="actualizar_usuario()">Actualizar</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggle_pass(id, btn){
    var inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.innerHTML = inp.type === 'password' ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
}

function actualizar_usuario(){
    var form = document.getElementById('form_act_usuario');
    if(!form.reportValidity()) return;
    if($('#inp_pass_act').val() !== $('#inp_pass_actc').val()){
        Swal.fire({title:"Contraseñas no coinciden",text:"Las contraseñas ingresadas no son iguales.",icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        return;
    }
    $.post("Usuarios/back_usuarios.php", $(form).serialize() + "&accion=actualizar", function(resp){
        var r = JSON.parse(resp);
        if(r.ok){
            $("#modal_general").modal('hide');
            Swal.fire({title:"Usuario actualizado",text:"Los datos fueron actualizados correctamente.",icon:"success",timer:1500,showConfirmButton:false});
            cargar_usuarios();
        } else {
            Swal.fire({title:"No se pudo actualizar",text:r.error,icon:"warning",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
        }
    });
}
</script>
