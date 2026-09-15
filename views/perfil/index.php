<?php
/**
 * HU-42 / HU-39 — Panel "Mi perfil" del personal de la clínica.
 *
 * Lo pinta PerfilController::mostrar() dentro del layout del rol, con:
 * $perfil (cuenta en sesión), $rolNombre, $cuentaGoogle, $pideActual y
 * $cuenta (miembro desde, acceso anterior, intentos fallidos y actividad).
 */
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$partes = array_values(array_filter(explode(' ', trim($perfil['nombre_completo'] ?? ''))));
$iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1)) ?: 'U';
$fallidos = (int) $cuenta['fallidos_30'];
$iconos = ['acceso' => 'fa-check', 'fallo' => 'fa-times', 'cambio' => 'fa-pen'];
?>
<div class="perfil">

    <section class="perfil-hero">
        <div class="perfil-hero__avatar" aria-hidden="true"><?= $e($iniciales) ?></div>
        <div class="perfil-hero__info">
            <span class="perfil-hero__rol"><?= $e($rolNombre) ?></span>
            <h2 class="perfil-hero__nombre"><?= $e($perfil['nombre_completo']) ?></h2>
            <ul class="perfil-hero__datos">
                <li><i class="far fa-envelope"></i> <span id="perfilEmailHero"><?= $e($perfil['email']) ?></span></li>
                <?php if ($cuenta['miembro_desde']): ?>
                    <li><i class="far fa-calendar"></i> Miembro desde <?= $e($cuenta['miembro_desde']) ?></li>
                <?php endif; ?>
                <?php if ($cuenta['acceso_anterior']): ?>
                    <li><i class="far fa-clock"></i> Acceso anterior: <?= $e($cuenta['acceso_anterior']) ?></li>
                <?php endif; ?>
                <?php if ($cuentaGoogle): ?>
                    <li><i class="fab fa-google"></i> Sesión iniciada con Google</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>

    <div class="perfil-grid">

        <!-- Datos de contacto -->
        <section class="perfil-card" aria-labelledby="perfilDatosTitulo">
            <header class="perfil-card__head">
                <h3 id="perfilDatosTitulo">Datos de contacto</h3>
                <p>Así te contacta la clínica y te llegan los avisos.</p>
            </header>

            <dl class="perfil-readonly">
                <div><dt>Documento</dt><dd><?= $e(trim(($perfil['tipo_documento'] ?? '') . ' ' . $perfil['documento'])) ?></dd></div>
                <div><dt>Rol</dt><dd><?= $e($rolNombre) ?></dd></div>
            </dl>

            <form id="perfilContactoForm" class="perfil-form" novalidate>
                <div class="perfil-field">
                    <label for="perfilEmail">Correo electrónico</label>
                    <input type="email" id="perfilEmail" class="perfil-input" value="<?= $e($perfil['email']) ?>" autocomplete="email" required>
                </div>
                <div class="perfil-field">
                    <label for="perfilTelefono">Teléfono</label>
                    <input type="tel" id="perfilTelefono" class="perfil-input" value="<?= $e($perfil['telefono']) ?>" placeholder="Ej. 300 123 4567" autocomplete="tel">
                </div>
                <div class="perfil-form__foot">
                    <p class="perfil-msg" id="perfilContactoMsg" role="status" aria-live="polite"></p>
                    <button type="submit" class="perfil-btn perfil-btn--primary" id="perfilContactoGuardar" disabled>Guardar cambios</button>
                </div>
            </form>

            <!-- Solo lectura a propósito: el rol lo asigna el administrador (RN-501). -->
            <p class="perfil-hint"><i class="fas fa-lock"></i> El nombre, el documento y el rol los gestiona el administrador de la clínica.</p>
        </section>

        <!-- Contraseña -->
        <section class="perfil-card" id="seguridad" aria-labelledby="perfilSeguridadTitulo">
            <header class="perfil-card__head">
                <h3 id="perfilSeguridadTitulo"><?= $pideActual ? 'Cambiar contraseña' : 'Crear contraseña' ?></h3>
                <p><?= $pideActual
                    ? 'Usa una contraseña que no uses en otros sitios.'
                    : 'Tu cuenta entra con Google. Crea una contraseña para entrar también sin Google.' ?></p>
            </header>

            <form id="perfilPasswordForm" class="perfil-form" novalidate>
                <?php if ($pideActual): ?>
                    <div class="perfil-field">
                        <label for="perfilPwdActual">Contraseña actual</label>
                        <div class="perfil-clave">
                            <input type="password" id="perfilPwdActual" class="perfil-input" autocomplete="current-password" required>
                            <button type="button" class="perfil-clave__ver" data-ver="perfilPwdActual" aria-label="Mostrar contraseña" aria-pressed="false"><i class="far fa-eye"></i></button>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="perfil-field">
                    <label for="perfilPwdNueva">Nueva contraseña</label>
                    <div class="perfil-clave">
                        <input type="password" id="perfilPwdNueva" class="perfil-input" autocomplete="new-password" aria-describedby="perfilPwdRequisitos" required>
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
                        <input type="password" id="perfilPwdConfirmar" class="perfil-input" autocomplete="new-password" required>
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
                <ol class="perfil-actividad">
                    <?php foreach ($cuenta['actividad'] as $a): ?>
                        <li class="perfil-evento" data-tipo="<?= $e($a['tipo']) ?>">
                            <span class="perfil-evento__icono" aria-hidden="true"><i class="fas <?= $iconos[$a['tipo']] ?? 'fa-circle' ?>"></i></span>
                            <div class="perfil-evento__info">
                                <span class="perfil-evento__titulo"><?= $e($a['titulo']) ?></span>
                                <span class="perfil-evento__meta"><?= $e($a['momento']) ?><?= $a['ip'] !== '' ? ' · IP ' . $e($a['ip']) : '' ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <div class="perfil-logout">
                <span>¿Terminaste por hoy?</span>
                <a href="index.php?action=logout" class="perfil-btn perfil-btn--danger"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
            </div>
        </section>
    </div>
</div>

<script src="js/perfil.js?v=2"></script>
