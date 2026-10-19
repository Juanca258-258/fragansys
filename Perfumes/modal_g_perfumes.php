<?php
require_once("../sesion.php");
require_once("../conexion.php");
require_once("../helpers.php");
$minimo_defecto = obtener_config($conn)['stock_minimo_defecto'];
$p = null;
$decants = [];
$precio_frasco = '';
include(__DIR__ . "/form_perfume.php");
