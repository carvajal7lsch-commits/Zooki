<?php
// views/vet/calendario.php — Agenda del veterinario.
//
// Los estilos se cargan en el <head> del layout (calendario.css): enlazarlos
// aquí, en medio del body, pintaba la página sin estilos y luego con estilos.
// FullCalendar v6 inyecta los suyos desde el JS.
?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<div class="agenda">
    <div class="agenda-toolbar">
        <div class="agenda-toolbar__nav">
            <button type="button" class="cal-icon-btn" data-nav="prev" aria-label="Periodo anterior"><i class="fas fa-chevron-left"></i></button>
            <h2 class="agenda-toolbar__title" id="calCustomTitle">&nbsp;</h2>
            <button type="button" class="cal-icon-btn" data-nav="next" aria-label="Periodo siguiente"><i class="fas fa-chevron-right"></i></button>
            <button type="button" class="cal-btn cal-btn--ghost cal-btn--sm" data-nav="today">Hoy</button>
        </div>

        <div class="agenda-toolbar__views" role="group" aria-label="Vista del calendario">
            <button type="button" class="view-tab active" data-view="dayGridMonth">Mes</button>
            <button type="button" class="view-tab" data-view="timeGridWeek">Semana</button>
            <button type="button" class="view-tab" data-view="timeGridDay">Día</button>
        </div>

        <div class="agenda-toolbar__filters">
            <button type="button" class="filter-btn active" data-tipo="cita" aria-pressed="true"><span class="tipo-dot"></span>Citas</button>
            <button type="button" class="filter-btn active" data-tipo="vacunacion" aria-pressed="true"><span class="tipo-dot"></span>Vacunas</button>
            <button type="button" class="filter-btn active" data-tipo="desparasitacion" aria-pressed="true"><span class="tipo-dot"></span>Desparasitación</button>
            <!-- El veterinario solo ve su agenda: el JS oculta este filtro para él. -->
            <div class="select-wrapper" id="filterVeterinarioWrap">
                <select id="filterVeterinario" class="filter-vet-select" aria-label="Filtrar por veterinario">
                    <option value="">Todos los veterinarios</option>
                </select>
                <i class="fas fa-chevron-down select-arrow"></i>
            </div>
        </div>
    </div>

    <div class="agenda-layout">
        <section class="agenda-calendar" aria-label="Calendario">
            <div id="calendar"></div>
            <footer class="agenda-legend" aria-label="Leyenda">
                <span class="legend-day legend-day--today">Hoy</span>
                <span class="legend-day legend-day--past">Día pasado · solo consulta</span>
                <span class="legend-sep"></span>
                <span class="legend-estado" data-estado="pendiente">Pendiente</span>
                <span class="legend-estado" data-estado="confirmada">Confirmada</span>
                <span class="legend-estado" data-estado="en_curso">En curso</span>
                <span class="legend-estado" data-estado="completada">Completada</span>
                <span class="legend-estado" data-estado="cancelada">Cancelada</span>
                <span class="legend-estado" data-estado="no_asistio">No asistió</span>
                <span class="legend-estado" data-estado="sin_cerrar">Sin cerrar</span>
                <span class="legend-estado" data-estado="cerrada_sin_consulta">Cerrada sin consulta</span>
            </footer>
        </section>

        <aside class="agenda-panel" id="agendaPanel" aria-live="polite">
            <header class="agenda-panel__head" id="agendaPanelHead"></header>
            <div class="agenda-panel__body" id="agendaPanelBody">
                <div class="agenda-skeleton"><span></span><span></span><span></span></div>
            </div>
        </aside>
    </div>
</div>

<!-- ══ MODAL — Agendar cita ══ -->
<div id="citaModalOverlay" class="zk-overlay" onclick="if (event.target === this) closeCitaModal()">
    <div class="zk-modal" role="dialog" aria-modal="true" aria-labelledby="citaModalTitle">
        <header class="zk-modal__head">
            <div>
                <h3 class="zk-modal__title" id="citaModalTitle">Nueva cita</h3>
                <p class="zk-modal__subtitle" id="citaModalFechaLabel"></p>
            </div>
            <button type="button" class="cal-icon-btn" onclick="closeCitaModal()" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </header>

        <form id="formCitaModal" class="zk-modal__split" onsubmit="crearCitaModal(event)" novalidate autocomplete="off">
            <div class="zk-modal__col">
                <p class="zk-modal__section">Datos de la cita</p>
                <input type="hidden" id="modal_fecha">
                <input type="hidden" id="modal_veterinario_hidden">
                <input type="hidden" id="modal_mascota">
                <input type="hidden" id="modal_duracion_minutos">
                <input type="hidden" id="modal_hora">

                <div class="zk-field">
                    <label class="zk-label" for="cm_mascota_search">Mascota</label>
                    <div class="zk-search" id="cm_mascota_wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" id="cm_mascota_search" class="zk-input zk-input--icon"
                               placeholder="Busca por mascota o propietario" oninput="filtrarMascotas(this.value)">
                        <div class="zk-dropdown" id="cm_mascota_dropdown" role="listbox"></div>
                    </div>
                    <div class="zk-selected" id="cm_mascota_chip" hidden>
                        <i class="fas fa-paw"></i>
                        <span id="cm_mascota_chip_name"></span>
                        <button type="button" onclick="limpiarMascotaSeleccionada(true)">Cambiar</button>
                    </div>
                </div>

                <div class="zk-field" id="cm_vet_field" hidden>
                    <label class="zk-label" for="modal_veterinario">Veterinario</label>
                    <select id="modal_veterinario" class="zk-input" onchange="onModalVeterinarioChange()">
                        <option value="">Selecciona un veterinario</option>
                    </select>
                </div>

                <div class="zk-field">
                    <label class="zk-label" for="modal_tipo_cita">Tipo de cita</label>
                    <select id="modal_tipo_cita" class="zk-input" onchange="onModalTipoCitaChange()">
                        <option value="">Selecciona el tipo</option>
                    </select>
                </div>

                <div class="zk-field">
                    <label class="zk-label" for="modal_motivo">Motivo <span class="zk-optional">(opcional)</span></label>
                    <input type="text" id="modal_motivo" class="zk-input" maxlength="255" placeholder="Ej. control de peso">
                </div>
            </div>

            <div class="zk-modal__col zk-modal__col--slots">
                <p class="zk-modal__section">Horario disponible</p>
                <div class="slot-panel" id="modal_slots_container"></div>
            </div>
        </form>

        <footer class="zk-modal__foot">
            <div class="zk-error" id="modal_error_container" role="alert" hidden>
                <i class="fas fa-exclamation-circle"></i><span id="modal_error_message"></span>
            </div>
            <p class="zk-summary" id="cm_resumen"></p>
            <div class="zk-modal__actions">
                <button type="button" class="cal-btn cal-btn--ghost" onclick="closeCitaModal()">Cancelar</button>
                <button type="submit" form="formCitaModal" class="cal-btn cal-btn--primary" id="cm_submit" disabled>Agendar cita</button>
            </div>
        </footer>
    </div>
</div>

<!-- ══ MODAL — Reprogramar cita ══ -->
<div id="reprogramarModalOverlay" class="zk-overlay" onclick="if (event.target === this) cerrarModalReprogramar()">
    <div class="zk-modal" role="dialog" aria-modal="true" aria-labelledby="reprogTitle">
        <header class="zk-modal__head">
            <div>
                <h3 class="zk-modal__title" id="reprogTitle">Reprogramar cita</h3>
                <p class="zk-modal__subtitle" id="reprogSubtitle"></p>
            </div>
            <button type="button" class="cal-icon-btn" onclick="cerrarModalReprogramar()" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </header>

        <div class="zk-modal__split">
            <div class="zk-modal__col">
                <p class="zk-modal__section">Nueva fecha</p>
                <input type="hidden" id="reprogramar_hora">

                <div class="zk-field">
                    <label class="zk-label" for="reprogramar_fecha">Fecha</label>
                    <input type="date" id="reprogramar_fecha" class="zk-input" onchange="cargarSlotsReprogramar()">
                </div>

                <div class="zk-field">
                    <label class="zk-label" for="reprogramar_veterinario">Veterinario</label>
                    <select id="reprogramar_veterinario" class="zk-input" onchange="cargarSlotsReprogramar()">
                        <option value="">Selecciona un veterinario</option>
                    </select>
                </div>

                <div class="zk-field">
                    <label class="zk-label" for="reprogramar_tipo_cita">Tipo de cita</label>
                    <select id="reprogramar_tipo_cita" class="zk-input" onchange="cargarSlotsReprogramar()">
                        <option value="">Selecciona el tipo</option>
                    </select>
                </div>
            </div>

            <div class="zk-modal__col zk-modal__col--slots">
                <p class="zk-modal__section">Horario disponible</p>
                <div class="slot-panel" id="reprogramar_slots_container"></div>
            </div>
        </div>

        <footer class="zk-modal__foot">
            <div class="zk-error" id="reprog_error" role="alert" hidden>
                <i class="fas fa-exclamation-circle"></i><span id="reprog_error_message"></span>
            </div>
            <div class="zk-modal__actions">
                <button type="button" class="cal-btn cal-btn--ghost" onclick="cerrarModalReprogramar()">Cancelar</button>
                <button type="button" class="cal-btn cal-btn--primary" id="reprogramar_btn_confirmar" onclick="confirmarReprogramacion()" disabled>Confirmar reprogramación</button>
            </div>
        </footer>
    </div>
</div>

<script>
  const USER_ROL = <?= (int)($_SESSION['usuario_id_rol'] ?? 0) ?>;
  const USER_DOC = <?= json_encode($_SESSION['usuario_doc'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="js/calendario.js?v=7"></script>

<!-- MODAL NUEVA CONSULTA (necesario para Iniciar Atención desde el calendario) -->
<?php include __DIR__ . "/modal_consulta.php"; ?>
