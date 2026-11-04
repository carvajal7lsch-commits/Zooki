<?php
/**
 * Tarjeta «Modo demo» (decisión del dueño, 2026-11-03). Solo se incluye con
 * ZOOKI_DEMO_HABILITADA=1. El comportamiento está en js/demo-plataforma.js.
 */
$hoy = (new DateTimeImmutable('today', new DateTimeZone('America/Bogota')))->format('Y-m-d');
?>
<section class="plataforma-panel" id="panelDemo" role="tabpanel" aria-labelledby="pestanaDemo" data-panel="demo" hidden>
    <div class="personal-grid view-grid plataforma-tarjetas">
        <article class="person-card plataforma-tarjeta plataforma-demo" data-demo>
            <div class="card-header-mini">
                <span class="avatar-mini avatar-iniciales plataforma-icono-tarjeta" aria-hidden="true"><i class="bi bi-easel"></i></span>
                <span class="tag-mini plataforma-estado plataforma-estado--pendiente"><i class="bi bi-person-badge" aria-hidden="true"></i> Datos ficticios</span>
            </div>
            <div class="card-body-mini">
                <h3 class="card-title-mini">Modo demo</h3>
                <p class="plataforma-tarjeta__texto">Las mismas operaciones de <code>scripts/semilla_demo.php</code>, sin entrar al servidor. Solo tocan las cinco clínicas y las cuentas de la demo.</p>

                <form class="plataforma-form plataforma-demo__form" data-demo-accion="plataforma_demo_crear_ajax" novalidate>
                    <?php Csrf::field(); ?>
                    <h4 class="plataforma-demo__titulo">Crear o rehacer demo</h4>
                    <label class="input-group">Día de la demo
                        <input type="date" name="dia" value="<?= $e($hoy) ?>" required data-demo-dia>
                        <small data-error-campo role="status"></small>
                    </label>
                    <label class="input-group">Clave para todas las cuentas
                        <input type="password" name="clave" required autocomplete="new-password" placeholder="Mínimo 8, con mayúscula, minúscula y número" <?= ValidadorCuenta::atributosHtml('password') ?> data-demo-clave>
                        <small data-error-campo role="status"></small>
                    </label>
                    <button type="submit" class="plataforma-boton-mini plataforma-boton-mini--principal" data-demo-boton><i class="bi bi-arrow-repeat" aria-hidden="true"></i> <span>Crear o rehacer demo</span></button>
                </form>

                <form class="plataforma-form plataforma-demo__form" data-demo-accion="plataforma_demo_escena_ajax">
                    <?php Csrf::field(); ?>
                    <h4 class="plataforma-demo__titulo">Preparar escena ahora</h4>
                    <p class="plataforma-tarjeta__nota">Rehace la agenda de hoy de San Roque. De lunes a sábado, entre 8:00 y 18:00.</p>
                    <button type="submit" class="plataforma-boton-mini plataforma-boton-mini--principal" data-demo-boton><i class="bi bi-play-circle" aria-hidden="true"></i> <span>Preparar escena ahora</span></button>
                </form>

                <form class="plataforma-form plataforma-demo__form" data-demo-accion="plataforma_demo_borrar_ajax" data-demo-confirmar="Se borran las cinco clínicas de la demo, sus cuentas y todos sus datos ficticios. No se puede deshacer.">
                    <?php Csrf::field(); ?>
                    <h4 class="plataforma-demo__titulo">Borrar demo</h4>
                    <button type="submit" class="plataforma-boton-mini plataforma-demo__borrar" data-demo-boton><i class="bi bi-trash3" aria-hidden="true"></i> <span>Borrar demo</span></button>
                </form>

                <div class="plataforma-demo__resultado" data-demo-resultado role="status" aria-live="polite"></div>
            </div>
        </article>
    </div>
</section>
