<?php
require_once("../sesion.php");
require_once("../conexion.php");
$id = (int)$_POST['id'];
$c  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM combos WHERE id=$id AND activo=1"));
if (!$c) {
    echo '<div class="modal-body vacio">El combo no existe.</div>';
    exit;
}
$productos = mysqli_fetch_all(mysqli_query($conn,
    "SELECT presentacion_id, cantidad FROM combo_presentaciones WHERE combo_id=$id ORDER BY id"), MYSQLI_ASSOC);
include(__DIR__ . "/form_combo.php");
