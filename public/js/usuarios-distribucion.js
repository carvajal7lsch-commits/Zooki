/** HU-T.7/RNF-16: comparte la distribución del directorio veterinario; mueve los mismos controles. */
(() => {
    'use strict';
    const modulo = document.getElementById('usuariosModulo');
    const destino = document.querySelector('[data-usuarios-cabecera-pestanas]');
    if (!modulo || !destino) return;
    const pestanas = modulo.querySelector('.tabs-wrapper');
    const origen = modulo.querySelector('[data-usuarios-origen-pestanas]');
    const acciones = modulo.querySelector('[data-usuarios-barra-acciones]');
    const nuevo = modulo.querySelector('[data-accion="nuevo"]');
    const movil = window.matchMedia('(max-width: 767px)');
    acciones.append(nuevo);
    function situar() {
        (movil.matches ? origen : destino).append(pestanas);
    }
    movil.addEventListener('change', situar);
    situar();
})();
