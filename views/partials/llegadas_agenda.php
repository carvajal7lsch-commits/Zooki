<?php if (Contexto::rolActivo() === Roles::VETERINARIO): ?>
<section id="agenda-panel-llegadas" class="agenda-herramientas__panel" role="tabpanel" aria-labelledby="agenda-tab-llegadas" hidden data-llegadas-agenda data-clinica="<?= (int) Contexto::clinicaActiva() ?>" data-veterinario="<?= (int) Contexto::idUsuario() ?>">
    <header class="ah-head">
        <div>
            <h3>Llegadas de hoy</h3>
            <p>Registrar la llegada no inicia la atención. Un rojo se recibe por Urgencias.</p>
        </div>
        <div class="ah-acciones">
            <button type="button" class="zk-icono" data-llegadas-actualizar title="Actualizar" aria-label="Actualizar llegadas"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>
        </div>
    </header>
    <div data-llegadas-csrf hidden><?php Csrf::field(); ?></div>
    <p class="ah-estado" role="status" data-llegadas-aviso></p>
    <div class="ah-grupo">
        <h4 class="ah-sub">Citas de hoy</h4>
        <div class="ah-lista" data-llegadas-citas data-vacio="No tienes citas pendientes hoy."></div>
    </div>
    <div class="ah-grupo">
        <h4 class="ah-sub">Llegadas sin reserva</h4>
        <div class="ah-lista" data-llegadas-solicitudes data-vacio="No hay casos calculados esperando llegada."></div>
    </div>
    <div class="ah-grupo">
        <h4 class="ah-sub">Espera presencial <span class="ah-ayuda">Amarillo antes que verde; luego, orden de llegada.</span></h4>
        <div class="ah-lista" data-llegadas-espera data-vacio="Nadie en espera."></div>
    </div>
    <details class="ah-plegable">
        <summary>Historial de reclasificaciones</summary>
        <div class="ah-lista" data-llegadas-historial data-vacio="Sin reclasificaciones."></div>
    </details>
</section>
<script src="js/llegadas-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>
