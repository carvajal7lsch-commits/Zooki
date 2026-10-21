<?php
require_once __DIR__ . '/../../helpers/Csrf.php';
$esAdminHorario = Contexto::rolActivo() === Roles::ADMIN;
?>
<link rel="stylesheet" href="css/coordinacion-horarios.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<section class="horarios-coordinados horarios-card perfil-card" data-coordinacion-horarios data-admin="<?= $esAdminHorario ? '1' : '0' ?>" data-clinica="<?= (int) Contexto::clinicaActiva() ?>">
    <h2>Horario del veterinario</h2>
    <p><?= $esAdminHorario ? 'Define las franjas y revisa las propuestas de tu clínica.' : 'Propón tus franjas. El horario cambia cuando lo aprueba tu administrador.' ?></p>
    <p>Las citas que queden fuera se muestran pendientes de reajuste; sus horas se conservan.</p>
    <form data-horario-vet novalidate>
        <?php Csrf::field(); ?>
        <label>Veterinario <select name="id_veterinario" data-veterinario required></select></label>
        <div data-franjas></div>
        <span data-error-campo="franjas" class="coordinacion-error" aria-live="polite"></span>
        <button type="button" data-agregar-franja>Agregar franja</button>
        <button type="submit"><?= $esAdminHorario ? 'Guardar horario' : 'Proponer horario' ?></button>
        <p data-aviso role="status"></p>
    </form>
    <h3>Propuestas</h3>
    <div data-propuestas></div>
    <?php if ($esAdminHorario): ?>
        <h3>Cierre puntual de la clínica</h3>
        <form data-cierre novalidate>
            <?php Csrf::field(); ?>
            <label>Inicio <input type="datetime-local" name="inicio" step="1" required><span data-error-campo="inicio" class="coordinacion-error"></span></label>
            <label>Fin <input type="datetime-local" name="fin" step="1" required><span data-error-campo="fin" class="coordinacion-error"></span></label>
            <label>Motivo <input name="motivo" maxlength="255" data-espacios="normalizar" required><span data-error-campo="motivo" class="coordinacion-error"></span></label>
            <button type="submit">Registrar cierre</button>
            <p data-aviso role="status"></p>
        </form>
    <?php endif; ?>
    <h3>Cierres puntuales</h3>
    <div data-cierres></div>
    <?php require __DIR__ . '/ausencias_agenda.php'; ?>
    <h3>Citas pendientes de reajuste</h3>
    <div data-afectadas></div>
</section>
<?php if (!$esAdminHorario): ?>
    <script src="js/configuracion-clinica.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>
<script src="js/coordinacion-horarios.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>

<script src="js/ausencias-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
