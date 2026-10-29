/**
 * Pulido de interfaz (2026-10-21): ventanas nativas (<dialog>) y pestañas de Configuración.
 * En el celular la ventana se muestra como hoja inferior (CSS); Esc y el fondo la cierran.
 */
(function (global) {
    'use strict';

    function cerrarDialogo(elemento) {
        const dialogo = elemento && elemento.closest ? elemento.closest('dialog') : null;
        if (dialogo && dialogo.open) dialogo.close();
    }

    function abrirDialogo(id) {
        const dialogo = document.getElementById(id);
        if (!dialogo || dialogo.open) return dialogo;
        if (typeof dialogo.showModal === 'function') dialogo.showModal();
        else dialogo.setAttribute('open', '');
        const primero = dialogo.querySelector('[autofocus]') || dialogo.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea');
        if (primero) primero.focus();
        return dialogo;
    }

    function activarPestana(raiz, clave, enfocar) {
        const tabs = Array.from(raiz.querySelectorAll('[data-config-tab]'));
        if (!tabs.some(tab => tab.dataset.configTab === clave)) clave = tabs[0].dataset.configTab;
        tabs.forEach(tab => {
            const activa = tab.dataset.configTab === clave;
            tab.setAttribute('aria-selected', activa ? 'true' : 'false');
            tab.tabIndex = activa ? 0 : -1;
            if (activa && enfocar) tab.focus();
        });
        document.querySelectorAll('[data-config-panel]').forEach(panel => {
            panel.hidden = panel.dataset.configPanel !== clave;
        });
        try {
            global.history.replaceState(null, '', '#' + clave);
        } catch (_) {
            // Sin historial (vista embebida): la pestaña igual cambia.
        }
    }

    function iniciar() {
        // Varias vistas pueden cargar este archivo; los oyentes se registran una sola vez.
        if (global.__zookiDialogos) return;
        global.__zookiDialogos = true;
        let fondoPulsado = null;
        document.addEventListener('pointerdown', evento => {
            fondoPulsado = null;
            const dialogo = evento.target;
            if (dialogo.tagName !== 'DIALOG' || !dialogo.classList.contains('zk-dialogo')) return;
            const rect = dialogo.getBoundingClientRect();
            const fuera = evento.clientX < rect.left || evento.clientX > rect.right || evento.clientY < rect.top || evento.clientY > rect.bottom;
            if (fuera) fondoPulsado = dialogo;
        });
        document.addEventListener('click', evento => {
            const fondo = fondoPulsado;
            fondoPulsado = null;
            const abrir = evento.target.closest('[data-abrir-dialogo]');
            if (abrir) {
                abrirDialogo(abrir.dataset.abrirDialogo);
                return;
            }
            const cerrar = evento.target.closest('[data-cerrar-dialogo]');
            if (cerrar) {
                cerrarDialogo(cerrar);
                return;
            }
            // Abrir un calendario entre la pulsación y la suelta puede redirigir el clic al diálogo.
            // Solo se cierra si el gesto empezó y terminó en el fondo exterior.
            if (evento.target === fondo) {
                const rect = fondo.getBoundingClientRect();
                const fuera = evento.clientX < rect.left || evento.clientX > rect.right || evento.clientY < rect.top || evento.clientY > rect.bottom;
                if (fuera) fondo.close();
            }
        });
        const tabs = document.querySelector('[data-config-tabs]');
        if (!tabs) return;
        const lista = Array.from(tabs.querySelectorAll('[data-config-tab]'));
        lista.forEach((tab, indice) => {
            tab.addEventListener('click', () => activarPestana(tabs, tab.dataset.configTab, false));
            tab.addEventListener('keydown', evento => {
                const vertical = tabs.getAttribute('aria-orientation') === 'vertical';
                const paso = evento.key === 'ArrowRight' || (vertical && evento.key === 'ArrowDown') ? 1 : evento.key === 'ArrowLeft' || (vertical && evento.key === 'ArrowUp') ? -1 : 0;
                if (!paso) return;
                evento.preventDefault();
                activarPestana(tabs, lista[(indice + paso + lista.length) % lista.length].dataset.configTab, true);
            });
        });
        activarPestana(tabs, (global.location.hash || '').slice(1), false);
        global.addEventListener('hashchange', () => activarPestana(tabs, (global.location.hash || '').slice(1), false));
    }

    global.zookiAbrirDialogo = abrirDialogo;
    global.zookiCerrarDialogo = cerrarDialogo;
    if (typeof module !== 'undefined' && module.exports) module.exports = { activarPestana, cerrarDialogo };
    if (typeof document === 'undefined') return;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})(typeof window !== 'undefined' ? window : globalThis);
