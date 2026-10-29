/**
 * M0.2 — El registro de clínica es otra página, pero entra y sale con el
 * mismo giro de tarjeta del registro del propietario: la tarjeta que se va
 * gira hasta quedar de canto, se navega, y la página nueva la termina de
 * girar. La marca viaja en sessionStorage solo para ese salto.
 *
 * Sin JavaScript los enlaces navegan normal; quien prefiere menos movimiento
 * no ve el giro.
 */
(function () {
    'use strict';

    const MARCA = 'zooki-acceso-giro';
    const DURACION_MS = 220;

    function sinMovimiento() {
        return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function tarjeta() {
        return document.querySelector('[data-acceso-giro-tarjeta]');
    }

    function marcar() {
        try {
            sessionStorage.setItem(MARCA, '1');
        } catch (error) {
            // Sin almacenamiento la página nueva solo aparece sin girar.
        }
    }

    /** Primera mitad del giro y, al terminar, la navegación. */
    function salir(destino) {
        const elemento = tarjeta();
        if (!elemento || sinMovimiento()) {
            window.location.href = destino;
            return;
        }
        marcar();
        elemento.classList.add('acceso-giro-salida');
        setTimeout(() => { window.location.href = destino; }, DURACION_MS);
    }

    /** Segunda mitad del giro, al llegar desde la otra página. */
    function entrar() {
        const raiz = document.documentElement;
        if (!raiz.classList.contains('acceso-giro-preparado')) return;
        const elemento = tarjeta();
        if (!elemento || sinMovimiento()) {
            raiz.classList.remove('acceso-giro-preparado');
            return;
        }
        // La clase de animación se aplica antes de retirar la posición de canto del head.
        elemento.classList.add('acceso-giro-entrada');
        raiz.classList.remove('acceso-giro-preparado');
        setTimeout(() => elemento.classList.remove('acceso-giro-entrada'), DURACION_MS);
    }

    document.addEventListener('click', evento => {
        const enlace = evento.target.closest('a[data-acceso-giro-ir]');
        // registro-pasos.js usa la misma flecha para volver de paso y ya canceló la navegación.
        if (!enlace || evento.defaultPrevented) return;
        evento.preventDefault();
        const modal = enlace.closest('.modal-backdrop');
        if (modal && window.ZookiCrearCuenta) window.ZookiCrearCuenta.cerrar(modal);
        salir(enlace.getAttribute('href'));
    });
    document.addEventListener('DOMContentLoaded', entrar);
    window.ZookiAccesoGiro = { salir, entrar, DURACION_MS };
}());
