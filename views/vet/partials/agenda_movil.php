<?php $dialogoSemana = 'agendaMesMovil'; ?>
<div class="agenda-movil" data-agenda-movil>
    <?php require __DIR__ . '/../../partials/inicio_semana.php'; ?>
    <div class="agenda-movil__controles">
        <button type="button" class="agenda-movil__hoy" data-agenda-hoy>Hoy</button>
        <button type="button" class="agenda-movil__filtros" data-abrir-dialogo="agendaFiltrosMovil" aria-label="Filtros de la agenda"><i class="bi bi-sliders" aria-hidden="true"></i><span data-agenda-filtros-activos hidden></span></button>
    </div>
    <div class="agenda-movil__dia"><strong data-agenda-dia-titulo></strong><span data-agenda-dia-resumen role="status"></span></div>
    <div class="agenda-movil__lista" data-agenda-dia-lista></div>
</div>
<dialog class="zk-dialogo agenda-hoja" id="agendaMesMovil" aria-labelledby="agendaMesTitulo">
    <button type="button" class="zk-hoja__asa" data-cerrar-dialogo aria-label="Cerrar calendario"></button>
    <div class="agenda-mes__cabecera"><button type="button" data-agenda-mes-paso="-1" aria-label="Mes anterior">‹</button><h2 id="agendaMesTitulo"></h2><button type="button" data-agenda-mes-paso="1" aria-label="Mes siguiente">›</button></div>
    <div class="agenda-mes__dias" data-agenda-mes-dias role="group" aria-label="Días del mes"></div>
    <p class="agenda-hoja__ayuda">Los puntos indican días con eventos.</p>
</dialog>
<dialog class="zk-dialogo agenda-hoja" id="agendaFiltrosMovil" aria-labelledby="agendaFiltrosTitulo">
    <button type="button" class="zk-hoja__asa" data-cerrar-dialogo aria-label="Cerrar filtros"></button>
    <h2 id="agendaFiltrosTitulo">Filtros</h2>
    <div data-agenda-filtros-destino></div>
    <button type="button" class="zk-btn" data-cerrar-dialogo>Ver agenda</button>
</dialog>
<dialog class="zk-dialogo agenda-hoja" id="agendaDetalleMovil" aria-label="Detalle de la cita">
    <button type="button" class="zk-hoja__asa" data-cerrar-dialogo aria-label="Cerrar detalle"></button>
    <div data-agenda-detalle-destino></div>
</dialog>
<dialog class="zk-dialogo agenda-hoja agenda-revision" id="agendaRevisionMovil" aria-labelledby="agendaRevisionTitulo">
    <button type="button" class="zk-hoja__asa" data-cerrar-dialogo aria-label="Cerrar revisión"></button>
    <h2 id="agendaRevisionTitulo">Revisar nivel</h2>
    <p data-agenda-revision-paciente></p>
    <div class="agenda-hoja__ayuda" data-agenda-revision-contexto></div>
    <div class="agenda-revision__niveles" data-agenda-revision-niveles role="group" aria-label="Nivel revisado"></div>
    <label class="agenda-revision__etiqueta" for="agendaRevisionMotivo">Motivo <span class="zk-requerido">*</span></label>
    <div data-agenda-revision-form></div>
</dialog>
