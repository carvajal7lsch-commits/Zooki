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
            for (const caso of r.solicitudes) {
                const tarjeta = elemento('section');
                tarjeta.append(elemento('h4', caso.nombre + ' · Calculado: ' + caso.nivel_calculado + (caso.nivel_aprobado ? ' · Aprobado: ' + caso.nivel_aprobado : '')));
                const contexto = JSON.parse(caso.contexto_triage);
                const episodios = [...(contexto.entrada.sintomas || []), ...(contexto.entrada.exposiciones || [])];
                tarjeta.append(elemento('p', episodios.map(e => e.codigo.split('.').at(-1).replaceAll('_', ' ') + ': ' + (e.tiempo === 'conocido' ? e.minutos + ' minutos' : e.tiempo.replaceAll('_', ' '))).join('; ')));
                tarjeta.append(elemento('p', 'Edad: ' + (contexto.paciente.edad_meses ?? 'sin dato') + ' meses · Vacunación: ' + (contexto.declarados.vacunacion || 'sin dato') + ' · Sexo: ' + (contexto.paciente.sexo || 'sin dato')));
                const mensaje = elemento('small');
                mensaje.className = 'cuenta-validacion';
                if (filtro.value === '1') {
                    const ver = elemento('button', 'Ver historial');
                    ver.type = 'button';
                    ver.addEventListener('click', async () => {
                        const respuesta = await (await fetch('index.php?action=listar_solicitudes_agenda_ajax&id_solicitud=' + caso.id_solicitud)).json();
                        mensaje.textContent = respuesta.success ? respuesta.historial.map(h => h.fecha_hora + ' · ' + h.descripcion + ' · ' + h.datos_nuevos).join('\n') : respuesta.message;
                    });
                    tarjeta.append(ver, mensaje);
                } else if (raiz.dataset.puedeRevisar === '1') {
                    const form = elemento('form');
                    const nivel = elemento('select');
                    for (const color of ['verde', 'amarillo', 'naranja', 'rojo']) nivel.add(new Option(color, color));
                    nivel.value = caso.nivel_calculado;
                    const motivo = elemento('input');
                    motivo.maxLength = 255;
                    motivo.dataset.caracteres = '\\p{L}\\p{M}\\p{N}\\p{P}\\p{S} ';
                    motivo.dataset.espacios = 'normalizar';
                    motivo.setAttribute('aria-label', 'Motivo de revisión');
                    const aprobar = elemento('button', 'Confirmar revisión');
                    aprobar.type = 'submit';
                    aprobar.disabled = true;
                    const clave = zookiCrearComandoAgenda();
                    let enviando = false;
                    motivo.addEventListener('input', () => {
                        aprobar.disabled = enviando || !motivo.value.trim();
                        mensaje.textContent = motivo.value.trim() ? '' : 'El motivo es obligatorio.';
                    });
                    form.append(nivel, motivo, aprobar, mensaje);
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
                    tarjeta.append(form);
                }
                lista.append(tarjeta);
            }
            if (!r.solicitudes.length) aviso.textContent = 'No hay solicitudes en esta vista.';
        } catch (error) {
            if (turno === actual) aviso.textContent = 'No se pudieron consultar las solicitudes.';
        }
    }
    raiz.querySelector('[data-solicitudes-actualizar]').addEventListener('click', cargar);
    filtro.addEventListener('change', cargar);
    raiz.addEventListener('toggle', () => { if (raiz.open) cargar(); });
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
