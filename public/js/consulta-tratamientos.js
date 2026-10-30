/**
 * M2 C5b — HU-2.4 y HU-2.9 (RE-2.9.1 a RE-2.9.4, RN-211, RN-209): filas de
 * tratamiento de la consulta, compartidas por el modal y Atención.
 *
 * - Cada fila se clona de la plantilla de views/partials/consulta_tratamiento_fila.php,
 *   con los máximos del servidor.
 * - El medicamento busca fármacos del conocimiento (buscar_farmaco_ajax): elegir uno
 *   pone su código; un nombre libre no se cruza y la fila lo dice (evaluación
 *   incompleta, nunca «sin interacciones»).
 * - La fecha de fin se calcula desde la duración solo cuando es inequívoca
 *   («7 días», «2 semanas», «dosis única»); el veterinario la ve y la corrige.
 * - validar_prescripcion_ajax evalúa todas las filas juntas, con una espera corta:
 *   el bloqueo no se confirma; la advertencia pide «Confirmo la prescripción» y,
 *   si es protegida (RN-113), una conciliación escrita. La huella vigente viaja con
 *   cada fila; si cambia, la fila vuelve a pedir confirmación.
 * - Los errores del servidor tratamientos.N(.campo) salen junto a su fila.
 */
(function () {
    'use strict';

    const RUTA_BUSCAR = 'index.php?action=buscar_farmaco_ajax';
    const RUTA_VALIDAR = 'index.php?action=validar_prescripcion_ajax';
    const BUSQUEDA_MIN = 2;
    const ESPERA_BUSQUEDA_MS = 250;
    const ESPERA_VALIDAR_MS = 400;
    const TEXTOS = {
        sinCruce: 'No se pudo cruzar con el conocimiento: evaluación incompleta.',
        cruzado: 'Del catálogo: ',
        evaluando: 'Revisando alertas de prescripción…',
        bloqueo: 'Prescripción bloqueada: cambia el fármaco o quita la fila.',
        advertencia: 'Advertencia: confirma la prescripción o cambia el fármaco.',
        sinAlertas: 'Sin alertas en el conocimiento cargado. Es apoyo: decide el veterinario.',
        incompleta: 'Evaluación incompleta: hay medicación, alergias o datos sin enlazar; no significa que no haya alertas.',
        error: 'No se pudieron revisar las alertas. Se revisan de nuevo al guardar.',
        confirmar: 'Confirma la prescripción o cambia el fármaco.',
        conciliar: 'Documenta la conciliación con el propietario.',
        bloqueada: 'Esta fila está bloqueada: cambia el fármaco o quítala.',
        finAuto: 'Calculado desde la duración; corrígelo si hace falta.',
        finManual: 'Si el tratamiento es acotado, indica el fin.',
        cambio: 'Cambió algo en la prescripción: revísala y confirma de nuevo.',
    };
    // El servidor dice el campo; algunos se muestran en el aviso de otro.
    const AVISO_DE_CAMPO = { farmaco: 'medicamento', huella: 'decision' };
    const OBLIGATORIOS = ['medicamento', 'dosis', 'via_administracion', 'duracion', 'fecha_inicio'];

    function hoy() {
        const fecha = new Date();
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const dia = String(fecha.getDate()).padStart(2, '0');
        return `${fecha.getFullYear()}-${mes}-${dia}`;
    }

    function normalizar(texto) {
        return String(texto || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    /**
     * A.2: el fin se deduce solo de una duración inequívoca. «N días» cuenta el
     * día de inicio (7 días desde el 1 terminan el 7); «N semanas» son N×7 días;
     * «dosis única», el mismo día. Lo demás («hasta mejorar», meses) no se adivina.
     */
    function finDesdeDuracion(inicio, duracion) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(String(inicio || ''))) return null;
        const texto = normalizar(duracion);
        let dias = null;
        if (texto === 'dosis unica' || texto === '1 dosis') {
            dias = 1;
        } else {
            const partes = texto.match(/^(\d{1,3}) (dia|dias|semana|semanas)$/);
            if (partes && Number(partes[1]) > 0) dias = Number(partes[1]) * (partes[2].startsWith('semana') ? 7 : 1);
        }
        if (dias === null) return null;
        const [anio, mes, dia] = inicio.split('-').map(Number);
        const fin = new Date(Date.UTC(anio, mes - 1, dia + dias - 1));
        return fin.toISOString().slice(0, 10);
    }

    function elemento(etiqueta, clase, texto) {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    }

    function campoDe(fila, nombre) {
        return fila.querySelector(`[data-tratamiento-campo="${nombre}"]`);
    }

    function avisoDe(fila, nombre) {
        return fila.querySelector(`[data-tratamiento-aviso="${AVISO_DE_CAMPO[nombre] || nombre}"]`);
    }

    function pintarAviso(fila, nombre, mensaje) {
        const aviso = avisoDe(fila, nombre);
        if (!aviso) return;
        aviso.textContent = mensaje || '';
        aviso.classList.toggle('cuenta-validacion--error', Boolean(mensaje));
        const campo = campoDe(fila, nombre);
        if (campo && campo.type !== 'hidden') campo.setAttribute('aria-invalid', mensaje ? 'true' : 'false');
    }

    /** La regla del servidor para un campo de la fila; avisos son los de ValidadorTratamiento::AVISOS. */
    function errorDeCampo(fila, nombre, avisos) {
        const campo = campoDe(fila, nombre);
        if (!campo) return '';
        const valor = String(campo.value || '').trim();
        if (OBLIGATORIOS.includes(nombre) && valor === '') return avisos[nombre] || 'Este campo es obligatorio.';
        const maximo = Number(campo.getAttribute('maxlength'));
        if (maximo > 0 && Array.from(valor).length > maximo) return `Admite hasta ${maximo} caracteres.`;
        if (nombre === 'fecha_fin' && valor !== '' && valor < String(campoDe(fila, 'fecha_inicio').value || '')) return avisos.fecha_fin || 'El fin no puede ser anterior al inicio.';
        return '';
    }

    /** Lo que se muestra de una fuente: la clínica primero (reutiliza la regla de los diagnósticos). */
    function nodoFuente(fuentes) {
        const principal = window.ZookiDiagnosticos ? window.ZookiDiagnosticos.fuentePrincipal(fuentes) : null;
        if (!principal) return null;
        const parrafo = elemento('span', 'consulta-tratamiento__fuente', ' Fuente: ');
        if (principal.url) {
            const enlace = elemento('a', '', principal.texto);
            enlace.href = principal.url;
            enlace.target = '_blank';
            enlace.rel = 'noopener noreferrer';
            parrafo.append(enlace);
        } else {
            parrafo.append(principal.texto);
        }
        return parrafo;
    }

    /** Un motivo o advertencia público; el aviso protegido sale tal cual, sin nada más. */
    function itemAlerta(alerta) {
        const item = elemento('li', `consulta-tratamiento__alerta consulta-tratamiento__alerta--${alerta.tipo}`, alerta.mensaje);
        if (alerta.tipo === 'interaccion_protegida') return item;
        if (alerta.paciente) item.append(` Paciente: ${alerta.paciente}.`);
        if (alerta.farmaco) item.append(` Interactúa con: ${alerta.farmaco}.`);
        const fuente = nodoFuente(alerta.fuentes);
        if (fuente) item.append(fuente);
        return item;
    }

    /** Las filas que el servidor recibe: las que tienen medicamento, en orden (Consulta las numera así). */
    function filasEnviadas(filas) {
        return filas.filter(fila => String(campoDe(fila, 'medicamento').value || '').trim() !== '');
    }

    /**
     * Prepara un bloque [data-tratamientos]. opciones.idMascota() da la mascota;
     * opciones.avisar(mensaje) muestra un aviso breve (zookiToast por defecto).
     */
    function crear(raiz, opciones = {}) {
        const lista = raiz.querySelector('[data-tratamientos-lista]');
        const plantilla = raiz.querySelector('[data-tratamiento-plantilla]');
        const vacio = raiz.querySelector('[data-tratamientos-vacio]');
        const idMascota = opciones.idMascota || (() => '');
        const avisos = JSON.parse(raiz.dataset.avisos || '{}');
        const errorDe = (fila, nombre) => errorDeCampo(fila, nombre, avisos);
        const avisar = opciones.avisar || (mensaje => { if (typeof zookiToast === 'function') zookiToast(mensaje, 'info'); });
        let temporizador = null;
        let peticion = 0;
        let enCurso = null;
        let firmaEvaluada = null;

        function filas() {
            return [...lista.querySelectorAll('[data-tratamiento]')];
        }

        function actualizarVacio() {
            if (vacio) vacio.hidden = filas().length > 0;
        }

        function firma() {
            return JSON.stringify(filasEnviadas(filas()).map(fila => campoDe(fila, 'farmaco').value || ''));
        }

        function cuerpo(enviadas) {
            const datos = new URLSearchParams();
            datos.append('id_mascota', String(idMascota()));
            enviadas.forEach((fila, indice) => datos.append(`tratamientos[${indice}][farmaco]`, campoDe(fila, 'farmaco').value || ''));
            return datos;
        }

        function ponerDecision(fila, confirmada) {
            fila.querySelector('[data-prescripcion-casilla]').checked = confirmada;
            campoDe(fila, 'decision').value = confirmada ? 'confirmar' : '';
        }

        function pintarPrescripcion(fila, resultado) {
            const anterior = fila._evaluacion;
            const estaba = campoDe(fila, 'decision').value === 'confirmar';
            const listaAlertas = fila.querySelector('[data-prescripcion-lista]');
            const estado = fila.querySelector('[data-prescripcion-estado]');
            const confirmar = fila.querySelector('[data-prescripcion-confirmar]');
            const conciliacion = fila.querySelector('[data-prescripcion-conciliacion]');
            const sinCodigo = (campoDe(fila, 'farmaco').value || '') === '';
            fila._evaluacion = resultado;
            campoDe(fila, 'huella').value = resultado.huella || '';
            fila.classList.remove('consulta-tratamiento--bloqueo', 'consulta-tratamiento--advertencia', 'consulta-tratamiento--incompleta');
            const alertas = [...(resultado.motivos || []), ...(resultado.bloqueada ? [] : (resultado.advertencias || []))];
            const items = alertas.map(itemAlerta);
            if (!sinCodigo && resultado.evaluacion_incompleta) items.push(elemento('li', 'consulta-tratamiento__alerta consulta-tratamiento__alerta--incompleta', TEXTOS.incompleta));
            listaAlertas.replaceChildren(...items);
            listaAlertas.hidden = items.length === 0;
            let texto = TEXTOS.sinAlertas;
            if (sinCodigo) {
                texto = TEXTOS.sinCruce;
                fila.classList.add('consulta-tratamiento--incompleta');
            } else if (resultado.bloqueada) {
                texto = TEXTOS.bloqueo;
                fila.classList.add('consulta-tratamiento--bloqueo');
            } else if (resultado.requiere_decision) {
                texto = TEXTOS.advertencia;
                fila.classList.add('consulta-tratamiento--advertencia');
            }
            estado.textContent = texto;
            confirmar.hidden = !resultado.requiere_decision;
            conciliacion.hidden = !resultado.requiere_conciliacion;
            // Una confirmación vale solo para la huella con la que se dio.
            const vigente = estaba && anterior && anterior.huella === resultado.huella && resultado.requiere_decision;
            if (estaba && !vigente) {
                ponerDecision(fila, false);
                if (resultado.requiere_decision) {
                    pintarAviso(fila, 'decision', TEXTOS.cambio);
                    avisar(TEXTOS.cambio);
                }
            }
            if (!resultado.requiere_decision) pintarAviso(fila, 'decision', '');
            if (!resultado.requiere_conciliacion) pintarAviso(fila, 'conciliacion', '');
            pintarAviso(fila, 'fila', '');
        }

        async function evaluar() {
            const numero = ++peticion;
            const enviadas = filasEnviadas(filas());
            const firmaActual = firma();
            if (enviadas.length === 0) {
                firmaEvaluada = firmaActual;
                return;
            }
            enviadas.forEach(fila => { fila.querySelector('[data-prescripcion-estado]').textContent = TEXTOS.evaluando; });
            try {
                const respuesta = await (await fetch(RUTA_VALIDAR, { method: 'POST', body: cuerpo(enviadas) })).json();
                if (numero !== peticion) return;
                if (!respuesta.success || !Array.isArray(respuesta.filas)) {
                    const indice = typeof respuesta.campo === 'string' ? Number(respuesta.campo.split('.')[1]) : NaN;
                    if (enviadas[indice]) pintarAviso(enviadas[indice], 'medicamento', respuesta.aviso || respuesta.message);
                    enviadas.forEach(fila => { fila.querySelector('[data-prescripcion-estado]').textContent = TEXTOS.error; });
                    return;
                }
                enviadas.forEach((fila, indice) => { if (respuesta.filas[indice]) pintarPrescripcion(fila, respuesta.filas[indice]); });
                firmaEvaluada = firmaActual;
            } catch (error) {
                if (numero !== peticion) return;
                enviadas.forEach(fila => { fila.querySelector('[data-prescripcion-estado]').textContent = TEXTOS.error; });
            }
        }

        /** Con una espera corta; cada cambio de fármaco o de fechas vuelve a evaluar todas las filas. */
        function programar() {
            clearTimeout(temporizador);
            firmaEvaluada = null;
            enCurso = new Promise(resolver => {
                temporizador = setTimeout(() => {
                    evaluar().then(resolver, resolver);
                }, ESPERA_VALIDAR_MS);
            });
        }

        function quitarFarmaco(fila) {
            if (campoDe(fila, 'farmaco').value === '') return;
            campoDe(fila, 'farmaco').value = '';
            fila.querySelector('[data-farmaco-estado]').textContent = TEXTOS.sinCruce;
            programar();
        }

        function elegirFarmaco(fila, farmaco) {
            const campo = campoDe(fila, 'medicamento');
            campo.value = farmaco.etiqueta;
            campoDe(fila, 'farmaco').value = farmaco.codigo;
            fila.querySelector('[data-farmaco-estado]').textContent = TEXTOS.cruzado + farmaco.etiqueta;
            cerrarSugerencias(fila);
            pintarAviso(fila, 'medicamento', '');
            programar();
        }

        function cerrarSugerencias(fila) {
            const sugerencias = fila.querySelector('[data-farmaco-sugerencias]');
            sugerencias.replaceChildren();
            sugerencias.hidden = true;
            campoDe(fila, 'medicamento').setAttribute('aria-expanded', 'false');
            window.ZookiCombobox.reiniciar(campoDe(fila, 'medicamento'));
        }

        async function buscar(fila, texto) {
            const numero = ++fila._busqueda;
            const sugerencias = fila.querySelector('[data-farmaco-sugerencias]');
            try {
                const respuesta = await (await fetch(`${RUTA_BUSCAR}&${new URLSearchParams({ q: texto }).toString()}`)).json();
                if (numero !== fila._busqueda) return;
                const farmacos = respuesta.success && Array.isArray(respuesta.farmacos) ? respuesta.farmacos : [];
                if (farmacos.length === 0) {
                    sugerencias.replaceChildren(elemento('li', 'consulta-sintomas__vacio', 'No está en el catálogo: se guarda con el nombre escrito, sin cruzar.'));
                } else {
                    sugerencias.replaceChildren(...farmacos.map(farmaco => {
                        const item = elemento('li');
                        item.setAttribute('role', 'presentation');
                        const boton = elemento('button', 'consulta-sintomas__opcion', farmaco.etiqueta);
                        boton.type = 'button';
                        boton.tabIndex = -1;
                        boton.dataset.farmaco = farmaco.codigo;
                        boton.setAttribute('role', 'option');
                        boton.addEventListener('click', () => elegirFarmaco(fila, farmaco));
                        item.append(boton);
                        return item;
                    }));
                }
                sugerencias.hidden = false;
                campoDe(fila, 'medicamento').setAttribute('aria-expanded', 'true');
                window.ZookiCombobox.reiniciar(campoDe(fila, 'medicamento'));
            } catch (error) {
                if (numero === fila._busqueda) cerrarSugerencias(fila);
            }
        }

        /** Propone el fin desde la duración mientras el veterinario no lo haya cambiado a mano. */
        function proponerFin(fila) {
            const inicio = campoDe(fila, 'fecha_inicio').value;
            const fin = campoDe(fila, 'fecha_fin');
            const ayuda = fila.querySelector('[data-fin-ayuda]');
            fin.setAttribute('min', inicio || '');
            if (fila.dataset.finManual === '1') return;
            const calculado = finDesdeDuracion(inicio, campoDe(fila, 'duracion').value);
            fin.value = calculado || '';
            ayuda.textContent = calculado ? TEXTOS.finAuto : TEXTOS.finManual;
        }

        function enlazar(fila) {
            fila._busqueda = 0;
            fila._enviada = false;
            let temporizadorBusqueda = null;
            const medicamento = campoDe(fila, 'medicamento');
            medicamento.addEventListener('input', () => {
                quitarFarmaco(fila);
                pintarAviso(fila, 'medicamento', errorDe(fila, 'medicamento'));
                const texto = medicamento.value.trim();
                // Entrar o salir de las filas enviadas cambia los cruces de las demás.
                const enviada = texto !== '';
                if (enviada !== fila._enviada) programar();
                fila._enviada = enviada;
                clearTimeout(temporizadorBusqueda);
                if (texto.length < BUSQUEDA_MIN) {
                    fila._busqueda++;
                    cerrarSugerencias(fila);
                    return;
                }
                temporizadorBusqueda = setTimeout(() => buscar(fila, texto), ESPERA_BUSQUEDA_MS);
            });
            // C8 (H6): flechas, Enter y Esc. Enter no envía la consulta: elige el fármaco activo de la lista.
            window.ZookiCombobox.enlazar(medicamento, fila.querySelector('[data-farmaco-sugerencias]'), { cerrar: () => cerrarSugerencias(fila) });
            for (const nombre of ['dosis', 'via_administracion']) {
                const campo = campoDe(fila, nombre);
                campo.addEventListener('input', () => pintarAviso(fila, nombre, errorDe(fila, nombre)));
                campo.addEventListener('change', () => pintarAviso(fila, nombre, errorDe(fila, nombre)));
            }
            campoDe(fila, 'duracion').addEventListener('input', () => {
                pintarAviso(fila, 'duracion', errorDe(fila, 'duracion'));
                proponerFin(fila);
                pintarAviso(fila, 'fecha_fin', errorDe(fila, 'fecha_fin'));
            });
            campoDe(fila, 'fecha_inicio').addEventListener('change', () => {
                pintarAviso(fila, 'fecha_inicio', errorDe(fila, 'fecha_inicio'));
                proponerFin(fila);
                pintarAviso(fila, 'fecha_fin', errorDe(fila, 'fecha_fin'));
                programar();
            });
            campoDe(fila, 'fecha_fin').addEventListener('change', () => {
                fila.dataset.finManual = campoDe(fila, 'fecha_fin').value === '' ? '' : '1';
                fila.querySelector('[data-fin-ayuda]').textContent = fila.dataset.finManual === '1' ? '' : TEXTOS.finManual;
                pintarAviso(fila, 'fecha_fin', errorDe(fila, 'fecha_fin'));
                programar();
            });
            fila.querySelector('[data-prescripcion-casilla]').addEventListener('change', evento => {
                ponerDecision(fila, evento.target.checked);
                if (evento.target.checked) pintarAviso(fila, 'decision', '');
            });
            const conciliacion = campoDe(fila, 'conciliacion');
            const contador = fila.querySelector('[data-conciliacion-contador]');
            conciliacion.addEventListener('input', () => {
                contador.textContent = `${conciliacion.value.length}/${conciliacion.getAttribute('maxlength')}`;
                if (conciliacion.value.trim() !== '') pintarAviso(fila, 'conciliacion', '');
            });
            fila.querySelector('[data-quitar-tratamiento]').addEventListener('click', () => {
                fila.remove();
                actualizarVacio();
                programar();
            });
        }

        function agregar() {
            const fila = plantilla.content.firstElementChild.cloneNode(true);
            campoDe(fila, 'fecha_inicio').value = hoy();
            fila.querySelector('[data-farmaco-estado]').textContent = TEXTOS.sinCruce;
            fila.querySelector('[data-fin-ayuda]').textContent = TEXTOS.finManual;
            lista.appendChild(fila);
            enlazar(fila);
            proponerFin(fila);
            actualizarVacio();
            campoDe(fila, 'medicamento').focus();
            return fila;
        }

        function agregarSugerida(pauta) {
            const fila = agregar();
            fila.dataset.pautaSugerida = pauta.codigo;
            for (const nombre of ['medicamento', 'farmaco', 'dosis', 'via_administracion', 'duracion', 'observaciones']) {
                campoDe(fila, nombre).value = pauta[nombre];
            }
            fila.querySelector('[data-farmaco-estado]').textContent = TEXTOS.cruzado + pauta.medicamento;
            const marca = elemento('small', 'consulta-ayuda', 'Sugerido por Zooki · verifica la dosis');
            marca.dataset.marcaSugerida = '';
            fila.prepend(marca);
            fila._dosisSugerida = pauta.dosis;
            fila._farmacoSugerido = pauta.farmaco;
            fila._dosisEditada = false;
            fila._sugerenciaEditada = false;
            fila.addEventListener('input', evento => {
                fila._sugerenciaEditada = true;
                if (evento.target === campoDe(fila, 'dosis') || evento.target === campoDe(fila, 'medicamento')) fila._dosisEditada = true;
            });
            fila.addEventListener('change', () => { fila._sugerenciaEditada = true; });
            proponerFin(fila);
            pintarPrescripcion(fila, pauta.seguridad);
            for (const nombre of OBLIGATORIOS) pintarAviso(fila, nombre, errorDe(fila, nombre));
            programar();
            return fila;
        }

        function retirarSugeridas(sugeridas) {
            for (const fila of sugeridas) {
                if (!fila._sugerenciaEditada) fila.remove();
            }
            actualizarVacio();
            programar();
        }

        function recalcularSugeridas(sugeridas, pautas) {
            for (const fila of sugeridas) {
                if (!filas().includes(fila)) continue;
                const pauta = pautas.find(p => p.codigo === fila.dataset.pautaSugerida);
                if (!pauta || pauta.seguridad.bloqueada) {
                    if (!fila._sugerenciaEditada) fila.remove();
                    continue;
                }
                if (!fila._dosisEditada && campoDe(fila, 'farmaco').value === fila._farmacoSugerido && campoDe(fila, 'dosis').value === fila._dosisSugerida) {
                    campoDe(fila, 'dosis').value = pauta.dosis;
                    fila._dosisSugerida = pauta.dosis;
                    pintarAviso(fila, 'dosis', errorDe(fila, 'dosis'));
                }
            }
            actualizarVacio();
            programar();
        }

        /** Marca toda fila con error y devuelve el primer campo para llevar allí al veterinario. */
        function validar() {
            let primero = null;
            const marcar = (fila, nombre, mensaje, campo) => {
                pintarAviso(fila, nombre, mensaje);
                if (mensaje && primero === null) primero = campo || campoDe(fila, nombre) || fila;
            };
            for (const fila of filas()) {
                for (const nombre of [...OBLIGATORIOS, 'fecha_fin']) marcar(fila, nombre, errorDe(fila, nombre));
                const evaluacion = fila._evaluacion;
                if (!evaluacion) continue;
                const casilla = fila.querySelector('[data-prescripcion-casilla]');
                marcar(fila, 'fila', evaluacion.bloqueada ? TEXTOS.bloqueada : '', campoDe(fila, 'medicamento'));
                marcar(fila, 'decision', evaluacion.requiere_decision && !casilla.checked ? TEXTOS.confirmar : '', casilla);
                marcar(fila, 'conciliacion', evaluacion.requiere_conciliacion && campoDe(fila, 'conciliacion').value.trim() === '' ? TEXTOS.conciliar : '');
            }
            return primero;
        }

        /** 422 tratamientos.N(.campo): el aviso va junto a la fila N; con su evaluación nueva, se repinta. */
        function mostrarErrorServidor(respuesta) {
            const partes = String(respuesta && respuesta.campo || '').split('.');
            if (partes[0] !== 'tratamientos') return null;
            const fila = filasEnviadas(filas())[Number(partes[1])];
            if (!fila) return null;
            if (respuesta.prescripcion) pintarPrescripcion(fila, respuesta.prescripcion);
            const nombre = partes[2] || 'fila';
            pintarAviso(fila, nombre, respuesta.aviso || respuesta.message);
            return campoDe(fila, AVISO_DE_CAMPO[nombre] || nombre) || campoDe(fila, 'medicamento');
        }

        /** Antes de guardar: espera la evaluación pendiente y, si algo cambió, evalúa de nuevo. */
        async function listo() {
            if (enCurso) await enCurso;
            if (firmaEvaluada !== firma()) {
                clearTimeout(temporizador);
                await evaluar();
            }
        }

        raiz.querySelectorAll('[data-agregar-tratamiento]').forEach(boton => boton.addEventListener('click', agregar));
        // Una alergia o una medicación continua nueva cambia los cruces.
        document.addEventListener('zooki:alertas-medicas', evento => {
            if (String(evento.detail && evento.detail.idMascota) === String(idMascota()) && filas().length > 0) programar();
        });
        actualizarVacio();

        return {
            agregar,
            agregarSugerida,
            retirarSugeridas,
            recalcularSugeridas,
            validar,
            listo,
            mostrarErrorServidor,
            filas,
            limpiar() {
                peticion++;
                clearTimeout(temporizador);
                enCurso = null;
                firmaEvaluada = null;
                lista.replaceChildren();
                actualizarVacio();
            },
        };
    }

    window.ZookiTratamientos = { RUTA_BUSCAR, RUTA_VALIDAR, TEXTOS, finDesdeDuracion, filasEnviadas, crear };
})();
