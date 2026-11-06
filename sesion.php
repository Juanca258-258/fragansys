<?php
// Sin esto PHP usa UTC y, de noche, cree que ya es el día siguiente (MySQL sí usa la hora local).
date_default_timezone_set('America/Mexico_City');

session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit;
}
