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
        const oferta = raiz.querySelector?.('[data-inicio-oferta]');
        if (oferta) {
            const clave = 'zooki_oferta_lanzamiento_' + oferta.dataset.clinica;
            try {
                oferta.hidden = global.localStorage.getItem(clave) === 'cerrada';
            } catch (error) {
                // La indisponibilidad del almacenamiento no impide cerrar el aviso visible.
            }
            oferta.querySelector('[data-cerrar-oferta]').addEventListener('click', () => {
                oferta.hidden = true;
                try {
                    global.localStorage.setItem(clave, 'cerrada');
                } catch (error) {
                    // El cierre de esta página no depende del almacenamiento local.
                }
            });
        }
        const calendario = global.ZookiInicio.iniciarCalendario(raiz);
        global.ZookiInicio.iniciarMovil(raiz, calendario);
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
