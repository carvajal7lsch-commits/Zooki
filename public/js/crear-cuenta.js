/**
 * M0.1 — Un solo «Crear cuenta» bajo el inicio de sesión, con dos opciones:
 * «Soy propietario» gira la tarjeta al registro del propietario (HU-5.8) y
 * «Registrar mi clínica» lleva a su registro (HU-0.1). Sin JavaScript el
 * enlace abre el registro del propietario y la otra opción es un enlace.
 */
(function () {
    'use strict';

    function abrir(modal) {
        modal.hidden = false;
        document.body.classList.add('modal-open');
        setTimeout(() => modal.classList.add('active'), 10);
        const primera = modal.querySelector('[data-crear-cuenta]');
        if (primera) primera.focus();
    }

    function cerrar(modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
        setTimeout(() => { modal.hidden = true; }, 200);
    }

    function iniciar() {
        const enlace = document.querySelector('[data-crear-cuenta-abrir]');
        const modal = document.getElementById('crearCuentaModal');
        const flipper = document.getElementById('authFlipper');
        if (!enlace || !modal) return;
        enlace.addEventListener('click', evento => {
            evento.preventDefault();
            abrir(modal);
        });
        modal.addEventListener('click', evento => {
            if (evento.target === modal || evento.target.closest('[data-crear-cuenta-cerrar]')) {
                cerrar(modal);
                return;
            }
            const opcion = evento.target.closest('[data-crear-cuenta="propietario"]');
            if (!opcion) return;
            evento.preventDefault();
            cerrar(modal);
            if (flipper) flipper.classList.add('flipped');
        });
        document.addEventListener('keydown', evento => {
            if (evento.key === 'Escape' && !modal.hidden) cerrar(modal);
        });
    }

    document.addEventListener('DOMContentLoaded', iniciar);
    window.ZookiCrearCuenta = { iniciar, abrir, cerrar };
}());
