/** RNF-16: prepara la llegada antes del primer pintado; nunca muestra primero la tarjeta de frente. */
(function () {
    'use strict';
    let marcada = false;
    try {
        marcada = sessionStorage.getItem('zooki-acceso-giro') === '1';
        sessionStorage.removeItem('zooki-acceso-giro');
    } catch (error) {
        return;
    }
    const reducido = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    if (!marcada || reducido) return;
    const raiz = document.documentElement;
    raiz.classList.add('acceso-giro-preparado');
    raiz.classList.add('acceso-giro-llegada');
    // Si el script de la pantalla falla al cargar, el formulario sigue siendo accesible.
    const LIMITE_PREPARACION_MS = 5000;
    setTimeout(() => raiz.classList.remove('acceso-giro-preparado'), LIMITE_PREPARACION_MS);
}());
