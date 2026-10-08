<?php
require_once("../sesion.php");
require_once("../conexion.php");
$id = (int)$_POST['id'];
$p  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM perfumes WHERE id=$id"));

$decants = [];
$precio_frasco = '';
$res = mysqli_query($conn, "SELECT tipo, ml, precio FROM presentaciones WHERE perfume_id=$id AND activo=1 ORDER BY ml");
while ($pr = mysqli_fetch_assoc($res)) {
    if ($pr['tipo'] == 'frasco') {
        $precio_frasco = $pr['precio'];
    } else {
        $decants[] = ['ml' => $pr['ml'], 'precio' => $pr['precio']];
    }
}

include(__DIR__ . "/form_perfume.php");
