<?php
require_once("../sesion.php");
require_once("../conexion.php");

header('Content-Type: application/json; charset=utf-8');

$fila = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(estado_pedido = 'nuevo') AS nuevos, COALESCE(MAX(id), 0) AS ultimo_id,
            SUM(estado_pago = 'pagado') AS pagados, COALESCE(MAX(fecha_actualizacion), '') AS ultimo_cambio
     FROM pedidos"));

// ultimo_id permite detectar que llegó un pedido aunque el número de "nuevos" no cambie
// (por ejemplo, si al mismo tiempo se confirmó otro). ultimo_cambio detecta cambios en pedidos existentes
// (un pago, una cancelación del cliente) y pagados permite avisar cuando entra un pago.
echo json_encode([
    'nuevos'        => (int)$fila['nuevos'],
    'ultimo_id'     => (int)$fila['ultimo_id'],
    'pagados'       => (int)$fila['pagados'],
    'ultimo_cambio' => $fila['ultimo_cambio'],
]);
