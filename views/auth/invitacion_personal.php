<?php
/**
 * D2.2 (RE-T.7.5, RN-705) — Invitación de una clínica a una persona que ya
 * tiene cuenta. Recibe de IdentidadController: $fila (null si el enlace no
 * sirve), $id, $token, $rol, $resultado, $reemplazada y $error. El GET no
 * decide nada. M0.2: marco del acceso.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tituloPagina = 'Invitación a un equipo · Zooki';
$aceptada = ($resultado['decision'] ?? '') === 'aceptada';
$rechazada = ($resultado['decision'] ?? '') === 'rechazada';
require __DIR__ . '/../partials/acceso_marco_inicio.php';
if ($aceptada) {
    $estado = ['tipo' => 'exito', 'icono' => 'ri-team-line', 'titulo' => 'Bienvenido al equipo', 'texto' => 'Ahora eres parte de ' . $resultado['clinica'] . ' como ' . $resultado['rol'] . '. Al iniciar sesión elige con qué perfil quieres continuar; tus otros perfiles siguen igual.'];
} elseif ($rechazada) {
    $estado = ['tipo' => 'aviso', 'icono' => 'ri-close-circle-line', 'titulo' => 'Invitación rechazada', 'texto' => 'No se hizo ningún cambio en tu cuenta y la clínica no verá tus datos.'];
} elseif ($reemplazada ?? false) {
    $estado = ['tipo' => 'aviso', 'icono' => 'ri-mail-unread-line', 'titulo' => 'Hay una invitación más reciente', 'texto' => 'Esta invitación se reemplazó por una más reciente. Usa el enlace del último correo.'];
} elseif ($fila === null) {
    $estado = ['tipo' => 'error', 'icono' => 'ri-link-unlink', 'titulo' => 'Enlace no disponible', 'texto' => 'La invitación no es válida, venció o ya fue utilizada.'];
} else {
    $estado = ['tipo' => 'info', 'icono' => 'ri-team-line', 'titulo' => 'Te invitaron a un equipo', 'html' => '<strong>' . $e($fila['nombre_clinica']) . '</strong> te invitó a su equipo como <strong>' . $e($rol) . '</strong>.'];
}
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <?php if ($error !== null): ?>
                <div class="registro-resumen" role="alert"><p><?= $e($error) ?></p></div>
            <?php endif; ?>
            <?php if ($resultado === null && $fila !== null): ?>
                <p class="acceso-nota">Si aceptas, la clínica verá tu nombre, documento, correo y teléfono, y podrás trabajar en ella con tu misma cuenta. Si no conoces a esta clínica, rechaza la invitación.</p>
                <form method="POST" action="index.php?action=invitacion_personal" class="acceso-form acceso-form--decision">
                    <?php Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?= $e($id) ?>">
                    <input type="hidden" name="token" value="<?= $e($token) ?>">
                    <button type="submit" name="decision" value="aceptar" class="btn-primary"><span>Aceptar la invitación</span><i class="ri-check-line" aria-hidden="true"></i></button>
                    <button type="submit" name="decision" value="rechazar" class="btn-secondary"><span>Rechazar</span></button>
                </form>
            <?php endif; ?>
            <p class="acceso-enlaces"><a class="acceso-enlace" href="index.php?action=login">Ir al inicio de sesión</a></p>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
</body>
</html>
