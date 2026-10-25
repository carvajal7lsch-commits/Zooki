/* S1: la pantalla es de lectura; ninguna interacción puede iniciar un pago. */
(() => {
    'use strict';
    function iniciar(raiz) {
        const boton = raiz?.querySelector('.suscripcion__pagar');
        if (boton) boton.disabled = true;
    }
    if (typeof module !== 'undefined') module.exports = { iniciar };
    if (typeof document !== 'undefined') iniciar(document.querySelector('[data-suscripcion-pantalla]'));
})();
