/* RNF-16: presentación móvil de HU-4.13/14/15/18/19; las consultas y comandos siguen en sus módulos. */
(function (global) {
    'use strict';
    const movil = global.matchMedia('(max-width: 767px)');
    let raiz;
    let herramientas;
    let mesVisible;
    let destinoFecha = 'agenda';
    let revisionActual = null;
    const local = fecha => fecha.getFullYear() + '-' + String(fecha.getMonth() + 1).padStart(2, '0') + '-' + String(fecha.getDate()).padStart(2, '0');
    const fecha = texto => new Date(texto + 'T12:00:00');
    const activa = () => Boolean(raiz && movil.matches);
    const nodo = (tipo, texto, clase = '') => {
        const el = document.createElement(tipo);
        el.textContent = texto;
        el.className = clase;
        return el;
    };
    function eventos() {
        return typeof calendarInstance !== 'undefined' && calendarInstance ? calendarInstance.getEvents().filter(ev => ev.start && FilterManager.esVisible(ev)) : [];
    }
    function cambiarDia(dia) {
        if (!calendarInstance) return;
        calendarInstance.gotoDate(dia);
        seleccionarDia(dia);
        actualizar();
    }
    function pintarMes() {
        if (!activa() || !mesVisible) return;
        const cont = document.querySelector('[data-agenda-mes-dias]');
        document.getElementById('agendaMesTitulo').textContent = capitalizar(mesVisible.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' }));
        cont.replaceChildren();
        ['L', 'M', 'M', 'J', 'V', 'S', 'D'].forEach(dia => cont.append(nodo('small', dia)));
        const inicio = new Date(mesVisible.getFullYear(), mesVisible.getMonth(), 1, 12);
        const desplazamiento = (inicio.getDay() + 6) % 7;
        const total = new Date(inicio.getFullYear(), inicio.getMonth() + 1, 0).getDate();
        const puntos = new Set(eventos().map(ev => local(ev.start)));
        for (let i = 0; i < desplazamiento; i++) cont.append(nodo('span', ''));
        for (let i = 1; i <= total; i++) {
            const dia = new Date(inicio.getFullYear(), inicio.getMonth(), i, 12);
            const clave = local(dia);
            const boton = nodo('button', i, 'inicio-semana__dia');
            boton.type = 'button';
            boton.dataset.agendaMesFecha = clave;
            boton.setAttribute('aria-label', dia.toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }));
            boton.setAttribute('aria-pressed', String(clave === (destinoFecha === 'cita' ? val('modal_fecha') : local(_selectedDate || new Date()))));
            boton.disabled = diaNoReservable(dia) && (destinoFecha === 'cita' || !puntos.has(clave));
            if (puntos.has(clave)) boton.append(nodo('span', '', 'inicio-semana__punto'));
            cont.append(boton);
        }
    }
    function abrirMes(destino = 'agenda') {
        destinoFecha = destino;
        mesVisible = new Date((destino === 'cita' ? fecha(val('modal_fecha')) : _selectedDate) || new Date());
        pintarMes();
        global.zookiAbrirDialogo('agendaMesMovil');
    }
    function actualizar() {
        if (!activa() || !_selectedDate) return;
        const elegido = local(_selectedDate);
        const inicio = fecha(elegido);
        inicio.setDate(inicio.getDate() - (inicio.getDay() + 6) % 7);
        const dias = Array.from({ length: 7 }, (_, indice) => {
            const dia = new Date(inicio);
            dia.setDate(dia.getDate() + indice);
            return local(dia);
        });
        const todos = eventos();
        const puntos = new Set(todos.map(ev => local(ev.start)));
        global.ZookiInicio.pintarSemana(raiz, dias, elegido, local(new Date()), dia => puntos.has(dia));
        raiz.querySelector('[data-inicio-semana-mes]').textContent = capitalizar(_selectedDate.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' }));
        const lista = raiz.querySelector('[data-agenda-dia-lista]');
        lista.replaceChildren();
        const delDia = todos.filter(ev => local(ev.start) === elegido).sort((a, b) => a.start - b.start);
        raiz.querySelector('[data-agenda-dia-titulo]').textContent = capitalizar(_selectedDate.toLocaleDateString('es-CO', { weekday: 'long', day: '2-digit', month: '2-digit' }));
        const atendidas = delDia.filter(ev => ev.extendedProps.estado === 'completada').length;
        raiz.querySelector('[data-agenda-dia-resumen]').textContent = `${delDia.length} en la agenda · ${atendidas} atendidas`;
        if (!delDia.length) lista.append(nodo('p', _cargando ? 'Cargando agenda…' : 'Sin eventos para este día.', 'agenda-hoja__ayuda'));
        delDia.forEach(ev => {
            const p = ev.extendedProps;
            const fila = nodo('button', '', 'agenda-movil__fila');
            fila.type = 'button';
            fila.dataset.agendaEvento = ev.id;
            const hora = nodo('span', formatearHoraReserva(ev.start.toTimeString().slice(0, 5)), 'agenda-movil__hora');
            if (p.tipo === 'cita' && ['rojo', 'naranja', 'amarillo'].includes(p.prioridad)) {
                const punto = nodo('i', '', 'agenda-prioridad');
                punto.dataset.nivel = p.prioridad;
                punto.setAttribute('aria-label', capitalizar(p.prioridad));
                hora.prepend(punto);
            }
            const contenido = nodo('span', '', 'agenda-movil__paciente');
            contenido.append(nodo('strong', p.mascota_nombre || ev.title), nodo('small', [p.tipo_cita_nombre || TIPOS_EVENTO[p.tipo] || 'Consulta', p.propietario_nombre].filter(Boolean).join(' · ')));
            const chip = nodo('span', p.tipo === 'cita' ? ESTADOS_CITA[p.estado] || p.estado : TIPOS_EVENTO[p.tipo], 'estado-pill');
            if (p.tipo === 'cita') chip.dataset.estado = p.estado;
            else chip.dataset.tipo = p.tipo;
            fila.append(hora, contenido, chip);
            lista.append(fila);
        });
        const filtrado = ['cita', 'vacunacion', 'desparasitacion'].some(tipo => !FilterManager.isActive(tipo));
        raiz.querySelector('[data-agenda-filtros-activos]').hidden = !filtrado;
        conteo('agenda', delDia.length);
        if (document.getElementById('agendaMesMovil').open) pintarMes();
    }
    function conteo(clave, cantidad) {
        const salida = herramientas?.querySelector(`[data-agenda-conteo="${clave}"]`);
        if (salida && salida.textContent !== String(cantidad)) salida.textContent = cantidad;
    }
    function prepararRevision(tarjeta) {
        const form = tarjeta.querySelector('form');
        if (!form || tarjeta.querySelector('[data-triage-revisar]')) return;
        form.classList.add('agenda-revision-form');
        const boton = nodo('button', 'Revisar nivel', 'zk-btn zk-btn--sec zk-btn--mini agenda-revisar');
        boton.type = 'button';
        boton.dataset.triageRevisar = '';
        boton.addEventListener('click', () => {
            const dialogo = document.getElementById('agendaRevisionMovil');
            const marca = document.createComment('Formulario original de revisión');
            const error = tarjeta.querySelector('.ah-error');
            form.before(marca);
            revisionActual = { form, error, marca, tarjeta };
            dialogo.querySelector('[data-agenda-revision-paciente]').textContent = tarjeta.querySelector('strong').textContent;
            const contexto = dialogo.querySelector('[data-agenda-revision-contexto]');
            contexto.replaceChildren();
            tarjeta.querySelectorAll('.ah-item__texto, .ah-item__meta').forEach(dato => contexto.append(nodo('p', dato.textContent)));
            const select = form.querySelector('select');
            form.querySelector('input').id = 'agendaRevisionMotivo';
            const niveles = dialogo.querySelector('[data-agenda-revision-niveles]');
            niveles.replaceChildren();
            Array.from(select.options).forEach(opcion => {
                const nivel = nodo('button', opcion.text, 'agenda-revision__nivel ah-nivel');
                nivel.type = 'button';
                nivel.dataset.nivel = opcion.value;
                nivel.setAttribute('aria-pressed', String(select.value === opcion.value));
                nivel.addEventListener('click', () => {
                    select.value = opcion.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    niveles.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b === nivel)));
                });
                niveles.append(nivel);
            });
            form.querySelector('[type="submit"]').textContent = 'Confirmar nivel';
            const cancelar = nodo('button', 'Cancelar', 'agenda-revision-cancelar');
            cancelar.type = 'button';
            cancelar.dataset.cerrarDialogo = '';
            error.setAttribute('aria-live', 'polite');
            form.insertBefore(error, form.querySelector('[type="submit"]'));
            form.append(cancelar);
            dialogo.querySelector('[data-agenda-revision-form]').append(form);
            global.zookiAbrirDialogo(dialogo.id);
        });
        tarjeta.append(boton);
    }
    function herramientasActualizadas() {
        if (!herramientas) return;
        const lista = herramientas.querySelector('[data-solicitudes-lista]');
        if (herramientas.querySelector('[data-solicitudes-filtro]').value === '0') conteo('triage', lista.querySelectorAll('.ah-item').length);
        conteo('llegadas', herramientas.querySelectorAll('[data-llegadas-citas] > article, [data-llegadas-solicitudes] > article, [data-llegadas-espera] > article').length);
        conteo('reasignaciones', herramientas.querySelectorAll('[data-reasignaciones-lista] > article').length);
        conteo('urgencias', herramientas.querySelectorAll('[data-rojos-lista] > article, [data-ingresos-lista] > article').length);
        lista.querySelectorAll('.ah-item').forEach(prepararRevision);
        if (revisionActual && !revisionActual.tarjeta.isConnected) document.getElementById('agendaRevisionMovil').close();
    }
    function iniciar() {
        raiz = document.querySelector('[data-agenda-movil]')?.closest('.agenda');
        if (!raiz) return;
        // Las hojas pertenecen a la pantalla, no al panel ocultable de Agenda.
        raiz.querySelectorAll('.agenda-hoja').forEach(dialogo => document.body.append(dialogo));
        herramientas = document.querySelector('[data-agenda-herramientas]');
        const marca = document.createComment('Agenda de escritorio');
        raiz.before(marca);
        const tabs = herramientas.querySelector('[role="tablist"]');
        const agenda = nodo('button', 'Agenda', 'agenda-herramientas__tab agenda-tab-movil');
        agenda.type = 'button';
        agenda.id = 'agenda-tab-agenda';
        agenda.dataset.agendaTab = 'agenda';
        agenda.setAttribute('role', 'tab');
        agenda.setAttribute('aria-controls', 'agenda-panel-agenda');
        agenda.setAttribute('aria-selected', 'false');
        tabs.prepend(agenda);
        const panel = nodo('section', '', 'agenda-panel-movil');
        panel.id = 'agenda-panel-agenda';
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', agenda.id);
        panel.hidden = true;
        herramientas.querySelector('.agenda-herramientas__cuerpo').prepend(panel);
        agenda.addEventListener('click', () => activarPanelAgenda(herramientas, 'agenda', false));
        // El tab nuevo participa del mismo teclado que los paneles existentes.
        tabs.addEventListener('keydown', e => {
            if (!activa() || !['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
            e.stopImmediatePropagation();
            e.preventDefault();
            const botones = Array.from(tabs.querySelectorAll('[role="tab"]'));
            const indice = botones.indexOf(e.target.closest('button'));
            const proximo = botones[(indice + (e.key === 'ArrowRight' ? 1 : botones.length - 1)) % botones.length];
            activarPanelAgenda(herramientas, proximo.dataset.agendaTab, true);
        }, true);
        tabs.querySelectorAll('[data-agenda-tab]').forEach(tab => {
            if (tab.dataset.agendaTab === 'reasignaciones') {
                const titulo = tab.querySelector('span');
                titulo.classList.add('agenda-solo-escritorio');
                const corto = nodo('span', 'Reasignar', 'agenda-solo-movil');
                titulo.after(corto);
            }
            const contador = nodo('span', '0', 'agenda-conteo');
            contador.dataset.agendaConteo = tab.dataset.agendaTab;
            tab.append(contador);
        });
        const filtros = document.querySelector('.agenda-toolbar__filters');
        const marcaFiltros = document.createComment('Filtros de escritorio');
        filtros.before(marcaFiltros);
        const detalle = document.getElementById('agendaPanel');
        const marcaDetalle = document.createComment('Detalle de escritorio');
        detalle.before(marcaDetalle);
        const ajustar = () => {
            document.body.classList.toggle('agenda-vet-movil', activa());
            if (activa()) {
                panel.append(raiz);
                document.querySelector('[data-agenda-filtros-destino]').append(filtros);
                document.querySelector('[data-agenda-detalle-destino]').append(detalle);
                const clave = (global.location.hash || '#agenda').slice(1);
                activarPanelAgenda(herramientas, tabs.querySelector(`[data-agenda-tab="${clave}"]`) ? clave : 'agenda', false);
            } else {
                document.querySelectorAll('.agenda-hoja[open]').forEach(d => d.close());
                marca.after(raiz);
                marcaFiltros.after(filtros);
                marcaDetalle.after(detalle);
                if (agenda.getAttribute('aria-selected') === 'true') activarPanelAgenda(herramientas, 'triage', false);
                panel.hidden = true;
            }
            calendarInstance?.updateSize();
            actualizar();
        };
        const filtro = herramientas.querySelector('[data-solicitudes-filtro]');
        const conmutador = nodo('div', '', 'agenda-triage-filtro');
        for (const [valor, titulo] of [['0', 'Pendientes'], ['1', 'Revisadas']]) {
            const boton = nodo('button', titulo);
            boton.type = 'button';
            boton.setAttribute('aria-pressed', String(filtro.value === valor));
            boton.addEventListener('click', () => {
                filtro.value = valor;
                conmutador.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b === boton)));
                filtro.dispatchEvent(new Event('change', { bubbles: true }));
            });
            conmutador.append(boton);
        }
        filtro.closest('.ah-acciones').prepend(conmutador);
        const observador = new MutationObserver(herramientasActualizadas);
        herramientas.querySelectorAll('.ah-lista').forEach(lista => observador.observe(lista, { childList: true }));
        herramientasActualizadas();
        document.getElementById('agendaRevisionMovil').addEventListener('close', () => {
            if (!revisionActual) return;
            const { form, error, marca, tarjeta } = revisionActual;
            form.querySelector('input').removeAttribute('id');
            form.querySelector('.agenda-revision-cancelar')?.remove();
            if (tarjeta.isConnected) {
                marca.after(form, error);
                form.querySelector('[type="submit"]').textContent = 'Confirmar';
            } else {
                form.remove();
                error.remove();
            }
            marca.remove();
            revisionActual = null;
        });
        raiz.addEventListener('click', e => {
            const dia = e.target.closest('[data-inicio-semana-fecha]');
            if (dia) cambiarDia(fecha(dia.dataset.inicioSemanaFecha));
            const paso = e.target.closest('[data-inicio-semana-paso]');
            if (paso) {
                const siguiente = new Date(_selectedDate);
                siguiente.setDate(siguiente.getDate() + Number(paso.dataset.inicioSemanaPaso) * 7);
                cambiarDia(siguiente);
            }
            if (e.target.closest('[data-agenda-hoy]')) cambiarDia(new Date());
            if (e.target.closest('[data-inicio-calendario-abrir]')) abrirMes();
            const evento = e.target.closest('[data-agenda-evento]');
            if (evento) {
                mostrarDetalleCita(evento.dataset.agendaEvento);
                global.zookiAbrirDialogo('agendaDetalleMovil');
            }
        });
        detalle.addEventListener('click', e => {
            if (e.target.closest('[data-accion="volver"], [data-accion="reprogramar"]')) document.getElementById('agendaDetalleMovil').close();
        });
        document.addEventListener('click', e => {
            if (e.target.closest('[data-agenda-nueva]')) abrirCitaModal(_selectedDate && diaNoReservable(_selectedDate) ? new Date() : _selectedDate || new Date());
            if (e.target.closest('[data-cita-cambiar-dia]')) abrirMes('cita');
            const paso = e.target.closest('[data-agenda-mes-paso]');
            if (paso) {
                mesVisible = new Date(mesVisible.getFullYear(), mesVisible.getMonth() + Number(paso.dataset.agendaMesPaso), 1, 12);
                calendarInstance.gotoDate(mesVisible);
                pintarMes();
            }
            const dia = e.target.closest('[data-agenda-mes-fecha]');
            if (dia) {
                const elegida = dia.dataset.agendaMesFecha;
                if (destinoFecha === 'cita') {
                    val('modal_fecha', elegida);
                    document.getElementById('citaModalFechaLabel').textContent = capitalizar(formatFecha(fecha(elegida)));
                    triageModalC4?.refrescarSeleccion?.();
                    mostrarPasoCita(1);
                    cargarSlotsModal();
                } else cambiarDia(fecha(elegida));
                document.getElementById('agendaMesMovil').close();
            }
        });
        movil.addEventListener('change', ajustar);
        document.getElementById('agendaMesMovil').addEventListener('close', () => {
            if (_selectedDate) calendarInstance?.gotoDate(_selectedDate);
        });
        ajustar();
    }
    global.ZookiAgendaMovil = { activa, actualizar, herramientasActualizadas };
    document.addEventListener('DOMContentLoaded', iniciar);
})(window);
