/** S4: metadatos y recuperación de pagos existentes; nunca crea cargos. */
(function () {
    'use strict';
    const solicitudes = new WeakMap();

    function validarId(campo) {
        const valido = new RegExp('^(?:' + campo.pattern + ')$').test(campo.value);
        const mensaje = valido ? '' : 'Revisa el ID de transacción Wompi.';
        campo.setCustomValidity(mensaje);
        campo.setAttribute('aria-invalid', String(!valido));
        campo.parentElement.querySelector('[data-error-campo]').textContent = mensaje;
        return valido;
    }

    async function cargar(destino, clinica, pagina = 1) {
        const solicitud = {};
        solicitudes.set(destino, solicitud);
        destino.dataset.clinica = String(clinica);
        destino.dataset.pagina = String(pagina);
        destino.textContent = 'Consultando historial…';
        try {
            const respuesta = await fetch('index.php?action=plataforma_pagos_ajax&id_clinica=' + encodeURIComponent(clinica) + '&pagina=' + encodeURIComponent(pagina), { headers: { Accept: 'text/html' } });
            const html = await respuesta.text();
            if (solicitudes.get(destino) !== solicitud) return;
            if (!respuesta.ok) throw new Error(html || 'No se pudo consultar el historial.');
            // HTML del parcial escapado por PHP; no incluye scripts del proveedor.
            destino.innerHTML = html;
        } catch (error) {
            if (solicitudes.get(destino) === solicitud) destino.textContent = error.message || 'No se pudo consultar el historial.';
        }
    }

    async function revisar(form, conciliar = false) {
        if (form.dataset.enviando === '1') return;
        const nota = form.querySelector('textarea');
        const id = conciliar ? form.querySelector('input[name="id_transaccion"]') : null;
        if (!window.ZookiPlataforma.validar(nota) || (id && !validarId(id)) || !form.reportValidity()) return;
        const boton = form.querySelector('button[type="submit"]');
        const salida = form.querySelector('[data-resultado]');
        const destino = form.closest('[data-finanzas-destino]');
        form.dataset.enviando = '1';
        boton.disabled = true;
        try {
            const accion = conciliar ? 'plataforma_conciliar_ajax' : 'plataforma_incidencia_ajax';
            const respuesta = await fetch('index.php?action=' + accion, { method: 'POST', body: new FormData(form) });
            const datos = await respuesta.json();
            if (!respuesta.ok || !datos.success) throw new Error(datos.message || 'No se pudo guardar la revisión.');
            await cargar(destino, destino.dataset.clinica, Number(destino.dataset.pagina || 1));
            window.zookiToast(datos.message);
        } catch (error) {
            salida.textContent = error.message || 'No se pudo guardar la revisión.';
        } finally {
            boton.disabled = false;
            delete form.dataset.enviando;
        }
    }

    document.addEventListener('click', evento => {
        const boton = evento.target.closest('[data-modal-pestana="pagos"], [data-finanzas-pagina], [data-finanzas-global]');
        if (!boton) return;
        if (boton.matches('[data-finanzas-global]')) {
            cargar(document.querySelector('#incidenciasSinClinica [data-finanzas-destino]'), 0);
        } else if (boton.matches('[data-finanzas-pagina]')) {
            const destino = boton.closest('[data-finanzas-destino]');
            cargar(destino, destino.dataset.clinica, Number(boton.dataset.finanzasPagina));
        } else {
            const dialogo = document.getElementById('gestionarClinica');
            const id = dialogo.querySelector('input[name="id_clinica"]').value;
            cargar(dialogo.querySelector('[data-finanzas-destino]'), id);
        }
    });
    document.addEventListener('submit', evento => {
        if (!evento.target.matches('[data-finanzas-revision], [data-finanzas-conciliar]')) return;
        evento.preventDefault();
        revisar(evento.target, evento.target.matches('[data-finanzas-conciliar]'));
    });
    document.addEventListener('input', evento => {
        if (evento.target.matches('[data-finanzas-revision] textarea, [data-finanzas-conciliar] textarea')) window.ZookiPlataforma.validar(evento.target);
        if (evento.target.matches('[data-finanzas-conciliar] input[name="id_transaccion"]')) validarId(evento.target);
    });
    window.ZookiPagosPlataforma = { cargar, revisar };
}());
