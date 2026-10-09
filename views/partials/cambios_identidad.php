<?php
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/GoogleToken.php';
$eIdentidad = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$cuentaIdentidad = $perfil ?? $usuarioData ?? [];
$claseFormulario = isset($usuarioData) ? 'portal-form' : 'perfil-form';
$claseCampo = isset($usuarioData) ? 'portal-input' : 'perfil-input';
$claseBoton = isset($usuarioData) ? 'btn-primary' : 'perfil-btn perfil-btn--primary';
?>
<details class="cuenta-identidad <?= isset($usuarioData) ? 'profile-card' : 'perfil-card' ?>">
    <summary>Datos de acceso</summary>
    <p>Para corregirlos, confirma tu identidad. El correo anterior seguirá vigente hasta verificar el nuevo.</p>
    <?php foreach (['correo', 'documento'] as $datoIdentidad): ?>
        <form class="<?= $claseFormulario ?>" data-validacion-cuenta data-identidad-accion="<?= $datoIdentidad === 'correo' ? 'solicitar_cambio_correo_ajax' : 'cambiar_documento_ajax' ?>" data-google-client="<?= $eIdentidad(GoogleToken::clientId()) ?>">
            <?php Csrf::field(); ?>
            <?php if ($datoIdentidad === 'correo'): ?>
                <label>Correo nuevo <input class="<?= $claseCampo ?>" type="email" name="email" maxlength="255" autocomplete="email" required></label>
                <?php if (empty($cuentaIdentidad['tiene_password'])): ?>
                    <p>Crea una contraseña en la sección de contraseña antes de cambiar tu correo.</p>
                <?php endif; ?>
            <?php else: ?>
                <label>Tipo de documento <select class="<?= $claseCampo ?>" name="tipo_documento" required>
                    <?php foreach (Usuario::TIPOS_DOCUMENTO as $tipoIdentidad): ?>
                        <option value="<?= $eIdentidad($tipoIdentidad) ?>"><?= $eIdentidad($tipoIdentidad) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label>Documento nuevo <input class="<?= $claseCampo ?>" type="text" name="documento" inputmode="numeric" pattern="\d{5,15}" maxlength="15" required></label>
            <?php endif; ?>
            <label>Contraseña actual <input class="<?= $claseCampo ?>" type="password" name="password_actual" autocomplete="current-password" maxlength="72" <?= empty($cuentaIdentidad['tiene_google']) ? 'required' : '' ?>></label>
            <?php if (!empty($cuentaIdentidad['tiene_google'])): ?>
                <button type="button" data-confirmar-google>Confirmar mi identidad con Google</button>
                <input type="hidden" name="access_token" value="">
            <?php endif; ?>
            <p role="status" data-identidad-resultado></p>
            <button type="submit" class="<?= $claseBoton ?>">Solicitar cambio de <?= $datoIdentidad ?></button>
        </form>
    <?php endforeach; ?>
</details>
