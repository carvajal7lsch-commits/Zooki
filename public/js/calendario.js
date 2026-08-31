// ═══════════════════════════════════════
// FilterManager — Filtros visuales por tipo de evento
// Requirements: 1.3, 1.4, 1.5, 1.6
// ═══════════════════════════════════════
const FilterManager = {
    // Estado interno: todos los tipos activos por defecto
    state: {
        cita: true,
        vacunacion: true,
        desparasitacion: true,
        veterinario: '' // Filtro por veterinario (vacío = todos)
    },

    /**
     * Inicializa el FilterManager con todos los filtros activos
     * y aplica el filtrado inicial sobre el calendario.
     * @param {FullCalendar.Calendar} calendar
     */
    init(calendar) {
        const vetDoc = isUsuarioVeterinario() ? getUsuarioDoc() : '';
        this.state = { cita: true, vacunacion: true, desparasitacion: true, veterinario: vetDoc };
        this.applyFilters(calendar);
    },

    /**
     * Activa o desactiva el filtro para el tipo dado y aplica el filtrado.
     * @param {string} tipo - 'cita' | 'vacunacion' | 'desparasitacion'
     */
    toggle(tipo) {
        if (tipo in this.state) {
            this.state[tipo] = !this.state[tipo];
        }

        if (calendarInstance) {
            this.applyFilters(calendarInstance);
        }
        // Actualizar card lateral si hay un día seleccionado
        actualizarCardConFiltros();
    },

    /**
     * Establece el filtro de veterinario y aplica el filtrado.
     * @param {string} veterinario - documento del veterinario (vacío = todos)
     */
    setVeterinario(veterinario) {
        this.state.veterinario = veterinario;
        if (calendarInstance) {
            this.applyFilters(calendarInstance);
        }
        // Actualizar card lateral si hay un día seleccionado
        actualizarCardConFiltros();
    },

    /**
     * Retorna true si el filtro para el tipo dado está activo.
     * @param {string} tipo
     * @returns {boolean}
     */
    isActive(tipo) {
        return this.state[tipo] === true;
    },

    /**
     * Retorna el veterinario seleccionado.
     * @returns {string}
     */
    getVeterinario() {
        return this.state.veterinario;
    },

    /**
     * Aplica el filtrado actual sobre todos los eventos del calendario,
     * ocultando o mostrando cada evento según el estado del filtro de su tipo.
     * @param {FullCalendar.Calendar} calendar
     */
    applyFilters(calendar) {
        if (!calendar) return;
        const events = calendar.getEvents();
        events.forEach(event => {
            const tipo = event.extendedProps.tipo;
            const veterinarioDoc = event.extendedProps.doc_veterinario || '';

            const tipoVisible = tipo ? (this.state[tipo] !== false) : true;
            const vetVisible = !this.state.veterinario || !veterinarioDoc || veterinarioDoc === this.state.veterinario;

            event.setProp('display', tipoVisible && vetVisible ? 'auto' : 'none');
        });
    },

    /**
     * Retorna la clase CSS correspondiente al tipo de evento.
     * @param {string} tipo - 'cita' | 'vacunacion' | 'desparasitacion'
     * @returns {string}
     */
    getColorClass(tipo, estado = null) {
        if (tipo === 'cita' && estado) {
            const estadoNorm = estado.toLowerCase();
            const mapEstado = {
                pendiente: 'event-cita-pendiente',
                confirmada: 'event-cita-confirmada',
                en_curso: 'event-cita-encurso',
                encurso: 'event-cita-encurso',
                completada: 'event-cita-completada',
                cancelada: 'event-cita-cancelada'
            };
            if (mapEstado[estadoNorm]) {
                return mapEstado[estadoNorm];
            }
        }

        const map = {
            cita:             'event-cita',
            vacunacion:       'event-vacunacion',
            desparasitacion:  'event-desparasitacion'
        };
        return map[tipo] || 'event-cita';
    }
};

function isUsuarioVeterinario() {
    return typeof USER_ROL !== 'undefined' && Number(USER_ROL) === 2;
}

function getUsuarioDoc() {
    return typeof USER_DOC !== 'undefined' ? USER_DOC : '';
}

// ═══════════════════════════════════════
// Drawer helpers
// ═══════════════════════════════════════
function openCitaDrawer() {
    const overlay = document.getElementById('citaDrawerOverlay');
    overlay.style.display = 'block';
    requestAnimationFrame(() => {
        overlay.style.opacity = '1';
        document.getElementById('citaDrawer').style.transform = 'translateX(0)';
    });
}

function closeCitaDrawer() {
    document.getElementById('citaDrawer').style.transform = 'translateX(100%)';
    const overlay = document.getElementById('citaDrawerOverlay');
    overlay.style.opacity = '0';
    setTimeout(() => { overlay.style.display = 'none'; }, 400);
}

function openDetalleCitaDrawer() {
    const overlay = document.getElementById('detalleCitaDrawerOverlay');
    overlay.style.display = 'block';
    requestAnimationFrame(() => {
        overlay.style.opacity = '1';
        document.getElementById('detalleCitaDrawer').style.transform = 'translateX(0)';
    });
}

function closeDetalleCitaDrawer() {
    document.getElementById('detalleCitaDrawer').style.transform = 'translateX(100%)';
    const overlay = document.getElementById('detalleCitaDrawerOverlay');
    overlay.style.opacity = '0';
    setTimeout(() => { overlay.style.display = 'none'; }, 400);
}

// ═══════════════════════════════════════
// Nueva cita — abrir drawer con fecha
// ═══════════════════════════════════════
function toLocalDateStr(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function abrirDrawerNuevaCita(date) {
    const fechaStr = toLocalDateStr(date);
    const d = new Date(fechaStr + 'T12:00:00');
    const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('drawerFechaLabel').textContent = d.toLocaleDateString('es-CO', opciones);
    document.getElementById('formCrearCitaDrawer').reset();
    document.getElementById('crear_fecha').value = fechaStr;

    const crearVetInput = document.getElementById('crear_veterinario');
    const filtroVet = document.getElementById('filtro_veterinario') || document.getElementById('filterVeterinario');
    if (crearVetInput) {
        if (filtroVet && !isUsuarioVeterinario()) {
            crearVetInput.value = filtroVet.value || '';
        } else {
            crearVetInput.value = getUsuarioDoc();
        }
    }

    const filtroMascota = document.getElementById('filtro_mascota');
    if (filtroMascota) {
        document.getElementById('crear_mascota').value = filtroMascota.value;
    }

    const filtroTipoCita = document.getElementById('filtro_tipo_cita');
    if (filtroTipoCita) {
        document.getElementById('crear_tipo_cita').value = filtroTipoCita.value;
    }

    const filtroMotivo = document.getElementById('filtro_motivo');
    if (filtroMotivo) {
        document.getElementById('crear_motivo').value = filtroMotivo.value;
    }
    
    // Copiar duración del tipo de cita
    const tipoSelect = document.getElementById('filtro_tipo_cita');
    if (tipoSelect.value) {
        const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
        document.getElementById('crear_duracion_minutos').value = selectedOption.dataset.duracion;
    }
    
    // Limpiar contenedores
    document.getElementById('sugerencias_horario').style.display = 'none';
    openCitaDrawer();
}

function getMonthRowHeight() {
    const v = getComputedStyle(document.documentElement).getPropertyValue('--cal-day-row-height').trim();
    return parseInt(v, 10) || 96;
}

function getMonthVisibleRows() {
    const v = getComputedStyle(document.documentElement).getPropertyValue('--cal-visible-rows').trim();
    return parseInt(v, 10) || 4;
}

/** Altura del viewport visible: solo 4 filas (el mes completo scrollea dentro) */
function getMonthViewportExtra() {
    const v = getComputedStyle(document.documentElement).getPropertyValue('--cal-viewport-extra').trim();
    return parseInt(v, 10) || 28;
}

function getMonthContentHeight() {
    return getMonthRowHeight() * getMonthVisibleRows() + getMonthViewportExtra();
}

/** height:auto anula contentHeight en FullCalendar; solo vista mes usa altura fija */
function syncMonthViewport(viewType) {
    if (!calendarInstance) return;
    const container = document.querySelector('.calendar-container');
    if (container) {
        container.classList.toggle('is-month-view', viewType === 'dayGridMonth');
    }
    calendarInstance.setOption('height', 'auto');
    calendarInstance.setOption('contentHeight', 'auto');
}

async function iniciarAtencionCita(idCita, options = {}) {
    const form = new URLSearchParams({ id_cita: idCita });
    try {
        const res = await fetch('index.php?action=iniciar_cita_ajax', { method: 'POST', body: form });
        const text = await res.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (parseErr) {
            console.error('Respuesta no es JSON:', text);
            Swal.fire({ title: 'Error del servidor', text: 'La respuesta no es JSON válido. Revisa la consola (F12).', icon: 'error', confirmButtonColor: '#0C66E4' });
            return;
        }
        if (result.success) {
            if (options.context !== 'drawer') {
                mostrarToast('Atención iniciada.', 'success');
            }
            if (calendarInstance) calendarInstance.refetchEvents();
            if (_selectedDate) cargarEventosDelDia(_selectedDate);

            // Redirigir a pantalla integral de atención
            if (result.redirect_url) {
                window.location.href = result.redirect_url;
                return;
            }

            closeDetalleCitaDrawer();
        } else {
            Swal.fire({ title: 'Error', text: result.message, icon: 'error', confirmButtonColor: '#0C66E4' });
        }
    } catch (e) {
        console.error(e);
        Swal.fire({ title: 'Error', text: 'No se pudo iniciar la atención.', icon: 'error', confirmButtonColor: '#0C66E4' });
    }
}

async function completarCita(idCita, options = {}) {
    const form = new URLSearchParams({ id_cita: idCita });
    try {
        const res = await fetch('index.php?action=completar_cita_ajax', { method: 'POST', body: form });
        const result = await res.json();
        if (result.success) {
            if (options.context !== 'drawer') {
                mostrarToast('Cita marcada como completada.', 'success');
            }
            if (calendarInstance) calendarInstance.refetchEvents();
            if (_selectedDate) cargarEventosDelDia(_selectedDate);
            closeDetalleCitaDrawer();
        } else {
            Swal.fire({ title: 'Error', text: result.message, icon: 'error', confirmButtonColor: '#0C66E4' });
        }
    } catch (e) {
        console.error(e);
        Swal.fire({ title: 'Error', text: 'No se pudo completar la cita.', icon: 'error', confirmButtonColor: '#0C66E4' });
    }
}

function applyMonthScrollLayout() {
    if (!calendarInstance || calendarInstance.view.type !== 'dayGridMonth') return;

    calendarInstance.setOption('height', 'auto');
    calendarInstance.setOption('contentHeight', 'auto');

    const root = document.querySelector('.calendar-container');
    if (!root) return;

    /* Quitar alturas fijas en harness/scroller que bloqueaban el scroll hasta el último día */
    root.querySelectorAll('.fc-scrollgrid-section-body .fc-scroller-harness').forEach(el => {
        el.style.removeProperty('height');
        el.style.removeProperty('max-height');
        el.style.removeProperty('min-height');
        el.style.removeProperty('overflow');
    });
    root.querySelectorAll('.fc-scrollgrid-section-body .fc-scroller').forEach(el => {
        el.style.removeProperty('max-height');
        el.style.removeProperty('min-height');
        el.style.setProperty('overflow-y', 'visible', 'important');
        el.style.setProperty('overflow-x', 'visible', 'important');
    });

    const oldOverride = document.getElementById('cal-month-scroll-override');
    if (oldOverride) oldOverride.remove();

    calendarInstance.updateSize();
}

function mountDayAddButton(arg) {
    if (arg.view.type !== 'dayGridMonth') return;
    const top = arg.el.querySelector('.fc-daygrid-day-top');
    if (!top || top.querySelector('.fc-day-add-btn')) return;

    // No mostrar botón + para días pasados
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const dayDate = new Date(arg.date);
    dayDate.setHours(0, 0, 0, 0);
    
    if (dayDate < today) return;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'fc-day-add-btn';
    btn.setAttribute('aria-label', 'Agregar cita');
    btn.innerHTML = '<i class="fas fa-plus"></i>';
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        if (calendarInstance) calendarInstance.unselect();
        abrirCitaModal(arg.date);
    });
    top.appendChild(btn);
}

// ═══════════════════════════════════════
// Cargar selects en filtros
// ═══════════════════════════════════════
async function cargarSelects() {
    try {
        const vetsRes = await fetch('index.php?action=listar_veterinarios_ajax');
        const vets = await vetsRes.json();
        const esVet = isUsuarioVeterinario();
        const docUsuario = getUsuarioDoc();
        const vetSelects = document.querySelectorAll('#filtro_veterinario, #crear_veterinario, #reprog_veterinario, #filterVeterinario');
        vetSelects.forEach(sel => {
            if (!sel) return;

            if (sel.tagName !== 'SELECT') {
                if (esVet && sel.tagName === 'INPUT' && sel.id === 'crear_veterinario') {
                    sel.value = docUsuario;
                }
                return;
            }

            if (esVet) {
                const match = vets.find(v => v.documento === docUsuario);
                sel.innerHTML = '';
                const opt = document.createElement('option');
                opt.value = docUsuario;
                opt.textContent = match ? match.nombre_completo : 'Mi agenda';
                sel.appendChild(opt);
                sel.value = docUsuario;
                sel.disabled = true;
            } else {
                const isFilter = sel.id === 'filterVeterinario';
                sel.innerHTML = isFilter ? '<option value="">Todos</option>' : '<option value="">Seleccione veterinario...</option>';
                vets.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.documento;
                    opt.textContent = v.nombre_completo;
                    sel.appendChild(opt);
                });
            }
        });

        if (esVet) {
            FilterManager.setVeterinario(docUsuario);
        }
    } catch (e) {
        console.error('Error al cargar veterinarios:', e);
    }

    try {
        const mascRes = await fetch('index.php?action=listar_mascotas_ajax');
        const masc = await mascRes.json();
        console.log('Mascotas obtenidas:', masc);
        const mascSelects = document.querySelectorAll('#filtro_mascota, #crear_mascota');
        mascSelects.forEach(sel => {
            if (sel) {
                sel.innerHTML = '<option value="">Seleccione mascota...</option>';
                masc.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id_mascota;
                    opt.textContent = m.nombre;
                    sel.appendChild(opt);
                });
            }
        });
    } catch (e) {
        console.error('Error al cargar mascotas:', e);
    }

    // Cargar tipos de cita
    try {
        const tiposRes = await fetch('index.php?action=listar_tipos_cita_ajax');
        const responseText = await tiposRes.text();
        console.log('Respuesta del servidor (tipos):', responseText);

        if (!responseText) {
            console.error('La respuesta está vacía');
            return;
        }

        const data = JSON.parse(responseText);
        if (!data.success || !Array.isArray(data.tipos)) {
            console.error('Error en la respuesta:', data);
            return;
        }

        const tipos = data.tipos;
        const tipoSelects = document.querySelectorAll('#filtro_tipo_cita, #crear_tipo_cita, #modal_tipo_cita');
        tipoSelects.forEach(sel => {
            if (sel) {
                sel.innerHTML = '<option value="">Seleccione tipo de cita...</option>';
                tipos.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id_tipo_cita;
                    opt.textContent = `${t.nombre} (${t.duracion_minutos} min)`;
                    opt.dataset.duracion = t.duracion_minutos;
                    opt.dataset.color = t.color || '#0C66E4';
                    sel.appendChild(opt);
                });
            }
        });
    } catch (e) {
        console.error('Error al cargar tipos de cita:', e);
    }
}

// ═══════════════════════════════════════
// Funciones para gestión de filtros
// ═══════════════════════════════════════
const modoAgendamiento = 'normal';

function actualizarDuracionFiltro() {
    const tipoSelect = document.getElementById('filtro_tipo_cita');
    const duracionInfo = document.getElementById('duracion_filtro_info');
    const duracionMinutos = document.getElementById('duracion_filtro_minutos');
    
    if (tipoSelect.value) {
        const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
        const duracion = selectedOption.dataset.duracion;
        duracionMinutos.textContent = duracion;
        duracionInfo.style.display = 'block';
    } else {
        duracionInfo.style.display = 'none';
    }
}

function actualizarDuracionCita() {
    const tipoSelect = document.getElementById('crear_tipo_cita');
    const duracionInfo = document.getElementById('duracion_info');
    const duracionMinutos = document.getElementById('duracion_minutos');
    
    if (tipoSelect.value) {
        const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
        const duracion = selectedOption.dataset.duracion;
        duracionMinutos.textContent = duracion;
        duracionInfo.style.display = 'block';
    } else {
        duracionInfo.style.display = 'none';
    }
}



async function cargarSugerenciasHorario() {
    const veterinario = document.getElementById('crear_veterinario').value;
    const fecha = document.getElementById('crear_fecha').value;
    const tipoCita = document.getElementById('crear_tipo_cita').value;
    const duracion = document.getElementById('crear_duracion_minutos').value;
    
    if (!veterinario || !fecha || !tipoCita || !duracion) {
        alert('Por favor, seleccione veterinario, mascota, tipo de cita y motivo en los filtros arriba del calendario primero.');
        return;
    }
    
    try {
        const res = await fetch(`index.php?action=get_sugerencias_horario_ajax&doc_veterinario=${veterinario}&fecha=${fecha}&duracion_minutos=${duracion}&modo=${modoAgendamiento}`);
        const data = await res.json();
        
        const sugerenciasContainer = document.getElementById('sugerencias_container');
        const sugerenciasDiv = document.getElementById('sugerencias_horario');
        
        if (data.success && data.sugerencias.length > 0) {
            sugerenciasContainer.innerHTML = '';
            data.sugerencias.forEach(hora => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = hora;
                btn.style.cssText = 'padding: 0.4rem 0.8rem; background: #E9F2FF; color: #0C66E4; border: 1px solid #579DFF; border-radius: 6px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s;';
                btn.onmouseover = function() { this.style.background = '#0C66E4'; this.style.color = 'white'; };
                btn.onmouseout = function() { this.style.background = '#E9F2FF'; this.style.color = '#0C66E4'; };
                btn.onclick = function() {
                    document.getElementById('crear_hora').value = hora;
                };
                sugerenciasContainer.appendChild(btn);
            });
            sugerenciasDiv.style.display = 'block';
        } else {
            sugerenciasContainer.innerHTML = '<span style="color: #626F86; font-size: 0.8rem;">No hay horarios disponibles para este tipo de cita en la fecha seleccionada.</span>';
            sugerenciasDiv.style.display = 'block';
        }
    } catch (e) {
        console.error('Error al cargar sugerencias:', e);
        alert('Error al cargar sugerencias de horario.');
    }
}

// ═══════════════════════════════════════
// Card fijo - Eventos del día
// ═══════════════════════════════════════
let _selectedDate = null;

function cargarEventosDelDia(date) {
    _selectedDate = date;
    const fechaStr = toLocalDateStr(date);
    const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const fechaDisplay = new Date(fechaStr + 'T12:00:00').toLocaleDateString('es-CO', opciones);

    // Verificar si es un día pasado
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const fechaSeleccionada = new Date(date);
    fechaSeleccionada.setHours(0, 0, 0, 0);
    const esDiaPasado = fechaSeleccionada < hoy;

    // Actualizar título
    const titleEl = document.getElementById('dayEventsTitle');
    if (titleEl) {
        titleEl.textContent = 'Detalle del D\u00eda';
    }
    const subEl = document.getElementById('dayEventsDateSub');
    if (subEl) {
        subEl.textContent = fechaDisplay.charAt(0).toUpperCase() + fechaDisplay.slice(1);
    }

    // Mostrar/ocultar botón de agendar cita según si es día pasado
    const btnAgendar = document.getElementById('btnAgendarCita');
    if (btnAgendar) {
        btnAgendar.style.display = esDiaPasado ? 'none' : 'flex';
    }

    // Obtener todos los eventos del calendario para esa fecha
    if (!calendarInstance) return;

    const allEvents = calendarInstance.getEvents();
    const dayEvents = allEvents.filter(ev => {
        const eventDate = ev.start ? toLocalDateStr(ev.start) : '';
        return eventDate === fechaStr;
    });

    // Filtrar según los filtros activos
    const filteredEvents = dayEvents.filter(ev => {
        const tipo = ev.extendedProps.tipo || 'cita';
        const tipoVisible = FilterManager.isActive(tipo);

        const vetSeleccionado = FilterManager.getVeterinario();
        const vetDocEvento = ev.extendedProps.doc_veterinario || '';
        const vetVisible = !vetSeleccionado || !vetDocEvento || vetDocEvento === vetSeleccionado;

        return tipoVisible && vetVisible;
    });

    // Ordenar por hora
    filteredEvents.sort((a, b) => {
        const ta = a.start ? a.start.getHours() * 60 + a.start.getMinutes() : 0;
        const tb = b.start ? b.start.getHours() * 60 + b.start.getMinutes() : 0;
        return ta - tb;
    });

    // Renderizar eventos
    const listEl = document.getElementById('dayEventsList');
    if (!listEl) return;

    if (filteredEvents.length === 0) {
        let emptyHtml = esDiaPasado 
            ? '<p class="day-events-empty">No hay eventos para este día</p>'
            : '<p class="day-events-empty">No hay eventos para este día con los filtros actuales</p>';
        
        // Aún así mostrar el recordatorio al final (Oculto temporalmente)
        /*
        emptyHtml += `
            <div class="recordatorio-card">
                <div class="recordatorio-overlay">
                    <span class="recordatorio-tag">Recordatorio</span>
                    <p class="recordatorio-text">Preparar reporte semanal de cirugías.</p>
                </div>
            </div>
        `;
        */
        listEl.innerHTML = emptyHtml;
    } else {
        const citas = filteredEvents.filter(ev => (ev.extendedProps.tipo || 'cita') === 'cita');
        const otros = filteredEvents.filter(ev => (ev.extendedProps.tipo || 'cita') !== 'cita');
        
        let html = '';
        
        // Función helper para renderizar un item
        const renderItem = (ev) => {
            const tipo = ev.extendedProps.tipo || 'cita';
            const hora = ev.start ? ev.start.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }) : '--:--';

            let mascotaRaw = ev.extendedProps.mascota_nombre || ev.title;
            if (mascotaRaw && mascotaRaw.includes(' \u2014 ')) mascotaRaw = mascotaRaw.split(' \u2014 ')[0];
            else if (mascotaRaw && mascotaRaw.includes(' - ')) mascotaRaw = mascotaRaw.split(' - ')[0];

            let mascotaName = mascotaRaw;
            const razaMatch = mascotaRaw.match(/(.+?)\s*\((.+?)\)/);
            if (razaMatch) { mascotaName = razaMatch[1].trim(); }

            const motivo = ev.extendedProps.motivo || 'Consulta General';
            const estadoRaw = (ev.extendedProps.estado || '').toLowerCase();
            const estadoLabelMap = {
                pendiente: 'Pendiente', confirmada: 'Confirmada',
                en_curso: 'En curso', encurso: 'En curso',
                completada: 'Completada', cancelada: 'Cancelada'
            };
            const estadoLabel = estadoLabelMap[estadoRaw] || (estadoRaw ? estadoRaw : 'Pendiente');

            // Badge de estado a la derecha
            let rightHtml = `<span class="day-event-type ${tipo}">${estadoLabel}</span>`;
            if (tipo !== 'cita') {
                rightHtml = `<span class="day-event-time-right">${hora}</span>`;
            }

            // Botón "Iniciar" inline — solo para citas pendiente/confirmada no pasadas
            const puedeIniciar = tipo === 'cita'
                && !['en_curso', 'encurso', 'completada', 'cancelada'].includes(estadoRaw)
                && !esDiaPasado;
            const iniciarBtn = puedeIniciar
                ? `<button class="day-event-iniciar-btn" onclick="event.stopPropagation(); iniciarAtencionCita('${ev.id}', {context:'card'})" title="Iniciar atención"><i class="fas fa-play"></i></button>`
                : '';

            return `
                <div class="day-event-item tipo-${tipo} ${estadoRaw ? `estado-${estadoRaw}` : ''}" onclick="mostrarPopoverDesdeCard('${ev.id}')">
                    <div class="day-event-body">
                        <div class="day-event-item-header">
                            <span class="day-event-time">${hora}</span>
                            ${rightHtml}
                            ${iniciarBtn}
                        </div>
                        <h4 class="day-event-title">${mascotaName}</h4>
                        <div class="day-event-details">
                            <div class="day-event-detail"><span>${motivo}</span></div>
                        </div>
                    </div>
                </div>
            `;
        };

        if (citas.length > 0) {
            html += `<div class="day-events-section-label"><span class="dot-section blue"></span> CITAS PROGRAMADAS</div>`;
            html += citas.map(renderItem).join('');
        }
        
        if (otros.length > 0) {
            html += `<div class="day-events-section-label"><span class="dot-section green"></span> OTROS EVENTOS</div>`;
            html += otros.map(renderItem).join('');
        }
        
        // Banner Recordatorio (Oculto temporalmente)
        /*
        html += `
            <div class="recordatorio-card">
                <div class="recordatorio-overlay">
                    <span class="recordatorio-tag">Recordatorio</span>
                    <p class="recordatorio-text">Preparar reporte semanal de cirugías.</p>
                </div>
            </div>
        `;
        */
        
        listEl.innerHTML = html;
    }
}

function actualizarCardConFiltros() {
    if (_selectedDate) {
        cargarEventosDelDia(_selectedDate);
    }
}

function abrirCitaModalDesdeCard() {
    if (_selectedDate) {
        abrirCitaModal(_selectedDate);
    }
}

function mostrarPopoverDesdeCard(eventId) {
    const event = calendarInstance.getEventById(eventId);
    if (!event) return;

    const props = event.extendedProps;
    const tipo = props.tipo || 'cita';
    const tipoLabel = tipo === 'cita' ? 'Cita' : (tipo === 'vacunacion' ? 'Vacunación' : 'Desparasitación');
    const hora = event.start ? event.start.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }) : '--:--';
    const fecha = event.start ? event.start.toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : 'Sin fecha';
    const mascota = props.mascota_nombre || '';
    const propietario = props.propietario_nombre || '';
    const veterinario = props.veterinario || '';
    const motivo = props.motivo || '';
    const estadoRaw = (props.estado || '').toLowerCase();

    // Verificar si es una cita pasada
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const fechaEvento = new Date(event.start);
    fechaEvento.setHours(0, 0, 0, 0);
    const esCitaPasada = fechaEvento < hoy;

    // Actualizar título del card
    const titleEl = document.getElementById('dayEventsTitle');
    if (titleEl) {
        titleEl.textContent = 'Detalle de la cita';
    }

    // Renderizar detalle en el card
    const listEl = document.getElementById('dayEventsList');
    if (!listEl) return;

    // Solo mostrar botones de acción para citas (no para vacunaciones/desparasitaciones)
    // y solo si no es una cita pasada
    const docEvento = props.doc_veterinario || '';
    const userRol = typeof USER_ROL !== 'undefined' ? Number(USER_ROL) : null;
    const esVeterinarioSesion = isUsuarioVeterinario() && docEvento && docEvento === getUsuarioDoc();
    const esStaff = userRol === 1 || userRol === 3;
    const puedeGestionar = tipo === 'cita' && !esCitaPasada && (esVeterinarioSesion || esStaff);
    const estadoBadgeMap = {
        pendiente:  { label: 'Pendiente',  color: '#946F00', bg: '#FFF7D6', icon: 'fa-clock' },
        confirmada: { label: 'Confirmada', color: '#006644', bg: '#E3FCEF', icon: 'fa-check-circle' },
        en_curso:   { label: 'En curso',   color: '#0F6EDE', bg: '#E0F2FE', icon: 'fa-play-circle' },
        encurso:    { label: 'En curso',   color: '#0F6EDE', bg: '#E0F2FE', icon: 'fa-play-circle' },
        completada: { label: 'Completada', color: '#15803D', bg: '#F0FDF4', icon: 'fa-check' },
        cancelada:  { label: 'Cancelada',  color: '#C2410C', bg: '#FFEDE9', icon: 'fa-times-circle' }
    };
    const estadoBadge = estadoBadgeMap[estadoRaw] || { label: estadoRaw || 'Pendiente', color: '#946F00', bg: '#FFF7D6', icon: 'fa-clock' };

    const puedeConfirmar = puedeGestionar && estadoRaw === 'pendiente';
    const puedeIniciar = puedeGestionar && !['en_curso', 'encurso', 'cancelada', 'completada'].includes(estadoRaw);
    const puedeCompletar = puedeGestionar && estadoRaw === 'en_curso';
    const puedeReprogramar = puedeGestionar && !['completada', 'cancelada'].includes(estadoRaw);
    const puedeCancelar = puedeGestionar && !['completada', 'cancelada'].includes(estadoRaw);
    const mostrarAcciones = puedeGestionar && !['completada', 'cancelada'].includes(estadoRaw);

    listEl.innerHTML = `
        <div class="cita-detalle-v2">
            <!-- Header con badge de estado -->
            <div class="cdv2-header">
                <div class="cdv2-pet-icon">
                    <i class="fas fa-paw"></i>
                </div>
                <div class="cdv2-header-info">
                    <h4 class="cdv2-pet-name">${mascota || 'Paciente'}</h4>
                    <span class="cdv2-owner">${propietario || ''}</span>
                </div>
                <span class="cdv2-estado-badge" style="background:${estadoBadge.bg};color:${estadoBadge.color};">
                    <i class="fas ${estadoBadge.icon}"></i> ${estadoBadge.label}
                </span>
            </div>

            <!-- Info rows -->
            <div class="cdv2-info-rows">
                <div class="cdv2-info-row">
                    <i class="far fa-clock"></i>
                    <span>${hora} &nbsp;·&nbsp; <span style="text-transform:capitalize;">${fecha.split(',').slice(0,2).join(',')}</span></span>
                </div>
                ${veterinario ? `<div class="cdv2-info-row">
                    <i class="fas fa-user-md"></i>
                    <span>${veterinario}</span>
                </div>` : ''}
                ${motivo ? `<div class="cdv2-info-row">
                    <i class="fas fa-stethoscope"></i>
                    <span>${motivo}</span>
                </div>` : ''}
                <div class="cdv2-info-row">
                    <i class="fas fa-tag"></i>
                    <span>${tipoLabel}</span>
                </div>
            </div>

            <!-- Acciones -->
            ${mostrarAcciones ? `
            <div class="cdv2-actions">
                ${puedeIniciar ? `
                <button class="cdv2-btn cdv2-btn-primary" onclick="iniciarAtencionDesdeCard('${eventId}')">
                    <i class="fas fa-play-circle"></i> Iniciar atención
                </button>` : ''}
                ${puedeConfirmar ? `
                <button class="cdv2-btn cdv2-btn-confirm" onclick="confirmarCita('${eventId}')">
                    <i class="fas fa-check"></i> Confirmar
                </button>` : ''}
                ${puedeCompletar ? `
                <button class="cdv2-btn cdv2-btn-complete" onclick="completarCitaDesdeCard('${eventId}')">
                    <i class="fas fa-check-circle"></i> Atendida
                </button>` : ''}
                <div class="cdv2-actions-row2">
                    ${puedeReprogramar ? `
                    <button class="cdv2-btn cdv2-btn-secondary" onclick="abrirModalReprogramarDesdeCard('${eventId}')">
                        <i class="fas fa-calendar-alt"></i> Reprogramar
                    </button>` : ''}
                    ${puedeCancelar ? `
                    <button class="cdv2-btn cdv2-btn-danger" onclick="cancelarCitaDesdeCard('${eventId}')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>` : ''}
                </div>
            </div>` : `
            <div class="cdv2-estado-aviso" style="background:${estadoBadge.bg};color:${estadoBadge.color};">
                <i class="fas ${estadoBadge.icon}"></i>
                ${estadoRaw === 'cancelada' ? 'Esta cita fue cancelada.' : estadoRaw === 'completada' ? 'Cita ya atendida.' : esCitaPasada ? 'Fecha caducada.' : 'Sin permisos para gestionar.'}
            </div>`}

            <!-- Volver -->
            <button class="cdv2-btn-volver" onclick="cargarEventosDelDia(_selectedDate)">
                <i class="fas fa-arrow-left"></i> Volver
            </button>
        </div>
    `;

    // Ocultar botón de agendar cita en vista de detalle
    const btnAgendar = document.getElementById('btnAgendarCita');
    if (btnAgendar) {
        btnAgendar.style.display = 'none';
    }
}

function abrirModalReprogramarDesdeCard(eventId) {
    const event = calendarInstance.getEventById(eventId);
    if (event) {
        abrirModalReprogramar(eventId);
    }
}

function iniciarAtencionDesdeCard(eventId) {
    iniciarAtencionCita(eventId, { context: 'card' });
}

function completarCitaDesdeCard(eventId) {
    completarCita(eventId, { context: 'card' });
}

function cancelarCitaDesdeCard(eventId) {
    Swal.fire({
        title: '¿Cancelar cita?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, mantener'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('index.php?action=cancelar_cita_ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id_cita=${eventId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    mostrarToast('Cita cancelada correctamente', 'success');
                    cargarEventosDelDia(_selectedDate);
                    calendarInstance.refetchEvents();
                    if (data.id_cita) {
                        fetch('index.php?action=enviar_email_ajax', {
                            method: 'POST',
                            body: new URLSearchParams({ id_cita: data.id_cita, tipo: 'cancelacion' })
                        }).catch(e => console.error(e));
                    }
                } else {
                    mostrarToast(data.message || 'Error al cancelar la cita', 'error');
                }
            })
            .catch(() => {
                mostrarToast('Error de conexión al cancelar la cita', 'error');
            });
        }
    });
}

// ═══════════════════════════════════════
// Calendar instance
// ═══════════════════════════════════════
let calendarInstance;

document.addEventListener('DOMContentLoaded', function() {
    cargarSelects();
    const calendarEl = document.getElementById('calendar');
    calendarInstance = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        selectable: false,
        expandRows: false,
        editable: typeof USER_ROL !== 'undefined' && USER_ROL === 1,
        eventStartEditable: typeof USER_ROL !== 'undefined' && USER_ROL === 1,
        headerToolbar: {
            left: 'today prev,next',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        height: 'auto',
        contentHeight: 'auto',
        views: {
            dayGridMonth: {
                expandRows: false
            }
        },
        dayMaxEvents: 2,
        dayMaxEventRows: 2,
        fixedWeekCount: false,
        firstDay: 1,
        dayCellDidMount: mountDayAddButton,
        dateClick: function(info) {
            // Verificar si es un día pasado
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const fechaSeleccionada = new Date(info.date);
            fechaSeleccionada.setHours(0, 0, 0, 0);

            if (fechaSeleccionada < hoy) {
                // Comprobar si hay eventos en ese día pasado
                const fechaStr = toLocalDateStr(info.date);
                const allEvents = calendarInstance ? calendarInstance.getEvents() : [];
                const tieneEventos = allEvents.some(ev => {
                    const eventDate = ev.start ? toLocalDateStr(ev.start) : '';
                    return eventDate === fechaStr;
                });

                if (!tieneEventos) {
                    // Sin eventos: informar sutilmente y salir
                    mostrarToast('No hay citas registradas en este día', 'info');
                    const cell = info.dayEl;
                    cell.classList.add('day-past-error');
                    setTimeout(() => cell.classList.remove('day-past-error'), 1500);
                    return;
                }

                // Tiene eventos: seleccionar el día normalmente (solo lectura)
                document.querySelectorAll('.fc-day-selected').forEach(el => el.classList.remove('fc-day-selected'));
                info.dayEl.classList.add('fc-day-selected');
                cargarEventosDelDia(info.date);
                return;
            }

            // Día futuro o hoy: selección normal
            document.querySelectorAll('.fc-day-selected').forEach(el => {
                el.classList.remove('fc-day-selected');
            });
            info.dayEl.classList.add('fc-day-selected');

            // Cargar eventos del día en el card lateral
            cargarEventosDelDia(info.date);
        },
        viewDidMount: function(info) {
            syncMonthViewport(info.view.type);
            if (info.view.type === 'dayGridMonth') {
                requestAnimationFrame(() => requestAnimationFrame(applyMonthScrollLayout));
            }
        },
        datesSet: function(info) {
            // Actualizar título personalizado
            const customTitle = document.getElementById('calCustomTitle');
            if (customTitle) {
                // Capitalizar primera letra (ej. "octubre de 2023" -> "Octubre 2023")
                let text = info.view.title;
                text = text.replace(' de ', ' ');
                customTitle.textContent = text.charAt(0).toUpperCase() + text.slice(1);
            }

            syncMonthViewport(info.view.type);
            if (info.view.type === 'dayGridMonth') {
                requestAnimationFrame(() => requestAnimationFrame(applyMonthScrollLayout));
            }
        },
        eventsSet: function() {
            if (calendarInstance && calendarInstance.view.type === 'dayGridMonth') {
                applyMonthScrollLayout();
            }
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día'
        },
        moreLinkText: 'más',
        moreLinkClick: 'popover',
        events: async function(info, successCallback, failureCallback) {
            try {
                const res = await fetch(`index.php?action=listar_citas_ajax&inicio=${info.startStr.split('T')[0]}&fin=${info.endStr.split('T')[0]}`);
                const eventos = await res.json();
                const events = eventos.map(e => {
                    const tipo = e.tipo || 'cita';
                    const hora = e.hora || '08:00';
                    const estadoEvento = (e.estado || '').toLowerCase();

                    const extendedProps = {
                        veterinario: e.veterinario_nombre,
                        doc_veterinario: e.doc_veterinario,
                        motivo: e.motivo,
                        estado: e.estado,
                        mascotaId: e.id_mascota,
                        mascota_nombre: e.mascota_nombre,
                        propietario_nombre: e.propietario_nombre,
                        tipo: tipo
                    };

                    const classNames = [];
                    if (tipo === 'cita') {
                        classNames.push(FilterManager.getColorClass('cita', estadoEvento));
                        if (estadoEvento) {
                            classNames.push(`estado-${estadoEvento}`);
                        }
                    } else if (tipo === 'vacunacion') {
                        classNames.push('event-vacunacion');
                    } else if (tipo === 'desparasitacion') {
                        classNames.push('event-desparasitacion');
                    } else {
                        classNames.push(FilterManager.getColorClass(tipo));
                    }

                    return {
                        id: e.id_cita,
                        title: `${e.mascota_nombre} (${e.propietario_nombre})`,
                        start: `${e.fecha}T${hora}`,
                        extendedProps,
                        className: classNames
                    };
                });
                successCallback(events);
            } catch(e) {
                failureCallback(e);
            }
        },
        // Aplicar clase de color por tipo de evento
        eventDidMount: function(info) {
            const tipo = info.event.extendedProps.tipo;
            const estado = info.event.extendedProps.estado;
            if (tipo) {
                info.el.classList.add(FilterManager.getColorClass(tipo, estado));
                if (tipo === 'cita' && estado) {
                    info.el.dataset.estadoCita = estado;
                }
            }
        },
        // Drag & drop — reprogramar cita al soltar
        eventDrop: function(info) {
            const id_cita = info.event.id;
            const nueva_fecha = info.event.startStr.split('T')[0];
            const nueva_hora = info.event.startStr.includes('T')
                ? info.event.startStr.split('T')[1].substring(0, 5)
                : (info.event.extendedProps.hora || '08:00');

            fetch('index.php?action=reprogramar_cita_ajax', {
                method: 'POST',
                body: new URLSearchParams({ id_cita, fecha: nueva_fecha, hora: nueva_hora })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    info.revert();
                    mostrarToast(data.message || 'El horario seleccionado no está disponible para ese veterinario.', 'error');
                } else {
                    mostrarToast('Cita reprogramada correctamente.', 'success');
                    if (data.id_cita) {
                        fetch('index.php?action=enviar_email_ajax', {
                            method: 'POST',
                            body: new URLSearchParams({ id_cita: data.id_cita, tipo: 'reprogramacion' })
                        }).catch(e => console.error(e));
                    }
                }
            })
            .catch(() => {
                info.revert();
                mostrarToast('Error de conexión al reprogramar la cita.', 'error');
            });
        },
        // Click en un evento → cargar eventos del día en el card lateral
        eventClick: function(info) {
            cargarEventosDelDia(info.event.start);
        }
    });
    calendarInstance.render();
    syncMonthViewport('dayGridMonth');
    applyMonthScrollLayout();

    // ── FilterManager: inicializar y conectar botones ──
    FilterManager.init(calendarInstance);
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tipo = this.dataset.tipo;
            FilterManager.toggle(tipo);
            this.classList.toggle('active', FilterManager.isActive(tipo));
        });
    });

    // Conectar select de veterinario con FilterManager
    const filterVetSelect = document.getElementById('filterVeterinario');
    if (filterVetSelect) {
        filterVetSelect.addEventListener('change', function() {
            FilterManager.setVeterinario(this.value);
        });
    }

    window.addEventListener('resize', function() {
        if (calendarInstance && calendarInstance.view.type === 'dayGridMonth') {
            applyMonthScrollLayout();
        }
    });
});

// ═══════════════════════════════════════
// Crear cita
// ═══════════════════════════════════════
async function crearCita(e) {
    e.preventDefault();
    const hora = document.getElementById('crear_hora').value;
    if (!hora) {
        Swal.fire({
            icon: 'warning',
            title: 'Hora requerida',
            text: 'Debes seleccionar un horario disponible antes de crear la cita. Haz clic en "Ver horarios disponibles" y elige un slot.',
            confirmButtonColor: '#0C66E4'
        });
        return;
    }
    const data = {
        id_mascota: document.getElementById('crear_mascota').value,
        doc_veterinario: document.getElementById('crear_veterinario').value,
        fecha: document.getElementById('crear_fecha').value,
        hora: hora,
        motivo: document.getElementById('crear_motivo').value,
        id_tipo_cita: document.getElementById('crear_tipo_cita').value,
        duracion_minutos: document.getElementById('crear_duracion_minutos').value
    };
    const form = new URLSearchParams(data);
    const res = await fetch('index.php?action=registrar_cita_ajax', { method: 'POST', body: form });
    const result = await res.json();
    if (result.success) {
        closeCitaDrawer();
        Swal.fire({ title: 'Éxito', text: result.message, icon: 'success', confirmButtonColor: '#0C66E4' });
        if (calendarInstance) calendarInstance.refetchEvents();
    } else {
        Swal.fire({ title: 'Error', text: result.message, icon: 'error', confirmButtonColor: '#0C66E4' });
    }
}

// ═══════════════════════════════════════
// Confirmar cita
// ═══════════════════════════════════════
async function confirmarCita(idCita) {
    const form = new URLSearchParams({ id_cita: idCita });
    const res = await fetch('index.php?action=confirmar_cita_ajax', { method: 'POST', body: form });
    const result = await res.json();
    if (result.success) {
        closeDetalleCitaDrawer();
        Swal.fire({ title: 'Confirmado', text: result.message, icon: 'success', confirmButtonColor: '#0C66E4' });
        if (calendarInstance) calendarInstance.refetchEvents();
        if (result.id_cita) {
            fetch('index.php?action=enviar_email_ajax', {
                method: 'POST',
                body: new URLSearchParams({ id_cita: result.id_cita, tipo: 'confirmacion' })
            }).catch(e => console.error(e));
        }
    } else {
        Swal.fire({ title: 'Error', text: result.message, icon: 'error', confirmButtonColor: '#0C66E4' });
    }
}

// ═══════════════════════════════════════
// Cancelar cita
// ═══════════════════════════════════════
async function cancelarCita(idCita) {
    const result = await Swal.fire({
        title: '¿Cancelar cita?',
        text: 'Esta acción cambiará el estado de la cita a cancelada. Se notificará al propietario.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#626F86',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, mantener'
    });

    if (result.isConfirmed) {
        const form = new URLSearchParams({ id_cita: idCita });
        const res = await fetch('index.php?action=cancelar_cita_ajax', { method: 'POST', body: form });
        const response = await res.json();
        if (response.success) {
            closeDetalleCitaDrawer();
            Swal.fire({ title: 'Cancelada', text: response.message, icon: 'success', confirmButtonColor: '#0C66E4' });
            if (calendarInstance) calendarInstance.refetchEvents();
            if (response.id_cita) {
                fetch('index.php?action=enviar_email_ajax', {
                    method: 'POST',
                    body: new URLSearchParams({ id_cita: response.id_cita, tipo: 'cancelacion' })
                }).catch(e => console.error(e));
            }
        } else {
            Swal.fire({ title: 'Error', text: response.message, icon: 'error', confirmButtonColor: '#0C66E4' });
        }
    }
}

// ═══════════════════════════════════════
// Reprogramar cita
// ═══════════════════════════════════════
async function reprogramarCita(idCita) {
    // Obtener datos actuales de la cita
    try {
        const res = await fetch(`index.php?action=get_cita_ajax&id=${idCita}`);
        const cita = await res.json();
        
        if (!cita.success) {
            Swal.fire({ title: 'Error', text: 'No se pudo obtener la información de la cita', icon: 'error', confirmButtonColor: '#0C66E4' });
            return;
        }
        
        const citaData = cita.cita;
        
        const { value: formValues } = await Swal.fire({
            title: 'Reprogramar Cita',
            html: `
                <div style="text-align:left; margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:700; margin-bottom:0.4rem;">Nueva Fecha:</label>
                    <input type="date" id="reprog_fecha" value="${citaData.fecha}" style="width:100%; padding:0.6rem; border:1px solid #DFE1E6; border-radius:6px;">
                </div>
                <div style="text-align:left; margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:700; margin-bottom:0.4rem;">Nueva Hora:</label>
                    <input type="time" id="reprog_hora" value="${citaData.hora}" style="width:100%; padding:0.6rem; border:1px solid #DFE1E6; border-radius:6px;">
                </div>
                <div style="text-align:left;">
                    <label style="display:block; font-size:0.85rem; font-weight:700; margin-bottom:0.4rem;">Veterinario:</label>
                    <select id="reprog_veterinario" style="width:100%; padding:0.6rem; border:1px solid #DFE1E6; border-radius:6px;"></select>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonColor: '#F59E0B',
            cancelButtonColor: '#626F86',
            confirmButtonText: 'Reprogramar',
            cancelButtonText: 'Cancelar',
            preOpen: () => {
                setTimeout(async () => {
                    await cargarSelects();
                    // Seleccionar el veterinario actual
                    const vetSelect = document.getElementById('reprog_veterinario');
                    if (vetSelect && citaData.doc_veterinario) {
                        vetSelect.value = citaData.doc_veterinario;
                    }
                }, 100);
            },
            preConfirm: () => {
                const fecha = document.getElementById('reprog_fecha').value;
                const hora = document.getElementById('reprog_hora').value;
                const veterinario = document.getElementById('reprog_veterinario').value;
                if (!fecha || !hora || !veterinario) {
                    Swal.showValidationMessage('Por favor completa todos los campos');
                    return false;
                }
                return { fecha, hora, veterinario };
            }
        });

        if (formValues) {
            const form = new URLSearchParams({
                id_cita: idCita,
                fecha: formValues.fecha,
                hora: formValues.hora,
                doc_veterinario: formValues.veterinario,
                motivo: 'Reprogramación',
                id_tipo_cita: citaData.id_tipo_cita || '',
                duracion_minutos: citaData.duracion_minutos || '30'
            });
            const res = await fetch('index.php?action=reprogramar_cita_ajax', { method: 'POST', body: form });
            const response = await res.json();
            if (response.success) {
                closeDetalleCitaDrawer();
                Swal.fire({ title: 'Reprogramada', text: response.message, icon: 'success', confirmButtonColor: '#0C66E4' });
                if (calendarInstance) calendarInstance.refetchEvents();
            } else {
                Swal.fire({ title: 'Error', text: response.message, icon: 'error', confirmButtonColor: '#0C66E4' });
            }
        }
    } catch (e) {
        console.error('Error al reprogramar cita:', e);
        Swal.fire({ title: 'Error', text: 'Error al reprogramar la cita', icon: 'error', confirmButtonColor: '#0C66E4' });
    }
}

// ═══════════════════════════════════════════════════
// MODAL AGENDAR CITA — funciones
// ═══════════════════════════════════════════════════
const modalModoAgendamiento = 'normal';

function format12h(time24) {
    if (!time24) return '';
    const parts = time24.split(':');
    if (parts.length < 2) return time24;
    let hours = parseInt(parts[0], 10);
    const minutes = parts[1];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    return `${hours}:${minutes} ${ampm}`;
}

function mostrarErrorModal(mensaje) {
    const container = document.getElementById('modal_error_container');
    const message = document.getElementById('modal_error_message');
    container.style.display = 'block';
    message.textContent = mensaje;
}

function ocultarErrorModal() {
    const container = document.getElementById('modal_error_container');
    container.style.display = 'none';
}

function abrirCitaModal(date) {
    const fechaStr = toLocalDateStr(date);
    const fechaDisplay = new Date(fechaStr + 'T12:00:00')
        .toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    // Mostrar fecha en chip compacto
    document.getElementById('modal_fecha').value = fechaStr;
    document.getElementById('cm_fecha_display').textContent =
        fechaDisplay.charAt(0).toUpperCase() + fechaDisplay.slice(1);
    document.getElementById('citaModalFechaLabel').textContent =
        fechaDisplay.charAt(0).toUpperCase() + fechaDisplay.slice(1);

    // Resetear mascota
    limpiarMascotaSeleccionada();
    document.getElementById('cm_mascota_search').value = '';
    document.getElementById('cm_mascota_dropdown').innerHTML = '';

    // Resetear tipo
    const tipoGrid = document.getElementById('cm_tipo_grid');
    if (tipoGrid) {
        tipoGrid.querySelectorAll('.cm-tipo-card').forEach(c => c.classList.remove('selected'));
    }
    document.getElementById('modal_tipo_cita').value = '';
    document.getElementById('modal_duracion_minutos').value = '';
    document.getElementById('modal_duracion_badge').style.display = 'none';

    // Resetear hora
    limpiarHoraSeleccionada();

    // Resetear motivo
    const motivoEl = document.getElementById('modal_motivo');
    if (motivoEl) motivoEl.value = '';

    // Resetear slots
    const slotsC = document.getElementById('modal_slots_container');
    if (slotsC) {
        slotsC.classList.remove('visible');
        slotsC.innerHTML = '<span style="font-size:0.82rem;color:#626F86;padding:0.25rem 0;">Selecciona un tipo de cita para ver horarios disponibles.</span>';
        slotsC.classList.add('visible');
    }

    ocultarErrorModal();

    // Cargar selects si están vacíos
    cargarSelectsModal();

    // Mostrar overlay
    const overlay = document.getElementById('citaModalOverlay');
    overlay.style.display = 'flex';
    requestAnimationFrame(() => {
        overlay.classList.add('is-open');
    });
    document.body.style.overflow = 'hidden';
}

// Lista de mascotas cacheada
let _mascotasList = [];

function filtrarMascotas(query) {
    const dropdown = document.getElementById('cm_mascota_dropdown');
    if (!query || query.length < 1) {
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        return;
    }
    const lower = query.toLowerCase();
    const results = _mascotasList.filter(m =>
        m.nombre.toLowerCase().includes(lower) ||
        (m.propietario && m.propietario.toLowerCase().includes(lower))
    );

    if (results.length === 0) {
        dropdown.innerHTML = '<div class="cm-dropdown-empty">Sin resultados</div>';
    } else {
        dropdown.innerHTML = results.slice(0, 8).map(m => `
            <div class="cm-dropdown-item" onclick="seleccionarMascota(${m.id_mascota}, '${m.nombre.replace(/'/g, "\\'")}', '${(m.propietario||'').replace(/'/g, "\\'")}')">
                <i class="fas fa-paw"></i>
                <div>
                    <span class="cm-dropdown-name">${m.nombre}</span>
                    ${m.propietario ? `<span class="cm-dropdown-sub">${m.propietario}</span>` : ''}
                </div>
            </div>
        `).join('');
    }
    dropdown.style.display = 'block';
}

function seleccionarMascota(id, nombre, propietario) {
    document.getElementById('modal_mascota').value = id;
    document.getElementById('cm_mascota_chip_name').textContent = nombre + (propietario ? ` — ${propietario}` : '');
    document.getElementById('cm_mascota_chip').style.display = 'flex';
    document.getElementById('cm_mascota_search').style.display = 'none';
    document.getElementById('cm_mascota_dropdown').style.display = 'none';
}

function limpiarMascotaSeleccionada() {
    document.getElementById('modal_mascota').value = '';
    document.getElementById('cm_mascota_chip').style.display = 'none';
    const searchEl = document.getElementById('cm_mascota_search');
    if (searchEl) { searchEl.style.display = ''; searchEl.value = ''; }
    const dropEl = document.getElementById('cm_mascota_dropdown');
    if (dropEl) { dropEl.style.display = 'none'; dropEl.innerHTML = ''; }
}

function limpiarHoraSeleccionada() {
    document.getElementById('modal_hora').value = '';
    const wrap = document.getElementById('cm_hora_chip_wrap');
    if (wrap) wrap.style.display = 'none';
    // Deseleccionar chips de slots
    document.querySelectorAll('#modal_slots_container .slot-chip').forEach(c => c.classList.remove('selected'));
}


function closeCitaModal() {
    const overlay = document.getElementById('citaModalOverlay');
    overlay.classList.remove('is-open');
    setTimeout(() => {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }, 280);
}

function handleModalOverlayClick(e) {
    if (e.target === document.getElementById('citaModalOverlay')) {
        closeCitaModal();
    }
}

// Escape key para cerrar
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCitaModal();
});



function onModalTipoCitaChange() {
    const sel = document.getElementById('modal_tipo_cita');
    const badge = document.getElementById('modal_duracion_badge');
    const valor = document.getElementById('modal_duracion_valor');
    const hidden = document.getElementById('modal_duracion_minutos');

    if (sel.value) {
        const opt = sel.options[sel.selectedIndex];
        const dur = opt.dataset.duracion || '';
        if (valor) valor.textContent = dur;
        if (hidden) hidden.value = dur;
        if (badge) badge.classList.add('visible');
    } else {
        if (badge) badge.classList.remove('visible');
        if (hidden) hidden.value = '';
    }
    // Limpiar slots al cambiar tipo
    const slotsC = document.getElementById('modal_slots_container');
    if (slotsC) {
        slotsC.classList.remove('visible');
        slotsC.innerHTML = '<span style="font-size:0.82rem;color:#626F86;padding:0.25rem 0;">Selecciona un tipo de cita para ver horarios disponibles.</span>';
        slotsC.classList.add('visible');
    }
    limpiarHoraSeleccionada();

    // Auto-cargar slots si ya tenemos vet
    const esVet = isUsuarioVeterinario();
    const vet = esVet
        ? document.getElementById('modal_veterinario_hidden')?.value
        : document.getElementById('modal_veterinario')?.value;
    
    if (vet && sel && sel.value) {
        cargarSlotsModal();
    }
}

async function cargarSelectsModal() {
    // Veterinarios
    try {
        const res = await fetch('index.php?action=listar_veterinarios_ajax');
        const vets = await res.json();
        const esVet = isUsuarioVeterinario();
        const docUsuario = getUsuarioDoc();

        if (esVet) {
            // Vet: mostrar chip, ocultar select (se oculta el chip para liberar espacio)
            const match = vets.find(v => v.documento === docUsuario);
            const vetName = match ? match.nombre_completo : 'Mi agenda';
            document.getElementById('modal_veterinario_hidden').value = docUsuario;
            document.getElementById('cm_vet_chip').style.display = 'none';
            document.getElementById('cm_vet_select_wrap').style.display = 'none';

            // Ocultar la fila .cm-info-row en la vista del veterinario para optimizar espacio vertical
            const infoRow = document.querySelector('.cm-info-row');
            if (infoRow) infoRow.style.display = 'none';

            // Agregar el nombre del veterinario al subtítulo del modal
            const label = document.getElementById('citaModalFechaLabel');
            if (label) {
                const text = label.textContent;
                if (!text.includes('• Vet:')) {
                    label.textContent = `${text} • Vet: ${vetName}`;
                }
            }
        } else {
            // Recepcionista/Staff: mostrar select
            const vetSel = document.getElementById('modal_veterinario');
            if (vetSel && vetSel.options.length <= 1) {
                vetSel.innerHTML = '<option value="">Seleccione...</option>';
                vets.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.documento;
                    opt.textContent = v.nombre_completo;
                    vetSel.appendChild(opt);
                });
            }
            document.getElementById('cm_vet_chip').style.display = 'none';
            document.getElementById('cm_vet_select_wrap').style.display = 'flex';

            // Mostrar la fila de información para recepción ya que contiene el select
            const infoRow = document.querySelector('.cm-info-row');
            if (infoRow) infoRow.style.display = 'flex';
        }
    } catch(e) { console.error('Error vets:', e); }

    // Mascotas — cargar y cachear para búsqueda en vivo
    if (_mascotasList.length === 0) {
        try {
            const res = await fetch('index.php?action=listar_mascotas_ajax');
            const masc = await res.json();
            _mascotasList = masc.map(m => ({
                id_mascota: m.id_mascota,
                nombre: m.nombre,
                propietario: m.propietario_nombre || m.propietario || ''
            }));
        } catch(e) { console.error('Error mascotas:', e); }
    }

    // Tipos de cita — renderizar como cards
    const tipoGrid = document.getElementById('cm_tipo_grid');
    if (tipoGrid && !tipoGrid.querySelector('.cm-tipo-card')) {
        try {
            const res = await fetch('index.php?action=listar_tipos_cita_ajax');
            const data = await res.json();
            if (!data.success || !Array.isArray(data.tipos)) return;

            const iconMap = { 'Consulta': 'fa-stethoscope', 'Vacunación': 'fa-syringe', 'Cirugía': 'fa-procedures', 'Control': 'fa-clipboard-check', 'Desparasitación': 'fa-shield-virus' };

            tipoGrid.innerHTML = data.tipos.map(t => {
                const icon = Object.entries(iconMap).find(([k]) => t.nombre.toLowerCase().includes(k.toLowerCase()))?.[1] || 'fa-tag';
                return `
                <div class="cm-tipo-card" data-id="${t.id_tipo_cita}" data-duracion="${t.duracion_minutos}" data-nombre="${t.nombre}"
                     onclick="seleccionarTipoCita(this)">
                    <i class="fas ${icon}"></i>
                    <span class="cm-tipo-nombre">${t.nombre}</span>
                    <span class="cm-tipo-dur">${t.duracion_minutos} min</span>
                </div>`;
            }).join('');
        } catch(e) { console.error('Error tipos:', e); }
    }
}

function seleccionarTipoCita(card) {
    document.querySelectorAll('.cm-tipo-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    const id = card.dataset.id;
    const dur = card.dataset.duracion;
    document.getElementById('modal_tipo_cita').value = id;
    document.getElementById('modal_duracion_minutos').value = dur;
    document.getElementById('modal_duracion_valor').textContent = dur;
    document.getElementById('modal_duracion_badge').style.display = 'flex';
    // Limpiar slots al cambiar tipo
    const slotsC = document.getElementById('modal_slots_container');
    if (slotsC) {
        slotsC.classList.remove('visible');
        slotsC.innerHTML = '<span style="font-size:0.82rem;color:#626F86;padding:0.25rem 0;">Selecciona un tipo de cita para ver horarios disponibles.</span>';
        slotsC.classList.add('visible');
    }
    limpiarHoraSeleccionada();

    // Auto-cargar slots si ya tenemos vet
    const esVet = isUsuarioVeterinario();
    const vet = esVet
        ? document.getElementById('modal_veterinario_hidden')?.value
        : document.getElementById('modal_veterinario')?.value;
    
    if (vet) {
        cargarSlotsModal();
    }
}

async function cargarSlotsModal() {
    // Obtener vet desde chip (veterinario) o select (recepcionista)
    const esVet = isUsuarioVeterinario();
    const vet = esVet
        ? document.getElementById('modal_veterinario_hidden')?.value
        : document.getElementById('modal_veterinario')?.value;
    const fecha = document.getElementById('modal_fecha')?.value;
    const tipo = document.getElementById('modal_tipo_cita')?.value;
    const duracion = document.getElementById('modal_duracion_minutos')?.value;

    if (!vet || !fecha || !tipo || !duracion) {
        // Retorno silencioso si no se han completado los datos para auto-carga
        return;
    }

    const btn = document.getElementById('modal_btn_slots');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
    }

    const container = document.getElementById('modal_slots_container');
    if (container) {
        container.innerHTML = '<div style="font-size:0.85rem;color:#626F86;padding:0.5rem 0;display:flex;align-items:center;gap:0.5rem;"><i class="fas fa-spinner fa-spin"></i> Cargando horarios disponibles...</div>';
        container.classList.add('visible');
    }
    ocultarErrorModal();

    try {
        // Primero obtener horas disponibles según configuración de horarios de la clínica
        // Pasar la duración como intervalo para mantener consistencia con las sugerencias
        const horariosRes = await fetch(
            `index.php?action=get_horas_disponibles_ajax&fecha=${fecha}&intervalo=${duracion}`
        );
        const horariosData = await horariosRes.json();
        
        if (!horariosData.success || !horariosData.horas || horariosData.horas.length === 0) {
            if (container) {
                container.innerHTML = '<span style="font-size:0.8rem;color:#626F86;padding:0.25rem 0;">El día seleccionado no es laborable o no hay horarios configurados.</span>';
                container.classList.add('visible');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search"></i> Ver horarios libres';
            }
            return;
        }

        // Luego obtener sugerencias del veterinario
        const res = await fetch(
            `index.php?action=get_sugerencias_horario_ajax&doc_veterinario=${vet}&fecha=${fecha}&duracion_minutos=${duracion}&modo=${modalModoAgendamiento}`
        );
        const data = await res.json();
        if (container) container.innerHTML = '';

        // Filtrar sugerencias para mostrar solo las horas que están en el horario laboral
        const horasLaborales = horariosData.horas || [];
        let horasMostrar = [];
        
        if (data.success && Array.isArray(data.sugerencias) && data.sugerencias.length > 0) {
            horasMostrar = data.sugerencias.filter(hora => horasLaborales.includes(hora));

            // Si no hay coincidencias exactas, usar todas las sugerencias como fallback
            if (horasMostrar.length === 0) {
                console.warn('Sugerencias disponibles pero fuera del horario laboral configurado:', data.sugerencias);
                horasMostrar = data.sugerencias;
            }
        }

        if (horasMostrar.length > 0) {
            horasMostrar.forEach(hora => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'slot-chip';
                chip.textContent = format12h(hora);
                chip.onclick = function() {
                    document.getElementById('modal_hora').value = hora;
                    document.querySelectorAll('.slot-chip').forEach(c => c.classList.remove('selected'));
                    chip.classList.add('selected');
                    // Mostrar chip de hora seleccionada
                    const wrap = document.getElementById('cm_hora_chip_wrap');
                    const chipText = document.getElementById('cm_hora_chip_text');
                    if (wrap && chipText) { chipText.textContent = format12h(hora); wrap.style.display = 'flex'; }
                    ocultarErrorModal();
                };
                if (container) container.appendChild(chip);
            });
        } else {
            console.warn('Sin sugerencias de horario para la fecha', fecha, 'duración', duracion, 'respuesta:', data, 'horarios configurados:', horasLaborales);
            if (container) container.innerHTML = '<span style="font-size:0.8rem;color:#626F86;padding:0.25rem 0;">No hay horarios disponibles para esta fecha dentro del horario laboral.</span>';
        }
        if (container) container.classList.add('visible');
    } catch(e) {
        console.error('Error slots:', e);
        mostrarErrorModal('Error al cargar horarios disponibles. Intenta nuevamente.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i> Ver horarios libres';
        }
    }
}

async function crearCitaModal(e) {
    e.preventDefault();
    const hora = document.getElementById('modal_hora').value;
    if (!hora) {
        mostrarErrorModal('Debes seleccionar uno de los horarios disponibles antes de agendar la cita.');
        return;
    }
    const data = {
        id_mascota:       document.getElementById('modal_mascota').value,
        doc_veterinario:  isUsuarioVeterinario()
            ? document.getElementById('modal_veterinario_hidden').value
            : (document.getElementById('modal_veterinario')?.value || ''),
        fecha:            document.getElementById('modal_fecha').value,
        hora:             hora,
        motivo:           document.getElementById('modal_motivo').value,
        id_tipo_cita:     document.getElementById('modal_tipo_cita').value,
        duracion_minutos: document.getElementById('modal_duracion_minutos').value
    };

    const submitBtn = document.querySelector('.btn-modal-submit');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agendando...';
    ocultarErrorModal();

    try {
        const res = await fetch('index.php?action=registrar_cita_ajax', {
            method: 'POST',
            body: new URLSearchParams(data)
        });
        const result = await res.json();

        if (result.success) {
            closeCitaModal();
            Swal.fire({
                title: '¡Cita Agendada!',
                text: result.message,
                icon: 'success',
                confirmButtonColor: '#0C66E4'
            });
            if (calendarInstance) calendarInstance.refetchEvents();
            
            // Disparar envío de correo en segundo plano
            if (result.id_cita) {
                fetch('index.php?action=enviar_email_ajax', {
                    method: 'POST',
                    body: new URLSearchParams({ id_cita: result.id_cita, tipo: 'confirmacion_nueva' })
                }).catch(e => console.error('Error enviando email asíncrono:', e));
            }
        } else {
            mostrarErrorModal(result.message);
        }
    } catch(err) {
        mostrarErrorModal('Error de conexión. Intenta nuevamente.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Agendar Cita';
    }
}


// ═══════════════════════════════════════
// Toast notifications
// ═══════════════════════════════════════
function mostrarToast(mensaje, tipo = 'success') {
    const existing = document.getElementById('calToast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'calToast';

    let bg, icon;
    if (tipo === 'success') {
        bg = '#0C66E4'; icon = 'fa-check-circle';
    } else if (tipo === 'info') {
        bg = '#475569'; icon = 'fa-info-circle';
    } else {
        bg = '#EF4444'; icon = 'fa-exclamation-circle';
    }

    toast.style.cssText = `
        position:fixed; bottom:24px; right:24px; z-index:9999;
        background:${bg}; color:white; padding:12px 20px;
        border-radius:10px; font-size:0.875rem; font-weight:600;
        box-shadow:0 8px 24px rgba(0,0,0,0.18);
        display:flex; align-items:center; gap:10px;
        animation:slideInToast 0.3s ease;
        max-width:360px;
    `;
    toast.innerHTML = `<i class="fas ${icon}"></i><span>${mensaje}</span>`;

    if (!document.getElementById('calToastStyle')) {
        const style = document.createElement('style');
        style.id = 'calToastStyle';
        style.textContent = '@keyframes slideInToast{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}';
        document.head.appendChild(style);
    }

    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ═══════════════════════════════════════
// EventPopover — Requirements 4.1–4.7
// ═══════════════════════════════════════
let _currentPopoverEventId = null;

function cerrarPopover() {
    const pop = document.getElementById('eventPopover');
    if (pop) {
        pop.style.display = 'none';
        pop.style.opacity = '0';
    }
    _currentPopoverEventId = null;
}

function posicionarPopover(popover, eventEl) {
    const rect = eventEl.getBoundingClientRect();
    const vpW  = window.innerWidth;
    const popW = 280;

    let left = rect.right + 8 + window.scrollX;
    if (rect.right + 8 + popW > vpW - 16) {
        left = rect.left - popW - 8 + window.scrollX;
    }
    popover.style.left = Math.max(8, left) + 'px';
    popover.style.top  = Math.max(8, rect.top + window.scrollY) + 'px';
}

function mostrarPopover(eventInfo) {
    // Cerrar popover previo
    cerrarPopover();

    const pop = document.getElementById('eventPopover');
    if (!pop) return;

    const ev    = eventInfo.event;
    const props = ev.extendedProps;
    const tipo  = props.tipo || 'cita';
    const estadoRaw = (props.estado || '').toLowerCase();
    const docEvento = props.doc_veterinario || props.doc_veterinario_doc || '';
    const userRol = typeof USER_ROL !== 'undefined' ? Number(USER_ROL) : null;
    const esVeterinarioSesion = isUsuarioVeterinario() && docEvento && docEvento === getUsuarioDoc();
    const esStaff = userRol === 1 || userRol === 3;

    _currentPopoverEventId = ev.id;

    // Badge de tipo
    const tipoLabels = { cita: 'Cita', vacunacion: 'Vacunación', desparasitacion: 'Desparasitación' };
    const tipoColors = {
        cita:            { bg: '#E9F2FF', color: '#0C66E4' },
        vacunacion:      { bg: '#F0FDF4', color: '#15803D' },
        desparasitacion: { bg: '#FFF7ED', color: '#C2410C' }
    };
    const tc = tipoColors[tipo] || tipoColors.cita;

    const badge = pop.querySelector('.popover-tipo-badge');
    if (badge) {
        badge.textContent = tipoLabels[tipo] || tipo;
        badge.style.background = tc.bg;
        badge.style.color = tc.color;
    }

    // Datos del evento
    const startDate = ev.start;
    const opciones  = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const fechaFmt  = startDate ? startDate.toLocaleDateString('es-CO', opciones) : 'Sin fecha';
    const horaFmt   = startDate ? startDate.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }) : '--:--';

    const setSpan = (id, val) => {
        const el = pop.querySelector('#' + id);
        if (el) el.textContent = val || '—';
    };

    setSpan('pop_mascota',     props.mascota_nombre   || ev.title.split(' — ')[0] || ev.title);
    setSpan('pop_propietario', props.propietario_nombre || props.propietario || '—');
    setSpan('pop_veterinario', props.veterinario_nombre || props.veterinario || '—');
    setSpan('pop_motivo',      props.motivo || '—');
    setSpan('pop_fecha',       `${fechaFmt} · ${horaFmt}`);

    // Estado badge
    const estadoBadge = pop.querySelector('#pop_estado_badge');
    const estadoVisualMap = {
        pendiente:   { bg: '#FFF7D6', color: '#946F00', border: '#FCD34D', icon: 'fa-clock', label: 'Pendiente' },
        confirmada:  { bg: '#E3FCEF', color: '#006644', border: '#ABF5D1', icon: 'fa-check-circle', label: 'Confirmada' },
        en_curso:    { bg: '#E0F2FE', color: '#0F6EDE', border: '#90CDF4', icon: 'fa-play-circle', label: 'En curso' },
        encurso:     { bg: '#E0F2FE', color: '#0F6EDE', border: '#90CDF4', icon: 'fa-play-circle', label: 'En curso' },
        completada:  { bg: '#F0FDF4', color: '#15803D', border: '#BBF7D0', icon: 'fa-check', label: 'Completada' },
        cancelada:   { bg: '#FFEDE9', color: '#C2410C', border: '#FECACA', icon: 'fa-times-circle', label: 'Cancelada' }
    };
    const estadoVisual = estadoVisualMap[estadoRaw] || estadoVisualMap.pendiente;
    if (estadoBadge) {
        estadoBadge.textContent = estadoVisual.label;
        estadoBadge.style.background = estadoVisual.bg;
        estadoBadge.style.color = estadoVisual.color;
    }

    // Ocultar filas exclusivas de cita para vacunacion/desparasitacion
    const vetRow    = pop.querySelector('.popover-vet-row');
    const estadoRow = pop.querySelector('.popover-estado-row');
    if (vetRow)    vetRow.style.display    = (tipo === 'cita') ? '' : 'none';
    if (estadoRow) estadoRow.style.display = (tipo === 'cita') ? '' : 'none';

    // Botón "Ver detalle completo" — solo para citas
    const btnDetalle = pop.querySelector('#pop_btn_detalle');
    if (btnDetalle) {
        if (tipo === 'cita') {
            btnDetalle.style.display = '';
            btnDetalle.onclick = function() {
                cerrarPopover();
                abrirDetalleCitaDesdePopover(ev.id, ev, props);
            };
        } else {
            btnDetalle.style.display = 'none';
        }
    }

    // Posicionar y mostrar
    pop.style.display = 'block';
    posicionarPopover(pop, eventInfo.el);
    requestAnimationFrame(() => { pop.style.opacity = '1'; });
}

// Abrir drawer de detalle desde el popover (preserva la lógica existente)
function abrirDetalleCitaDesdePopover(idCita, ev, props) {
    const startDate = ev.start;
    const opciones  = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const fechaFmt  = startDate ? startDate.toLocaleDateString('es-CO', opciones) : 'Sin fecha';
    const horaFmt   = startDate ? startDate.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }) : '--:--';

    const today = new Date(); today.setHours(0, 0, 0, 0);
    const citaDate = startDate ? new Date(startDate) : new Date();
    citaDate.setHours(0, 0, 0, 0);
    const esCitaPasada = citaDate < today;

    const puedeGestionar = tipo === 'cita' && (esVeterinarioSesion || esStaff);
    const puedeConfirmar = puedeGestionar && estadoRaw === 'pendiente';
    const puedeIniciar   = puedeGestionar && !['en_curso', 'encurso', 'cancelada', 'completada'].includes(estadoRaw);
    const puedeCompletar = puedeGestionar && !['cancelada', 'completada'].includes(estadoRaw);

    let accionesHtml = '';
    if (props.estado === 'cancelada') {
        accionesHtml = `<div style="text-align:center;padding:0.85rem;background:#FFEDEB;color:#BF2600;border:1px solid #FFBDAD;border-radius:8px;font-weight:700;font-size:0.85rem;"><i class="fas fa-times-circle" style="margin-right:0.4rem;"></i> Esta cita ha sido cancelada</div>`;
    } else if (esCitaPasada) {
        accionesHtml = `<div style="text-align:center;padding:0.85rem;background:#F1F5F9;color:#626F86;border:1px solid #DFE1E6;border-radius:8px;font-weight:700;font-size:0.85rem;"><i class="fas fa-history" style="margin-right:0.4rem;"></i> Esta cita ya caducó (fecha pasada)</div>`;
    } else if (tipo !== 'cita') {
        accionesHtml = `<div style="display:grid;grid-template-columns:1fr;gap:0.75rem;"><button onclick="abrirModalReprogramar(${idCita})" style="width:100%;padding:0.85rem;background:#F59E0B;color:white;border:none;border-radius:8px;font-family:inherit;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#D97706'" onmouseout="this.style.background='#F59E0B'"><i class="fas fa-calendar-alt"></i> Reprogramar</button></div>`;
    } else if (!puedeGestionar) {
        accionesHtml = '<div style="text-align:center;padding:0.75rem;background:#F8FAFC;color:#64748B;border:1px solid #E2E8F0;border-radius:8px;font-weight:600;font-size:0.8rem;"><i class="fas fa-info-circle" style="margin-right:0.4rem;"></i>Solo el personal autorizado puede gestionar esta cita.</div>';
    } else {
        accionesHtml = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            ${puedeConfirmar ? `<button onclick="confirmarCita('${idCita}')" style="width:100%;padding:0.85rem;background:#0C66E4;color:white;border:none;border-radius:8px;font-family:inherit;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#0055CC'" onmouseout="this.style.background='#0C66E4'"><i class="fas fa-check"></i> Confirmar</button>` : ''}
            ${puedeIniciar ? `<button onclick="iniciarAtencionCita('${idCita}', { context: 'drawer' })" style="width:100%;padding:0.85rem;background:#0284C7;color:white;border:none;border-radius:8px;font-family:inherit;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#0369A1'" onmouseout="this.style.background='#0284C7'"><i class="fas fa-play-circle"></i> Iniciar atención</button>` : ''}
            ${puedeCompletar ? `<button onclick="completarCita('${idCita}', { context: 'drawer' })" style="width:100%;padding:0.85rem;background:#16A34A;color:white;border:none;border-radius:8px;font-family:inherit;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#15803D'" onmouseout="this.style.background='#16A34A'"><i class="fas fa-check-circle"></i> Marcar como atendida</button>` : ''}
            <button onclick="abrirModalReprogramar(${idCita})" style="width:100%;padding:0.85rem;background:#F59E0B;color:white;border:none;border-radius:8px;font-family:inherit;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#D97706'" onmouseout="this.style.background='#F59E0B'"><i class="fas fa-calendar-alt"></i> Reprogramar</button>
            <button onclick="cancelarCita(${idCita})" style="width:100%;padding:0.85rem;background:#EF4444;color:white;border:none;border-radius:8px;font-family:inherit;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'"><i class="fas fa-times-circle"></i> Cancelar</button>
        </div>`;
    }

    const estadoColor = estadoVisual;

    const body = document.getElementById('detalleCitaBody');
    body.innerHTML = `
        <div style="display:flex;align-items:center;gap:1rem;background:#F7F8F9;padding:1.25rem;border-radius:12px;border:1px solid #DFE1E6;margin-bottom:1.5rem;">
            <div style="width:50px;height:50px;border-radius:14px;background:#E9F2FF;color:#0C66E4;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;"><i class="fas fa-paw"></i></div>
            <div style="flex:1;min-width:0;">
                <h4 style="margin:0;font-size:1.05rem;color:#172B4D;font-weight:800;">${ev.title}</h4>
                <span style="font-size:0.8rem;color:#626F86;font-weight:500;">Paciente registrado</span>
            </div>
            <div style="background:${estadoColor.bg};color:${estadoColor.color};border:1px solid ${estadoColor.border};padding:0.35rem 0.85rem;border-radius:50px;font-size:0.73rem;font-weight:700;display:flex;align-items:center;gap:0.35rem;flex-shrink:0;">
                <i class="fas ${estadoColor.icon}"></i> ${estadoColor.label}
            </div>
        </div>
        <p style="margin:0 0 0.75rem 0;font-size:0.73rem;color:#626F86;text-transform:uppercase;font-weight:800;letter-spacing:0.5px;"><i class="fas fa-info-circle" style="color:#0C66E4;margin-right:0.35rem;"></i>Información de la Cita</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1.5rem;">
            <div style="background:white;border:1px solid #DFE1E6;padding:1rem;border-radius:10px;">
                <div style="font-size:0.7rem;color:#626F86;font-weight:700;text-transform:uppercase;margin-bottom:0.3rem;"><i class="far fa-calendar-alt" style="margin-right:0.3rem;color:#579DFF;"></i>Fecha</div>
                <div style="font-weight:700;color:#172B4D;font-size:0.9rem;text-transform:capitalize;">${fechaFmt}</div>
            </div>
            <div style="background:white;border:1px solid #DFE1E6;padding:1rem;border-radius:10px;">
                <div style="font-size:0.7rem;color:#626F86;font-weight:700;text-transform:uppercase;margin-bottom:0.3rem;"><i class="far fa-clock" style="margin-right:0.3rem;color:#579DFF;"></i>Hora</div>
                <div style="font-weight:700;color:#172B4D;font-size:0.9rem;">${horaFmt}</div>
            </div>
        </div>
        <div style="background:white;border:1px solid #DFE1E6;border-radius:10px;overflow:hidden;margin-bottom:0.75rem;">
            <div style="background:#F7F8F9;padding:0.65rem 1rem;border-bottom:1px solid #DFE1E6;font-weight:700;color:#172B4D;font-size:0.8rem;"><i class="fas fa-user-md" style="color:#0C66E4;margin-right:0.4rem;"></i>Veterinario Asignado</div>
            <div style="padding:1rem;font-size:0.9rem;color:#44546F;font-weight:500;">${props.veterinario || props.veterinario_nombre || 'No asignado'}</div>
        </div>
        <div style="background:white;border:1px solid #DFE1E6;border-radius:10px;overflow:hidden;margin-bottom:1.5rem;">
            <div style="background:#F7F8F9;padding:0.65rem 1rem;border-bottom:1px solid #DFE1E6;font-weight:700;color:#172B4D;font-size:0.8rem;"><i class="fas fa-comment-medical" style="color:#0C66E4;margin-right:0.4rem;"></i>Motivo de la Cita</div>
            <div style="padding:1rem;font-size:0.9rem;color:#44546F;line-height:1.6;font-weight:500;">${props.motivo || 'Sin motivo registrado'}</div>
        </div>
        ${props.estado === 'cancelada' ? `
        <div style="text-align:center;padding:0.85rem;background:#FFEDEB;color:#BF2600;border:1px solid #FFBDAD;border-radius:8px;font-weight:700;font-size:0.85rem;"><i class="fas fa-times-circle" style="margin-right:0.4rem;"></i> Esta cita ha sido cancelada</div>
        ` : esCitaPasada ? `
        <div style="text-align:center;padding:0.85rem;background:#F1F5F9;color:#626F86;border:1px solid #DFE1E6;border-radius:8px;font-weight:700;font-size:0.85rem;"><i class="fas fa-history" style="margin-right:0.4rem;"></i> Esta cita ya caducó (fecha pasada)</div>
        ` : `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            ${props.estado !== 'confirmada' ? `<button onclick="confirmarCita(${idCita})" style="width:100%;padding:0.85rem;background:#0C66E4;color:white;border:none;border-radius:8px;font-family:inherit;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#0055CC'" onmouseout="this.style.background='#0C66E4'"><i class="fas fa-check-circle"></i> Confirmar</button>` : ''}
            <button onclick="abrirModalReprogramar(${idCita})" style="width:100%;padding:0.85rem;background:#F59E0B;color:white;border:none;border-radius:8px;font-family:inherit;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#D97706'" onmouseout="this.style.background='#F59E0B'"><i class="fas fa-calendar-alt"></i> Reprogramar</button>
            <button onclick="cancelarCita(${idCita})" style="width:100%;padding:0.85rem;background:#EF4444;color:white;border:none;border-radius:8px;font-family:inherit;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'"><i class="fas fa-times-circle"></i> Cancelar</button>
        </div>
        `}
    `;
    body.scrollTop = 0;
    openDetalleCitaDrawer();
}

// Cerrar popover al hacer clic fuera
document.addEventListener('click', function(e) {
    const pop = document.getElementById('eventPopover');
    if (!pop || pop.style.display === 'none') return;
    if (!pop.contains(e.target) && !e.target.closest('.fc-event')) {
        cerrarPopover();
    }
});

// Cerrar popover con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarPopover();
        // Cerrar también popover nativo de FullCalendar
        document.querySelectorAll('.fc-popover').forEach(pop => pop.remove());
    }
});

// Fix para el botón de cerrar del popover nativo de FullCalendar (+x más)
document.addEventListener('click', function(e) {
    if (e.target.closest('.fc-popover-close') || e.target.classList.contains('fc-icon-x')) {
        const popovers = document.querySelectorAll('.fc-popover');
        popovers.forEach(pop => pop.remove());
    }
});

// ═══════════════════════════════════════
// ReprogramarModal — Requirements 2.1–2.11
// ═══════════════════════════════════════
let _reprogramarCitaId = null;

function cerrarModalReprogramar() {
    const overlay = document.getElementById('reprogramarModalOverlay');
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => { overlay.style.display = 'none'; }, 250);
    }
    _reprogramarCitaId = null;
}

async function abrirModalReprogramar(idCita) {
    _reprogramarCitaId = idCita;
    closeDetalleCitaDrawer();

    const overlay = document.getElementById('reprogramarModalOverlay');
    if (!overlay) return;

    // Limpiar estado previo
    const errEl = document.getElementById('reprog_error');
    if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
    const slotsContainer = document.getElementById('reprogramar_slots_container');
    if (slotsContainer) { slotsContainer.innerHTML = ''; }
    const horaHidden = document.getElementById('reprogramar_hora');
    if (horaHidden) horaHidden.value = '';

    // Cargar datos actuales de la cita
    try {
        const res  = await fetch(`index.php?action=get_cita_ajax&id=${idCita}`);
        const data = await res.json();

        if (!data.success) {
            mostrarToast('No se pudo cargar la información de la cita.', 'error');
            return;
        }

        const cita = data.cita;

        // Cargar selects si están vacíos
        await cargarSelectsReprogramar();

        // Pre-poblar campos
        const vetSel   = document.getElementById('reprogramar_veterinario');
        const fechaIn  = document.getElementById('reprogramar_fecha');
        const tipoSel  = document.getElementById('reprogramar_tipo_cita');

        if (vetSel)  { vetSel.value  = cita.doc_veterinario || ''; }
        if (fechaIn) { fechaIn.value = cita.fecha || ''; }
        if (tipoSel) {
            tipoSel.value = cita.id_tipo_cita || '';
            actualizarDuracionReprogramar();
        }

        // Deshabilitar veterinario para Veterinarios (rol 2)
        if (vetSel && typeof USER_ROL !== 'undefined' && USER_ROL === 2) {
            vetSel.disabled = true;
        }

        // Mostrar overlay
        overlay.style.display = 'flex';
        requestAnimationFrame(() => { overlay.style.opacity = '1'; });

    } catch(e) {
        console.error('Error al abrir modal reprogramar:', e);
        mostrarToast('Error al cargar los datos de la cita.', 'error');
    }
}

async function cargarSelectsReprogramar() {
    const vetSel  = document.getElementById('reprogramar_veterinario');
    const tipoSel = document.getElementById('reprogramar_tipo_cita');

    if (vetSel && vetSel.options.length <= 1) {
        try {
            const res  = await fetch('index.php?action=listar_veterinarios_ajax');
            const vets = await res.json();
            vetSel.innerHTML = '<option value="">Seleccione veterinario...</option>';
            vets.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.documento;
                opt.textContent = v.nombre_completo;
                vetSel.appendChild(opt);
            });
        } catch(e) { console.error('Error vets reprogramar:', e); }
    }

    if (tipoSel && tipoSel.options.length <= 1) {
        try {
            const res   = await fetch('index.php?action=listar_tipos_cita_ajax');
            const data = await res.json();
            if (!data.success || !Array.isArray(data.tipos)) {
                console.error('Error en la respuesta:', data);
                return;
            }
            tipoSel.innerHTML = '<option value="">Seleccione tipo...</option>';
            data.tipos.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id_tipo_cita;
                opt.textContent = `${t.nombre} (${t.duracion_minutos} min)`;
                opt.dataset.duracion = t.duracion_minutos;
                tipoSel.appendChild(opt);
            });
        } catch(e) { console.error('Error tipos reprogramar:', e); }
    }
}

function actualizarDuracionReprogramar() {
    const tipoSel = document.getElementById('reprogramar_tipo_cita');
    const badge   = document.getElementById('reprogramar_duracion_badge');
    const valor   = document.getElementById('reprogramar_duracion_valor');

    if (!tipoSel || !badge || !valor) return;

    if (tipoSel.value) {
        const opt = tipoSel.options[tipoSel.selectedIndex];
        valor.textContent = opt.dataset.duracion || '—';
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
    // Limpiar slots al cambiar tipo
    const slotsContainer = document.getElementById('reprogramar_slots_container');
    if (slotsContainer) slotsContainer.innerHTML = '';
    const horaHidden = document.getElementById('reprogramar_hora');
    if (horaHidden) horaHidden.value = '';
}

async function cargarSlotsReprogramar() {
    const vet     = document.getElementById('reprogramar_veterinario')?.value;
    const fecha   = document.getElementById('reprogramar_fecha')?.value;
    const tipoSel = document.getElementById('reprogramar_tipo_cita');
    const duracion = tipoSel?.options[tipoSel.selectedIndex]?.dataset?.duracion || '30';
    const errEl   = document.getElementById('reprog_error');

    if (!vet || !fecha) {
        if (errEl) { errEl.textContent = 'Selecciona veterinario y fecha primero.'; errEl.style.display = 'block'; }
        return;
    }
    if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }

    const btn = document.getElementById('reprogramar_btn_slots');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...'; }

    try {
        const url = `index.php?action=get_sugerencias_horario_ajax&doc_veterinario=${encodeURIComponent(vet)}&fecha=${encodeURIComponent(fecha)}&duracion_minutos=${duracion}&id_cita_excluir=${_reprogramarCitaId || ''}`;
        const res  = await fetch(url);
        const data = await res.json();

        const container = document.getElementById('reprogramar_slots_container');
        if (!container) return;
        container.innerHTML = '';

        if (data.success && data.sugerencias && data.sugerencias.length > 0) {
            data.sugerencias.forEach(hora => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'reprog-slot-chip';
                chip.textContent = format12h(hora);
                chip.onclick = function() {
                    document.getElementById('reprogramar_hora').value = hora;
                    container.querySelectorAll('.reprog-slot-chip').forEach(c => c.classList.remove('selected'));
                    chip.classList.add('selected');
                    if (errEl) { errEl.style.display = 'none'; }
                };
                container.appendChild(chip);
            });
        } else {
            container.innerHTML = '<p style="font-size:0.82rem;color:#626F86;margin:0.5rem 0;">No hay horarios disponibles para esta fecha. Prueba con otra fecha o veterinario.</p>';
        }
    } catch(e) {
        console.error('Error slots reprogramar:', e);
        if (errEl) { errEl.textContent = 'Error al cargar horarios. Intenta nuevamente.'; errEl.style.display = 'block'; }
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-search"></i> Ver horarios disponibles'; }
    }
}

async function confirmarReprogramacion() {
    const hora  = document.getElementById('reprogramar_hora')?.value;
    const fecha = document.getElementById('reprogramar_fecha')?.value;
    const vet   = document.getElementById('reprogramar_veterinario')?.value;
    const errEl = document.getElementById('reprog_error');

    if (!hora) {
        if (errEl) { errEl.textContent = 'Debes seleccionar un horario disponible antes de confirmar.'; errEl.style.display = 'block'; }
        return;
    }
    if (errEl) { errEl.style.display = 'none'; }

    const btn = document.getElementById('reprogramar_btn_confirmar');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reprogramando...'; }

    try {
        const form = new URLSearchParams({
            id_cita: _reprogramarCitaId,
            fecha:   fecha,
            hora:    hora,
            doc_veterinario: vet
        });
        const res  = await fetch('index.php?action=reprogramar_cita_ajax', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
            cerrarModalReprogramar();
            if (calendarInstance) calendarInstance.refetchEvents();
            mostrarToast('Cita reprogramada correctamente.', 'success');
        } else {
            if (errEl) { errEl.textContent = data.message || 'Error al reprogramar la cita.'; errEl.style.display = 'block'; }
        }
    } catch(e) {
        console.error('Error confirmar reprogramacion:', e);
        if (errEl) { errEl.textContent = 'Error de conexión. Intenta nuevamente.'; errEl.style.display = 'block'; }
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Confirmar reprogramación'; }
    }
}

// Mover modales/drawers al final de document.body para evitar conflictos de z-index y contexto de apilamiento CSS
function moverModalesAlBody() {
    const idsToMove = [
        'citaModalOverlay',
        'citaDrawerOverlay',
        'citaDrawer',
        'detalleCitaDrawerOverlay',
        'detalleCitaDrawer',
        'reprogramarModalOverlay'
    ];
    idsToMove.forEach(id => {
        const el = document.getElementById(id);
        if (el && el.parentNode !== document.body) {
            document.body.appendChild(el);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', moverModalesAlBody);
} else {
    moverModalesAlBody();
}
