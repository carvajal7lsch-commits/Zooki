<?php
/**
 * HU-T.17 — Selector de contexto. Recibe de ContextoController:
 * $disponibles (contextos vigentes), $actual (el activo o null), $nombre y $error.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/Contexto.php';
require_once __DIR__ . '/../../config/App.php';

$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$iconos = [
    Contexto::CLINICA => 'fa-hospital',
    Contexto::PROPIETARIO => 'fa-paw',
    Contexto::PLATAFORMA => 'fa-shield-alt',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elige cómo entrar · Zooki</title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/contexto.css?v=<?= $e(App::assetVersion()) ?>">
</head>
<body class="contexto-pagina">
    <main class="contexto-tarjeta">
        <img src="img/icon_blue.png" alt="Zooki" class="contexto-logo">
        <h1 class="contexto-titulo">Hola, <?= $e(explode(' ', trim($nombre))[0] ?: 'de nuevo') ?></h1>

        <?php if ($error): ?>
            <p class="contexto-aviso contexto-aviso--error" role="alert"><?= $e($error) ?></p>
        <?php endif; ?>

        <?php if (!$disponibles): ?>
            <p class="contexto-texto">
                Tu cuenta todavía no tiene acceso a ninguna clínica. Cuando una clínica te vincule
                como parte de su personal o como propietario, podrás entrar desde aquí.
            </p>
        <?php else: ?>
            <p class="contexto-texto">Elige con qué perfil quieres continuar. Puedes cambiarlo después sin cerrar sesión.</p>
            <ul class="contexto-lista">
                <?php foreach ($disponibles as $c): ?>
                    <?php $esActual = $actual !== null && $actual['clave'] === $c['clave']; ?>
                    <li>
                        <form method="POST" action="index.php?action=cambiar_contexto">
                            <?php Csrf::field('default'); ?>
                            <input type="hidden" name="contexto" value="<?= $e($c['clave']) ?>">
                            <button type="submit" class="contexto-opcion<?= $esActual ? ' is-actual' : '' ?>">
                                <i class="fas <?= $e($iconos[$c['tipo']] ?? 'fa-user') ?>" aria-hidden="true"></i>
                                <span class="contexto-opcion__texto">
                                    <strong><?= $e($c['tipo'] === Contexto::CLINICA ? $c['clinica'] : Contexto::etiqueta($c)) ?></strong>
                                    <small><?= $e($c['rol']) ?><?= $esActual ? ' · actual' : '' ?></small>
                                </span>
                                <i class="fas fa-chevron-right contexto-opcion__flecha" aria-hidden="true"></i>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a class="contexto-salir" href="index.php?action=logout"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Cerrar sesión</a>
    </main>
</body>
</html>
