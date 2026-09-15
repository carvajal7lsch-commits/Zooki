/* ═══════════════════════════════════════════
   ZOOKI AGENDA
   Calendario, panel del día, modal de agendar y modal de reprogramar.
   Los colores de estado y de tipo viven solo en el CSS (base.css), que los
   lee de los atributos data-estado y data-tipo; aquí solo van las etiquetas.
   ═══════════════════════════════════════════ */

const ESTADOS_CITA = {
    pendiente: 'Pendiente',
    confirmada: 'Confirmada',
    en_curso: 'En curso',
    completada: 'Completada',
    cancelada: 'Cancelada',
    no_asistio: 'No asistió',
    sin_cerrar: 'Sin cerrar',
    cerrada_sin_consulta: 'Cerrada sin consulta'
};

const TIPOS_EVENTO = { cita: 'Cita', vacunacion: 'Vacunación', desparasitacion: 'Desparasitación' };

// RN-408: debe coincidir con ReglaAtencion::MINUTOS_ANTES_DE_INICIAR.
const MINUTOS_ANTES_DE_INICIAR = 15;

let calendarInstance = null;
let _selectedDate = null;
let _cargando = true;
let _panel = { modo: 'dia', eventoId: null };

// ═══════════════════════════════════════
// Utilidades
// ═══════════════════════════════════════
function esc(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

function val(id, nuevo) {
    const el = document.getElementById(id);
    if (!el) return '';
    if (nuevo !== undefined) el.value = nuevo;
    return el.value;
}

function toLocalDateStr(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function inicioDelDia(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
}

/** Un día anterior a hoy solo se consulta: no se agenda ni se mueve nada a él. */
function esDiaPasado(date) {
    return inicioDelDia(date) < inicioDelDia(new Date());
}

function esHoy(date) {
    return toLocalDateStr(date) === toLocalDateStr(new Date());
}

function capitalizar(texto) {
    return texto ? texto.charAt(0).toUpperCase() + texto.slice(1) : '';
}

function formatFecha(date, opciones) {
    return date.toLocaleDateString('es-CO', opciones);
}

function format12h(time24) {
    if (!time24) return '';
    const [h, m = '00'] = String(time24).split(':');
    const horas = parseInt(h, 10);
    return `${horas % 12 || 12}:${m.padStart(2, '0')} ${horas >= 12 ? 'PM' : 'AM'}`;
}

function formatHora(date) {
    return date ? format12h(`${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`) : '';
}

function minutosDe(hora) {
    const [h, m] = String(hora).split(':');
    return parseInt(h, 10) * 60 + (parseInt(m, 10) || 0);
}

function normalizarEstado(estado) {
    const e = String(estado || '').toLowerCase();
    return e === 'encurso' ? 'en_curso' : e;
}

function unirLista(items) {
    return items.length > 1 ? `${items.slice(0, -1).join(', ')} y ${items[items.length - 1]}` : (items[0] || '');
}

function isUsuarioVeterinario() {
    return typeof USER_ROL !== 'undefined' && Number(USER_ROL) === 2;
}

function esAdmin() {
    return typeof USER_ROL !== 'undefined' && Number(USER_ROL) === 1;
}

function getUsuarioDoc() {
    return typeof USER_DOC !== 'undefined' ? USER_DOC : '';
}

// ═══════════════════════════════════════
// Catálogos (se piden una vez y se reutilizan)
// ═══════════════════════════════════════
const _catalogos = {};

function catalogo(clave, url, transformar) {
    if (!_catalogos[clave]) {
        _catalogos[clave] = fetch(url)
            .then(r => r.json())
            .then(transformar)
            .catch(e => {
                console.error(`Error al cargar ${clave}:`, e);
                delete _catalogos[clave];
                return [];
            });
    }
    return _catalogos[clave];
}

const obtenerVeterinarios = () => catalogo('vets', 'index.php?action=listar_veterinarios_ajax',
    d => (Array.isArray(d) ? d : []).map(v => ({ value: v.documento, label: v.nombre_completo })));

const obtenerTipos = () => catalogo('tipos', 'index.php?action=listar_tipos_cita_ajax',
    d => (d && d.success && Array.isArray(d.tipos) ? d.tipos : []).map(t => ({
        value: t.id_tipo_cita,
        label: `${t.nombre} · ${t.duracion_minutos} min`,
        data: { duracion: t.duracion_minutos, nombre: t.nombre }
    })));

const obtenerMascotas = () => catalogo('mascotas', 'index.php?action=listar_mascotas_ajax',
    d => (Array.isArray(d) ? d : []).map(m => ({
        id: m.id_mascota,
        nombre: m.nombre || '',
        propietario: m.propietario_nombre || m.propietario || ''
    })));

/** Llena un select una sola vez (si ya tiene opciones, no lo toca). */
function llenarSelect(select, opciones, placeholder) {
    if (!select || select.options.length > 1) return;
    select.innerHTML = `<option value="">${esc(placeholder)}</option>` + opciones.map(o => {
        const data = Object.entries(o.data || {}).map(([k, v]) => ` data-${k}="${esc(v)}"`).join('');
        return `<option value="${esc(o.value)}"${data}>${esc(o.label)}</option>`;
    }).join('');
}

// ═══════════════════════════════════════
// Filtros por tipo de evento y por veterinario
// ═══════════════════════════════════════
const FilterManager = {
    state: { cita: true, vacunacion: true, desparasitacion: true, veterinario: '' },

    init() {
        this.state.veterinario = isUsuarioVeterinario() ? getUsuarioDoc() : '';
    },

    toggle(tipo) {
        if (tipo in this.state) this.state[tipo] = !this.state[tipo];
        this.aplicar();
    },

    setVeterinario(doc) {
        this.state.veterinario = doc;
        this.aplicar();
    },

    getVeterinario() {
        return this.state.veterinario;
    },

    isActive(tipo) {
        return this.state[tipo] === true;
    },

    esVisible(ev) {
        const tipo = ev.extendedProps.tipo || 'cita';
        const doc = ev.extendedProps.doc_veterinario || '';
        const vet = this.state.veterinario;
        return this.state[tipo] !== false && (!vet || !doc || doc === vet);
    },

    /** Oculta o muestra cada evento y refresca el panel. Solo toca los que cambian. */
    aplicar() {
        if (!calendarInstance) return;
        calendarInstance.getEvents().forEach(ev => {
            const display = this.esVisible(ev) ? 'auto' : 'none';
            if (ev.display !== display) ev.setProp('display', display);
        });
        refrescarPanel();
    }
};

// ═══════════════════════════════════════
// Calendario
// ═══════════════════════════════════════
async function cargarEventos(info, exito, fallo) {
    try {
        const inicio = info.startStr.split('T')[0];
        const fin = info.endStr.split('T')[0];
        const res = await fetch(`index.php?action=listar_citas_ajax&inicio=${inicio}&fin=${fin}`);
        const datos = await res.json();

        exito((Array.isArray(datos) ? datos : []).map(e => {
            const tipo = e.tipo || 'cita';
            return {
                // Vacunas y desparasitaciones vienen de otras tablas: se prefija
                // su id para que no choque con el de una cita.
                id: tipo === 'cita' ? String(e.id_cita) : `${tipo}-${e.id_cita}`,
                title: e.mascota_nombre || 'Paciente',
                start: `${e.fecha}T${e.hora || '08:00'}`,
                extendedProps: {
                    tipo,
                    estado: tipo === 'cita' ? (normalizarEstado(e.estado) || 'pendiente') : '',
                    veterinario: e.veterinario_nombre || '',
                    doc_veterinario: e.doc_veterinario || '',
                    motivo: e.motivo || '',
                    mascota_nombre: e.mascota_nombre || '',
                    propietario_nombre: e.propietario_nombre || '',
                    tipo_cita_nombre: e.tipo_cita_nombre || ''
                }
            };
        }));
    } catch (e) {
        console.error('Error al cargar la agenda:', e);
        fallo(e);
        mostrarToast('No se pudo cargar la agenda.', 'error');
    }
}

function mountDayAddButton(arg) {
    if (arg.view.type !== 'dayGridMonth' || esDiaPasado(arg.date)) return;
    const top = arg.el.querySelector('.fc-daygrid-day-top');
    if (!top || top.querySelector('.fc-day-add-btn')) return;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'fc-day-add-btn';
    btn.setAttribute('aria-label', `Agendar cita el ${formatFecha(arg.date, { day: 'numeric', month: 'long' })}`);
    btn.innerHTML = '<i class="fas fa-plus"></i>';
    btn.addEventListener('click', e => {
        e.stopPropagation();
        seleccionarDia(arg.date);
        abrirCitaModal(arg.date);
    });
    top.appendChild(btn);
}

function tieneEventos(date) {
    return !!calendarInstance && eventosDelDia(date).length > 0;
}

/** Marca la celda del día y, salvo que se pida lo contrario, pinta su lista en el panel. */
function seleccionarDia(date, pintarLista = true) {
    _selectedDate = inicioDelDia(date);
    document.querySelectorAll('.fc-daygrid-day.is-selected').forEach(el => el.classList.remove('is-selected'));
    const celda = document.querySelector(`.fc-daygrid-day[data-date="${toLocalDateStr(_selectedDate)}"]`);
    if (celda) celda.classList.add('is-selected');
    if (pintarLista) renderPanelDia();
}

function iniciarCalendario() {
    calendarInstance = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        locale: 'es',
        firstDay: 1,
        headerToolbar: false,
        height: '100%',
        expandRows: true,
        fixedWeekCount: false,
        dayMaxEvents: true,
        eventDisplay: 'block',
        nowIndicator: true,
        allDaySlot: false,
        scrollTime: '07:00:00',
        views: { dayGridMonth: { displayEventTime: false } },
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
        editable: esAdmin(),
        eventDurationEditable: false,
        moreLinkText: n => `+${n} más`,
        moreLinkClick: info => {
            seleccionarDia(info.date);
            return 'popover';
        },
        events: cargarEventos,
        loading: cargando => {
            _cargando = cargando;
            if (!cargando) FilterManager.aplicar();
        },
        dayCellDidMount: mountDayAddButton,
        // Conserva la selección cuando FullCalendar vuelve a pintar las celdas.
        dayCellClassNames: arg => (
            _selectedDate && toLocalDateStr(arg.date) === toLocalDateStr(_selectedDate) ? ['is-selected'] : []
        ),
        dateClick: info => {
            // Días pasados: solo consulta. Sin actividad no hay nada que mostrar
            // y el clic no hace nada (antes salía un aviso y la celda temblaba
            // en rojo, castigando un clic que la vista debía prevenir).
            if (esDiaPasado(info.date) && !tieneEventos(info.date)) return;
            seleccionarDia(info.date);
        },
        eventClick: info => {
            info.jsEvent.preventDefault();
            seleccionarDia(info.event.start, false);
            mostrarDetalleCita(info.event.id);
        },
        eventDidMount: info => {
            const p = info.event.extendedProps;
            if (p.tipo === 'cita') info.el.dataset.estado = p.estado;
            else info.el.dataset.tipo = p.tipo;
            info.el.title = [
                formatHora(info.event.start),
                p.mascota_nombre,
                p.tipo === 'cita' ? ESTADOS_CITA[p.estado] : TIPOS_EVENTO[p.tipo]
            ].filter(Boolean).join(' · ');
        },
        // Arrastrar una cita a un día pasado se bloquea antes de soltarla.
        eventAllow: drop => !esDiaPasado(drop.start),
        eventDrop: reprogramarPorArrastre,
        datesSet: info => {
            const titulo = info.view.title.replace(' de ', ' ');
            document.getElementById('calCustomTitle').textContent = capitalizar(titulo);
            document.querySelectorAll('.view-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.view === info.view.type);
            });

            // Al cambiar de periodo el panel sigue a la vista: hoy si está en
            // ella y, si no, el primer día del periodo.
            const { currentStart, currentEnd } = info.view;
            const dentro = d => d >= currentStart && d < currentEnd;
            if (!_selectedDate || !dentro(_selectedDate)) {
                const hoy = inicioDelDia(new Date());
                seleccionarDia(dentro(hoy) ? hoy : currentStart);
            } else {
                seleccionarDia(_selectedDate, false);
            }
        }
    });
    calendarInstance.render();
}

function conectarBarra() {
    document.querySelectorAll('[data-nav]').forEach(btn => btn.addEventListener('click', () => {
        if (btn.dataset.nav === 'today') {
            calendarInstance.today();
            seleccionarDia(new Date());
        } else {
            calendarInstance[btn.dataset.nav]();
        }
    }));

    document.querySelectorAll('.view-tab').forEach(tab => tab.addEventListener('click', () => {
        calendarInstance.changeView(tab.dataset.view);
    }));

    document.querySelectorAll('.filter-btn').forEach(btn => btn.addEventListener('click', () => {
        FilterManager.toggle(btn.dataset.tipo);
        const activo = FilterManager.isActive(btn.dataset.tipo);
        btn.classList.toggle('active', activo);
        btn.setAttribute('aria-pressed', String(activo));
    }));

    const wrap = document.getElementById('filterVeterinarioWrap');
    const select = document.getElementById('filterVeterinario');
    if (isUsuarioVeterinario()) {
        if (wrap) wrap.hidden = true;
        return;
    }
    obtenerVeterinarios().then(vets => llenarSelect(select, vets, 'Todos los veterinarios'));
    select.addEventListener('change', () => FilterManager.setVeterinario(select.value));
}

// ═══════════════════════════════════════
// Panel lateral: lista del día y detalle
// ═══════════════════════════════════════
function eventosDelDia(date) {
    const fecha = toLocalDateStr(date);
    return calendarInstance.getEvents()
        .filter(ev => ev.start && toLocalDateStr(ev.start) === fecha)
        .sort((a, b) => a.start - b.start);
}

function refrescarPanel() {
    if (!_selectedDate) return;
    if (_panel.modo === 'detalle' && calendarInstance && calendarInstance.getEventById(_panel.eventoId)) {
        mostrarDetalleCita(_panel.eventoId);
    } else {
        renderPanelDia();
    }
}

function resumenDia(nCitas, nOtros) {
    const partes = [];
    if (nCitas) partes.push(`${nCitas} ${nCitas === 1 ? 'cita' : 'citas'}`);
    if (nOtros) partes.push(`${nOtros} ${nOtros === 1 ? 'recordatorio' : 'recordatorios'}`);
    return partes.length ? partes.join(' · ') : 'Sin actividad';
}

function renderPanelDia() {
    if (!_selectedDate || !calendarInstance) return;
    _panel = { modo: 'dia', eventoId: null };

    const date = _selectedDate;
    const pasado = esDiaPasado(date);
    const todos = eventosDelDia(date);
    const visibles = todos.filter(ev => FilterManager.esVisible(ev));
    const citas = visibles.filter(ev => ev.extendedProps.tipo === 'cita');
    const otros = visibles.filter(ev => ev.extendedProps.tipo !== 'cita');

    const diaSemana = capitalizar(formatFecha(date, { weekday: 'long' }));
    const eyebrow = pasado ? 'Día pasado · solo consulta' : (esHoy(date) ? `Hoy · ${diaSemana}` : diaSemana);

    document.getElementById('agendaPanelHead').innerHTML = `
        <div class="agenda-panel__titles">
            <p class="agenda-panel__eyebrow${pasado ? ' is-past' : ''}">${esc(eyebrow)}</p>
            <h3 class="agenda-panel__title">${esc(formatFecha(date, { day: 'numeric', month: 'long' }))}</h3>
            <p class="agenda-panel__meta">${_cargando ? 'Cargando…' : esc(resumenDia(citas.length, otros.length))}</p>
        </div>
        ${pasado ? '' : '<button type="button" class="cal-btn cal-btn--primary cal-btn--sm" data-accion="agendar"><i class="fas fa-plus"></i> Agendar</button>'}`;

    const body = document.getElementById('agendaPanelBody');
    if (_cargando) {
        body.innerHTML = '<div class="agenda-skeleton"><span></span><span></span><span></span></div>';
        return;
    }
    if (!visibles.length) {
        const texto = todos.length ? 'Hay eventos este día, pero los filtros actuales los ocultan.'
            : pasado ? 'No hubo actividad este día.'
            : 'No hay citas para este día. Usa «Agendar» para crear la primera.';
        body.innerHTML = `<div class="agenda-empty"><i class="far fa-calendar"></i><p>${texto}</p></div>`;
        return;
    }
    body.innerHTML = seccionPanel('Citas', citas) + seccionPanel('Recordatorios', otros);
}

function seccionPanel(titulo, eventos) {
    if (!eventos.length) return '';
    return `
        <section class="agenda-section">
            <h4 class="agenda-section__title">${titulo} <span>${eventos.length}</span></h4>
            ${eventos.map(itemPanel).join('')}
        </section>`;
}

function itemPanel(ev) {
    const p = ev.extendedProps;
    const esCita = p.tipo === 'cita';
    const sub = esCita
        ? [p.tipo_cita_nombre || p.motivo || 'Consulta', p.propietario_nombre].filter(Boolean).join(' · ')
        : (p.propietario_nombre || TIPOS_EVENTO[p.tipo]);
    const pill = esCita
        ? `<span class="estado-pill" data-estado="${esc(p.estado)}">${esc(ESTADOS_CITA[p.estado] || p.estado)}</span>`
        : `<span class="estado-pill" data-tipo="${esc(p.tipo)}">${esc(TIPOS_EVENTO[p.tipo] || p.tipo)}</span>`;

    return `
        <button type="button" class="agenda-item" data-accion="detalle" data-id="${esc(ev.id)}">
            <span class="agenda-item__time">${esc(formatHora(ev.start))}</span>
            <span class="agenda-item__main">
                <span class="agenda-item__name">${esc(p.mascota_nombre || ev.title)}</span>
                <span class="agenda-item__sub">${esc(sub)}</span>
            </span>
            ${pill}
        </button>`;
}

function mostrarDetalleCita(eventId) {
    const ev = calendarInstance && calendarInstance.getEventById(eventId);
    if (!ev) { renderPanelDia(); return; }
    _panel = { modo: 'detalle', eventoId: eventId };

    const p = ev.extendedProps;
    const esCita = p.tipo === 'cita';
    const estado = p.estado;
    const pasada = esDiaPasado(ev.start);

    // RN-408: iniciar, continuar y marcar "no asistió" son del veterinario de
    // la cita; confirmar, reprogramar y cancelar también los hace el
    // administrador. Una cita en curso siempre se puede retomar, aunque sea de
    // otro día, para que una atención sin cerrar no quede "en curso" para siempre.
    const esSuCita = esCita && isUsuarioVeterinario() && p.doc_veterinario === getUsuarioDoc();
    const gestiona = esSuCita || (esCita && esAdmin());
    const abierta = ['pendiente', 'confirmada'].includes(estado);
    const enAtencion = ['en_curso', 'sin_cerrar'].includes(estado);
    // RN-408: se inicia el día de la cita desde 15 minutos antes de su hora.
    const iniciaDesde = new Date(ev.start.getTime() - MINUTOS_ANTES_DE_INICIAR * 60000);
    const enDiaDeInicio = esSuCita && abierta && esHoy(ev.start);
    const puedeIniciar = enDiaDeInicio && new Date() >= iniciaDesde;
    const esperaInicio = enDiaDeInicio && !puedeIniciar;
    // RN-410 / RN-411: una atención abierta se documenta o se cierra sin consulta.
    const puedeContinuar = esSuCita && enAtencion;
    const puedeCerrarSinConsulta = esSuCita && enAtencion;
    const puedeNoAsistio = esSuCita && abierta && ev.start <= new Date();
    const puedeConfirmar = gestiona && estado === 'pendiente' && !pasada;
    const puedeReprogramar = gestiona && abierta && !pasada;

    // Una sola acción principal, según el estado; el resto, secundarias.
    const primaria = puedeIniciar ? ['iniciar', 'fa-play', 'Iniciar atención']
        : esperaInicio ? ['esperar', 'fa-clock', `Disponible desde las ${formatHora(iniciaDesde)}`]
        : puedeContinuar ? ['continuar', 'fa-stethoscope', estado === 'sin_cerrar' ? 'Registrar consulta' : 'Continuar atención']
        : puedeConfirmar ? ['confirmar', 'fa-check', 'Confirmar cita']
        : null;
    const secundarias = [];
    if (puedeConfirmar && primaria[0] !== 'confirmar') secundarias.push(['confirmar', 'fa-check', 'Confirmar']);
    if (puedeReprogramar) secundarias.push(['reprogramar', 'fa-calendar-alt', 'Reprogramar']);
    if (puedeNoAsistio) secundarias.push(['no_asistio', 'fa-user-slash', 'No asistió']);
    if (puedeCerrarSinConsulta) secundarias.push(['cerrar_sin_consulta', 'fa-folder-minus', 'Cerrar sin consulta']);

    const boton = ([accion, icono, texto], clase) =>
        `<button type="button" class="cal-btn ${clase}" data-accion="${accion}" data-id="${esc(ev.id)}"${accion === 'esperar' ? ' disabled' : ''}><i class="fas ${icono}"></i> ${texto}</button>`;

    let acciones = '';
    if (primaria || secundarias.length) {
        acciones = `
            <div class="agenda-detail__actions">
                ${primaria ? boton(primaria, 'cal-btn--primary cal-btn--block') : ''}
                ${secundarias.length ? `<div class="agenda-detail__secondary">${secundarias.map(s => boton(s, 'cal-btn--ghost')).join('')}</div>` : ''}
                ${puedeReprogramar ? boton(['cancelar', 'fa-times', 'Cancelar cita'], 'cal-btn--danger-text cal-btn--block') : ''}
            </div>`;
    } else if (esCita) {
        const nota = {
            cancelada: 'Esta cita fue cancelada.',
            completada: 'La cita ya fue atendida.',
            no_asistio: 'El paciente no asistió.',
            en_curso: 'La atención está en curso con el veterinario asignado.',
            sin_cerrar: 'La atención quedó sin cerrar; la cierra el veterinario asignado.',
            cerrada_sin_consulta: 'La atención se cerró sin consulta.'
        }[estado] || (pasada ? 'Esta cita ya pasó; queda solo para consulta.'
            : 'Solo el veterinario asignado o el administrador gestionan esta cita.');
        acciones = `<p class="agenda-detail__note">${nota}</p>`;
    }

    const filas = [
        ['Fecha', `${capitalizar(formatFecha(ev.start, { weekday: 'long', day: 'numeric', month: 'long' }))} · ${formatHora(ev.start)}`],
        ['Propietario', p.propietario_nombre],
        ['Veterinario', esCita ? p.veterinario : ''],
        ['Tipo', esCita ? (p.tipo_cita_nombre || 'Cita') : TIPOS_EVENTO[p.tipo]],
        ['Motivo', p.motivo]
    ].filter(([, valor]) => valor);

    document.getElementById('agendaPanelHead').innerHTML = `
        <button type="button" class="cal-icon-btn" data-accion="volver" aria-label="Volver al día"><i class="fas fa-arrow-left"></i></button>
        <div class="agenda-panel__titles">
            <p class="agenda-panel__eyebrow${pasada ? ' is-past' : ''}">${esCita ? 'Detalle de la cita' : esc(TIPOS_EVENTO[p.tipo])}</p>
            <h3 class="agenda-panel__title">${esc(p.mascota_nombre || ev.title)}</h3>
        </div>
        ${esCita ? `<span class="estado-pill" data-estado="${esc(estado)}">${esc(ESTADOS_CITA[estado] || estado)}</span>` : ''}`;

    document.getElementById('agendaPanelBody').innerHTML = `
        <div class="agenda-detail">
            <dl class="agenda-detail__list">
                ${filas.map(([k, v]) => `<div class="agenda-detail__row"><dt>${k}</dt><dd>${esc(v)}</dd></div>`).join('')}
            </dl>
            ${acciones}
        </div>`;
}

function manejarClicPanel(e) {
    const el = e.target.closest('[data-accion]');
    if (!el) return;
    const id = el.dataset.id;
    switch (el.dataset.accion) {
        case 'agendar': abrirCitaModal(_selectedDate); break;
        case 'detalle': mostrarDetalleCita(id); break;
        case 'volver': renderPanelDia(); break;
        case 'iniciar': iniciarAtencionCita(id); break;
        case 'continuar': continuarAtencionCita(id); break;
        case 'confirmar': confirmarCita(id); break;
        case 'reprogramar': abrirModalReprogramar(id); break;
        case 'no_asistio': marcarNoAsistio(id); break;
        case 'cancelar': cancelarCita(id); break;
        case 'cerrar_sin_consulta': cerrarSinConsulta(id); break;
    }
}

// ═══════════════════════════════════════
// Acciones sobre una cita
// ═══════════════════════════════════════
async function postCita(accion, datos) {
    const res = await fetch(`index.php?action=${accion}`, { method: 'POST', body: new URLSearchParams(datos) });
    return res.json();
}

async function iniciarAtencionCita(idCita) {
    try {
        const r = await postCita('iniciar_cita_ajax', { id_cita: idCita });
        if (!r.success) {
            mostrarToast(r.message || 'No se pudo iniciar la atención.', 'error');
            return;
        }
        // Lleva a la pantalla integral de atención.
        if (r.redirect_url) {
            window.location.href = r.redirect_url;
            return;
        }
        mostrarToast('Atención iniciada.', 'success');
        calendarInstance.refetchEvents();
    } catch (e) {
        console.error(e);
        mostrarToast('No se pudo iniciar la atención.', 'error');
    }
}

// RN-406 / RN-408: desde el calendario no se "completa" una cita; se completa
// al guardar su consulta en la pantalla de atención.
function continuarAtencionCita(idCita) {
    window.location.href = `index.php?action=vet_atencion&id_cita=${encodeURIComponent(idCita)}`;
}

async function confirmarCita(idCita) {
    try {
        const r = await postCita('confirmar_cita_ajax', { id_cita: idCita });
        if (!r.success) {
            mostrarToast(r.message || 'No se pudo confirmar la cita.', 'error');
            return;
        }
        mostrarToast('Cita confirmada.', 'success');
        calendarInstance.refetchEvents();
        if (r.id_cita) enviarCorreoCita(r.id_cita, 'confirmacion');
    } catch (e) {
        console.error(e);
        mostrarToast('Error de conexión al confirmar la cita.', 'error');
    }
}

// RN-409: el paciente no llegó. La cita se cierra y libera su espacio.
async function marcarNoAsistio(idCita) {
    const confirmacion = await Swal.fire({
        title: '¿Marcar como no asistió?',
        text: 'La cita quedará cerrada y su espacio en la agenda se libera.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0052FF',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Sí, no asistió',
        cancelButtonText: 'Volver'
    });
    if (!confirmacion.isConfirmed) return;

    try {
        const r = await postCita('marcar_no_asistio_ajax', { id_cita: idCita });
        mostrarToast(r.message || (r.success ? 'Inasistencia registrada.' : 'No se pudo marcar la inasistencia.'), r.success ? 'success' : 'error');
        if (r.success) calendarInstance.refetchEvents();
    } catch (e) {
        console.error(e);
        mostrarToast('No se pudo marcar la inasistencia.', 'error');
    }
}

async function cancelarCita(idCita) {
    const confirmacion = await Swal.fire({
        title: '¿Cancelar la cita?',
        text: 'Se notificará al propietario. Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, mantener'
    });
    if (!confirmacion.isConfirmed) return;

    try {
        const r = await postCita('cancelar_cita_ajax', { id_cita: idCita });
        if (!r.success) {
            mostrarToast(r.message || 'No se pudo cancelar la cita.', 'error');
            return;
        }
        mostrarToast('Cita cancelada.', 'success');
        calendarInstance.refetchEvents();
        if (r.id_cita) enviarCorreoCita(r.id_cita, 'cancelacion');
    } catch (e) {
        console.error(e);
        mostrarToast('Error de conexión al cancelar la cita.', 'error');
    }
}

// RN-411: cerrar sin consulta una atención que no se va a documentar.
async function cerrarSinConsulta(idCita) {
    const { value: motivo } = await Swal.fire({
        title: 'Cerrar sin consulta',
        text: 'La cita queda cerrada sin historia clínica y no se puede reabrir. Si el paciente vuelve, agenda una cita nueva.',
        input: 'textarea',
        inputLabel: 'Motivo del cierre',
        inputPlaceholder: 'Ej. se inició por error, el paciente se retiró antes de la consulta…',
        inputAttributes: { maxlength: 255 },
        showCancelButton: true,
        confirmButtonColor: '#0052FF',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Cerrar atención',
        cancelButtonText: 'Volver',
        inputValidator: valor => ((valor || '').trim().length < 5 ? 'Escribe el motivo (mínimo 5 caracteres).' : undefined)
    });
    if (!motivo) return;

    try {
        const r = await postCita('cerrar_sin_consulta_ajax', { id_cita: idCita, motivo: motivo.trim() });
        mostrarToast(r.message || (r.success ? 'Atención cerrada sin consulta.' : 'No se pudo cerrar la atención.'), r.success ? 'success' : 'error');
        if (r.success) calendarInstance.refetchEvents();
    } catch (e) {
        console.error(e);
        mostrarToast('Error de conexión al cerrar la atención.', 'error');
    }
}

function reprogramarPorArrastre(info) {
    const inicio = info.event.start;
    postCita('reprogramar_cita_ajax', {
        id_cita: info.event.id,
        fecha: toLocalDateStr(inicio),
        hora: `${String(inicio.getHours()).padStart(2, '0')}:${String(inicio.getMinutes()).padStart(2, '0')}`
    }).then(r => {
        if (!r.success) {
            info.revert();
            mostrarToast(r.message || 'El horario no está disponible para ese veterinario.', 'error');
            return;
        }
        mostrarToast('Cita reprogramada.', 'success');
        if (r.id_cita) enviarCorreoCita(r.id_cita, 'reprogramacion');
    }).catch(() => {
        info.revert();
        mostrarToast('Error de conexión al reprogramar la cita.', 'error');
    });
}

// Correo al propietario tras agendar, confirmar, reprogramar o cancelar.
// Va en segundo plano (sinLoader): el usuario no tiene por qué esperar al
// SMTP, y keepalive deja terminar el envío aunque cambie de página.
function enviarCorreoCita(idCita, tipo) {
    fetch('index.php?action=enviar_email_ajax', {
        method: 'POST',
        body: new URLSearchParams({ id_cita: idCita, tipo }),
        keepalive: true,
        sinLoader: true
    }).catch(e => console.error('Error enviando el correo de la cita:', e));
}

// ═══════════════════════════════════════
// Selector de horarios (compartido por ambos modales)
// ═══════════════════════════════════════
function setSlotsEstado(id, estado, texto = '') {
    const cont = document.getElementById(id);
    cont.onclick = null;
    if (estado === 'cargando') {
        cont.innerHTML = `<div class="slot-grid">${'<span class="slot-skeleton"></span>'.repeat(12)}</div>`;
        return;
    }
    const icono = estado === 'guia' ? 'far fa-clock' : 'far fa-calendar-times';
    cont.innerHTML = `<div class="slot-state"><i class="${icono}"></i><p>${esc(texto)}</p></div>`;
}

/** Si la fecha es hoy, descarta las horas que ya pasaron. */
function quitarHorasPasadas(fecha, horas) {
    if (fecha !== toLocalDateStr(new Date())) return horas;
    const ahora = new Date();
    const minutosAhora = ahora.getHours() * 60 + ahora.getMinutes();
    return horas.filter(h => minutosDe(h) > minutosAhora);
}

/** Pinta los horarios repartidos en Mañana y Tarde para que el panel nunca crezca. */
function renderSlots(id, horas, alElegir) {
    const cont = document.getElementById(id);
    const grupos = [
        ['manana', 'Mañana', horas.filter(h => minutosDe(h) < 720)],
        ['tarde', 'Tarde', horas.filter(h => minutosDe(h) >= 720)]
    ].filter(([, , hs]) => hs.length);

    const cabecera = grupos.length > 1
        ? `<div class="slot-tabs" role="tablist">${grupos.map(([clave, nombre, hs], i) =>
            `<button type="button" role="tab" class="slot-tab${i ? '' : ' is-active'}" data-franja="${clave}" aria-selected="${!i}">${nombre} <span>${hs.length}</span></button>`
          ).join('')}</div>`
        : `<p class="slot-caption">${grupos[0][1]} · ${grupos[0][2].length} ${grupos[0][2].length === 1 ? 'horario libre' : 'horarios libres'}</p>`;

    cont.innerHTML = cabecera + grupos.map(([clave, , hs], i) =>
        `<div class="slot-grid" data-franja="${clave}"${i ? ' hidden' : ''}>${hs.map(h =>
            `<button type="button" class="slot-chip" data-hora="${esc(h)}">${esc(format12h(h))}</button>`
        ).join('')}</div>`
    ).join('');

    cont.onclick = e => {
        const tab = e.target.closest('.slot-tab');
        if (tab) {
            cont.querySelectorAll('.slot-tab').forEach(t => {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', String(t === tab));
            });
            cont.querySelectorAll('.slot-grid').forEach(g => { g.hidden = g.dataset.franja !== tab.dataset.franja; });
            return;
        }
        const chip = e.target.closest('.slot-chip');
        if (chip) {
            cont.querySelectorAll('.slot-chip').forEach(c => c.classList.toggle('is-selected', c === chip));
            alElegir(chip.dataset.hora);
        }
    };
}

// ═══════════════════════════════════════
// Modales: abrir y cerrar
// ═══════════════════════════════════════
function abrirOverlay(id) {
    document.getElementById(id).classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

function cerrarOverlay(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('is-open');
    if (!document.querySelector('.zk-overlay.is-open')) document.body.style.overflow = '';
}

// ═══════════════════════════════════════
// Modal: agendar cita
// ═══════════════════════════════════════
let _mascotaNombre = '';
let _turnoSlotsCita = 0;

function getVetModal() {
    return isUsuarioVeterinario() ? val('modal_veterinario_hidden') : val('modal_veterinario');
}

function textoGuiaSlots() {
    return isUsuarioVeterinario()
        ? 'Elige el tipo de cita para ver los horarios libres.'
        : 'Elige el veterinario y el tipo de cita para ver los horarios libres.';
}

function abrirCitaModal(date) {
    if (!date || esDiaPasado(date)) return;
    const fecha = inicioDelDia(date);
    const esVet = isUsuarioVeterinario();

    val('modal_fecha', toLocalDateStr(fecha));
    document.getElementById('citaModalFechaLabel').textContent =
        capitalizar(formatFecha(fecha, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })) +
        (esVet ? ' · Tu agenda' : '');

    limpiarMascotaSeleccionada();
    ['modal_tipo_cita', 'modal_duracion_minutos', 'modal_motivo', 'modal_hora'].forEach(id => val(id, ''));
    document.getElementById('cm_vet_field').hidden = esVet;
    if (esVet) val('modal_veterinario_hidden', getUsuarioDoc());

    ocultarErrorModal();
    setSlotsEstado('modal_slots_container', 'guia', textoGuiaSlots());
    actualizarResumenModal();
    abrirOverlay('citaModalOverlay');
    setTimeout(() => document.getElementById('cm_mascota_search').focus(), 60);

    Promise.all([esVet ? [] : obtenerVeterinarios(), obtenerTipos()]).then(([vets, tipos]) => {
        llenarSelect(document.getElementById('modal_veterinario'), vets, 'Selecciona un veterinario');
        llenarSelect(document.getElementById('modal_tipo_cita'), tipos, 'Selecciona el tipo');
        // Recepción/administración: si filtró la agenda por un veterinario, se propone ese.
        if (!esVet && FilterManager.getVeterinario()) {
            val('modal_veterinario', FilterManager.getVeterinario());
            actualizarResumenModal();
        }
    });
    obtenerMascotas(); // precarga para que el buscador responda al instante
}

function closeCitaModal() {
    cerrarOverlay('citaModalOverlay');
    document.getElementById('cm_mascota_dropdown').classList.remove('is-open');
}

async function filtrarMascotas(texto) {
    const dropdown = document.getElementById('cm_mascota_dropdown');
    const q = texto.trim().toLowerCase();
    if (!q) {
        dropdown.classList.remove('is-open');
        dropdown.innerHTML = '';
        return;
    }
    const mascotas = await obtenerMascotas();
    const resultados = mascotas
        .filter(m => m.nombre.toLowerCase().includes(q) || m.propietario.toLowerCase().includes(q))
        .slice(0, 8);

    dropdown.innerHTML = resultados.length
        ? resultados.map(m => `
            <button type="button" class="zk-option" role="option" data-id="${esc(m.id)}" data-nombre="${esc(m.nombre)}" data-propietario="${esc(m.propietario)}">
                <span class="zk-option__name">${esc(m.nombre)}</span>
                ${m.propietario ? `<span class="zk-option__sub">${esc(m.propietario)}</span>` : ''}
            </button>`).join('')
        : '<p class="zk-dropdown__empty">Sin resultados</p>';
    dropdown.classList.add('is-open');
}

function seleccionarMascota(id, nombre, propietario) {
    _mascotaNombre = nombre;
    val('modal_mascota', id);
    document.getElementById('cm_mascota_chip_name').textContent = propietario ? `${nombre} · ${propietario}` : nombre;
    document.getElementById('cm_mascota_chip').hidden = false;
    document.getElementById('cm_mascota_wrap').hidden = true;
    document.getElementById('cm_mascota_dropdown').classList.remove('is-open');
    ocultarErrorModal();
    actualizarResumenModal();
}

function limpiarMascotaSeleccionada(enfocar = false) {
    _mascotaNombre = '';
    val('modal_mascota', '');
    val('cm_mascota_search', '');
    document.getElementById('cm_mascota_chip').hidden = true;
    document.getElementById('cm_mascota_wrap').hidden = false;
    const dropdown = document.getElementById('cm_mascota_dropdown');
    dropdown.classList.remove('is-open');
    dropdown.innerHTML = '';
    actualizarResumenModal();
    if (enfocar) document.getElementById('cm_mascota_search').focus();
}

function onModalVeterinarioChange() {
    cargarSlotsModal();
}

function onModalTipoCitaChange() {
    const select = document.getElementById('modal_tipo_cita');
    const opcion = select.options[select.selectedIndex];
    val('modal_duracion_minutos', select.value ? (opcion.dataset.duracion || '') : '');
    cargarSlotsModal();
}

async function cargarSlotsModal() {
    const vet = getVetModal();
    const fecha = val('modal_fecha');
    const duracion = val('modal_duracion_minutos');
    val('modal_hora', '');
    ocultarErrorModal();
    actualizarResumenModal();

    if (!vet || !val('modal_tipo_cita') || !duracion) {
        setSlotsEstado('modal_slots_container', 'guia', textoGuiaSlots());
        return;
    }

    // Si el usuario cambia de tipo o de veterinario antes de que llegue la
    // respuesta, la vieja se descarta para no pintar horarios equivocados.
    const turno = ++_turnoSlotsCita;
    setSlotsEstado('modal_slots_container', 'cargando');

    try {
        const [horarios, sugerencias] = await Promise.all([
            fetch(`index.php?action=get_horas_disponibles_ajax&fecha=${fecha}&intervalo=${duracion}`).then(r => r.json()),
            fetch(`index.php?action=get_sugerencias_horario_ajax&doc_veterinario=${encodeURIComponent(vet)}&fecha=${fecha}&duracion_minutos=${duracion}&modo=normal`).then(r => r.json())
        ]);
        if (turno !== _turnoSlotsCita) return;

        const laborales = horarios.success && Array.isArray(horarios.horas) ? horarios.horas : [];
        if (!laborales.length) {
            setSlotsEstado('modal_slots_container', 'vacio', 'Este día no es laborable o no tiene horarios configurados.');
            return;
        }

        let horas = sugerencias.success && Array.isArray(sugerencias.sugerencias) ? sugerencias.sugerencias : [];
        const enHorario = horas.filter(h => laborales.includes(h));
        // Si ninguna coincide exacto (formatos distintos), se muestran las sugerencias tal cual.
        if (enHorario.length) horas = enHorario;
        horas = quitarHorasPasadas(fecha, horas);

        if (!horas.length) {
            setSlotsEstado('modal_slots_container', 'vacio', 'No quedan horarios libres este día. Prueba con otro día.');
            return;
        }
        renderSlots('modal_slots_container', horas, hora => {
            val('modal_hora', hora);
            ocultarErrorModal();
            actualizarResumenModal();
        });
    } catch (e) {
        if (turno !== _turnoSlotsCita) return;
        console.error('Error al cargar horarios:', e);
        setSlotsEstado('modal_slots_container', 'vacio', 'No se pudieron cargar los horarios. Intenta de nuevo.');
    }
}

/** Resumen en vivo en el pie; el botón se habilita solo con todo completo. */
function actualizarResumenModal() {
    const resumen = document.getElementById('cm_resumen');
    const submit = document.getElementById('cm_submit');
    const tipoSel = document.getElementById('modal_tipo_cita');
    if (!resumen || !submit || !tipoSel) return;

    const hora = val('modal_hora');
    const faltan = [];
    if (!val('modal_mascota')) faltan.push('mascota');
    if (!getVetModal()) faltan.push('veterinario');
    if (!tipoSel.value) faltan.push('tipo de cita');
    if (!hora) faltan.push('horario');

    submit.disabled = faltan.length > 0;
    const tipoNombre = tipoSel.value ? (tipoSel.options[tipoSel.selectedIndex].dataset.nombre || '') : '';
    resumen.innerHTML = faltan.length
        ? `Falta: ${esc(unirLista(faltan))}.`
        : `<strong>${esc(_mascotaNombre)}</strong> · ${esc(tipoNombre)} · ${esc(format12h(hora))}`;
}

function mostrarErrorModal(mensaje) {
    document.getElementById('modal_error_message').textContent = mensaje;
    document.getElementById('modal_error_container').hidden = false;
    document.getElementById('cm_resumen').hidden = true;
}

function ocultarErrorModal() {
    const error = document.getElementById('modal_error_container');
    if (!error) return;
    error.hidden = true;
    document.getElementById('cm_resumen').hidden = false;
}

async function crearCitaModal(e) {
    e.preventDefault();
    actualizarResumenModal();
    const submit = document.getElementById('cm_submit');
    if (submit.disabled) return;

    const datos = {
        id_mascota: val('modal_mascota'),
        doc_veterinario: getVetModal(),
        fecha: val('modal_fecha'),
        hora: val('modal_hora'),
        motivo: val('modal_motivo'),
        id_tipo_cita: val('modal_tipo_cita'),
        duracion_minutos: val('modal_duracion_minutos')
    };

    submit.disabled = true;
    submit.textContent = 'Agendando…';
    try {
        const r = await postCita('registrar_cita_ajax', datos);
        if (!r.success) {
            mostrarErrorModal(r.message || 'No se pudo agendar la cita.');
            return;
        }
        closeCitaModal();
        mostrarToast(`Cita agendada: ${_mascotaNombre}, ${format12h(datos.hora)}.`, 'success');
        seleccionarDia(new Date(`${datos.fecha}T12:00:00`));
        calendarInstance.refetchEvents();
        if (r.id_cita) enviarCorreoCita(r.id_cita, 'confirmacion_nueva');
    } catch (err) {
        mostrarErrorModal('Error de conexión. Intenta nuevamente.');
    } finally {
        submit.textContent = 'Agendar cita';
        actualizarResumenModal();
    }
}

// ═══════════════════════════════════════
// Modal: reprogramar cita
// ═══════════════════════════════════════
let _reprogramarCitaId = null;
let _turnoSlotsReprog = 0;

function actualizarBotonReprog() {
    document.getElementById('reprogramar_btn_confirmar').disabled = !val('reprogramar_hora');
}

function mostrarErrorReprog(mensaje) {
    document.getElementById('reprog_error_message').textContent = mensaje;
    document.getElementById('reprog_error').hidden = false;
}

function ocultarErrorReprog() {
    document.getElementById('reprog_error').hidden = true;
}

function cerrarModalReprogramar() {
    cerrarOverlay('reprogramarModalOverlay');
    _reprogramarCitaId = null;
}

async function abrirModalReprogramar(idCita) {
    _reprogramarCitaId = idCita;
    ocultarErrorReprog();
    val('reprogramar_hora', '');
    actualizarBotonReprog();
    setSlotsEstado('reprogramar_slots_container', 'cargando');

    const ev = calendarInstance.getEventById(idCita);
    document.getElementById('reprogSubtitle').textContent = ev
        ? `${ev.extendedProps.mascota_nombre} · hoy está el ${formatFecha(ev.start, { day: 'numeric', month: 'long' })} a las ${formatHora(ev.start)}`
        : 'Elige la nueva fecha y el horario';

    const fechaIn = document.getElementById('reprogramar_fecha');
    fechaIn.min = toLocalDateStr(new Date()); // no se reprograma hacia atrás
    abrirOverlay('reprogramarModalOverlay');

    try {
        const [datos, vets, tipos] = await Promise.all([
            fetch(`index.php?action=get_cita_ajax&id=${encodeURIComponent(idCita)}`).then(r => r.json()),
            obtenerVeterinarios(),
            obtenerTipos()
        ]);
        if (!datos.success) {
            cerrarModalReprogramar();
            mostrarToast('No se pudo cargar la cita.', 'error');
            return;
        }
        const cita = datos.cita;
        const vetSel = document.getElementById('reprogramar_veterinario');
        const tipoSel = document.getElementById('reprogramar_tipo_cita');
        llenarSelect(vetSel, vets, 'Selecciona un veterinario');
        llenarSelect(tipoSel, tipos, 'Selecciona el tipo');

        vetSel.value = cita.doc_veterinario || '';
        vetSel.disabled = isUsuarioVeterinario(); // el veterinario reprograma su propia agenda
        tipoSel.value = cita.id_tipo_cita || '';
        fechaIn.value = cita.fecha && cita.fecha >= fechaIn.min ? cita.fecha : fechaIn.min;
        cargarSlotsReprogramar();
    } catch (e) {
        console.error('Error al abrir reprogramar:', e);
        cerrarModalReprogramar();
        mostrarToast('Error al cargar los datos de la cita.', 'error');
    }
}

async function cargarSlotsReprogramar() {
    const vet = val('reprogramar_veterinario');
    const fecha = val('reprogramar_fecha');
    const tipoSel = document.getElementById('reprogramar_tipo_cita');
    const duracion = (tipoSel.options[tipoSel.selectedIndex] || {}).dataset?.duracion || '30';
    const id = 'reprogramar_slots_container';

    val('reprogramar_hora', '');
    actualizarBotonReprog();
    ocultarErrorReprog();

    if (!vet || !fecha) {
        setSlotsEstado(id, 'guia', 'Elige la nueva fecha y el veterinario para ver los horarios libres.');
        return;
    }
    if (esDiaPasado(new Date(`${fecha}T12:00:00`))) {
        setSlotsEstado(id, 'vacio', 'No se puede reprogramar a un día pasado.');
        return;
    }

    const turno = ++_turnoSlotsReprog;
    setSlotsEstado(id, 'cargando');
    try {
        const url = `index.php?action=get_sugerencias_horario_ajax&doc_veterinario=${encodeURIComponent(vet)}&fecha=${encodeURIComponent(fecha)}&duracion_minutos=${duracion}&id_cita_excluir=${encodeURIComponent(_reprogramarCitaId || '')}`;
        const data = await fetch(url).then(r => r.json());
        if (turno !== _turnoSlotsReprog) return;

        const horas = quitarHorasPasadas(fecha, data.success && Array.isArray(data.sugerencias) ? data.sugerencias : []);
        if (!horas.length) {
            setSlotsEstado(id, 'vacio', 'No hay horarios libres ese día. Prueba con otra fecha o veterinario.');
            return;
        }
        renderSlots(id, horas, hora => {
            val('reprogramar_hora', hora);
            ocultarErrorReprog();
            actualizarBotonReprog();
        });
    } catch (e) {
        if (turno !== _turnoSlotsReprog) return;
        console.error('Error al cargar horarios:', e);
        setSlotsEstado(id, 'vacio', 'No se pudieron cargar los horarios. Intenta de nuevo.');
    }
}

async function confirmarReprogramacion() {
    const hora = val('reprogramar_hora');
    const fecha = val('reprogramar_fecha');
    if (!hora) return;

    const btn = document.getElementById('reprogramar_btn_confirmar');
    btn.disabled = true;
    btn.textContent = 'Reprogramando…';
    try {
        const r = await postCita('reprogramar_cita_ajax', {
            id_cita: _reprogramarCitaId,
            fecha,
            hora,
            doc_veterinario: val('reprogramar_veterinario')
        });
        if (!r.success) {
            mostrarErrorReprog(r.message || 'No se pudo reprogramar la cita.');
            return;
        }
        cerrarModalReprogramar();
        mostrarToast(`Cita reprogramada para el ${formatFecha(new Date(`${fecha}T12:00:00`), { day: 'numeric', month: 'long' })}, ${format12h(hora)}.`, 'success');
        seleccionarDia(new Date(`${fecha}T12:00:00`));
        calendarInstance.refetchEvents();
        if (r.id_cita) enviarCorreoCita(r.id_cita, 'reprogramacion');
    } catch (e) {
        console.error('Error al reprogramar:', e);
        mostrarErrorReprog('Error de conexión. Intenta nuevamente.');
    } finally {
        btn.textContent = 'Confirmar reprogramación';
        actualizarBotonReprog();
    }
}

// ═══════════════════════════════════════
// Avisos
// ═══════════════════════════════════════
function mostrarToast(mensaje, tipo = 'success') {
    const previo = document.getElementById('calToast');
    if (previo) previo.remove();

    const icono = { success: 'fa-check-circle', info: 'fa-info-circle', error: 'fa-exclamation-circle' }[tipo] || 'fa-info-circle';
    const toast = document.createElement('div');
    toast.id = 'calToast';
    toast.className = `cal-toast cal-toast--${tipo}`;
    toast.setAttribute('role', 'status');
    toast.innerHTML = `<i class="fas ${icono}"></i><span>${esc(mensaje)}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('is-leaving');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// ═══════════════════════════════════════
// Arranque
// ═══════════════════════════════════════

// Los modales van al final del body: dentro del contenido quedaban atrapados
// en su contexto de apilamiento y no pasaban por encima de la barra lateral.
function moverModalesAlBody() {
    ['citaModalOverlay', 'reprogramarModalOverlay'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.parentNode !== document.body) document.body.appendChild(el);
    });
}

document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (document.getElementById('reprogramarModalOverlay')?.classList.contains('is-open')) cerrarModalReprogramar();
    else if (document.getElementById('citaModalOverlay')?.classList.contains('is-open')) closeCitaModal();
    document.querySelectorAll('.fc-popover').forEach(p => p.remove());
});

document.addEventListener('click', e => {
    // Cierra la lista de mascotas al hacer clic fuera del buscador.
    const wrap = document.getElementById('cm_mascota_wrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('cm_mascota_dropdown')?.classList.remove('is-open');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    moverModalesAlBody();
    FilterManager.init();
    conectarBarra();
    document.getElementById('agendaPanel').addEventListener('click', manejarClicPanel);
    document.getElementById('cm_mascota_dropdown').addEventListener('click', e => {
        const opcion = e.target.closest('.zk-option');
        if (opcion) seleccionarMascota(opcion.dataset.id, opcion.dataset.nombre, opcion.dataset.propietario);
    });
    iniciarCalendario();
});
