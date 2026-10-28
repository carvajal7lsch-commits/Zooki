/** C4: M2 confirma marcas; el servidor calcula, revisa y decide la rama. */
(function (raiz) {
    function crearEstado() {
        let turno = 0;
        let seleccion = '';
        let resultado = null;
        return {
            iniciar(datos) {
                seleccion = JSON.stringify(datos);
                resultado = null;
                return ++turno;
            },
            recibir(numero, respuesta) {
                if (numero !== turno) return false;
                resultado = respuesta;
                return true;
            },
            invalidar() {
                resultado = null;
                turno++;
            },
            consultar(datos) {
                const actual = JSON.stringify(datos);
                if (seleccion !== actual) {
                    seleccion = actual;
                    resultado = null;
                    turno++;
                }
                return resultado;
            },
            esActual(numero) {
                return numero === turno;
            }
        };
    }

    const MINUTOS_POR = { minutos: 1, horas: 60, dias: 1440, semanas: 10080 };

    function crear(bloque, opciones) {
        const buscar = selector => bloque.querySelector(selector);
        const salida = buscar('[data-triage-resultado]');
        const aviso = buscar('[data-triage-validacion]');
        const calcular = buscar('[data-triage-calcular]');
        const comprobar = buscar('[data-triage-comprobar]');
        const estado = crearEstado();
        const comando = raiz.zookiCrearComandoAgenda();
        const episodios = new Map();
        let ocupada = false;
        let capturaActual;
        let mascotaAnterior = '';
        let previoRevision = null;
        let nivelCalculado = '';

        function invalidar() {
            estado.invalidar();
            ocupada = false;
            calcular.disabled = false;
            aviso.textContent = '';
            pintarResultado(null);
            comprobar.hidden = true;
            buscar('[data-triage-revision]')?.setAttribute('hidden', '');
            opciones.alCambiar(null);
        }

        function tiempos() {
            const contenedor = buscar('[data-triage-tiempos]');
            const anteriores = new Map(episodios);
            episodios.clear();
            contenedor.replaceChildren();
            for (const [grupo, control] of Object.entries(controles)) {
                for (const marca of control.marcados()) {
                    const dato = anteriores.get(marca.codigo) || { codigo: marca.codigo, tiempo: marca.codigo === 'sintoma.control_preventivo' ? 'no_aplica' : 'no_se' };
                    episodios.set(marca.codigo, dato);
                    // Pulido de interfaz: una fila por marca, con su nombre y desde cuándo, en vez de un texto suelto.
                    const fila = document.createElement('div');
                    fila.className = 'triage-captura__tiempo';
                    const nombre = document.createElement('span');
                    nombre.className = 'triage-captura__tiempo-nombre';
                    nombre.textContent = marca.etiqueta;
                    // El nombre va en su propia línea y se recorta si no cabe; el completo queda en title.
                    nombre.title = marca.etiqueta;
                    const select = document.createElement('select');
                    select.className = 'zk-input';
                    select.setAttribute('aria-label', 'Desde cuándo: ' + marca.etiqueta);
                    const alternativas = [['no_se', 'Desde: no sé'], ['conocido', 'Hace…']];
                    if (marca.codigo === 'sintoma.control_preventivo') alternativas.push(['no_aplica', 'No aplica']);
                    for (const [valor, texto] of alternativas) select.add(new Option(texto, valor));
                    select.value = dato.tiempo;
                    // Pruebas de usuario (2026-10-21): el tiempo se escribe en la unidad natural
                    // (una fiebre de 3 días) y viaja al servidor convertido a minutos, como siempre.
                    const cantidad = document.createElement('input');
                    cantidad.className = 'zk-input triage-captura__minutos';
                    cantidad.type = 'text';
                    cantidad.inputMode = 'numeric';
                    cantidad.maxLength = 4;
                    cantidad.placeholder = 'Cant.';
                    cantidad.dataset.caracteres = '0-9';
                    cantidad.setAttribute('aria-label', 'Cuánto tiempo hace: ' + marca.etiqueta);
                    cantidad.value = dato.cantidad ?? '';
                    const unidad = document.createElement('select');
                    unidad.className = 'zk-input triage-captura__unidad';
                    unidad.setAttribute('aria-label', 'Unidad de tiempo: ' + marca.etiqueta);
                    for (const [valor, texto] of [['minutos', 'minutos'], ['horas', 'horas'], ['dias', 'días'], ['semanas', 'semanas']]) unidad.add(new Option(texto, valor));
                    unidad.value = dato.unidad || 'horas';
                    const conocido = () => dato.tiempo === 'conocido';
                    cantidad.hidden = !conocido();
                    unidad.hidden = !conocido();
                    const cambiar = () => {
                        dato.tiempo = select.value;
                        cantidad.hidden = !conocido();
                        unidad.hidden = !conocido();
                        dato.cantidad = cantidad.value;
                        dato.unidad = unidad.value;
                        if (conocido() && /^\d{1,4}$/.test(cantidad.value)) dato.minutos = String(Number(cantidad.value) * MINUTOS_POR[unidad.value]);
                        else delete dato.minutos;
                        dato.grupo = grupo;
                        invalidar();
                        aviso.textContent = conocido() && !/^\d{1,4}$/.test(cantidad.value) ? 'Indica hace cuánto empezó cada síntoma.' : '';
                    };
                    dato.grupo = grupo;
                    select.addEventListener('change', cambiar);
                    cantidad.addEventListener('input', cambiar);
                    unidad.addEventListener('change', cambiar);
                    const controlesTiempo = document.createElement('div');
                    controlesTiempo.className = 'triage-captura__tiempo-controles';
                    controlesTiempo.append(select, cantidad, unidad);
                    fila.append(nombre, controlesTiempo);
                    contenedor.append(fila);
                }
            }
            invalidar();
        }

        const nombresNivel = { rojo: 'Rojo · crítico', naranja: 'Naranja · urgente', amarillo: 'Amarillo · prioritario', verde: 'Verde · no urgente' };
        function pintarResultado(r, mensaje = '') {
            salida.replaceChildren();
            if (!r && !mensaje) {
                salida.hidden = true;
                delete salida.dataset.nivel;
                return;
            }
            salida.hidden = false;
            if (r?.nivel) {
                salida.dataset.nivel = r.nivel;
                const nivel = document.createElement('strong');
                nivel.className = 'triage-captura__nivel';
                nivel.textContent = nombresNivel[r.nivel] || r.nivel;
                salida.append(nivel);
            } else {
                delete salida.dataset.nivel;
            }
            const texto = document.createElement('span');
            texto.textContent = mensaje;
            salida.append(texto);
            if (r?.telefono_urgencias) {
                const telefono = document.createElement('a');
                telefono.className = 'triage-captura__telefono';
                telefono.href = 'tel:' + r.telefono_urgencias;
                telefono.textContent = 'Urgencias: ' + r.telefono_urgencias;
                salida.append(telefono);
            }
            if (r?.contagio) {
                const contagio = document.createElement('span');
                contagio.className = 'triage-captura__contagio';
                contagio.textContent = 'Posible contagio: avisa al personal para preparar aislamiento.';
                salida.append(contagio);
            }
        }

        // El celo solo aplica a hembras sin esterilizar; si no se conoce la ficha, se deja visible.
        const campoCelo = buscar('[data-triage-celo-campo]');
        function actualizarCelo() {
            if (!campoCelo) return;
            const ficha = opciones.mascota ? opciones.mascota() : null;
            const aplica = !ficha || !ficha.sexo || (String(ficha.sexo).toLowerCase() === 'hembra' && String(ficha.esterilizado ?? '') !== '1');
            if (campoCelo.hidden === !aplica) return;
            campoCelo.hidden = !aplica;
            if (!aplica && buscar('[data-triage-celo]').value) {
                buscar('[data-triage-celo]').value = '';
                invalidar();
            }
        }

        const controles = {};
        for (const grupo of ['sintomas', 'exposiciones']) {
            controles[grupo] = raiz.ZookiSintomas.crear(buscar(`[data-triage-grupo="${grupo}"]`), {
                idMascota: () => opciones.seleccion().id_mascota,
                alCambiar: () => tiempos(),
                urlBusqueda: (texto, mascota) => raiz.ZookiSintomas.urlBusqueda(texto, mascota) + (grupo === 'exposiciones' ? '&exposiciones=1' : '')
            });
        }
        buscar('[data-triage-otro]').addEventListener('click', () => {
            if (!controles.sintomas.marcados().some(m => m.codigo === 'sintoma.otro_no_se')) controles.sintomas.poner([...controles.sintomas.marcados(), { codigo: 'sintoma.otro_no_se', etiqueta: 'Otro / no sé' }]);
        });
        buscar('[data-triage-vacunacion]').addEventListener('change', invalidar);
        buscar('[data-triage-celo]').addEventListener('input', invalidar);
        buscar('[data-triage-motivo]')?.addEventListener('input', evento => {
            const valor = evento.target.value.trim();
            aviso.textContent = !valor || valor.length > 255 ? 'Indica el motivo de la revisión (1 a 255 caracteres).' : '';
        });

        function datos() {
            const seleccion = opciones.seleccion();
            const forma = new FormData();
            for (const [campo, valor] of Object.entries(seleccion)) forma.append(campo, valor);
            for (const grupo of ['sintomas', 'exposiciones']) {
                let indice = 0;
                for (const episodio of episodios.values()) {
                    if (episodio.grupo !== grupo) continue;
                    for (const [campo, valor] of Object.entries(episodio)) {
                        if (!['grupo', 'cantidad', 'unidad'].includes(campo)) forma.append(`${grupo}[${indice}][${campo}]`, valor);
                    }
                    indice++;
                }
            }
            if (buscar('[data-triage-vacunacion]').value) forma.append('vacunacion', buscar('[data-triage-vacunacion]').value);
            if (buscar('[data-triage-celo]').value) forma.append('dias_desde_celo', buscar('[data-triage-celo]').value);
            return forma;
        }

        async function ejecutar(accion, forma, numero) {
            ocupada = true;
            calcular.disabled = true;
            comprobar.disabled = true;
            try {
                const r = await (await fetch('index.php?action=' + accion, { method: 'POST', body: forma })).json();
                estado.consultar(opciones.seleccion());
                if (accion === 'revisar_triage_agenda_ajax' && !r.success) {
                    // La revisión rechazada deja el cálculo vigente a la vista y el motivo bajo su campo.
                    estado.recibir(numero, previoRevision);
                    avisoRevision.textContent = r.message || 'No se pudo guardar la revisión.';
                    return;
                }
                if (!estado.recibir(numero, r)) return;
                const revision = buscar('[data-triage-revision]');
                // Quien puede revisar lo hace ahí mismo: no necesita «Comprobar si ya fue revisado».
                pintarResultado(r, revision && r.revision ? 'Confirma el nivel abajo para ver los horarios.' : (r.message || ''));
                comprobar.hidden = !r.revision || Boolean(revision);
                if (revision) {
                    if (r.revision && revision.hidden) {
                        // Al pedir revisión se propone el nivel calculado y el motivo empieza vacío.
                        const nivel = buscar('[data-triage-nivel]');
                        nivelCalculado = r.nivel || '';
                        if (nivel && r.nivel) nivel.value = r.nivel;
                        if (motivoRevision) motivoRevision.value = '';
                        avisoRevision.textContent = '';
                    }
                    const mostrar = r.revision && revision.hidden;
                    revision.hidden = !r.revision;
                    if (mostrar) {
                        // La columna del modal tiene su propio scroll: se lleva la revisión a la vista
                        // para que no quede escondida debajo y el motivo quede listo para escribir.
                        if (typeof revision.scrollIntoView === 'function') revision.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        if (aprobar && typeof aprobar.focus === 'function') aprobar.focus({ preventScroll: true });
                    }
                }
                opciones.alCambiar(r.success && r.reservable ? r : null);
            } catch (error) {
                estado.consultar(opciones.seleccion());
                if (estado.recibir(numero, null)) pintarResultado(null, 'No se pudo consultar. Reintenta la misma evaluación.');
            } finally {
                if (estado.esActual(numero)) {
                    ocupada = false;
                    calcular.disabled = false;
                    comprobar.disabled = false;
                }
                habilitarRevision();
            }
        }

        calcular.addEventListener('click', () => {
            if (ocupada) return;
            const seleccion = opciones.seleccion();
            const tiemposInvalidos = [...episodios.values()].some(e => e.tiempo === 'conocido' && (e.minutos === '' || e.minutos === undefined || !Number.isFinite(Number(e.minutos)) || Number(e.minutos) < 0));
            if (!seleccion.id_mascota || !seleccion.fecha || ('id_clinica' in seleccion && !seleccion.id_clinica) || episodios.size === 0 || tiemposInvalidos) {
                aviso.textContent = 'Completa mascota, clínica, fecha y al menos una marca con su tiempo.';
                return;
            }
            aviso.textContent = '';
            const forma = datos();
            forma.append('agenda', '1');
            forma.append('clave_operacion', comando.clave(forma));
            const numero = estado.iniciar(seleccion);
            opciones.alCambiar(null);
            ejecutar('calcular_triage_ajax', forma, numero);
        });

        comprobar.addEventListener('click', async () => {
            if (ocupada) return;
            const actual = estado.consultar(opciones.seleccion());
            if (!actual?.id_solicitud) return;
            const numero = estado.iniciar(opciones.seleccion());
            ocupada = true;
            comprobar.disabled = true;
            const parametros = new URLSearchParams({ action: 'listar_solicitudes_agenda_ajax', id_solicitud: actual.id_solicitud, id_clinica: opciones.seleccion().id_clinica || '' });
            try {
                const r = await (await fetch('index.php?' + parametros)).json();
                estado.consultar(opciones.seleccion());
                if (!estado.recibir(numero, r)) return;
                pintarResultado(r, r.message || '');
                comprobar.hidden = !r.revision;
                opciones.alCambiar(r.success && r.reservable ? r : null);
            } catch (error) {
                estado.consultar(opciones.seleccion());
                if (estado.recibir(numero, actual)) pintarResultado(actual, 'No se pudo comprobar la revisión.');
            } finally {
                if (estado.esActual(numero)) {
                    ocupada = false;
                    comprobar.disabled = false;
                }
            }
        });

        // Pruebas de usuario (2026-10-21): el aviso de la revisión va bajo su motivo, no arriba junto
        // a «Calcular», y Confirmar solo se habilita con motivo.
        const aprobar = buscar('[data-triage-aprobar]');
        const motivoRevision = buscar('[data-triage-motivo]');
        const avisoRevision = buscar('[data-triage-revision-aviso]') || aviso;
        // Confirmar el nivel calculado es un clic; el motivo solo se exige si el veterinario lo cambia.
        const nivelRevision = buscar('[data-triage-nivel]');
        const MOTIVO_CONFIRMA = 'Nivel calculado confirmado por el veterinario al agendar.';
        const cambiaNivel = () => Boolean(nivelRevision && nivelCalculado && nivelRevision.value !== nivelCalculado);
        const habilitarRevision = () => {
            if (!aprobar || !motivoRevision) return;
            const texto = motivoRevision.value.trim();
            aprobar.disabled = ocupada || texto.length > 255 || (cambiaNivel() && !texto);
            aprobar.textContent = cambiaNivel() ? 'Guardar nivel' : 'Confirmar nivel';
            motivoRevision.placeholder = cambiaNivel() ? 'Por qué cambias el nivel (obligatorio)' : 'Motivo (opcional)';
        };
        motivoRevision?.addEventListener('input', () => {
            avisoRevision.textContent = cambiaNivel() && !motivoRevision.value.trim() ? 'Escribe por qué cambias el nivel.' : '';
            habilitarRevision();
        });
        nivelRevision?.addEventListener('change', () => {
            avisoRevision.textContent = '';
            habilitarRevision();
        });
        habilitarRevision();
        aprobar?.addEventListener('click', () => {
            if (ocupada) return;
            const actual = estado.consultar(opciones.seleccion());
            const escrito = motivoRevision.value.trim();
            const motivo = escrito || (cambiaNivel() ? '' : MOTIVO_CONFIRMA);
            if (!actual?.revision || !motivo || motivo.length > 255) {
                avisoRevision.textContent = actual?.revision ? 'Escribe por qué cambias el nivel (máximo 255 caracteres).' : 'Vuelve a calcular el nivel antes de confirmar.';
                return;
            }
            avisoRevision.textContent = '';
            previoRevision = actual;
            const forma = new FormData();
            for (const [campo, valor] of Object.entries({ id_solicitud: actual.id_solicitud, huella: actual.huella, nivel: buscar('[data-triage-nivel]').value, motivo })) forma.append(campo, valor);
            forma.append('clave_operacion', comando.clave(forma));
            ejecutar('revisar_triage_agenda_ajax', forma, estado.iniciar(opciones.seleccion()));
        });

        capturaActual = {
            // RNF-16: cambiar el día invalida la evaluación conservando las marcas escritas.
            refrescarSeleccion: invalidar,
            resultado() {
                return estado.consultar(opciones.seleccion());
            },
            consulta() {
                actualizarCelo();
                const seleccion = opciones.seleccion();
                if (mascotaAnterior && mascotaAnterior !== seleccion.id_mascota) {
                    mascotaAnterior = seleccion.id_mascota;
                    controles.sintomas.limpiar();
                    controles.exposiciones.limpiar();
                }
                mascotaAnterior = seleccion.id_mascota;
                const r = estado.consultar(seleccion);
                return r?.success && r.reservable ? r.id_solicitud : '';
            },
            reiniciar() {
                controles.sintomas.limpiar();
                controles.exposiciones.limpiar();
                comando.reiniciar();
                invalidar();
            }
        };
        actualizarCelo();
        return capturaActual;
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { crearEstado };
    else raiz.ZookiTriageAgenda = { crear, crearEstado };
})(globalThis);
