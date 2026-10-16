/**
 * M2 C5a — formulario de consulta compartido por sus dos entradas (A.1): el
 * modal (Pacientes, Consultas y Calendario) y Atención.
 *
 * - Motivo y diagnóstico se ven obligatorios desde que se abre la consulta y
 *   el aviso va junto al campo mientras se escribe, con la regla del servidor
 *   (ValidadorClinico::AVISOS_CONSULTA, en data-aviso-*).
 * - Un 422 del servidor con `campo` se muestra junto a ese campo.
 * - Une los síntomas (consulta-sintomas.js) con los diagnósticos sugeridos
 *   (consulta-diagnosticos.js).
 *
 * Al final, el adaptador del modal conserva las llamadas de siempre
 * (openConsultationModal, saveConsultation, updateFileList y
 * avisarAdjuntosRechazados), que antes vivían en medical-module.js, y pone
 * la pantalla al día con la respuesta, sin recargar.
 */
(function () {
    'use strict';

    const OBLIGATORIO = 'Obligatorio.';
    const EVENTO_REGISTRADA = 'zooki:consulta-registrada';

    function avisoDe(form, nombre) {
        return form.querySelector(`[data-campo-aviso="${nombre}"]`);
    }

    /** La misma regla del servidor: obligatorio, longitud máxima y rango de los signos. */
    function errorDe(campo) {
        const valor = String(campo.value === undefined || campo.value === null ? '' : campo.value);
        const texto = valor.trim();
        if (campo.required && texto === '') return campo.dataset.avisoRequerido || 'Este campo es obligatorio.';
        const maximo = Number(campo.getAttribute('maxlength'));
        if (maximo > 0 && valor.length > maximo) return `Admite hasta ${maximo} caracteres.`;
        if (texto !== '' && campo.type === 'number') {
            const numero = Number(texto);
            const minimo = campo.getAttribute('min');
            const tope = campo.getAttribute('max');
            const fuera = !Number.isFinite(numero)
                || (minimo !== null && numero < Number(minimo))
                || (tope !== null && numero > Number(tope))
                || (campo.getAttribute('step') === '1' && !Number.isInteger(numero));
            if (fuera) return campo.dataset.avisoRango || 'Revisa este valor.';
        }
        return '';
    }

    /** Aviso bajo el campo; los opcionales lo crean solo cuando hay un error. */
    function pintarAviso(form, campo, mensaje) {
        const nombre = campo.dataset.consultaCampo;
        let aviso = avisoDe(form, nombre);
        if (!aviso && !mensaje) return;
        if (!aviso) {
            aviso = document.createElement('small');
            aviso.className = 'cuenta-validacion';
            aviso.dataset.campoAviso = nombre;
            campo.insertAdjacentElement('afterend', aviso);
        }
        aviso.textContent = mensaje || (campo.required ? OBLIGATORIO : '');
        aviso.classList.toggle('cuenta-validacion--error', Boolean(mensaje));
        campo.setAttribute('aria-invalid', mensaje ? 'true' : 'false');
    }

    function camposDe(form) {
        return [...form.querySelectorAll('[data-consulta-campo]')];
    }

    function preparar(form) {
        camposDe(form).forEach(campo => {
            campo.addEventListener('input', () => pintarAviso(form, campo, errorDe(campo)));
        });
    }

    /** Marca todos los campos y devuelve el primero con error (o null). */
    function validarTodo(form) {
        let primero = null;
        camposDe(form).forEach(campo => {
            const mensaje = errorDe(campo);
            pintarAviso(form, campo, mensaje);
            if (mensaje && primero === null) primero = campo;
        });
        return primero;
    }

    function reiniciar(form) {
        camposDe(form).forEach(campo => pintarAviso(form, campo, ''));
    }

    /** 422 con `campo`: el aviso va junto a ese campo. Devuelve el campo, o null si no es de este formulario. */
    function mostrarErrorServidor(form, respuesta) {
        if (!respuesta || typeof respuesta.campo !== 'string') return null;
        const campo = form.querySelector(`[data-consulta-campo="${respuesta.campo}"]`);
        if (!campo) return null;
        pintarAviso(form, campo, respuesta.aviso || respuesta.message);
        return campo;
    }

    /**
     * Prepara una consulta: validación, síntomas y diagnósticos sugeridos.
     * opciones.idMascota() da la mascota; opciones.alCambiarSintomas(lista) es opcional.
     */
    function iniciar(form, opciones = {}) {
        const idMascota = opciones.idMascota || (() => '');
        const raizDiagnosticos = form.querySelector('[data-diagnosticos]');
        const campoDiagnostico = form.querySelector('[data-consulta-campo="diagnostico"]');
        const diagnosticos = raizDiagnosticos && window.ZookiDiagnosticos
            ? window.ZookiDiagnosticos.crear(raizDiagnosticos, { idMascota, campoDiagnostico })
            : null;
        const raizSintomas = form.querySelector('[data-sintomas]');
        const sintomas = raizSintomas && window.ZookiSintomas
            ? window.ZookiSintomas.crear(raizSintomas, {
                idMascota,
                alCambiar: lista => {
                    if (diagnosticos) diagnosticos.actualizar(lista);
                    if (opciones.alCambiarSintomas) opciones.alCambiarSintomas(lista);
                },
            })
            : null;
        preparar(form);

        return {
            sintomas,
            diagnosticos,
            validar: () => validarTodo(form),
            limpiar() {
                reiniciar(form);
                if (sintomas) sintomas.limpiar();
                if (diagnosticos) diagnosticos.limpiar();
            },
            mostrarErrorServidor(respuesta) {
                if (respuesta && respuesta.campo === 'sintomas' && sintomas) {
                    sintomas.mostrarError(respuesta.aviso || respuesta.message);
                    return sintomas.campo;
                }
                return mostrarErrorServidor(form, respuesta);
            },
        };
    }

    // ── Adaptador del modal de consulta (views/vet/modal_consulta.php) ──────

    let consultaModal = null;

    function controladorModal() {
        const form = document.getElementById('formConsulta');
        if (!form) return null;
        if (consultaModal === null) {
            consultaModal = iniciar(form, { idMascota: () => document.getElementById('consultation_id_mascota').value });
        }
        return consultaModal;
    }

    /** Activa una pestaña del modal con su botón, como si se hiciera clic. */
    function mostrarPestana(id) {
        const modal = document.getElementById('modalConsulta');
        const boton = modal ? modal.querySelector(`[data-tab="${id}"]`) : null;
        if (boton && typeof switchModalTab === 'function') switchModalTab({ target: boton, currentTarget: boton }, id);
    }

    /** Lleva a la pestaña del campo y lo enfoca. */
    function irAlCampoDelModal(campo) {
        const pestana = campo.closest('.modal-tab-content');
        if (pestana) mostrarPestana(pestana.id);
        campo.focus();
    }

    function openConsultationModal(id, nombre) {
        const form = document.getElementById('formConsulta');
        form.reset();
        document.getElementById('consultation_id_mascota').value = id;
        document.getElementById('consultationPetName').textContent = nombre;
        document.getElementById('treatmentsList').replaceChildren();
        const archivos = document.getElementById('fileList');
        if (archivos) archivos.replaceChildren();
        controladorModal().limpiar();
        mostrarPestana('tabMotivo');
        openModal('modalConsulta');
    }

    async function saveConsultation(evento) {
        evento.preventDefault();
        const form = evento.currentTarget || evento.target;
        const consulta = controladorModal();
        const invalido = consulta.validar();
        if (invalido) {
            irAlCampoDelModal(invalido);
            return;
        }

        const boton = form.querySelector('[type="submit"]');
        if (boton) boton.disabled = true;
        try {
            const idMascota = String(document.getElementById('consultation_id_mascota').value);
            const res = await (await fetch('index.php?action=registrar_consulta_ajax', { method: 'POST', body: new FormData(form) })).json();
            if (res.success) {
                closeModal('modalConsulta');
                // La pantalla se pone al día con la respuesta, sin recargar ni esperar al aviso.
                document.dispatchEvent(new CustomEvent(EVENTO_REGISTRADA, { detail: { idMascota, consulta: res.consulta || null } }));
                zookiToast(res.message);
                return;
            }
            if (avisarAdjuntosRechazados(res)) return;
            const campo = consulta.mostrarErrorServidor(res);
            if (campo) {
                irAlCampoDelModal(campo);
                return;
            }
            zookiAviso(res.message || 'No se pudo guardar la consulta.');
        } catch (error) {
            console.error('saveConsultation', error);
            zookiAviso('No se pudo guardar la consulta. Intenta nuevamente.');
        } finally {
            if (boton) boton.disabled = false;
        }
    }

    /** Muestra los nombres de los archivos elegidos en un input de adjuntos. */
    function updateFileList(input) {
        const zona = input.closest('.file-upload-zone');
        const lista = zona ? zona.querySelector('.file-list, #fileList') : null;
        if (!lista) return;
        lista.replaceChildren(...[...input.files].map(archivo => {
            const chip = document.createElement('span');
            chip.className = 'file-chip';
            const icono = document.createElement('i');
            icono.className = /\.pdf$/i.test(archivo.name) ? 'fas fa-file-pdf' : 'fas fa-image';
            chip.append(icono, ` ${archivo.name}`);
            return chip;
        }));
    }

    /**
     * HU-34: si el servidor rechazó adjuntos, dice cuáles y por qué, uno por
     * uno. Devuelve true si mostró el aviso. La usan el modal y Atención.
     */
    function avisarAdjuntosRechazados(res) {
        if (!Array.isArray(res.adjuntos_rechazados) || !res.adjuntos_rechazados.length) return false;

        const lista = res.adjuntos_rechazados
            .map(a => `<li><strong>${escapeHtml(a.archivo)}</strong>: ${escapeHtml(a.motivo)}</li>`)
            .join('');

        Swal.fire({
            icon: 'warning',
            title: 'La consulta no se guardó',
            html: `<p class="adjuntos-rechazados__texto">${escapeHtml(res.message)}</p>
                   <ul class="adjuntos-rechazados__lista">${lista}</ul>`,
            confirmButtonColor: '#5560FF',
        });
        return true;
    }

    function enlazarModal() {
        const form = document.getElementById('formConsulta');
        if (!form) return;
        controladorModal();
        form.addEventListener('submit', saveConsultation);
        const archivos = form.querySelector('[data-consulta-archivos]');
        if (archivos) archivos.addEventListener('change', () => updateFileList(archivos));
    }

    window.ZookiConsultaForm = { OBLIGATORIO, EVENTO_REGISTRADA, errorDe, validarTodo, mostrarErrorServidor, iniciar };
    window.openConsultationModal = openConsultationModal;
    window.saveConsultation = saveConsultation;
    window.updateFileList = updateFileList;
    window.avisarAdjuntosRechazados = avisarAdjuntosRechazados;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enlazarModal);
    } else {
        enlazarModal();
    }
})();
