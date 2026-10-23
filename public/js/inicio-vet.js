/**
 * Inicio del veterinario (HU-6.1, views/vet/area.php).
 *
 * La vista llega pintada desde el servidor. Aquí se cambia de día y de mes en
 * el calendario (panel_dia_ajax devuelve los mismos parciales PHP), se
 * registra la llegada del siguiente paciente, se inicia la atención cuando
 * llega su ventana (RN-407) y se abren las próximas dosis. Los datos de la
 * base llegan ya escapados en el HTML del servidor.
 */
(function (global) {
    'use strict';

    const REVISAR_CADA_MS = 20 * 1000;
    const REFRESCAR_TRAS_MS = 5 * 60 * 1000;

    /** Pide un fragmento del Inicio; null si la respuesta no sirve. */
    async function pedirFragmento(parametros) {
        const respuesta = await fetch('index.php?action=panel_dia_ajax&' + new URLSearchParams(parametros));
        const datos = await respuesta.json();
        if (!datos || !datos.success || typeof datos.html !== 'string') {
            throw new Error((datos && datos.message) || 'No se pudo cargar.');
        }
        return datos.html;
    }

    function iniciar(raiz) {
        const calendario = raiz.querySelector('[data-inicio-calendario]');
        const contenidoCalendario = raiz.querySelector('[data-inicio-calendario-contenido]');
        const dia = raiz.querySelector('[data-inicio-dia]');
        const contenidoDia = raiz.querySelector('[data-inicio-dia-contenido]');
        let turnoDia = 0;
        let turnoMes = 0;

        /** RE-6.1.9: «Tu día» del día tocado; la respuesta vieja se descarta. */
        async function mostrarDia(fecha) {
            const turno = ++turnoDia;
            calendario.dataset.elegido = fecha;
            contenidoCalendario.querySelectorAll('[data-inicio-fecha]').forEach((boton) => {
                const elegido = boton.dataset.inicioFecha === fecha;
                boton.classList.toggle('is-elegido', elegido);
                boton.setAttribute('aria-pressed', elegido ? 'true' : 'false');
            });
            dia.setAttribute('aria-busy', 'true');
            try {
                const html = await pedirFragmento({ fecha });
                if (turno !== turnoDia) return;
                contenidoDia.innerHTML = html;
                contenidoDia.scrollTop = 0;
            } catch (error) {
                if (turno !== turnoDia) return;
                global.zookiAviso?.('No se pudo cargar ese día. Intenta nuevamente.');
            } finally {
                if (turno === turnoDia) dia.setAttribute('aria-busy', 'false');
            }
        }

        /** RE-6.1.8: otro mes, con el día elegido todavía marcado. */
        async function mostrarMes(mes) {
            const turno = ++turnoMes;
            try {
                const html = await pedirFragmento({ mes, elegido: calendario.dataset.elegido });
                if (turno !== turnoMes) return;
                contenidoCalendario.innerHTML = html;
            } catch (error) {
                if (turno === turnoMes) global.zookiAviso?.('No se pudo cargar ese mes. Intenta nuevamente.');
            }
        }

        contenidoCalendario.addEventListener('click', (evento) => {
            const dia = evento.target.closest('[data-inicio-fecha]');
            if (dia) {
                mostrarDia(dia.dataset.inicioFecha);
                return;
            }
            const flecha = evento.target.closest('[data-inicio-mes]');
            if (flecha) mostrarMes(flecha.dataset.inicioMes);
        });

        raiz.addEventListener('click', (evento) => {
            const iniciarBoton = evento.target.closest('[data-iniciar]');
            if (iniciarBoton && !iniciarBoton.disabled) {
                iniciarAtencion(iniciarBoton);
                return;
            }
            const llegada = evento.target.closest('[data-inicio-llegada]');
            if (llegada && !llegada.disabled) {
                registrarLlegada(raiz, llegada);
                return;
            }
            const abrir = evento.target.closest('[data-inicio-abrir]');
            if (abrir) {
                const dialogo = global.document.getElementById(abrir.dataset.inicioAbrir);
                if (dialogo && typeof dialogo.showModal === 'function') dialogo.showModal();
                return;
            }
            if (evento.target.closest('[data-inicio-cerrar]')) evento.target.closest('[data-inicio-dialogo]')?.close();
        });

        // Clic en el fondo del diálogo: el objetivo es el propio <dialog>, no su contenido.
        raiz.querySelectorAll('[data-inicio-dialogo]').forEach((dialogo) => {
            dialogo.addEventListener('click', (evento) => {
                if (evento.target === dialogo) dialogo.close();
            });
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

    // La agenda cambia durante el día: si la pestaña estuvo oculta un buen
    // rato, al volver se recarga para no mostrar estados viejos.
    let ocultaDesde = null;
    global.document.addEventListener('visibilitychange', () => {
        if (global.document.hidden) {
            ocultaDesde = Date.now();
        } else if (ocultaDesde && Date.now() - ocultaDesde > REFRESCAR_TRAS_MS) {
            global.location.reload();
        }
    });
})(globalThis);
