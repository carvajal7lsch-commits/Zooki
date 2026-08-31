<?php
// views/citas/calendario.php
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
<link rel="stylesheet" href="css/calendar-modules/sidebar-cards.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/calendar-modules/drawers-modals.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/calendar-modules/mockup-redesign.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/calendario.css?v=<?= time() ?>">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<div class="animate__animated animate__fadeIn">
    <div class="calendar-page-wrapper">
        <div class="calendar-layout">
            <!-- Columna Izquierda: Controles y Calendario -->
            <div class="calendar-left-column">
                <div class="agenda-control-bar">
                    <!-- Izquierda: tabs vista -->
                    <div class="control-left">
                        <div class="view-tab-group">
                            <button class="view-tab active" data-view="dayGridMonth">Mes</button>
                            <button class="view-tab" data-view="timeGridWeek">Semana</button>
                            <button class="view-tab" data-view="timeGridDay">D&#237;a</button>
                        </div>
                    </div>

                    <!-- Centro: Filtros -->
                    <div class="control-center">
                        <span class="filter-label" style="margin-left: 10px; margin-right: 5px;">MOSTRAR:</span>
                        <label class="filter-check-group" style="border:none; background:transparent; padding:0; gap:6px; font-weight:500;">
                            <input type="checkbox" class="check-citas" checked
                                   onchange="document.querySelector('.filter-btn[data-tipo=cita]').click()">
                            Citas
                        </label>
                        <label class="filter-check-group" style="border:none; background:transparent; padding:0; gap:6px; font-weight:500;">
                            <input type="checkbox" class="check-vacunas" checked
                                   onchange="document.querySelector('.filter-btn[data-tipo=vacunacion]').click()">
                            Vacunas
                        </label>
                        <label class="filter-check-group" style="border:none; background:transparent; padding:0; gap:6px; font-weight:500; margin-right:15px;">
                            <input type="checkbox" class="check-desparasitacion" checked
                                   onchange="document.querySelector('.filter-btn[data-tipo=desparasitacion]').click()">
                            Desparasitaci&#243;n
                        </label>

                        <!-- Botones ocultos -->
                        <button class="filter-btn active" data-tipo="cita" style="display:none">Citas</button>
                        <button class="filter-btn active" data-tipo="vacunacion" style="display:none">Vacunas</button>
                        <button class="filter-btn active" data-tipo="desparasitacion" style="display:none">Desparasitaciones</button>

                        <!-- Selector veterinario -->
                        <div class="select-wrapper">
                            <select id="filterVeterinario" class="filter-vet-select" style="background:#f1f5f9; border:none; padding-left:15px; border-radius:20px;">
                                <option value="">Todos los Veterinarios</option>
                            </select>
                            <i class="fas fa-chevron-down select-arrow" style="right:12px; color:#475569;"></i>
                        </div>
                    </div>

                    <!-- Derecha: Navegación de mes -->
                    <div class="control-right custom-month-nav">
                        <button class="btn-cal-nav prev-month" onclick="if(calendarInstance) calendarInstance.prev()">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="calCustomTitle" class="cal-custom-title">Octubre 2023</span>
                        <button class="btn-cal-nav next-month" onclick="if(calendarInstance) calendarInstance.next()">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="calendar-container is-month-view">
                    <div id="calendar"></div>
                </div>
            </div>

            <!-- Card fijo - Eventos del día -->
            <div class="day-events-card">
                <div class="day-events-header">
                    <h3 id="dayEventsTitle">Detalle del Día</h3>
                    <p class="day-events-date-sub" id="dayEventsDateSub">Selecciona un día</p>
                </div>
                <div id="dayEventsList" class="day-events-list">
                    <p class="day-events-empty">Haz clic en un día del calendario para ver sus eventos</p>
                </div>
                <div class="day-events-footer mockup-footer">
                    <button id="btnAgendarCita" class="btn-ver-notas-text" style="display:flex; justify-content:center; align-items:center; gap:8px; color:#1a56db;" onclick="abrirCitaModalDesdeCard()">
                        <i class="fas fa-plus"></i> Agendar cita
                    </button>
                    <button class="btn-print-icon" title="Imprimir">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL — Agendar Cita (Cell Click)
     ══════════════════════════════════════════════ -->
<div id="citaModalOverlay" class="users-modal cita-modal-overlay" onclick="handleModalOverlayClick(event)">
    <div class="cita-modal" role="dialog" aria-modal="true" aria-labelledby="citaModalTitle">

        <!-- Header -->
        <div class="cita-modal-header">
            <div class="cita-modal-header-left">
                <div class="cita-modal-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div>
                    <h3 class="cita-modal-title" id="citaModalTitle">Nueva Cita</h3>
                    <p class="cita-modal-subtitle" id="citaModalFechaLabel">Selecciona los datos de la cita</p>
                </div>
            </div>
            <button class="cita-modal-close" onclick="closeCitaModal()" aria-label="Cerrar modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="cita-modal-body">
            <!-- Error container -->
            <div id="modal_error_container" style="display:none; background:#FEF2F2; border:1px solid #FECACA; border-radius:8px; padding:0.65rem 1rem; margin-bottom:0.75rem;">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <i class="fas fa-exclamation-circle" style="color:#DC2626; font-size:0.85rem;"></i>
                    <span id="modal_error_message" style="color:#991B1B; font-size:0.82rem; font-weight:600;"></span>
                </div>
            </div>

            <form id="formCitaModal" onsubmit="crearCitaModal(event)">

                <!-- Fila info compacta: Fecha + Veterinario -->
                <div class="cm-info-row">
                    <!-- Chip de Fecha -->
                    <div class="cm-chip" style="display: none;">
                        <i class="far fa-calendar-alt cm-chip-icon"></i>
                        <div class="cm-chip-text">
                            <span class="cm-chip-label">Fecha</span>
                            <span class="cm-chip-value" id="cm_fecha_display">—</span>
                        </div>
                        <input type="hidden" id="modal_fecha" name="fecha">
                    </div>

                    <!-- Chip de Veterinario (vet) o select pequeño (recepcionista) -->
                    <div class="cm-chip" id="cm_vet_chip" style="display:none;">
                        <i class="fas fa-user-md cm-chip-icon"></i>
                        <div class="cm-chip-text">
                            <span class="cm-chip-label">Veterinario</span>
                            <span class="cm-chip-value" id="cm_vet_display">—</span>
                        </div>
                        <input type="hidden" id="modal_veterinario_hidden" name="doc_veterinario">
                    </div>
                    <div class="cm-vet-select-wrap" id="cm_vet_select_wrap" style="display:none;">
                        <label class="cm-mini-label"><i class="fas fa-user-md"></i> Veterinario</label>
                        <select id="modal_veterinario" name="doc_veterinario" class="cm-select-compact" onchange="cargarSlotsModal()">
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                </div>

                <!-- Búsqueda de Mascota -->
                <div class="cm-field">
                    <label class="cm-label">Mascota</label>
                    <div class="input-wrapper" id="cm_mascota_wrap">
                        <i class="fas fa-search field-icon"></i>
                        <input type="text" id="cm_mascota_search" class="cm-search-input"
                               placeholder="Buscar por nombre de mascota..."
                               autocomplete="off"
                               oninput="filtrarMascotas(this.value)">
                        <div class="cm-search-dropdown" id="cm_mascota_dropdown"></div>
                    </div>
                    <div class="cm-selected-chip" id="cm_mascota_chip" style="display:none;">
                        <i class="fas fa-paw"></i>
                        <span id="cm_mascota_chip_name"></span>
                        <button type="button" class="cm-chip-remove" onclick="limpiarMascotaSeleccionada()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <input type="hidden" id="modal_mascota" name="id_mascota">
                </div>

                <!-- Tipo de Cita — cards -->
                <div class="cm-field">
                    <label class="cm-label">Tipo de Cita</label>
                    <div class="cm-tipo-grid" id="cm_tipo_grid">
                        <p class="cm-loading-text"><i class="fas fa-spinner fa-spin"></i> Cargando...</p>
                    </div>
                    <input type="hidden" id="modal_tipo_cita" name="id_tipo_cita">
                    <input type="hidden" id="modal_duracion_minutos" name="duracion_minutos">
                    <div class="cm-duracion-chip" id="modal_duracion_badge" style="display:none;">
                        <i class="far fa-clock"></i> <span id="modal_duracion_valor">--</span> min de duración
                    </div>
                </div>

                <!-- Motivo -->
                <div class="cm-field">
                    <label class="cm-label">Motivo <span style="color:#94a3b8;font-weight:400;">(opcional)</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-comment-medical field-icon"></i>
                        <input type="text" id="modal_motivo" name="motivo" class="cm-input"
                            placeholder="Ej. Vacunación anual, control de peso...">
                    </div>
                </div>

                <!-- Horario seleccionado (chip, oculto hasta seleccionar) -->
                <div class="cm-hora-chip-wrap" id="cm_hora_chip_wrap" style="display:none;">
                    <i class="far fa-clock" style="color:#1a56db;"></i>
                    <span>Horario seleccionado: <strong id="cm_hora_chip_text">—</strong></span>
                    <button type="button" class="cm-chip-remove" onclick="limpiarHoraSeleccionada()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <input type="hidden" id="modal_hora" name="hora">

                <!-- Horarios disponibles -->
                <div class="cm-field">
                    <label class="cm-label">Horarios Disponibles</label>
                    <button type="button" class="slots-btn" id="modal_btn_slots" onclick="cargarSlotsModal()" style="display: none;">
                        <i class="fas fa-search"></i> Ver horarios libres
                    </button>
                    <div class="slots-container" id="modal_slots_container"></div>
                </div>

            </form>
        </div>

        <!-- Footer -->
        <div class="cita-modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeCitaModal()">
                Cancelar
            </button>
            <button type="submit" form="formCitaModal" class="btn-modal-submit">
                <i class="fas fa-check"></i> Agendar Cita
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     PANEL LATERAL — Crear Cita (Drawer)
     ══════════════════════════════════════════════ -->
<div id="citaDrawerOverlay" class="drawer-overlay" onclick="closeCitaDrawer()"></div>

<div id="citaDrawer" class="drawer-panel">
    <!-- Header del Drawer -->
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div class="drawer-icon-box">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <div>
                <h3 class="drawer-title">Nueva Cita</h3>
                <p id="drawerFechaLabel" class="drawer-subtitle">Programar una nueva cita</p>
            </div>
        </div>
        <button onclick="closeCitaDrawer()" class="drawer-close-btn">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Cuerpo scrolleable del Drawer -->
    <div class="drawer-body">
        <form id="formCrearCitaDrawer" onsubmit="crearCita(event)">
            <!-- Fecha -->
            <div class="drawer-field">
                <label class="drawer-label">
                    <i class="far fa-calendar-alt"></i>Fecha
                </label>
                <input type="date" name="fecha" id="crear_fecha" required readonly class="drawer-input-readonly">
            </div>

            <!-- Hora -->
            <div class="drawer-field">
                <label class="drawer-label">
                    <i class="far fa-clock"></i>Hora
                </label>
                <input type="time" name="hora" id="crear_hora" readonly class="drawer-input-time">
                <small class="drawer-info-helper">
                    <i class="fas fa-info-circle"></i>
                    Selecciona un horario disponible en la sección de abajo
                </small>
            </div>

            <!-- Campos ocultos para enviar los datos de los filtros -->
            <input type="hidden" name="doc_veterinario" id="crear_veterinario">
            <input type="hidden" name="id_mascota" id="crear_mascota">
            <input type="hidden" name="id_tipo_cita" id="crear_tipo_cita">
            <input type="hidden" name="duracion_minutos" id="crear_duracion_minutos">
            <input type="hidden" name="motivo" id="crear_motivo">

            <!-- Horarios Disponibles -->
            <div class="drawer-field">
                <label class="drawer-label">
                    <i class="fas fa-lightbulb" style="color:#F59E0B;"></i>Horarios Disponibles
                </label>
                
                <button type="button" onclick="cargarSugerenciasHorario()" class="drawer-btn-search">
                    <i class="fas fa-search"></i> Ver horarios disponibles
                </button>
                <div id="sugerencias_horario" class="drawer-sugerencias-box">
                    <div id="sugerencias_container" class="drawer-sugerencias-flex"></div>
                </div>
            </div>

            <!-- Botón Crear -->
            <button type="submit" class="drawer-btn-submit">
                <i class="fas fa-check"></i> Crear Cita
            </button>
        </form>
    </div>
</div>


<!-- ══════════════════════════════════════════════
     PANEL LATERAL — Detalle de Cita (Drawer)
     ══════════════════════════════════════════════ -->
<div id="detalleCitaDrawerOverlay" class="drawer-overlay" onclick="closeDetalleCitaDrawer()"></div>

<div id="detalleCitaDrawer" class="drawer-panel">
    <!-- Header del Drawer -->
    <div class="drawer-header">
        <div class="drawer-header-left">
            <div class="drawer-icon-box">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div>
                <h3 class="drawer-title">Detalle de Cita</h3>
                <p class="drawer-subtitle">Información de la cita programada</p>
            </div>
        </div>
        <button onclick="closeDetalleCitaDrawer()" class="drawer-close-btn">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Cuerpo scrolleable -->
    <div id="detalleCitaBody" class="drawer-body"></div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL — Reprogramar Cita
     Requirements: 2.1–2.11
     ══════════════════════════════════════════════ -->
<div id="reprogramarModalOverlay" class="modal-reprog-overlay" onclick="if(event.target===this)cerrarModalReprogramar()">
    <div class="modal-reprog-container" onclick="event.stopPropagation()">

        <!-- Header -->
        <div class="modal-reprog-header">
            <div class="modal-reprog-header-left">
                <div class="modal-reprog-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <h3 class="modal-reprog-title">Reprogramar Cita</h3>
                    <p class="modal-reprog-subtitle">Selecciona nueva fecha, hora y veterinario</p>
                </div>
            </div>
            <button onclick="cerrarModalReprogramar()" class="modal-reprog-close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="modal-reprog-body">

            <!-- Error container -->
            <div id="reprog_error" class="modal-reprog-error"></div>

            <!-- Veterinario -->
            <div>
                <label class="modal-reprog-label">Veterinario</label>
                <div class="input-wrapper">
                    <i class="fas fa-user-md field-icon"></i>
                    <select id="reprogramar_veterinario" class="modal-reprog-select">
                        <option value="">Seleccione veterinario...</option>
                    </select>
                </div>
            </div>

            <!-- Fecha -->
            <div>
                <label class="modal-reprog-label">Nueva Fecha</label>
                <div class="input-wrapper">
                    <i class="far fa-calendar-alt field-icon"></i>
                    <input type="date" id="reprogramar_fecha" class="modal-reprog-input">
                </div>
            </div>

            <!-- Tipo de cita -->
            <div>
                <label class="modal-reprog-label">Tipo de Cita</label>
                <div class="input-wrapper">
                    <i class="fas fa-tag field-icon"></i>
                    <select id="reprogramar_tipo_cita" onchange="actualizarDuracionReprogramar()" class="modal-reprog-select">
                        <option value="">Seleccione tipo...</option>
                    </select>
                </div>
                <div id="reprogramar_duracion_badge" class="modal-reprog-duracion">
                    <i class="fas fa-clock"></i> Duración estimada:
                    <span id="reprogramar_duracion_valor" class="modal-reprog-duracion-val">—</span> min
                </div>
            </div>

            <!-- Hora oculta -->
            <input type="hidden" id="reprogramar_hora">

            <!-- Slots -->
            <div>
                <button id="reprogramar_btn_slots" type="button" onclick="cargarSlotsReprogramar()" class="modal-reprog-btn-slots">
                    <i class="fas fa-search"></i> Ver horarios disponibles
                </button>
                <div id="reprogramar_slots_container" class="modal-reprog-slots-container"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="modal-reprog-footer">
            <button type="button" onclick="cerrarModalReprogramar()" class="modal-reprog-btn-cancel">
                Cancelar
            </button>
            <button id="reprogramar_btn_confirmar" type="button" onclick="confirmarReprogramacion()" class="modal-reprog-btn-confirm">
                <i class="fas fa-check"></i> Confirmar reprogramación
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     EVENT POPOVER
     Requirements: 4.1–4.7
     ══════════════════════════════════════════════ -->
<div id="eventPopover" class="event-popover-custom" role="tooltip" aria-live="polite">
    <div class="popover-custom-header">
        <span class="popover-custom-tipo"></span>
        <button class="popover-custom-close" onclick="cerrarPopover()" aria-label="Cerrar">×</button>
    </div>
    <div class="popover-custom-body">
        <div class="popover-custom-row">
            <i class="fas fa-paw"></i>
            <span id="pop_mascota"></span>
        </div>
        <div class="popover-custom-row">
            <i class="fas fa-user"></i>
            <span id="pop_propietario"></span>
        </div>
        <div class="popover-custom-row popover-vet-row">
            <i class="fas fa-user-md"></i>
            <span id="pop_veterinario"></span>
        </div>
        <div class="popover-custom-row popover-estado-row">
            <i class="fas fa-circle"></i>
            <span id="pop_estado_badge" class="popover-custom-estado-badge"></span>
        </div>
        <div class="popover-custom-row">
            <i class="fas fa-comment-medical"></i>
            <span id="pop_motivo"></span>
        </div>
        <div class="popover-custom-row">
            <i class="far fa-calendar-alt"></i>
            <span id="pop_fecha"></span>
        </div>
    </div>
    <div class="popover-custom-footer">
        <button id="pop_btn_detalle" class="popover-custom-btn-detail">
            <i class="fas fa-external-link-alt"></i> Ver detalle completo
        </button>
    </div>
</div>

<script>
  const USER_ROL = <?= (int)($_SESSION['usuario_id_rol'] ?? 0) ?>;
  const USER_DOC = <?= json_encode($_SESSION['usuario_doc'] ?? '') ?>;
</script>
<script src="js/calendario.js?v=<?= time() ?>"></script>

<script>
// ── View Tabs (Mes / Semana / Día) ──
document.querySelectorAll('.view-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.view-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        if (typeof calendarInstance !== 'undefined' && calendarInstance) {
            calendarInstance.changeView(this.dataset.view);
        }
    });
});
</script>

<!-- MODAL NUEVA CONSULTA (necesario para Iniciar Atención desde el calendario) -->
<?php include __DIR__ . "/modal_consulta.php"; ?>
