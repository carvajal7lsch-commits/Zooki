<?php
// HU-2.2 / RN-201: la atención sin cita es del veterinario; las citas se
// atienden desde el calendario. El listado trae solo las consultas de la
// clínica activa (RN-112); el script lee los datos de data-consultas.
$puedeAtenderSinCita = Contexto::rolActivo() === Roles::VETERINARIO;
$consultasJson = json_encode($consultas, JSON_UNESCAPED_UNICODE);
?>
<div class="consultations-dashboard animate__animated animate__fadeIn" id="consultasApp"
     data-consultas="<?= htmlspecialchars($consultasJson, ENT_QUOTES, 'UTF-8') ?>">
    <div class="header-container-white">
        <div class="head-title-desc">
            <h1 class="users-page-title">Consultas Médicas</h1>
            <p class="users-module-desc">Registro e historial clínico de pacientes</p>
        </div>

        <div class="mini-kpis-container">
            <div class="mini-kpi">
                <svg id="kpi-total-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-total">0</span>
                    <span class="mini-kpi-label">Total Atenciones</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-caninos-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-caninos">0</span>
                    <span class="mini-kpi-label">Caninos</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-felinos-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-felinos">0</span>
                    <span class="mini-kpi-label">Felinos</span>
                </div>
            </div>
        </div>

        <div class="header-actions">
            <?php if ($puedeAtenderSinCita): ?>
            <button type="button" class="btn-primary" id="btnAtencionSinCita" title="Para urgencias o pacientes que llegan sin cita. Las citas se atienden desde el calendario.">
                <i class="fas fa-ambulance"></i> Atención sin cita
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="users-controls-bar__filters is-active consultas-filtros">
        <div class="search-input consultas-busqueda">
            <i class="fas fa-search"></i>
            <input type="text" id="consultationSearch" placeholder="Buscar por paciente, propietario o diagnóstico...">
        </div>
        <div class="consultas-filtros-grupo">
            <select class="filter-select" id="filtroEspecie">
                <option value="">Todas las especies</option>
                <option value="canino">Caninos</option>
                <option value="felino">Felinos</option>
                <option value="ave">Aves</option>
                <option value="roedor">Roedores</option>
            </select>

            <select class="filter-select" id="filtroFecha">
                <option value="">Todas las fechas</option>
                <option value="hoy">Hoy</option>
                <option value="ayer">Ayer</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mes</option>
                <option value="custom">Personalizada...</option>
            </select>
            <input type="date" id="filtroFechaCustom" class="filter-select consultas-fecha-personalizada" hidden>

            <button type="button" class="btn-clean consultas-limpiar" id="btnLimpiarFiltros">
                <i class="fas fa-eraser"></i> Limpiar
            </button>
        </div>
    </div>

    <div class="consultations-grid" id="consultationsGrid"></div>
</div>

<!-- Detalle de una consulta (drawer) -->
<div id="consultaDrawerOverlay" class="drawer-overlay" hidden></div>

<div id="consultaDrawer" class="drawer-panel drawer-md closed">
    <div class="drawer-header">
        <div class="drawer-title-group">
            <div class="drawer-icon-box drawer-icon-consulta">
                <i class="fas fa-file-medical"></i>
            </div>
            <div>
                <h3>Detalle de Atención</h3>
                <p>Ficha clínica completa</p>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="btnCerrarConsultaDrawer">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div id="detalleConsultaBody" class="drawer-body"></div>
</div>

<!-- Historial clínico (drawer); lo llena viewMedicalHistory() de medical-module.js -->
<div id="historialDrawerOverlay" class="drawer-overlay" hidden></div>

<div id="historialDrawer" class="drawer-panel drawer-lg closed">
    <div class="drawer-header">
        <div class="drawer-title-group">
            <div class="drawer-icon-box drawer-icon-historial">
                <i class="fas fa-notes-medical"></i>
            </div>
            <div>
                <h3>Historial Clínico</h3>
                <p>Paciente: <strong id="historyPetName" class="historial-paciente"></strong></p>
            </div>
        </div>
        <button type="button" class="drawer-close-btn" id="btnCerrarHistorialDrawer">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="historial-resumen">
        <div class="historial-dato">
            <span class="historial-dato-etiqueta">N° Historia Clínica</span>
            <span id="historyHCNumber" class="historial-dato-valor historial-dato-hc">---</span>
        </div>
        <div class="historial-separador"></div>
        <div class="historial-dato">
            <span class="historial-dato-etiqueta">Especie</span>
            <span id="historyPetSpecie" class="historial-dato-valor">---</span>
        </div>
        <div class="historial-separador"></div>
        <div class="historial-dato">
            <span class="historial-dato-etiqueta">Edad</span>
            <span id="historyPetAge" class="historial-dato-valor">---</span>
        </div>
    </div>

    <div class="drawer-body">
        <div>
            <h4 class="section-label"><i class="fas fa-syringe text-success"></i>Resumen de Vacunación</h4>
            <div id="vaccineList" class="vaccine-grid"></div>
        </div>
        <div>
            <h4 class="section-label"><i class="fas fa-history text-primary"></i>Línea de Tiempo de Consultas</h4>
            <div id="historyTimeline" class="history-timeline"></div>
        </div>
    </div>
</div>

<!-- Atención sin cita: elegir la mascota -->
<div id="modalSelectPetConsulta" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-ambulance"></i> Atención sin cita</h3>
            <span class="close" data-cerrar-modal="modalSelectPetConsulta">&times;</span>
        </div>
        <div class="modal-body">
            <p class="consulta-sin-cita-aviso">
                <i class="fas fa-info-circle"></i>
                Úsala para urgencias o pacientes que llegan sin cita. Si el paciente tiene cita,
                atiéndelo desde el <a href="index.php?action=vet_agenda">calendario</a> con
                <strong>Iniciar atención</strong>: así la consulta queda ligada a su cita.
            </p>
            <div class="input-group full m-0">
                <label for="consultaPetSearch">Buscar por nombre de mascota o dueño</label>
                <div class="premium-search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="consultaPetSearch" class="premium-search-input-modal" placeholder="Ej. Firulais, Juan...">
                </div>
            </div>
            <div id="consultaPetSuggestions" class="suggestions-list">
                <div class="empty-state-text"><i class="fas fa-search icon-large"></i>Escribe para buscar un paciente...</div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/modal_consulta.php"; ?>
