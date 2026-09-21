/**
 * HU-43 — Configuración del horario de atención.
 *
 * Una fila por día (.horario-dia[data-dia]) con dos bloques, mañana y tarde.
 * Cada cambio se valida y se guarda solo; el resumen de la semana se
 * recalcula al instante. Las horas se eligen con BlockPicker (time-picker.js).
 */
(() => {
    const DEFECTO = { morning: ['08:00', '12:00'], afternoon: ['14:00', '18:00'] };
    const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    const PERIODOS = ['morning', 'afternoon'];

    let pickers = [];

    const minutos = hhmm => {
        const [h, m] = (hhmm || '').split(':').map(Number);
        return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
    };
    let pickersModal = [];

    const fila = dia => document.querySelector(`.horario-dia[data-dia="${dia}"]`);
    const toggleDia = dia => fila(dia).querySelector('.dia-activo');
    const toggleBloque = (dia, periodo) => fila(dia).querySelector(`.${periodo}-bloque-activo`);
    const entrada = (dia, periodo, extremo) => fila(dia).querySelector(`.${periodo}-${extremo}`);

    // ── Estado del guardado ──
    const estado = document.getElementById('horariosEstado');
    const TEXTOS_ESTADO = {
        listo: ['fa-cloud', 'Los cambios se guardan solos'],
        guardando: ['fa-spinner fa-spin', 'Guardando…'],
        guardado: ['fa-check-circle', 'Cambios guardados'],
        invalido: ['fa-exclamation-circle', 'Corrige los horarios marcados'],
        error: ['fa-exclamation-triangle', 'No se pudo guardar'],
    };
    let temporizadorEstado = null;

    function pintarEstado(clave) {
        const [icono, texto] = TEXTOS_ESTADO[clave];
        estado.dataset.estado = clave;
        estado.innerHTML = `<i class="fas ${icono}"></i> <span>${texto}</span>`;
        clearTimeout(temporizadorEstado);
        if (clave === 'guardado') temporizadorEstado = setTimeout(() => pintarEstado('listo'), 3000);
    }

    // ── Selectores de hora ──
    function iniciarPickers() {
        pickers.forEach(p => p.destroy());
        pickers = [];
        for (let dia = 1; dia <= 7; dia++) {
            PERIODOS.forEach(periodo => {
                pickers.push(new BlockPicker(entrada(dia, periodo, 'inicio'), entrada(dia, periodo, 'fin'), {
                    minuteInterval: 15,
                    period: periodo,
                    onTimeChange: () => { actualizarResumen(); guardar(); },
                }));
            });
        }
    }

    // ── Pintado de filas ──
    function pintarDia(dia) {
        const abierto = toggleDia(dia).checked;
        fila(dia).classList.toggle('inactivo', !abierto);
        PERIODOS.forEach(periodo => {
            document.getElementById(`bloque-${periodo}-${dia}`)
                .classList.toggle('bloque-inactivo', !toggleBloque(dia, periodo).checked);
        });
    }

    // Rango permitido de cada periodo, en minutos (el mismo de chk_morning y chk_afternoon).
    const RANGO = { morning: [6 * 60, 12 * 60], afternoon: [12 * 60, 21 * 60] };

    /**
     * Un bloque encendido sin horas válidas para su periodo toma el horario por
     * defecto. Cubre los días que estaban cerrados con horas en 00:00.
     */
    function completarHoras(dia, periodo) {
        const ini = entrada(dia, periodo, 'inicio');
        const fin = entrada(dia, periodo, 'fin');
        const [min, max] = RANGO[periodo];
        const mi = minutos(ini.value);
        const mf = minutos(fin.value);
        if (mi !== null && mf !== null && mi >= min && mf <= max && mf > mi) return false;
        [ini.value, fin.value] = DEFECTO[periodo];
        return true;
    }

    function alCambiarDia(dia) {
        // Abrir un día sin bloques activos enciende los dos, con horas válidas.
        let rehacerPickers = false;
        if (toggleDia(dia).checked && !PERIODOS.some(p => toggleBloque(dia, p).checked)) {
            PERIODOS.forEach(p => {
                toggleBloque(dia, p).checked = true;
                rehacerPickers = completarHoras(dia, p) || rehacerPickers;
            });
        }
        if (rehacerPickers) iniciarPickers();
        pintarDia(dia);
        actualizarResumen();
        guardar();
    }

    function alCambiarBloque(dia, periodo) {
        if (toggleBloque(dia, periodo).checked && completarHoras(dia, periodo)) iniciarPickers();
        // Con al menos un bloque el día queda abierto; sin ninguno, cerrado.
        toggleDia(dia).checked = PERIODOS.some(p => toggleBloque(dia, p).checked);
        pintarDia(dia);
        actualizarResumen();
        guardar();
    }

    // ── Resumen ──

    function minutosBloque(dia, periodo) {
        if (!toggleBloque(dia, periodo).checked) return 0;
        const ini = minutos(entrada(dia, periodo, 'inicio').value);
        const fin = minutos(entrada(dia, periodo, 'fin').value);
        return ini !== null && fin !== null && fin > ini ? fin - ini : 0;
    }

    function formatoDuracion(total) {
        const h = Math.floor(total / 60);
        const m = total % 60;
        if (!h) return `${m} min`;
        return m ? `${h} h ${m} min` : `${h} h`;
    }

    function formatoHora(hhmm) {
        const [h, m] = hhmm.split(':').map(Number);
        const h12 = h % 12 === 0 ? 12 : h % 12;
        return `${h12}:${String(m).padStart(2, '0')} ${h < 12 ? 'a. m.' : 'p. m.'}`;
    }

    function actualizarResumen() {
        let diasAbiertos = 0;
        let totalSemana = 0;

        for (let dia = 1; dia <= 7; dia++) {
            const abierto = toggleDia(dia).checked;
            const totalDia = abierto ? PERIODOS.reduce((s, p) => s + minutosBloque(dia, p), 0) : 0;
            fila(dia).querySelector('[data-horas]').textContent = abierto ? formatoDuracion(totalDia) : '—';
            if (abierto) diasAbiertos++;
            totalSemana += totalDia;
        }

        document.getElementById('resumenDias').textContent = diasAbiertos;
        document.getElementById('resumenHoras').textContent = formatoDuracion(totalSemana);

        // Hoy, según el día de la semana del navegador (domingo = 7).
        const hoy = new Date().getDay() || 7;
        const abiertoHoy = toggleDia(hoy).checked;
        const tarjeta = document.getElementById('resumenHoy');
        tarjeta.dataset.abierto = abiertoHoy ? 'si' : 'no';
        document.getElementById('resumenHoyTitulo').textContent = `Hoy · ${DIAS[hoy]}`;
        document.getElementById('resumenHoyEstado').textContent = abiertoHoy ? 'Abierto' : 'Cerrado';
        document.getElementById('resumenHoyDetalle').textContent = abiertoHoy
            ? PERIODOS.filter(p => toggleBloque(hoy, p).checked && entrada(hoy, p, 'inicio').value && entrada(hoy, p, 'fin').value)
                .map(p => `${formatoHora(entrada(hoy, p, 'inicio').value)} – ${formatoHora(entrada(hoy, p, 'fin').value)}`)
                .join('  ·  ')
            : 'La agenda no ofrece citas hoy.';
    }

    // ── Validación (mismas reglas que el servidor) ──
    function validar() {
        let valido = true;

        for (let dia = 1; dia <= 7; dia++) {
            const bloques = {};
            PERIODOS.forEach(periodo => {
                const error = document.getElementById(`error-${periodo}-${dia}`);
                const inputs = document.getElementById(`bloque-${periodo}-${dia}`).querySelector('.bloque-inputs');
                error.textContent = '';
                inputs.classList.remove('has-error');
                bloques[periodo] = {
                    activo: toggleDia(dia).checked && toggleBloque(dia, periodo).checked,
                    ini: entrada(dia, periodo, 'inicio').value,
                    fin: entrada(dia, periodo, 'fin').value,
                    marcar(texto) { error.textContent = texto; inputs.classList.add('has-error'); valido = false; },
                };
            });

            const m = bloques.morning;
            PERIODOS.forEach(p => {
                const b = bloques[p];
                if (b.activo && (!b.ini || !b.fin)) b.marcar('Elige la hora de inicio y de fin.');
            });

            if (m.activo && m.ini && m.fin) {
                const [hi] = m.ini.split(':').map(Number);
                const [hf, mf] = m.fin.split(':').map(Number);
                if (hi < 6 || hi > 11) m.marcar('La mañana empieza entre 6:00 y 11:45 a. m.');
                else if (hf < 6 || hf > 12 || (hf === 12 && mf > 0)) m.marcar('La mañana termina a más tardar a las 12:00 p. m.');
                else if (m.fin <= m.ini) m.marcar('La hora de fin debe ser mayor que la de inicio.');
            }

            const t = bloques.afternoon;
            if (t.activo && t.ini && t.fin) {
                const [hi] = t.ini.split(':').map(Number);
                const [hf] = t.fin.split(':').map(Number);
                if (hi < 12 || hi >= 21 || hf < 12 || hf >= 21) t.marcar('La tarde va entre las 12:00 p. m. y las 9:00 p. m.');
                else if (t.fin <= t.ini) t.marcar('La hora de fin debe ser mayor que la de inicio.');
            }

            if (m.activo && t.activo && m.fin && t.ini && m.fin > t.ini) {
                m.marcar('Los bloques no deben superponerse.');
                t.marcar('Los bloques no deben superponerse.');
            }
        }
        return valido;
    }

    // ── Guardado ──
    let guardadoPendiente = null;

    function guardar() {
        clearTimeout(guardadoPendiente);
        if (!validar()) {
            pintarEstado('invalido');
            return;
        }
        // Agrupa cambios seguidos (varios switches en pocos milisegundos) en un solo envío.
        guardadoPendiente = setTimeout(enviar, 250);
    }

    async function enviar() {
        const datos = new FormData();
        for (let dia = 1; dia <= 7; dia++) {
            const abierto = toggleDia(dia).checked;
            datos.append(`horarios[${dia}][activo]`, abierto ? 1 : 0);
            PERIODOS.forEach(periodo => {
                // Un día cerrado no tiene bloques activos: la base exige horas
                // válidas a todo bloque activo (chk_morning / chk_afternoon).
                datos.append(`horarios[${dia}][${periodo}_activo]`, abierto && toggleBloque(dia, periodo).checked ? 1 : 0);
                // Las horas se envían aunque el bloque esté apagado, para conservarlas.
                // Vacías van como cadena vacía: null viajaba como el texto "null".
                datos.append(`horarios[${dia}][${periodo}_inicio]`, entrada(dia, periodo, 'inicio').value || '');
                datos.append(`horarios[${dia}][${periodo}_fin]`, entrada(dia, periodo, 'fin').value || '');
            });
        }

        pintarEstado('guardando');
        try {
            const res = await fetch('index.php?action=guardar_horarios_clinica_ajax', { method: 'POST', body: datos });
            const r = await res.json();
            if (r.success) {
                pintarEstado('guardado');
            } else {
                pintarEstado('error');
                zookiAviso(r.message || 'No se pudo guardar el horario.');
            }
        } catch (e) {
            console.error('Horarios:', e);
            pintarEstado('error');
        }
    }

    // ── Carga inicial ──
    async function cargar() {
        try {
            const r = await (await fetch('index.php?action=get_horarios_clinica_ajax')).json();
            if (!r.success) return;
            r.horarios.forEach(h => {
                const dia = Number(h.dia_semana);
                if (!fila(dia)) return;
                toggleDia(dia).checked = Number(h.activo) === 1;
                toggleBloque(dia, 'morning').checked = Number(h.bloque_morning_activo) !== 0;
                toggleBloque(dia, 'afternoon').checked = Number(h.bloque_afternoon_activo) !== 0;
                entrada(dia, 'morning', 'inicio').value = (h.bloque_morning_inicio || '').substring(0, 5);
                entrada(dia, 'morning', 'fin').value = (h.bloque_morning_fin || '').substring(0, 5);
                entrada(dia, 'afternoon', 'inicio').value = (h.bloque_afternoon_inicio || '').substring(0, 5);
                entrada(dia, 'afternoon', 'fin').value = (h.bloque_afternoon_fin || '').substring(0, 5);
            });
        } catch (e) {
            console.error('Horarios:', e);
            zookiAviso('No se pudo cargar el horario de atención.');
        }
        for (let dia = 1; dia <= 7; dia++) pintarDia(dia);
        iniciarPickers();
        actualizarResumen();
        validar();
    }

    // ── Restaurar ──
    async function restaurar() {
        const ok = await zookiConfirmar(
            'Todos los días vuelven al horario inicial de la clínica. Esta acción reemplaza tu configuración actual.',
            '¿Restaurar el horario por defecto?',
            'Sí, restaurar'
        );
        if (!ok) return;
        try {
            const r = await (await fetch('index.php?action=restaurar_horarios_defecto_ajax', { method: 'POST' })).json();
            if (!r.success) {
                zookiAviso(r.message || 'No se pudo restaurar el horario.');
                return;
            }
            await cargar();
            zookiToast('Horario restaurado.');
        } catch (e) {
            console.error('Horarios:', e);
            zookiAviso('No se pudo restaurar el horario.');
        }
    }

    // ── Modal: varios días ──
    const modal = document.getElementById('modalMultiDia');

    function abrirModal() {
        modal.querySelectorAll('.md-day-check').forEach(c => { c.checked = false; });
        document.getElementById('mdError').textContent = '';
        PERIODOS.forEach(periodo => {
            const s = periodo === 'morning' ? 'Morning' : 'Afternoon';
            document.getElementById(`md${s}Activo`).checked = true;
            document.getElementById(`md${s}Section`).classList.remove('bloque-inactivo');
            [document.getElementById(`md${s}Inicio`).value, document.getElementById(`md${s}Fin`).value] = DEFECTO[periodo];
        });
        modal.hidden = false;

        pickersModal.forEach(p => p.destroy());
        pickersModal = PERIODOS.map(periodo => {
            const s = periodo === 'morning' ? 'Morning' : 'Afternoon';
            return new BlockPicker(document.getElementById(`md${s}Inicio`), document.getElementById(`md${s}Fin`), { minuteInterval: 15, period: periodo });
        });
    }

    const cerrarModal = () => { modal.hidden = true; };

    function aplicarVariosDias() {
        const error = document.getElementById('mdError');
        error.textContent = '';

        const dias = [...modal.querySelectorAll('.md-day-check:checked')].map(c => Number(c.value));
        if (!dias.length) { error.textContent = 'Selecciona al menos un día.'; return; }

        const valores = {};
        PERIODOS.forEach(periodo => {
            const s = periodo === 'morning' ? 'Morning' : 'Afternoon';
            valores[periodo] = {
                activo: document.getElementById(`md${s}Activo`).checked,
                ini: document.getElementById(`md${s}Inicio`).value,
                fin: document.getElementById(`md${s}Fin`).value,
            };
        });
        const m = valores.morning;
        const t = valores.afternoon;

        if (!m.activo && !t.activo) { error.textContent = 'Activa al menos un bloque (mañana o tarde).'; return; }
        if (m.activo) {
            if (!m.ini || !m.fin) { error.textContent = 'Completa las horas del bloque de la mañana.'; return; }
            if (m.fin <= m.ini) { error.textContent = 'Mañana: la hora de fin debe ser mayor que la de inicio.'; return; }
            const [hi] = m.ini.split(':').map(Number);
            const [hf, mf] = m.fin.split(':').map(Number);
            if (hi < 6 || hi > 11) { error.textContent = 'Mañana: empieza entre 6:00 y 11:45 a. m.'; return; }
            if (hf < 6 || hf > 12 || (hf === 12 && mf > 0)) { error.textContent = 'Mañana: termina a más tardar a las 12:00 p. m.'; return; }
        }
        if (t.activo) {
            if (!t.ini || !t.fin) { error.textContent = 'Completa las horas del bloque de la tarde.'; return; }
            if (t.fin <= t.ini) { error.textContent = 'Tarde: la hora de fin debe ser mayor que la de inicio.'; return; }
            const [hi] = t.ini.split(':').map(Number);
            const [hf] = t.fin.split(':').map(Number);
            if (hi < 12 || hi >= 21 || hf < 12 || hf >= 21) { error.textContent = 'Tarde: va entre las 12:00 p. m. y las 9:00 p. m.'; return; }
        }

        dias.forEach(dia => {
            PERIODOS.forEach(periodo => {
                const v = valores[periodo];
                toggleBloque(dia, periodo).checked = v.activo;
                if (v.activo) {
                    entrada(dia, periodo, 'inicio').value = v.ini;
                    entrada(dia, periodo, 'fin').value = v.fin;
                }
            });
            toggleDia(dia).checked = true;
            pintarDia(dia);
        });

        cerrarModal();
        iniciarPickers();
        actualizarResumen();
        guardar();
    }

    // ── Eventos ──
    document.querySelector('.horarios-tabla').addEventListener('change', e => {
        const t = e.target;
        if (t.classList.contains('dia-activo')) alCambiarDia(Number(t.dataset.dia));
        else if (t.classList.contains('bloque-activo')) alCambiarBloque(Number(t.dataset.dia), t.dataset.periodo);
    });
    document.getElementById('btnRestaurar').addEventListener('click', restaurar);
    document.getElementById('btnVariosDias').addEventListener('click', abrirModal);
    document.getElementById('btnAplicarMultiDia').addEventListener('click', aplicarVariosDias);
    modal.addEventListener('click', e => {
        if (e.target === modal || e.target.closest('[data-cerrar-modal]')) cerrarModal();
    });
    modal.addEventListener('change', e => {
        const periodo = e.target.dataset.mdPeriodo;
        if (!periodo) return;
        const s = periodo === 'morning' ? 'Morning' : 'Afternoon';
        document.getElementById(`md${s}Section`).classList.toggle('bloque-inactivo', !e.target.checked);
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !modal.hidden) cerrarModal();
    });

    cargar();
})();
