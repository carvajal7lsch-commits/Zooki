/**
 * Inicio del veterinario (HU-6.1, views/vet/area.php).
 *
 * Lo común con el Inicio del administrador (cambiar de día y de mes, las
 * ventanas y recargar al volver) está en inicio-comun.js, que se carga antes.
 * Aquí queda lo del veterinario: registrar la llegada del siguiente paciente,
 * iniciar la atención cuando llega su ventana (RN-407) y las fotos de los
 * pacientes recientes.
 */
(function (global) {
    'use strict';

    const REVISAR_CADA_MS = 20 * 1000;

    function iniciar(raiz) {
        global.ZookiInicio.iniciarCalendario(raiz);
        global.ZookiInicio.iniciarDialogos(raiz);

        raiz.addEventListener('click', (evento) => {
            const iniciarBoton = evento.target.closest('[data-iniciar]');
            if (iniciarBoton && !iniciarBoton.disabled) {
                iniciarAtencion(iniciarBoton);
                return;
            }
            const llegada = evento.target.closest('[data-inicio-llegada]');
            if (llegada && !llegada.disabled) registrarLlegada(raiz, llegada);
        });

        // Si la foto de un paciente reciente no está, se ven sus iniciales. Puede haber
        // fallado antes de que corra este script: entonces ya está completa y sin tamaño.
        raiz.querySelectorAll('[data-inicio-foto]').forEach((foto) => {
            const usarIniciales = () => {
                const avatar = foto.parentElement;
                avatar.classList.remove('inicio-ava--foto');
                avatar.classList.add(avatar.dataset.color);
                avatar.setAttribute('aria-hidden', 'true');
                avatar.textContent = avatar.dataset.iniciales;
            };
            if (foto.complete && foto.naturalWidth === 0) usarIniciales();
            else foto.addEventListener('error', usarIniciales, { once: true });
        });
    }

    async function iniciarAtencion(boton) {
        boton.disabled = true;
        try {
            const respuesta = await fetch('index.php?action=iniciar_cita_ajax', {
                method: 'POST',
                body: new URLSearchParams({ id_cita: boton.dataset.iniciar }),
            });
            const r = await respuesta.json();
            if (!r.success) {
                global.zookiAviso(r.message || 'No se pudo iniciar la atención.');
                boton.disabled = false;
                return;
            }
            global.location.href = r.redirect_url
                || `index.php?action=vet_atencion&id_cita=${encodeURIComponent(boton.dataset.iniciar)}`;
        } catch (error) {
            console.error(error);
            global.zookiAviso('No se pudo iniciar la atención. Revisa tu conexión.');
            boton.disabled = false;
        }
    }

    /**
     * La misma acción que Llegadas (RE-4.19.1): registra la llegada sin
     * iniciar la atención. Dentro de la tolerancia la tarjeta ofrece iniciar;
     * pasada la tolerancia se decide en Llegadas (RN-426).
     */
    async function registrarLlegada(raiz, boton) {
        boton.disabled = true;
        const cuerpo = new FormData();
        cuerpo.set('id_cita', boton.dataset.inicioLlegada);
        cuerpo.set('id_clinica', raiz.dataset.clinica);
        cuerpo.set('clave_operacion', global.zookiCrearComandoAgenda().clave(cuerpo));
        try {
            const r = await (await fetch('index.php?action=registrar_llegada_ajax', { method: 'POST', body: cuerpo })).json();
            if (!r.success) {
                global.zookiAviso(r.message || 'No se pudo registrar la llegada.');
                boton.disabled = false;
                return;
            }
            const opciones = r.resultado && r.resultado.opciones;
            if (opciones && !opciones.dentro_tolerancia) {
                global.location.href = 'index.php?action=vet_agenda#llegadas';
                return;
            }
            global.zookiToast?.('Llegada registrada.');
            global.location.reload();
        } catch (error) {
            console.error(error);
            global.zookiAviso('No se pudo registrar la llegada. Revisa tu conexión.');
            boton.disabled = false;
        }
    }

    // RN-407: el servidor pinta el botón deshabilitado con la hora desde la que
    // se puede iniciar; al llegar esa hora se habilita sin recargar la página.
    function habilitarBotonesListos(raiz) {
        const ahora = Date.now();
        raiz.querySelectorAll('[data-iniciar][data-desde]').forEach((boton) => {
            if (!boton.disabled) return;
            if (ahora < Date.parse(boton.dataset.desde)) return;
            delete boton.dataset.desde;
            boton.removeAttribute('title');
            boton.textContent = 'Iniciar atención';
            boton.disabled = false;
        });
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { iniciar, habilitarBotonesListos };
        return;
    }

    const raiz = global.document.querySelector('[data-inicio]');
    if (!raiz) return;
    iniciar(raiz);
    habilitarBotonesListos(raiz);
    global.setInterval(() => habilitarBotonesListos(raiz), REVISAR_CADA_MS);
    global.ZookiInicio.recargarAlVolver(global.document);
})(globalThis);
