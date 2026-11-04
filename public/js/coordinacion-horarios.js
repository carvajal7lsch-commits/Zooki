(function (global) {
    'use strict';

    function errorFranjas(franjas) {
        const hora = valor => valor.length === 5 ? valor + ':00' : valor;
        const ordenadas = franjas.map(f => ({ ...f, hora_inicio: hora(f.hora_inicio), hora_fin: hora(f.hora_fin) })).sort((a, b) => Number(a.dia_semana) - Number(b.dia_semana) || a.hora_inicio.localeCompare(b.hora_inicio));
        let previa;
        for (const f of ordenadas) {
            if (!/^[1-7]$/.test(String(f.dia_semana)) || !/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/.test(f.hora_inicio) || !/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/.test(f.hora_fin)) return 'Completa el día y las horas válidas.';
            if (f.hora_fin <= f.hora_inicio) return 'El fin debe ser posterior al inicio.';
            if (previa && String(previa.dia_semana) === String(f.dia_semana) && f.hora_inicio < previa.hora_fin) return 'Los turnos no pueden solaparse.';
            previa = f;
        }
        return '';
    }

    function clave() {
        return Array.from(global.crypto.getRandomValues(new Uint8Array(32)), n => n.toString(16).padStart(2, '0')).join('');
    }

    const enviar = typeof module !== 'undefined' && module.exports ? require('./configuracion-clinica.js').enviar : global.ConfiguracionClinicaUI.enviar;
    if (typeof module !== 'undefined' && module.exports) module.exports = { errorFranjas, enviar };
    if (typeof document === 'undefined') return;

    function iniciar() {
        const raiz = document.querySelector('[data-coordinacion-horarios]');
        if (!raiz) return;
        const admin = raiz.dataset.admin === '1';
        const form = raiz.querySelector('[data-horario-vet]');
        const select = form.querySelector('[data-veterinario]');
        const lista = form.querySelector('[data-franjas]');
        const estado = { enviando: false, cargando: false };
        const comandos = new Map();
        let lectura = 0;
        let recargarPendiente = false;

        function bloquear(valor) {
            raiz.querySelectorAll('input,select,button').forEach(e => { e.disabled = valor; });
            semana.bloquear(valor);
        }

        const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        const ESTADOS = { pendiente: 'Pendiente', aprobada: 'Aprobada', rechazada: 'Rechazada', pendiente_calculo: 'Pendiente de cálculo', pendiente_reprogramacion: 'Pendiente de reprogramar', pendiente_reasignacion: 'Pendiente de reasignar', pendiente_revision: 'Requiere revisión' };
        const hora = valor => String(valor || '').slice(0, 5);
        // «2030-01-07 09:00:00» → «07/01/2030 09:00» sin depender de la zona del navegador.
        const fecha = valor => {
            const m = String(valor || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T]?(\d{2}:\d{2})?/);
            return m ? `${m[3]}/${m[2]}/${m[1]}${m[4] ? ' ' + m[4] : ''}` : String(valor || '');
        };

        function texto(contenedor, etiqueta, valor) {
            const elemento = document.createElement(etiqueta);
            elemento.textContent = valor;
            contenedor.append(elemento);
            return elemento;
        }

        const semana = global.HorarioTrabajo.crear(raiz, { alCambiar: validar });
        const pintarSemana = franjas => {
            semana.pintar(franjas);
            raiz.dispatchEvent(new CustomEvent('horario-trabajo:actualizado', { bubbles: true, detail: franjas }));
        };
        const franjas = () => semana.franjas();

        function validar() {
            const error = errorFranjas(franjas());
            form.querySelector('[data-error-campo="franjas"]').textContent = error;
            lista.querySelectorAll('input,select').forEach(e => e.setAttribute('aria-invalid', error ? 'true' : 'false'));
            return !error;
        }

        function claveDelComando(accion, datos) {
            const firma = JSON.stringify([accion, Array.from(datos.entries())]);
            if (!comandos.has(firma)) comandos.set(firma, clave());
            return comandos.get(firma);
        }

        async function guardar(accion, datos, contenedor) {
            if (estado.cargando || estado.enviando) return false;
            lectura++;
            datos.set('id_clinica', raiz.dataset.clinica);
            datos.set('csrf_token', form.elements.namedItem('csrf_token').value);
            datos.set('clave_operacion', claveDelComando(accion, datos));
            return enviar(estado, datos, accion, respuesta => {
                comandos.clear();
                const canonico = respuesta.resultado;
                pintar(canonico.vista);
            }, {
                fetch: global.fetch.bind(global),
                bloquear,
                aviso: mensaje => { if (global.zookiToast) global.zookiToast(mensaje); },
                error: datosError => {
                    const error = contenedor.querySelector(`[data-error-campo="${datosError.campo}"]`) || contenedor.querySelector('[data-aviso]');
                    if (error) error.textContent = datosError.aviso || datosError.message || 'No se pudo guardar.';
                }
            }).finally(() => {
                if (recargarPendiente) {
                    recargarPendiente = false;
                    cargar();
                }
            });
        }

        function boton(contenedor, nombre, accion, secundario = false) {
            const b = texto(contenedor, 'button', nombre);
            b.type = 'button';
            b.className = secundario ? 'coord-btn coord-btn--sec coord-btn--mini' : 'coord-btn coord-btn--mini';
            b.addEventListener('click', () => {
                if (!estado.enviando && !estado.cargando) accion();
            });
        }

        function pintar(datos) {
            const elegido = String(datos.id_veterinario);
            select.replaceChildren();
            for (const vet of datos.veterinarios) {
                const opcion = texto(select, 'option', vet.nombre_completo);
                opcion.value = vet.id_usuario;
            }
            select.value = elegido;
            semana.apertura?.(admin ? null : datos.apertura);
            pintarSemana(datos.franjas);
            for (const [nombre, filas] of [['propuestas', datos.propuestas], ['cierres', datos.cierres], ['afectadas', datos.afectadas]]) {
                const contenedor = raiz.querySelector(`[data-${nombre}]`);
                contenedor.replaceChildren();
                if (nombre === 'propuestas') contenedor.dataset.pendientes = String(filas.filter(fila => fila.estado === 'pendiente').length);
                for (const registro of filas) {
                    const fila = document.createElement('article');
                    fila.className = 'coord-item';
                    if (nombre === 'propuestas') {
                        const vet = datos.veterinarios.find(v => String(v.id_usuario) === String(registro.id_veterinario));
                        texto(fila, 'strong', `${vet ? vet.nombre_completo : 'Veterinario'} · ${ESTADOS[registro.estado] || registro.estado}`);
                        const propuestas = JSON.parse(registro.franjas);
                        texto(fila, 'p', propuestas.map(f => `${DIAS[Number(f.dia_semana) - 1] || f.dia_semana} ${hora(f.hora_inicio)}–${hora(f.hora_fin)}`).join(' · ') || 'Sin franjas');
                        if (registro.motivo_rechazo) texto(fila, 'p', 'Motivo: ' + registro.motivo_rechazo);
                        if (admin && registro.estado === 'pendiente') {
                            const acciones = document.createElement('div');
                            acciones.className = 'coord-item__acciones';
                            const motivo = document.createElement('input');
                            motivo.maxLength = 255;
                            motivo.placeholder = 'Motivo si la rechazas';
                            motivo.setAttribute('aria-label', 'Motivo de rechazo');
                            acciones.append(motivo);
                            fila.append(acciones);
                            const aviso = texto(fila, 'span', '');
                            aviso.className = 'coordinacion-error';
                            aviso.dataset.aviso = '';
                            motivo.addEventListener('input', () => { aviso.textContent = motivo.value.trim() ? '' : 'El rechazo necesita un motivo.'; });
                            for (const aprobar of [true, false]) {
                                boton(acciones, aprobar ? 'Aprobar' : 'Rechazar', () => {
                                    if (!aprobar && !motivo.value.trim()) {
                                        aviso.textContent = 'El rechazo necesita un motivo.';
                                        return;
                                    }
                                    const cuerpo = new FormData();
                                    cuerpo.set('id_propuesta', registro.id_propuesta);
                                    cuerpo.set('motivo', aprobar ? '' : motivo.value.trim());
                                    guardar(aprobar ? 'aprobar_horario_vet_ajax' : 'rechazar_horario_vet_ajax', cuerpo, fila);
                                }, !aprobar);
                            }
                        }
                    } else if (nombre === 'cierres') {
                        texto(fila, 'strong', registro.motivo);
                        texto(fila, 'p', `${fecha(registro.inicio)} → ${fecha(registro.fin)} · ${Number(registro.activo) ? 'Activo' : 'Retirado'}`);
                        if (!Number(registro.activo)) fila.classList.add('coord-item--apagado');
                        texto(fila, 'p', '').dataset.aviso = '';
                        if (admin && Number(registro.activo)) boton(fila, 'Retirar cierre', () => {
                            const cuerpo = new FormData();
                            cuerpo.set('id_cierre', registro.id_cierre);
                            guardar('retirar_cierre_clinica_ajax', cuerpo, fila);
                        }, true);
                    } else {
                        texto(fila, 'strong', `Cita ${registro.id_cita} · ${fecha(registro.fecha + ' ' + registro.hora)}`);
                        texto(fila, 'p', `${ESTADOS[registro.estado_reajuste] || registro.estado_reajuste}${registro.motivo_reajuste ? ' · ' + registro.motivo_reajuste : ''}`);
                    }
                    contenedor.append(fila);
                }
            }
            validar();
        }

        async function cargar() {
            if (estado.enviando) {
                recargarPendiente = true;
                return;
            }
            const version = ++lectura;
            estado.cargando = true;
            bloquear(true);
            try {
                const respuesta = await global.fetch(`index.php?action=get_coordinacion_horarios_ajax${select.value ? '&id_veterinario=' + encodeURIComponent(select.value) : ''}`);
                const datos = await respuesta.json();
                if (version !== lectura) return;
                if (!respuesta.ok || !datos.success) throw new Error(datos.message || 'No se pudo cargar.');
                pintar(datos);
            } catch (error) {
                if (version === lectura) form.querySelector('[data-aviso]').textContent = error.message;
            } finally {
                if (version === lectura) {
                    estado.cargando = false;
                    bloquear(false);
                }
            }
        }

        lista.addEventListener('input', validar);
        lista.addEventListener('change', validar);
        select.addEventListener('change', cargar);
        form.addEventListener('submit', evento => {
            evento.preventDefault();
            if (estado.enviando || estado.cargando || !validar()) return;
            const cuerpo = new FormData();
            cuerpo.set('id_veterinario', select.value);
            cuerpo.set('franjas', JSON.stringify(franjas()));
            guardar(admin ? 'guardar_horario_vet_ajax' : 'proponer_horario_vet_ajax', cuerpo, form);
        });
        const cierre = raiz.querySelector('[data-cierre]');
        if (cierre) {
            if (global.SelectorFechas && typeof global.flatpickr === 'function') {
                cierre.querySelectorAll('[data-fecha-cierre]').forEach(campo => {
                    global.SelectorFechas.iniciarFechaHora(campo, {
                        onChange() {
                            campo.dispatchEvent(new Event('input', { bubbles: true }));
                        },
                    });
                });
            }
            function validarCierre() {
                const inicio = cierre.elements.namedItem('inicio').value;
                const fin = cierre.elements.namedItem('fin').value;
                const motivo = cierre.elements.namedItem('motivo').value.trim();
                const errores = { inicio: inicio ? '' : 'Completa el inicio.', fin: fin && fin > inicio ? '' : 'El fin debe ser posterior al inicio.', motivo: motivo && motivo.length <= 255 ? '' : 'Escribe un motivo (hasta 255 caracteres).' };
                for (const [campo, error] of Object.entries(errores)) cierre.querySelector(`[data-error-campo="${campo}"]`).textContent = error;
                return Object.values(errores).every(error => !error);
            }
            cierre.addEventListener('input', validarCierre);
            cierre.addEventListener('submit', evento => {
                evento.preventDefault();
                if (estado.enviando || !validarCierre()) return;
                guardar('crear_cierre_clinica_ajax', new FormData(cierre), cierre).then(guardado => {
                    if (!guardado) return;
                    cierre.reset();
                    if (global.zookiCerrarDialogo) global.zookiCerrarDialogo(cierre);
                });
            });
        }
        document.addEventListener('horarios-actualizados', cargar);
        global.addEventListener('agenda:ausencia', cargar);
        cargar();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})(typeof window !== 'undefined' ? window : globalThis);
