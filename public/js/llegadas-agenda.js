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
                if (salida) salida.textContent = error;
                nodo.setAttribute('aria-invalid', error ? 'true' : 'false');
                valido = valido && !error;
            });
            form.querySelector('[type="submit"]').disabled = escribiendo || !valido;
            return valido;
        }

        function formulario(padre, titulo, accion, datos, construir = () => {}, despues = null) {
            const form = document.createElement('form');
            form.className = 'perfil-card';
            padre.append(form);
            const comando = global.zookiCrearComandoAgenda();
            construir(form);
            const boton = texto(form, 'button', titulo);
            boton.type = 'submit';
            boton.className = 'btn-secondary';
            const mensaje = texto(form, 'p', '');
            mensaje.setAttribute('role', 'status');
            form.addEventListener('input', () => validar(form));
            form.addEventListener('change', () => validar(form));
            form.addEventListener('submit', async evento => {
                evento.preventDefault();
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
            texto(padre, 'p', `Tiempo clínico restante: ${opciones.segundos_restantes} segundos. ${opciones.dentro_tolerancia ? 'Dentro de tolerancia.' : 'Tolerancia superada.'}`);
            if (opciones.dentro_tolerancia) {
                formulario(padre, 'Atender servicio completo y propagar retraso', 'decidir', { id_cita: id, decision: 'completa' });
                if (opciones.reducida) formulario(padre, 'Atender con duración reducida explícita', 'decidir', { id_cita: id, decision: 'reducida' }, form => {
                    const entrada = campo(form, 'segundos', 'Duración activa decidida (segundos)');
                    entrada.maxLength = String(opciones.segundos_restantes).length;
                });
            }
            formulario(padre, 'Atender en siguiente hueco', 'decidir', { id_cita: id, decision: 'siguiente_hueco' });
            formulario(padre, 'Reprogramar', 'decidir', { id_cita: id, decision: 'reprogramar' }, form => {
                campo(form, 'fecha', 'Fecha', null, true, 'date');
                campo(form, 'hora', 'Hora', null, true, 'time').step = '1';
                campo(form, 'id_veterinario', 'Veterinario', [[String(global.__vetLlegada), 'Veterinario asignado']]);
            });
            (opciones.otro_veterinario || []).forEach(v => {
                formulario(padre, `Otro veterinario: ${v.nombre}, ${v.hora}`, 'decidir', { id_cita: id, decision: 'otro_veterinario', id_veterinario: v.id_veterinario, fecha: new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Bogota' }).format(new Date()), hora: v.hora });
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
                    const tarjeta = texto(raiz.querySelector('[data-llegadas-citas]'), 'section', '');
                    tarjeta.className = 'perfil-card';
                    texto(tarjeta, 'h4', `${c.hora} · Cita ${c.id_cita} · ${c.prioridad} · ${c.origen === 'llegada_sin_reserva' ? 'Llegada sin reserva' : 'Reserva'}`);
                    formulario(tarjeta, 'Registrar llegada y ver opciones', 'registrar', { id_cita: c.id_cita }, () => {}, (resultado, form) => {
                        form.remove();
                        decisiones(tarjeta, c.id_cita, resultado.opciones);
                    });
                    formulario(tarjeta, 'Ajustar nivel con motivo', 'ajustar', { id_cita: c.id_cita }, form => {
                        campo(form, 'nivel', 'Nivel ajustado', ['verde', 'amarillo', 'naranja', 'rojo'].map(n => [n, n]));
                        campo(form, 'motivo', 'Motivo veterinario');
                    });
                });
                r.resultado.solicitudes.forEach(s => {
                    formulario(raiz.querySelector('[data-llegadas-solicitudes]'), `Registrar llegada del caso ${s.id_solicitud} (${s.nivel_aprobado || s.nivel_calculado})`, 'presentar', { id_solicitud: s.id_solicitud });
                });
                r.resultado.espera.forEach(s => {
                    const tarjeta = texto(raiz.querySelector('[data-llegadas-espera]'), 'section', '');
                    texto(tarjeta, 'h4', `${s.nivel_aprobado || s.nivel_calculado} · ${s.fecha_llegada} · Caso ${s.id_solicitud}`);
                    const boton = texto(tarjeta, 'button', 'Ver espacios y alternativas');
                    boton.type = 'button';
                    boton.addEventListener('click', async () => {
                        const actual = lectura;
                        boton.disabled = true;
                        try {
                            const respuesta = await (await fetch(`index.php?action=listar_llegadas_ajax&id_solicitud=${s.id_solicitud}`)).json();
                            if (actual !== lectura || !tarjeta.isConnected) return;
                            if (!respuesta.success) throw new Error(respuesta.message || 'No se pudo consultar.');
                            boton.remove();
                            const alternativas = respuesta.resultado;
                            if (alternativas.horas.length) formulario(tarjeta, 'Asignar cita de llegada', 'asignar', { id_solicitud: s.id_solicitud }, form => campo(form, 'hora', 'Espacio compatible', alternativas.horas.map(h => [h, h])));
                            else texto(tarjeta, 'p', 'Sin hueco completo; permanece en espera. Reevalúa otra fecha o veterinario antes de asignar.');
                            alternativas.otros.forEach(v => texto(tarjeta, 'p', `Disponible ${v.nombre}: ${v.hora}. Evalúa con ese veterinario para asignar.`));
                        } catch (error) {
                            if (actual === lectura && tarjeta.isConnected) texto(tarjeta, 'p', error.message);
                            boton.disabled = false;
                        }
                    });
                });
                r.resultado.historial.forEach(h => texto(raiz.querySelector('[data-llegadas-historial]'), 'p', `${h.fecha_hora} · Cita ${h.registro_id} · Autor ${h.id_usuario} · ${h.datos_anteriores} → ${h.datos_nuevos}`));
            } catch (error) {
                if (turno === lectura) aviso.textContent = error.message;
            }
        }

        global.__vetLlegada = raiz.dataset.veterinario;
        raiz.querySelector('[data-llegadas-actualizar]').addEventListener('click', () => { if (!escribiendo) recargar(); });
        recargar();
    });
})(globalThis);
