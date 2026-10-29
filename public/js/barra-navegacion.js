/* R / RNF-16: ayudas de la barra compartida; los enlaces conservan su navegación nativa. */
(function () {
    'use strict';

    const barra = document.querySelector('.pill-sidebar');
    if (!barra || barra.dataset.ayudasListas) return;
    barra.dataset.ayudasListas = '1';

    const ayuda = document.createElement('div');
    ayuda.id = 'barraNavegacionAyuda';
    ayuda.className = 'pill-ayuda';
    ayuda.setAttribute('role', 'tooltip');
    ayuda.hidden = true;
    document.body.appendChild(ayuda);
    let activador = null;
    let descripcionAnterior = '';

    for (const enlace of barra.querySelectorAll('.pill-nav-item, .pill-logo, .pill-logout')) {
        enlace.dataset.barraAyuda = enlace.getAttribute('aria-label') || enlace.title || '';
        // Con JS hay ayuda inmediata; sin JS se conserva el title de PHP como respaldo.
        enlace.removeAttribute('title');
    }

    function ocultar() {
        ayuda.hidden = true;
        if (activador) {
            if (descripcionAnterior) activador.setAttribute('aria-describedby', descripcionAnterior);
            else activador.removeAttribute('aria-describedby');
        }
        activador = null;
    }

    function mostrar(elemento) {
        if (!window.matchMedia('(min-width: 768px)').matches) return;
        if (elemento === activador && !ayuda.hidden) return;
        ocultar();
        activador = elemento;
        descripcionAnterior = elemento.getAttribute('aria-describedby') || '';
        elemento.setAttribute('aria-describedby', [descripcionAnterior, ayuda.id].filter(Boolean).join(' '));
        ayuda.textContent = elemento.dataset.barraAyuda;
        ayuda.hidden = false;
        const fila = elemento.getBoundingClientRect();
        const icono = elemento.querySelector(':scope > i, :scope > svg, .pill-logo-img')?.getBoundingClientRect();
        const referencia = icono?.width ? icono : fila;
        const tamano = ayuda.getBoundingClientRect();
        // La etiqueta acompaña al icono, incluso cuando la pestaña activa ocupa todo el lateral.
        const izquierda = Math.min(referencia.right + 18, window.innerWidth - tamano.width - 12);
        const arriba = Math.max(12, Math.min(referencia.top + (referencia.height - tamano.height) / 2, window.innerHeight - tamano.height - 12));
        ayuda.style.left = Math.max(14, izquierda) + 'px';
        ayuda.style.top = arriba + 'px';
    }

    barra.addEventListener('pointerover', evento => {
        if (evento.pointerType === 'touch') return;
        const enlace = evento.target.closest('[data-barra-ayuda]');
        if (enlace && barra.contains(enlace)) mostrar(enlace);
    });
    barra.addEventListener('pointerout', evento => {
        if (activador && !activador.contains(evento.relatedTarget)) ocultar();
    });
    barra.addEventListener('focusin', evento => {
        const enlace = evento.target.closest('[data-barra-ayuda]');
        if (enlace) mostrar(enlace);
    });
    barra.addEventListener('focusout', ocultar);
    barra.addEventListener('click', ocultar);
    document.addEventListener('keydown', evento => {
        if (evento.key === 'Escape') ocultar();
    });
    window.addEventListener('resize', ocultar);
    window.addEventListener('scroll', ocultar, { passive: true, capture: true });
    window.addEventListener('pageswap', ocultar);
    window.addEventListener('pageshow', ocultar);
}());
