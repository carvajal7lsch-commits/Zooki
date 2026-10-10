<?php
$tituloPagina = 'Activar clínica';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
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
            <div class="reset-icon"><i class="ri-hospital-line" aria-hidden="true"></i></div>
            <?php if ($terminado): ?>
                <h1>Tu clínica está activa</h1>
                <p class="reset-subtitle">Ya puedes iniciar sesión. Si tienes otros roles, elige la clínica al entrar.</p>
            <?php elseif ($fila === null): ?>
                <h1>Enlace no disponible</h1>
                <p class="reset-subtitle">El enlace no es válido, venció o ya fue utilizado.</p>
            <?php else: ?>
                <h1>Confirma el registro</h1>
                <p class="reset-subtitle">Confirma que quieres registrar <strong><?= $e($fila['clinica']) ?></strong> con tu cuenta y ser su administrador.</p>
            <?php endif; ?>
        </div>
        <?php if ($error !== null): ?>
            <p class="reset-aviso" role="alert"><?= $e($error) ?></p>
        <?php endif; ?>
        <?php if (!$terminado && $fila !== null): ?>
            <form method="POST" action="index.php?action=activar_clinica" class="reset-form">
                <?php Csrf::field(); ?>
                <input type="hidden" name="id" value="<?= $e($id) ?>">
                <input type="hidden" name="token" value="<?= $e($token) ?>">
                <label class="registro-consentimiento">
                    <input type="checkbox" name="acepta_datos" value="1" required>
                    <span>Acepto la <a href="index.php?action=privacidad" target="_blank" rel="noopener">política de tratamiento de datos</a>.</span>
                </label>
                <button type="submit" class="btn-primary">Confirmar y activar clínica</button>
            </form>
        <?php endif; ?>
        <a class="reset-back" href="index.php?action=login">Ir a iniciar sesión</a>
    </main>
</div>
</body>
</html>
