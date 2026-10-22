<?php
/**
 * D2 (RE-T.5.7, RE-T.5.8) — Datos de acceso: el titular corrige su correo o
 * su documento confirmando su identidad. Se incluye en Mi perfil y en el
 * portal; D2.2 (revisión de D2.1): cada campo usa las clases de formulario
 * de la pantalla que lo incluye, sin cambiar su diseño.
 *
 * M5 C4a: con $identidadDato ('correo' o 'documento') se pinta solo ese
 * formulario, sin la tarjeta plegable: el portal lo pone en su modal.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
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
<?php $datosIdentidad = isset($identidadDato) ? [$identidadDato] : ['correo', 'documento']; ?>
<?php if (!isset($identidadDato) && empty($identidadSoloDialogos)): ?>
<?php if ($enPortal): ?>
<details class="cuenta-identidad profile-card portal-plegable">
    <summary><i class="ri-shield-user-line" aria-hidden="true"></i> Datos de acceso</summary>
<?php else: ?>
<section class="cuenta-identidad perfil-card" aria-labelledby="perfilAccesoTitulo">
    <header class="perfil-card__head"><h3 id="perfilAccesoTitulo">Datos de acceso</h3></header>
<?php endif; ?>
    <p class="<?= $claseAyuda ?>">Para corregirlos, confirma tu identidad. El correo anterior seguirá vigente hasta verificar el nuevo.</p>
<?php endif; ?>
    <?php foreach ($datosIdentidad as $datoIdentidad): ?>
        <?php $prefijo = 'identidad' . ucfirst($datoIdentidad); ?>
        <?php $dialogoIdentidad = !$enPortal && !isset($identidadDato); ?>
        <?php if ($dialogoIdentidad): ?>
        <?php if (empty($identidadSoloDialogos)): ?>
        <div class="perfil-acceso-fila">
            <span><?= $datoIdentidad === 'correo' ? 'Correo' : 'Documento' ?></span>
            <strong<?= $datoIdentidad === 'documento' ? ' data-documento-cuenta' : '' ?>><?= $eIdentidad($cuentaIdentidad[$datoIdentidad === 'correo' ? 'email' : 'documento'] ?? '') ?></strong>
            <button type="button" class="perfil-btn perfil-btn--sec" data-abrir-dialogo="dialogo<?= ucfirst($prefijo) ?>" aria-label="Cambiar <?= $datoIdentidad ?>">Cambiar</button>
        </div>
        <?php endif; ?>
        <dialog class="zk-dialogo perfil-dialogo" id="dialogo<?= ucfirst($prefijo) ?>" aria-labelledby="titulo<?= ucfirst($prefijo) ?>">
        <?php endif; ?>
        <form class="<?= $claseFormulario ?> cuenta-identidad__form<?= $dialogoIdentidad ? ' zk-dialogo__form' : '' ?>" data-validacion-cuenta data-identidad-accion="<?= $datoIdentidad === 'correo' ? 'solicitar_cambio_correo_ajax' : 'cambiar_documento_ajax' ?>" data-google-client="<?= $eIdentidad($clienteGoogleIdentidad ?? GoogleToken::clientId()) ?>">
            <?php if ($dialogoIdentidad): ?>
            <header class="zk-dialogo__head"><span class="zk-dialogo__icono" aria-hidden="true"><i class="fas fa-id-card"></i></span><div><h3 id="titulo<?= ucfirst($prefijo) ?>">Cambiar <?= $datoIdentidad ?></h3><p>Confirma tu identidad para corregir este dato.</p></div><button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button></header>
            <div class="zk-dialogo__cuerpo">
            <?php endif; ?>
            <?php Csrf::field(); ?>
            <?php if ($datoIdentidad === 'correo'): ?>
                <?php // M5 C4a: con el correo actual, el aviso dice «Ese ya es tu correo» sin ir al servidor. ?>
                <?php $campoIdentidad($prefijo . 'Email', 'Correo nuevo', '<input id="' . $prefijo . 'Email" class="' . $claseCampo . '" type="email" name="email" ' . ValidadorCuenta::atributosHtml('email') . ' data-correo-actual="' . $eIdentidad($cuentaIdentidad['email'] ?? '') . '" autocomplete="email" required>'); ?>
                <?php if (empty($cuentaIdentidad['tiene_password'])): ?>
                    <p class="<?= $claseAyuda ?>"><?= $enPortal ? 'Primero crea una contraseña en Seguridad.' : 'Crea una contraseña en la sección de contraseña antes de cambiar tu correo.' ?></p>
                <?php endif; ?>
            <?php else: ?>
                <?php
                if ($dialogoIdentidad) echo '<div class="zk-dialogo__campos">';
                $opciones = '';
                foreach (Usuario::TIPOS_DOCUMENTO as $tipoIdentidad) {
                    $opciones .= '<option value="' . $eIdentidad($tipoIdentidad) . '">' . $eIdentidad($tipoIdentidad) . '</option>';
                }
                $campoIdentidad($prefijo . 'Tipo', 'Tipo de documento', '<select id="' . $prefijo . 'Tipo" class="' . $claseCampo . '" name="tipo_documento" required>' . $opciones . '</select>');
                $campoIdentidad($prefijo . 'Numero', 'Documento nuevo', '<input id="' . $prefijo . 'Numero" class="' . $claseCampo . '" type="text" name="documento" ' . ValidadorCuenta::atributosHtml('documento') . ' pattern="\d{5,15}" required>');
                if ($dialogoIdentidad) echo '</div>';
                ?>
            <?php endif; ?>
            <?php
            // M5 C4a: en el portal, el ojito para ver la contraseña que se escribe (como en el login).
            $ojitoIdentidad = $enPortal ? '<button type="button" class="portal-ojito" data-ojito="' . $prefijo . 'Clave" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false"><i class="ri-eye-line portal-ojito__ver" aria-hidden="true"></i><i class="ri-eye-off-line portal-ojito__ocultar" aria-hidden="true"></i></button>' : '<button type="button" class="perfil-clave__ver" data-ver="' . $prefijo . 'Clave" aria-label="Mostrar contraseña" aria-pressed="false"><i class="far fa-eye" aria-hidden="true"></i></button>';
            $campoIdentidad($prefijo . 'Clave', 'Contraseña actual', ($dialogoIdentidad ? '<div class="perfil-clave">' : '') . '<input id="' . $prefijo . 'Clave" class="' . $claseCampo . '" type="password" name="password_actual" autocomplete="current-password"' . ($dialogoIdentidad ? ' placeholder="Tu contraseña actual"' : '') . ' ' . ValidadorCuenta::atributosHtml('password') . (empty($cuentaIdentidad['tiene_google']) ? ' required' : '') . '>' . $ojitoIdentidad . ($dialogoIdentidad ? '</div>' : ''));
            ?>
            <?php if (!empty($cuentaIdentidad['tiene_google'])): ?>
                <button type="button" class="<?= $claseBotonGoogle ?>" data-confirmar-google>Confirmar mi identidad con Google</button>
                <input type="hidden" name="access_token" value="">
            <?php endif; ?>
            <p class="<?= $claseResultado ?>" role="status" data-identidad-resultado></p>
            <?php if ($dialogoIdentidad): ?>
            </div>
            <footer class="zk-dialogo__pie"><button type="button" class="perfil-btn perfil-btn--sec" data-cerrar-dialogo>Cancelar</button><button type="submit" class="<?= $claseBoton ?>">Solicitar cambio de <?= $datoIdentidad ?></button></footer>
            <?php elseif (isset($identidadDato)): ?>
                <div class="users-modal__footer portal-modal__pie">
                    <button type="submit" class="btn-primary">Solicitar cambio de <?= $datoIdentidad ?></button>
                </div>
            <?php else: ?>
                <button type="submit" class="<?= $claseBoton ?>">Solicitar cambio de <?= $datoIdentidad ?></button>
            <?php endif; ?>
        </form>
        <?php if ($dialogoIdentidad): ?></dialog><?php endif; ?>
    <?php endforeach; ?>
<?php if (!isset($identidadDato) && empty($identidadSoloDialogos)): ?>
<?php if ($enPortal): ?>
</details>
<?php else: ?>
</section>
<?php endif; ?>
<?php endif; ?>
