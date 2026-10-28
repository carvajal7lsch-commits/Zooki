/** C8: presencia separada de atención, decisiones explícitas y acuses idempotentes. */
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

    if (typeof module !== 'undefined' && module.exports) module.exports = { errorCampo };
    if (typeof document === 'undefined') return;
    document.addEventListener('DOMContentLoaded', () => {
        const raiz = document.querySelector('[data-llegadas-agenda]');
        if (!raiz) return;
        const aviso = raiz.querySelector('[data-llegadas-aviso]');
        let lectura = 0;
        let escribiendo = false;

        function texto(padre, tipo, contenido) {
            const nodo = document.createElement(tipo);
            nodo.textContent = contenido;
            padre.append(nodo);
            return nodo;
        }

        const NIVELES = { verde: 'Verde', amarillo: 'Amarillo', naranja: 'Naranja', rojo: 'Rojo' };
        const hora = valor => String(valor || '').slice(0, 5);
        const fechaHora = valor => {
            const m = String(valor || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/);
            return m ? `${m[3]}/${m[2]} ${m[4]}` : String(valor || '');
        };

        function insignia(padre, nivel) {
            const nodo = texto(padre, 'span', NIVELES[nivel] || nivel || 'Sin nivel');
            nodo.className = 'ah-nivel';
            nodo.dataset.nivel = nivel || '';
            return nodo;
        }

        // Pulido de interfaz: tarjeta compacta con nombre, nivel y datos en una línea.
        function item(lista, titulo, nivel, meta = '') {
            const tarjeta = texto(lista, 'article', '');
            tarjeta.className = 'ah-item';
            const cabeza = texto(tarjeta, 'div', '');
            cabeza.className = 'ah-item__head';
            texto(cabeza, 'strong', titulo);
            if (nivel) insignia(cabeza, nivel);
            if (meta) texto(tarjeta, 'p', meta).className = 'ah-item__meta';
            return tarjeta;
        }

        function campo(form, nombre, etiqueta, opciones = null, requerido = true, tipo = 'text') {
            const label = texto(form, 'label', etiqueta);
            const nodo = document.createElement(opciones ? 'select' : 'input');
            nodo.name = nombre;
            nodo.required = requerido;
            if (opciones) {
                texto(nodo, 'option', 'Elige').value = '';
                opciones.forEach(([valor, titulo]) => { texto(nodo, 'option', titulo).value = valor; });
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
                const error = errorCampo(nodo.name, nodo.value, nodo.required);
                const salida = form.querySelector(`[data-error-campo="${nodo.name}"]`);
                // El error se muestra cuando el campo ya se tocó o se intentó enviar, no al aparecer.
                if (salida) salida.textContent = nodo.dataset.tocado === '1' || form.dataset.intento === '1' ? error : '';
                nodo.setAttribute('aria-invalid', error ? 'true' : 'false');
                valido = valido && !error;
            });
            form.querySelector('[type="submit"]').disabled = escribiendo || !valido;
            return valido;
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
            const tocar = evento => { if (evento.target.name) evento.target.dataset.tocado = '1'; validar(form); };
            form.addEventListener('input', tocar);
            form.addEventListener('change', tocar);
            form.addEventListener('submit', async evento => {
                evento.preventDefault();
                form.dataset.intento = '1';
                if (escribiendo || !validar(form)) return;
                const cuerpo = new FormData(form);
                Object.entries(datos).forEach(([k, v]) => cuerpo.set(k, v));
                const token = raiz.querySelector('[data-llegadas-csrf] input');
                cuerpo.set(token.name, token.value);
                cuerpo.set('id_clinica', raiz.dataset.clinica);
                cuerpo.set('clave_operacion', comando.clave(cuerpo));
                escribiendo = true;
                ++lectura;
                raiz.querySelectorAll('button').forEach(b => { b.disabled = true; });
                try {
                    const r = await (await fetch(`index.php?action=${accion}_llegada_ajax`, { method: 'POST', body: cuerpo })).json();
                    if (!r.success) throw new Error(r.message || 'No se pudo guardar.');
                    aviso.textContent = r.message;
                    comando.reiniciar();
                    if (despues) despues(r.resultado, form);
                    else await recargar();
                    global.dispatchEvent(new Event('agenda:urgencia'));
                } catch (error) {
                    mensaje.textContent = error.message;
                } finally {
                    escribiendo = false;
                    raiz.querySelectorAll('button').forEach(b => { b.disabled = false; });
                    raiz.querySelectorAll('form').forEach(validar);
                }
            });
            validar(form);
            return form;
        }

        function decisiones(padre, id, opciones) {
            if (!opciones) return;
            const minutos = Math.max(0, Math.round(opciones.segundos_restantes / 60));
            const estado = texto(padre, 'p', `Quedan ${minutos} min de tiempo clínico · ${opciones.dentro_tolerancia ? 'dentro de la tolerancia' : 'tolerancia superada'}`);
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
            formulario(grupo, 'Siguiente hueco', 'decidir', { id_cita: id, decision: 'siguiente_hueco' }, () => {}, null, true);
            formulario(grupo, 'Reprogramar', 'decidir', { id_cita: id, decision: 'reprogramar' }, form => {
                campo(form, 'fecha', 'Fecha', null, true, 'date');
                campo(form, 'hora', 'Hora', null, true, 'time').step = '1';
                campo(form, 'id_veterinario', 'Veterinario', [[String(global.__vetLlegada), 'Veterinario asignado']]);
            }, null, true);
            (opciones.otro_veterinario || []).forEach(v => {
                formulario(grupo, `Con ${v.nombre} a las ${hora(v.hora)}`, 'decidir', { id_cita: id, decision: 'otro_veterinario', id_veterinario: v.id_veterinario, fecha: new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Bogota' }).format(new Date()), hora: v.hora }, () => {}, null, true);
            });
        }

        async function recargar() {
            const turno = ++lectura;
            try {
                const r = await (await fetch('index.php?action=listar_llegadas_ajax')).json();
                if (turno !== lectura) return;
                if (!r.success) throw new Error(r.message || 'No se pudo consultar.');
                for (const nombre of ['citas', 'solicitudes', 'espera', 'historial']) raiz.querySelector(`[data-llegadas-${nombre}]`).replaceChildren();
                r.resultado.citas.forEach(c => {
                    const tarjeta = item(raiz.querySelector('[data-llegadas-citas]'), `${hora(c.hora)} · ${c.mascota || 'Cita ' + c.id_cita}`, c.prioridad, c.origen === 'llegada_sin_reserva' ? 'Llegada sin reserva' : 'Con reserva');
                    formulario(tarjeta, 'Registrar llegada', 'registrar', { id_cita: c.id_cita }, () => {}, (resultado, form) => {
                        form.remove();
                        decisiones(tarjeta, c.id_cita, resultado.opciones);
                    });
                    const ajuste = texto(tarjeta, 'details', '');
                    ajuste.className = 'ah-mini';
                    texto(ajuste, 'summary', 'Ajustar nivel');
                    formulario(ajuste, 'Guardar nivel', 'ajustar', { id_cita: c.id_cita }, form => {
                        campo(form, 'nivel', 'Nivel', ['verde', 'amarillo', 'naranja', 'rojo'].map(n => [n, NIVELES[n]]));
                        campo(form, 'motivo', 'Motivo');
                    }, null, true);
                });
                r.resultado.solicitudes.forEach(s => {
                    const tarjeta = item(raiz.querySelector('[data-llegadas-solicitudes]'), s.mascota || `Caso ${s.id_solicitud}`, s.nivel_aprobado || s.nivel_calculado);
                    tarjeta.classList.add('ah-item--fila');
                    formulario(tarjeta, 'Registrar llegada', 'presentar', { id_solicitud: s.id_solicitud });
                });
                r.resultado.espera.forEach(s => {
                    const tarjeta = item(raiz.querySelector('[data-llegadas-espera]'), s.mascota || `Caso ${s.id_solicitud}`, s.nivel_aprobado || s.nivel_calculado, `Llegó ${fechaHora(s.fecha_llegada)}`);
                    const boton = texto(tarjeta, 'button', 'Ver espacios');
                    boton.type = 'button';
                    boton.className = 'zk-btn zk-btn--sec zk-btn--mini';
                    boton.addEventListener('click', async () => {
                        const actual = lectura;
                        boton.disabled = true;
                        try {
                            const respuesta = await (await fetch(`index.php?action=listar_llegadas_ajax&id_solicitud=${s.id_solicitud}`)).json();
                            if (actual !== lectura || !tarjeta.isConnected) return;
                            if (!respuesta.success) throw new Error(respuesta.message || 'No se pudo consultar.');
                            boton.remove();
                            const alternativas = respuesta.resultado;
                            if (alternativas.horas.length) formulario(tarjeta, 'Asignar', 'asignar', { id_solicitud: s.id_solicitud }, form => campo(form, 'hora', 'Espacio compatible', alternativas.horas.map(h => [h, hora(h)])));
                            else texto(tarjeta, 'p', 'Sin hueco completo hoy: sigue en espera.').className = 'ah-item__meta';
                            alternativas.otros.forEach(v => { texto(tarjeta, 'p', `Libre con ${v.nombre} a las ${hora(v.hora)}.`).className = 'ah-item__meta'; });
                        } catch (error) {
                            if (actual === lectura && tarjeta.isConnected) texto(tarjeta, 'p', error.message).className = 'ah-error';
                            boton.disabled = false;
                        }
                    });
                });
                r.resultado.historial.forEach(h => {
                    let antes = {};
                    let despues = {};
                    try { antes = JSON.parse(h.datos_anteriores) || {}; despues = JSON.parse(h.datos_nuevos) || {}; } catch (_) { /* registro antiguo */ }
                    const linea = texto(raiz.querySelector('[data-llegadas-historial]'), 'p', `${fechaHora(h.fecha_hora)} · Cita ${h.registro_id} · ${NIVELES[antes.nivel_anterior] || antes.nivel_anterior || '—'} → ${NIVELES[despues.nivel_ajustado] || despues.nivel_ajustado || '—'}${despues.motivo ? ' · ' + despues.motivo : ''}`);
                    linea.className = 'ah-item__meta';
                });
            } catch (error) {
                if (turno === lectura) aviso.textContent = error.message;
            }
        }

        global.__vetLlegada = raiz.dataset.veterinario;
        raiz.querySelector('[data-llegadas-actualizar]').addEventListener('click', () => { if (!escribiendo) recargar(); });
        recargar();
    });
})(globalThis);
