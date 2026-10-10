/** HU-0.1: envío con CAPTCHA de un solo uso; no informa qué cuenta recibió el enlace. */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registroClinicaForm');
    if (!form) return;
    const salida = document.getElementById('registroClinicaResultado');
    const reporte = document.getElementById('reportarNit');
    const enviar = async (accion, boton) => {
        boton.disabled = true;
        try {
            const respuesta = await fetch('index.php?action=' + accion, { method: 'POST', body: new FormData(form) });
            const datos = await respuesta.json();
            salida.textContent = datos.message;
            reporte.hidden = !datos.reportar_nit;
            if (datos.success) {
                window.zookiToast(datos.message);
                if (accion === 'registrar_clinica_ajax') form.reset();
            }
        } catch (error) {
            salida.textContent = 'No se pudo completar la solicitud. Inténtalo nuevamente.';
        } finally {
            boton.disabled = false;
            window.ZookiTurnstile?.reiniciar(form);
        }
    };
    form.addEventListener('submit', evento => {
        evento.preventDefault();
        enviar('registrar_clinica_ajax', evento.submitter || form.querySelector('button[type="submit"]'));
    });
    reporte.addEventListener('click', () => enviar('reportar_nit_ajax', reporte));
});
