<?php
// PLANTILLA. Copia este archivo como "mail_config.php" (ese no se sube a git) y pon los datos de tu correo.
// Con Gmail: MAIL_PASSWORD es una "contraseña de aplicación" de 16 letras, NO la contraseña normal de la cuenta.
// Se crea en myaccount.google.com → Seguridad → Verificación en 2 pasos (activarla) → Contraseñas de aplicaciones.

const MAIL_HABILITADO = true;                     // false para no enviar correos (p. ej. mientras se prueba)

const MAIL_HOST       = 'smtp.gmail.com';         // Outlook/Hotmail: 'smtp-mail.outlook.com'
const MAIL_PUERTO     = 587;                      // 587 con 'tls'; 465 con 'ssl'
const MAIL_SEGURIDAD  = 'tls';
const MAIL_USUARIO    = 'tucorreo@gmail.com';     // Cuenta desde la que se envían los correos
const MAIL_PASSWORD   = 'PEGA_AQUI_LA_CONTRASEÑA_DE_APLICACION';

const MAIL_REMITENTE  = 'tucorreo@gmail.com';     // Con Gmail debe ser la misma que MAIL_USUARIO
const MAIL_NOMBRE     = 'Fragansys';              // Nombre que ve el cliente como remitente

// Quien recibe el aviso de pedido nuevo. Puede ser la misma cuenta u otra.
const MAIL_ADMIN      = 'tucorreo@gmail.com';
