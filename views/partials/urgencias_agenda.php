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
            <button type="button" class="zk-btn zk-btn--peligro" data-abrir-dialogo="dialogoRojo"><i class="fas fa-plus" aria-hidden="true"></i> Urgencia</button>
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
            <input type="hidden" name="id_solicitud" value="">
            <input type="hidden" name="identidad" value="provisional">
            <input type="hidden" name="motivo" value="Ingreso de urgencia presencial">
            <input type="hidden" name="id_tipo_cita" value="" data-ingreso-tipo>
            <header class="zk-dialogo__head">
                <h3 id="dialogoRojoTitulo">Nueva urgencia</h3>
                <button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button>
            </header>
            <div class="zk-dialogo__cuerpo">
                <p data-evaluacion-conservada hidden></p>
                <div class="urgencia-rapida__opciones" role="group" aria-label="Identificación del paciente">
                    <button type="button" data-urgencia-identidad="provisional" aria-pressed="true">Identificar después</button>
                    <button type="button" data-urgencia-identidad="conocida" aria-pressed="false">Buscar paciente</button>
                </div>
                <p class="urgencia-rapida__ayuda" data-identidad-provisional>La identificación se completa después. Puedes comenzar la atención ahora.</p>
                <div class="urgencia-rapida__paciente" data-identidad-conocida hidden>
                    <label>Buscar por nombre <input type="search" data-urgencia-buscar autocomplete="off" placeholder="Nombre del paciente"></label>
                    <label>Paciente <select name="id_mascota" required disabled><option value="">Elige el paciente</option></select><span data-error-campo="id_mascota" aria-live="polite"></span></label>
                </div>
                <label data-ingreso-decision hidden>Decisión clínica <input name="decision" maxlength="255" data-caracteres="\P{C}" data-espacios="normalizar" disabled><span data-error-campo="decision" aria-live="polite"></span></label>
                <p class="ah-error" data-ingreso-configuracion role="status"></p>
                <p class="ah-error" data-aviso role="status"></p>
                <button type="button" class="zk-btn zk-btn--sec zk-btn--mini" data-ingreso-ir-urgencias hidden>Ir a Urgencias</button>
            </div>
            <footer class="zk-dialogo__pie">
                <button type="button" class="zk-btn zk-btn--sec" data-ingreso-solo-llegada>Solo registrar llegada</button>
                <button type="submit" class="zk-btn zk-btn--peligro" disabled>Atender ahora</button>
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
<script src="js/urgencias-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>&amp;r=<?= substr(hash_file('sha256', __DIR__ . '/../../public/js/urgencias-agenda.js'), 0, 12) ?>" defer></script>
