/* RE-5.7.1–4: la sesión del servidor decide qué citas puede consultar el propietario. */
(() => {
    'use strict';
    const avisos = document.querySelector('[data-portal-avisos]');
    if (!avisos) return;
    const lista = avisos.querySelector('[data-portal-avisos-lista]');
    const contador = avisos.querySelector('[data-portal-avisos-conteo]');
    const leer = avisos.querySelector('[data-portal-avisos-leer]');
    let cargando = false;
    async function cargar() {
        if (cargando || document.hidden) return;
        cargando = true;
        try {
            const respuesta = await fetch('index.php?action=get_notificaciones_ajax', { sinLoader: true });
            const datos = await respuesta.json();
            if (!respuesta.ok || !datos.success) throw new Error('No se pudieron cargar las notificaciones.');
            contador.textContent = String(datos.no_leidas);
            contador.hidden = !datos.no_leidas;
            leer.hidden = !datos.no_leidas;
            avisos.querySelector('summary').setAttribute('aria-label', `Notificaciones: ${datos.no_leidas} sin leer`);
            lista.replaceChildren();
            for (const aviso of datos.notificaciones) {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'portal-avisos__item' + (Number(aviso.leida) ? '' : ' portal-avisos__item--nuevo');
                const titulo = document.createElement('strong');
                titulo.textContent = aviso.titulo;
                const mensaje = document.createElement('span');
                mensaje.textContent = aviso.mensaje;
                boton.append(titulo, mensaje);
                boton.addEventListener('click', async () => {
                    boton.disabled = true;
                    try {
                        await marcar('marcar_notificacion_leida_ajax', aviso.id);
                        avisos.open = false;
                        switchTab('citas');
                        await mostrarDetalleCita(Number(aviso.id_cita));
                        cargar();
                    } catch (error) {
                        zookiToast(error.message, 'error');
                    } finally {
                        boton.disabled = false;
                    }
                });
                lista.append(boton);
            }
            if (!datos.notificaciones.length) lista.textContent = 'No tienes notificaciones.';
        } catch (error) {
            lista.textContent = error.message;
        } finally {
            cargando = false;
        }
    }
    async function marcar(accion, id = '') {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const respuesta = await fetch('index.php?action=' + accion, { method: 'POST', body: new URLSearchParams({ id, csrf_token: token }), headers: { 'X-CSRF-Token': token }, sinLoader: true });
        if (!respuesta.ok || !(await respuesta.json()).success) throw new Error('No se pudo marcar la notificación.');
    }
    leer.addEventListener('click', async () => {
        try {
            await marcar('marcar_todas_notificaciones_leidas_ajax');
            cargar();
        } catch (error) {
            zookiToast(error.message, 'error');
        }
    });
    avisos.addEventListener('toggle', () => { if (avisos.open) cargar(); });
    document.addEventListener('click', evento => { if (!avisos.contains(evento.target)) avisos.open = false; });
    avisos.addEventListener('keydown', evento => {
        if (evento.key !== 'Escape') return;
        avisos.open = false;
        avisos.querySelector('summary').focus();
    });
    const temporizador = setInterval(cargar, 10000);
    window.addEventListener('pagehide', () => clearInterval(temporizador), { once: true });
    cargar();
    // El enlace del correo/campana abre la cita mediante la lectura autorizada existente.
    const cita = new URLSearchParams(location.search).get('cita');
    if (/^[1-9]\d*$/.test(cita || '')) {
        switchTab('citas');
        mostrarDetalleCita(Number(cita));
    }
})();
