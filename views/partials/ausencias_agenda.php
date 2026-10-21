<?php require_once __DIR__ . '/../../helpers/ValidadorAgendaClinica.php'; ?>
<section class="coord-card" data-config-panel="ausencias" data-ausencias-agenda data-admin="<?= $esAdminHorario ? '1' : '0' ?>" data-clinica="<?= (int) Contexto::clinicaActiva() ?>" data-actor="<?= (int) Contexto::idUsuario() ?>" aria-labelledby="ausenciasTitulo">
    <header class="coord-card__head">
        <div>
            <h2 id="ausenciasTitulo">Ausencias y cobertura</h2>
            <p><?= $esAdminHorario ? 'Al registrarla se aplica de inmediato y sus citas pasan a otro veterinario o quedan para reprogramar.' : 'Solicita una ausencia; solo cuenta cuando la aprueba tu administrador.' ?></p>
        </div>
        <button type="button" class="coord-btn" data-abrir-dialogo="dialogoAusencia"><i class="fas fa-plus" aria-hidden="true"></i> <?= $esAdminHorario ? 'Registrar ausencia' : 'Solicitar ausencia' ?></button>
    </header>
    <div class="coord-lista" data-lista-ausencias aria-live="polite" data-vacio="No hay ausencias registradas."></div>
    <dialog class="zk-dialogo" id="dialogoAusencia" aria-labelledby="dialogoAusenciaTitulo">
        <form data-form-ausencia novalidate class="zk-dialogo__form">
            <header class="zk-dialogo__head">
                <h3 id="dialogoAusenciaTitulo"><?= $esAdminHorario ? 'Registrar ausencia' : 'Solicitar ausencia' ?></h3>
                <button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button>
            </header>
            <div class="zk-dialogo__cuerpo coord-dialogo-campos">
                <?php Csrf::field(); ?>
                <?php if ($esAdminHorario): ?>
                    <label class="coord-campo coord-campo--ancho">Veterinario <select name="id_veterinario" required></select></label>
                <?php else: ?>
                    <input type="hidden" name="id_veterinario" value="<?= (int) Contexto::idUsuario() ?>">
                <?php endif; ?>
                <label class="coord-campo">Desde <input type="datetime-local" name="inicio" step="1" required><span data-error-campo="inicio" class="coordinacion-error" aria-live="polite"></span></label>
                <label class="coord-campo">Hasta <input type="datetime-local" name="fin" step="1" required><span data-error-campo="fin" class="coordinacion-error" aria-live="polite"></span></label>
                <label class="coord-campo coord-campo--ancho">Motivo <span class="coord-opcional">(opcional)</span> <input name="motivo" maxlength="<?= ValidadorAgendaClinica::MOTIVO_MAX ?>" data-espacios="normalizar"><span data-error-campo="motivo" class="coordinacion-error" aria-live="polite"></span></label>
                <p class="coord-aviso coord-campo--ancho" data-aviso role="status"></p>
            </div>
            <footer class="zk-dialogo__pie">
                <button type="button" class="coord-btn coord-btn--sec" data-cerrar-dialogo>Cancelar</button>
                <button type="submit" class="coord-btn"><?= $esAdminHorario ? 'Registrar ausencia' : 'Solicitar ausencia' ?></button>
            </footer>
        </form>
    </dialog>
</section>
