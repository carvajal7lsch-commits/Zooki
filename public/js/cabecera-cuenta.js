/** HU-T.5/T.17, RNF-16: avatar con menú en escritorio y hoja de cuenta en móvil. */
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
    function cerrar() {
        cabecera.removeAttribute('data-abierta');
        boton.setAttribute('aria-expanded', 'false');
        if (dialogo.open) dialogo.close();
    }
    boton.addEventListener('click', () => {
        if (boton.getAttribute('aria-expanded') === 'true') {
            cerrar();
            return;
        }
        cabecera.setAttribute('data-abierta', '');
        boton.setAttribute('aria-expanded', 'true');
        cabecera.querySelector('.notif-dropdown')?.classList.remove('active');
        if (movil.matches) dialogo.showModal();
        else menu.querySelector('a')?.focus();
    });
    dialogo.addEventListener('close', cerrar);
    document.addEventListener('click', evento => {
        if (!cabecera.contains(evento.target) || evento.target.closest('[data-cuenta-destino]')) cerrar();
        if (evento.target.closest('#notifBell')) cerrar();
    });
    cabecera.addEventListener('keydown', evento => {
        if (evento.key !== 'Escape') return;
        cerrar();
        boton.focus();
    });
    function situar() {
        cerrar();
        const seccion = document.querySelector('.ajustes-seccion');
        const destino = movil.matches && document.body.dataset.ajustesVista === 'seccion' && seccion ? seccion : origen;
        if (cabecera.parentElement !== destino) destino.append(cabecera);
        (movil.matches ? cabecera.querySelector('[data-cuenta-hoja]') : cabecera).append(menu);
        cabecera.querySelector(movil.matches ? '[data-clinica-movil]' : '[data-clinica-escritorio]').append(clinica);
        boton.setAttribute('aria-haspopup', movil.matches ? 'dialog' : 'true');
    }
    movil.addEventListener('change', situar);
    new MutationObserver(situar).observe(document.body, { attributes: true, attributeFilter: ['data-ajustes-vista'] });
    situar();
})();
