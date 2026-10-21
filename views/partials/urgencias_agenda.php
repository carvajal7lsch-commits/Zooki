<?php
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../helpers/ValidadorMascota.php';
$esVetUrgencia = Contexto::rolActivo() === Roles::VETERINARIO;
$reglasUrgencia = ['nombreMax'=>ValidadorCuenta::NOMBRE_MAX,'nombreCaracteres'=>ValidadorCuenta::NOMBRE_CARACTERES,'telefonoMin'=>ValidadorTelefono::MIN,'telefonoMax'=>ValidadorTelefono::MAX,'mascotaMax'=>ValidadorMascota::NOMBRE_MAX];
?>
<section class="agenda-herramientas__panel" id="agenda-panel-urgencias" role="tabpanel" aria-labelledby="agenda-tab-urgencias" hidden data-urgencias-agenda data-vet="<?= $esVetUrgencia ? '1' : '0' ?>" data-clinica="<?= (int)Contexto::clinicaActiva() ?>" data-reglas="<?= htmlspecialchars(json_encode($reglasUrgencia),ENT_QUOTES,'UTF-8') ?>">
    <header class="ah-head">
        <div>
            <h3>Urgencias</h3>
            <p>El aviso remoto no acredita llegada. La prioridad y la interrupción las decide el veterinario.</p>
        </div>
        <div class="ah-acciones">
            <?php if ($esVetUrgencia): ?>
            <button type="button" class="zk-btn zk-btn--peligro" data-abrir-dialogo="dialogoRojo"><i class="fas fa-plus" aria-hidden="true"></i> Rojo presencial</button>
            <?php endif; ?>
            <button type="button" class="zk-icono" data-urgencias-actualizar title="Actualizar" aria-label="Actualizar urgencias"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>
        </div>
    </header>
    <div data-urgencias-csrf hidden><?php Csrf::field(); ?></div>
    <p class="ah-estado" data-urgencias-aviso role="status"></p>
    <div class="ah-grupo">
        <h4 class="ah-sub">Rojos activos</h4>
        <div class="ah-lista" data-rojos-lista data-vacio="No hay urgencias activas."></div>
    </div>
    <div class="ah-grupo">
        <h4 class="ah-sub">Identidad pendiente <span class="ah-ayuda">Solo cuenta, vínculo o confirmación del titular acreditan propiedad.</span></h4>
        <div class="ah-lista" data-ingresos-lista data-vacio="No hay fichas por completar."></div>
    </div>
    <?php if ($esVetUrgencia): ?>
    <dialog class="zk-dialogo" id="dialogoRojo" aria-labelledby="dialogoRojoTitulo">
        <form data-ingreso-rojo novalidate class="zk-dialogo__form ah-dialogo">
            <header class="zk-dialogo__head">
                <h3 id="dialogoRojoTitulo">Registrar rojo presencial</h3>
                <button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button>
            </header>
            <div class="zk-dialogo__cuerpo">
                <label>Identidad <select name="identidad"><option value="conocida">Cuenta y ficha verificadas</option><option value="provisional">Relación no verificada o sin cuenta</option></select></label>
                <label data-identidad-conocida>Mascota <select name="id_mascota" required></select><span data-error-campo="id_mascota" aria-live="polite"></span></label>
                <fieldset data-identidad-provisional hidden disabled>
                    <legend>Ficha provisional: no atribuye propiedad ni crea cuenta.</legend>
                    <label>Nombre de mascota <span class="ah-opcional">(opcional)</span> <input name="nombre_mascota" <?= ValidadorMascota::atributosHtml('nombre') ?>><span data-error-campo="nombre_mascota" aria-live="polite"></span></label>
                    <label>Nombre del acompañante <span class="ah-opcional">(opcional)</span> <input name="nombre_acompanante" <?= ValidadorCuenta::atributosHtml('nombre_completo') ?>><span data-error-campo="nombre_acompanante" aria-live="polite"></span></label>
                    <label>Teléfono del acompañante <span class="ah-opcional">(opcional)</span> <input name="telefono_acompanante" <?= ValidadorTelefono::atributosHtml() ?>><span data-error-campo="telefono_acompanante" aria-live="polite"></span></label>
                </fieldset>
                <label>Motivo clínico <input name="motivo" maxlength="255" data-caracteres="\P{C}" data-espacios="normalizar" required placeholder="Ej. dificultad respiratoria"><span data-error-campo="motivo" aria-live="polite"></span></label>
                <p data-aviso role="status"></p>
            </div>
            <footer class="zk-dialogo__pie">
                <button type="button" class="zk-btn zk-btn--sec" data-cerrar-dialogo>Cancelar</button>
                <button type="submit" class="zk-btn zk-btn--peligro">Registrar llegada roja</button>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>
    <template data-completar-ficha>
        <fieldset>
            <legend>Completar la misma ficha</legend>
            <label>Nombre <input name="nombre" <?= ValidadorMascota::atributosHtml('nombre') ?> required><span data-error-campo="nombre"></span></label>
            <label>Especie <select name="especie" required></select><span data-error-campo="especie"></span></label>
            <label>Raza <select name="raza" required></select><span data-error-campo="raza"></span></label>
            <label>Sexo <select name="sexo" required><option value="">Elige</option><option>Macho</option><option>Hembra</option></select><span data-error-campo="sexo"></span></label>
            <label>Peso (kg) <input name="peso" <?= ValidadorMascota::atributosHtml('peso') ?> required><span data-error-campo="peso"></span></label>
            <label>Nacimiento <span class="ah-opcional">(opcional)</span> <input name="fecha_nacimiento" type="date"><span data-error-campo="fecha_nacimiento"></span></label>
        </fieldset>
    </template>
</section>
<script src="js/urgencias-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
