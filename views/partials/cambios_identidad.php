<?php
/**
 * D2 (RE-T.5.7, RE-T.5.8) — Datos de acceso: el titular corrige su correo o
 * su documento confirmando su identidad. Se incluye en Mi perfil y en el
 * portal; D2.2 (revisión de D2.1): cada campo usa las clases de formulario
 * de la pantalla que lo incluye, sin cambiar su diseño.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/GoogleToken.php';
$eIdentidad = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$cuentaIdentidad = $perfil ?? $usuarioData ?? [];
$enPortal = isset($usuarioData);
$claseFormulario = $enPortal ? 'portal-form' : 'perfil-form';
$claseGrupo = $enPortal ? 'input-group' : 'perfil-field';
$claseEtiqueta = $enPortal ? 'portal-label' : '';
$claseEnvoltura = $enPortal ? 'search-input-wrapper campo' : '';
$claseCampo = $enPortal ? '' : 'perfil-input';
$claseBoton = $enPortal ? 'btn-primary btn-primary--compacto' : 'perfil-btn perfil-btn--primary';
$claseBotonGoogle = $enPortal ? 'btn-enlace' : 'perfil-btn';
$claseAyuda = $enPortal ? 'portal-ayuda' : 'perfil-hint';
$claseResultado = $enPortal ? 'portal-ayuda' : 'perfil-msg';
/** Un campo con la estructura de la pantalla: grupo, etiqueta y, en el portal, su envoltura. */
$campoIdentidad = static function (string $id, string $etiqueta, string $control) use ($claseGrupo, $claseEtiqueta, $claseEnvoltura, $eIdentidad): void {
    echo '<div class="' . $claseGrupo . '">';
    echo '<label for="' . $eIdentidad($id) . '"' . ($claseEtiqueta !== '' ? ' class="' . $claseEtiqueta . '"' : '') . '>' . $eIdentidad($etiqueta) . '</label>';
    echo $claseEnvoltura !== '' ? '<div class="' . $claseEnvoltura . '">' . $control . '</div>' : $control;
    echo '</div>';
};
?>
<details class="cuenta-identidad <?= $enPortal ? 'profile-card' : 'perfil-card' ?>">
    <summary>Datos de acceso</summary>
    <p class="<?= $claseAyuda ?>">Para corregirlos, confirma tu identidad. El correo anterior seguirá vigente hasta verificar el nuevo.</p>
    <?php foreach (['correo', 'documento'] as $datoIdentidad): ?>
        <?php $prefijo = 'identidad' . ucfirst($datoIdentidad); ?>
        <form class="<?= $claseFormulario ?> cuenta-identidad__form" data-validacion-cuenta data-identidad-accion="<?= $datoIdentidad === 'correo' ? 'solicitar_cambio_correo_ajax' : 'cambiar_documento_ajax' ?>" data-google-client="<?= $eIdentidad(GoogleToken::clientId()) ?>">
            <?php Csrf::field(); ?>
            <?php if ($datoIdentidad === 'correo'): ?>
                <?php $campoIdentidad($prefijo . 'Email', 'Correo nuevo', '<input id="' . $prefijo . 'Email" class="' . $claseCampo . '" type="email" name="email" maxlength="255" autocomplete="email" required>'); ?>
                <?php if (empty($cuentaIdentidad['tiene_password'])): ?>
                    <p class="<?= $claseAyuda ?>">Crea una contraseña en la sección de contraseña antes de cambiar tu correo.</p>
                <?php endif; ?>
            <?php else: ?>
                <?php
                $opciones = '';
                foreach (Usuario::TIPOS_DOCUMENTO as $tipoIdentidad) {
                    $opciones .= '<option value="' . $eIdentidad($tipoIdentidad) . '">' . $eIdentidad($tipoIdentidad) . '</option>';
                }
                $campoIdentidad($prefijo . 'Tipo', 'Tipo de documento', '<select id="' . $prefijo . 'Tipo" class="' . $claseCampo . '" name="tipo_documento" required>' . $opciones . '</select>');
                $campoIdentidad($prefijo . 'Numero', 'Documento nuevo', '<input id="' . $prefijo . 'Numero" class="' . $claseCampo . '" type="text" name="documento" inputmode="numeric" pattern="\d{5,15}" maxlength="15" required>');
                ?>
            <?php endif; ?>
            <?php $campoIdentidad($prefijo . 'Clave', 'Contraseña actual', '<input id="' . $prefijo . 'Clave" class="' . $claseCampo . '" type="password" name="password_actual" autocomplete="current-password" maxlength="72"' . (empty($cuentaIdentidad['tiene_google']) ? ' required' : '') . '>'); ?>
            <?php if (!empty($cuentaIdentidad['tiene_google'])): ?>
                <button type="button" class="<?= $claseBotonGoogle ?>" data-confirmar-google>Confirmar mi identidad con Google</button>
                <input type="hidden" name="access_token" value="">
            <?php endif; ?>
            <p class="<?= $claseResultado ?>" role="status" data-identidad-resultado></p>
            <button type="submit" class="<?= $claseBoton ?>">Solicitar cambio de <?= $datoIdentidad ?></button>
        </form>
    <?php endforeach; ?>
</details>
