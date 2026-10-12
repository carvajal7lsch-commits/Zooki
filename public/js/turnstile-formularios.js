/**
 * RE-0.1.4, RE-5.8.6 y RE-T.13.5: un widget de Turnstile por formulario,
 * con el id guardado para renovar solo el del formulario enviado.
 *
 * M0.1: tema claro siempre y ancho del formulario como máximo. Los registros
 * usan «interaction-only» (Cloudflare solo lo muestra si necesita que la
 * persona interactúe); el login lo pinta solo cuando el servidor lo exige.
 * Un contenedor oculto (o dentro de un paso oculto) no se pinta hasta que
 * se muestre con mostrar().
 */
(function () {
    const widgets = new WeakMap();
    // Por debajo de este ancho el widget flexible se saldría del formulario.
    const ANCHO_MINIMO_FLEXIBLE = 300;

    function opciones(contenedor) {
        return {
            sitekey: contenedor.dataset.sitekey,
            theme: 'light',
            language: 'es',
            size: contenedor.clientWidth > 0 && contenedor.clientWidth < ANCHO_MINIMO_FLEXIBLE ? 'compact' : 'flexible',
            appearance: contenedor.dataset.turnstileApariencia || 'interaction-only',
        };
    }

    function pintar(contenedor) {
        if (!window.turnstile || widgets.has(contenedor)) return;
        if (contenedor.hidden || contenedor.closest('[hidden]')) return;
        widgets.set(contenedor, window.turnstile.render(contenedor, opciones(contenedor)));
    }

    function iniciar() {
        document.querySelectorAll('[data-turnstile-formulario]').forEach(pintar);
    }

    function mostrar(formulario) {
        const contenedor = formulario.querySelector('[data-turnstile-formulario]');
        if (!contenedor) return;
        contenedor.hidden = false;
        pintar(contenedor);
    }

    function reiniciar(formulario) {
        const contenedor = formulario.querySelector('[data-turnstile-formulario]');
        const id = contenedor ? widgets.get(contenedor) : undefined;
        if (id !== undefined && window.turnstile) window.turnstile.reset(id);
    }

    window.ZookiTurnstile = { iniciar, mostrar, reiniciar };
    window.zookiTurnstileListo = iniciar;
    document.addEventListener('DOMContentLoaded', iniciar);
}());
