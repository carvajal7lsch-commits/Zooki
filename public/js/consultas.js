/**
 * Consultas Médicas (views/vet/consultas.php).
 *
 * Lista las consultas de la clínica activa con filtros y abre su detalle o el
 * historial de la mascota. Los datos llegan en data-consultas; todo texto que
 * viene de la base se escapa antes de ir a innerHTML, porque el historial
 * puede mostrar lo que escribió otra clínica (RN-113).
 *
 * Usa de medical-module.js: escaparTexto, openNewConsultationFlow,
 * searchPetForConsultation, viewMedicalHistory, closeHistorialDrawer y
 * closeModal.
 */
(function () {
    'use strict';

    const app = document.getElementById('consultasApp');
    if (!app) return;

    let todasLasConsultas = [];
    try {
        todasLasConsultas = JSON.parse(app.dataset.consultas || '[]');
    } catch (e) {
        console.error('consultas: datos ilegibles', e);
    }

    let consultasFiltradas = [];

    const campoBusqueda = document.getElementById('consultationSearch');
    const filtroEspecie = document.getElementById('filtroEspecie');
    const filtroFecha = document.getElementById('filtroFecha');
    const filtroFechaCustom = document.getElementById('filtroFechaCustom');

    function textoSeguro(valor, porDefecto = '') {
        const texto = valor === null || valor === undefined || valor === '' ? porDefecto : valor;
        return escaparTexto(texto);
    }

    function fechaLocal(fechaHora) {
        if (!fechaHora || fechaHora.startsWith('0000')) return null;
        const [parteFecha] = fechaHora.split(' ');
        const [anio, mes, dia] = parteFecha.split('-');
        return new Date(anio, mes - 1, dia);
    }

    function rangoDelFiltro() {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        let inicio = null;

        switch (filtroFecha.value) {
            case 'hoy':
                inicio = new Date(hoy);
                break;
            case 'ayer':
                inicio = new Date(hoy);
                inicio.setDate(inicio.getDate() - 1);
                break;
            case 'semana':
                inicio = new Date(hoy);
                inicio.setDate(hoy.getDate() - hoy.getDay());
                break;
            case 'mes':
                inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                break;
            case 'custom':
                if (filtroFechaCustom.value) inicio = fechaLocal(filtroFechaCustom.value);
                break;
        }
        if (!inicio) return null;

        let fin = new Date(inicio);
        if (filtroFecha.value === 'semana') fin.setDate(fin.getDate() + 6);
        if (filtroFecha.value === 'mes') fin = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
        fin.setHours(23, 59, 59, 999);
        return { inicio, fin };
    }

    function filtrarConsultas() {
        const termino = campoBusqueda.value.toLowerCase();
        const especie = filtroEspecie.value;
        const rango = rangoDelFiltro();

        let filtradas = [...todasLasConsultas];

        if (termino) {
            filtradas = filtradas.filter((c) =>
                [c.nombre_mascota, c.nombre_propietario, c.diagnostico, c.motivo_consulta]
                    .some((campo) => (campo || '').toLowerCase().includes(termino))
            );
        }

        if (especie) {
            filtradas = filtradas.filter((c) => (c.nombre_especie || '').toLowerCase() === especie);
        }

        if (rango) {
            filtradas = filtradas.filter((c) => {
                const fecha = fechaLocal(c.fecha_hora);
                return fecha !== null && fecha >= rango.inicio && fecha <= rango.fin;
            });
        }

        filtradas.sort((a, b) => new Date((b.fecha_hora || '').replace(' ', 'T')) - new Date((a.fecha_hora || '').replace(' ', 'T')));

        actualizarMetricas(filtradas);
        renderizarGrid(filtradas);
    }

    function actualizarMetricas(filtradas) {
        const total = filtradas.length;
        const caninos = filtradas.filter((c) => (c.nombre_especie || '').toLowerCase() === 'canino').length;
        const felinos = filtradas.filter((c) => (c.nombre_especie || '').toLowerCase() === 'felino').length;

        document.getElementById('kpi-total').textContent = total;
        document.getElementById('kpi-caninos').textContent = caninos;
        document.getElementById('kpi-felinos').textContent = felinos;

        dibujarSparkline('kpi-total-spark', total, total, '#0052FF');
        dibujarSparkline('kpi-caninos-spark', caninos, total, '#10B981');
        dibujarSparkline('kpi-felinos-spark', felinos, total, '#F59E0B');
    }

    function dibujarSparkline(idElemento, valor, maximo, color) {
        const contenedor = document.getElementById(idElemento);
        if (!contenedor) return;

        const tope = maximo === 0 ? 1 : maximo;
        const alto = 28 - ((valor / tope) * 15);

        let trazo = 'M0,28 L25,28 L50,28 L75,28 L100,28';
        if (valor !== 0) {
            const diferencia = 28 - alto;
            const p1 = 28 - Math.random() * (diferencia * 0.5);
            const p2 = 28 - Math.random() * (diferencia * 1.2);
            const p3 = 28 - Math.random() * (diferencia * 0.8);
            trazo = `M0,28 L25,${p1} L50,${p2} L75,${p3} L100,${alto}`;
        }

        contenedor.innerHTML = `
            <defs>
                <linearGradient id="grad-${idElemento}" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="${color}" stop-opacity="0.15" />
                    <stop offset="100%" stop-color="${color}" stop-opacity="0" />
                </linearGradient>
            </defs>
            <path d="${trazo}" fill="none" stroke="${color}" stroke-width="2" vector-effect="non-scaling-stroke"></path>
            <path d="${trazo} L100,30 L0,30 Z" fill="url(#grad-${idElemento})" stroke="none"></path>
            <circle cx="100" cy="${alto}" r="2.5" fill="${color}" />
        `;
    }

    function renderizarGrid(consultas) {
        consultasFiltradas = consultas;
        const grid = document.getElementById('consultationsGrid');
        grid.innerHTML = '';

        if (consultas.length === 0) {
            grid.innerHTML = `
                <div class="consultations-empty">
                    <i class="fas fa-folder-open consultas-vacio-icono"></i>
                    No se encontraron consultas registradas con los filtros seleccionados.
                </div>`;
            return;
        }

        consultas.forEach((c, indice) => {
            const fecha = fechaLocal(c.fecha_hora);
            const diaMes = fecha ? `${String(fecha.getDate()).padStart(2, '0')}/${String(fecha.getMonth() + 1).padStart(2, '0')}` : '';
            const hora = c.fecha_hora && c.fecha_hora.includes(' ') ? c.fecha_hora.split(' ')[1].substring(0, 5) : '--:--';
            const especie = (c.nombre_especie || '').toLowerCase();
            const claseEspecie = escaparTexto(especie);
            const icono = especie === 'felino' ? 'fa-cat' : 'fa-dog';

            const tarjeta = document.createElement('div');
            tarjeta.className = 'consultation-card';
            tarjeta.dataset.indice = indice;
            tarjeta.innerHTML = `
                <div class="consultation-card-header">
                    <span class="consultation-time">
                        <i class="far fa-clock"></i> ${escaparTexto(hora)}
                        <span class="consultation-date">(${diaMes})</span>
                    </span>
                    <span class="consultation-species-badge ${claseEspecie}">${textoSeguro(c.nombre_especie, 'Otro')}</span>
                </div>
                <div class="consultation-patient-info">
                    <div class="consultation-avatar ${claseEspecie}"><i class="fas ${icono}"></i></div>
                    <div class="consultation-names">
                        <strong class="consultation-pet-name">${textoSeguro(c.nombre_mascota, '—')}</strong>
                        <span class="consultation-owner-name"><i class="far fa-user"></i> ${textoSeguro(c.nombre_propietario, '—')}</span>
                    </div>
                </div>
                <div class="consultation-diag" title="${textoSeguro(c.diagnostico)}">${textoSeguro(c.diagnostico, 'Sin diagnóstico preliminar registrado.')}</div>
                <div class="consultation-footer-actions">
                    ${c.id_cita ? '' : '<span class="consultation-sin-cita-badge" title="Registrada sin cita (urgencia o imprevisto)"><i class="fas fa-ambulance"></i> Sin cita</span>'}
                    <button type="button" class="btn-icon premium history" data-accion="detalle" title="Ver Detalle"><i class="fas fa-eye"></i></button>
                    <button type="button" class="btn-icon premium medical" data-accion="historial" title="Historial Clínico"><i class="fas fa-notes-medical"></i></button>
                </div>`;
            grid.appendChild(tarjeta);
        });
    }

    function alHacerClicEnGrid(evento) {
        const tarjeta = evento.target.closest('.consultation-card');
        if (!tarjeta) return;

        const consulta = consultasFiltradas[Number(tarjeta.dataset.indice)];
        if (!consulta) return;

        const boton = evento.target.closest('[data-accion]');
        if (boton && boton.dataset.accion === 'historial') {
            viewMedicalHistory(consulta.id_mascota, consulta.nombre_mascota || '');
            return;
        }
        verDetalle(consulta);
    }

    function abrirDetalle() {
        document.getElementById('consultaDrawerOverlay').hidden = false;
        setTimeout(() => document.getElementById('consultaDrawer').classList.remove('closed'), 10);
    }

    function cerrarDetalle() {
        document.getElementById('consultaDrawer').classList.add('closed');
        setTimeout(() => { document.getElementById('consultaDrawerOverlay').hidden = true; }, 400);
    }

    function signoVital(etiqueta, clase, icono, valor, unidad) {
        return `
            <div class="vital-sign-card">
                <div class="vital-sign-icon ${clase}"><i class="fas ${icono}"></i></div>
                <div class="vital-sign-info">
                    <span class="vital-sign-label">${etiqueta}</span>
                    <span class="vital-sign-value">${textoSeguro(valor, '--')} ${unidad}</span>
                </div>
            </div>`;
    }

    function cajaDeReporte(clase, icono, titulo, texto, porDefecto) {
        return `
            <div class="report-box ${clase}">
                <div class="report-box-header"><i class="fas ${icono}"></i>${titulo}</div>
                <div class="report-box-body">${textoSeguro(texto, porDefecto)}</div>
            </div>`;
    }

    function verDetalle(c) {
        let fechaTexto = 'Sin fecha';
        let horaTexto = '--:--';
        if (c.fecha_hora && !c.fecha_hora.startsWith('0000')) {
            const fecha = new Date(c.fecha_hora.replace(' ', 'T'));
            fechaTexto = fecha.toLocaleDateString();
            horaTexto = fecha.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        const cuerpo = document.getElementById('detalleConsultaBody');
        cuerpo.innerHTML = `
            <div class="clinical-summary-card">
                <div class="clinical-summary-patient">
                    <div class="clinical-summary-patient-icon"><i class="fas fa-paw"></i></div>
                    <div>
                        <h4>${textoSeguro(c.nombre_mascota)}</h4>
                        <span>Propietario: ${textoSeguro(c.nombre_propietario, '—')}</span>
                    </div>
                </div>
                <div class="clinical-summary-meta">
                    <div class="clinical-summary-meta-doc"><i class="fas fa-user-md"></i>${textoSeguro(c.veterinario, 'Veterinario')}</div>
                    <div class="clinical-summary-meta-date"><i class="far fa-calendar-alt"></i>${fechaTexto} · ${horaTexto}</div>
                </div>
            </div>

            <h4 class="section-label">Triage / Constantes Vitales</h4>
            <div class="vital-signs-grid">
                ${signoVital('Peso', 'weight', 'fa-weight', c.peso, 'Kg')}
                ${signoVital('Temperatura', 'temp', 'fa-thermometer-half', c.temperatura, '°C')}
                ${signoVital('Frec. Cardíaca', 'heart', 'fa-heartbeat', c.frecuencia_cardiaca, 'LPM')}
            </div>

            <h4 class="section-label consultas-reporte-titulo">Reporte Clínico</h4>
            <div class="consultas-reporte">
                ${cajaDeReporte('default', 'fa-comment-medical', 'Motivo de Consulta', c.motivo_consulta, 'Sin especificar.')}
                ${cajaDeReporte('default', 'fa-notes-medical', 'Anamnesis / Observaciones', c.anamnesis, 'Sin observaciones registradas.')}
                ${cajaDeReporte('info', 'fa-microscope', 'Diagnóstico', c.diagnostico, 'Sin diagnóstico registrado.')}
                ${cajaDeReporte('success', 'fa-pills', 'Plan de Tratamiento', c.plan_tratamiento, 'Sin plan de tratamiento registrado.')}
            </div>`;
        cuerpo.scrollTop = 0;
        abrirDetalle();
    }

    function limpiarFiltros() {
        campoBusqueda.value = '';
        filtroEspecie.value = '';
        filtroFecha.value = '';
        filtroFechaCustom.value = '';
        filtroFechaCustom.hidden = true;
        filtrarConsultas();
    }

    campoBusqueda.addEventListener('input', filtrarConsultas);
    filtroEspecie.addEventListener('change', filtrarConsultas);
    filtroFechaCustom.addEventListener('change', filtrarConsultas);
    filtroFecha.addEventListener('change', () => {
        filtroFechaCustom.hidden = filtroFecha.value !== 'custom';
        filtrarConsultas();
    });
    document.getElementById('btnLimpiarFiltros').addEventListener('click', limpiarFiltros);
    document.getElementById('consultationsGrid').addEventListener('click', alHacerClicEnGrid);

    document.getElementById('btnCerrarConsultaDrawer').addEventListener('click', cerrarDetalle);
    document.getElementById('consultaDrawerOverlay').addEventListener('click', cerrarDetalle);
    document.getElementById('btnCerrarHistorialDrawer').addEventListener('click', closeHistorialDrawer);
    document.getElementById('historialDrawerOverlay').addEventListener('click', closeHistorialDrawer);

    const botonSinCita = document.getElementById('btnAtencionSinCita');
    if (botonSinCita) botonSinCita.addEventListener('click', openNewConsultationFlow);

    const busquedaMascota = document.getElementById('consultaPetSearch');
    if (busquedaMascota) busquedaMascota.addEventListener('input', () => searchPetForConsultation(busquedaMascota.value));

    document.querySelectorAll('[data-cerrar-modal]').forEach((boton) => {
        boton.addEventListener('click', () => closeModal(boton.dataset.cerrarModal));
    });

    filtrarConsultas();
})();
