<?php require_once __DIR__ . '/../../helpers/ValidadorAgendaClinica.php'; ?>
<section data-ausencias-agenda data-admin="<?= $esAdminHorario ? '1' : '0' ?>" data-clinica="<?= (int) Contexto::clinicaActiva() ?>" data-actor="<?= (int) Contexto::idUsuario() ?>">
    <h3>Ausencias y cobertura</h3>
    <p><?= $esAdminHorario ? 'La ausencia se hace efectiva al guardar y busca cobertura sin aceptación del sustituto.' : 'Solicita una ausencia propia. Solo bloquea cuando la aprueba tu administrador.' ?></p>
    <form data-form-ausencia novalidate>
        <?php Csrf::field(); ?>
        <?php if ($esAdminHorario): ?>
            <label>Veterinario <select name="id_veterinario" required></select></label>
        <?php else: ?>
            <input type="hidden" name="id_veterinario" value="<?= (int) Contexto::idUsuario() ?>">
        <?php endif; ?>
        <label>Inicio <input type="datetime-local" name="inicio" step="1" required><span data-error-campo="inicio" class="coordinacion-error" aria-live="polite"></span></label>
        <label>Fin <input type="datetime-local" name="fin" step="1" required><span data-error-campo="fin" class="coordinacion-error" aria-live="polite"></span></label>
        <label>Motivo (opcional) <input name="motivo" maxlength="<?= ValidadorAgendaClinica::MOTIVO_MAX ?>" data-espacios="normalizar"><span data-error-campo="motivo" class="coordinacion-error" aria-live="polite"></span></label>
        <button type="submit"><?= $esAdminHorario ? 'Registrar ausencia' : 'Solicitar ausencia' ?></button>
        <p data-aviso role="status"></p>
    </form>
    <div data-lista-ausencias aria-live="polite"></div>
</section>
