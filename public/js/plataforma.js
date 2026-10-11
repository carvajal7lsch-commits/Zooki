/** HU-0.3: mutaciones explícitas; los avisos de IP nunca salen al cargar la pantalla. */
(function () {
    'use strict';
    const CLAVE_VISTA = 'zooki-plataforma-vista';

    function vista(tipo) {
        document.querySelectorAll('[data-lista-vista]').forEach(elemento => {
            elemento.hidden = elemento.dataset.listaVista !== tipo;
        });
        document.querySelectorAll('[data-vista]').forEach(boton => {
            boton.setAttribute('aria-pressed', String(boton.dataset.vista === tipo));
        });
        try {
            localStorage.setItem(CLAVE_VISTA, tipo);
        } catch (error) {
            // La preferencia es opcional si el navegador no permite almacenamiento.
        }
    }

    function restaurarVista() {
        let elegida = 'tarjetas';
        try {
            elegida = localStorage.getItem(CLAVE_VISTA) === 'tabla' ? 'tabla' : 'tarjetas';
        } catch (error) {
            // Tarjetas por defecto, también en navegación con almacenamiento restringido.
        }
        vista(elegida);
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
        if (form.dataset.confirmacion) return form.dataset.confirmacion;
        if (form.dataset.plataformaAccion === 'plataforma_estado_ajax') {
            if (form.elements.estado.value === 'baja') return 'Dar de baja impide operar a esta clínica y no permite reactivarla desde el panel. Sus registros se conservan.';
            if (form.elements.estado.value === 'suspendida') return 'Suspender impide operar a esta clínica y retira el contexto de su personal en la siguiente petición.';
        }
        if (form.dataset.plataformaAccion === 'plataforma_suscripcion_ajax' && form.elements.estado.value === 'cancelada') return '¿Cancelar la suscripción de esta clínica?';
        // Cerrar casos no tiene reapertura en E1; el correo enviado tampoco se puede retirar.
        if (form.dataset.plataformaAccion === 'plataforma_caso_ajax') return '¿Cerrar este caso con la nota indicada?';
        if (form.dataset.plataformaAccion === 'plataforma_aviso_ajax') return '¿Enviar este texto al administrador elegido?';
        return null;
    }

    async function actualizar() {
        const respuesta = await fetch(window.location.href, { headers: { Accept: 'text/html' } });
        if (!respuesta.ok) throw new Error('Recarga el panel para ver el resultado.');
        const pagina = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
        const contenido = pagina.getElementById('plataformaContenido');
        if (!contenido) throw new Error('Recarga el panel para ver el resultado.');
        document.getElementById('plataformaContenido').replaceWith(contenido);
        restaurarVista();
    }

    async function enviar(form, boton) {
        const campos = Array.from(form.querySelectorAll('textarea[data-caracteres]'));
        if (!campos.map(validar).every(Boolean) || !form.reportValidity() || form.dataset.enviando === '1') return;
        form.dataset.enviando = '1';
        const salida = form.querySelector('[data-resultado]');
        try {
            const texto = confirmacion(form);
            if (texto) {
                const respuesta = await Swal.fire({ title: 'Confirmar acción', text: texto, icon: 'warning', showCancelButton: true, confirmButtonText: 'Confirmar', cancelButtonText: 'Cancelar' });
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

    document.addEventListener('DOMContentLoaded', restaurarVista);
    document.addEventListener('input', evento => {
        if (evento.target.matches('textarea[data-caracteres]')) validar(evento.target);
    });
    document.addEventListener('click', evento => {
        const boton = evento.target.closest('button');
        if (!boton) return;
        if (boton.dataset.vista) vista(boton.dataset.vista);
        if (boton.hasAttribute('data-cerrar-dialogo')) boton.closest('dialog').close();
        if (boton.dataset.gestionarClinica) {
            const dialogo = document.getElementById('gestionarClinica');
            dialogo.querySelector('[data-clinica-titulo]').textContent = boton.dataset.nombre;
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
            plan.querySelector('button').disabled = ['baja', 'pendiente_verificacion'].includes(boton.dataset.estado);
            const estados = dialogo.querySelector('[data-plataforma-accion="plataforma_estado_ajax"]');
            Array.from(estados.elements.estado.options).forEach(opcion => {
                opcion.disabled = opcion.value === boton.dataset.estado || (opcion.value === 'activa' && (boton.dataset.estado !== 'suspendida' || !boton.dataset.plan));
            });
            estados.elements.estado.value = Array.from(estados.elements.estado.options).find(opcion => !opcion.disabled)?.value || '';
            estados.querySelector('button').disabled = boton.dataset.estado === 'baja';
            dialogo.showModal();
        }
    });
    document.addEventListener('submit', evento => {
        const form = evento.target;
        if (!form.dataset.plataformaAccion) return;
        evento.preventDefault();
        enviar(form, evento.submitter || form.querySelector('button[type="submit"]'));
    });
    window.ZookiPlataforma = { validar, confirmacion, vista, enviar };
}());
