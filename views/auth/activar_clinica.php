<?php
/**
 * HU-0.2 — Confirmar el registro de la clínica desde el correo (POST con
 * CSRF, consentimiento y token vigente). Recibe de RegistroClinicaController
 * $fila, $id, $token, $error, $terminado y $superAdministrador.
 * M0.2: marco del acceso; la cuenta del super-administrador recibe el motivo
 * claro (bitácora de F1) y no ve el botón.
 */
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$superAdministrador = $superAdministrador ?? false;
$tituloPagina = 'Activar clínica · Zooki';
require __DIR__ . '/../partials/acceso_marco_inicio.php';
if ($terminado) {
    $estado = ['tipo' => 'exito', 'icono' => 'ri-hospital-line', 'titulo' => 'Tu clínica está activa', 'texto' => 'Ya puedes iniciar sesión. Si tienes otros roles, elige la clínica al entrar.'];
} elseif ($fila === null) {
    $estado = ['tipo' => 'error', 'icono' => 'ri-link-unlink', 'titulo' => 'Enlace no disponible', 'texto' => 'El enlace no es válido, venció o ya fue utilizado.'];
} elseif ($superAdministrador) {
    $estado = ['tipo' => 'aviso', 'icono' => 'ri-shield-user-line', 'titulo' => 'Usa otro correo', 'texto' => $error];
} else {
    $estado = ['tipo' => 'info', 'icono' => 'ri-hospital-line', 'titulo' => 'Confirma el registro', 'html' => 'Confirma que quieres registrar <strong>' . $e($fila['clinica']) . '</strong> con tu cuenta y ser su administrador.'];
}
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <?php if ($error !== null && !$superAdministrador): ?>
                <div class="registro-resumen" role="alert"><p><?= $e($error) ?></p></div>
            <?php endif; ?>
            <?php if ($fila !== null && !$superAdministrador): ?>
                <?php // D3.1: antes y después de confirmar, quien ya tenía cuenta sabe con qué contraseña entra. ?>
                <p class="acceso-nota">Al iniciar sesión elegirás la clínica.<?php if ($fila['proposito'] === 'vinculo_admin_clinica'): ?> La contraseña escrita en el formulario no se usó. Entrarás con tu contraseña de siempre. <a class="acceso-enlace" href="index.php?action=login&amp;recuperar=1">¿Olvidaste tu contraseña?</a><?php endif; ?></p>
            <?php endif; ?>
            <?php if (!$terminado && $fila !== null && !$superAdministrador): ?>
                <form method="POST" action="index.php?action=activar_clinica" class="acceso-form">
                    <?php Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?= $e($id) ?>">
                    <input type="hidden" name="token" value="<?= $e($token) ?>">
                    <?php $consentimientoId = 'aceptaDatosActivacion'; require __DIR__ . '/../partials/consentimiento_datos.php'; ?>
                    <button type="submit" class="btn-primary"><span>Confirmar y activar clínica</span><i class="ri-check-line" aria-hidden="true"></i></button>
                </form>
            <?php endif; ?>
            <p class="acceso-enlaces">
                <?php if ($superAdministrador): ?><a class="acceso-enlace" href="index.php?action=registro_clinica">Registrar con otro correo</a><?php endif; ?>
                <a class="acceso-enlace" href="index.php?action=login">Ir al inicio de sesión</a>
            </p>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
</body>
</html>
