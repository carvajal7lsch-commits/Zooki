/* RNF-16: el navegador puede omitir una transición (carga lenta, recuperación o falta de soporte). */
(function () {
    'use strict';

    const raiz = document.documentElement;
    let revelada = false;

    function respaldo() {
        raiz.classList.add('navegacion-respaldo');
    }

    function cerrarSinInterrumpir(transicion) {
        // El navegador puede abortar su captura; eso no es un error de la navegación de Zooki.
        transicion.finished.catch(() => {});
        transicion.updateCallbackDone.catch(() => {});
    }

    window.addEventListener('pagereveal', evento => {
        revelada = true;
        if (!evento.viewTransition) {
            respaldo();
            return;
        }
        cerrarSinInterrumpir(evento.viewTransition);
        evento.viewTransition.ready.catch(respaldo);
    });
    window.addEventListener('pageswap', evento => {
        if (!evento.viewTransition) return;
        cerrarSinInterrumpir(evento.viewTransition);
        evento.viewTransition.ready.catch(() => {});
    });
    document.addEventListener('DOMContentLoaded', () => {
        if (!('PageRevealEvent' in window) && !revelada) respaldo();
    }, { once: true });
}());
