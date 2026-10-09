<?php
/**
 * D2.2 (revisión de D2.1) — <head> de las páginas que se abren desde un
 * enlace del correo (activar cuenta, confirmar correo, invitación al
 * personal): el mismo diseño de reset_password.php.
 *
 * Espera $tituloPagina.
 */
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title><?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8') ?> · Zooki</title>
    <link rel="icon" type="image/png" href="img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/reset-password.css?v=d22">
    <link rel="stylesheet" href="css/validacion-cuenta.css?v=d22">
