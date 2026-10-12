/**
 * HU-0.3: mutaciones explícitas; los avisos de IP nunca salen al cargar la pantalla.
 *
 * M0.2: pestañas Clínicas / Soporte / Seguridad (se recuerdan como la vista de
 * tarjetas o tabla), filtros que actúan al escribir y solo se ven en Clínicas,
 * y modal «Gestionar» por pestañas internas.
 * Tras una acción el panel se vuelve a pedir y conserva pestaña, vista y filtros.
 */
(function () {
    'use strict';
    const CLAVE_VISTA = 'zooki-plataforma-vista';
    const CLAVE_PESTANA = 'zooki-plataforma-pestana';
    const PESTANAS = ['clinicas', 'soporte', 'seguridad'];

    function guardar(clave, valor) {
        try {
            localStorage.setItem(clave, valor);
        } catch (error) {
            // La preferencia es opcional si el navegador no permite almacenamiento.
        }
    }

    function leer(clave) {
        try {
            return localStorage.getItem(clave);
        } catch (error) {
            return null;
        }
    }

    function vista(tipo) {
        document.querySelectorAll('[data-lista-vista]').forEach(elemento => {
            elemento.hidden = elemento.dataset.listaVista !== tipo;
        });
        document.querySelectorAll('[data-vista]').forEach(boton => {
            boton.setAttribute('aria-pressed', String(boton.dataset.vista === tipo));
            boton.classList?.toggle('active', boton.dataset.vista === tipo);
        });
        guardar(CLAVE_VISTA, tipo);
    }

    function restaurarVista() {
        vista(leer(CLAVE_VISTA) === 'tabla' ? 'tabla' : 'tarjetas');
    }

    function pestana(nombre) {
        const elegida = PESTANAS.includes(nombre) ? nombre : 'clinicas';
        document.querySelectorAll('[data-pestana]').forEach(boton => {
            const activa = boton.dataset.pestana === elegida;
            boton.classList?.toggle('active', activa);
            boton.setAttribute('aria-selected', String(activa));
        });
        document.querySelectorAll('[data-panel]').forEach(panel => {
            panel.hidden = panel.dataset.panel !== elegida;
        });
        // Buscador, filtros y vista son de las clínicas (como en Usuarios, por pestaña).
        document.querySelector('[data-filtros-clinicas]')?.classList.toggle('is-active', elegida === 'clinicas');
        guardar(CLAVE_PESTANA, elegida);
    }

    function restaurarPestana() {
        pestana(leer(CLAVE_PESTANA) || 'clinicas');
    }

    function filtros() {
        return {
            buscar: document.querySelector('[data-filtro-buscar]'),
            estado: document.querySelector('[data-filtro-estado]'),
            plan: document.querySelector('[data-filtro-plan]'),
        };
    }

    /** Muestra solo las clínicas que coinciden; tarjetas y tabla a la vez. */
    function filtrar() {
        const campos = filtros();
        const texto = (campos.buscar?.value || '').trim().toLowerCase();
        const estado = campos.estado?.value || '';
        const plan = campos.plan?.value || '';
        let visibles = 0;
        document.querySelectorAll('[data-clinica-fila]').forEach(fila => {
            // data-buscar trae el NIT con y sin guion (PresentacionPlataforma::buscable).
            const coincide = (!texto || fila.dataset.buscar.includes(texto))
                && (!estado || fila.dataset.estado === estado)
                && (!plan || fila.dataset.plan === plan);
            fila.hidden = !coincide;
            if (coincide && fila.closest('[data-lista-vista="tarjetas"]')) visibles += 1;
        });
        const vacio = document.querySelector('[data-sin-resultados]');
        if (vacio) vacio.hidden = visibles > 0;
        return visibles;
    }

    function iniciar() {
        restaurarVista();
        restaurarPestana();
        const formulario = document.querySelector('[data-filtros-clinicas]');
        formulario?.classList.add('plataforma-filtros--vivo');
        filtrar();
    }

    function validar(campo) {
        const limpio = campo.value.trim().replace(/\s+/gu, ' ');
        const maximo = Number(campo.dataset.maxBytes);
        const error = !limpio || new TextEncoder().encode(limpio).length > maximo
            || !/^[\p{L}\p{M}\p{N}\p{P}\p{S} ]+$/u.test(limpio);
        const mensaje = error ? 'Escribe un motivo o nota de hasta 65535 bytes.' : '';
        campo.setCustomValidity(mensaje);
        campo.setAttribute('aria-invalid', String(error));
        campo.parentElement.querySelector('[data-error-campo]').textContent = mensaje;
        return !error;
    }

    function confirmacion(form) {
        if (form.dataset.plataformaAccion === 'plataforma_estado_ajax') {
            if (form.elements.estado.value === 'baja') return 'Dar de baja impide operar a esta clínica y no permite reactivarla desde el panel. Sus registros se conservan.';
        }
        return null;
    }

    async function actualizar() {
        const respuesta = await fetch(window.location.href, { headers: { Accept: 'text/html' } });
        if (!respuesta.ok) throw new Error('Recarga el panel para ver el resultado.');
        const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
        const contenido = pagina.getElementById('plataformaContenido');
        if (!contenido) throw new Error('Recarga el panel para ver el resultado.');
        // Lo que la persona escribió en los filtros sigue después de actualizar.
        const anteriores = filtros();
        const valores = { buscar: anteriores.buscar?.value, estado: anteriores.estado?.value, plan: anteriores.plan?.value };
        document.getElementById('plataformaContenido').replaceWith(contenido);
        const nuevos = filtros();
        Object.keys(valores).forEach(clave => {
            if (nuevos[clave] && valores[clave] !== undefined) nuevos[clave].value = valores[clave];
        });
        iniciar();
    }

    async function enviar(form, boton) {
        const campos = Array.from(form.querySelectorAll('textarea[data-caracteres]'));
        if (!campos.map(validar).every(Boolean) || !form.reportValidity() || form.dataset.enviando === '1') return;
        form.dataset.enviando = '1';
        const salida = form.querySelector('[data-resultado]');
        try {
            const texto = confirmacion(form);
            if (texto) {
                const respuesta = await Swal.fire({ target: form.closest('.users-modal') || document.body, title: 'Confirmar acción', text: texto, icon: 'warning', showCancelButton: true, confirmButtonText: 'Confirmar', cancelButtonText: 'Cancelar' });
                if (!respuesta.isConfirmed) return;
            }
            boton.disabled = true;
            const respuesta = await fetch('index.php?action=' + form.dataset.plataformaAccion, { method: 'POST', body: new FormData(form) });
            const datos = await respuesta.json();
            salida.textContent = datos.message;
            if (datos.success) {
                // Se actualiza antes del toast; ninguna acción depende de cerrarlo.
                await actualizar();
                window.zookiToast(datos.message);
            }
        } catch (error) {
            salida.textContent = error.message || 'No se pudo completar la operación.';
        } finally {
            boton.disabled = false;
            delete form.dataset.enviando;
        }
    }

    /** Pestañas internas del modal: un formulario a la vista. */
    function pestanaModal(dialogo, nombre) {
        dialogo.querySelectorAll('[data-modal-pestana]').forEach(boton => {
            const activa = boton.dataset.modalPestana === nombre;
            boton.classList?.toggle('active', activa);
            boton.setAttribute('aria-selected', String(activa));
        });
        dialogo.querySelectorAll('[data-modal-panel]').forEach(panel => {
            panel.hidden = panel.dataset.modalPanel !== nombre;
        });
    }

    function abrirGestion(boton) {
        const dialogo = document.getElementById('gestionarClinica');
        dialogo.querySelector('[data-clinica-titulo]').textContent = boton.dataset.nombre;
        // La misma etiqueta de estado de la tarjeta (tag-mini con su icono).
        const insignia = dialogo.querySelector('[data-clinica-estado]');
        if (insignia) {
            insignia.className = 'tag-mini plataforma-estado ' + (boton.dataset.estadoClase || '');
            insignia.textContent = ' ' + (boton.dataset.estadoTexto || '');
            const icono = document.createElement('i');
            icono.className = 'bi ' + (boton.dataset.estadoIcono || '');
            icono.setAttribute('aria-hidden', 'true');
            insignia.prepend(icono);
        }
        const nit = dialogo.querySelector('[data-clinica-nit]');
        if (nit) nit.textContent = boton.dataset.nit ? 'NIT ' + boton.dataset.nit : '';
        const administrador = dialogo.querySelector('[data-clinica-admin]');
        if (administrador) administrador.textContent = boton.dataset.administrador || '';
        dialogo.querySelectorAll('form').forEach(form => {
            form.reset();
            form.elements.id_clinica.value = boton.dataset.gestionarClinica;
            form.querySelector('[data-resultado]').textContent = '';
            form.querySelector('[data-error-campo]').textContent = '';
            form.elements.motivo.setCustomValidity('');
        });
        const plan = dialogo.querySelector('[data-plataforma-accion="plataforma_suscripcion_ajax"]');
        plan.elements.id_plan.value = boton.dataset.plan;
        plan.elements.estado.value = boton.dataset.suscripcion || 'activa';
        const sinPlan = ['baja', 'pendiente_verificacion'].includes(boton.dataset.estado);
        plan.querySelector('button').disabled = sinPlan;
        const avisoPlan = plan.querySelector('[data-aviso-plan]');
        if (avisoPlan) avisoPlan.hidden = !sinPlan;
        const estados = dialogo.querySelector('[data-plataforma-accion="plataforma_estado_ajax"]');
        Array.from(estados.elements.estado.options).forEach(opcion => {
            opcion.disabled = opcion.value === boton.dataset.estado || (opcion.value === 'activa' && (boton.dataset.estado !== 'suspendida' || !boton.dataset.plan));
        });
        estados.elements.estado.value = Array.from(estados.elements.estado.options).find(opcion => !opcion.disabled)?.value || '';
        estados.querySelector('button').disabled = boton.dataset.estado === 'baja';
        // Una clínica sin plan (pendiente o dada de baja) abre en Estado, lo único que se puede cambiar.
        pestanaModal(dialogo, sinPlan ? 'estado' : 'plan');
        dialogo.classList.add('is-open');
        dialogo.setAttribute('aria-hidden', 'false');
        dialogo.querySelector('select').focus();
    }

    function cerrarDialogo(modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('DOMContentLoaded', iniciar);
    document.addEventListener('input', evento => {
        if (evento.target.matches('textarea[data-caracteres]')) validar(evento.target);
        if (evento.target.matches('[data-filtro-buscar]')) filtrar();
    });
    document.addEventListener('change', evento => {
        if (evento.target.matches('[data-filtro-estado], [data-filtro-plan]')) filtrar();
    });
    document.addEventListener('keydown', evento => {
        if (evento.key !== 'Escape') return;
        const abierto = document.querySelector('.users-modal.is-open');
        if (abierto) cerrarDialogo(abierto);
    });
    document.addEventListener('click', evento => {
        const boton = evento.target.closest('button');
        if (!boton) return;
        if (boton.dataset.vista) vista(boton.dataset.vista);
        if (boton.dataset.pestana) pestana(boton.dataset.pestana);
        if (boton.dataset.modalPestana) pestanaModal(boton.closest('.users-modal'), boton.dataset.modalPestana);
        if (boton.hasAttribute('data-cerrar-dialogo')) cerrarDialogo(boton.closest('.users-modal'));
        if (boton.dataset.gestionarClinica) abrirGestion(boton);
    });
    document.addEventListener('submit', evento => {
        const form = evento.target;
        // Con JavaScript los filtros actúan al escribir; el envío no recarga la página.
        if (form.matches?.('[data-filtros-clinicas]')) {
            evento.preventDefault();
            filtrar();
            return;
        }
        if (!form.dataset.plataformaAccion) return;
        evento.preventDefault();
        enviar(form, evento.submitter || form.querySelector('button[type="submit"]'));
    });
    window.ZookiPlataforma = { validar, confirmacion, vista, pestana, filtrar, pestanaModal, enviar };
}());
