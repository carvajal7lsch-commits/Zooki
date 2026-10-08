/**
 * HU-T.16 (RE-T.16.2) — Con la sesión vencida el servidor responde 401 a las
 * peticiones AJAX; aquí la interfaz lleva al inicio de sesión, donde se ve el
 * motivo (lo deja helpers/Sesion.php). Así ningún módulo tiene que repetirlo.
 *
 * Envuelve fetch una sola vez; se carga en los layouts del personal y del portal.
 */
(function () {
    'use strict';

    if (typeof window.fetch !== 'function' || window.fetch.zookiSesion) return;

    const fetchOriginal = window.fetch;
    const conSesion = async function (...argumentos) {
        const respuesta = await fetchOriginal.apply(this, argumentos);
        if (respuesta.status === 401) {
            window.location.href = 'index.php?action=login';
        }
        return respuesta;
    };
    conSesion.zookiSesion = true;
    window.fetch = conSesion;
})();
