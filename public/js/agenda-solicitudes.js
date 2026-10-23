/** C4: bandeja y decisiones de la clínica activa; solo el veterinario resuelve. */
document.addEventListener('DOMContentLoaded', () => {
    const raiz = document.querySelector('[data-agenda-solicitudes]');
    if (!raiz) return;
    const lista = raiz.querySelector('[data-solicitudes-lista]');
    const aviso = raiz.querySelector('[data-solicitudes-error]');
    const filtro = raiz.querySelector('[data-solicitudes-filtro]');
    let turno = 0;
    function elemento(tipo, texto) {
        const nodo = document.createElement(tipo);
        if (texto) nodo.textContent = texto;
        return nodo;
    }
    const NIVELES = { verde: 'Verde', amarillo: 'Amarillo', naranja: 'Naranja', rojo: 'Rojo' };
    const VACUNAS = { completa: 'al día', incompleta: 'incompletas', desconocida: 'no se sabe' };
    function insignia(nivel, titulo) {
        const nodo = elemento('span', NIVELES[nivel] || nivel);
        nodo.className = 'ah-nivel';
        nodo.dataset.nivel = nivel;
        nodo.title = titulo;
        return nodo;
    }
    function episodio(e, etiquetas) {
        const nombre = etiquetas[e.codigo] || e.codigo.split('.').at(-1).replaceAll('_', ' ');
        if (e.tiempo === 'conocido') return nombre + ' (hace ' + e.minutos + ' min)';
        if (e.tiempo === 'no_se') return nombre + ' (desde: no se sabe)';
        return nombre;
    }
    function edad(meses) {
        if (meses === null || meses === undefined || meses === '') return '';
        const n = Number(meses);
        if (n < 12) return n + (n === 1 ? ' mes' : ' meses');
        const anios = Math.floor(n / 12);
        return anios + (anios === 1 ? ' año' : ' años') + (n % 12 ? ' ' + (n % 12) + ' m' : '');
    }
    async function cargar() {
        const actual = ++turno;
        aviso.textContent = '';
        try {
            const r = await (await fetch('index.php?action=listar_solicitudes_agenda_ajax&historial=' + filtro.value)).json();
            if (turno !== actual) return;
            if (!r.success) {
                aviso.textContent = r.message;
                return;
            }
            lista.replaceChildren();
            const etiquetas = r.etiquetas || {};
            for (const caso of r.solicitudes) {
                // Pulido de interfaz: tarjeta compacta (nombre + nivel, síntomas legibles, datos del paciente).
                const tarjeta = elemento('article');
                tarjeta.className = 'ah-item';
                const cabeza = elemento('div');
                cabeza.className = 'ah-item__head';
                cabeza.append(elemento('strong', caso.nombre));
                if (caso.nivel_calculado) cabeza.append(insignia(caso.nivel_calculado, 'Calculado'));
                if (caso.nivel_aprobado && caso.nivel_aprobado !== caso.nivel_calculado) {
                    if (caso.nivel_calculado) cabeza.append(elemento('span', '→'));
                    cabeza.append(insignia(caso.nivel_aprobado, 'Revisado'));
                }
                if (caso.nivel_aprobado) {
                    const marca = elemento('span', 'Revisado');
                    marca.className = 'ah-marca';
                    cabeza.append(marca);
                }
                tarjeta.append(cabeza);
                // Contextos antiguos o de llegada pueden no traer todos los bloques: se leen con tolerancia.
                let contexto = {};
                try { contexto = JSON.parse(caso.contexto_triage) || {}; } catch (_) { contexto = {}; }
                const entrada = contexto.entrada || {};
                const datosPaciente = contexto.paciente || {};
                const declarados = contexto.declarados || {};
                const episodios = [...(entrada.sintomas || []), ...(entrada.exposiciones || [])].filter(e => e && typeof e.codigo === 'string');
                const sintomas = elemento('p', episodios.map(e => episodio(e, etiquetas)).join(' · ') || (contexto.motivo ? 'Ingreso presencial: ' + contexto.motivo : 'Sin síntomas registrados'));
                sintomas.className = 'ah-item__texto';
                const paciente = elemento('p', [edad(datosPaciente.edad_meses), datosPaciente.sexo, 'Vacunas: ' + (VACUNAS[declarados.vacunacion] || 'sin dato')].filter(Boolean).join(' · '));
                paciente.className = 'ah-item__meta';
                tarjeta.append(sintomas);
                if (contexto.entrada) tarjeta.append(paciente);
                const mensaje = elemento('small');
                mensaje.className = 'ah-error';
                if (filtro.value === '1') {
                    const ver = elemento('button', 'Ver historial');
                    ver.type = 'button';
                    ver.className = 'zk-btn zk-btn--sec zk-btn--mini';
                    ver.addEventListener('click', async () => {
                        const respuesta = await (await fetch('index.php?action=listar_solicitudes_agenda_ajax&id_solicitud=' + caso.id_solicitud)).json();
                        mensaje.className = 'ah-item__meta ah-historial';
                        mensaje.textContent = respuesta.success ? respuesta.historial.map(h => h.fecha_hora.slice(0, 16) + ' · ' + h.descripcion).join('\n') : respuesta.message;
                    });
                    tarjeta.append(ver, mensaje);
                } else if (raiz.dataset.puedeRevisar === '1') {
                    const form = elemento('form');
                    form.className = 'ah-item__form';
                    const nivel = elemento('select');
                    for (const color of ['verde', 'amarillo', 'naranja', 'rojo']) nivel.add(new Option(NIVELES[color], color));
                    nivel.value = caso.nivel_calculado;
                    nivel.setAttribute('aria-label', 'Nivel revisado');
                    const motivo = elemento('input');
                    motivo.maxLength = 255;
                    motivo.dataset.caracteres = '\\p{L}\\p{M}\\p{N}\\p{P}\\p{S} ';
                    motivo.dataset.espacios = 'normalizar';
                    motivo.placeholder = 'Motivo de la revisión';
                    motivo.setAttribute('aria-label', 'Motivo de revisión');
                    const aprobar = elemento('button', 'Confirmar');
                    aprobar.type = 'submit';
                    aprobar.className = 'zk-btn zk-btn--mini';
                    aprobar.disabled = true;
                    const clave = zookiCrearComandoAgenda();
                    let enviando = false;
                    motivo.addEventListener('input', () => {
                        aprobar.disabled = enviando || !motivo.value.trim();
                        mensaje.textContent = motivo.value.trim() ? '' : 'El motivo es obligatorio.';
                    });
                    form.append(nivel, motivo, aprobar);
                    form.addEventListener('submit', async evento => {
                        evento.preventDefault();
                        if (enviando || !motivo.value.trim()) return;
                        const datos = new URLSearchParams({ id_solicitud: caso.id_solicitud, huella: caso.huella_entrada, nivel: nivel.value, motivo: motivo.value.trim() });
                        datos.set('clave_operacion', clave.clave(datos));
                        enviando = true;
                        aprobar.disabled = true;
                        try {
                            const respuesta = await (await fetch('index.php?action=revisar_triage_agenda_ajax', { method: 'POST', body: datos })).json();
                            if (!respuesta.success) mensaje.textContent = respuesta.message;
                            else await cargar();
                        } catch (error) {
                            mensaje.textContent = 'Respuesta no recibida. Reintenta la misma revisión.';
                        } finally {
                            enviando = false;
                            aprobar.disabled = !motivo.value.trim();
                        }
                    });
                    tarjeta.append(form, mensaje);
                }
                lista.append(tarjeta);
            }
        } catch (error) {
            if (turno === actual) aviso.textContent = 'No se pudieron consultar las solicitudes.';
        }
    }
    raiz.querySelector('[data-solicitudes-actualizar]').addEventListener('click', cargar);
    filtro.addEventListener('change', cargar);
    // D: el panel de pestañas avisa al mostrarse (antes era un <details>).
    raiz.addEventListener('agenda:mostrar', cargar);
});

const comandosOrdenC4 = new Map();
const ordenEnCursoC4 = new Set();
document.addEventListener('input', evento => {
    const form = evento.target.closest('[data-reordenar-naranja]');
    if (!form) return;
    const valido = /^[1-9]\d*$/.test(form.elements.posicion.value) && Boolean(form.elements.motivo.value.trim());
    form.querySelector('[type="submit"]').disabled = !valido || ordenEnCursoC4.has(form.dataset.cita);
    form.querySelector('[data-orden-error]').textContent = valido ? '' : 'Indica posición positiva y motivo.';
});
document.addEventListener('submit', async evento => {
    const form = evento.target.closest('[data-reordenar-naranja]');
    if (!form) return;
    evento.preventDefault();
    const id = form.dataset.cita;
    if (ordenEnCursoC4.has(id) || !form.elements.motivo.value.trim() || !/^[1-9]\d*$/.test(form.elements.posicion.value)) return;
    if (!comandosOrdenC4.has(id)) comandosOrdenC4.set(id, zookiCrearComandoAgenda());
    const datos = new FormData(form);
    datos.set('id_cita', id);
    datos.set('clave_operacion', comandosOrdenC4.get(id).clave(datos));
    const boton = form.querySelector('[type="submit"]');
    ordenEnCursoC4.add(id);
    boton.disabled = true;
    try {
        const respuesta = await (await fetch('index.php?action=reordenar_sobrecupos_ajax', { method: 'POST', body: datos })).json();
        if (!respuesta.success) form.querySelector('[data-orden-error]').textContent = respuesta.message;
        else {
            calendarInstance.refetchEvents();
            zookiToast(respuesta.message, 'success');
        }
    } catch (error) {
        form.querySelector('[data-orden-error]').textContent = 'Respuesta no recibida. Reintenta el mismo cambio.';
    } finally {
        ordenEnCursoC4.delete(id);
        boton.disabled = false;
    }
});
