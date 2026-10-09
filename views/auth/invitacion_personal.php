<?php
/**
 * D2.2 (RE-T.7.5, RN-705) — Invitación de una clínica a una persona que ya
 * tiene cuenta. Recibe de IdentidadController: $fila (null si el enlace no
 * sirve), $id, $token, $rol, $resultado y $error. El GET no decide nada.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tituloPagina = 'Invitación a un equipo';
$aceptada = ($resultado['decision'] ?? '') === 'aceptada';
$rechazada = ($resultado['decision'] ?? '') === 'rechazada';
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
            <div class="reset-icon"><i class="ri-team-line" aria-hidden="true"></i></div>
            <?php if ($aceptada): ?>
                <h1>Bienvenido al equipo</h1>
                <p class="reset-subtitle">Ahora eres parte de <?= $e($resultado['clinica']) ?> como <?= $e($resultado['rol']) ?>. Al iniciar sesión elige con qué perfil quieres continuar; tus otros perfiles siguen igual.</p>
            <?php elseif ($rechazada): ?>
                <h1>Invitación rechazada</h1>
                <p class="reset-subtitle">No se hizo ningún cambio en tu cuenta y la clínica no verá tus datos.</p>
            <?php elseif ($fila === null): ?>
                <h1>Enlace no disponible</h1>
                <p class="reset-subtitle">La invitación no es válida, venció o ya fue utilizada.</p>
            <?php else: ?>
                <h1>Te invitaron a un equipo</h1>
                <p class="reset-subtitle"><strong><?= $e($fila['nombre_clinica']) ?></strong> te invitó a su equipo como <strong><?= $e($rol) ?></strong>.</p>
            <?php endif; ?>
        </div>

        <?php if ($error !== null): ?>
            <p class="reset-aviso" role="alert"><?= $e($error) ?></p>
        <?php endif; ?>

        <?php if ($resultado === null && $fila !== null): ?>
            <p class="reset-detalle">Si aceptas, la clínica verá tu nombre, documento, correo y teléfono, y podrás trabajar en ella con tu misma cuenta. Si no conoces a esta clínica, rechaza la invitación.</p>
            <form method="POST" action="index.php?action=invitacion_personal" class="reset-form reset-decision">
                <?php Csrf::field(); ?>
                <input type="hidden" name="id" value="<?= $e($id) ?>">
                <input type="hidden" name="token" value="<?= $e($token) ?>">
                <button type="submit" name="decision" value="aceptar" class="btn-primary">
                    <span>Aceptar la invitación</span>
                    <i class="ri-check-line" aria-hidden="true"></i>
                </button>
                <button type="submit" name="decision" value="rechazar" class="btn-secondary">
                    <span>Rechazar</span>
                </button>
            </form>
        <?php endif; ?>

        <a class="reset-enlace-login" href="index.php?action=login">Ir al inicio de sesión</a>
    </main>
</div>
</body>
</html>
