<?php if (Contexto::rolActivo() === Roles::VETERINARIO): ?>
<section id="agenda-panel-llegadas" class="agenda-herramientas__panel" role="tabpanel" aria-labelledby="agenda-tab-llegadas" hidden data-llegadas-agenda data-clinica="<?= (int) Contexto::clinicaActiva() ?>" data-veterinario="<?= (int) Contexto::idUsuario() ?>">
    <h3>Llegadas y decisiones de atención</h3>
    <p>Calcula el triage antes de registrar una llegada sin reserva. Rojo se recibe por Urgencias. La llegada no inicia atención.</p>
    <div data-llegadas-csrf hidden><?php Csrf::field(); ?></div>
    <button type="button" class="btn-secondary" data-llegadas-actualizar>Actualizar llegadas</button>
    <p role="status" data-llegadas-aviso></p>
    <div data-llegadas-citas></div>
    <h4>Llegadas sin reserva</h4>
    <div data-llegadas-solicitudes></div>
    <h4>Espera presencial</h4>
    <p>Amarillo antes de verde; después, llegada física y orden de registro. Sin hueco no se inicia consulta.</p>
    <div data-llegadas-espera></div>
    <h4>Historial de reclasificaciones de esta clínica</h4>
    <div data-llegadas-historial></div>
</section>
<script src="js/llegadas-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>
