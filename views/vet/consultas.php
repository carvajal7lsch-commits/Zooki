<div class="consultations-dashboard animate__animated animate__fadeIn">
    <!-- Cabecera Premium -->
    <div class="header-container-white">
        <div class="head-title-desc">
            <h1 class="users-page-title">Consultas Médicas</h1>
            <p class="users-module-desc">Registro e historial clínico de pacientes</p>
        </div>
        
        <!-- Mini KPIs Compactos con Sparklines SVG -->
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
            <?php if ($_SESSION["usuario_id_rol"] == 2): ?>
            <?php // HU-56 / RN-208: las citas se atienden desde el calendario; esto es para urgencias. ?>
            <button class="btn-primary" onclick="openNewConsultationFlow()" title="Para urgencias o pacientes que llegan sin cita. Las citas se atienden desde el calendario.">
                <i class="fas fa-ambulance"></i> Atención sin cita
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros Avanzados Estilo Usuarios.php -->
    <div class="users-controls-bar__filters is-active" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
        <div class="search-input" style="flex: 0 1 350px; min-width: 250px;">
            <i class="fas fa-search"></i>
            <input type="text" id="consultationSearch" placeholder="Buscar por paciente, propietario o diagnóstico..." onkeyup="filtrarConsultas()">
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <select class="filter-select" id="filtroEspecie" onchange="filtrarConsultas()">
                <option value="">Todas las especies</option>
                <option value="canino">Caninos</option>
                <option value="felino">Felinos</option>
                <option value="ave">Aves</option>
                <option value="roedor">Roedores</option>
            </select>
            
            <select class="filter-select" id="filtroFecha" onchange="filtrarConsultas()">
                <option value="">Todas las fechas</option>
                <option value="hoy">Hoy</option>
                <option value="ayer">Ayer</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mes</option>
                <option value="custom">Personalizada...</option>
            </select>
            <input type="date" id="filtroFechaCustom" class="filter-select" style="display:none; padding: 0.35rem 0.5rem;" onchange="filtrarConsultas()">
            
            <button class="btn-clean" onclick="limpiarFiltros()" style="margin: 0; padding: 0.4rem 0.75rem;">
                <i class="fas fa-eraser"></i> Limpiar
            </button>
        </div>
    </div>

    <!-- Grid de Tarjetas de Consulta -->
    <div class="consultations-grid" id="consultationsGrid">
        <!-- Renderizado dinámico vía Javascript -->
    </div>
</div>

<!-- PANEL LATERAL DETALLE DE CONSULTA (Drawer) -->
<div id="consultaDrawerOverlay" class="drawer-overlay" style="display:none;" onclick="closeConsultaDrawer()"></div>

<div id="consultaDrawer" class="drawer-panel drawer-md closed">
    <!-- Header del Drawer -->
    <div class="drawer-header">
        <div class="drawer-title-group">
            <div class="drawer-icon-box" style="background: var(--primary-soft); color: var(--primary);">
                <i class="fas fa-file-medical"></i>
            </div>
            <div>
                <h3>Detalle de Atención</h3>
                <p>Ficha clínica completa</p>
            </div>
        </div>
        <button class="drawer-close-btn" onclick="closeConsultaDrawer()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Cuerpo scrolleable -->
    <div id="detalleConsultaBody" class="drawer-body"></div>
</div>

<!-- PANEL LATERAL HISTORIAL CLÍNICO (Drawer) -->
<div id="historialDrawerOverlay" class="drawer-overlay" style="display:none;" onclick="closeHistorialDrawer()"></div>

<div id="historialDrawer" class="drawer-panel drawer-lg closed">
    <!-- Header del Drawer -->
    <div class="drawer-header">
        <div class="drawer-title-group">
            <div class="drawer-icon-box" style="background: #f0fdf4; color: #10b981;">
                <i class="fas fa-notes-medical"></i>
            </div>
            <div>
                <h3>Historial Clínico</h3>
                <p>Paciente: <strong id="historyPetName" style="color:var(--primary);"></strong></p>
            </div>
        </div>
        <button class="drawer-close-btn" onclick="closeHistorialDrawer()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Info rápida de la mascota -->
    <div style="padding: 1rem 1.75rem; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; gap: 1.5rem; flex-shrink: 0;">
        <div style="display:flex; flex-direction:column; gap:0.15rem;">
            <span style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">N° Historia Clínica</span>
            <span id="historyHCNumber" style="font-weight:800; font-size:1rem; color:var(--primary);">---</span>
        </div>
        <div style="width:1px; background:#e2e8f0;"></div>
        <div style="display:flex; flex-direction:column; gap:0.15rem;">
            <span style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">Especie</span>
            <span id="historyPetSpecie" style="font-weight:700; font-size:0.95rem; color:var(--text-main);">---</span>
        </div>
        <div style="width:1px; background:#e2e8f0;"></div>
        <div style="display:flex; flex-direction:column; gap:0.15rem;">
            <span style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">Edad</span>
            <span id="historyPetAge" style="font-weight:700; font-size:0.95rem; color:var(--text-main);">---</span>
        </div>
    </div>

    <!-- Cuerpo scrolleable -->
    <div class="drawer-body">
        <!-- Resumen de Vacunación -->
        <div>
            <h4 class="section-label"><i class="fas fa-syringe text-success"></i>Resumen de Vacunación</h4>
            <div id="vaccineList" class="vaccine-grid"></div>
        </div>
        <!-- Línea de tiempo -->
        <div>
            <h4 class="section-label"><i class="fas fa-history text-primary"></i>Línea de Tiempo de Consultas</h4>
            <div id="historyTimeline" class="history-timeline"></div>
        </div>
    </div>
</div>

<!-- MODAL SELECCIONAR MASCOTA PARA CONSULTA -->
<div id="modalSelectPetConsulta" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-ambulance"></i> Atención sin cita</h3>
            <span class="close" onclick="closeModal('modalSelectPetConsulta')">&times;</span>
        </div>
        <div class="modal-body">
            <p class="consulta-sin-cita-aviso">
                <i class="fas fa-info-circle"></i>
                Úsala para urgencias o pacientes que llegan sin cita. Si el paciente tiene cita,
                atiéndelo desde el <a href="index.php?action=vet_agenda">calendario</a> con
                <strong>Iniciar atención</strong>: así la consulta queda ligada a su cita.
            </p>
            <div class="input-group full m-0">
                <label>Buscar por nombre de mascota o dueño</label>
                <div class="premium-search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="consultaPetSearch" class="premium-search-input-modal" placeholder="Ej. Firulais, Juan..." oninput="searchPetForConsultation(this.value)">
                </div>
            </div>
            <div id="consultaPetSuggestions" class="suggestions-list">
                <div class="empty-state-text"><i class="fas fa-search icon-large"></i>Escribe para buscar un paciente...</div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/modal_consulta.php"; ?>

<script>
// Datos serializados desde PHP para filtrado y renderizado reactivo e instantáneo
const todasLasConsultas = <?php echo json_encode($consultas); ?>;

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('filtroFecha').addEventListener('change', function() {
        const customInput = document.getElementById('filtroFechaCustom');
        customInput.style.display = this.value === 'custom' ? 'block' : 'none';
    });

    filtrarConsultas();
});

function filtrarConsultas() {
    const term = document.getElementById('consultationSearch').value.toLowerCase();
    const especie = document.getElementById('filtroEspecie').value;
    const fecha = document.getElementById('filtroFecha').value;
    const fechaCustom = document.getElementById('filtroFechaCustom').value;

    let filtradas = [...todasLasConsultas];

    // 1. Filtrar por término de búsqueda
    if (term) {
        filtradas = filtradas.filter(c => 
            (c.nombre_mascota && c.nombre_mascota.toLowerCase().includes(term)) ||
            (c.nombre_propietario && c.nombre_propietario.toLowerCase().includes(term)) ||
            (c.diagnostico && c.diagnostico.toLowerCase().includes(term)) ||
            (c.motivo_consulta && c.motivo_consulta.toLowerCase().includes(term))
        );
    }

    // 2. Filtrar por especie
    if (especie) {
        filtradas = filtradas.filter(c => 
            c.nombre_especie && c.nombre_especie.toLowerCase() === especie
        );
    }

    // 3. Filtrar por fecha
    if (fecha) {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        let fechaInicio, fechaFin;

        switch(fecha) {
            case 'hoy':
                fechaInicio = hoy;
                fechaFin = new Date(hoy);
                break;
            case 'ayer':
                fechaInicio = new Date(hoy);
                fechaInicio.setDate(fechaInicio.getDate() - 1);
                fechaFin = new Date(fechaInicio);
                break;
            case 'semana':
                fechaInicio = new Date(hoy);
                fechaInicio.setDate(hoy.getDate() - hoy.getDay());
                fechaFin = new Date(fechaInicio);
                fechaFin.setDate(fechaFin.getDate() + 6);
                break;
            case 'mes':
                fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                fechaFin = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
                break;
            case 'custom':
                if (fechaCustom) {
                    fechaInicio = new Date(fechaCustom);
                    fechaInicio.setMinutes(fechaInicio.getMinutes() + fechaInicio.getTimezoneOffset());
                    fechaInicio.setHours(0, 0, 0, 0);
                    fechaFin = new Date(fechaInicio);
                }
                break;
        }

        if (fechaInicio && fechaFin) {
            fechaFin.setHours(23, 59, 59, 999);
            filtradas = filtradas.filter(c => {
                if (!c.fecha_hora || c.fecha_hora.startsWith('0000')) return false;
                const [datePart] = c.fecha_hora.split(' ');
                const [year, month, day] = datePart.split('-');
                const citaFecha = new Date(year, month - 1, day);
                return citaFecha >= fechaInicio && citaFecha <= fechaFin;
            });
        }
    }

    // Ordenar de forma descendente por fecha
    filtradas.sort((a, b) => {
        const dateA = new Date((a.fecha_hora || '').replace(' ', 'T'));
        const dateB = new Date((b.fecha_hora || '').replace(' ', 'T'));
        return dateB - dateA;
    });

    actualizarMetricas(filtradas);
    renderizarGrid(filtradas);
}

function actualizarMetricas(filtradas) {
    const total = filtradas.length;
    let caninos = 0;
    let felinos = 0;

    filtradas.forEach(c => {
        const esp = (c.nombre_especie || '').toLowerCase();
        if (esp === 'canino') caninos++;
        else if (esp === 'felino') felinos++;
    });

    document.getElementById('kpi-total').textContent = total;
    document.getElementById('kpi-caninos').textContent = caninos;
    document.getElementById('kpi-felinos').textContent = felinos;

    drawSparkline('kpi-total-spark', total, total, '#0052FF');
    drawSparkline('kpi-caninos-spark', caninos, total, '#10B981');
    drawSparkline('kpi-felinos-spark', felinos, total, '#F59E0B');
}

function drawSparkline(elementId, value, max, color) {
    const container = document.getElementById(elementId);
    if(!container) return;

    if (max === 0) max = 1;
    const normalizedHeight = 28 - ((value / max) * 15);
    
    let pathData;
    if (value === 0) {
        pathData = `M0,28 L25,28 L50,28 L75,28 L100,28`;
    } else {
        const heightDiff = 28 - normalizedHeight;
        const p1 = 28 - Math.random() * (heightDiff * 0.5);
        const p2 = 28 - Math.random() * (heightDiff * 1.2);
        const p3 = 28 - Math.random() * (heightDiff * 0.8);
        pathData = `M0,28 L25,${p1} L50,${p2} L75,${p3} L100,${normalizedHeight}`;
    }
    
    const fillPath = `${pathData} L100,30 L0,30 Z`;
    
    container.innerHTML = `
        <defs>
            <linearGradient id="grad-${elementId}" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" style="stop-color:${color};stop-opacity:0.15" />
                <stop offset="100%" style="stop-color:${color};stop-opacity:0" />
            </linearGradient>
        </defs>
        <path d="${pathData}" fill="none" stroke="${color}" stroke-width="2" vector-effect="non-scaling-stroke"></path>
        <path d="${fillPath}" fill="url(#grad-${elementId})" stroke="none"></path>
        <circle cx="100" cy="${normalizedHeight}" r="2.5" fill="${color}" />
    `;
}

let currentFilteredConsultas = [];

function renderizarGrid(consultas) {
    currentFilteredConsultas = consultas;
    const grid = document.getElementById('consultationsGrid');
    grid.innerHTML = '';

    if (consultas.length === 0) {
        grid.innerHTML = `
            <div class="consultations-empty">
                <i class="fas fa-folder-open icon-large" style="font-size: 2.5rem; color: #cbd5e1; display: block; margin-bottom: 0.75rem;"></i>
                No se encontraron consultas registradas con los filtros seleccionados.
            </div>
        `;
        return;
    }

    consultas.forEach((c, index) => {
        const isValid = c.fecha_hora && !c.fecha_hora.startsWith('0000');
        let dateStr = 'Sin fecha';
        let timeStr = '--:--';
        let diaMesStr = '';

        if (isValid) {
            const parts = c.fecha_hora.split(' ');
            const [y, m, d] = parts[0].split('-');
            dateStr = `${d}/${m}/${y}`;
            diaMesStr = `${d}/${m}`;
            if (parts[1]) {
                timeStr = parts[1].substring(0, 5);
            }
        }

        const especieClass = (c.nombre_especie || '').toLowerCase();
        const iconClass = especieClass === 'felino' ? 'fa-cat' : 'fa-dog';

        const card = document.createElement('div');
        card.className = 'consultation-card';
        card.setAttribute('onclick', `viewFullConsultationByIndex(${index})`);

        card.innerHTML = `
            <div class="consultation-card-header">
                <span class="consultation-time">
                    <i class="far fa-clock"></i> ${timeStr} 
                    <span class="consultation-date">(${diaMesStr})</span>
                </span>
                <span class="consultation-species-badge ${especieClass}">
                    ${c.nombre_especie || 'Otro'}
                </span>
            </div>
            
            <div class="consultation-patient-info">
                <div class="consultation-avatar ${especieClass}">
                    <i class="fas ${iconClass}"></i>
                </div>
                <div class="consultation-names">
                    <strong class="consultation-pet-name">${c.nombre_mascota || '—'}</strong>
                    <span class="consultation-owner-name">
                        <i class="far fa-user"></i> ${c.nombre_propietario || '—'}
                    </span>
                </div>
            </div>

            <div class="consultation-diag" title="${c.diagnostico || ''}">
                ${c.diagnostico || 'Sin diagnóstico preliminar registrado.'}
            </div>

            <div class="consultation-footer-actions" onclick="event.stopPropagation();">
                ${c.id_cita ? '' : '<span class="consultation-sin-cita-badge" title="Registrada sin cita (urgencia o imprevisto)"><i class="fas fa-ambulance"></i> Sin cita</span>'}
                <button class="btn-icon premium history" onclick="viewFullConsultationByIndex(${index})" title="Ver Detalle">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn-icon premium medical" onclick="viewMedicalHistory(${c.id_mascota}, '${c.nombre_mascota}')" title="Historial Clínico">
                    <i class="fas fa-notes-medical"></i>
                </button>
            </div>
        `;
        grid.appendChild(card);
    });
}

function viewFullConsultationByIndex(index) {
    const c = currentFilteredConsultas[index];
    if (c) {
        viewFullConsultation(c);
    }
}

function limpiarFiltros() {
    document.getElementById('consultationSearch').value = '';
    document.getElementById('filtroEspecie').value = '';
    document.getElementById('filtroFecha').value = '';
    document.getElementById('filtroFechaCustom').value = '';
    document.getElementById('filtroFechaCustom').style.display = 'none';
    filtrarConsultas();
}

function openHistorialDrawer() {
    document.getElementById('historialDrawerOverlay').style.display = 'block';
    setTimeout(() => {
        document.getElementById('historialDrawer').classList.remove('closed');
    }, 10);
}

function closeHistorialDrawer() {
    document.getElementById('historialDrawer').classList.add('closed');
    setTimeout(() => {
        document.getElementById('historialDrawerOverlay').style.display = 'none';
    }, 400);
}

function openConsultaDrawer() {
    document.getElementById('consultaDrawerOverlay').style.display = 'block';
    setTimeout(() => {
        document.getElementById('consultaDrawer').classList.remove('closed');
    }, 10);
}

function closeConsultaDrawer() {
    document.getElementById('consultaDrawer').classList.add('closed');
    setTimeout(() => {
        document.getElementById('consultaDrawerOverlay').style.display = 'none';
    }, 400);
}

function viewFullConsultation(c) {
    let dateStr = c.fecha_hora;
    let formattedDate = 'Sin fecha';
    let formattedTime = '--:--';
    if (dateStr && !dateStr.startsWith('0000')) {
        let d = new Date(dateStr.replace(' ', 'T'));
        formattedDate = d.toLocaleDateString();
        formattedTime = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    const body = document.getElementById('detalleConsultaBody');
    body.innerHTML = `
        <!-- Tarjeta del Paciente y Doctor -->
        <div class="clinical-summary-card">
            <div class="clinical-summary-patient">
                <div class="clinical-summary-patient-icon">
                    <i class="fas fa-paw"></i>
                </div>
                <div>
                    <h4>${c.nombre_mascota}</h4>
                    <span>Propietario: ${c.nombre_propietario}</span>
                </div>
            </div>
            <div class="clinical-summary-meta">
                <div class="clinical-summary-meta-doc"><i class="fas fa-user-md"></i>${c.veterinario || 'Veterinario'}</div>
                <div class="clinical-summary-meta-date"><i class="far fa-calendar-alt"></i>${formattedDate} · ${formattedTime}</div>
            </div>
        </div>

        <!-- Triage Vitales -->
        <h4 class="section-label">Triage / Constantes Vitales</h4>
        <div class="vital-signs-grid">
            <div class="vital-sign-card">
                <div class="vital-sign-icon weight"><i class="fas fa-weight"></i></div>
                <div class="vital-sign-info">
                    <span class="vital-sign-label">Peso</span>
                    <span class="vital-sign-value">${c.peso || '--'} Kg</span>
                </div>
            </div>
            <div class="vital-sign-card">
                <div class="vital-sign-icon temp"><i class="fas fa-thermometer-half"></i></div>
                <div class="vital-sign-info">
                    <span class="vital-sign-label">Temperatura</span>
                    <span class="vital-sign-value">${c.temperatura || '--'} °C</span>
                </div>
            </div>
            <div class="vital-sign-card">
                <div class="vital-sign-icon heart"><i class="fas fa-heartbeat"></i></div>
                <div class="vital-sign-info">
                    <span class="vital-sign-label">Frec. Cardíaca</span>
                    <span class="vital-sign-value">${c.frecuencia_cardiaca || '--'} LPM</span>
                </div>
            </div>
        </div>

        <!-- Reporte Clínico -->
        <h4 class="section-label" style="margin-top: 1.5rem;">Reporte Clínico</h4>
        <div style="display: flex; flex-direction: column; gap: 0.9rem;">
            <div class="report-box default">
                <div class="report-box-header"><i class="fas fa-comment-medical"></i>Motivo de Consulta</div>
                <div class="report-box-body">${c.motivo_consulta || 'Sin especificar.'}</div>
            </div>
            <div class="report-box default">
                <div class="report-box-header"><i class="fas fa-notes-medical"></i>Anamnesis / Observaciones</div>
                <div class="report-box-body">${c.anamnesis || 'Sin observaciones registradas.'}</div>
            </div>
            <div class="report-box info">
                <div class="report-box-header"><i class="fas fa-microscope"></i>Diagnóstico</div>
                <div class="report-box-body">${c.diagnostico || 'Sin diagnóstico registrado.'}</div>
            </div>
            <div class="report-box success">
                <div class="report-box-header"><i class="fas fa-pills"></i>Plan de Tratamiento</div>
                <div class="report-box-body">${c.plan_tratamiento || 'Sin plan de tratamiento registrado.'}</div>
            </div>
        </div>
    `;
    body.scrollTop = 0;
    openConsultaDrawer();
}
</script>
