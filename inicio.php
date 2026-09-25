<?php
ob_start();
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}
$rol             = $_SESSION['rol'];
$usuario         = $_SESSION['usuario'];
$nombre_completo = $_SESSION['nombre_completo'];
$es_admin        = ($rol === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fragansys — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet" href="css/estilos.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>

<div class="app-shell">

    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo">F</div>
            <div class="sidebar-brand-text">
                <div class="brand">Fragansys</div>
                <div class="sub">ADMIN</div>
            </div>
        </div>

        <ul class="sidebar-nav" style="list-style:none; margin:0;">
            <li><a href="#" class="nav-link active" onclick="activar(this); cargar_dashboard(); return false;">
                <i class="fa-solid fa-gauge-high"></i> Dashboard
            </a></li>
            <li><a href="#" class="nav-link" onclick="activar(this); cargar_productos(); return false;">
                <i class="fa-solid fa-box"></i> Productos
            </a></li>
            <li><a href="#" class="nav-link" onclick="activar(this); cargar_catalogo(); return false;">
                <i class="fa-solid fa-book-open"></i> Catálogo
            </a></li>
            <li><a href="#" class="nav-link" onclick="activar(this); cargar_combos(); return false;">
                <i class="fa-solid fa-gift"></i> Combos
            </a></li>
            <li><a href="#" class="nav-link" onclick="activar(this); cargar_ventas(); return false;">
                <i class="fa-solid fa-cart-shopping"></i> Ventas
            </a></li>
            <?php if ($es_admin): ?>
            <li><a href="#" class="nav-link" onclick="activar(this); cargar_configuracion(); return false;">
                <i class="fa-solid fa-gear"></i> Configuración
            </a></li>
            <li><a href="#" class="nav-link" onclick="activar(this); cargar_usuarios(); return false;">
                <i class="fa-solid fa-users"></i> Usuarios
            </a></li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-footer">
            <div class="nombre"><i class="fa-solid fa-circle-user me-1"></i> <?= htmlspecialchars($nombre_completo ?: $usuario) ?></div>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i>Cerrar sesión</a>
        </div>
    </div>

    <div class="contenido">
        <div id="contenedor_principal"></div>
    </div>

</div>

<!-- Modal General -->
<div class="modal fade" id="modal_general" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" id="contenido_modal"></div>
  </div>
</div>

<script>
function activar(el){
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
}

function cargar_dashboard(){
    $("#contenedor_principal").load("Dashboard/modal_dashboard.php");
}
function cargar_productos(){
    $("#contenedor_principal").load("Productos/modal_productos.php", function(){
        if($.fn.DataTable.isDataTable('#tbl_productos')){
            $('#tbl_productos').DataTable().destroy();
        }
        $('#tbl_productos').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/2.3.8/i18n/es-MX.json' },
            pageLength: 25,
            order: [],
            responsive: true,
            dom: 'tip',
            columnDefs: [{ orderable: false, targets: -1 }]
        });
    });
}
function modal_g_productos(){
    $("#contenido_modal").load("Productos/modal_g_productos.php");
    $("#modal_general").modal('show');
}
function modal_act_productos(id){
    $.post("Productos/modal_act_productos.php", {id:id}, function(data){
        $("#contenido_modal").html(data);
        $("#modal_general").modal('show');
    });
}

function cargar_catalogo(){
    $("#contenedor_principal").load("Catalogo/modal_catalogo.php", function(){
        if($.fn.DataTable.isDataTable('#tbl_catalogo')){
            $('#tbl_catalogo').DataTable().destroy();
        }
        $('#tbl_catalogo').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/2.3.8/i18n/es-MX.json' },
            pageLength: 25,
            order: [],
            responsive: true,
            dom: 'tip',
            columnDefs: [{ orderable: false, targets: -1 }]
        });
    });
}
function modal_g_catalogo(){
    $("#contenido_modal").load("Catalogo/modal_g_catalogo.php");
    $("#modal_general").modal('show');
}
function modal_act_catalogo(id){
    $.post("Catalogo/modal_act_catalogo.php", {id:id}, function(data){
        $("#contenido_modal").html(data);
        $("#modal_general").modal('show');
    });
}
function cargar_combos(){
    $("#contenedor_principal").load("Combos/modal_combos.php");
}
function cargar_ventas(){
    $("#contenedor_principal").load("Ventas/modal_ventas.php");
}
function cargar_configuracion(){
    $("#contenedor_principal").load("Configuracion/modal_configuracion.php");
}
function cargar_usuarios(){
    $("#contenedor_principal").load("Usuarios/modal_usuarios.php");
}

function ir_a(seccion){
    var link = document.querySelector('.sidebar-nav [onclick*="cargar_'+seccion+'"]');
    if(link){ activar(link); }
    window['cargar_'+seccion]();
}

$(document).ready(function(){
    cargar_dashboard();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.8/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>
