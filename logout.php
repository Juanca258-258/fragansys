<?php
session_start();
if (isset($_SESSION['id_usuario'])) {
    require_once("conexion.php");
    require_once("helpers.php");
    registrar_movimiento($conn, 'Sesión', 'Cerrar sesión', "Cerró sesión");
}
session_unset();
session_destroy();
header("Location: login.php");
exit;
