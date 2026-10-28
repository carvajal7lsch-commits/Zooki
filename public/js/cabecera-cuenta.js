/** HU-T.5/T.17, RNF-16: avatar con menú en escritorio y hoja de cuenta en móvil; lo mismo para los avisos. */
(() => {
    'use strict';
    const cabecera = document.querySelector('[data-cabecera-cuenta]');
    if (!cabecera) return;
    const boton = cabecera.querySelector('[data-cabecera-cuenta-boton]');
    const origen = cabecera.parentElement;
    const movil = window.matchMedia('(max-width: 767px)');
    const menu = cabecera.querySelector('[data-cuenta-menu]');
    const dialogo = cabecera.querySelector('#cabeceraCuentaDialogo');
    const clinica = cabecera.querySelector('[data-clinica-cuenta]');
    const campana = cabecera.querySelector('#notifBell');
    const avisos = cabecera.querySelector('#notifDropdown');
    const panelAvisos = cabecera.querySelector('[data-notif-panel]');
    const dialogoAvisos = cabecera.querySelector('#notifDialogo');
    function cerrar() {
        cabecera.removeAttribute('data-abierta');
        boton.setAttribute('aria-expanded', 'false');
        if (dialogo.open) dialogo.close();
    }
    function cerrarAvisos() {
        avisos.classList.remove('active');
        campana.setAttribute('aria-expanded', 'false');
        if (dialogoAvisos.open) dialogoAvisos.close();
    }
    boton.addEventListener('click', () => {
        if (boton.getAttribute('aria-expanded') === 'true') {
            cerrar();
            return;
        }
        cerrarAvisos();
        cabecera.setAttribute('data-abierta', '');
        boton.setAttribute('aria-expanded', 'true');
        if (movil.matches) dialogo.showModal();
        else menu.querySelector('a')?.focus();
    });
    campana.addEventListener('click', () => {
        if (campana.getAttribute('aria-expanded') === 'true') {
            cerrarAvisos();
            return;
        }
        cerrar();
        campana.setAttribute('aria-expanded', 'true');
        if (movil.matches) dialogoAvisos.showModal();
        else avisos.classList.add('active');
    });
    dialogo.addEventListener('close', cerrar);
    dialogoAvisos.addEventListener('close', cerrarAvisos);
    document.addEventListener('click', evento => {
        if (!cabecera.contains(evento.target) || evento.target.closest('[data-cuenta-destino]')) cerrar();
        if (!evento.target.closest('.notifications-wrapper, #notifDialogo')) cerrarAvisos();
    });
    cabecera.addEventListener('keydown', evento => {
        if (evento.key !== 'Escape') return;
        const enAvisos = campana.getAttribute('aria-expanded') === 'true';
        cerrar();
        cerrarAvisos();
        (enAvisos ? campana : boton).focus();
    });
    function situar() {
        cerrar();
        cerrarAvisos();
        const seccion = document.querySelector('.ajustes-seccion');
        const destino = movil.matches && document.body.dataset.ajustesVista === 'seccion' && seccion ? seccion : origen;
        if (cabecera.parentElement !== destino) destino.append(cabecera);
        (movil.matches ? cabecera.querySelector('[data-cuenta-hoja]') : cabecera).append(menu);
        (movil.matches ? dialogoAvisos.querySelector('[data-notif-hoja]') : avisos).append(panelAvisos);
        cabecera.querySelector(movil.matches ? '[data-clinica-movil]' : '[data-clinica-escritorio]').append(clinica);
        boton.setAttribute('aria-haspopup', movil.matches ? 'dialog' : 'true');
        campana.setAttribute('aria-haspopup', movil.matches ? 'dialog' : 'true');
        campana.setAttribute('aria-controls', movil.matches ? 'notifDialogo' : 'notifDropdown');
    }
    movil.addEventListener('change', situar);
    new MutationObserver(situar).observe(document.body, { attributes: true, attributeFilter: ['data-ajustes-vista'] });
    situar();
})();
