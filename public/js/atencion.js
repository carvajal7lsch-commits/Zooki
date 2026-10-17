/*
 * Pantalla de atención de una cita (views/vet/atencion.php).
 *
 * La página no hace scroll: la vista ocupa el alto disponible y el trabajo se
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

    // ── Alto disponible (sin scroll de página) ──────────────────────────
    function ajustarAlto() {
        const cuerpo = document.querySelector('.content-body');
        const paddingInferior = cuerpo ? parseFloat(getComputedStyle(cuerpo).paddingBottom) || 0 : 0;
        const arriba = app.getBoundingClientRect().top + window.scrollY;
        const alto = Math.max(window.innerHeight - arriba - paddingInferior, 560);
        app.style.setProperty('--atencion-alto', `${Math.floor(alto)}px`);
    }

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

    // ── Pestañas ────────────────────────────────────────────────────────
    const navPasos = document.getElementById('navPasos');

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
        if (!pasos.length) return;
        pasoActual = Math.max(0, Math.min(indice, pasos.length - 1));
        pasos.forEach((paso, i) => paso.classList.toggle('is-active', i === pasoActual));
        botonesPaso.forEach((boton, i) => {
            boton.classList.toggle('is-active', i === pasoActual);
            boton.classList.toggle('is-hecho', i < pasoActual);
        });
        if (window.matchMedia && window.matchMedia('(max-width: 767px)').matches && botonesPaso[pasoActual]) botonesPaso[pasoActual].scrollIntoView({ block: 'nearest', inline: 'nearest' });
        if (btnAnterior) btnAnterior.disabled = pasoActual === 0;
        if (btnSiguiente) btnSiguiente.hidden = pasoActual === pasos.length - 1;
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

    // ── Borrador automático ─────────────────────────────────────────────
    // Solo los campos de texto y números: archivos y medicamentos no se
    // pueden guardar en el navegador (por eso sigue el aviso al salir).
    const CLAVE_BORRADOR = `zooki_atencion_borrador_${idCita}`;
    const CAMPOS_BORRADOR = [
        'motivo', 'anamnesis', 'peso', 'temperatura', 'frecuencia_cardiaca',
        'frecuencia_respiratoria', 'diagnostico', 'plan_tratamiento', 'observaciones',
    ];
    const estadoBorrador = document.getElementById('estadoBorrador');
    let temporizadorBorrador = null;

    function mostrarEstadoBorrador(marcaTiempo) {
        if (!estadoBorrador || !marcaTiempo) return;
        const hora = new Date(marcaTiempo).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
        estadoBorrador.textContent = `Borrador guardado ${hora}`;
    }

    function guardarBorrador() {
        try {
            const datos = {};
            CAMPOS_BORRADOR.forEach((nombre) => {
                const campo = formConsulta.elements[nombre];
                if (campo) datos[nombre] = campo.value;
            });
            // C5a: los síntomas marcados también son datos editables del borrador.
            if (consulta && consulta.sintomas) datos.sintomas = consulta.sintomas.marcados();
            // C5b: «No se pudo pesar» se recuerda como lo que es, no como un peso.
            const sinPeso = formConsulta.querySelector('[data-sin-peso]');
            if (sinPeso) datos.sin_peso = sinPeso.checked;
            const ahora = Date.now();
            localStorage.setItem(CLAVE_BORRADOR, JSON.stringify({ datos, guardado: ahora }));
            mostrarEstadoBorrador(ahora);
        } catch (e) {
            // Sin almacenamiento disponible (modo privado, etc.): no pasa nada.
        }
    }

    function restaurarBorrador() {
        try {
            const crudo = localStorage.getItem(CLAVE_BORRADOR);
            if (!crudo) return;
            const { datos, guardado } = JSON.parse(crudo);
            let restaurado = false;
            CAMPOS_BORRADOR.forEach((nombre) => {
                const campo = formConsulta.elements[nombre];
                if (campo && datos[nombre] && campo.value !== datos[nombre]) {
                    campo.value = datos[nombre];
                    restaurado = true;
                }
            });
            const sinPeso = formConsulta.querySelector('[data-sin-peso]');
            if (sinPeso && datos.sin_peso === true && !sinPeso.checked) {
                sinPeso.checked = true;
                sinPeso.dispatchEvent(new Event('change'));
                restaurado = true;
            }
            if (consulta && consulta.sintomas && Array.isArray(datos.sintomas) && datos.sintomas.length) {
                consulta.sintomas.poner(datos.sintomas);
                restaurado = true;
            }
            if (restaurado) {
                hayCambiosSinGuardar = true;
                zookiToast('Recuperamos el borrador que no alcanzaste a guardar.', 'info');
            }
            mostrarEstadoBorrador(guardado);
        } catch (e) {
            // Borrador ilegible: se ignora.
        }
    }

    function borrarBorrador() {
        try { localStorage.removeItem(CLAVE_BORRADOR); } catch (e) { /* sin almacenamiento */ }
    }

    // ── Iniciar atención ────────────────────────────────────────────────
    function marcarEnCurso() {
        app.dataset.estado = 'en_curso';
        const estado = document.getElementById('atencionEstado');
        estado.textContent = 'En curso';
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

        const mensaje = consultaRegistrada
            ? 'La cita quedará marcada como completada.'
            : 'Se guardará la consulta en la historia clínica y la cita quedará completada. Después no se puede editar.';
        if (!(await zookiConfirmar(mensaje, '¿Finalizar la atención?', 'Sí, finalizar'))) return;

        finalizando = true;
        textoBotonFinalizar(true);

        try {
            if (!consultaRegistrada) {
                // Guardar la consulta completa la cita en la misma transacción
                // (RN-406): o quedan las dos cosas, o ninguna.
                const res = await postJson('index.php?action=registrar_consulta_ajax', new FormData(formConsulta));
                if (!res.success) {
                    finalizando = false;
                    if (avisarAdjuntosRechazados(res)) return;
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

            const resultado = await Swal.fire({
                icon: 'success',
                title: 'Atención finalizada',
                text: 'La consulta quedó en la historia clínica y la cita se marcó como completada.',
                showCancelButton: true,
                confirmButtonText: 'Volver a la agenda',
                cancelButtonText: 'Ver resumen',
                confirmButtonColor: ZOOKI_COLOR_OK,
                cancelButtonColor: '#94A3B8',
            });
            if (resultado.isConfirmed) {
                window.location.href = 'index.php?action=vet_agenda';
            } else {
                window.location.reload();
            }
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

        const error = primerCampoInvalido(form);
        if (error) {
            marcarInvalido(error.campo);
            error.campo.focus();
            zookiToast(error.mensaje, 'error');
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
        ajustarAlto();
        // El layout entra con una animación de 0.5 s que desplaza el contenido:
        // se vuelve a medir cuando termina.
        setTimeout(ajustarAlto, 600);
        window.addEventListener('resize', ajustarAlto);

        const foto = document.getElementById('atencionFoto');
        if (foto) foto.addEventListener('error', () => usarFotoPorDefecto(foto), { once: true });

        app.querySelectorAll('.atencion__tab').forEach((boton) => {
            boton.addEventListener('click', () => activarPestana(boton.dataset.tab));
        });

        botonesPaso.forEach((boton) => {
            boton.addEventListener('click', () => mostrarPaso(Number(boton.dataset.paso)));
        });
        if (btnAnterior) btnAnterior.addEventListener('click', () => mostrarPaso(pasoActual - 1));
        if (btnSiguiente) btnSiguiente.addEventListener('click', () => mostrarPaso(pasoActual + 1));
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
                        clearTimeout(temporizadorBorrador);
                        temporizadorBorrador = setTimeout(guardarBorrador, 600);
                    },
                });
            }
            restaurarBorrador();
            formConsulta.addEventListener('input', (e) => {
                if (e.target.type === 'file') return;
                hayCambiosSinGuardar = true;
                clearTimeout(temporizadorBorrador);
                temporizadorBorrador = setTimeout(guardarBorrador, 600);
            });
            // Enter en un input no debe enviar nada: se finaliza con el botón.
            formConsulta.addEventListener('submit', (e) => e.preventDefault());

            const archivos = document.getElementById('archivosConsulta');
            if (archivos) archivos.addEventListener('change', () => { updateFileList(archivos); hayCambiosSinGuardar = true; });
            iniciarTratamientos();
        }

        const formVacuna = document.getElementById('formVacunaAtencion');
        if (formVacuna) formVacuna.addEventListener('submit', (e) => registrarProcedimiento(e, {
            endpoint: 'index.php?action=registrar_vacuna_ajax',
            lista: 'listaVacunasAplicadas',
            plantilla: plantillaVacuna,
            contador: 'vacuna',
            exito: 'Vacuna registrada',
        }));

        const formDesp = document.getElementById('formDesparasitacionAtencion');
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
