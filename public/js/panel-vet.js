/**
 * Panel de inicio del veterinario (HU-18).
 *
 * La vista llega pintada desde el servidor; aquí solo se inicia la atención,
 * se habilita el botón cuando llega la ventana de inicio (RN-408) y se
 * refrescan los datos al volver a la pestaña después de un rato.
 */
(() => {
    const panel = document.querySelector('.panel');
    if (!panel) return;

    const REVISAR_CADA_MS = 20 * 1000;
    const REFRESCAR_TRAS_MS = 5 * 60 * 1000;

    async function iniciarAtencion(boton) {
        boton.disabled = true;
        try {
            const res = await fetch('index.php?action=iniciar_cita_ajax', {
                method: 'POST',
                body: new URLSearchParams({ id_cita: boton.dataset.iniciar }),
            });
            const r = await res.json();
            if (!r.success) {
                zookiAviso(r.message || 'No se pudo iniciar la atención.');
                boton.disabled = false;
                return;
            }
            window.location.href = r.redirect_url
                || `index.php?action=vet_atencion&id_cita=${encodeURIComponent(boton.dataset.iniciar)}`;
        } catch (e) {
            console.error(e);
            zookiAviso('No se pudo iniciar la atención. Revisa tu conexión.');
            boton.disabled = false;
        }
    }

    // RN-408: el servidor pinta el botón deshabilitado con la hora desde la que
    // se puede iniciar; al llegar esa hora se habilita sin recargar la página.
    function habilitarBotonesListos() {
        const ahora = Date.now();
        panel.querySelectorAll('button[data-iniciar][data-desde]:disabled').forEach(boton => {
            if (ahora < Date.parse(boton.dataset.desde)) return;
            delete boton.dataset.desde;
            boton.removeAttribute('title');
            boton.innerHTML = '<i class="fas fa-play"></i> Iniciar atención';
            boton.disabled = false;
        });
    }

    panel.addEventListener('click', e => {
        const boton = e.target.closest('button[data-iniciar]');
        if (boton && !boton.disabled) iniciarAtencion(boton);
    });

    habilitarBotonesListos();
    setInterval(habilitarBotonesListos, REVISAR_CADA_MS);

    // La agenda cambia durante el día: si la pestaña estuvo oculta un buen
    // rato, al volver se recarga para no mostrar estados viejos.
    let ocultaDesde = null;
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            ocultaDesde = Date.now();
        } else if (ocultaDesde && Date.now() - ocultaDesde > REFRESCAR_TRAS_MS) {
            window.location.reload();
        }
    });
})();
