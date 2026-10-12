/**
 * M0.1 — Piezas comunes del registro del propietario y del de clínica:
 *
 * - Resumen del formulario: el detalle que no cabe en el aviso de una línea
 *   de cada campo, y la respuesta del servidor cuando rechaza el envío.
 * - Ventana de espera (views/partials/registro_espera.php): primero «Enviando
 *   tu registro…» y, si el servidor acepta, «Revisa tu correo». La respuesta
 *   es la misma exista o no la cuenta (RE-T.13.2), así que no hay sondeo.
 */
(function () {
    'use strict';

    const SIN_CONEXION = 'No pudimos comunicarnos con el servidor. Inténtalo nuevamente.';

    function resumen(form) {
        return form.querySelector('[data-registro-resumen]');
    }

    function etiqueta(campo) {
        const grupo = campo.closest('.input-group, .consent-check');
        const label = grupo ? grupo.querySelector('label') : null;
        if (label && label !== grupo) return label.textContent.trim();
        return campo.name === 'acepta_datos' ? 'Política de datos' : (campo.name || '');
    }

    function limpiarResumen(form) {
        const salida = resumen(form);
        if (!salida) return;
        salida.textContent = '';
        salida.hidden = true;
    }

    /** Mensaje principal y, si hay, una línea por campo con su detalle completo. */
    function mostrarResumen(form, mensaje, detalles = []) {
        const salida = resumen(form);
        if (!salida) return;
        salida.textContent = '';
        const titulo = document.createElement('p');
        titulo.textContent = mensaje;
        salida.appendChild(titulo);
        if (detalles.length > 0) {
            const lista = document.createElement('ul');
            detalles.forEach(detalle => {
                const item = document.createElement('li');
                item.textContent = detalle;
                lista.appendChild(item);
            });
            salida.appendChild(lista);
        }
        salida.hidden = false;
    }

    function espera(form) {
        return document.getElementById(form.dataset.registroEspera);
    }

    function estado(ventana, nombre) {
        ventana.querySelectorAll('[data-espera-estado]').forEach(bloque => {
            bloque.hidden = bloque.dataset.esperaEstado !== nombre;
        });
    }

    function cargando(form) {
        const ventana = espera(form);
        if (!ventana) return;
        estado(ventana, 'cargando');
        form.hidden = true;
        ventana.hidden = false;
    }

    function listo(form, mensaje) {
        const ventana = espera(form);
        if (!ventana) return;
        const salida = ventana.querySelector('[data-espera-mensaje]');
        if (salida && mensaje) salida.textContent = mensaje;
        estado(ventana, 'listo');
        form.hidden = true;
        ventana.hidden = false;
    }

    function volverAlFormulario(form) {
        const ventana = espera(form);
        if (ventana) ventana.hidden = true;
        form.hidden = false;
    }

    /**
     * Envía el formulario por AJAX con la ventana de carga. Devuelve la
     * respuesta del servidor; con un error, vuelve al formulario con el
     * mensaje en el resumen. El CAPTCHA se renueva siempre: Cloudflare
     * consume el token aunque otro campo se rechace (RE-5.8.6).
     */
    async function enviar(form, accion) {
        limpiarResumen(form);
        cargando(form);
        let datos;
        try {
            const respuesta = await fetch('index.php?action=' + accion, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const tipo = respuesta.headers.get('content-type') || '';
            datos = tipo.includes('application/json')
                ? await respuesta.json()
                : { success: false, message: 'La página venció. Recárgala e inténtalo de nuevo.' };
        } catch (error) {
            datos = { success: false, message: SIN_CONEXION };
        } finally {
            window.ZookiTurnstile?.reiniciar(form);
        }
        if (datos.success) {
            listo(form, datos.message);
        } else {
            volverAlFormulario(form);
            mostrarResumen(form, datos.message || SIN_CONEXION);
        }
        return datos;
    }

    function vincular(form) {
        form.addEventListener('cuenta:invalida', evento => {
            const detalles = evento.detail.campos.map(campo => {
                const mensaje = campo.validationMessage || 'Revisa este campo.';
                return etiqueta(campo) + ': ' + mensaje;
            });
            mostrarResumen(form, 'Revisa estos campos antes de continuar:', detalles);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[data-registro]').forEach(vincular);
    });

    window.ZookiRegistro = { enviar, cargando, listo, volverAlFormulario, mostrarResumen, limpiarResumen, vincular };
}());
