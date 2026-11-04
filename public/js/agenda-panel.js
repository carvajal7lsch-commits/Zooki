/* HU-4.13/14/15/18/19: las herramientas conservan sus datos y acciones dentro del panel lateral. */
(function (global) {
    'use strict';

    function iniciar() {
        const destino = document.querySelector('[data-agenda-pendientes]');
        const herramientas = document.querySelector('[data-agenda-herramientas]');
        if (!destino || !herramientas) return;
        const citas = document.getElementById('agendaPanelCitas');
        const botones = Array.from(document.querySelectorAll('[data-agenda-vista]'));
        const rojo = herramientas.querySelector('[data-abrir-dialogo="dialogoRojo"]');
        const destinoRojo = document.querySelector('[data-agenda-rojo-destino]');
        const marcaRojo = document.createComment('Rojo presencial en el panel móvil');
        rojo.before(marcaRojo);
        rojo.classList.add('agenda-rojo-compacto');
        const marca = document.createComment('Herramientas fuera de la agenda móvil');
        herramientas.before(marca);
        const orden = ['urgencias', 'triage', 'reasignaciones'];
        const conteos = Object.fromEntries(orden.map(clave => [clave, 0]));
        const pestanaPedida = orden.find(clave => global.location.hash === '#' + clave);
        let vista = pestanaPedida ? 'pendientes' : 'citas';
        let esMovil = false;
        let eleccionManual = Boolean(pestanaPedida);
        const hoy = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Bogota' }).format(new Date());
        let dia = global.ZookiAgendaDia || hoy();
        const titulo = herramientas.querySelector('[data-agenda-pendientes-titulo]');
        const volverHoy = herramientas.querySelector('[data-agenda-volver-hoy]');
        // Rojos solo existe hoy: en otro día se dice en vez de mostrar lo de hoy.
        const avisosSoloHoy = [['urgencias', 'Rojos']].map(([clave, nombre]) => {
            const panel = herramientas.querySelector('#agenda-panel-' + clave);
            const nota = document.createElement('p');
            nota.className = 'ah-estado ah-solo-hoy';
            nota.textContent = nombre + ' solo existe hoy. Vuelve a hoy para verla.';
            nota.hidden = true;
            panel?.prepend(nota);
            return nota;
        });

        const cantidad = selector => Array.from(herramientas.querySelectorAll(selector)).filter(nodo => !nodo.hidden).length;
        /** Decisión del dueño (2026-11-03): hoy, todo lo pendiente; otro día, solo lo de sus citas. */
        function otroDia() {
            return !esMovil && dia !== hoy();
        }
        function aplicarDia() {
            const filtrar = otroDia();
            herramientas.querySelectorAll('[data-solicitudes-lista] > article, [data-reasignaciones-lista] > article').forEach(tarjeta => {
                tarjeta.hidden = filtrar && tarjeta.dataset.fecha !== dia;
            });
            herramientas.dataset.otroDia = String(filtrar);
            avisosSoloHoy.forEach(nota => { nota.hidden = !filtrar; });
            if (volverHoy) volverHoy.hidden = !filtrar;
            const fecha = new Date(dia + 'T12:00:00');
            if (titulo) titulo.textContent = filtrar ? 'Pendientes del ' + fecha.toLocaleDateString('es-CO', { day: 'numeric', month: 'long' }) : 'Pendientes de hoy';
            const texto = fecha.toLocaleDateString('es-CO', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
            const salidaFecha = herramientas.querySelector('[data-agenda-pendientes-fecha]');
            if (salidaFecha) salidaFecha.textContent = texto.charAt(0).toUpperCase() + texto.slice(1);
        }
        function elegirPrioridad() {
            const clave = orden.find(nombre => conteos[nombre] > 0) || orden[0];
            if (herramientas.querySelector(`[data-agenda-tab="${clave}"]`)?.getAttribute('aria-selected') !== 'true') {
                activarPanelAgenda(herramientas, clave, false);
            }
        }
        function mostrarVista(clave, priorizar = true) {
            vista = clave;
            citas.hidden = !esMovil && clave !== 'citas';
            destino.hidden = esMovil || clave !== 'pendientes';
            botones.forEach(boton => {
                const seleccionado = boton.dataset.agendaVista === clave;
                boton.setAttribute('aria-selected', String(seleccionado));
                boton.tabIndex = seleccionado ? 0 : -1;
            });
            if (clave === 'pendientes' && !esMovil && priorizar) {
                eleccionManual = false;
                elegirPrioridad();
            }
        }
        function actualizarConteos() {
            aplicarDia();
            // Revisadas es una vista de historia: no reemplaza el número de triages pendientes.
            if (herramientas.querySelector('[data-solicitudes-filtro]').value === '0') {
                conteos.triage = cantidad('[data-solicitudes-lista] > article');
            }
            conteos.reasignaciones = cantidad('[data-reasignaciones-lista] > article');
            conteos.urgencias = otroDia() ? 0 : cantidad('[data-rojos-lista] > article, [data-ingresos-lista] [data-conciliar-identidad]');
            for (const clave of orden) {
                const contador = herramientas.querySelector(`[data-agenda-conteo="${clave}"]`);
                contador.textContent = conteos[clave];
                contador.dataset.conPendientes = String(conteos[clave] > 0);
            }
            const total = Object.values(conteos).reduce((suma, numero) => suma + numero, 0);
            const salida = herramientas.querySelector('[data-agenda-pendientes-total]');
            salida.textContent = total;
            salida.setAttribute('aria-label', `${total} pendientes en total`);
            const contadorInferior = document.querySelector('[data-agenda-pendientes-conteo]');
            contadorInferior.textContent = total;
            contadorInferior.hidden = total === 0;
            contadorInferior.closest('button').setAttribute('aria-label', `Pendientes: ${total}`);
            if (!esMovil && vista === 'pendientes' && !eleccionManual) elegirPrioridad();
        }
        botones.forEach((boton, indice) => {
            boton.addEventListener('click', () => mostrarVista(boton.dataset.agendaVista));
            boton.addEventListener('keydown', evento => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(evento.key)) return;
                evento.preventDefault();
                const siguiente = evento.key === 'Home' ? 0 : evento.key === 'End' ? botones.length - 1 : (indice + 1) % botones.length;
                mostrarVista(botones[siguiente].dataset.agendaVista);
                botones[siguiente].focus();
            });
        });
        herramientas.addEventListener('click', evento => {
            if (!evento.target.closest('[data-agenda-tab]')) return;
            if (!esMovil && vista !== 'pendientes') mostrarVista('pendientes', false);
            eleccionManual = true;
        });
        herramientas.addEventListener('keydown', evento => {
            if (evento.target.closest('[data-agenda-tab]') && ['ArrowLeft', 'ArrowRight'].includes(evento.key)) eleccionManual = true;
        });
        herramientas.addEventListener('focusin', evento => {
            if (evento.target.matches('input, select, textarea')) eleccionManual = true;
        });
        volverHoy?.addEventListener('click', () => global.dispatchEvent(new CustomEvent('agenda:volver-hoy')));
        const observador = new MutationObserver(actualizarConteos);
        herramientas.querySelectorAll('.ah-lista').forEach(lista => observador.observe(lista, { childList: true, subtree: true }));
        global.ZookiPanelAgenda = {
            // Pendientes es siempre de hoy: elegir otro día o una cita en el calendario muestra Citas con ese día.
            mostrarCitas() {
                if (!esMovil && vista !== 'citas') mostrarVista('citas');
            },
            cambiarDia(fecha) {
                dia = fecha;
                actualizarConteos();
            },
            // Se suelta antes de reubicar la agenda: evita insertar un ancestro dentro de sí mismo.
            soltarHerramientas() {
                marca.after(herramientas);
            },
            ajustar(movil) {
                esMovil = movil;
                herramientas.classList.toggle('agenda-pendientes', !movil);
                if (!movil) destino.append(herramientas);
                if (movil) marcaRojo.after(rojo);
                else destinoRojo.append(rojo);
                // Un enlace directo a Pendientes expresa una elección; el conteo de otra bandeja no la sustituye.
                mostrarVista(vista, !pestanaPedida);
                actualizarConteos();
            }
        };
        // La bandeja de triage es diferida; se consulta también para contarla antes de abrir sus tarjetas.
        herramientas.querySelector('[data-agenda-solicitudes]').dispatchEvent(new CustomEvent('agenda:mostrar'));
        actualizarConteos();
    }
    document.addEventListener('DOMContentLoaded', iniciar);
})(window);
