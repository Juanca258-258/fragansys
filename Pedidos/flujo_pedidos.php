<?php
// Orden en que avanza un pedido desde el panel. "cancelado" no está aquí porque solo lo pone el cliente.
const FLUJO_PEDIDO = ['nuevo', 'confirmado', 'en_preparacion', 'enviado', 'entregado'];

const ETIQUETAS_ESTADO = [
    'nuevo'          => 'Nuevo',
    'confirmado'     => 'Confirmado',
    'en_preparacion' => 'En preparación',
    'enviado'        => 'Enviado',
    'entregado'      => 'Entregado',
    'cancelado'      => 'Cancelado',
];

// Texto del botón que lleva al siguiente estado, indexado por el estado actual.
const ACCION_SIGUIENTE = [
    'nuevo'          => 'Confirmar pedido',
    'confirmado'     => 'Pasar a preparación',
    'en_preparacion' => 'Marcar como enviado',
    'enviado'        => 'Marcar como entregado',
];

function siguiente_estado($estado) {
    $i = array_search($estado, FLUJO_PEDIDO, true);
    return ($i === false || $i === count(FLUJO_PEDIDO) - 1) ? null : FLUJO_PEDIDO[$i + 1];
}
