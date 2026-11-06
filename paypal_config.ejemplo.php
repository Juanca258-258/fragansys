<?php
// PLANTILLA. Copia este archivo como "paypal_config.php" (ese no se sube a git) y pon tus claves.
// Las claves salen de developer.paypal.com → Apps & Credentials → (modo Sandbox) → tu App.

const PAYPAL_MODO      = 'sandbox';                 // 'sandbox' para pruebas, 'live' cuando ya se cobre de verdad
const PAYPAL_CLIENT_ID = 'PEGA_AQUI_EL_CLIENT_ID';
const PAYPAL_SECRET    = 'PEGA_AQUI_EL_SECRET';
const PAYPAL_MONEDA    = 'MXN';

// Prefijo para identificar los pedidos de cada PC en PayPal (los números de pedido se repiten entre PCs).
const PAYPAL_PREFIJO   = 'PC1';
