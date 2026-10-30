/* RNF-16: misma tabla y BlockPicker de la clínica, sin cambiar el contrato de franjas. */
(function (global) {
    'use strict';
    const PERIODOS = ['morning', 'afternoon'];
    const DEFECTO = { morning: ['08:00', '12:00'], afternoon: ['14:00', '18:00'] };
    function adaptarDia(originales) {
        const bloques = { morning: [], afternoon: [] };
        const ordenadas = originales.map(f => ({ ...f })).sort((a, b) => a.hora_inicio.localeCompare(b.hora_inicio));
        let especial = false;
        for (const f of ordenadas) {
            if (!/^\d{2}:\d{2}(:00)?$/.test(f.hora_inicio) || !/^\d{2}:\d{2}(:00)?$/.test(f.hora_fin) || f.hora_inicio < '06:00' || f.hora_fin > '21:00:00' || f.hora_fin <= f.hora_inicio) especial = true;
            for (const periodo of PERIODOS) {
                const inicio = f.hora_inicio.slice(0, 5);
                const fin = f.hora_fin.slice(0, 5);
                const a = periodo === 'morning' ? inicio : inicio < '12:00' ? '12:00' : inicio;
                const b = periodo === 'morning' && fin > '12:00' ? '12:00' : fin;
                if (a >= b || (periodo === 'morning' && a >= '12:00')) continue;
                const previo = bloques[periodo].at(-1);
                if (previo && previo[1] === a) previo[1] = b;
                else bloques[periodo].push([a, b]);
            }
        }
        if (PERIODOS.some(p => bloques[p].length > 1)) especial = true;
        return { originales: originales.map(f => ({ ...f })), bloques, especial, editado: false };
    }
    function crear(raiz, opciones = {}) {
        const tabla = raiz.querySelector('[data-franjas]');
        const dias = new Map();
        let bloqueado = false;
        let apertura = null;
        let selectores = [];
        const filas = () => Array.from(tabla.querySelectorAll('[data-dia-semana]'));
        const controles = (fila, periodo) => ({
            activo: fila.querySelector('.' + periodo + '-bloque-activo'),
            inicio: fila.querySelector('.' + periodo + '-inicio'),
            fin: fila.querySelector('.' + periodo + '-fin'),
        });
        function actualizar() {
            filas().forEach(fila => {
                const dato = dias.get(Number(fila.dataset.diaSemana));
                const activo = fila.querySelector('[data-dia-activo]');
                const horario = apertura?.find(f => Number(f.dia_semana) === Number(fila.dataset.diaSemana));
                const cerrado = apertura !== null && (!horario || !Number(horario.activo));
                activo.disabled = bloqueado || dato?.especial || cerrado;
                if (cerrado) activo.checked = false;
                fila.classList.toggle('dia-cerrado', !activo.checked);
                let minutos = 0;
                PERIODOS.forEach(periodo => {
                    const c = controles(fila, periodo);
                    const noAbre = apertura !== null && (!horario || !Number(horario['bloque_' + periodo + '_activo']));
                    if (noAbre) c.activo.checked = false;
                    c.activo.disabled = bloqueado || !activo.checked || dato?.especial || noAbre;
                    if (horario && !cerrado && !noAbre) {
                        c.inicio.min = String(horario['bloque_' + periodo + '_inicio']).slice(0, 5);
                        c.inicio.max = String(horario['bloque_' + periodo + '_fin']).slice(0, 5);
                        c.fin.min = c.inicio.min;
                        c.fin.max = c.inicio.max;
                    }
                    const deshabilitado = c.activo.disabled || !c.activo.checked;
                    c.inicio.disabled = deshabilitado;
                    c.fin.disabled = deshabilitado;
                    fila.querySelector('[data-turno="' + periodo + '"]').classList.toggle('bloque-inactivo', deshabilitado);
                    const boton = c.fin.parentElement.querySelector('.block-picker-display');
                    if (boton) boton.disabled = deshabilitado;
                    const a = c.inicio.value.split(':').map(Number);
                    const b = c.fin.value.split(':').map(Number);
                    if (activo.checked && c.activo.checked) minutos += (b[0] - a[0]) * 60 + b[1] - a[1];
                });
                fila.querySelector('[data-horas]').textContent = activo.checked ? (minutos / 60).toLocaleString('es-CO', { maximumFractionDigits: 2 }) + ' h' : 'Libre';
            });
        }
        function pintar(franjas) {
            selectores.forEach(p => p.destroy());
            selectores = [];
            filas().forEach(fila => {
                const numero = Number(fila.dataset.diaSemana);
                const dato = adaptarDia(franjas.filter(f => Number(f.dia_semana) === numero));
                dias.set(numero, dato);
                fila.querySelector('[data-dia-activo]').checked = dato.originales.length > 0;
                const aviso = fila.querySelector('[data-horario-especial]');
                aviso.hidden = !dato.especial;
                aviso.textContent = dato.especial ? 'Horario especial conservado: ' + dato.originales.map(f => 'de ' + f.hora_inicio + ' a ' + f.hora_fin).join('; ') + '. No se modifica desde los dos turnos.' : '';
                PERIODOS.forEach(periodo => {
                    const c = controles(fila, periodo);
                    c.activo.checked = dato.bloques[periodo].length > 0;
                    [c.inicio.value, c.fin.value] = dato.bloques[periodo][0] || DEFECTO[periodo];
                    selectores.push(new BlockPicker(c.inicio, c.fin, { minuteInterval: 1, period: periodo, compactDisplay: raiz.closest('[data-ajustes-horarios]') ? 'rango' : true }));
                });
            });
            actualizar();
        }
        function franjas() {
            return filas().flatMap(fila => {
                const numero = Number(fila.dataset.diaSemana);
                const dato = dias.get(numero);
                if (!dato) return [];
                // Guardar otro día nunca redondea ni reescribe un horario histórico.
                if (apertura !== null && !apertura.some(f => Number(f.dia_semana) === numero && Number(f.activo))) return [];
                if (!dato.editado || dato.especial) return dato.originales.map(f => ({ ...f }));
                if (!fila.querySelector('[data-dia-activo]').checked) return [];
                return PERIODOS.flatMap(periodo => {
                    const c = controles(fila, periodo);
                    return c.activo.checked ? [{ dia_semana: String(numero), hora_inicio: c.inicio.value + ':00', hora_fin: c.fin.value + ':00' }] : [];
                });
            });
        }
        tabla.addEventListener('change', evento => {
            const fila = evento.target.closest('[data-dia-semana]');
            if (!fila || bloqueado) return;
            const dato = dias.get(Number(fila.dataset.diaSemana));
            if (dato.especial) return;
            dato.editado = true;
            actualizar();
            opciones.alCambiar?.();
        });
        const modal = raiz.querySelector('.horario-varios');
        const botonVarios = raiz.querySelector('[data-horario-varios]');
        let selectoresModal = [];
        botonVarios.addEventListener('click', () => {
            if (bloqueado) return;
            modal.querySelector('[id$="mdError"]').textContent = '';
            modal.showModal();
            modal.querySelectorAll('.md-day-check').forEach(c => {
                c.disabled = apertura !== null && !apertura.some(f => Number(f.dia_semana) === Number(c.value) && Number(f.activo));
                if (c.disabled) c.checked = false;
            });
            if (!selectoresModal.length) PERIODOS.forEach(periodo => {
                const sufijo = periodo === 'morning' ? 'Morning' : 'Afternoon';
                selectoresModal.push(new BlockPicker(modal.querySelector('[id$="md' + sufijo + 'Inicio"]'), modal.querySelector('[id$="md' + sufijo + 'Fin"]'), { minuteInterval: 1, period: periodo }));
            });
            limitarModal();
        });
        modal.addEventListener('click', evento => {
            if (evento.target.closest('[data-cerrar-modal]')) modal.close();
        });
        function limitarModal() {
            const elegidos = Array.from(modal.querySelectorAll('.md-day-check:checked:not(:disabled)'), c => Number(c.value));
            selectoresModal.forEach((p, indice) => {
                const sufijo = indice === 0 ? 'Morning' : 'Afternoon';
                const activo = modal.querySelector('[id$="md' + sufijo + 'Activo"]');
                if (apertura !== null) {
                    const periodo = PERIODOS[indice];
                    const diasAbiertos = apertura.filter(f => Number(f.activo) && (!elegidos.length || elegidos.includes(Number(f.dia_semana))));
                    const turnos = diasAbiertos.filter(f => Number(f['bloque_' + periodo + '_activo']));
                    activo.disabled = !turnos.length || turnos.length !== diasAbiertos.length;
                    if (activo.disabled) activo.checked = false;
                    if (turnos.length) {
                        const inicio = turnos.map(f => String(f['bloque_' + periodo + '_inicio']).slice(0, 5)).sort().at(-1);
                        const fin = turnos.map(f => String(f['bloque_' + periodo + '_fin']).slice(0, 5)).sort()[0];
                        p.startInput.min = p.endInput.min = inicio;
                        p.startInput.max = p.endInput.max = fin;
                    }
                }
                const deshabilitado = activo.disabled || !activo.checked;
                p.startInput.disabled = deshabilitado;
                p.endInput.disabled = deshabilitado;
                p.display.disabled = deshabilitado;
            });
        }
        modal.addEventListener('change', limitarModal);
        modal.querySelector('[id$="btnAplicarMultiDia"]').addEventListener('click', () => {
            const elegidos = Array.from(modal.querySelectorAll('.md-day-check:checked:not(:disabled)'), c => Number(c.value));
            const error = modal.querySelector('[id$="mdError"]');
            if (!elegidos.length) { error.textContent = 'Selecciona al menos un día.'; return; }
            if (elegidos.some(d => dias.get(d).especial)) { error.textContent = 'El horario especial se conserva. Excluye ese día para aplicar los turnos.'; return; }
            const nuevos = [];
            for (const periodo of PERIODOS) {
                const sufijo = periodo === 'morning' ? 'Morning' : 'Afternoon';
                if (!modal.querySelector('[id$="md' + sufijo + 'Activo"]').checked) continue;
                const inicio = modal.querySelector('[id$="md' + sufijo + 'Inicio"]').value;
                const fin = modal.querySelector('[id$="md' + sufijo + 'Fin"]').value;
                if (!inicio || !fin || fin <= inicio) { error.textContent = 'Completa las horas y elige un fin posterior al inicio.'; return; }
                nuevos.push({ hora_inicio: inicio + ':00', hora_fin: fin + ':00' });
            }
            if (!nuevos.length) { error.textContent = 'Activa al menos un turno.'; return; }
            if (apertura !== null && elegidos.some(d => nuevos.some(f => !apertura.some(h => Number(h.dia_semana) === d && Number(h.activo) && PERIODOS.some(p => Number(h['bloque_' + p + '_activo']) && f.hora_inicio >= h['bloque_' + p + '_inicio'] && f.hora_fin <= h['bloque_' + p + '_fin']))))) {
                error.textContent = 'Elige solo las horas de apertura de la clínica.';
                return;
            }
            const actuales = franjas().filter(f => !elegidos.includes(Number(f.dia_semana)));
            elegidos.forEach(d => nuevos.forEach(f => actuales.push({ dia_semana: String(d), ...f })));
            pintar(actuales);
            opciones.alCambiar?.();
            modal.close();
        });
        return { pintar, franjas, apertura(valor) { apertura = Array.isArray(valor) ? valor : null; }, bloquear(valor) { bloqueado = valor; actualizar(); } };
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { adaptarDia };
    else global.HorarioTrabajo = { crear };
})(typeof window !== 'undefined' ? window : globalThis);
