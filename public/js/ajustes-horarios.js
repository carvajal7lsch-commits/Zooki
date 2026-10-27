/** HU-7.1/HU-4.11, RNF-16: presentación de Horarios en Ajustes; conserva controles y sus escritores. */
(() => {
    'use strict';

    function iniciar() {
        const raiz = document.querySelector('[data-ajustes-horarios]');
        if (!raiz) return;
        raiz.classList.add('ajustes-horarios');
        const clinica = raiz.querySelector('#config-horarios');
        const equipo = raiz.querySelector('#perfilHorarios');
        const pestañas = document.createElement('div');
        pestañas.className = 'ajustes-horarios__tabs';
        pestañas.dataset.configPanel = 'horarios';
        pestañas.hidden = clinica.hidden;
        pestañas.setAttribute('role', 'tablist');
        pestañas.setAttribute('aria-label', 'Horarios de la clínica y del equipo');
        const paneles = {};
        for (const [clave, nombre, contenido] of [['clinica', 'Atención de la clínica', clinica], ['equipo', 'Equipo veterinario', equipo]]) {
            const panel = document.createElement('div');
            panel.className = 'ajustes-horarios__panel';
            panel.id = 'ajustes-horario-' + clave;
            panel.dataset.horariosPanel = clave;
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', 'ajustes-horario-tab-' + clave);
            contenido.before(panel);
            panel.append(contenido);
            paneles[clave] = panel;
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.id = 'ajustes-horario-tab-' + clave;
            boton.dataset.horariosTab = clave;
            boton.setAttribute('role', 'tab');
            boton.setAttribute('aria-controls', panel.id);
            boton.textContent = nombre;
            boton.addEventListener('click', () => elegir(clave, true));
            pestañas.append(boton);
        }
        raiz.prepend(pestañas);
        function elegir(clave, actualizarDireccion) {
            for (const [nombre, panel] of Object.entries(paneles)) panel.hidden = clave !== nombre;
            for (const boton of pestañas.querySelectorAll('button')) {
                const activa = boton.dataset.horariosTab === clave;
                boton.setAttribute('aria-selected', String(activa));
                boton.tabIndex = activa ? 0 : -1;
            }
            if (!actualizarDireccion) return;
            const url = new URL(location.href);
            if (clave === 'equipo') url.searchParams.set('vista_horarios', 'equipo');
            else url.searchParams.delete('vista_horarios');
            url.hash = 'horarios';
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        }
        pestañas.addEventListener('keydown', evento => {
            const botones = [...pestañas.querySelectorAll('button')];
            const indice = botones.indexOf(document.activeElement);
            const paso = evento.key === 'ArrowRight' ? 1 : evento.key === 'ArrowLeft' ? -1 : 0;
            if (indice < 0 || !paso) return;
            evento.preventDefault();
            const siguiente = botones[(indice + paso + botones.length) % botones.length];
            elegir(siguiente.dataset.horariosTab, true);
            siguiente.focus();
        });
        elegir(new URLSearchParams(location.search).get('vista_horarios') === 'equipo' ? 'equipo' : 'clinica', false);

        const form = equipo.querySelector('[data-horario-vet]');
        const barra = document.createElement('div');
        barra.className = 'ajustes-horarios__barra';
        barra.append(form.querySelector('[data-veterinario]').closest('label'), equipo.querySelector('[data-horario-varios]'));
        form.prepend(barra);

        const dialogo = document.createElement('dialog');
        dialogo.className = 'zk-dialogo';
        dialogo.id = 'ajustesSolicitudesHorario';
        dialogo.setAttribute('aria-labelledby', 'ajustesSolicitudesTitulo');
        dialogo.innerHTML = '<header class="zk-dialogo__head"><div><h3 id="ajustesSolicitudesTitulo">Solicitudes de cambio</h3><p>Horarios que los veterinarios enviaron para tu aprobación.</p></div><button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button></header><div class="zk-dialogo__cuerpo"></div>';
        const propuestas = equipo.querySelector('.coord-sub');
        propuestas.querySelector('h3').remove();
        dialogo.querySelector('.zk-dialogo__cuerpo').append(propuestas);
        equipo.append(dialogo);
        const solicitudes = document.createElement('button');
        solicitudes.type = 'button';
        solicitudes.className = 'coord-btn coord-btn--sec';
        solicitudes.dataset.abrirDialogo = dialogo.id;
        solicitudes.setAttribute('aria-haspopup', 'dialog');
        equipo.querySelector('.coord-card__head').append(solicitudes);
        const listaPropuestas = propuestas.querySelector('[data-propuestas]');
        const contar = () => { solicitudes.textContent = 'Solicitudes de cambio · ' + (listaPropuestas.dataset.pendientes || '0'); };
        new MutationObserver(contar).observe(listaPropuestas, { attributes: true, attributeFilter: ['data-pendientes'] });
        contar();

        const opciones = document.createElement('div');
        opciones.className = 'ajustes-horarios__opciones';
        const reglas = document.createElement('details');
        const tituloReglas = document.createElement('summary');
        tituloReglas.textContent = 'Reglas de los turnos';
        reglas.append(tituloReglas, clinica.querySelector('.horarios-notas'));
        opciones.append(reglas, clinica.querySelector('#btnRestaurar'));
        clinica.querySelector('.horarios').append(opciones);

        const movil = matchMedia('(max-width: 767px)');
        const filas = [...raiz.querySelectorAll('.horario-dia')].map(fila => {
            const desplegable = document.createElement('details');
            desplegable.className = 'ajustes-horarios__dia';
            const resumen = document.createElement('summary');
            const texto = document.createElement('span');
            const nombre = document.createElement('strong');
            nombre.textContent = fila.querySelector('.horario-dia__nombre > span').textContent;
            const rangos = document.createElement('small');
            texto.append(nombre, rangos);
            const flecha = document.createElement('i');
            flecha.className = 'fas fa-chevron-down';
            flecha.setAttribute('aria-hidden', 'true');
            resumen.append(texto, flecha);
            desplegable.append(resumen);
            return { fila, desplegable, rangos };
        });
        function actualizarFilas() {
            for (const { fila, rangos } of filas) {
                const activo = fila.querySelector('[data-dia-activo]').checked;
                const especial = fila.querySelector('[data-horario-especial]');
                const turnos = [...fila.querySelectorAll('[data-turno]')].filter(turno => turno.querySelector('.bloque-activo').checked);
                const resumen = !activo ? (fila.closest('[data-franjas]') ? 'Libre' : 'Cerrado')
                    : especial && !especial.hidden ? 'Horario especial · ver turnos'
                    : turnos.map(turno => [...turno.querySelectorAll('input[type="time"]')].map(campo => campo.value).join('–')).join(' · ') || 'Sin turnos';
                if (rangos.textContent !== resumen) rangos.textContent = resumen;
            }
        }
        function adaptar() {
            for (const { fila, desplegable } of filas) {
                if (movil.matches && !desplegable.isConnected) {
                    fila.before(desplegable);
                    desplegable.append(fila);
                } else if (!movil.matches && desplegable.isConnected) {
                    desplegable.before(fila);
                    desplegable.remove();
                }
            }
            actualizarFilas();
        }
        // Se mueven los nodos originales: girar el celular no duplica entradas ni pierde los oyentes del guardado.
        movil.addEventListener('change', adaptar);
        raiz.addEventListener('change', actualizarFilas);
        for (const tabla of raiz.querySelectorAll('.horarios-tabla')) {
            new MutationObserver(actualizarFilas).observe(tabla, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
        }
        adaptar();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();
