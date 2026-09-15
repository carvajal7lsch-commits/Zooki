<?php
$nombre = explode(" ", trim($_SESSION["usuario_nombre"]))[0];
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>

<div class="animate__animated animate__fadeIn">
    <div class="header-container-white" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
        <div class="head-title-desc" style="flex: 1; min-width: 250px;">
            <h1 class="users-page-title" style="margin-bottom: 0.2rem; font-size: 1.5rem; font-weight: 800; color: #1e293b;">Gestión de Citas</h1>
            <p class="users-module-desc" style="margin: 0; color: #64748b; font-size: 0.9rem;">Supervisión global agrupada por veterinario</p>
        </div>
        
        <!-- Mini KPIs Compactos Integrados -->
        <div class="mini-kpis-container" style="margin: 0; flex: 2; justify-content: center;">
            <div class="mini-kpi">
                <svg id="kpi-total-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-total">0</span>
                    <span class="mini-kpi-label">Total Citas</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-pendientes-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-pendientes">0</span>
                    <span class="mini-kpi-label">Pendientes</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-completadas-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-completadas">0</span>
                    <span class="mini-kpi-label">Completadas</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-canceladas-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-canceladas">0</span>
                    <span class="mini-kpi-label">Canceladas</span>
                </div>
            </div>
        </div>

        <div class="header-actions" style="margin-left: auto; display: flex; gap: 0.5rem;">
            <button class="btn-secondary" onclick="exportarCitas()">
                <i class="fas fa-file-excel" style="color: #0052FF;"></i> Excel
            </button>
            <button class="btn-secondary" onclick="exportarCitasPDF()">
                <i class="fas fa-file-pdf" style="color: #0052FF;"></i> PDF
            </button>
        </div>
    </div>

    <!-- Filtros Avanzados -->
    <div class="filters-bar card-filters">
        <div class="filter-group">
            <label><i class="far fa-calendar-alt"></i> Fecha:</label>
            <select id="filtroFecha" onchange="filtrarCitas()">
                <option value="">Todas</option>
                <option value="hoy" selected>Hoy</option>
                <option value="ayer">Ayer</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mes</option>
                <option value="custom">Personalizada</option>
            </select>
            <input type="date" id="filtroFechaCustom" style="display:none;" onchange="filtrarCitas()">
        </div>
        <div class="filter-group">
            <label>Tipo de cita:</label>
            <select id="filtroTipoCita" onchange="filtrarCitas()">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Estado:</label>
            <select id="filtroEstado" onchange="filtrarCitas()">
                <option value="">Todos</option>
                <option value="pendiente">Pendiente</option>
                <option value="confirmada">Confirmada</option>
                <option value="en_curso">En curso</option>
                <option value="completada">Completada</option>
                <option value="no_asistio">No asistió</option>
                <option value="sin_cerrar">Sin cerrar</option>
                <option value="cerrada_sin_consulta">Cerrada sin consulta</option>
                <option value="cancelada">Cancelada</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Veterinario:</label>
            <select id="filtroVet" onchange="filtrarCitas()">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-search">
            <i class="fas fa-search"></i>
            <input type="text" id="filtroSearch" placeholder="Buscar paciente o cliente..." onkeyup="filtrarCitas()">
        </div>
        <button class="btn-clean" onclick="limpiarFiltros()">
            <i class="fas fa-eraser"></i> Limpiar
        </button>
    </div>

    <!-- Contenedor Kanban Carousel -->
    <div class="kanban-carousel-container" style="position: relative;">
        <button class="carousel-btn left" onclick="scrollKanban('left')" id="btnScrollLeft" style="display: none;">
            <i class="fas fa-chevron-left"></i>
        </button>
        
        <div class="kanban-wrapper" id="kanbanWrapper">
            <div class="kanban-board" id="kanbanBoard">
                <div style="text-align:center; padding:3rem; width: 100%;">
                    <div class="loader-small" style="margin:0 auto;"></div>
                </div>
            </div>
        </div>

        <button class="carousel-btn right" onclick="scrollKanban('right')" id="btnScrollRight" style="display: none;">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<script>
let todasLasCitas = [];
let citasFiltradasGlobal = [];
let veterinariosMap = {};
let veterinariosArray = [];
let tiposCita = [];

document.addEventListener('DOMContentLoaded', function() {
    cargarDatosIniciales();
    
    document.getElementById('filtroFecha').addEventListener('change', function() {
        const customInput = document.getElementById('filtroFechaCustom');
        customInput.style.display = this.value === 'custom' ? 'block' : 'none';
    });

    const kanbanWrapper = document.getElementById('kanbanWrapper');
    if (kanbanWrapper) {
        kanbanWrapper.addEventListener('scroll', checkScrollButtons);
        window.addEventListener('resize', checkScrollButtons);

        // Lógica de arrastre para scroll
        let isDown = false;
        let startX;
        let scrollLeft;

        kanbanWrapper.addEventListener('mousedown', (e) => {
            isDown = true;
            kanbanWrapper.style.cursor = 'grabbing';
            startX = e.pageX - kanbanWrapper.offsetLeft;
            scrollLeft = kanbanWrapper.scrollLeft;
        });

        kanbanWrapper.addEventListener('mouseleave', () => {
            isDown = false;
            kanbanWrapper.style.cursor = 'grab';
        });

        kanbanWrapper.addEventListener('mouseup', () => {
            isDown = false;
            kanbanWrapper.style.cursor = 'grab';
        });

        kanbanWrapper.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - kanbanWrapper.offsetLeft;
            const walk = (x - startX) * 2; // Multiplicador de velocidad
            kanbanWrapper.scrollLeft = scrollLeft - walk;
        });
        
        kanbanWrapper.style.cursor = 'grab';
    }
});

function scrollKanban(direction) {
    const wrapper = document.getElementById('kanbanWrapper');
    const scrollAmount = 340; // Approx one column width + gap
    if (direction === 'left') {
        wrapper.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        wrapper.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
}

function checkScrollButtons() {
    const wrapper = document.getElementById('kanbanWrapper');
    const btnLeft = document.getElementById('btnScrollLeft');
    const btnRight = document.getElementById('btnScrollRight');
    
    if (!wrapper || !btnLeft || !btnRight) return;
    
    if (wrapper.scrollWidth > wrapper.clientWidth) {
        btnLeft.style.display = wrapper.scrollLeft > 0 ? 'flex' : 'none';
        btnRight.style.display = wrapper.scrollLeft < (wrapper.scrollWidth - wrapper.clientWidth - 5) ? 'flex' : 'none';
    } else {
        btnLeft.style.display = 'none';
        btnRight.style.display = 'none';
    }
}

async function cargarDatosIniciales() {
    try {
        const [citasRes, vetsRes] = await Promise.all([
            fetch('index.php?action=listar_todas_citas_ajax'),
            fetch('index.php?action=listar_veterinarios_ajax')
        ]);
        
        const citasData = await citasRes.json();
        const vetsData = await vetsRes.json();
        
        if (!citasData.success) {
            document.getElementById('kanbanBoard').innerHTML = 
                `<div class="kanban-empty">Error al cargar las citas: ${citasData.message}</div>`;
            return;
        }
        
        todasLasCitas = citasData.citas;
        
        const selectVet = document.getElementById('filtroVet');
        if (Array.isArray(vetsData)) {
            veterinariosArray = vetsData;
            vetsData.forEach(v => {
                veterinariosMap[v.documento] = v.nombre_completo;
                selectVet.innerHTML += `<option value="${v.documento}">Dr. ${v.nombre_completo.split(' ')[0]}</option>`;
            });
        }
        
        const tiposUnicos = new Set();
        todasLasCitas.forEach(cita => {
            if (cita.tipo_cita_nombre) tiposUnicos.add(cita.tipo_cita_nombre);
        });
        
        const selectTipo = document.getElementById('filtroTipoCita');
        tiposUnicos.forEach(tipo => {
            selectTipo.innerHTML += `<option value="${tipo}">${tipo}</option>`;
        });
        
        // Cargar por defecto las de HOY
        aplicarFiltros();
    } catch (error) {
        console.error('Error cargando datos:', error);
        document.getElementById('kanbanBoard').innerHTML = 
            `<div class="kanban-empty">Error de conexión: ${error.message}</div>`;
    }
}

function filtrarCitas() {
    aplicarFiltros();
}

function aplicarFiltros() {
    const fecha = document.getElementById('filtroFecha').value;
    const fechaCustom = document.getElementById('filtroFechaCustom').value;
    const tipoCita = document.getElementById('filtroTipoCita').value;
    const estado = document.getElementById('filtroEstado').value;
    const vetFiltro = document.getElementById('filtroVet').value;
    const search = document.getElementById('filtroSearch').value.toLowerCase();
    
    let citasFiltradas = [...todasLasCitas];
    
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
                    // Add timezone offset to prevent date shifting
                    fechaInicio.setMinutes(fechaInicio.getMinutes() + fechaInicio.getTimezoneOffset());
                    fechaInicio.setHours(0, 0, 0, 0);
                    fechaFin = new Date(fechaInicio);
                }
                break;
        }
        
        if (fechaInicio && fechaFin) {
            fechaFin.setHours(23, 59, 59, 999);
            citasFiltradas = citasFiltradas.filter(c => {
                // Ensure correct parsing of local date
                const [year, month, day] = c.fecha.split('-');
                const citaFecha = new Date(year, month - 1, day);
                return citaFecha >= fechaInicio && citaFecha <= fechaFin;
            });
        }
    }
    
    if (tipoCita) citasFiltradas = citasFiltradas.filter(c => c.tipo_cita_nombre === tipoCita);
    if (estado) citasFiltradas = citasFiltradas.filter(c => c.estado === estado);
    if (vetFiltro) citasFiltradas = citasFiltradas.filter(c => c.doc_veterinario === vetFiltro);
    if (search) {
        citasFiltradas = citasFiltradas.filter(c => 
            (c.mascota_nombre && c.mascota_nombre.toLowerCase().includes(search)) ||
            (c.propietario_nombre && c.propietario_nombre.toLowerCase().includes(search)) ||
            (c.motivo && c.motivo.toLowerCase().includes(search))
        );
    }
    
    // Order by date and time
    citasFiltradas.sort((a, b) => {
        const fechaA = new Date(a.fecha + 'T' + (a.hora || '00:00:00'));
        const fechaB = new Date(b.fecha + 'T' + (b.hora || '00:00:00'));
        return fechaA - fechaB; // Ascending order
    });
    
    actualizarMétricas(citasFiltradas);
    renderizarKanban(citasFiltradas, vetFiltro);
    citasFiltradasGlobal = citasFiltradas;
}

function actualizarMétricas(citasFiltradas) {
    const total = citasFiltradas.length;
    let pendientes = 0, completadas = 0, canceladas = 0;
    
    citasFiltradas.forEach(c => {
        if (c.estado === 'pendiente' || c.estado === 'confirmada') pendientes++;
        else if (c.estado === 'completada') completadas++;
        else if (c.estado === 'cancelada') canceladas++;
    });
    
    document.getElementById('kpi-total').textContent = total;
    document.getElementById('kpi-pendientes').textContent = pendientes;
    document.getElementById('kpi-completadas').textContent = completadas;
    document.getElementById('kpi-canceladas').textContent = canceladas;

    drawSparkline('kpi-total-spark', total, total, '#0052FF');
    drawSparkline('kpi-pendientes-spark', pendientes, total, '#F59E0B');
    drawSparkline('kpi-completadas-spark', completadas, total, '#10B981');
    drawSparkline('kpi-canceladas-spark', canceladas, total, '#EF4444');
}

function renderizarKanban(citas, vetFiltro) {
    const board = document.getElementById('kanbanBoard');
    board.innerHTML = '';
    
    // Filter veterinarians if one is selected in the dropdown
    let vetsToRender = veterinariosArray;
    if (vetFiltro) {
        vetsToRender = veterinariosArray.filter(v => v.documento === vetFiltro);
    }
    
    if (vetsToRender.length === 0) {
        board.innerHTML = `<div class="kanban-empty">No hay veterinarios registrados.</div>`;
        return;
    }
    
    // Agrupar citas por veterinario
    const agrupadas = {};
    vetsToRender.forEach(v => agrupadas[v.documento] = []);
    
    citas.forEach(c => {
        if (agrupadas[c.doc_veterinario]) {
            agrupadas[c.doc_veterinario].push(c);
        }
    });
    
    const STATE_COLORS = {
        pendiente: '#F59E0B',
        confirmada: '#5560FF',
        en_curso: '#0EA5E9',
        completada: '#10B981',
        no_asistio: '#94A3B8',
        sin_cerrar: '#E11D48',
        cerrada_sin_consulta: '#64748B',
        cancelada: '#EF4444'
    };
    
    vetsToRender.forEach(vet => {
        const vetCitas = agrupadas[vet.documento];
        const initial = vet.nombre_completo.charAt(0);
        const firstName = vet.nombre_completo.split(' ')[0];
        const numCitas = vetCitas.length;
        
        let columnHtml = `
            <div class="kanban-col">
                <div class="kb-col-header">
                    <div class="kb-vet-info">
                        <div class="kb-avatar">${initial}</div>
                        <div>
                            <div class="kb-vet-name">Dr. ${firstName}</div>
                            <div class="kb-vet-count">${numCitas} cita(s)</div>
                        </div>
                    </div>
                </div>
                <div class="kb-col-body">
        `;
        
        if (numCitas === 0) {
            columnHtml += `
                <div class="kb-empty-state">
                    <i class="far fa-calendar-times"></i>
                    <p>Sin citas para mostrar</p>
                </div>
            `;
        } else {
            vetCitas.forEach(cita => {
                const color = STATE_COLORS[cita.estado] || '#6B7280';
                const horaStr = cita.hora ? cita.hora.substring(0, 5) : '—';
                const fechaParts = cita.fecha.split('-');
                const diaMesStr = `${fechaParts[2]}/${fechaParts[1]}`;
                
                columnHtml += `
                    <div class="kb-card" onclick="verDetalle(${cita.id_cita})">
                        <div class="kb-card-header">
                            <span class="kb-time"><i class="far fa-clock"></i> ${horaStr} <span class="kb-date">(${diaMesStr})</span></span>
                            <span class="kb-status" style="background: ${color}15; color: ${color};">${cita.estado}</span>
                        </div>
                        <div class="kb-patient-name">${cita.mascota_nombre || '—'}</div>
                        <div class="kb-owner-name"><i class="far fa-user"></i> ${cita.propietario_nombre || '—'}</div>
                        <div class="kb-reason">${cita.tipo_cita_nombre || 'Consulta'}</div>
                    </div>
                `;
            });
        }
        
        columnHtml += `</div></div>`; // Cierre kb-col-body y kanban-col
        board.innerHTML += columnHtml;
    });
    
    setTimeout(checkScrollButtons, 100);
}

function verDetalle(idCita) {
    const cita = todasLasCitas.find(c => c.id_cita == idCita);
    if (!cita) return;
    
    const estadoColor = {
        pendiente: '#F59E0B', confirmada: '#5560FF',
        completada: '#10B981', cancelada: '#EF4444'
    }[cita.estado] || '#6B7280';
    
    Swal.fire({
        html: `
            <div class="cita-modal-container">
                <div class="cita-modal-header" style="background: linear-gradient(135deg, #0052FF 0%, #5560FF 100%); color: white; padding: 2rem 2.5rem; border-radius: 0; margin-bottom: 1.5rem; text-align: left; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800;">Detalle de la Cita</h3>
                        <span style="background: white; color: ${estadoColor}; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-right: 2.5rem;">${cita.estado}</span>
                    </div>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.85rem; opacity: 0.9; font-weight: 500;"><i class="far fa-calendar-alt"></i> ${cita.fecha} &nbsp;&bull;&nbsp; <i class="far fa-clock"></i> ${cita.horaStr || cita.hora}</p>
                </div>

                <div class="cita-modal-body" style="text-align: left; padding: 0 2.5rem 2.5rem 2.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div class="modal-info-box" style="background: #f8fafc; padding: 1rem; border-radius: 10px; border: 1px solid #e2e8f0; transition: all 0.2s;">
                            <p style="margin: 0; font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">Paciente</p>
                            <p style="margin: 0.2rem 0 0 0; font-size: 0.95rem; font-weight: 700; color: #1e293b;"><i class="fas fa-paw" style="color: #0052FF; width: 16px; margin-right: 4px;"></i> ${cita.mascota_nombre || 'No especificado'}</p>
                        </div>
                        <div class="modal-info-box" style="background: #f8fafc; padding: 1rem; border-radius: 10px; border: 1px solid #e2e8f0; transition: all 0.2s;">
                            <p style="margin: 0; font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">Propietario</p>
                            <p style="margin: 0.2rem 0 0 0; font-size: 0.95rem; font-weight: 700; color: #1e293b;"><i class="far fa-user" style="color: #0052FF; width: 16px; margin-right: 4px;"></i> ${cita.propietario_nombre || 'No especificado'}</p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="border-left: 3px solid #0052FF; padding-left: 0.8rem; background: #f8fafc; padding: 0.8rem; border-radius: 0 8px 8px 0;">
                            <p style="margin: 0; font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Veterinario Asignado</p>
                            <p style="margin: 0.2rem 0 0 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;"><i class="fas fa-user-md" style="color: #94a3b8; width: 16px; margin-right: 4px;"></i> Dr. ${cita.veterinario_nombre || (veterinariosMap[cita.doc_veterinario])}</p>
                        </div>
                        <div style="border-left: 3px solid #5560FF; padding-left: 0.8rem; background: #f8fafc; padding: 0.8rem; border-radius: 0 8px 8px 0;">
                            <p style="margin: 0; font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 800;">Tipo de Cita</p>
                            <p style="margin: 0.2rem 0 0 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;"><i class="fas fa-clipboard-list" style="color: #94a3b8; width: 16px; margin-right: 4px;"></i> ${cita.tipo_cita_nombre}</p>
                        </div>
                    </div>

                    <div style="background: #f1f5f9; padding: 1.2rem; border-radius: 10px; border: 1px solid #e2e8f0;">
                        <p style="margin: 0 0 0.5rem 0; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 800;"><i class="far fa-comment-alt" style="margin-right: 6px;"></i> Motivo / Notas Adicionales</p>
                        <p style="margin: 0; font-size: 0.9rem; color: #334155; line-height: 1.6; font-style: italic;">"${cita.motivo || 'Sin observaciones adicionales.'}"</p>
                    </div>
                </div>
            </div>
        `,
        showCloseButton: true,
        showConfirmButton: false,
        padding: '0',
        customClass: {
            popup: 'premium-modal',
            htmlContainer: 'premium-html-container',
            closeButton: 'premium-close-btn'
        },
        width: '600px'
    });
}

function limpiarFiltros() {
    document.getElementById('filtroFecha').value = 'hoy';
    document.getElementById('filtroFechaCustom').value = '';
    document.getElementById('filtroFechaCustom').style.display = 'none';
    document.getElementById('filtroTipoCita').value = '';
    document.getElementById('filtroEstado').value = '';
    document.getElementById('filtroVet').value = '';
    document.getElementById('filtroSearch').value = '';
    aplicarFiltros();
}

function drawSparkline(elementId, value, max, color) {
    const container = document.getElementById(elementId);
    if(!container) return;

    if (max === 0) max = 1;
    const normalizedHeight = 28 - ((value / max) * 15); // Y bounds: 28 to 13 (softer curve)
    
    let pathData;
    if (value === 0) {
        // Línea plana abajo si el valor es cero
        pathData = `M0,28 L25,28 L50,28 L75,28 L100,28`;
    } else {
        // La altura máxima de los puntos intermedios debe ser proporcional al valor final
        const heightDiff = 28 - normalizedHeight;
        
        // Puntos aleatorios pero escalados al tamaño del dato para mantener coherencia visual
        const p1 = 28 - Math.random() * (heightDiff * 0.5);
        const p2 = 28 - Math.random() * (heightDiff * 1.2); // Permite un pequeño pico antes del final
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

async function exportarCitas() {
    if (citasFiltradasGlobal.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin datos',
            text: 'No hay citas para exportar con los filtros aplicados actualmente.',
            confirmButtonColor: '#0052FF'
        });
        return;
    }

    try {
        // Mostrar cargando mientras procesa la librería (en caso de que sean muchas filas)
        Swal.fire({
            title: 'Generando Excel...',
            text: 'Dando formato corporativo a los datos.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Crear libro y hoja
        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'Zooki Software';
        const sheet = workbook.addWorksheet('Reporte de Citas');

        // Fila 1: Título Principal
        sheet.mergeCells('A1:I1');
        const titleCell = sheet.getCell('A1');
        titleCell.value = 'ZOOKI - REPORTE DE CITAS';
        titleCell.font = { name: 'Arial', size: 14, bold: true, color: { argb: 'FFFFFFFF' } };
        titleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0052FF' } }; // Azul Zooki
        titleCell.alignment = { horizontal: 'center', vertical: 'middle' };
        sheet.getRow(1).height = 35;

        // Fila 2: Subtítulo Fecha
        sheet.mergeCells('A2:I2');
        const subtitleCell = sheet.getCell('A2');
        subtitleCell.value = `Generado el: ${new Date().toLocaleString()}`;
        subtitleCell.font = { name: 'Arial', size: 10, italic: true, color: { argb: 'FF64748B' } };
        subtitleCell.alignment = { horizontal: 'center', vertical: 'middle' };
        sheet.getRow(2).height = 20;

        // Fila 3: Espacio en blanco
        sheet.addRow([]);

        // Fila 4: Encabezados de Columna
        const headerRow = sheet.addRow([
            'ID', 'Paciente', 'Propietario', 'Veterinario', 
            'Fecha', 'Hora', 'Estado', 'Tipo de Cita', 'Motivo'
        ]);

        headerRow.eachCell((cell) => {
            cell.font = { name: 'Arial', bold: true, color: { argb: 'FFFFFFFF' } };
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF334155' } }; // Slate dark
            cell.alignment = { horizontal: 'center', vertical: 'middle' };
        });
        headerRow.height = 25;

        // Insertar los Datos
        citasFiltradasGlobal.forEach(c => {
            const estadoTexto = c.estado.toUpperCase();
            
            const row = sheet.addRow([
                c.id_cita,
                c.mascota_nombre || '—',
                c.propietario_nombre || '—',
                `Dr. ${c.veterinario_nombre || veterinariosMap[c.doc_veterinario] || '—'}`,
                c.fecha,
                c.hora ? c.hora.substring(0, 5) : '—',
                estadoTexto,
                c.tipo_cita_nombre || 'Consulta',
                c.motivo || 'Sin observaciones.'
            ]);

            // Estilos individuales para las celdas de esta fila
            row.eachCell((cell, colNumber) => {
                cell.font = { name: 'Arial', size: 10 };
                cell.alignment = { vertical: 'middle' };
                
                // Centrar ciertas columnas (ID, Fecha, Hora)
                if (colNumber === 1 || colNumber === 5 || colNumber === 6) {
                    cell.alignment = { horizontal: 'center', vertical: 'middle' };
                }

                // Columna Estado (7): Pintar según el status
                if (colNumber === 7) {
                    cell.font.bold = true;
                    cell.alignment = { horizontal: 'center', vertical: 'middle' };
                    
                    if (estadoTexto === 'PENDIENTE') cell.font.color = { argb: 'FFF59E0B' }; // Naranja
                    else if (estadoTexto === 'COMPLETADA') cell.font.color = { argb: 'FF10B981' }; // Verde
                    else if (estadoTexto === 'CONFIRMADA') cell.font.color = { argb: 'FF5560FF' }; // Azul clarito
                    else if (estadoTexto === 'CANCELADA') cell.font.color = { argb: 'FFEF4444' }; // Rojo
                }
            });
        });

        // Configurar el ancho de las columnas
        sheet.columns = [
            { width: 8 },  // ID
            { width: 18 }, // Paciente
            { width: 22 }, // Propietario
            { width: 25 }, // Veterinario
            { width: 12 }, // Fecha
            { width: 10 }, // Hora
            { width: 16 }, // Estado
            { width: 25 }, // Tipo
            { width: 40 }  // Motivo
        ];

        // Crear el archivo binario y forzar descarga
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        
        const fechaStr = new Date().toISOString().split('T')[0];
        
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `Zooki_Reporte_Citas_${fechaStr}.xlsx`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Cerrar alerta de carga y mostrar éxito
        Swal.close();
        
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: 'Excel Exportado Correctamente' });

    } catch (error) {
        console.error('Error al generar Excel:', error);
        Swal.fire('Error', 'Hubo un problema al generar el archivo Excel. Revisa tu consola.', 'error');
    }
}

function exportarCitasPDF() {
    if (citasFiltradasGlobal.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin datos',
            text: 'No hay citas para exportar con los filtros aplicados actualmente.',
            confirmButtonColor: '#0052FF'
        });
        return;
    }

    try {
        Swal.fire({
            title: 'Generando PDF...',
            text: 'Preparando documento corporativo.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');

        // Título del PDF
        doc.setFillColor(0, 82, 255);
        doc.rect(0, 0, doc.internal.pageSize.width, 25, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(16);
        doc.setFont("helvetica", "bold");
        doc.text('ZOOKI - REPORTE DE CITAS', 14, 16);
        
        doc.setFontSize(9);
        doc.setFont("helvetica", "normal");
        doc.text(`Generado el: ${new Date().toLocaleString()}`, doc.internal.pageSize.width - 14, 16, { align: 'right' });

        // Preparar Datos
        const columnas = ['ID', 'Paciente', 'Propietario', 'Veterinario', 'Fecha', 'Hora', 'Estado', 'Tipo de Cita'];
        const filas = citasFiltradasGlobal.map(c => [
            c.id_cita,
            c.mascota_nombre || '—',
            c.propietario_nombre || '—',
            `Dr. ${c.veterinario_nombre || veterinariosMap[c.doc_veterinario] || '—'}`,
            c.fecha,
            c.hora ? c.hora.substring(0, 5) : '—',
            c.estado.toUpperCase(),
            c.tipo_cita_nombre || 'Consulta'
        ]);

        // AutoTable
        doc.autoTable({
            head: [columnas],
            body: filas,
            startY: 30,
            theme: 'grid',
            headStyles: {
                fillColor: [51, 65, 85], // slate-800
                textColor: 255,
                fontSize: 9,
                fontStyle: 'bold',
                halign: 'center'
            },
            bodyStyles: {
                fontSize: 8,
                textColor: [30, 41, 59]
            },
            columnStyles: {
                0: { halign: 'center', cellWidth: 15 },
                4: { halign: 'center', cellWidth: 25 },
                5: { halign: 'center', cellWidth: 20 },
                6: { halign: 'center', fontStyle: 'bold', cellWidth: 30 }
            },
            didParseCell: function(data) {
                // Colorear columna de Estado
                if (data.section === 'body' && data.column.index === 6) {
                    const estado = data.cell.raw;
                    if (estado === 'PENDIENTE') data.cell.styles.textColor = [245, 158, 11]; // Amber
                    else if (estado === 'COMPLETADA') data.cell.styles.textColor = [16, 185, 129]; // Emerald
                    else if (estado === 'CONFIRMADA') data.cell.styles.textColor = [85, 96, 255]; // Zooki Blue
                    else if (estado === 'CANCELADA') data.cell.styles.textColor = [239, 68, 68]; // Red
                }
            }
        });

        const fechaStr = new Date().toISOString().split('T')[0];
        doc.save(`Zooki_Reporte_Citas_${fechaStr}.pdf`);

        Swal.close();
        
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: 'PDF Exportado Correctamente' });

    } catch (error) {
        console.error('Error al generar PDF:', error);
        Swal.fire('Error', 'Hubo un problema al generar el PDF.', 'error');
    }
}
</script>

<style>
/* Mini KPIs Compactos */
.mini-kpis-container {
    display: flex;
    gap: 1.5rem;
    align-items: center;
}

.mini-kpi {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: transparent;
    border: none;
    padding: 0.2rem 0;
    position: relative;
    min-width: 85px;
    overflow: hidden;
}

.kpi-sparkline {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 45%;
    z-index: 0;
    pointer-events: none;
}

.mini-kpi-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
    text-shadow: 0 0 6px #f8fafc, 0 0 3px #ffffff;
}

.mini-kpi-value {
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1;
    color: #0052FF;
}

.mini-kpi-label {
    font-size: 0.65rem;
    color: #334155;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.3rem;
}

.kpi-divider {
    width: 1px;
    height: 30px;
    background: #e2e8f0;
}

/* Filtros Avanzados Card */
.card-filters {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    background: white;
    padding: 0.8rem 1rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    border: 1px solid rgba(0,0,0,0.04);
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: nowrap;
    overflow-x: auto;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}

.filter-group label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.filter-group select {
    padding: 0.35rem 1.8rem 0.35rem 0.8rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.85rem;
    color: var(--text-primary);
    background-color: #f8fafc;
    transition: all 0.2s;
    outline: none;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 1em;
}

.filter-group select:hover {
    border-color: #cbd5e1;
}

.filter-group select:focus {
    border-color: #0052FF;
    box-shadow: 0 0 0 3px rgba(0,82,255,0.1);
    background-color: white;
}

/* Filter Search */
.filter-search {
    display: flex;
    align-items: center;
    position: relative;
}

.filter-search i {
    position: absolute;
    left: 1rem;
    color: #94a3b8;
    font-size: 0.9rem;
}

.filter-search input {
    padding: 0.45rem 1rem 0.45rem 2.2rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.85rem;
    width: 200px;
    outline: none;
    transition: all 0.2s;
    background: #f8fafc;
}

.filter-search input:focus {
    border-color: #0052FF;
    box-shadow: 0 0 0 3px rgba(0,82,255,0.1);
    background: white;
}

.btn-clean {
    padding: 0.45rem 0.8rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    background: white;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    white-space: nowrap;
    margin-left: auto;
}

.btn-clean:hover {
    background: #f1f5f9;
    color: var(--text-primary);
}

/* Kanban Wrapper y Estructura */
.kanban-wrapper {
    width: 100%;
    overflow-x: auto;
    padding-bottom: 1rem;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

.kanban-wrapper::-webkit-scrollbar {
    display: none; /* Chrome, Safari and Opera */
}

.carousel-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: white;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    color: var(--primary);
    transition: all 0.2s;
}

.carousel-btn:hover {
    background: #f8fafc;
    box-shadow: 0 4px 15px rgba(0,82,255,0.15);
    color: #0052FF;
    border-color: rgba(0,82,255,0.3);
}

.carousel-btn.left {
    left: -15px;
}

.carousel-btn.right {
    right: -15px;
}

.kanban-board {
    display: flex;
    gap: 1.25rem;
    min-width: min-content;
    align-items: flex-start;
}

.kanban-col {
    background: #f8fafc;
    border-radius: 14px;
    width: 320px;
    min-width: 320px;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 280px);
    border: 1px solid rgba(0,0,0,0.04);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
}

.kb-col-header {
    padding: 1rem;
    background: white;
    border-radius: 14px 14px 0 0;
    border-bottom: 1px solid var(--border-color);
    position: sticky;
    top: 0;
    z-index: 2;
}

.kb-vet-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.kb-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(0,82,255,0.1);
    color: #0052FF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
}

.kb-vet-name {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 0.95rem;
    line-height: 1.2;
}

.kb-vet-count {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-top: 0.1rem;
}

.kb-col-body {
    padding: 1rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    flex: 1;
}

/* Kanban Cards (Estilo Clean) */
.kb-card {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.04);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}

.kb-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    border-color: #cbd5e1;
}

/* Premium Modal */
.premium-modal {
    border-radius: 16px !important;
    overflow: hidden !important;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1) !important;
    padding: 0 !important;
}
.premium-html-container {
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
}
.premium-close-btn {
    color: white !important;
    background: rgba(255,255,255,0.2) !important;
    border-radius: 50% !important;
    width: 32px !important;
    height: 32px !important;
    margin: 0 !important;
    position: absolute !important;
    top: 15px !important;
    right: 15px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: background 0.2s !important;
    z-index: 10 !important;
}
.premium-close-btn:focus {
    box-shadow: none !important;
}
.premium-close-btn:hover {
    background: rgba(255,255,255,0.3) !important;
    color: white !important;
}
.modal-info-box:hover {
    background: white !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.04) !important;
    border-color: #cbd5e1 !important;
}

.kb-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.25rem;
}

.kb-time {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.kb-date {
    font-weight: normal;
    color: var(--text-muted);
    font-size: 0.7rem;
}

.kb-status {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.kb-patient-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.kb-owner-name {
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.kb-reason {
    font-size: 0.7rem;
    color: var(--primary);
    background: rgba(0,82,255,0.05);
    padding: 0.3rem 0.5rem;
    border-radius: 6px;
    display: inline-block;
    align-self: flex-start;
    margin-top: 0.25rem;
    font-weight: 600;
}

.kb-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    color: #cbd5e1;
    text-align: center;
    gap: 0.5rem;
}
.kb-empty-state i {
    font-size: 2rem;
}
.kb-empty-state p {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.kanban-empty {
    width: 100%;
    text-align: center;
    padding: 3rem;
    color: var(--text-muted);
    font-size: 0.9rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px dashed #cbd5e1;
}

.swal-text-left {
    text-align: left !important;
}
</style>
