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
        <div class="agenda-control-bar">
            <div class="control-left">
                <span class="filter-label">Mostrar:</span>
                <button class="filter-btn active" data-tipo="cita">Citas</button>
                <button class="filter-btn active" data-tipo="vacunacion">Vacunas</button>
                <button class="filter-btn active" data-tipo="desparasitacion">Desparasitaciones</button>
            </div>

            <div class="control-center">
                <span class="legend-item"><span class="dot pendiente"></span> Pendiente</span>
                <span class="legend-item"><span class="dot confirmada"></span> Confirmada</span>
                <span class="legend-item"><span class="dot en-curso"></span> En curso</span>
                <span class="legend-item"><span class="dot completada"></span> Completada</span>
                <span class="legend-item"><span class="dot cancelada"></span> Cancelada</span>
            </div>

            <div class="control-right">
                <span class="filter-label">Veterinario:</span>
                <div class="select-wrapper">
                    <select id="filterVeterinario" class="filter-vet-select">
                        <option value="">Todos</option>
                    </select>
                    <i class="fas fa-chevron-down select-arrow"></i>
                </div>
            </div>
        </div>

        <div class="calendar-layout">
            <div class="calendar-container is-month-view">
                <div id="calendar"></div>
            </div>

            <!-- Card fijo - Eventos del día -->
            <div class="day-events-card">
                <div class="day-events-header">
                    <h3 id="dayEventsTitle">Selecciona un día</h3>
                </div>
                <div id="dayEventsList" class="day-events-list">
                    <p class="day-events-empty">Haz clic en un día del calendario para ver sus eventos</p>
                </div>
                <div class="day-events-footer">
                    <button id="btnAgendarCita" class="btn-agendar-cita" onclick="abrirCitaModalDesdeCard()">
                        <i class="fas fa-plus"></i> Agendar cita
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
            <!-- Contenedor de mensajes de error -->
            <div id="modal_error_container" style="display:none; background:#FEF2F2; border:1px solid #FECACA; border-radius:8px; padding:0.75rem 1rem; margin-bottom:1rem;">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <i class="fas fa-exclamation-circle" style="color:#DC2626; font-size:0.9rem;"></i>
                    <span id="modal_error_message" style="color:#991B1B; font-size:0.85rem; font-weight:600;"></span>
                </div>
            </div>

            <form id="formCitaModal" onsubmit="crearCitaModal(event)">

                <!-- Fecha (readonly) -->
                <div class="cita-field">
                    <label class="cita-label" for="modal_fecha">Fecha</label>
                    <div class="input-wrapper">
                        <i class="far fa-calendar-alt field-icon"></i>
                        <input type="date" id="modal_fecha" name="fecha" class="cita-input" readonly required>
                    </div>
                </div>

                <!-- Fila: Veterinario + Mascota -->
                <div class="cita-modal-row">
                    <div class="cita-field">
                        <label class="cita-label" for="modal_veterinario">Veterinario</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user-md field-icon"></i>
                            <select id="modal_veterinario" name="doc_veterinario" class="cita-select" required onchange="cargarSlotsModal()">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                    </div>
                    <div class="cita-field">
                        <label class="cita-label" for="modal_mascota">Mascota</label>
                        <div class="input-wrapper">
                            <i class="fas fa-paw field-icon"></i>
                            <select id="modal_mascota" name="id_mascota" class="cita-select" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fila: Tipo de cita + Hora -->
                <div class="cita-modal-row">
                    <div class="cita-field">
                        <label class="cita-label" for="modal_tipo_cita">Tipo de Cita</label>
                        <div class="input-wrapper">
                            <i class="fas fa-tag field-icon"></i>
                            <select id="modal_tipo_cita" name="id_tipo_cita" class="cita-select" required onchange="onModalTipoCitaChange()">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="cita-duracion-badge" id="modal_duracion_badge">
                            <i class="fas fa-clock"></i>
                            Duración: <span id="modal_duracion_valor">--</span> min
                        </div>
                    </div>
                    <div class="cita-field">
                        <label class="cita-label" for="modal_hora">Hora</label>
                        <div class="input-wrapper">
                            <i class="far fa-clock field-icon"></i>
                            <input type="time" id="modal_hora" name="hora" class="cita-input" readonly
                                placeholder="Selecciona un horario disponible"
                                title="Selecciona un horario disponible de la lista">
                        </div>
                        <small style="font-size:0.7rem; color:#626F86; margin-top:0.2rem; display:flex; align-items:center; gap:0.3rem;">
                            <i class="fas fa-info-circle" style="color:#579DFF;"></i>
                            Selecciona un horario disponible haciendo clic en la lista de abajo
                        </small>
                    </div>
                </div>

                <!-- Motivo -->
                <div class="cita-field">
                    <label class="cita-label" for="modal_motivo">Motivo</label>
                    <div class="input-wrapper">
                        <i class="fas fa-comment-medical field-icon"></i>
                        <input type="text" id="modal_motivo" name="motivo" class="cita-input"
                            placeholder="Ej. Vacunación anual, control de peso...">
                    </div>
                </div>

                <!-- Horarios disponibles -->
                <div class="cita-field">
                    <label class="cita-label">
                        Horarios Disponibles
                    </label>
                    <button type="button" class="slots-btn" id="modal_btn_slots" onclick="cargarSlotsModal()" style="display: none;">
                        <i class="fas fa-search"></i> Ver horarios libres
                    </button>
                    <div class="slots-container" id="modal_slots_container"></div>
                </div>

                <!-- Campos ocultos -->
                <input type="hidden" id="modal_duracion_minutos" name="duracion_minutos">

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
