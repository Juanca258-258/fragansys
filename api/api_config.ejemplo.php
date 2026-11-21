<?php
// PLANTILLA. Copia este archivo como "api_config.php" (ese no se sube a git) y cambia la clave.
// La clave firma las sesiones de la app Android: si la cambias, todos los clientes tendrán que volver a iniciar sesión.
// Para generar una nueva: php -r "echo bin2hex(random_bytes(32));"

const API_CLAVE_SECRETA = 'PEGA_AQUI_UNA_CLAVE_LARGA_Y_ALEATORIA';

// Días que dura iniciada la sesión del cliente en la app
const API_DIAS_SESION = 30;
