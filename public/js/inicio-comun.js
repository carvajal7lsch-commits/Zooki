/**
 * Piezas comunes de los Inicios del veterinario (HU-6.1) y del administrador
 * (HU-6.2).
 *
 * La vista llega pintada desde el servidor. Aquí se cambia de día y de mes en
 * el calendario: la acción AJAX de cada Inicio (data-inicio-accion en la raíz)
 * devuelve los mismos parciales PHP del primer render, y una respuesta vieja
 * nunca pisa a la nueva. También se abren y cierran las ventanas del Inicio y
 * se recarga la página si estuvo oculta un buen rato. Los datos de la base
 * llegan ya escapados en el HTML del servidor.
 */
(function (global) {
    'use strict';

    const REFRESCAR_TRAS_MS = 5 * 60 * 1000;

    /** Pide un fragmento del Inicio a su acción; falla si la respuesta no sirve. */
    async function pedirFragmento(accion, parametros) {
        const respuesta = await fetch('index.php?action=' + encodeURIComponent(accion) + '&' + new URLSearchParams(parametros));
        const datos = await respuesta.json();
        if (!datos || !datos.success || typeof datos.html !== 'string') {
            throw new Error((datos && datos.message) || 'No se pudo cargar.');
        }
        return datos.html;
    }

    /** RE-6.1.8/RE-6.1.9 y RE-6.2.8/RE-6.2.9: el día tocado y el mes elegido. */
    function iniciarCalendario(raiz) {
        const accion = raiz.dataset.inicioAccion;
        const calendario = raiz.querySelector('[data-inicio-calendario]');
        const contenidoCalendario = raiz.querySelector('[data-inicio-calendario-contenido]');
        const dia = raiz.querySelector('[data-inicio-dia]');
        const contenidoDia = raiz.querySelector('[data-inicio-dia-contenido]');
        let turnoDia = 0;
        let turnoMes = 0;

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
                const html = await pedirFragmento(accion, { fecha });
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

        async function mostrarMes(mes) {
            const turno = ++turnoMes;
            try {
                const html = await pedirFragmento(accion, { mes, elegido: calendario.dataset.elegido });
                if (turno !== turnoMes) return;
                contenidoCalendario.innerHTML = html;
            } catch (error) {
                if (turno === turnoMes) global.zookiAviso?.('No se pudo cargar ese mes. Intenta nuevamente.');
            }
        }

        contenidoCalendario.addEventListener('click', (evento) => {
            const boton = evento.target.closest('[data-inicio-fecha]');
            if (boton) {
                mostrarDia(boton.dataset.inicioFecha);
                return;
            }
            const flecha = evento.target.closest('[data-inicio-mes]');
            if (flecha) mostrarMes(flecha.dataset.inicioMes);
        });
    }

    /** Ventanas del Inicio: se abren con data-inicio-abrir y se cierran con su botón o con clic en el fondo. */
    function iniciarDialogos(raiz) {
        raiz.addEventListener('click', (evento) => {
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
    }

    /** La agenda cambia durante el día: si la pestaña estuvo oculta un buen rato, al volver se recarga. */
    function recargarAlVolver(documento) {
        let ocultaDesde = null;
        documento.addEventListener('visibilitychange', () => {
            if (documento.hidden) {
                ocultaDesde = Date.now();
            } else if (ocultaDesde && Date.now() - ocultaDesde > REFRESCAR_TRAS_MS) {
                global.location.reload();
            }
        });
    }

    const api = { iniciarCalendario, iniciarDialogos, recargarAlVolver };
    global.ZookiInicio = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(globalThis);
