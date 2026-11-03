(function (global) {
    'use strict';

    function errorCampo(nombre, valor, requerido, reglas) {
        const texto = String(valor || '').trim();
        if (!texto) return requerido ? 'Este campo es obligatorio.' : '';
        if (['motivo', 'decision', 'evidencia', 'motivo_conservacion'].includes(nombre) && (Array.from(texto).length > 255 || /\p{C}/u.test(texto))) return 'Máximo 255 caracteres de texto.';
        if (nombre === 'nombre_acompanante' && (Array.from(texto).length < 3 || Array.from(texto).length > reglas.nombreMax || !new RegExp(`^[${reglas.nombreCaracteres}]+$`, 'u').test(texto))) return 'Nombre de 3 a 100 caracteres.';
        if (nombre === 'telefono_acompanante' && (!/^[0-9+ \-]+$/.test(texto) || texto.length < reglas.telefonoMin || texto.length > reglas.telefonoMax)) return 'Revisa el teléfono.';
        if (['nombre', 'nombre_mascota'].includes(nombre) && (Array.from(texto).length > reglas.mascotaMax || !/^[\p{L}\p{N}][\p{L}\p{N} .'\-]*$/u.test(texto))) return 'Revisa el nombre de la mascota.';
        if (nombre === 'peso' && (!/^\d{1,3}([.,]\d{1,2})?$/.test(texto) || Number(texto.replace(',', '.')) <= 0 || Number(texto.replace(',', '.')) > 150)) return 'Peso mayor que 0 y hasta 150 kg.';
        if (nombre === 'fecha_nacimiento') {
            const hoy = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Bogota' }).format(new Date());
            const minimo = new Date(`${hoy}T12:00:00Z`);
            minimo.setUTCFullYear(minimo.getUTCFullYear() - 40);
            if (!/^\d{4}-\d\d-\d\d$/.test(texto) || !Number.isFinite(Date.parse(texto)) || texto > hoy || texto < minimo.toISOString().slice(0,10)) return 'Revisa la fecha de nacimiento.';
        }
        return '';
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { errorCampo };
    if (typeof document === 'undefined') return;

    function iniciar() {
        const raiz = document.querySelector('[data-urgencias-agenda]');
        if (!raiz) return;
        const reglas = JSON.parse(raiz.dataset.reglas);
        const vet = raiz.dataset.vet === '1';
        const aviso = raiz.querySelector('[data-urgencias-aviso]');
        const comandos = new WeakMap();
        let escribiendo = false;
        let lectura = 0;
        let catalogos;
        const ESTADOS = { espera_presencial: 'en espera', en_atencion: 'en atención', abierta: 'abierta', derivada: 'derivada' };

        function texto(padre, etiqueta, contenido) {
            const nodo = document.createElement(etiqueta);
            nodo.textContent = contenido;
            padre.append(nodo);
            return nodo;
        }

        function opciones(selector, lista, id, nombre) {
            selector.replaceChildren();
            const vacia = texto(selector, 'option', 'Elige');
            vacia.value = '';
            lista.forEach(dato => {
                const opcion = texto(selector, 'option', dato[nombre] || `Ficha #${dato[id]}`);
                opcion.value = dato[id];
            });
        }

        function campo(form, nombre, etiqueta, requerido = false, tipo = 'text') {
            const label = texto(form, 'label', etiqueta);
            const entrada = document.createElement(tipo === 'select' ? 'select' : 'input');
            entrada.name = nombre;
            entrada.required = requerido;
            if (tipo !== 'select') {
                entrada.type = tipo;
                entrada.maxLength = 255;
                entrada.dataset.espacios = 'normalizar';
                entrada.dataset.caracteres = '\\P{C}';
            }
            label.append(entrada);
            const error = texto(label, 'span', '');
            error.dataset.errorCampo = nombre;
            error.setAttribute('aria-live', 'polite');
            return entrada;
        }

        function validar(form) {
            let valido = true;
            form.querySelectorAll('[name]').forEach(entrada => {
                if (entrada.type === 'hidden' || entrada.disabled || entrada.closest('fieldset[disabled]')) return;
                const error = errorCampo(entrada.name, entrada.value, entrada.required, reglas);
                const nodo = form.querySelector(`[data-error-campo="${entrada.name}"]`);
                // El error aparece al tocar el campo o al intentar enviar, no al abrir el formulario.
                if (nodo) nodo.textContent = entrada.dataset.tocado === '1' || form.dataset.intento === '1' ? error : '';
                entrada.setAttribute('aria-invalid', error ? 'true' : 'false');
                valido = valido && !error;
            });
            if (form.hasAttribute('data-ingreso-rojo')) {
                const paciente = form.elements.namedItem('id_mascota');
                const faltaPaciente = !paciente.disabled && !paciente.value;
                const buscador = form.querySelector('[data-urgencia-buscar]');
                form.querySelector('[data-error-campo="id_mascota"]').textContent = faltaPaciente && (buscador.dataset.tocado || form.dataset.intento) ? 'Selecciona un paciente de los resultados.' : '';
                buscador.setAttribute('aria-invalid', String(faltaPaciente && Boolean(buscador.dataset.tocado || form.dataset.intento)));
                valido = valido && !faltaPaciente;
            }
            form.querySelector('[type="submit"]').disabled = escribiendo || !valido;
            const soloLlegada = form.querySelector('[data-ingreso-solo-llegada]');
            if (soloLlegada) soloLlegada.disabled = escribiendo || !valido;
            if (form.hasAttribute('data-ingreso-rojo')) {
                const hayAviso = form.querySelector('[data-aviso]').textContent;
                form.querySelector('[data-identidad-provisional]').hidden = form.elements.namedItem('identidad').value !== 'provisional' || Boolean(hayAviso);
            }
            return valido;
        }

        function preparar(form, accion, extras = () => ({})) {
            // El formulario de la ventana trae su botón y su aviso en el HTML; los demás los reciben aquí.
            if (!form.querySelector('[type="submit"]')) {
                const boton = texto(form, 'button', form.dataset.etiqueta || 'Guardar');
                boton.type = 'submit';
                boton.className = 'zk-btn zk-btn--mini';
            }
            if (!form.querySelector('[data-aviso]')) {
                const salida = texto(form, 'p', '');
                salida.setAttribute('data-aviso', '');
                salida.className = 'ah-error';
            }
            form.noValidate = true;
            const comando = global.zookiCrearComandoAgenda();
            comandos.set(form, comando);
            const tocar = evento => { if (evento.target.name) evento.target.dataset.tocado = '1'; validar(form); };
            form.addEventListener('input', tocar);
            form.addEventListener('change', tocar);
            form.addEventListener('submit', async evento => {
                evento.preventDefault();
                form.dataset.intento = '1';
                if (escribiendo || !validar(form)) return;
                const cuerpo = new FormData(form);
                const token = raiz.querySelector('[data-urgencias-csrf] input');
                cuerpo.set(token.name, token.value);
                cuerpo.set('id_clinica', raiz.dataset.clinica);
                for (const [nombre, valor] of Object.entries(extras())) cuerpo.set(nombre, valor);
                cuerpo.set('clave_operacion', comando.clave(cuerpo));
                escribiendo = true;
                ++lectura;
                raiz.querySelectorAll('button').forEach(b => { b.disabled = true; });
                try {
                    const destino = typeof accion === 'function' ? accion() : accion;
                    const respuesta = await global.fetch(`index.php?action=${destino}`, { method: 'POST', body: cuerpo });
                    const datos = await respuesta.json();
                    if (!respuesta.ok || datos.success === false) throw new Error(datos.message || 'No se pudo guardar.');
                    comando.reiniciar();
                    aviso.textContent = datos.message;
                    if (form.closest('dialog')) {
                        if (global.zookiCerrarDialogo) global.zookiCerrarDialogo(form);
                        form.reset();
                        delete form.dataset.intento;
                        form.querySelectorAll('[data-tocado]').forEach(e => { delete e.dataset.tocado; });
                        form.querySelector('[data-aviso]').textContent = '';
                        const identidad = form.elements.namedItem('identidad');
                        if (identidad) identidad.dispatchEvent(new Event('change'));
                    }
                    // La lista autoritativa se relee antes de anunciar el cambio al calendario.
                    await recargar();
                    global.dispatchEvent(new Event('agenda:urgencia'));
                } catch (error) {
                    form.querySelector('[data-aviso]').textContent = error.message;
                } finally {
                    escribiendo = false;
                    raiz.querySelectorAll('button').forEach(b => { b.disabled = false; });
                    raiz.querySelectorAll('form').forEach(validar);
                }
            });
            validar(form);
        }

        function formulario(padre, titulo, accion, datos) {
            const form = document.createElement('form');
            form.className = 'ah-item__form';
            form.dataset.etiqueta = titulo;
            padre.append(form);
            form.extras = () => datos;
            return form;
        }

        function rojos(lista, pendientesIdentidad = []) {
            const contenedor = raiz.querySelector('[data-rojos-lista]');
            contenedor.replaceChildren();
            lista.forEach(s => {
                const tarjeta = texto(contenedor, 'article', '');
                tarjeta.className = 'ah-item ah-item--rojo';
                tarjeta.dataset.llegada = s.fecha_llegada || '';
                tarjeta.dataset.ingreso = s.id_ingreso || '';
                const identidadPendiente = pendientesIdentidad.some(ingreso => String(ingreso.id_ingreso) === String(s.id_ingreso) && ingreso.estado === 'pendiente');
                const cabeza = texto(tarjeta, 'div', '');
                cabeza.className = 'ah-item__head';
                texto(cabeza, 'h3', s.nombre || `Ficha #${s.id_mascota}`);
                const nivel = texto(cabeza, 'span', 'Rojo');
                nivel.className = 'ah-nivel';
                nivel.dataset.nivel = 'rojo';
                texto(tarjeta, 'p', s.fecha_llegada ? `Llegó ${String(s.fecha_llegada).slice(11, 16)} · ${ESTADOS[s.estado] || s.estado}` : 'Aviso remoto: aún no se acredita la llegada').className = 'ah-item__meta';
                const resumen = texto(tarjeta, 'p', [ESTADOS[s.estado] || s.estado, identidadPendiente ? 'Identidad pendiente' : ''].filter(Boolean).join(' · '));
                resumen.className = 'ah-item__texto';
                if (!vet) return;
                const atender = texto(tarjeta, 'button', 'Atender ahora');
                atender.type = 'button';
                atender.className = 'zk-btn zk-btn--peligro zk-btn--mini';
                atender.addEventListener('click', () => document.dispatchEvent(new CustomEvent('agenda:atender-rojo', { detail: s })));
            });
        }

        function ingresos(lista) {
            const contenedor = raiz.querySelector('[data-ingresos-lista]');
            contenedor.replaceChildren();
            lista.forEach(i => {
                const tarjeta = texto(contenedor, 'article', '');
                tarjeta.className = 'ah-item';
                const cabeza = texto(tarjeta, 'div', '');
                cabeza.className = 'ah-item__head';
                texto(cabeza, 'h3', i.nombre || 'Ficha provisional');
                texto(tarjeta, 'p', [`Ingreso ${i.id_ingreso}`, i.nombre_acompanante, i.telefono_acompanante].filter(Boolean).join(' · ')).className = 'ah-item__meta';
                if (i.estado !== 'pendiente') {
                    texto(tarjeta, 'p', 'Identidad resuelta; el contacto se conserva solo mientras haga falta.').className = 'ah-item__meta';
                    return;
                }
                const abrir = texto(tarjeta, 'button', 'Completar ficha');
                abrir.type = 'button';
                abrir.className = 'zk-btn zk-btn--sec zk-btn--mini';
                abrir.dataset.conciliarIdentidad = String(i.id_ingreso);
                const dialogo = texto(tarjeta, 'dialog', '');
                dialogo.className = 'zk-dialogo urgencia-conciliacion';
                dialogo.id = 'conciliarIdentidad-' + i.id_ingreso;
                dialogo.setAttribute('aria-labelledby', dialogo.id + '-titulo');
                abrir.addEventListener('click', () => global.zookiAbrirDialogo(dialogo.id));
                const f = formulario(dialogo, 'Conciliar identidad verificada', 'conciliar_rojo_ajax', {});
                f.className = 'zk-dialogo__form';
                const modo = campo(f, 'modo', 'Resolver', true, 'select');
                opciones(modo, [{ id: 'consolidar', nombre: 'Consolidar sobre ficha existente' }, { id: 'completar', nombre: 'Completar esta ficha' }], 'id', 'nombre');
                modo.value = 'consolidar';
                const titular = campo(f, 'id_propietario', 'Titular con cuenta y vínculo verificados', true, 'select');
                opciones(titular, catalogos.propietarios, 'id_usuario', 'nombre_completo');
                const final = campo(f, 'id_mascota_final', 'Ficha definitiva del titular', true, 'select');
                const fragmento = raiz.querySelector('[data-completar-ficha]').content.cloneNode(true);
                f.append(fragmento);
                const ficha = f.querySelector('fieldset');
                const especie = f.elements.namedItem('especie');
                const raza = f.elements.namedItem('raza');
                opciones(especie, catalogos.especies, 'id_especie', 'nombre_especie');
                especie.addEventListener('change', () => {
                    opciones(raza, catalogos.razas.filter(r => String(r.id_especie) === especie.value), 'id_raza', 'nombre_raza');
                    validar(f);
                });
                campo(f, 'evidencia', 'Referencia de cuenta, vínculo o confirmación usada y verificación de relación', true);
                campo(f, 'motivo_conservacion', 'Motivo excepcional para conservar contacto (opcional)');
                const ajustar = () => {
                    ficha.disabled = modo.value !== 'completar';
                    ficha.hidden = ficha.disabled;
                    final.disabled = modo.value === 'completar';
                    final.closest('label').hidden = final.disabled;
                    opciones(final, catalogos.mascotas.filter(m => String(m.id_propietario) === titular.value), 'id_mascota', 'nombre');
                    validar(f);
                };
                preparar(f, 'conciliar_rojo_ajax', () => ({ id_ingreso: i.id_ingreso, ...(modo.value === 'completar' ? { id_mascota_final: i.id_mascota_provisional } : {}) }));
                modo.addEventListener('change', ajustar);
                titular.addEventListener('change', ajustar);
                ajustar();
                const contenido = Array.from(f.children);
                const cabecera = texto(f, 'header', '');
                cabecera.className = 'zk-dialogo__head';
                const titulo = texto(cabecera, 'h3', 'Conciliar identidad');
                titulo.id = dialogo.id + '-titulo';
                const cerrar = texto(cabecera, 'button', '×');
                cerrar.type = 'button';
                cerrar.className = 'zk-dialogo__cerrar';
                cerrar.setAttribute('data-cerrar-dialogo', '');
                cerrar.setAttribute('aria-label', 'Cerrar conciliación');
                const cuerpo = texto(f, 'div', '');
                cuerpo.className = 'zk-dialogo__cuerpo';
                const guardar = f.querySelector('[type="submit"]');
                contenido.filter(n => n !== guardar).forEach(n => cuerpo.append(n));
                const pie = texto(f, 'footer', '');
                pie.className = 'zk-dialogo__pie';
                const cancelar = texto(pie, 'button', 'Cancelar');
                cancelar.type = 'button';
                cancelar.className = 'zk-btn zk-btn--sec';
                cancelar.setAttribute('data-cerrar-dialogo', '');
                pie.append(guardar);
            });
        }

        function normalizarNombre(valor) {
            return String(valor || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase().replace(/\s+/g, ' ');
        }

        function llenarPacientesIngreso(form) {
            const buscador = form.querySelector('[data-urgencia-buscar]');
            const resultados = form.querySelector('[data-urgencia-resultados]');
            const busqueda = normalizarNombre(buscador.value);
            const pacientes = (catalogos?.mascotas || []).filter(mascota => [mascota.nombre, mascota.propietario, mascota.documento_propietario].some(valor => normalizarNombre(valor).includes(busqueda))).slice(0, 8);
            resultados.replaceChildren();
            buscador.removeAttribute('aria-activedescendant');
            if (!catalogos || !pacientes.length) texto(resultados, 'p', catalogos ? 'No hay pacientes que coincidan.' : 'Cargando pacientes…');
            pacientes.forEach(mascota => {
                const boton = texto(resultados, 'button', '');
                boton.type = 'button';
                boton.id = `urgenciaPaciente-${mascota.id_mascota}`;
                boton.dataset.paciente = mascota.id_mascota;
                boton.setAttribute('role', 'option');
                boton.setAttribute('aria-selected', 'false');
                boton.tabIndex = -1;
                texto(boton, 'strong', mascota.nombre);
                texto(boton, 'span', [mascota.propietario, mascota.documento_propietario].filter(Boolean).join(' · '));
                boton.addEventListener('click', () => {
                    form.elements.namedItem('id_mascota').value = mascota.id_mascota;
                    buscador.value = [mascota.nombre, mascota.propietario].filter(Boolean).join(' · ');
                    buscador.dataset.tocado = '1';
                    resultados.hidden = true;
                    buscador.setAttribute('aria-expanded', 'false');
                    buscador.removeAttribute('aria-activedescendant');
                    validar(form);
                });
            });
            resultados.hidden = document.activeElement !== buscador || form.elements.namedItem('identidad').value !== 'conocida';
            buscador.setAttribute('aria-expanded', String(!resultados.hidden));
        }

        async function recargar(automatica = false) {
            const turno = ++lectura;
            const respuesta = await global.fetch('index.php?action=listar_urgencias_ajax');
            const datos = await respuesta.json();
            if (turno !== lectura) return;
            if (!respuesta.ok || datos.success === false) throw new Error(datos.message || 'No se pudieron consultar las urgencias.');
            catalogos = datos.catalogos;
            const form = document.querySelector('#dialogoRojo [data-ingreso-rojo]');
            if (form) {
                llenarPacientesIngreso(form);
                validar(form);
            }
            if (automatica && panelAgendaEnEdicion(raiz)) return;
            rojos(datos.rojos, datos.ingresos);
            ingresos(datos.ingresos);
        }

        function prepararIngreso(form) {
            const registrar = global.zookiCrearComandoAgenda();
            const atender = global.zookiCrearComandoAgenda();
            const priorizar = global.zookiCrearComandoAgenda();
            const salida = form.querySelector('[data-aviso]');
            const principal = form.querySelector('[type="submit"]');
            let solicitud = null;
            let registroIntentado = false;
            let cuerpoRegistro = null;
            let excepcion = null;
            const camposIdentidad = ['identidad', 'id_mascota', 'motivo'];

            function ajustarExcepcion() {
                form.querySelector('[data-ingreso-excepcion]').hidden = !excepcion;
                form.elements.namedItem('detalle_urgencia').disabled = !excepcion;
                form.querySelector('[data-ingreso-excepcion-titulo]').textContent = excepcion === 'prioridad'
                    ? 'Selecciona el motivo para priorizar y atender a este paciente.' : 'Selecciona el motivo de la decisión clínica para atender a este paciente.';
            }

            function reiniciar() {
                solicitud = null;
                registroIntentado = false;
                cuerpoRegistro = null;
                excepcion = null;
                registrar.reiniciar();
                atender.reiniciar();
                priorizar.reiniciar();
                form.reset();
                form.elements.namedItem('id_solicitud').value = '';
                form.elements.namedItem('identidad').value = 'provisional';
                form.elements.namedItem('motivo').value = 'Ingreso de urgencia presencial';
                form.elements.namedItem('decision').value = '';
                llenarPacientesIngreso(form);
                camposIdentidad.forEach(nombre => { form.elements.namedItem(nombre).disabled = false; });
                form.querySelectorAll('[data-urgencia-identidad], [data-urgencia-buscar]').forEach(control => { control.disabled = false; });
                delete form.dataset.intento;
                form.querySelectorAll('[data-tocado]').forEach(campo => { delete campo.dataset.tocado; });
                form.querySelector('[data-evaluacion-conservada]').hidden = true;
                salida.textContent = '';
                principal.textContent = 'Atender ahora';
                ajustarExcepcion();
                form.elements.namedItem('identidad').dispatchEvent(new Event('change'));
            }

            async function enviar(accion, cuerpo, comando) {
                const token = raiz.querySelector('[data-urgencias-csrf] input');
                cuerpo.set(token.name, token.value);
                cuerpo.set('id_clinica', raiz.dataset.clinica);
                cuerpo.set('clave_operacion', comando.clave(cuerpo));
                const respuesta = await global.fetch(`index.php?action=${accion}`, { method: 'POST', body: cuerpo, sinLoader: true });
                const datos = await respuesta.json();
                if (!respuesta.ok || datos.success === false) {
                    const error = new Error(datos.message || 'No se pudo completar la acción.');
                    error.rechazo = respuesta.status < 500;
                    error.campo = datos.campo;
                    throw error;
                }
                return datos;
            }

            async function refrescar() {
                try {
                    await recargar();
                } catch (error) {
                    aviso.textContent = error.message;
                }
                global.dispatchEvent(new Event('agenda:urgencia'));
            }

            async function ejecutar(iniciarAtencion) {
                if (escribiendo) return;
                form.dataset.intento = '1';
                if (!validar(form)) return;
                const motivoExcepcion = form.elements.namedItem('decision').value || form.elements.namedItem('detalle_urgencia').value.trim();
                if (iniciarAtencion && excepcion && !motivoExcepcion) {
                    salida.textContent = 'Selecciona un motivo clínico o escribe el detalle de la decisión.';
                    return;
                }
                const cuerpoIngreso = new FormData(form);
                const cuerpoAtencion = new FormData();
                cuerpoAtencion.set('decision', excepcion === 'decision' ? motivoExcepcion : '');
                const controles = Array.from(form.querySelectorAll('input, select, textarea, button'));
                const deshabilitados = controles.map(control => control.disabled);
                escribiendo = true;
                ++lectura;
                controles.forEach(control => { control.disabled = true; });
                principal.textContent = solicitud ? 'Iniciando atención…' : 'Registrando llegada…';
                salida.textContent = '';
                try {
                    if (!solicitud) {
                        registroIntentado = true;
                        // Una respuesta perdida conserva el cuerpo y la clave para no duplicar la llegada.
                        if (!cuerpoRegistro) cuerpoRegistro = cuerpoIngreso;
                        const accion = cuerpoRegistro.get('id_solicitud') ? 'llegada_rojo_ajax' : 'ingresar_rojo_ajax';
                        const datos = await enviar(accion, cuerpoRegistro, registrar);
                        const id = Number(datos.resultado?.id_solicitud);
                        if (!Number.isInteger(id) || id < 1) throw new Error('No se pudo confirmar el ingreso. Reintenta sin cambiar los datos.');
                        solicitud = id;
                        form.elements.namedItem('id_solicitud').value = id;
                        aviso.textContent = datos.message;
                    }
                    if (!iniciarAtencion) {
                        global.zookiCerrarDialogo(form);
                        reiniciar();
                        global.zookiToast?.('Llegada registrada en Pendientes → Rojos.', 'success');
                    } else {
                        principal.textContent = 'Iniciando atención…';
                        if (excepcion === 'prioridad') {
                            const orden = new FormData();
                            orden.set('id_solicitud', solicitud);
                            orden.set('motivo', motivoExcepcion);
                            await enviar('ordenar_rojo_ajax', orden, priorizar);
                            priorizar.reiniciar();
                            excepcion = null;
                            form.elements.namedItem('decision').value = '';
                        }
                        cuerpoAtencion.set('id_solicitud', solicitud);
                        const datos = await enviar('atender_rojo_ajax', cuerpoAtencion, atender);
                        if (datos.resultado?.espera) {
                            // Una espera confirmada es un resultado definitivo: otro intento debe evaluar el estado nuevo.
                            atender.reiniciar();
                            throw new Error(datos.message || 'El rojo sigue en espera.');
                        }
                        const cita = Number(datos.resultado?.id_cita);
                        if (!Number.isInteger(cita) || cita < 1) throw new Error('No se pudo confirmar la apertura de Atención. Reintenta atender.');
                        global.location.assign(`index.php?action=vet_atencion&id_cita=${encodeURIComponent(cita)}`);
                        return;
                    }
                } catch (error) {
                    if (['decision', 'prioridad'].includes(error.campo)) {
                        excepcion = error.campo;
                        form.elements.namedItem('decision').value = '';
                    }
                    if (error.rechazo) {
                        if (solicitud) atender.reiniciar();
                        else {
                            registroIntentado = false;
                            cuerpoRegistro = null;
                        }
                    }
                    salida.textContent = solicitud ? `La llegada quedó registrada. ${error.message}` : error.message;
                } finally {
                    escribiendo = false;
                    controles.forEach((control, indice) => { control.disabled = deshabilitados[indice]; });
                    if (solicitud || registroIntentado) camposIdentidad.forEach(nombre => { form.elements.namedItem(nombre).disabled = true; });
                    form.querySelectorAll('[data-urgencia-identidad], [data-urgencia-buscar]').forEach(control => { control.disabled = Boolean(solicitud || registroIntentado); });
                    ajustarExcepcion();
                    principal.textContent = solicitud ? 'Reintentar atender' : 'Atender ahora';
                    validar(form);
                }
                if (solicitud || !registroIntentado) await refrescar();
            }

            form.addEventListener('submit', evento => {
                evento.preventDefault();
                ejecutar(true);
            });
            form.querySelector('[data-ingreso-solo-llegada]').addEventListener('click', () => ejecutar(false));
            for (const nombre of ['input', 'change']) {
                form.addEventListener(nombre, evento => {
                    if (evento.target.name) evento.target.dataset.tocado = '1';
                    validar(form);
                });
            }
            form.querySelectorAll('[data-urgencia-motivo]').forEach(boton => {
                boton.addEventListener('click', () => {
                    const detalle = form.elements.namedItem('detalle_urgencia').value.trim();
                    form.elements.namedItem('decision').value = [boton.textContent, detalle].filter(Boolean).join(' · ');
                    ejecutar(true);
                });
            });
            form.closest('dialog').addEventListener('cancel', evento => {
                if (escribiendo) evento.preventDefault();
            });
            function retomar(caso) {
                if (escribiendo) return;
                if (caso.estado === 'en_atencion' && caso.id_cita) {
                    global.location.assign(`index.php?action=vet_atencion&id_cita=${encodeURIComponent(caso.id_cita)}`);
                    return;
                }
                reiniciar();
                form.elements.namedItem('id_solicitud').value = caso.id_solicitud;
                form.elements.namedItem('id_mascota').value = caso.id_mascota;
                const resumen = form.querySelector('[data-evaluacion-conservada]');
                resumen.textContent = caso.nombre || 'Urgencia registrada';
                resumen.hidden = false;
                global.zookiAbrirDialogo('dialogoRojo');
                if (caso.fecha_llegada) {
                    solicitud = Number(caso.id_solicitud);
                    camposIdentidad.forEach(nombre => { form.elements.namedItem(nombre).disabled = true; });
                    ejecutar(true);
                } else {
                    resumen.textContent += ' · La llegada física se registra al pulsar Atender ahora.';
                }
            }
            return { reiniciar, retomar, conservar: () => solicitud !== null || registroIntentado };
        }

        const ingreso = raiz.querySelector('[data-ingreso-rojo]');
        if (ingreso) {
            const flujoIngreso = prepararIngreso(ingreso);
            document.addEventListener('agenda:atender-rojo', evento => flujoIngreso.retomar(evento.detail));
            document.addEventListener('agenda:ingreso-rojo', async evento => {
                try {
                    await recargar();
                    flujoIngreso.reiniciar();
                    ingreso.elements.namedItem('id_solicitud').value = evento.detail.id_solicitud;
                    ingreso.elements.namedItem('identidad').value = 'conocida';
                    ingreso.elements.namedItem('identidad').dispatchEvent(new Event('change'));
                    ingreso.elements.namedItem('id_mascota').value = evento.detail.id_mascota;
                    ingreso.elements.namedItem('motivo').value = evento.detail.resumen.slice(0, 255);
                    const resumen = ingreso.querySelector('[data-evaluacion-conservada]');
                    resumen.textContent = 'Evaluación roja conservada: ' + evento.detail.resumen;
                    resumen.hidden = false;
                    validar(ingreso);
                    global.zookiAbrirDialogo('dialogoRojo');
                } catch (error) {
                    aviso.textContent = error.message;
                }
            });
            document.addEventListener('click', evento => {
                if (!evento.target.closest('[data-abrir-dialogo="dialogoRojo"]')) return;
                if (escribiendo || flujoIngreso.conservar()) return;
                flujoIngreso.reiniciar();
                ingreso.elements.namedItem('id_solicitud').value = '';
                ingreso.querySelector('[data-evaluacion-conservada]').hidden = true;
                validar(ingreso);
            });
            ingreso.elements.namedItem('identidad').addEventListener('change', () => {
                const provisional = ingreso.elements.namedItem('identidad').value === 'provisional';
                ingreso.querySelector('[data-identidad-provisional]').hidden = !provisional;
                const conocida = ingreso.querySelector('[data-identidad-conocida]');
                conocida.hidden = provisional;
                ingreso.elements.namedItem('id_mascota').disabled = provisional;
                ingreso.querySelector('[data-urgencia-resultados]').hidden = true;
                ingreso.querySelector('[data-urgencia-buscar]').setAttribute('aria-expanded', 'false');
                ingreso.querySelectorAll('[data-urgencia-identidad]').forEach(boton => {
                    boton.setAttribute('aria-pressed', String(boton.dataset.urgenciaIdentidad === ingreso.elements.namedItem('identidad').value));
                });
                validar(ingreso);
            });
            ingreso.querySelectorAll('[data-urgencia-identidad]').forEach(boton => {
                boton.addEventListener('click', () => {
                    ingreso.elements.namedItem('identidad').value = boton.dataset.urgenciaIdentidad;
                    ingreso.elements.namedItem('identidad').dispatchEvent(new Event('change'));
                    if (boton.dataset.urgenciaIdentidad === 'conocida') ingreso.querySelector('[data-urgencia-buscar]').focus();
                });
            });
            const buscador = ingreso.querySelector('[data-urgencia-buscar]');
            buscador.addEventListener('input', () => {
                ingreso.elements.namedItem('id_mascota').value = '';
                buscador.dataset.tocado = '1';
                llenarPacientesIngreso(ingreso);
                validar(ingreso);
            });
            buscador.addEventListener('focus', () => llenarPacientesIngreso(ingreso));
            buscador.addEventListener('keydown', evento => {
                const resultados = ingreso.querySelector('[data-urgencia-resultados]');
                if (evento.key === 'Escape' && !resultados.hidden) {
                    evento.preventDefault();
                    evento.stopPropagation();
                    resultados.hidden = true;
                    buscador.setAttribute('aria-expanded', 'false');
                    return;
                }
                if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(evento.key)) return;
                evento.preventDefault();
                if (resultados.hidden) llenarPacientesIngreso(ingreso);
                const opciones = Array.from(resultados.querySelectorAll('[role="option"]'));
                if (!opciones.length) return;
                const actual = opciones.findIndex(opcion => opcion.id === buscador.getAttribute('aria-activedescendant'));
                if (evento.key === 'Enter') {
                    if (actual >= 0) opciones[actual].click();
                    return;
                }
                const siguiente = actual < 0 ? (evento.key === 'ArrowDown' ? 0 : opciones.length - 1) : (actual + (evento.key === 'ArrowDown' ? 1 : -1) + opciones.length) % opciones.length;
                opciones.forEach((opcion, indice) => opcion.setAttribute('aria-selected', String(indice === siguiente)));
                buscador.setAttribute('aria-activedescendant', opciones[siguiente].id);
                opciones[siguiente].scrollIntoView({ block: 'nearest' });
            });
            document.addEventListener('click', evento => {
                if (evento.target === buscador || evento.target.closest('[data-urgencia-resultados]')) return;
                ingreso.querySelector('[data-urgencia-resultados]').hidden = true;
                buscador.setAttribute('aria-expanded', 'false');
            });
            // RNF-16: desde la barra puede abrirse con Pendientes oculto; el diálogo nativo debe tener caja visible.
            document.body.append(ingreso.closest('dialog'));
        }
        sincronizarPanelAgenda(raiz, automatica => recargar(automatica).catch(error => { aviso.textContent = error.message; }), () => escribiendo);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})(typeof window !== 'undefined' ? window : globalThis);
