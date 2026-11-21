<?php
// Página a la que PayPal regresa al aprobar o cancelar un pago hecho desde la app.
// La app la intercepta antes de mostrarla; solo se ve si se abrió fuera de la app.
$aprobado = isset($_GET['resultado']) && $_GET['resultado'] === 'aprobado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fragansys</title>
</head>
<body style="font-family: Georgia, serif; text-align: center; padding: 3rem 1rem; color: #4a3320; background: #fefaf6;">
    <h2><?= $aprobado ? 'Pago aprobado' : 'Pago cancelado' ?></h2>
    <p>Regresa a la app de Fragansys para continuar.</p>
</body>
</html>
