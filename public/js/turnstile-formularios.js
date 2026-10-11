/** RE-0.1.4/RE-5.8.6: conservar el id de cada widget para renovar solo el formulario enviado. */
(function () {
    const widgets = new WeakMap();
    function iniciar() {
        if (!window.turnstile) return;
        document.querySelectorAll('[data-turnstile-formulario]').forEach(contenedor => {
            if (widgets.has(contenedor)) return;
            const id = window.turnstile.render(contenedor, { sitekey: contenedor.dataset.sitekey });
            widgets.set(contenedor, id);
        });
    }
    function reiniciar(formulario) {
        const contenedor = formulario.querySelector('[data-turnstile-formulario]');
        const id = contenedor ? widgets.get(contenedor) : undefined;
        if (id !== undefined && window.turnstile) window.turnstile.reset(id);
    }
    window.ZookiTurnstile = { iniciar, reiniciar };
    window.zookiTurnstileListo = iniciar;
    document.addEventListener('DOMContentLoaded', iniciar);
}());
