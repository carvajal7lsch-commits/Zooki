<?php
/**
 * HU-42 / HU-39 — Panel "Mi perfil" del personal de la clínica.
 *
 * Lo pinta PerfilController::mostrar() dentro del layout del rol, con:
 * $perfil (cuenta en sesión), $rolNombre y $cuentaGoogle.
 */
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$partes = array_values(array_filter(explode(' ', trim($perfil['nombre_completo'] ?? ''))));
$iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1)) ?: 'U';
?>
<div class="perfil">
    <section class="perfil-hero">
        <div class="perfil-hero__avatar" aria-hidden="true"><?= $e($iniciales) ?></div>
        <div class="perfil-hero__info">
            <h2 class="perfil-hero__name"><?= $e($perfil['nombre_completo']) ?></h2>
            <div class="perfil-hero__meta">
                <span class="perfil-badge"><?= $e($rolNombre) ?></span>
                <span class="perfil-hero__dato"><i class="far fa-envelope"></i> <span id="perfilEmailHero"><?= $e($perfil['email']) ?></span></span>
                <?php if ($cuentaGoogle): ?>
                <span class="perfil-hero__dato"><i class="fab fa-google"></i> Sesión iniciada con Google</span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="perfil-grid">
        <section class="perfil-card" aria-labelledby="perfilDatosTitulo">
            <header class="perfil-card__head">
                <h3 id="perfilDatosTitulo">Datos de la cuenta</h3>
                <p>Mantén tu información de contacto al día.</p>
            </header>

            <dl class="perfil-readonly">
                <div><dt>Nombre completo</dt><dd><?= $e($perfil['nombre_completo']) ?></dd></div>
                <div><dt>Documento</dt><dd><?= $e(trim(($perfil['tipo_documento'] ?? '') . ' ' . $perfil['documento'])) ?></dd></div>
                <div><dt>Rol</dt><dd><?= $e($rolNombre) ?></dd></div>
            </dl>
            <!-- Solo lectura a propósito: el rol lo asigna el administrador (RN-501). -->
            <p class="perfil-hint"><i class="fas fa-lock"></i> El nombre, el documento y el rol los gestiona el administrador de la clínica.</p>

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
        </section>

        <section class="perfil-card" id="seguridad" aria-labelledby="perfilSeguridadTitulo">
            <header class="perfil-card__head">
                <h3 id="perfilSeguridadTitulo">Seguridad</h3>
                <p>Usa una contraseña que no uses en otros sitios.</p>
            </header>

            <form id="perfilPasswordForm" class="perfil-form" novalidate>
                <!-- Siempre se pide: las cuentas del personal las crea el
                     administrador con contraseña, aunque luego se entre con Google. -->
                <div class="perfil-field">
                    <label for="perfilPwdActual">Contraseña actual</label>
                    <input type="password" id="perfilPwdActual" class="perfil-input" autocomplete="current-password" required>
                </div>
                <div class="perfil-field">
                    <label for="perfilPwdNueva">Nueva contraseña</label>
                    <input type="password" id="perfilPwdNueva" class="perfil-input" autocomplete="new-password" required>
                    <small class="perfil-field__hint" id="perfilPwdFeedback" aria-live="polite">Mínimo 8 caracteres, con mayúscula, minúscula y número.</small>
                </div>
                <div class="perfil-field">
                    <label for="perfilPwdConfirmar">Confirmar contraseña</label>
                    <input type="password" id="perfilPwdConfirmar" class="perfil-input" autocomplete="new-password" required>
                </div>
                <div class="perfil-form__foot">
                    <p class="perfil-msg" id="perfilPasswordMsg" role="status" aria-live="polite"></p>
                    <button type="submit" class="perfil-btn perfil-btn--primary" id="perfilPasswordGuardar">Actualizar contraseña</button>
                </div>
            </form>

            <div class="perfil-logout">
                <div>
                    <strong>Cerrar sesión</strong>
                    <p>Termina tu sesión en este dispositivo.</p>
                </div>
                <a href="index.php?action=logout" class="perfil-btn perfil-btn--danger">Cerrar sesión</a>
            </div>
        </section>
    </div>
</div>

<script src="js/perfil.js?v=1"></script>
