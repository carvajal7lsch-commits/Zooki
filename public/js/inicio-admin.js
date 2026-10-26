/**
 * Inicio del administrador (HU-6.2, views/admin/panel.php).
 *
 * Todo lo que necesita es común con el Inicio del veterinario y está en
 * inicio-comun.js, que se carga antes: tocar un día cambia la agenda por
 * veterinario (RE-6.2.9), las flechas cambian de mes (RE-6.2.8) y una
 * respuesta vieja nunca pisa a la nueva. La acción AJAX es la de
 * data-inicio-accion (panel_admin_dia_ajax). Al cambiar de día, la agenda
 * nueva vuelve a empezar por el primer veterinario.
 */
(function (global) {
    'use strict';

    function iniciar(raiz) {
        global.ZookiInicio.iniciarCalendario(raiz);
        global.ZookiInicio.iniciarDialogos(raiz);
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { iniciar };
        return;
    }

    const raiz = global.document.querySelector('[data-inicio]');
    if (!raiz) return;
    iniciar(raiz);
    global.ZookiInicio.recargarAlVolver(global.document);
})(globalThis);
