<?php
// Tienda pública de Fragansys — proyecto separado del panel admin.
// Comparte la base de datos (../conexion.php) y las imágenes (../uploads/),
// pero tendrá su propia sesión de cliente (tabla `clientes`), independiente
// de la sesión de administrador ($_SESSION['id_usuario']) que usa el panel.
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fragansys</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
<style>
body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#faf7f0;
    font-family:'Poppins', sans-serif;
    color:#2b2620;
    text-align:center;
}
.logo{
    width:64px; height:64px;
    border:2px solid #c69a4c;
    border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    color:#c69a4c;
    font-family:'Playfair Display', serif;
    font-size:1.8rem;
    font-weight:700;
    margin:0 auto 16px;
}
h1{ font-family:'Playfair Display', serif; margin:0 0 6px; }
p{ color:#8a7f68; font-size:0.9rem; }
</style>
</head>
<body>
<div>
    <div class="logo">F</div>
    <h1>Fragansys</h1>
    <p>Catálogo público — próximamente.</p>
</div>
</body>
</html>
