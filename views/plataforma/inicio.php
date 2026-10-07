<?php
/**
 * Página mínima de la plataforma (C1). El panel del super-administrador
 * (HU-0.3) llega en la etapa E; por ahora confirma que el contexto de
 * plataforma funciona y no ofrece ningún dato clínico (RN-004, RE-T.15.4).
 */
require_once __DIR__ . '/../../config/App.php';

$__nombre = (string) ($_SESSION['usuario_nombre'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma · Zooki</title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/contexto.css?v=<?= htmlspecialchars(App::assetVersion()) ?>">
</head>
<body class="contexto-pagina">
    <main class="contexto-tarjeta">
        <img src="img/icon_blue.png" alt="Zooki" class="contexto-logo">
        <h1 class="contexto-titulo">Plataforma Zooki</h1>
        <p class="contexto-texto">
            <?= htmlspecialchars($__nombre) ?>, entraste como super-administrador. El panel para gestionar
            clínicas, planes y suscripciones estará disponible en una próxima entrega.
        </p>
        <a class="contexto-salir" href="index.php?action=logout"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Cerrar sesión</a>
    </main>
</body>
</html>
