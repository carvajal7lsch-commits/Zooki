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
        document.querySelectorAll('[data-solo-clinicas]').forEach(nodo => { nodo.hidden = elegida !== 'clinicas'; });
        guardar(CLAVE_PESTANA, elegida);
    }

    function restaurarPestana() {
        const pedida = window.location.hash?.slice(1);
        const desdeCorreo = ['soporte', 'seguridad'].includes(pedida);
        pestana(desdeCorreo ? pedida : (leer(CLAVE_PESTANA) || 'clinicas'));
        // La campana abre la sección pedida una vez; después se conserva la pestaña elegida.
        if (desdeCorreo) window.history?.replaceState(null, '', window.location.href.replace(/#(?:soporte|seguridad)$/, ''));
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
        const resumen = document.querySelector('[data-plataforma-resumen]');
        if (resumen) {
            const filas = [...document.querySelectorAll('[data-lista-vista="tarjetas"] [data-clinica-fila]')].filter(fila => !fila.hidden);
            const activas = filas.filter(fila => fila.dataset.estado === 'activa').length;
            const suspendidas = filas.filter(fila => fila.dataset.estado === 'suspendida').length;
            resumen.textContent = visibles + (visibles === 1 ? ' clínica' : ' clínicas') + ' · ' + activas + ' activas · ' + suspendidas + ' suspendidas';
        }
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

    function validarFechaConcesion(campo) {
        const valor = campo.value;
        const fecha = new Date(valor + 'T00:00:00Z');
        const valida = campo.disabled || (!campo.required && !valor) || (/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(valor) && valor >= '0001-01-01' && (!campo.max || valor <= campo.max) && !Number.isNaN(fecha.getTime()) && fecha.toISOString().slice(0, 10) === valor);
        const mensaje = valida ? '' : 'Indica el último día incluido de la concesión.';
        campo.setCustomValidity(mensaje);
        campo.setAttribute('aria-invalid', String(!valida));
        const salida = campo.parentElement.querySelector('[data-error-campo]');
        if (salida) salida.textContent = mensaje;
        return valida;
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
        const fechaFin = form.querySelector('[data-suscripcion-fin]');
        if (fechaFin && !validarFechaConcesion(fechaFin)) return;
        const campos = Array.from(form.querySelectorAll('textarea[data-caracteres]'));
        if (!campos.map(validar).every(Boolean) || !form.reportValidity() || form.dataset.enviando === '1') return;
        form.dataset.enviando = '1';
        const salida = form.querySelector('[data-resultado]');
        try {
            const texto = confirmacion(form);
            if (texto) {
                if (!(await zookiConfirmarCorto(texto, 'Dar de baja'))) return;
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

    /**
     * M0.2.1: qué se ofrece en la pestaña Estado según el estado actual. Es
     * el reflejo de Plataforma::cambiarEstado, que sigue siendo quien decide:
     * activa o pendiente → suspender; suspendida → reactivar si el registro
     * se verificó (tiene plan); dar de baja aparte, salvo si ya está de baja.
     */
    function accionesEstado(estado, tienePlan) {
        if (estado === 'baja') {
            return { acciones: [], baja: false, nota: 'La clínica está dada de baja: no se puede reactivar desde este panel.' };
        }
        if (estado === 'suspendida') {
            return tienePlan
                ? { acciones: ['activa'], baja: true, nota: null }
                : { acciones: [], baja: true, nota: 'No se puede reactivar: la clínica no verificó su registro.' };
        }
        const nota = estado === 'pendiente_verificacion' ? 'La clínica está pendiente: se activa cuando su administrador confirma el correo.' : null;
        return { acciones: ['suspendida'], baja: true, nota };
    }

    const TEXTO_ACCION = { suspendida: 'Suspender clínica', activa: 'Reactivar clínica', baja: 'Dar de baja' };

    function cabecera(dialogo, boton) {
        dialogo.querySelector('[data-clinica-titulo]').textContent = boton.dataset.nombre;
        const avatar = dialogo.querySelector('[data-clinica-avatar]');
        if (avatar) avatar.textContent = (boton.dataset.nombre || '').split(/\s+/).slice(0, 2).map(p => p.slice(0, 1)).join('').toUpperCase();
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
    }

    /**
     * Suscripción según el plan y el interruptor: el Gratuito no tiene pago
     * (se envía «activa»); en un plan de pago, apagada pide «Suspendida» o
     * «Cancelada». El valor que llega al servidor va en el campo oculto.
     */
    function actualizarSuscripcion(form) {
        const plan = form.querySelector('input[name="id_plan"]:checked');
        const gratuito = !plan || plan.dataset.planGratuito === '1';
        const bloque = form.querySelector('[data-suscripcion]');
        if (bloque) bloque.hidden = gratuito;
        const avisoGratuito = form.querySelector('[data-suscripcion-gratuito]');
        if (avisoGratuito) avisoGratuito.hidden = !plan || !gratuito;
        const interruptor = form.querySelector('[data-suscripcion-activa]');
        const activa = !interruptor || interruptor.checked;
        const inactivas = form.querySelector('[data-suscripcion-inactiva]');
        if (inactivas) inactivas.hidden = activa;
        const inactiva = form.querySelector('input[name="suscripcion_inactiva"]:checked');
        const valor = form.querySelector('[data-suscripcion-valor]');
        if (valor) valor.value = gratuito || activa ? 'activa' : (inactiva ? inactiva.value : 'suspendida');
        const fecha = form.querySelector('[data-suscripcion-fin]');
        if (fecha) {
            fecha.required = !gratuito;
            fecha.disabled = gratuito;
            fecha.parentElement.hidden = gratuito;
        }
        return valor ? valor.value : 'activa';
    }

    /** Plan actual siempre marcado; si no se puede cambiar, nota arriba y todo deshabilitado. */
    function prepararPlan(dialogo, boton) {
        const plan = dialogo.querySelector('[data-plataforma-accion="plataforma_suscripcion_ajax"]');
        const sinPlan = ['baja', 'pendiente_verificacion'].includes(boton.dataset.estado);
        const suscripcion = boton.dataset.suscripcion || 'activa';
        plan.querySelectorAll('input[name="id_plan"]').forEach(opcion => {
            opcion.checked = opcion.value === (boton.dataset.plan || '');
        });
        const interruptor = plan.querySelector('[data-suscripcion-activa]');
        if (interruptor) interruptor.checked = suscripcion === 'activa';
        plan.querySelectorAll('input[name="suscripcion_inactiva"]').forEach(opcion => {
            opcion.checked = opcion.value === (suscripcion === 'cancelada' ? 'cancelada' : 'suspendida');
        });
        plan.querySelectorAll('input, textarea, button').forEach(campo => {
            if (campo.type !== 'hidden') campo.disabled = sinPlan;
        });
        const guardar = dialogo.querySelector('[data-guardar-plan]');
        if (guardar) guardar.disabled = sinPlan;
        const aviso = dialogo.querySelector('[data-aviso-plan]');
        if (aviso) aviso.hidden = !sinPlan;
        const fecha = plan.querySelector('[data-suscripcion-fin]');
        if (fecha) fecha.value = boton.dataset.fechaFin || '';
        plan.querySelectorAll('input[name="ciclo"]').forEach(campo => {
            campo.checked = campo.value === (boton.dataset.ciclo || 'mensual');
        });
        actualizarSuscripcion(plan);
        return sinPlan;
    }

    /** Tarjetas de acción: solo las que el estado actual admite, ninguna elegida al abrir. */
    function prepararEstado(dialogo, boton) {
        const ofrecido = accionesEstado(boton.dataset.estado, Boolean(boton.dataset.plan));
        let disponibles = 0;
        dialogo.querySelectorAll('[data-opcion-estado]').forEach(tarjeta => {
            const valor = tarjeta.dataset.opcionEstado;
            const disponible = valor === 'baja' ? ofrecido.baja : ofrecido.acciones.includes(valor);
            tarjeta.hidden = !disponible;
            const opcion = tarjeta.querySelector('input');
            if (opcion) {
                opcion.disabled = !disponible;
                opcion.checked = false;
            }
            if (disponible) disponibles += 1;
        });
        const formulario = dialogo.querySelector('[data-estado-acciones]');
        if (formulario) formulario.hidden = disponibles === 0;
        const pie = dialogo.querySelector('[data-pie-estado]');
        if (pie) pie.hidden = disponibles === 0;
        const detalle = dialogo.querySelector('[data-estado-detalle]');
        if (detalle) detalle.hidden = true;
        const aplicar = dialogo.querySelector('[data-aplicar-estado]');
        if (aplicar) {
            aplicar.disabled = true;
            aplicar.textContent = 'Elige una acción';
            aplicar.className = 'btn-modal-primary';
        }
        const nota = dialogo.querySelector('[data-estado-nota]');
        if (nota) {
            nota.hidden = ofrecido.nota === null;
            const texto = nota.querySelector('[data-estado-nota-texto]');
            if (texto) texto.textContent = ofrecido.nota || '';
        }
        return ofrecido;
    }

    /** Al elegir una tarjeta aparecen el motivo y el botón con su nombre; dar de baja, en rojo. */
    function elegirAccion(dialogo, valor) {
        const detalle = dialogo.querySelector('[data-estado-detalle]');
        if (detalle) detalle.hidden = false;
        const aplicar = dialogo.querySelector('[data-aplicar-estado]');
        if (aplicar) {
            aplicar.disabled = false;
            aplicar.textContent = TEXTO_ACCION[valor] || 'Aplicar';
            aplicar.className = valor === 'baja' ? 'btn-modal-peligro' : 'btn-modal-primary';
        }
    }

    /** Un motivo frecuente llena el campo; se puede editar después. */
    function sugerirMotivo(boton) {
        const form = boton.closest('form');
        const campo = form ? form.querySelector('textarea[name="motivo"]') : null;
        if (!campo || campo.disabled) return;
        campo.value = boton.dataset.motivoSugerido;
        validar(campo);
        campo.focus();
    }

    function abrirGestion(boton) {
        const dialogo = document.getElementById('gestionarClinica');
        cabecera(dialogo, boton);
        dialogo.querySelectorAll('form').forEach(form => {
            form.reset();
            form.elements.id_clinica.value = boton.dataset.gestionarClinica;
            form.querySelector('[data-resultado]').textContent = '';
            form.querySelector('[data-error-campo]').textContent = '';
            form.elements.motivo.setCustomValidity('');
        });
        const sinPlan = prepararPlan(dialogo, boton);
        prepararEstado(dialogo, boton);
        // Una clínica sin plan (pendiente o dada de baja) abre en Estado, lo único que se puede cambiar.
        pestanaModal(dialogo, sinPlan ? 'estado' : 'plan');
        dialogo.classList.add('is-open');
        dialogo.setAttribute('aria-hidden', 'false');
        dialogo.querySelector('.close-modal')?.focus();
    }

    /** Modales de Soporte y Seguridad (cerrar un caso, avisar a una clínica): se abren limpios. */
    function abrirDialogo(id) {
        const dialogo = document.getElementById(id);
        if (!dialogo) return;
        dialogo.querySelectorAll('form').forEach(form => {
            form.reset();
            form.querySelectorAll('[data-resultado], [data-error-campo]').forEach(salida => { salida.textContent = ''; });
        });
        dialogo.classList.add('is-open');
        dialogo.setAttribute('aria-hidden', 'false');
        dialogo.querySelector('.close-modal')?.focus();
    }

    function cerrarDialogo(modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('DOMContentLoaded', iniciar);
    window.addEventListener?.('hashchange', restaurarPestana);
    document.addEventListener('input', evento => {
        if (evento.target.matches('textarea[data-caracteres]')) validar(evento.target);
        if (evento.target.matches('[data-suscripcion-fin]')) validarFechaConcesion(evento.target);
        if (evento.target.matches('[data-filtro-buscar]')) filtrar();
    });
    document.addEventListener('change', evento => {
        if (evento.target.matches('[data-filtro-estado], [data-filtro-plan]')) filtrar();
        const form = evento.target.closest?.('form');
        if (form && evento.target.matches('input[name="id_plan"], [data-suscripcion-activa], input[name="suscripcion_inactiva"]')) actualizarSuscripcion(form);
        if (form && form.matches('[data-estado-acciones]') && evento.target.name === 'estado') elegirAccion(document.getElementById('gestionarClinica'), evento.target.value);
    });
    document.addEventListener('keydown', evento => {
        const fila = evento.target.closest?.('[data-clinica-fila]');
        if (fila && evento.target === fila && ['Enter', ' '].includes(evento.key)) {
            evento.preventDefault();
            abrirGestion(fila.querySelector('[data-gestionar-clinica]'));
            return;
        }
        if (evento.key !== 'Escape') return;
        document.querySelectorAll('.users-modal.is-open').forEach(cerrarDialogo);
    });
    document.addEventListener('click', evento => {
        const boton = evento.target.closest('button');
        if (!boton) {
            const fila = evento.target.closest?.('[data-clinica-fila]');
            if (fila) abrirGestion(fila.querySelector('[data-gestionar-clinica]'));
            return;
        }
        if (boton.dataset.motivoSugerido) sugerirMotivo(boton);
        if (boton.dataset.abrirDialogo) abrirDialogo(boton.dataset.abrirDialogo);
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
    window.ZookiPlataforma = { validarFechaConcesion, validar, confirmacion, vista, pestana, filtrar, pestanaModal, accionesEstado, actualizarSuscripcion, enviar };
}());
