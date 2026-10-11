<?php
/**
 * HU-36 / D2 — Confirmar el correo del registro con un botón (POST): abrir el
 * enlace no basta, porque los filtros de correo lo abren solos. D2.2: con el
 * diseño de reset_password.php.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tituloPagina = 'Confirmar correo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php require __DIR__ . '/../partials/cabecera_enlace.php'; ?>
</head>
<body class="login-page reset-page">
<div class="reset-wrapper">
    <main class="reset-card">
        <div class="reset-hero">
            <div class="reset-icon"><i class="ri-mail-check-line" aria-hidden="true"></i></div>
            <h1>Confirma tu correo</h1>
            <p class="reset-subtitle">Presiona el botón para confirmar que este correo te pertenece.</p>
        </div>
        <form method="POST" action="index.php?action=verificar_email" class="reset-form">
            <?php Csrf::field(); ?>
            <input type="hidden" name="id" value="<?= $e($id) ?>">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <button type="submit" class="btn-primary">
                <span>Confirmar mi correo</span>
                <i class="ri-check-line" aria-hidden="true"></i>
            </button>
        </form>
        <a class="reset-enlace-login" href="index.php?action=login">Ir al inicio de sesión</a>
    </main>
</div>
</body>
</html>
