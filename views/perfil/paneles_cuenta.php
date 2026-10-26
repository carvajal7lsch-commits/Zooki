<?php
/**
 * HU-T.5 / HU-39 — Paneles «Mi cuenta» y «Seguridad» de la cuenta en sesión.
 *
 * Los incluyen Mi perfil del veterinario (views/perfil/index.php) y Ajustes del
 * administrador (views/admin/configuracion.php). Esperan $perfil, $rolNombre,
 * $pideActual, $cuenta y $esVetPerfil (el resumen de horario y ausencias es del veterinario).
 */
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fallidos = (int) $cuenta['fallidos_30'];
$iconos = ['acceso' => 'fa-check', 'fallo' => 'fa-times', 'cambio' => 'fa-pen'];
?>
    <div class="perfil-panel" id="perfilCuenta" role="tabpanel" aria-labelledby="perfilTabCuenta" data-config-panel="cuenta">

        <!-- Datos de contacto -->
        <section class="perfil-card" aria-labelledby="perfilDatosTitulo">
            <header class="perfil-card__head">
                <h3 id="perfilDatosTitulo">Mi cuenta</h3>
                <p>Así te contacta la clínica y te llegan los avisos.</p>
            </header>

            <form id="perfilContactoForm" class="perfil-form" novalidate data-validacion-cuenta>
                <div class="perfil-field">
                    <label>Documento</label>
                    <div class="perfil-campo-acceso"><span data-documento-cuenta><?= $e(trim(($perfil['tipo_documento'] ?? '') . ' ' . $perfil['documento'])) ?></span><button type="button" data-abrir-dialogo="dialogoIdentidadDocumento" aria-label="Cambiar documento">Cambiar</button></div>
                </div>
                <div class="perfil-field"><label for="perfilRol">Rol</label><input id="perfilRol" class="perfil-input" value="<?= $e($rolNombre) ?>" readonly></div>

                <div class="perfil-field">
                    <label for="perfilEmail">Correo electrónico</label>
                    <div class="perfil-campo-acceso"><input <?= ValidadorCuenta::atributosHtml('email') ?> type="email" id="perfilEmail" readonly class="perfil-input" value="<?= $e($perfil['email']) ?>" autocomplete="email" required><button type="button" data-abrir-dialogo="dialogoIdentidadCorreo" aria-label="Cambiar correo">Cambiar</button></div>
                </div>
                <div class="perfil-field">
                    <label for="perfilTelefono">Teléfono</label>
                    <?php // D1: la misma regla de teléfono que valida el servidor. ?>
                    <input type="tel" id="perfilTelefono" class="perfil-input" value="<?= $e($perfil['telefono']) ?>" placeholder="Ej. 300 123 4567" autocomplete="tel" minlength="<?= ValidadorTelefono::MIN ?>" <?= ValidadorTelefono::atributosHtml() ?>>
                </div>
                <div class="perfil-form__foot">
                    <p class="perfil-msg" id="perfilContactoMsg" role="status" aria-live="polite"></p>
                    <button type="submit" class="perfil-btn perfil-btn--primary" id="perfilContactoGuardar" disabled>Guardar cambios</button>
                </div>
            </form>

            <!-- Solo lectura a propósito: el rol lo asigna el administrador (RN-701). -->
            <p class="perfil-hint"><i class="fas fa-lock"></i> El rol lo gestiona el administrador. Puedes corregir tus datos de acceso confirmando tu identidad.</p>
        </section>

        <?php $identidadSoloDialogos = true; require __DIR__ . '/../partials/cambios_identidad.php'; unset($identidadSoloDialogos); ?>
        <?php if ($esVetPerfil): ?>
        <div class="perfil-resumen">
            <section><header><h3>Mi semana</h3><a href="#horarios">Ver horario <i class="fas fa-arrow-right" aria-hidden="true"></i></a></header><ul data-resumen-semana aria-live="polite"><li>Cargando horario…</li></ul></section>
            <section><header><h3>Ausencias</h3><a href="#ausencias">Ver ausencias <i class="fas fa-arrow-right" aria-hidden="true"></i></a></header><p data-resumen-ausencia aria-live="polite">Cargando ausencias…</p></section>
        </div>
        <?php endif; ?>
    </div>
    <div class="perfil-panel perfil-seguridad" id="seguridad" role="tabpanel" aria-labelledby="perfilTabSeguridad" data-config-panel="seguridad" hidden>
        <!-- Contraseña -->
        <section class="perfil-card" aria-labelledby="perfilSeguridadTitulo">
            <header class="perfil-card__head">
                <h3 id="perfilSeguridadTitulo"><?= $pideActual ? 'Cambiar contraseña' : 'Crear contraseña' ?></h3>
                <p><?= $pideActual
                    ? 'Usa una contraseña que no uses en otros sitios.'
                    : 'Tu cuenta entra con Google. Crea una contraseña para entrar también sin Google.' ?></p>
            </header>

            <form id="perfilPasswordForm" class="perfil-form" novalidate data-validacion-cuenta>
                <?php if (!$pideActual) require __DIR__ . '/../partials/confirmacion_google.php'; ?>
                <?php if ($pideActual): ?>
                    <div class="perfil-field">
                        <label for="perfilPwdActual">Contraseña actual</label>
                        <div class="perfil-clave">
                            <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="perfilPwdActual" class="perfil-input" placeholder="Tu contraseña actual" autocomplete="current-password" required>
                            <button type="button" class="perfil-clave__ver" data-ver="perfilPwdActual" aria-label="Mostrar contraseña" aria-pressed="false"><i class="far fa-eye"></i></button>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="perfil-field">
                    <label for="perfilPwdNueva">Nueva contraseña</label>
                    <div class="perfil-clave">
                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" placeholder="Nueva contraseña" id="perfilPwdNueva" data-cuenta-ayuda="perfilPwdFeedback" class="perfil-input" autocomplete="new-password" aria-describedby="perfilPwdRequisitos" required>
                        <button type="button" class="perfil-clave__ver" data-ver="perfilPwdNueva" aria-label="Mostrar contraseña" aria-pressed="false"><i class="far fa-eye"></i></button>
                    </div>
                    <ul class="perfil-requisitos" id="perfilPwdRequisitos">
                        <li data-requisito="longitud">Mínimo 8 caracteres</li>
                        <li data-requisito="mayuscula">Una mayúscula</li>
                        <li data-requisito="minuscula">Una minúscula</li>
                        <li data-requisito="numero">Un número</li>
                    </ul>
                    <small class="perfil-field__hint" id="perfilPwdFeedback" aria-live="polite"></small>
                </div>
                <div class="perfil-field">
                    <label for="perfilPwdConfirmar">Confirmar contraseña</label>
                    <div class="perfil-clave">
                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" placeholder="Repite la nueva" id="perfilPwdConfirmar" data-cuenta-ayuda="perfilPwdCoincide" class="perfil-input" autocomplete="new-password" required>
                        <button type="button" class="perfil-clave__ver" data-ver="perfilPwdConfirmar" aria-label="Mostrar contraseña" aria-pressed="false"><i class="far fa-eye"></i></button>
                    </div>
                    <small class="perfil-field__hint" id="perfilPwdCoincide" aria-live="polite"></small>
                </div>
                <div class="perfil-form__foot">
                    <p class="perfil-msg" id="perfilPasswordMsg" role="status" aria-live="polite"></p>
                    <button type="submit" class="perfil-btn perfil-btn--primary" id="perfilPasswordGuardar"><?= $pideActual ? 'Actualizar contraseña' : 'Crear contraseña' ?></button>
                </div>
            </form>
        </section>

        <!-- Actividad reciente (HU-42 / RN-G05) -->
        <section class="perfil-card" aria-labelledby="perfilActividadTitulo">
            <header class="perfil-card__head">
                <h3 id="perfilActividadTitulo">Actividad reciente</h3>
                <p>Accesos y cambios de tu cuenta. Si no reconoces alguno, cambia tu contraseña.</p>
            </header>

            <?php if ($fallidos > 0): ?>
                <p class="perfil-alerta"><i class="fas fa-exclamation-triangle"></i>
                    <?= $fallidos ?> <?= $fallidos === 1 ? 'intento fallido' : 'intentos fallidos' ?> de acceso en los últimos 30 días.
                </p>
            <?php endif; ?>

            <?php if (!$cuenta['disponible']): ?>
                <p class="perfil-vacio">No se pudo cargar la actividad de la cuenta.</p>
            <?php elseif (!$cuenta['actividad']): ?>
                <p class="perfil-vacio">Todavía no hay actividad registrada.</p>
            <?php else: ?>
                <ol class="perfil-actividad" id="perfilActividad">
                    <?php foreach (array_values($cuenta['actividad']) as $indiceActividad => $a): ?>
                        <li class="perfil-evento" data-tipo="<?= $e($a['tipo']) ?>"<?= $indiceActividad >= 5 ? ' data-actividad-extra hidden' : '' ?>>
                            <span class="perfil-evento__icono" aria-hidden="true"><i class="fas <?= $iconos[$a['tipo']] ?? 'fa-circle' ?>"></i></span>
                            <div class="perfil-evento__info">
                                <span class="perfil-evento__titulo"><?= $e($a['titulo']) ?></span>
                                <span class="perfil-evento__meta"><?= $e($a['momento']) ?><?= $a['ip'] !== '' ? ' · IP ' . $e($a['ip']) : '' ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (count($cuenta['actividad']) > 5): ?>
                <nav class="perfil-paginacion" aria-label="Páginas de actividad"><button type="button" class="perfil-btn perfil-btn--sec" data-actividad-anterior disabled>Anterior</button><span data-actividad-pagina role="status">Página 1 de <?= (int) ceil(count($cuenta['actividad']) / 5) ?></span><button type="button" class="perfil-btn perfil-btn--sec" data-actividad-siguiente>Siguiente</button></nav>
                <?php endif; ?>
            <?php endif; ?>

        </section>
    </div>
