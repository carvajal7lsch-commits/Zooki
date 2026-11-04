/*
 * Pantalla de atención de una cita (views/vet/atencion.php).
 *
 * La página desplaza las tarjetas completas y el trabajo se
 * reparte en pestañas (Consulta, Vacuna, Desparasitación, Historial) y, dentro
 * de la consulta, en pasos. "Finalizar atención" guarda la consulta y completa
 * la cita en un solo paso. Nada recarga la página salvo al terminar.
 *
 * Usa de otros archivos: zookiToast/zookiAviso/zookiConfirmar y escapeHtml
 * (avisos.js) y, de la consulta compartida con el modal (M2 C5a y C5b),
 * ZookiConsultaForm, updateFileList y avisarAdjuntosRechazados
 * (consulta-formulario.js), con los síntomas, los diagnósticos sugeridos y
 * las filas de tratamiento con sus alertas de prescripción.
 */
(function () {
    'use strict';

    const app = document.getElementById('atencionApp');
    if (!app) return;

    const idCita = app.dataset.idCita;
    const idMascota = app.dataset.idMascota;
    const formConsulta = document.getElementById('formConsultaAtencion');
    const btnFinalizar = document.getElementById('btnFinalizarAtencion');

    let hayCambiosSinGuardar = false;
    let finalizando = false;
    // M2 C5a: síntomas, diagnósticos sugeridos y avisos junto al campo, los mismos del modal.
    let consulta = null;

    // ── Utilidades ──────────────────────────────────────────────────────
    const fechaCorta = (iso) => {
        if (!iso) return '—';
        const [a, m, d] = String(iso).substring(0, 10).split('-');
        return `${d}/${m}/${a}`;
    };

    async function postJson(url, cuerpo) {
        const res = await fetch(url, { method: 'POST', body: cuerpo });
        return res.json();
    }

    // Primer campo inválido del contenedor, con su mensaje. Se revisan tanto
    // las reglas nativas (required, min, max) como los textos con solo espacios.
    function primerCampoInvalido(contenedor) {
        for (const campo of contenedor.querySelectorAll('input, select, textarea')) {
            if (!campo.willValidate) continue;
            const soloEspacios = campo.required && typeof campo.value === 'string' && campo.value.trim() === '';
            if (soloEspacios || !campo.checkValidity()) {
                const etiqueta = campo.dataset.etiqueta || 'este campo';
                const mensaje = soloEspacios || campo.validity.valueMissing
                    ? `Falta ${etiqueta}.`
                    : `Revisa ${etiqueta}: ${campo.validationMessage}`;
                return { campo, mensaje };
            }
        }
        return null;
    }

    function marcarInvalido(campo) {
        campo.classList.add('is-invalid');
        campo.addEventListener('input', () => campo.classList.remove('is-invalid'), { once: true });
    }

    // RE-2.1.6: las tarjetas de procedimientos conservan sus límites y validan junto al campo.
    function validarCampoProcedimiento(campo) {
        if (!campo.willValidate) return null;
        let mensaje = '';
        if (campo.required && campo.value.trim() === '') mensaje = 'Este campo es obligatorio.';
        else if (campo.maxLength > 0 && [...campo.value].length > campo.maxLength) mensaje = `Admite hasta ${campo.maxLength} caracteres.`;
        else if (!campo.checkValidity()) mensaje = campo.type === 'date' ? 'Revisa la fecha indicada.' : 'Revisa este campo.';
        let aviso = campo.parentElement.querySelector('[data-aviso-procedimiento]');
        if (!aviso && mensaje) {
            aviso = document.createElement('small');
            aviso.dataset.avisoProcedimiento = '';
            aviso.className = 'cuenta-validacion cuenta-validacion--error';
            aviso.id = campo.form.id + '-' + campo.name + '-aviso';
            aviso.setAttribute('aria-live', 'polite');
            campo.setAttribute('aria-describedby', aviso.id);
            campo.insertAdjacentElement('afterend', aviso);
        }
        if (aviso) aviso.textContent = mensaje;
        campo.setAttribute('aria-invalid', String(Boolean(mensaje)));
        campo.classList.toggle('is-invalid', Boolean(mensaje));
        return mensaje ? campo : null;
    }

    function validarProcedimiento(form) {
        const proxima = form.elements.fecha_proxima;
        if (proxima) proxima.min = form.elements.fecha_aplicacion.value || form.elements.fecha_aplicacion.max;
        const errores = [...form.querySelectorAll('input, select, textarea')].map(validarCampoProcedimiento);
        return errores.find(Boolean) || null;
    }

    function iniciarValidacionProcedimiento(form) {
        for (const campo of form.querySelectorAll('input:not([type="hidden"]), select, textarea')) {
            const comprobar = () => {
                validarCampoProcedimiento(campo);
                if (campo.name === 'fecha_aplicacion' && form.elements.fecha_proxima) {
                    form.elements.fecha_proxima.min = campo.value || campo.max;
                    if (form.elements.fecha_proxima.value) validarCampoProcedimiento(form.elements.fecha_proxima);
                }
            };
            campo.addEventListener('input', comprobar);
            campo.addEventListener('change', comprobar);
        }
    }

    // ── Pestañas ────────────────────────────────────────────────────────
    const navPasos = document.getElementById('navPasos');
    app.querySelector('[data-abrir-alertas]')?.addEventListener('click', () => {
        const alertas = app.querySelector('.atencion-franja__alertas');
        const abierta = alertas.classList.toggle('is-abierta');
        app.querySelector('[data-abrir-alertas]').setAttribute('aria-expanded', String(abierta));
    });

    function activarPestana(nombre) {
        app.querySelectorAll('.atencion__tab').forEach((boton) => {
            const activa = boton.dataset.tab === nombre;
            boton.classList.toggle('is-active', activa);
            boton.setAttribute('aria-selected', String(activa));
        });
        app.querySelectorAll('.atencion__vista').forEach((vista) => {
            vista.classList.toggle('is-active', vista.dataset.vista === nombre);
        });
        if (navPasos) navPasos.hidden = nombre !== 'consulta';
        const progreso = app.querySelector('[data-pasos-paciente]');
        if (progreso) progreso.hidden = nombre !== 'consulta';
        const acciones = document.getElementById('accionesAtencion');
        if (acciones && formConsulta) acciones.hidden = nombre !== 'consulta';

        if (nombre === 'historial') cargarHistorialCompacto();
        if (nombre === 'vacuna') cargarCatalogosVacuna();
        if (nombre === 'desparasitacion') cargarCatalogoProductos();
    }

    let historialCargado = false;
    async function cargarHistorialCompacto() {
        const lista = document.getElementById('atencionHistorial');
        if (!lista || historialCargado || typeof filaHistorialCompacto !== 'function') return;
        try {
            const respuesta = await fetch('index.php?action=listar_historial_ajax&id_mascota=' + encodeURIComponent(idMascota));
            const datos = await respuesta.json();
            if (datos.success === false) throw new Error(datos.message || 'No se pudo cargar el detalle del historial.');
            const filas = eventosHistorialCompacto(datos);
            if (filas.length) lista.innerHTML = filas.map(fila => '<li>' + filaHistorialCompacto(fila.registro, fila.tipo) + '</li>').join('');
            historialCargado = true;
        } catch (error) {
            zookiToast('No se pudo cargar el detalle del historial. Vuelve a abrir la pestaña para reintentar.', 'error');
        }
    }

    // ── Pasos de la consulta ────────────────────────────────────────────
    const pasos = formConsulta ? [...formConsulta.querySelectorAll('.atencion__paso')] : [];
    const botonesPaso = [...app.querySelectorAll('.atencion__paso-btn')];
    const btnAnterior = document.getElementById('btnPasoAnterior');
    const btnSiguiente = document.getElementById('btnPasoSiguiente');
    let pasoActual = 0;

    function mostrarPaso(indice) {
        if (consulta?.pasos) consulta.pasos.ir(indice, false);
    }

    // Lleva al paso del campo, lo marca y lo enfoca.
    function irAlCampo(campo) {
        const indice = pasos.indexOf(campo.closest('.atencion__paso'));
        activarPestana('consulta');
        mostrarPaso(indice);
        if (botonesPaso[indice]) botonesPaso[indice].classList.add('tiene-error');
        marcarInvalido(campo);
        campo.focus();
    }

    // Lleva al primer error de la consulta (pestaña y paso). Motivo, diagnóstico
    // y signos dicen su aviso junto al campo (C5a); los demás campos, en un aviso breve.
    function validarConsulta() {
        botonesPaso.forEach((b) => b.classList.remove('tiene-error'));
        const conAviso = consulta ? consulta.validar() : null;
        if (conAviso) {
            irAlCampo(conAviso);
            return false;
        }
        const error = primerCampoInvalido(formConsulta);
        if (!error) return true;

        irAlCampo(error.campo);
        zookiToast(error.mensaje, 'error');
        return false;
    }

    // RE-2.1.7: el componente común conserva la procedencia de cada sugerencia.
    function borrarBorrador() {
        consulta?.pasos?.borrar(true);
    }

    // ── Iniciar atención ────────────────────────────────────────────────
    function marcarEnCurso() {
        app.dataset.estado = 'en_curso';
        const estado = document.getElementById('atencionEstado');
        const punto = document.createElement('i');
        punto.className = 'fas fa-circle';
        punto.setAttribute('aria-hidden', 'true');
        estado.replaceChildren(punto, document.createTextNode(' En curso'));
        estado.className = 'atencion__estado estado--en_curso';
        const aviso = document.getElementById('avisoIniciar');
        if (aviso) aviso.hidden = true;
        app.querySelectorAll('fieldset[data-requiere-inicio]').forEach((f) => { f.disabled = false; });
        document.getElementById('accionesAtencion').hidden = false;
    }

    async function iniciarAtencion(boton) {
        boton.disabled = true;
        try {
            const r = await postJson('index.php?action=iniciar_cita_ajax', new URLSearchParams({ id_cita: idCita }));
            if (!r.success) {
                if (r.campo === 'llegada') {
                    globalThis.location.assign(`index.php?action=vet_agenda&llegada_cita=${encodeURIComponent(idCita)}`);
                    return;
                }
                zookiAviso(r.message || 'No se pudo iniciar la atención.');
                return;
            }
            marcarEnCurso();
            zookiToast('Atención iniciada');
        } catch (e) {
            console.error('iniciarAtencion', e);
            zookiAviso('No se pudo iniciar la atención. Revisa tu conexión.');
        } finally {
            boton.disabled = false;
        }
    }

    // ── Finalizar atención (consulta + completar cita) ──────────────────
    function textoBotonFinalizar(cargando) {
        btnFinalizar.disabled = cargando;
        btnFinalizar.innerHTML = cargando
            ? '<i class="fas fa-spinner fa-spin"></i> Finalizando...'
            : '<i class="fas fa-check-circle"></i> Finalizar atención';
    }

    async function finalizarAtencion() {
        const consultaRegistrada = app.dataset.consultaRegistrada === '1';
        // C5b: las alertas de prescripción deben estar al día antes de validar.
        if (!consultaRegistrada && consulta) await consulta.listo();
        if (!consultaRegistrada && formConsulta && !validarConsulta()) return;
        if (!consultaRegistrada && consulta?.pasos && !consulta.pasos.validarPaso(2)) return;

        finalizando = true;
        let mensajeCierre = 'Atención registrada';
        textoBotonFinalizar(true);

        try {
            if (!consultaRegistrada) {
                // Guardar la consulta completa la cita en la misma transacción
                // (RN-406): o quedan las dos cosas, o ninguna.
                const res = await postJson('index.php?action=registrar_consulta_ajax', new FormData(formConsulta));
                if (!res.success) {
                    finalizando = false;
                    if (avisarAdjuntosRechazados(res, formConsulta)) return;
                    // C5a: el error de un campo va junto a ese campo, en su paso.
                    const campo = consulta ? consulta.mostrarErrorServidor(res) : null;
                    if (campo) {
                        irAlCampo(campo);
                        return;
                    }
                    zookiAviso(res.message || 'No se pudo guardar la consulta.');
                    return;
                }
                app.dataset.consultaRegistrada = '1';
                hayCambiosSinGuardar = false;
                borrarBorrador();
                if (res.control?.creado === false) mensajeCierre += '. Agenda el control desde Nueva cita.';
            } else {
                // La consulta ya existía pero la cita quedó abierta (datos de
                // antes de v1.9.0, cuando eran dos pasos): solo falta cerrarla.
                const r = await postJson('index.php?action=completar_cita_ajax', new URLSearchParams({ id_cita: idCita }));
                if (!r.success) {
                    finalizando = false;
                    zookiAviso(r.message || 'No se pudo completar la cita.');
                    return;
                }
            }

            zookiRedirigirConAviso('index.php?action=vet_agenda', mensajeCierre, 'success');
        } catch (e) {
            console.error('finalizarAtencion', e);
            finalizando = false;
            zookiAviso('No se pudo finalizar la atención. Revisa tu conexión e intenta de nuevo.');
        } finally {
            if (!finalizando) textoBotonFinalizar(false);
        }
    }

    // ── Vacuna y desparasitación (sin recargar) ─────────────────────────
    const plantillaVacuna = (d) => '<li>' + filaHistorialCompacto(d, 'vacuna') + '</li>';
    const plantillaDesparasitacion = (d) => '<li>' + filaHistorialCompacto(d, 'desparasitacion') + '</li>';

    function sumarAlContador(nombre) {
        const contador = app.querySelector(`[data-contador="${nombre}"]`);
        if (contador) contador.textContent = String((parseInt(contador.textContent, 10) || 0) + 1);
    }

    async function registrarProcedimiento(evento, config) {
        evento.preventDefault();
        const form = evento.currentTarget;

        const error = validarProcedimiento(form);
        if (error) {
            error.focus();
            return;
        }

        const boton = form.querySelector('[type="submit"]');
        boton.disabled = true;
        try {
            const datos = new FormData(form);
            const r = await postJson(config.endpoint, datos);
            if (!r.success) {
                zookiAviso(r.message || 'No se pudo registrar.');
                return;
            }

            const lista = document.getElementById(config.lista);
            const vacio = lista.querySelector('.atencion__vacio');
            if (vacio) vacio.remove();
            lista.insertAdjacentHTML('afterbegin', config.plantilla(Object.fromEntries(datos)));
            sumarAlContador(config.contador);
            historialCargado = false;

            const fecha = form.elements.fecha_aplicacion.value;
            form.reset();
            form.elements.fecha_aplicacion.value = fecha;
            zookiToast(config.exito);
        } catch (e) {
            console.error('registrarProcedimiento', e);
            zookiAviso('No se pudo registrar. Revisa tu conexión.');
        } finally {
            boton.disabled = false;
        }
    }

    // ── Catálogos (se piden una sola vez, al abrir la pestaña) ──────────
    const catalogosCargados = new Set();

    async function llenarDatalist(idLista, url, extraerNombres) {
        if (catalogosCargados.has(idLista)) return;
        catalogosCargados.add(idLista);
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (!data.success) return;
            document.getElementById(idLista).innerHTML = extraerNombres(data)
                .map((nombre) => `<option value="${escapeHtml(nombre)}"></option>`)
                .join('');
        } catch (e) {
            catalogosCargados.delete(idLista);
            console.error('llenarDatalist', idLista, e);
        }
    }

    function cargarCatalogosVacuna() {
        llenarDatalist('listaVacunas', `index.php?action=get_vacunas_por_especie_ajax&id_mascota=${encodeURIComponent(idMascota)}`,
            (d) => (d.vacunas || []).map((v) => v.nombre_vacuna));
        llenarDatalist('listaLaboratorios', 'index.php?action=get_laboratorios_ajax',
            (d) => (d.laboratorios || []).map((l) => l.nombre_laboratorio));
    }

    function cargarCatalogoProductos() {
        llenarDatalist('listaProductos', 'index.php?action=get_productos_desparasitacion_ajax',
            (d) => (d.productos || []).map((p) => p.nombre_producto));
    }

    // ── Medicamentos ────────────────────────────────────────────────────
    // C5b: las filas, el fármaco, las alertas y la lista vacía los maneja
    // consulta-tratamientos.js; aquí solo se marca que hay cambios.
    function iniciarTratamientos() {
        const boton = document.getElementById('btnAgregarTratamiento');
        if (boton) boton.addEventListener('click', () => { hayCambiosSinGuardar = true; });
    }

    // ── Arranque ────────────────────────────────────────────────────────
    function iniciar() {
        const foto = document.getElementById('atencionFoto');
        if (foto) foto.addEventListener('error', () => usarFotoPorDefecto(foto), { once: true });

        app.querySelectorAll('.atencion__tab').forEach((boton) => {
            boton.addEventListener('click', () => activarPestana(boton.dataset.tab));
        });

        mostrarPaso(0);

        const btnIniciar = document.getElementById('btnIniciarAtencion');
        if (btnIniciar) btnIniciar.addEventListener('click', () => iniciarAtencion(btnIniciar));
        if (btnFinalizar) btnFinalizar.addEventListener('click', finalizarAtencion);

        if (formConsulta) {
            if (window.ZookiConsultaForm) {
                consulta = window.ZookiConsultaForm.iniciar(formConsulta, {
                    idMascota: () => idMascota,
                    alCambiarSintomas: () => {
                        hayCambiosSinGuardar = true;

                    },
                });
            }
            formConsulta.addEventListener('input', (e) => {
                if (e.target.type === 'file') return;
                hayCambiosSinGuardar = true;

            });
            // Enter en un input no debe enviar nada: se finaliza con el botón.
            formConsulta.addEventListener('submit', (e) => e.preventDefault());

            const archivos = document.getElementById('archivosConsulta');
            if (archivos) archivos.addEventListener('change', () => { updateFileList(archivos); hayCambiosSinGuardar = true; });
            iniciarTratamientos();
        }

        const formVacuna = document.getElementById('formVacunaAtencion');
        if (formVacuna) iniciarValidacionProcedimiento(formVacuna);
        if (formVacuna) formVacuna.addEventListener('submit', (e) => registrarProcedimiento(e, {
            endpoint: 'index.php?action=registrar_vacuna_ajax',
            lista: 'listaVacunasAplicadas',
            plantilla: plantillaVacuna,
            contador: 'vacuna',
            exito: 'Vacuna registrada',
        }));

        const formDesp = document.getElementById('formDesparasitacionAtencion');
        if (formDesp) iniciarValidacionProcedimiento(formDesp);
        if (formDesp) formDesp.addEventListener('submit', (e) => registrarProcedimiento(e, {
            endpoint: 'index.php?action=registrar_desparasitacion_ajax',
            lista: 'listaDesparasitacionesAplicadas',
            plantilla: plantillaDesparasitacion,
            contador: 'desparasitacion',
            exito: 'Desparasitación registrada',
        }));

        window.addEventListener('beforeunload', (e) => {
            if (hayCambiosSinGuardar && !finalizando) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', iniciar);
})();
