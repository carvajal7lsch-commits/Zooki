<?php
/**
 * M0.2 — Marco de las páginas a las que se llega desde un correo y de las que
 * siguen al inicio de sesión (política, perfil, contexto, contraseña): el
 * mismo del login y los registros. Abre la página hasta el <main>;
 * partials/acceso_marco_fin.php lo cierra con el collage.
 *
 * Variables: $tituloPagina y, opcional, $accesoSalida ('inicio' por defecto,
 * o 'sesion' en las páginas con la sesión abierta: el botón de arriba cierra
 * la sesión en lugar de volver a la página de inicio).
 */
$accesoSalida = $accesoSalida ?? 'inicio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php require __DIR__ . '/cabecera_acceso.php'; ?>
    <?php // Los enlaces de los correos llevan un token en la URL: no se envía a otros sitios. ?>
    <meta name="referrer" content="no-referrer">
</head>
<body class="login-page acceso-pagina-cuerpo">
<div class="login-container">
    <div class="login-form-area">
        <div class="login-topbar">
            <a href="index.php" class="login-brand" aria-label="Zooki, ir al inicio"><img src="img/icon_blue.png" alt="" class="brand-logo" draggable="false"><span class="brand-text">Zooki</span></a>
            <?php if ($accesoSalida === 'sesion'): ?>
                <a href="index.php?action=logout" class="login-back"><i class="ri-logout-box-r-line" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            <?php else: ?>
                <a href="index.php" class="login-back"><i class="ri-arrow-left-line" aria-hidden="true"></i><span>Volver al inicio</span></a>
            <?php endif; ?>
        </div>
        <main class="form-wrapper registro-marco acceso-pagina">
