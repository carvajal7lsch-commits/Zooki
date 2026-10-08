<?php
/**
 * RE-T.19.3 — La política cambió de versión: se acepta antes de continuar.
 * Recibe de CuentaController $version, $vigencia y $error.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../config/App.php';

$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de datos · Zooki</title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/contexto.css?v=<?= $e(App::assetVersion()) ?>">
    <link rel="stylesheet" href="css/cuenta-pendiente.css?v=<?= $e(App::assetVersion()) ?>">
</head>
<body class="contexto-pagina">
    <main class="contexto-tarjeta">
        <img src="img/icon_blue.png" alt="Zooki" class="contexto-logo">
        <h1 class="contexto-titulo">Actualizamos nuestra política de datos</h1>

        <?php if ($error): ?>
            <p class="contexto-aviso contexto-aviso--error" role="alert"><?= $e($error) ?></p>
        <?php endif; ?>

        <p class="contexto-texto">
            Para seguir usando Zooki necesitamos que leas y aceptes la versión <?= $e($version) ?> de la
            Política de Tratamiento de Datos Personales, vigente desde el <?= $e($vigencia) ?>.
        </p>

        <form method="POST" action="index.php?action=aceptar_politica" class="cuenta-form">
            <?php Csrf::field('default'); ?>
            <label class="cuenta-check">
                <input type="checkbox" name="acepta_datos" value="1" required>
                <span>
                    Leí y acepto la
                    <a href="index.php?action=privacidad" target="_blank" rel="noopener">Política de Tratamiento de Datos</a>.
                </span>
            </label>
            <button type="submit" class="cuenta-boton">Aceptar y continuar</button>
        </form>

        <a class="contexto-salir" href="index.php?action=logout"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Cerrar sesión</a>
    </main>
</body>
</html>
