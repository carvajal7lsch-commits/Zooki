/** RE-4.19: acciones de presencia junto a la cita, con decisiones y acuses idempotentes de C8. */
(function (global) {
    'use strict';

    function errorCampo(nombre, valor, requerido = false) {
        const texto = String(valor ?? '').trim();
        if (!texto) return requerido ? 'Este campo es obligatorio.' : '';
        if (nombre === 'motivo' && (Array.from(texto).length > 255 || /\p{C}/u.test(texto))) return 'Motivo de 1 a 255 caracteres.';
        if (['segundos', 'id_veterinario'].includes(nombre) && (!/^[1-9]\d*$/.test(texto) || Number(texto) > 2147483647)) return 'Indica un entero positivo.';
        if (nombre === 'hora' && !/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(texto)) return 'Selecciona una hora válida.';
        if (nombre === 'fecha' && (!/^\d{4}-\d\d-\d\d$/.test(texto) || !Number.isFinite(Date.parse(texto)))) return 'Selecciona una fecha válida.';
        return '';
    }

    function accionesLlegada(cita, contexto) {
        if (!contexto.esVeterinario || String(cita.id_veterinario) !== String(contexto.idVeterinario)
            || cita.fecha !== contexto.hoy || !['pendiente', 'confirmada'].includes(cita.estado)) return [];
        const tolerancia = Number(cita.tolerancia_llegada_min);
        const pactada = new Date(cita.fecha + 'T' + cita.hora).getTime();
        const llegada = cita.hora_llegada ? new Date(cita.hora_llegada.replace(' ', 'T')).getTime() : contexto.ahora.getTime();
        const tarde = Number.isInteger(tolerancia) && tolerancia >= 0 && llegada > pactada + tolerancia * 60000;
        if (!cita.hora_llegada) return tarde ? ['registrar', 'no_asistio'] : ['registrar'];
        return tarde ? ['resolver', 'ajustar'] : ['ajustar'];
    }

    function desdeEvento(evento) {
        return { ...evento.extendedProps, id_cita: evento.id, fecha: toLocalDateStr(evento.start),
            hora: evento.start.toTimeString().slice(0, 8), mascota: evento.extendedProps.mascota_nombre };
    }

    const ETIQUETAS = { registrar: 'Registrar llegada', resolver: 'Resolver llegada', no_asistio: 'No asistió', ajustar: 'Ajustar nivel' };
    function botones(evento, omitir = []) {
        if (evento.extendedProps.tipo !== 'cita') return '';
        const acciones = accionesLlegada(desdeEvento(evento), { esVeterinario: isUsuarioVeterinario(),
            idVeterinario: getUsuarioId(), hoy: new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Bogota' }).format(new Date()), ahora: new Date() });
        const contenido = acciones.filter(accion => !omitir.includes(accion)).map(accion =>
            `<button type="button" class="cal-btn cal-btn--ghost" data-llegada-accion="${accion}" data-llegada-cita="${Number(evento.id)}">${ETIQUETAS[accion]}</button>`).join('');
        return contenido ? `<div class="agenda-detail__secondary agenda-cita-llegada">${contenido}</div>` : '';
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { errorCampo, accionesLlegada };
    if (typeof document === 'undefined') return;
    global.ZookiLlegadasCita = { botones, enfocar };
    function enfocar() {
        const parametros = new URLSearchParams(global.location.search);
        const id = parametros.get('llegada_cita');
        if (!/^[1-9]\d*$/.test(id || '') || !calendarInstance?.getEventById(id)) return;
        parametros.delete('llegada_cita');
        global.history.replaceState(null, '', `${global.location.pathname}?${parametros}${global.location.hash}`);
        seleccionarDia(calendarInstance.getEventById(id).start);
        document.querySelector('[data-agenda-vista="citas"]')?.click();
        mostrarDetalleCita(id);
        document.dispatchEvent(new CustomEvent('agenda:resolver-llegada', { detail: { idCita: id } }));
    }
    document.addEventListener('DOMContentLoaded', () => {
        const raiz = document.querySelector('[data-llegadas-cita]');
        if (!raiz) return;
        let escribiendo = false;
        const NIVELES = { verde: 'Verde', amarillo: 'Amarillo', naranja: 'Naranja', rojo: 'Rojo' };
        const hora = valor => String(valor || '').slice(0, 5);
        function texto(padre, tipo, contenido) {
            const nodo = document.createElement(tipo);
            nodo.textContent = contenido;
            padre.append(nodo);
            return nodo;
        }
        function campo(form, nombre, etiqueta, opciones = null, requerido = true, tipo = 'text') {
            const label = texto(form, 'label', etiqueta);
            const nodo = document.createElement(opciones ? 'select' : 'input');
            nodo.name = nombre;
            nodo.required = requerido;
            if (opciones) {
                texto(nodo, 'option', 'Elige').value = '';
                opciones.forEach(([valor, titulo]) => {
                    texto(nodo, 'option', titulo).value = valor;
                });
            } else {
                nodo.type = tipo;
                nodo.maxLength = nombre === 'motivo' ? 255 : 10;
                nodo.dataset.caracteres = nombre === 'segundos' ? '0-9' : '\\P{C}';
                nodo.dataset.espacios = 'normalizar';
                if (nombre === 'segundos') nodo.inputMode = 'numeric';
            }
            label.append(nodo);
            const error = texto(label, 'span', '');
            error.dataset.errorCampo = nombre;
            error.setAttribute('aria-live', 'polite');
            return nodo;
        }

        function validar(form) {
            let valido = true;
            form.querySelectorAll('[name]').forEach(nodo => {
                if (nodo.disabled) return;
                const motivoCombinado = nodo.name === 'motivo' && typeof form.obtenerMotivo === 'function';
                const valor = motivoCombinado ? form.obtenerMotivo() : nodo.value;
                const error = motivoCombinado && !valor
                    ? 'Elige un motivo de revisión o escribe un detalle.'
                    : errorCampo(nodo.name, valor, motivoCombinado || nodo.required);
                const salida = form.querySelector(`[data-error-campo="${nodo.name}"]`);
                // El error se muestra cuando el campo ya se tocó o se intentó enviar, no al aparecer.
                if (salida) salida.textContent = nodo.dataset.tocado === '1' || form.dataset.intento === '1' ? error : '';
                nodo.setAttribute('aria-invalid', error ? 'true' : 'false');
                nodo.setCustomValidity(error);
                valido = valido && !error;
            });
            form.querySelector('[type="submit"]').disabled = escribiendo || !valido;
            return valido;
        }

        function selectorNivel(form) {
            const bloque = texto(form, 'fieldset', '');
            bloque.className = 'llegada-nivel';
            texto(bloque, 'legend', 'Nivel');
            const nivel = document.createElement('select');
            nivel.name = 'nivel';
            nivel.required = true;
            nivel.hidden = true;
            texto(nivel, 'option', 'Elige').value = '';
            bloque.append(nivel);
            const opciones = texto(bloque, 'div', '');
            opciones.className = 'llegada-niveles';
            Object.entries(NIVELES).forEach(([valor, etiqueta]) => {
                texto(nivel, 'option', etiqueta).value = valor;
                const boton = texto(opciones, 'button', etiqueta);
                boton.type = 'button';
                boton.dataset.llegadaNivel = valor;
                boton.setAttribute('aria-pressed', 'false');
                boton.addEventListener('click', () => {
                    nivel.value = valor;
                    opciones.querySelectorAll('button').forEach(otro => otro.setAttribute('aria-pressed', String(otro === boton)));
                    nivel.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
            const error = texto(bloque, 'span', '');
            error.dataset.errorCampo = 'nivel';
            error.setAttribute('aria-live', 'polite');
        }

        function formulario(padre, titulo, accion, datos, construir = () => {}, despues = null, secundario = false) {
            const form = document.createElement('form');
            form.className = 'ah-item__form';
            form.noValidate = true;
            padre.append(form);
            const comando = global.zookiCrearComandoAgenda();
            construir(form);
            const boton = texto(form, 'button', titulo);
            boton.type = 'submit';
            boton.className = secundario ? 'zk-btn zk-btn--sec zk-btn--mini' : 'zk-btn zk-btn--mini';
            const mensaje = texto(form, 'p', '');
            mensaje.className = 'ah-error';
            mensaje.setAttribute('role', 'status');
            const tocar = evento => {
                if (evento.target.name) evento.target.dataset.tocado = '1';
                validar(form);
            };
            form.addEventListener('input', tocar);
            form.addEventListener('change', tocar);
            form.addEventListener('submit', async evento => {
                evento.preventDefault();
                form.dataset.intento = '1';
                if (escribiendo || !validar(form)) return;
                const cuerpo = new FormData(form);
                if (form.obtenerMotivo) cuerpo.set('motivo', form.obtenerMotivo());
                Object.entries(datos).forEach(([k, v]) => cuerpo.set(k, v));
                const token = raiz.querySelector('[data-llegadas-csrf] input');
                cuerpo.set(token.name, token.value);
                cuerpo.set('id_clinica', raiz.dataset.clinica);
                cuerpo.set('clave_operacion', comando.clave(cuerpo));
                escribiendo = true;
                boton.disabled = true;
                try {
                    const destino = accion.endsWith('_ajax') ? accion : `${accion}_llegada_ajax`;
                    const r = await (await fetch(`index.php?action=${destino}`, { method: 'POST', body: cuerpo })).json();
                    if (!r.success) throw new Error(r.message || 'No se pudo guardar.');
                    global.zookiToast?.(r.message || 'Llegada actualizada.');
                    comando.reiniciar();
                    if (despues) despues(r.resultado, form);
                    else {
                        const dialogo = form.closest('.llegada-dialogo');
                        if (dialogo) {
                            dialogo.dataset.resuelto = '1';
                            dialogo.close();
                        }
                    }
                    actualizarAgendaTrasCambio({ idCita: datos.id_cita, estado: accion === 'marcar_no_asistio_ajax' ? 'no_asistio' : undefined });
                } catch (error) {
                    mensaje.textContent = error.message;
                } finally {
                    escribiendo = false;
                    document.querySelectorAll('.llegada-dialogo form').forEach(validar);
                    if (form.isConnected) validar(form);
                }
            });
            validar(form);
            return form;
        }

        function abrirLlegada(cita, titulo, construir) {
            const dialogo = texto(document.body, 'dialog', '');
            dialogo.className = 'zk-dialogo llegada-dialogo';
            dialogo.id = 'resolverLlegada-' + cita.id_cita;
            dialogo.setAttribute('aria-labelledby', dialogo.id + '-titulo');
            const cabecera = texto(dialogo, 'header', '');
            cabecera.className = 'zk-dialogo__head';
            const encabezado = texto(cabecera, 'h3', titulo + ': ' + (cita.mascota || 'paciente'));
            encabezado.id = dialogo.id + '-titulo';
            const cerrar = texto(cabecera, 'button', '×');
            cerrar.type = 'button';
            cerrar.className = 'zk-dialogo__cerrar';
            cerrar.setAttribute('data-cerrar-dialogo', '');
            cerrar.setAttribute('aria-label', 'Cerrar');
            const cuerpo = texto(dialogo, 'div', '');
            cuerpo.className = 'zk-dialogo__cuerpo';
            construir(cuerpo);
            const pie = texto(dialogo, 'footer', '');
            pie.className = 'zk-dialogo__pie';
            const volver = texto(pie, 'button', 'Volver a Citas');
            volver.type = 'button';
            volver.className = 'zk-btn zk-btn--sec';
            volver.setAttribute('data-cerrar-dialogo', '');
            dialogo.addEventListener('close', () => {
                dialogo.remove();
            }, { once: true });
            global.zookiAbrirDialogo(dialogo.id);
            return cuerpo;
        }

        function decisiones(padre, id, opciones) {
            if (!opciones) return;
            const estado = texto(padre, 'p', opciones.dentro_tolerancia ? 'La llegada está registrada dentro de la tolerancia. Elige cómo continuar la atención.' : 'La llegada está registrada. La hora reservada ya pasó; elige otro horario para atenderlo.');
            estado.className = 'ah-item__meta' + (opciones.dentro_tolerancia ? '' : ' ah-retraso');
            const grupo = texto(padre, 'div', '');
            grupo.className = 'ah-decisiones';
            if (opciones.dentro_tolerancia) {
                formulario(grupo, 'Atender completo', 'decidir', { id_cita: id, decision: 'completa' });
                if (opciones.reducida) formulario(grupo, 'Atender reducido', 'decidir', { id_cita: id, decision: 'reducida' }, form => {
                    const entrada = campo(form, 'segundos', 'Duración (segundos)');
                    entrada.maxLength = String(opciones.segundos_restantes).length;
                }, null, true);
            }
            if (opciones.siguiente_hueco?.length) formulario(grupo, 'Siguiente horario disponible', 'decidir', { id_cita: id, decision: 'siguiente_hueco' }, () => {}, null, true);
            else if (!opciones.dentro_tolerancia) texto(grupo, 'p', 'No quedan horarios disponibles hoy. Puedes reprogramar para otro día.').className = 'ah-item__meta';
            formulario(grupo, 'Reprogramar', 'decidir', { id_cita: id, decision: 'reprogramar' }, form => {
                campo(form, 'fecha', 'Fecha', null, true, 'date');
                campo(form, 'hora', 'Hora', null, true, 'time').step = '1';
                const veterinario = texto(form, 'input', '');
                veterinario.type = 'hidden';
                veterinario.name = 'id_veterinario';
                veterinario.value = String(raiz.dataset.veterinario);
            }, null, true);
            (opciones.otro_veterinario || []).forEach(v => {
                formulario(grupo, `Con ${v.nombre} a las ${hora(v.hora)}`, 'decidir', { id_cita: id, decision: 'otro_veterinario', id_veterinario: v.id_veterinario, fecha: new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Bogota' }).format(new Date()), hora: v.hora }, () => {}, null, true);
            });
        }

        function resolver(id, registrar = false) {
            if (escribiendo || document.querySelector('.llegada-dialogo[open]')) return;
            const evento = calendarInstance?.getEventById(String(id));
            if (!evento) return;
            const cita = desdeEvento(evento);
            const cuerpo = abrirLlegada(cita, 'Resolver llegada', () => {});
            const form = formulario(cuerpo, 'Registrar llegada', 'registrar', { id_cita: cita.id_cita }, () => {}, resultado => {
                cuerpo.replaceChildren();
                decisiones(cuerpo, cita.id_cita, resultado.opciones);
            });
            // Abrir una cita ausente no acredita presencia: el usuario registra la llegada explícitamente.
            if (registrar || cita.hora_llegada) form.requestSubmit();
        }
        document.addEventListener('agenda:resolver-llegada', evento => resolver(evento.detail.idCita));
        document.addEventListener('click', evento => {
            const boton = evento.target.closest('[data-llegada-accion]');
            if (!boton || escribiendo) return;
            const id = boton.dataset.llegadaCita;
            if (boton.dataset.llegadaAccion === 'no_asistio') {
                marcarNoAsistio(id);
                return;
            }
            if (boton.dataset.llegadaAccion !== 'ajustar') {
                resolver(id, boton.dataset.llegadaAccion === 'registrar');
                return;
            }
            const cita = desdeEvento(calendarInstance.getEventById(id));
            abrirLlegada(cita, 'Ajustar nivel', cuerpo => {
                formulario(cuerpo, 'Guardar nivel', 'ajustar', { id_cita: id }, form => {
                    selectorNivel(form);
                    const motivo = campo(form, 'motivo', 'Motivo de revisión');
                    form.obtenerMotivo = global.ZookiMotivosRevision?.crear(motivo) || (() => motivo.value.trim());
                    // RE-4.18: el motivo rápido es suficiente; el detalle es opcional.
                    motivo.required = false;
                });
            });
        });
        enfocar();
    });
})(globalThis);
