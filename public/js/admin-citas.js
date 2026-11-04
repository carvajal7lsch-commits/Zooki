/** RE-6.2.5 / HU-4.13: supervisión de citas de la clínica activa. */
let todasLasCitas = [];
let citasFiltradasGlobal = [];
let veterinariosMap = {};
let veterinariosArray = [];
let tiposCita = [];
const estadosCitasAdmin = { pendiente: 'Pendiente', confirmada: 'Confirmada', en_curso: 'En curso', completada: 'Completada', no_asistio: 'No asistió', sin_cerrar: 'Sin cerrar', cancelada: 'Cancelada' };
const campoCitas = id => document.getElementById(id);
const movilCitas = window.matchMedia('(max-width: 767px)');
let lecturaCitas = 0;
let rangoPeriodoCitas = 'Todas las fechas';

document.addEventListener('DOMContentLoaded', () => {
    const cancelar = document.querySelector('[data-cancelar-cita-admin]');
    if (cancelar) {
        let enviando = false;
        const comando = zookiCrearComandoAgenda();
        const motivo = cancelar.elements.namedItem('motivo');
        const boton = cancelar.querySelector('[type="submit"]');
        const aviso = cancelar.querySelector('[data-cancelacion-error]');
        const validar = () => {
            const texto = motivo.value.trim();
            const valido = texto.length > 0 && Array.from(texto).length <= 255 && !/\p{C}/u.test(texto);
            aviso.textContent = valido ? '' : 'Indica el motivo (1 a 255 caracteres).';
            boton.disabled = enviando || !valido;
            return valido;
        };
        motivo.addEventListener('input', validar);
        cancelar.addEventListener('submit', async evento => {
            evento.preventDefault();
            if (enviando || !validar()) return;
            if (!(await zookiConfirmarCorto('¿Cancelar esta cita? Se conservará el motivo en auditoría.', 'Cancelar cita', [], 'Mantener cita'))) return;
            enviando = true;
            validar();
            try {
                const datos = new URLSearchParams(new FormData(cancelar));
                datos.set('id_cita', cancelar.dataset.idCita);
                datos.set('clave_operacion', comando.clave(datos));
                const respuesta = await (await fetch('index.php?action=cancelar_cita_ajax', { method: 'POST', body: datos })).json();
                if (!respuesta.success) throw new Error(respuesta.message || 'No se pudo cancelar.');
                comando.reiniciar();
                zookiCerrarDialogo(cancelar);
                await cargarDatosIniciales();
                zookiToast(respuesta.message || 'Cita cancelada.', 'success');
            } catch (error) {
                aviso.textContent = error.message;
            } finally {
                enviando = false;
                boton.disabled = false;
            }
        });
    }
    const selectores = document.querySelector('[data-citas-selectores]');
    const fecha = document.querySelector('[data-citas-fecha]');
    const exportar = document.querySelector('[data-citas-exportar]');
    function situar() {
        const dialogo = campoCitas('citasFiltros');
        if (dialogo.open) dialogo.close();
        document.querySelector(movilCitas.matches ? '[data-citas-selectores-destino]' : '[data-citas-selectores-origen]').append(selectores);
        document.querySelector(movilCitas.matches ? '[data-citas-fecha-destino]' : '[data-citas-fecha-origen]').append(fecha);
        document.querySelector(movilCitas.matches ? '.top-header--citas .header-left' : '[data-citas-exportar-origen]').append(exportar);
        const entrada = campoCitas('filtroFechaCustom');
        if (entrada._flatpickr) entrada._flatpickr.destroy();
        if (window.flatpickr && window.SelectorFechas) {
            SelectorFechas.iniciar('#filtroFechaCustom', {
                maxDate: null,
                altFormat: 'd/m/Y',
                appendTo: movilCitas.matches ? dialogo : document.body,
                position: instancia => {
                    const calendario = instancia.calendarContainer;
                    const ancla = (instancia.altInput || entrada).getBoundingClientRect();
                    calendario.classList.add('citas-calendario');
                    calendario.style.left = Math.max(8, Math.min(ancla.left, window.innerWidth - calendario.offsetWidth - 8)) + 'px';
                    calendario.style.top = Math.max(8, Math.min(ancla.bottom + 6, window.innerHeight - calendario.offsetHeight - 8)) + 'px';
                },
                onChange: () => {
                    campoCitas('filtroFecha').value = entrada.value ? 'custom' : 'hoy';
                    aplicarFiltros();
                }
            });
        }
        checkScrollButtons();
    }
    document.querySelectorAll('[data-citas-periodo]').forEach(boton => {
        boton.addEventListener('click', () => {
            campoCitas('filtroFecha').value = boton.dataset.citasPeriodo;
            aplicarFiltros();
        });
    });
    document.querySelector('[data-citas-calendario]').addEventListener('click', () => {
        const fecha = campoCitas('filtroFechaCustom');
        if (fecha._flatpickr) fecha._flatpickr.open();
        else {
            fecha.type = 'date';
            fecha.showPicker?.();
        }
    });
    document.querySelectorAll('[data-filtrar-citas]').forEach(campo => campo.addEventListener('change', () => {
        if (campo.id === 'filtroFechaCustom') campoCitas('filtroFecha').value = campo.value ? 'custom' : 'hoy';
        aplicarFiltros();
    }));
    campoCitas('filtroSearch').addEventListener('input', aplicarFiltros);
    const estados = document.querySelector('[data-citas-estados]');
    for (const opcion of campoCitas('filtroEstado').options) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.dataset.citasEstado = opcion.value;
        boton.textContent = opcion.value ? opcion.textContent : 'Todos';
        boton.addEventListener('click', () => {
            campoCitas('filtroEstado').value = opcion.value;
            aplicarFiltros();
        });
        estados.append(boton);
    }
    campoCitas('kanbanWrapper').addEventListener('scroll', checkScrollButtons);
    document.querySelector('[data-citas-veterinarios]').addEventListener('click', evento => {
        const boton = evento.target.closest('[data-citas-vet]');
        if (!boton) return;
        campoCitas('filtroVet').value = boton.dataset.citasVet;
        aplicarFiltros();
    });
    const contarPaneles = () => {
        const solicitudes = document.querySelector('[data-solicitudes-lista]');
        const pendientes = campoCitas('agenda-panel-triage').querySelector('[data-solicitudes-filtro]').value === '0';
        if (pendientes) document.querySelector('[data-citas-conteo="triage"]').textContent = solicitudes.children.length;
        document.querySelector('[data-citas-conteo="urgencias"]').textContent = document.querySelector('[data-rojos-lista]').children.length;
    };
    new MutationObserver(contarPaneles).observe(campoCitas('citasAdmin'), { childList: true, subtree: true });
    // Carga los conteos al entrar sin cambiar el panel seleccionado ni la autorización.
    campoCitas('agenda-panel-triage').dispatchEvent(new CustomEvent('agenda:mostrar'));
    document.querySelector('[data-solicitudes-filtro]').addEventListener('change', contarPaneles);
    const originalFiltro = document.querySelector('[data-solicitudes-filtro]');
    const segmento = document.createElement('div');
    segmento.className = 'citas-triage-filtros';
    for (const opcion of originalFiltro.options) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.textContent = opcion.textContent;
        boton.dataset.citasRevision = opcion.value;
        boton.addEventListener('click', () => {
            originalFiltro.value = opcion.value;
            originalFiltro.dispatchEvent(new Event('change'));
            segmento.querySelectorAll('button').forEach(n => n.setAttribute('aria-pressed', String(n === boton)));
        });
        boton.setAttribute('aria-pressed', String(opcion.value === originalFiltro.value));
        segmento.append(boton);
    }
    originalFiltro.closest('label').hidden = true;
    originalFiltro.closest('.ah-acciones').prepend(segmento);
    movilCitas.addEventListener('change', situar);
    window.addEventListener('resize', checkScrollButtons);
    document.addEventListener('click', evento => {
        if (!evento.target.closest('[data-citas-exportar]') || evento.target.closest('[data-ui-accion]')) exportar.open = false;
    });
    situar();
    cargarDatosIniciales();
});

async function cargarDatosIniciales() {
    const lectura = ++lecturaCitas;
    try {
        const respuestas = await Promise.all([fetch('index.php?action=listar_todas_citas_ajax'), fetch('index.php?action=listar_veterinarios_ajax')]);
        const datos = await Promise.all(respuestas.map(r => r.json()));
        if (lectura !== lecturaCitas) return;
        if (!datos[0].success) throw new Error(datos[0].message || 'No se pudieron cargar las citas.');
        todasLasCitas = datos[0].citas;
        veterinariosArray = Array.isArray(datos[1]) ? datos[1] : [];
        veterinariosMap = Object.fromEntries(veterinariosArray.map(v => [String(v.id_usuario), v.nombre_completo]));
        const vet = campoCitas('filtroVet');
        const previoVet = vet.value;
        vet.replaceChildren(new Option('Todo el equipo', ''));
        veterinariosArray.forEach(v => vet.add(new Option(v.nombre_completo, String(v.id_usuario))));
        vet.value = veterinariosArray.some(v => String(v.id_usuario) === previoVet) ? previoVet : '';
        const tipo = campoCitas('filtroTipoCita');
        const previoTipo = tipo.value;
        tiposCita = [...new Set(todasLasCitas.map(c => c.tipo_cita_nombre).filter(Boolean))];
        tipo.replaceChildren(new Option('Todos los tipos', ''));
        tiposCita.forEach(nombre => tipo.add(new Option(nombre, nombre)));
        tipo.value = tiposCita.includes(previoTipo) ? previoTipo : '';
        aplicarFiltros();
    } catch (error) {
        const salida = document.createElement('p');
        salida.className = 'citas-vacio';
        salida.setAttribute('role', 'status');
        salida.textContent = 'Error al cargar las citas: ' + error.message;
        campoCitas('kanbanBoard').replaceChildren(salida);
    }
}
function filtrarCitas() {
    aplicarFiltros();
}
function scrollKanban(direccion) {
    const wrapper = campoCitas('kanbanWrapper');
    wrapper.scrollBy({ left: (direccion === 'left' ? -1 : 1) * (wrapper.querySelector('.kanban-col')?.clientWidth || 300), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
}
function checkScrollButtons() {
    const wrapper = campoCitas('kanbanWrapper');
    campoCitas('btnScrollLeft').hidden = movilCitas.matches || wrapper.scrollLeft <= 0;
    campoCitas('btnScrollRight').hidden = movilCitas.matches || wrapper.scrollLeft + wrapper.clientWidth >= wrapper.scrollWidth - 2;
}
function fechaCitasTexto(fecha) {
    return String(fecha || '').split('-').reverse().join('/');
}
function actualizarMétricas(citas) {
    campoCitas('kpi-total').textContent = citas.length;
    campoCitas('kpi-pendientes').textContent = citas.filter(c => ['pendiente', 'confirmada'].includes(c.estado)).length;
    campoCitas('kpi-completadas').textContent = citas.filter(c => c.estado === 'completada').length;
    campoCitas('kpi-canceladas').textContent = citas.filter(c => c.estado === 'cancelada').length;
    for (const [id, singular, plural] of [['total', 'cita', 'citas'], ['pendientes', 'pendiente', 'pendientes'], ['completadas', 'completada', 'completadas'], ['canceladas', 'cancelada', 'canceladas']]) {
        const numero = campoCitas('kpi-' + id);
        numero.nextSibling.textContent = ' ' + (numero.textContent === '1' ? singular : plural);
    }
}
function actualizarControlesCitas(citas, vetFiltro) {
    const periodo = campoCitas('filtroFecha').value;
    const etiquetas = { hoy: 'Hoy · ' + fechaCitasTexto(fechaLocalCitas(new Date())), ayer: 'Ayer', semana: 'Esta semana', mes: 'Este mes', custom: fechaCitasTexto(campoCitas('filtroFechaCustom').value), '': 'Todas las fechas' };
    document.querySelector('[data-citas-periodo-texto]').textContent = ['', 'custom', 'hoy'].includes(periodo) ? (periodo === 'hoy' ? etiquetas.hoy : rangoPeriodoCitas) : etiquetas[periodo] + ' · ' + rangoPeriodoCitas;
    document.querySelectorAll('[data-citas-periodo]').forEach(n => n.setAttribute('aria-pressed', String(n.dataset.citasPeriodo === periodo)));
    document.querySelectorAll('[data-citas-estado]').forEach(n => n.setAttribute('aria-pressed', String(n.dataset.citasEstado === campoCitas('filtroEstado').value)));
    document.querySelector('[data-citas-conteo="agenda"]').textContent = citas.length;
    const cantidad = Number(periodo !== 'hoy') + ['filtroTipoCita', 'filtroEstado', 'filtroVet', 'filtroSearch'].filter(id => campoCitas(id).value !== '').length;
    const conteo = document.querySelector('[data-citas-filtros-conteo]');
    conteo.textContent = cantidad;
    conteo.hidden = !cantidad;
    document.querySelector('.citas-resumen [data-ui-accion="limpiar-citas"]').hidden = !cantidad;
    document.querySelector('[data-citas-ver]').textContent = 'Ver ' + citas.length + (citas.length === 1 ? ' cita' : ' citas');
    // Los chips ofrecen cambiar de veterinario sin perder los otros filtros.
    const sinVet = citasBaseSinVet;
    const contenedor = document.querySelector('[data-citas-veterinarios]');
    contenedor.replaceChildren();
    for (const v of [{ id_usuario: '', nombre_completo: 'Todos' }, ...veterinariosArray]) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.dataset.citasVet = String(v.id_usuario);
        boton.setAttribute('aria-pressed', String(String(v.id_usuario) === vetFiltro));
        const cantidad = sinVet.filter(c => !v.id_usuario || String(c.id_veterinario) === String(v.id_usuario)).length;
        boton.textContent = (v.id_usuario ? v.nombre_completo.split(' ')[0] : 'Todos') + ' ' + cantidad;
        contenedor.append(boton);
    }
}
function fechaLocalCitas(fecha) {
    return fecha.getFullYear() + '-' + String(fecha.getMonth()+1).padStart(2, '0') + '-' + String(fecha.getDate()).padStart(2, '0');
}
let citasBaseSinVet = [];
function renderizarKanban(citas, vetFiltro) {
    const board = campoCitas('kanbanBoard');
    const vets = vetFiltro ? veterinariosArray.filter(v => String(v.id_usuario) === vetFiltro) : veterinariosArray;
    board.classList.toggle('kanban-board--varias', vets.length >= 4);
    board.replaceChildren();
    if (!vets.length) {
        const vacio = document.createElement('p');
        vacio.className = 'citas-vacio';
        vacio.textContent = 'No hay veterinarios registrados.';
        board.append(vacio);
    }
    for (const vet of vets) {
        const filas = citas.filter(c => String(c.id_veterinario) === String(vet.id_usuario));
        const pendientes = filas.filter(c => ['pendiente', 'confirmada'].includes(c.estado)).length;
        const iniciales = vet.nombre_completo.trim().split(/\s+/).slice(0, 2).map(n => n[0]).join('');
        const columna = document.createElement('section');
        columna.className = 'kanban-col';
        columna.setAttribute('aria-label', vet.nombre_completo);
        columna.innerHTML = '<header class="kb-col-header"><span class="kb-avatar">' + escapeHtml(iniciales) + '</span><div><h2 class="kb-vet-name">' + escapeHtml(vet.nombre_completo) + '</h2><p class="kb-vet-count">' + filas.length + (filas.length === 1 ? ' cita · ' : ' citas · ') + pendientes + (pendientes === 1 ? ' pendiente' : ' pendientes') + '</p></div></header><div class="kb-col-body"></div>';
        const cuerpo = columna.querySelector('.kb-col-body');
        if (!filas.length) cuerpo.innerHTML = '<p class="citas-vacio">Sin citas</p>';
        for (const cita of filas) {
            const fila = document.createElement('div');
            fila.className = 'kb-card';
            fila.dataset.uiAccion = 'detalle-cita-admin';
            fila.dataset.uiId = String(Number(cita.id_cita));
            fila.dataset.estado = cita.estado;
            fila.setAttribute('role', 'button');
            fila.tabIndex = 0;
            const nivel = ['rojo', 'naranja', 'amarillo'].includes(cita.prioridad) ? '<span class="citas-triage-punto" data-nivel="' + cita.prioridad + '" role="img" aria-label="Triage ' + cita.prioridad + '"></span>' : '';
            const dia = ['', 'semana', 'mes'].includes(campoCitas('filtroFecha').value) ? '<small class="kb-date">' + escapeHtml(fechaCitasTexto(cita.fecha).slice(0, 5)) + '</small>' : '';
            fila.innerHTML = '<div class="kb-time">' + nivel + '<span>' + escapeHtml((cita.hora || '').slice(0, 5)) + dia + '</span></div><div class="kb-paciente"><strong>' + escapeHtml(cita.mascota_nombre || '—') + '</strong><span>' + escapeHtml(cita.propietario_nombre || '—') + ' · ' + escapeHtml(cita.tipo_cita_nombre || 'Consulta') + '</span></div><span class="kb-status" data-estado="' + escapeHtml(cita.estado) + '">' + escapeHtml(estadosCitasAdmin[cita.estado] || cita.estado) + '</span>';
            cuerpo.append(fila);
        }
        board.append(columna);
    }
    requestAnimationFrame(checkScrollButtons);
}
function verDetalle(id) {
    const cita = todasLasCitas.find(c => String(c.id_cita) === String(id));
    if (!cita) return;
    const dialogo = campoCitas('citasDetalle');
    const cancelacion = dialogo.querySelector('[data-cancelar-cita-admin]');
    if (cancelacion) {
        cancelacion.reset();
        cancelacion.dataset.idCita = cita.id_cita;
        cancelacion.hidden = !['pendiente', 'confirmada'].includes(cita.estado);
        cancelacion.querySelector('[type="submit"]').disabled = true;
        cancelacion.querySelector('[data-cancelacion-error]').textContent = '';
    }
    dialogo.querySelectorAll('[data-cita-detalle]').forEach(n => {
        const clave = n.dataset.citaDetalle;
        n.textContent = cita[clave] || (clave === 'veterinario_nombre' ? veterinariosMap[cita.id_veterinario] : clave === 'motivo' ? 'Sin observaciones' : '—');
    });
    const estado = dialogo.querySelector('[data-cita-detalle-estado]');
    estado.dataset.estado = cita.estado;
    estado.textContent = estadosCitasAdmin[cita.estado] || cita.estado;
    dialogo.querySelector('[data-cita-detalle-fecha]').textContent = fechaCitasTexto(cita.fecha) + ' · ' + (cita.hora || '').slice(0, 5) + ' · ' + (cita.tipo_cita_nombre || 'Consulta');
    zookiAbrirDialogo('citasDetalle');
}
function limpiarFiltros() {
    campoCitas('filtroFecha').value = 'hoy';
    const fecha = campoCitas('filtroFechaCustom');
    if (fecha._flatpickr) fecha._flatpickr.clear(false);
    fecha.value = '';
    for (const id of ['filtroTipoCita', 'filtroEstado', 'filtroVet', 'filtroSearch']) campoCitas(id).value = '';
    aplicarFiltros();
}
window.addEventListener('agenda:urgencia', cargarDatosIniciales);

function aplicarFiltros() {
    const fecha = document.getElementById('filtroFecha').value;
    const fechaCustom = document.getElementById('filtroFechaCustom').value;
    const tipoCita = document.getElementById('filtroTipoCita').value;
    const estado = document.getElementById('filtroEstado').value;
    const vetFiltro = document.getElementById('filtroVet').value;
    const search = document.getElementById('filtroSearch').value.toLowerCase();

    let citasFiltradas = [...todasLasCitas];
    rangoPeriodoCitas = 'Todas las fechas';

    if (fecha) {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        let fechaInicio;
        let fechaFin;

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
            const inicioTexto = fechaCitasTexto(fechaLocalCitas(fechaInicio));
            const finTexto = fechaCitasTexto(fechaLocalCitas(fechaFin));
            rangoPeriodoCitas = inicioTexto === finTexto ? inicioTexto : inicioTexto + ' – ' + finTexto;
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

    if (search) {
        citasFiltradas = citasFiltradas.filter(c =>
            (c.mascota_nombre && c.mascota_nombre.toLowerCase().includes(search)) ||
            (c.propietario_nombre && c.propietario_nombre.toLowerCase().includes(search)) ||
            (c.motivo && c.motivo.toLowerCase().includes(search))
        );
    }

    citasBaseSinVet = [...citasFiltradas];
    if (vetFiltro) citasFiltradas = citasFiltradas.filter(c => String(c.id_veterinario) === vetFiltro);
    // Orden por fecha y hora
    citasFiltradas.sort((a, b) => {
        const fechaA = new Date(a.fecha + 'T' + (a.hora || '00:00:00'));
        const fechaB = new Date(b.fecha + 'T' + (b.hora || '00:00:00'));
        return fechaA - fechaB; // Ascending order
    });

    actualizarMétricas(citasFiltradas);
    renderizarKanban(citasFiltradas, vetFiltro);
    citasFiltradasGlobal = citasFiltradas;
    actualizarControlesCitas(citasFiltradas, vetFiltro);
}


async function exportarCitas() {
    if (citasFiltradasGlobal.length === 0) {
        zookiToast('No hay citas para exportar con estos filtros.', 'warning');
        return;
    }

    try {
        // Mostrar cargando mientras procesa la librería (en caso de que sean muchas filas)
        zookiToast('Generando archivo…', 'info');

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
                `Dr. ${c.veterinario_nombre || veterinariosMap[String(c.id_veterinario)] || '—'}`,
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


        zookiToast('Excel Exportado Correctamente', 'success');

    } catch (error) {
        console.error('Error al generar Excel:', error);
        zookiToast('Hubo un problema al generar el archivo Excel. Revisa tu consola.', 'error');
    }
}

function exportarCitasPDF() {
    if (citasFiltradasGlobal.length === 0) {
        zookiToast('No hay citas para exportar con estos filtros.', 'warning');
        return;
    }

    try {
        zookiToast('Generando archivo…', 'info');

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
            `Dr. ${c.veterinario_nombre || veterinariosMap[String(c.id_veterinario)] || '—'}`,
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



        zookiToast('PDF Exportado Correctamente', 'success');

    } catch (error) {
        console.error('Error al generar PDF:', error);
        zookiToast('Hubo un problema al generar el PDF.', 'error');
    }
}
