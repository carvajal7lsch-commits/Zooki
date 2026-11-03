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
        </div>
        <div class="ah-acciones">
            <?php if ($esVetUrgencia): ?>
            <button type="button" class="zk-btn zk-btn--peligro" data-abrir-dialogo="dialogoRojo"><i class="fas fa-plus" aria-hidden="true"></i> Urgencia</button>
            <?php endif; ?>
        </div>
    </header>
    <div data-urgencias-csrf hidden><?php Csrf::field(); ?></div>
    <p class="ah-estado" data-urgencias-aviso role="status"></p>
    <div class="ah-grupo">
        <h4 class="ah-sub">Rojos activos</h4>
        <div class="ah-lista" data-rojos-lista data-vacio="No hay urgencias activas."></div>
        <p class="ah-tarjetas__nota">El aviso remoto no acredita la llegada. La prioridad la decide el veterinario.</p>
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
            <input type="hidden" name="decision" value="">
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
                <p class="urgencia-rapida__ayuda" data-identidad-provisional>Puedes completar la identificación del paciente después.</p>
                <div class="urgencia-rapida__paciente" data-identidad-conocida hidden>
                    <input type="hidden" name="id_mascota" value="" disabled>
                    <label for="urgenciaBuscarPaciente">Buscar paciente</label>
                    <input id="urgenciaBuscarPaciente" type="search" data-urgencia-buscar autocomplete="off" placeholder="Mascota, propietario o cédula" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="urgenciaPacientes" aria-describedby="urgenciaPacienteError">
                    <div id="urgenciaPacientes" class="urgencia-rapida__resultados" role="listbox" aria-label="Pacientes encontrados" data-urgencia-resultados hidden></div>
                    <span id="urgenciaPacienteError" data-error-campo="id_mascota" aria-live="polite"></span>
                </div>
                <div class="urgencia-rapida__excepcion" data-ingreso-excepcion hidden>
                    <p class="urgencia-rapida__ayuda" data-ingreso-excepcion-titulo></p>
                    <label>Detalle adicional <span class="ah-opcional">(opcional)</span> <input name="detalle_urgencia" maxlength="200" data-caracteres="\P{C}" data-espacios="normalizar" disabled></label>
                    <div class="urgencia-rapida__motivos" role="group" aria-label="Motivo clínico">
                        <button type="button" data-urgencia-motivo>Este paciente es más grave</button>
                        <button type="button" data-urgencia-motivo>El otro paciente está estable</button>
                        <button type="button" data-urgencia-motivo>Orden del veterinario</button>
                    </div>
                </div>
                <p class="ah-error" data-aviso role="status"></p>
            </div>
            <footer class="zk-dialogo__pie">
                <button type="button" class="zk-btn zk-btn--sec" data-ingreso-solo-llegada>Solo registrar llegada</button>
                <button type="submit" class="zk-btn zk-btn--peligro">Atender ahora</button>
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
