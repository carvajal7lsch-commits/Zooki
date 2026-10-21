<?php
require_once __DIR__ . '/../../helpers/Csrf.php';
$esAdminHorario = Contexto::rolActivo() === Roles::ADMIN;
// Pulido de interfaz (2026-10-21): tarjetas separadas; en Configuración cada una pertenece a una pestaña
// (data-config-panel) y los formularios de cierre y ausencia abren en una ventana.
?>
<link rel="stylesheet" href="css/dialogos-zooki.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="css/coordinacion-horarios.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<div class="coordinacion" data-coordinacion-horarios data-admin="<?= $esAdminHorario ? '1' : '0' ?>" data-clinica="<?= (int) Contexto::clinicaActiva() ?>">
    <section class="coord-card" data-config-panel="horarios" aria-labelledby="coordVetTitulo">
        <header class="coord-card__head">
            <div>
                <h2 id="coordVetTitulo">Horario del veterinario</h2>
                <p><?= $esAdminHorario ? 'Franjas de trabajo de cada veterinario. Las citas que queden fuera se marcan para reajuste.' : 'Propón tus franjas; cambian cuando las aprueba tu administrador.' ?></p>
            </div>
        </header>
        <form data-horario-vet novalidate class="coord-horario">
            <?php Csrf::field(); ?>
            <label class="coord-campo coord-campo--vet">Veterinario <select name="id_veterinario" data-veterinario required></select></label>
            <div class="coord-franjas" data-franjas></div>
            <span data-error-campo="franjas" class="coordinacion-error" aria-live="polite"></span>
            <div class="coord-acciones">
                <button type="button" class="coord-btn coord-btn--sec" data-agregar-franja><i class="fas fa-plus" aria-hidden="true"></i> Agregar franja</button>
                <button type="submit" class="coord-btn"><?= $esAdminHorario ? 'Guardar horario' : 'Proponer horario' ?></button>
            </div>
            <p class="coord-aviso" data-aviso role="status"></p>
        </form>
        <div class="coord-sub">
            <h3>Propuestas</h3>
            <div class="coord-lista" data-propuestas data-vacio="No hay propuestas pendientes."></div>
        </div>
    </section>

    <section class="coord-card" id="config-ausencias" data-config-panel="ausencias" aria-labelledby="coordCierresTitulo">
        <header class="coord-card__head">
            <div>
                <h2 id="coordCierresTitulo">Cierres puntuales</h2>
                <p>Días u horas en que la clínica no atiende (festivos, mantenimiento).</p>
            </div>
            <?php if ($esAdminHorario): ?>
                <button type="button" class="coord-btn" data-abrir-dialogo="dialogoCierre"><i class="fas fa-plus" aria-hidden="true"></i> Nuevo cierre</button>
            <?php endif; ?>
        </header>
        <div class="coord-lista" data-cierres data-vacio="No hay cierres registrados."></div>
        <?php if ($esAdminHorario): ?>
            <dialog class="zk-dialogo" id="dialogoCierre" aria-labelledby="dialogoCierreTitulo">
                <form data-cierre novalidate class="zk-dialogo__form">
                    <header class="zk-dialogo__head">
                        <h3 id="dialogoCierreTitulo">Nuevo cierre puntual</h3>
                        <button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button>
                    </header>
                    <div class="zk-dialogo__cuerpo coord-dialogo-campos">
                        <?php Csrf::field(); ?>
                        <label class="coord-campo">Desde <input type="datetime-local" name="inicio" step="1" required><span data-error-campo="inicio" class="coordinacion-error"></span></label>
                        <label class="coord-campo">Hasta <input type="datetime-local" name="fin" step="1" required><span data-error-campo="fin" class="coordinacion-error"></span></label>
                        <label class="coord-campo coord-campo--ancho">Motivo <input name="motivo" maxlength="255" data-espacios="normalizar" placeholder="Ej. Festivo" required><span data-error-campo="motivo" class="coordinacion-error"></span></label>
                        <p class="coord-aviso coord-campo--ancho" data-aviso role="status"></p>
                    </div>
                    <footer class="zk-dialogo__pie">
                        <button type="button" class="coord-btn coord-btn--sec" data-cerrar-dialogo>Cancelar</button>
                        <button type="submit" class="coord-btn">Registrar cierre</button>
                    </footer>
                </form>
            </dialog>
        <?php endif; ?>
    </section>

    <?php require __DIR__ . '/ausencias_agenda.php'; ?>

    <section class="coord-card" data-config-panel="ausencias" aria-labelledby="coordAfectadasTitulo">
        <header class="coord-card__head">
            <div>
                <h2 id="coordAfectadasTitulo">Citas pendientes de reajuste</h2>
                <p>Citas que quedaron fuera de un horario o cierre; conservan su hora hasta que se reprogramen.</p>
            </div>
        </header>
        <div class="coord-lista" data-afectadas data-vacio="Ninguna cita necesita reajuste."></div>
    </section>
</div>
<?php if (!$esAdminHorario): ?>
    <script src="js/configuracion-clinica.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="js/dialogos-zooki.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>
<script src="js/coordinacion-horarios.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script src="js/ausencias-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
