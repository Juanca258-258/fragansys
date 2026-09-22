<?php
ob_start();
session_start();
if (isset($_SESSION['id_usuario'])) {
    header("Location: inicio.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once("conexion.php");
    $usuario  = $_POST['usuario'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['id_usuario']       = $user['id'];
        $_SESSION['usuario']          = $user['usuario'];
        $_SESSION['nombre_completo']  = $user['nombre_completo'];
        $_SESSION['rol']              = $user['rol'];
        header("Location: inicio.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fragansys — Iniciar sesión</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="css/estilos.css">
<style>
body{
    background: radial-gradient(circle at top, #2a2a2a 0%, #141414 70%);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}
.login-card{
    background:#fff;
    border-radius:18px;
    box-shadow:0 30px 70px rgba(0,0,0,0.35);
    padding:44px 40px;
    width:100%;
    max-width:390px;
}
.login-logo{
    width:60px; height:60px;
    border:2px solid var(--dorado);
    border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    color:var(--dorado);
    font-size:1.7rem;
    font-weight:700;
    margin:0 auto 14px;
}
.login-title{
    font-size:1.4rem;
    font-weight:700;
    color:var(--texto);
    text-align:center;
}
.login-sub{
    font-size:0.82rem;
    color:var(--texto-suave);
    text-align:center;
    margin-bottom:28px;
}
.btn-login{
    background:linear-gradient(135deg, var(--dorado) 0%, var(--dorado-oscuro) 100%);
    color:#fff;
    border:none;
    border-radius:9px;
    padding:11px;
    font-weight:600;
    font-size:0.9rem;
    width:100%;
    transition: transform .15s, box-shadow .15s;
}
.btn-login:hover{
    transform: translateY(-1px);
    box-shadow:0 8px 20px rgba(198,154,76,0.4);
    color:#fff;
}
.alerta-error{
    background:#fdf1ee;
    border:1px solid #f3cfc4;
    color:#c0522e;
    border-radius:9px;
    padding:10px 14px;
    font-size:0.82rem;
    margin-bottom:16px;
}
</style>
</head>
<body>

<div class="login-card">
    <div class="login-logo">F</div>
    <div class="login-title serif">Fragansys</div>
    <div class="login-sub">Ingresa tus credenciales para continuar</div>

    <?php if ($error): ?>
    <div class="alerta-error">
        <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="mb-3">
            <label>Usuario</label>
            <input type="text" name="usuario" class="form-control"
                   placeholder="Ingresa tu usuario" required autofocus>
        </div>
        <div class="mb-4">
            <label>Contraseña</label>
            <input type="password" name="password" class="form-control"
                   placeholder="Ingresa tu contraseña" required>
        </div>
        <button type="submit" class="btn-login">
            <i class="fa-solid fa-right-to-bracket me-2"></i>Iniciar sesión
        </button>
    </form>
</div>

</body>
</html>
