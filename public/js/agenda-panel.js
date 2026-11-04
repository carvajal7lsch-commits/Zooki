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
        const orden = ['urgencias', 'triage', 'llegadas', 'reasignaciones'];
        const conteos = Object.fromEntries(orden.map(clave => [clave, 0]));
        const pestanaPedida = orden.find(clave => global.location.hash === '#' + clave);
        let vista = pestanaPedida ? 'pendientes' : 'citas';
        let esMovil = false;
        let eleccionManual = Boolean(pestanaPedida);

        const cantidad = selector => herramientas.querySelectorAll(selector).length;
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
            // Revisadas es una vista de historia: no reemplaza el número de triages pendientes.
            if (herramientas.querySelector('[data-solicitudes-filtro]').value === '0') {
                conteos.triage = cantidad('[data-solicitudes-lista] > article');
            }
            conteos.llegadas = cantidad('[data-llegadas-citas] > article, [data-llegadas-solicitudes] > article, [data-llegadas-espera] > article');
            conteos.reasignaciones = cantidad('[data-reasignaciones-lista] > article');
            conteos.urgencias = cantidad('[data-rojos-lista] > article, [data-ingresos-lista] [data-conciliar-identidad]');
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
        const fecha = new Date().toLocaleDateString('es-CO', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
        herramientas.querySelector('[data-agenda-pendientes-fecha]').textContent = fecha.charAt(0).toUpperCase() + fecha.slice(1);
        const observador = new MutationObserver(actualizarConteos);
        herramientas.querySelectorAll('.ah-lista').forEach(lista => observador.observe(lista, { childList: true, subtree: true }));
        global.ZookiPanelAgenda = {
            // Pendientes es siempre de hoy: elegir otro día o una cita en el calendario muestra Citas con ese día.
            mostrarCitas() {
                if (!esMovil && vista !== 'citas') mostrarVista('citas');
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
                // Un enlace directo a Sala expresa una elección; el conteo de otra bandeja no la sustituye.
                mostrarVista(vista, !pestanaPedida);
            }
        };
        // La bandeja de triage es diferida; se consulta también para contarla antes de abrir sus tarjetas.
        herramientas.querySelector('[data-agenda-solicitudes]').dispatchEvent(new CustomEvent('agenda:mostrar'));
        actualizarConteos();
    }
    document.addEventListener('DOMContentLoaded', iniciar);
})(window);
