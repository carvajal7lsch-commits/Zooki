/**
 * HU-0.1: envío con CAPTCHA de un solo uso; no informa qué cuenta recibió el
 * enlace. M0.1: la misma ventana de carga y de «Revisa tu correo» del
 * registro del propietario (registro-cuenta.js); si el servidor rechaza el
 * NIT por duplicado, vuelve al paso 1 con «Reportar a soporte».
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registroClinicaForm');
    if (!form) return;
    // RNF-16: sin pasos de JavaScript se conservan las acciones en el flujo normal.
    if (window.ZookiPasos) {
        form.querySelectorAll('.registro-acciones').forEach(acciones => acciones.classList.add('z-barra-inferior'));
    }
    const reporte = document.getElementById('reportarNit');
    form.addEventListener('cuenta:limitada', () => {
        window.ZookiRegistro.mostrarResumen(form, 'Hiciste muchas comprobaciones seguidas. Espera unos segundos y vuelve a intentar');
    });
    form.addEventListener('cuenta:validada', evento => {
        if (evento.detail.campo === 'nit') reporte.hidden = !evento.detail.nitDuplicado;
    });
    form.addEventListener('input', evento => {
        if (evento.target.name === 'nit') reporte.hidden = true;
    });
    form.addEventListener('submit', async evento => {
        evento.preventDefault();
        const datos = await window.ZookiRegistro.enviar(form, 'registrar_clinica_ajax');
        if (datos.success) {
            form.reset();
            return;
        }
        if (datos.reportar_nit) {
            reporte.hidden = false;
            window.ZookiPasos?.ir(form, 1);
        }
    });
    // El reporte no cambia de pantalla: responde en el resumen del formulario.
    reporte.addEventListener('click', async () => {
        reporte.disabled = true;
        try {
            const respuesta = await fetch('index.php?action=reportar_nit_ajax', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const datos = await respuesta.json();
            window.ZookiRegistro.mostrarResumen(form, datos.message);
            if (datos.success) window.zookiToast(datos.message);
        } catch (error) {
            window.ZookiRegistro.mostrarResumen(form, 'No se pudo enviar el reporte. Inténtalo nuevamente.');
        } finally {
            reporte.disabled = false;
            window.ZookiTurnstile?.reiniciar(form);
        }
    });
});
