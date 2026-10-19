<?php
require_once("../sesion.php");
require_once("../conexion.php");

if ($_SESSION['rol'] !== 'admin') {
    echo '<div class="panel vacio">No tienes permiso para administrar usuarios.</div>';
    exit;
}

$result = mysqli_query($conn,
    "SELECT id, usuario, nombre, apellidos, rol, fecha_creacion
     FROM usuarios WHERE activo = 1 ORDER BY nombre, apellidos");
$mi_id = (int)$_SESSION['id_usuario'];
$usuarios = mysqli_fetch_all($result, MYSQLI_ASSOC);
$num_admin = count(array_filter($usuarios, function ($u) { return $u['rol'] === 'admin'; }));
?>
<div class="modulo-cab compacto">
    <div class="modulo-icono"><i class="fa-solid fa-users"></i></div>
    <div>
        <h1>Usuarios</h1>
        <div class="sub">Personas con acceso al panel administrativo</div>
    </div>
    <div class="acciones-cab">
        <span class="dato-cab"><i class="fa-solid fa-users"></i> <strong><?= count($usuarios) ?></strong> usuario<?= count($usuarios) == 1 ? '' : 's' ?></span>
        <span class="dato-cab"><i class="fa-solid fa-user-shield"></i> <strong><?= $num_admin ?></strong> administrador<?= $num_admin == 1 ? '' : 'es' ?></span>
        <button class="btn-dorado" onclick="modal_g_usuarios()"><i class="fa-solid fa-plus"></i> Nuevo usuario</button>
    </div>
</div>

<div class="panel panel-modulo">
<div class="d-flex justify-content-between align-items-center mb-3 gap-3">
    <div class="input-group input-busqueda">
        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted" style="font-size:.8rem"></i></span>
        <input type="text" class="form-control" placeholder="Buscar nombre o usuario..."
               oninput="if($.fn.DataTable.isDataTable('#tbl_usuarios')){$('#tbl_usuarios').DataTable().search(this.value).draw();}">
    </div>
</div>

<div class="table-responsive">
<table id="tbl_usuarios" class="table tabla-modulo align-middle">
<thead>
<tr>
    <th>Nombre</th>
    <th>Usuario</th>
    <th>Rol</th>
    <th>Alta</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($usuarios as $u) {
    $protegido = (int)$u['id'] === 1 || (int)$u['id'] === $mi_id;
    $iniciales = mb_strtoupper(mb_substr($u['nombre'], 0, 1) . mb_substr($u['apellidos'], 0, 1));
?>
<tr>
    <td>
        <div class="d-flex align-items-center gap-2">
            <span class="avatar-ini"><?= htmlspecialchars($iniciales) ?></span>
            <div>
                <div class="fw-semibold">
                    <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?>
                    <?php if ((int)$u['id'] === $mi_id): ?><span class="small text-muted fw-normal">(tú)</span><?php endif; ?>
                </div>
            </div>
        </div>
    </td>
    <td><?= htmlspecialchars($u['usuario']) ?></td>
    <td>
        <?php if ($u['rol'] == 'admin'): ?>
        <span class="estado info"><i class="fa-solid fa-user-shield"></i> Administrador</span>
        <?php else: ?>
        <span class="estado neutro"><i class="fa-solid fa-user-tag"></i> Vendedor</span>
        <?php endif; ?>
    </td>
    <td data-order="<?= $u['fecha_creacion'] ?>"><?= date('d/m/Y', strtotime($u['fecha_creacion'])) ?></td>
    <td style="white-space:nowrap">
        <button class="btn-accion" onclick="modal_act_usuarios(<?= $u['id'] ?>)">
            <i class="fa-solid fa-pen"></i> Editar
        </button>
        <?php if ($protegido): ?>
        <span title="<?= (int)$u['id'] === $mi_id ? 'No puedes eliminar tu propio usuario' : 'El administrador principal no puede eliminarse' ?>">
            <button class="btn-accion peligro" disabled style="pointer-events:none">
                <i class="fa-solid fa-trash"></i> Eliminar
            </button>
        </span>
        <?php else: ?>
        <button class="btn-accion peligro" onclick="eliminar_usuario(<?= $u['id'] ?>)">
            <i class="fa-solid fa-trash"></i> Eliminar
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
function eliminar_usuario(id){
    Swal.fire({
        title: "¿Eliminar usuario?",
        text: "Ya no podrá iniciar sesión en el panel.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if(!result.isConfirmed) return;
        $.post("Usuarios/back_usuarios.php", { accion:"eliminar", id:id }, function(resp){
            var r = JSON.parse(resp);
            if(r.ok){
                Swal.fire({title:"Eliminado",text:"El usuario fue eliminado correctamente",icon:"success",timer:1500,showConfirmButton:false});
                cargar_usuarios();
            } else {
                Swal.fire({title:"No permitido",text:r.error,icon:"error",confirmButtonColor:"#d33",confirmButtonText:"Aceptar"});
            }
        });
    });
}
</script>
