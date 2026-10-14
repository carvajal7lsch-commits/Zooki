<?php
/**
 * HU-5.8 (RN-109) — El titular confirma el vínculo de su cuenta con la
 * clínica que eligió al registrarse. Recibe de PropietarioController $id,
 * $token y $resultado (null antes de confirmar). M0.2: marco del acceso.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tituloPagina = 'Confirmar vínculo · Zooki';
require __DIR__ . '/../partials/acceso_marco_inicio.php';
if ($resultado === null) {
    $estado = ['tipo' => 'info', 'icono' => 'ri-links-line', 'titulo' => 'Vínculo con una clínica', 'texto' => 'Confirma solo si elegiste vincular tu cuenta a la clínica indicada en el correo.'];
} elseif ($resultado && ($pendiente ?? false)) {
    // M5 C3: la cuenta la registró otra clínica y aún no se activa.
    $estado = ['tipo' => 'exito', 'titulo' => 'Vínculo confirmado', 'texto' => 'Tu cuenta quedó vinculada a la clínica. Para entrar, actívala con el enlace de activación que te enviamos.'];
} elseif ($resultado) {
    $estado = ['tipo' => 'exito', 'titulo' => 'Vínculo confirmado', 'texto' => 'Tu cuenta quedó vinculada a la clínica. Ya puedes iniciar sesión.'];
} else {
    $estado = ['tipo' => 'error', 'icono' => 'ri-link-unlink', 'titulo' => 'Enlace no disponible', 'texto' => 'El enlace no es válido, venció o ya fue utilizado.'];
}
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <?php if ($resultado === null): ?>
            <form method="post" action="index.php?action=confirmar_vinculo_propietario" class="acceso-form">
                <?php Csrf::field(); ?>
                <input type="hidden" name="id" value="<?= $e($id) ?>">
                <input type="hidden" name="token" value="<?= $e($token) ?>">
                <button type="submit" class="btn-primary"><span>Confirmar vínculo</span><i class="ri-check-line" aria-hidden="true"></i></button>
            </form>
            <?php endif; ?>
            <p class="acceso-enlaces"><a class="acceso-enlace" href="index.php?action=login">Ir al inicio de sesión</a></p>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
</body>
</html>
