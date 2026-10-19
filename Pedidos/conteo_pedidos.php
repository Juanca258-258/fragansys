<?php
require_once("../sesion.php");
require_once("../conexion.php");

header('Content-Type: application/json; charset=utf-8');

$fila = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(estado_pedido = 'nuevo') AS nuevos, COALESCE(MAX(id), 0) AS ultimo_id FROM pedidos"));

// ultimo_id permite detectar que llegó un pedido aunque el número de "nuevos" no cambie
// (por ejemplo, si al mismo tiempo se confirmó otro).
echo json_encode(['nuevos' => (int)$fila['nuevos'], 'ultimo_id' => (int)$fila['ultimo_id']]);
