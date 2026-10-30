/** HU-7.4: validación inmediata, respuesta canónica y una sola escritura en vuelo. */
(function (global) {
    'use strict';

    function errorCampo(nombre, entrada, reglas) {
        const valor = String(entrada).trim();
        if (nombre === 'nombre' || nombre === 'nombre_tipo' || nombre === 'direccion') {
            const minimo = nombre === 'nombre' ? reglas.nombreMin : (nombre === 'direccion' ? 0 : 1);
            const maximo = nombre === 'nombre' ? reglas.nombreMax : (nombre === 'direccion' ? reglas.direccionMax : reglas.tipoMax);
            if (Array.from(valor).length < minimo || Array.from(valor).length > maximo || (valor && !new RegExp(`^[${reglas.caracteresTexto}]+$`, 'u').test(valor))) return 'Revisa el texto y su longitud.';
        }
        if (nombre === 'telefono' || nombre === 'telefono_urgencias') {
            if (valor && (!/^[0-9+ \-]+$/.test(valor) || valor.length < reglas.telefonoMin || valor.length > reglas.telefonoMax)) return 'Revisa el formato del teléfono.';
        }
        if (nombre === 'email_contacto' && valor && (valor.length > reglas.correoMax || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor))) return 'Escribe un correo válido.';
        if (nombre === 'url_logo' && valor) {
            if (valor.length > reglas.logoMax || /[\s<>"'\\]/.test(valor) || valor.startsWith('//')) return 'Revisa la URL o ruta del logo.';
            try {
                const url = new URL(valor, 'https://zooki.invalid/');
                if (!['http:', 'https:'].includes(url.protocol)) return 'Usa una URL HTTP/HTTPS o una ruta de imagen.';
            } catch (_) {
                return 'Revisa la URL o ruta del logo.';
            }
        }
        if (nombre === 'duracion_minutos' || nombre === 'margen_minutos') {
            const maximo = nombre === 'duracion_minutos' ? reglas.duracionMax : reglas.margenMax;
            if (!/^\d{1,3}$/.test(valor) || Number(valor) < (nombre === 'duracion_minutos' ? 1 : 0) || Number(valor) > maximo) return nombre === 'duracion_minutos' ? `La duración debe ser de 1 a ${maximo} minutos.` : `El margen debe ser de 0 a ${maximo} minutos.`;
        }
        if (nombre === 'anticipacion_cambios_min' && (!/^\d{1,4}$/.test(valor) || Number(valor) > reglas.cambiosMax)) return `Indica de 0 a ${reglas.cambiosMax} minutos; 0 desactiva el plazo.`;
        if (['pausable', 'activo', 'solo_personal'].includes(nombre) && !['0', '1'].includes(valor)) return 'Elige Sí o No.';
        return '';
    }

    // El guardado compartido deja el estado actualizado antes de emitir el aviso.
    async function enviar(estado, cuerpo, accion, actualizar, dependencias) {
        if (estado.enviando) return false;
        estado.enviando = true;
        dependencias.bloquear(true);
        try {
            const respuesta = await dependencias.fetch(`index.php?action=${accion}`, { method: 'POST', body: cuerpo });
            const datos = await respuesta.json();
            if (!respuesta.ok || datos.success === false) {
                dependencias.error(datos);
                return false;
            }
            actualizar(datos);
            dependencias.aviso(datos.message || 'Cambios guardados.');
            return true;
        } catch (_) {
            dependencias.error({ message: 'No se pudo guardar. Intenta nuevamente.' });
            return false;
        } finally {
            estado.enviando = false;
            dependencias.bloquear(false);
        }
    }

    const api = { errorCampo, enviar };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    global.ConfiguracionClinicaUI = api;
    if (typeof document === 'undefined') return;

    function iniciar() {
        const raiz = document.querySelector('[data-configuracion-clinica]');
        if (!raiz) return;
        const reglas = JSON.parse(raiz.dataset.reglas);
        const marcaForm = raiz.querySelector('[data-form-marca]');
        const tipoForm = raiz.querySelector('[data-form-tipo]');
        const logoUI = global.LogoClinicaUI.iniciar(marcaForm, reglas);
        const lista = raiz.querySelector('[data-lista-tipos]');
        const estado = { enviando: false };
        const versiones = new WeakMap();
        let tipos = JSON.parse(raiz.dataset.tipos);

        function mostrarError(form, campo, mensaje) {
            const aviso = form.querySelector(`[data-error-campo="${campo}"]`);
            const entrada = form.elements.namedItem(campo);
            if (aviso) aviso.textContent = mensaje;
            if (entrada) entrada.setAttribute('aria-invalid', mensaje ? 'true' : 'false');
            const ayuda = form.querySelector(`#tipo-ayuda-${campo}`);
            if (ayuda) ayuda.hidden = !!mensaje;
        }

        function completar(form, datos) {
            for (const entrada of form.querySelectorAll('[data-config-campo], [name="id_tipo_cita"]')) {
                entrada.value = datos[entrada.name] ?? (entrada.name === 'solo_personal' ? '0' : '');
                // El selector con prefijo separado debe recibir también el número canónico del guardado.
                if (entrada.zookiTelefono) entrada.zookiTelefono.setNumber(entrada.value);
                versiones.set(entrada, (versiones.get(entrada) || 0) + 1);
                mostrarError(form, entrada.name, '');
            }
            form.querySelector('[data-aviso]').textContent = '';
        }

        function abrirTipo(titulo) {
            tipoForm.querySelector('[data-tipo-titulo]').textContent = titulo;
            if (global.zookiAbrirDialogo) global.zookiAbrirDialogo('dialogoTipo');
            tipoForm.elements.namedItem('nombre_tipo').focus();
        }

        // Pulido de interfaz: filas compactas con chips; editar abre la ventana del tipo.
        function renderizar() {
            lista.replaceChildren();
            if (!tipos.length) {
                const vacio = document.createElement('p');
                vacio.className = 'configuracion-vacio';
                vacio.textContent = 'Aún no hay tipos de cita. Crea el primero con «Nuevo tipo».';
                lista.append(vacio);
            }
            for (const tipo of tipos) {
                const fila = document.createElement('article');
                fila.className = 'configuracion-tipo';
                if (!Number(tipo.activo)) fila.classList.add('configuracion-tipo--inactivo');
                const datos = document.createElement('div');
                datos.className = 'configuracion-tipo__datos';
                const nombre = document.createElement('strong');
                nombre.textContent = tipo.nombre_tipo;
                const chips = document.createElement('div');
                chips.className = 'configuracion-tipo__chips';
                for (const texto of [`${tipo.duracion_minutos} min`, `+${tipo.margen_minutos} min margen`, Number(tipo.pausable) ? 'Pausable' : 'No pausable', Number(tipo.solo_personal) ? 'Solo personal' : 'Portal y personal', Number(tipo.activo) ? 'Activo' : 'Inactivo']) {
                    const chip = document.createElement('span');
                    chip.className = 'configuracion-chip';
                    if (texto === 'Inactivo') chip.classList.add('configuracion-chip--apagado');
                    chip.textContent = texto;
                    chips.append(chip);
                }
                datos.append(nombre, chips);
                const acciones = document.createElement('div');
                acciones.className = 'configuracion-tipo-acciones';
                for (const [texto, icono, ejecutar] of [
                    ['Editar', 'fa-pen', () => {
                        completar(tipoForm, tipo);
                        abrirTipo('Editar tipo de cita');
                    }],
                    [Number(tipo.activo) ? 'Desactivar' : 'Reactivar', Number(tipo.activo) ? 'fa-eye-slash' : 'fa-eye', () => {
                        const cuerpo = new FormData();
                        cuerpo.set('id_tipo_cita', tipo.id_tipo_cita);
                        cuerpo.set('activo', Number(tipo.activo) ? '0' : '1');
                        cuerpo.set('id_clinica', tipoForm.elements.namedItem('id_clinica').value);
                        cuerpo.set('csrf_token', tipoForm.elements.namedItem('csrf_token').value);
                        guardar(tipoForm, cuerpo, 'cambiar_estado_tipo_cita_ajax');
                    }]
                ]) {
                    const boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'configuracion-icono';
                    boton.title = texto;
                    boton.setAttribute('aria-label', `${texto} ${tipo.nombre_tipo}`);
                    const i = document.createElement('i');
                    i.className = `fas ${icono}`;
                    i.setAttribute('aria-hidden', 'true');
                    boton.append(i);
                    boton.addEventListener('click', () => { if (!estado.enviando) ejecutar(); });
                    acciones.append(boton);
                }
                fila.append(datos, acciones);
                lista.append(fila);
            }
        }

        function guardar(form, cuerpo, accion) {
            form.querySelector('[data-aviso]').textContent = '';
            return enviar(estado, cuerpo, accion, datos => {
                if (datos.marca) {
                    completar(marcaForm, datos.marca);
                    logoUI.restablecer();
                    const vista = raiz.querySelector('[data-vista-marca]');
                    for (const dato of vista.querySelectorAll('[data-marca-dato]')) {
                        dato.textContent = datos.marca[dato.dataset.marcaDato] || 'Sin registrar';
                    }
                    const logo = vista.querySelector('[data-marca-logo]');
                    logo.replaceChildren();
                    if (datos.marca.url_logo) {
                        const imagen = document.createElement('img');
                        imagen.src = datos.marca.url_logo;
                        imagen.alt = `Logo de ${datos.marca.nombre}`;
                        logo.append(imagen);
                    } else {
                        const inicial = document.createElement('span');
                        inicial.textContent = (datos.marca.nombre || 'Z').charAt(0).toUpperCase();
                        logo.append(inicial);
                    }
                }
                // Guardado bien: la ventana se cierra; la lista o la vista ya quedaron al día.
                if (accion !== 'cambiar_estado_tipo_cita_ajax' && global.zookiCerrarDialogo) global.zookiCerrarDialogo(form);
                if (datos.tipo) {
                    tipos = tipos.filter(tipo => Number(tipo.id_tipo_cita) !== Number(datos.tipo.id_tipo_cita));
                    tipos.push(datos.tipo);
                    tipos.sort((a, b) => Number(a.duracion_minutos) - Number(b.duracion_minutos) || Number(a.id_tipo_cita) - Number(b.id_tipo_cita));
                    renderizar();
                }
                if (datos.tipo && accion === 'guardar_tipo_cita_ajax') completar(tipoForm, datos.tipo);
            }, {
                fetch: global.fetch.bind(global),
                bloquear: bloqueado => raiz.querySelectorAll('input, select, button').forEach(elemento => { elemento.disabled = bloqueado; }),
                aviso: mensaje => { if (typeof global.zookiToast === 'function') global.zookiToast(mensaje); },
                error: datos => {
                    const juntoAlCampo = datos.campo && form.elements.namedItem(datos.campo);
                    form.querySelector('[data-aviso]').textContent = juntoAlCampo ? '' : (datos.message || 'No se pudo guardar.');
                    if (juntoAlCampo) mostrarError(form, datos.campo, datos.aviso || datos.message);
                }
            });
        }

        for (const form of [marcaForm, tipoForm]) {
            for (const entrada of form.querySelectorAll('[data-config-campo]')) {
                let temporizador;
                const validar = () => {
                    clearTimeout(temporizador);
                    const version = (versiones.get(entrada) || 0) + 1;
                    versiones.set(entrada, version);
                    const error = errorCampo(entrada.name, entrada.value, reglas);
                    mostrarError(form, entrada.name, error);
                    if (error) return;
                    const valor = entrada.value;
                    temporizador = setTimeout(async () => {
                        const cuerpo = new FormData();
                        cuerpo.set('campo', entrada.name);
                        cuerpo.set('valor', valor);
                        cuerpo.set('csrf_token', form.elements.namedItem('csrf_token').value);
                        try {
                            const respuesta = await global.fetch('index.php?action=validar_configuracion_clinica_ajax', { method: 'POST', body: cuerpo });
                            const datos = await respuesta.json();
                            if (versiones.get(entrada) !== version || entrada.value !== valor || estado.enviando) return;
                            mostrarError(form, entrada.name, respuesta.ok && datos.success !== false ? (datos.aviso || '') : (datos.message || 'No se pudo validar.'));
                        } catch (_) {
                            // El guardado vuelve a validar en servidor; un fallo de red no autoriza datos.
                        }
                    }, 400);
                };
                entrada.addEventListener('input', validar);
                entrada.addEventListener('change', validar);
            }
            form.addEventListener('submit', evento => {
                evento.preventDefault();
                if (estado.enviando) return;
                let valido = true;
                for (const entrada of form.querySelectorAll('[data-config-campo]')) {
                    const error = errorCampo(entrada.name, entrada.value, reglas);
                    mostrarError(form, entrada.name, error);
                    if (error) valido = false;
                }
                if (form === marcaForm && !logoUI.valido()) valido = false;
                if (!valido) return;
                guardar(form, new FormData(form), form === marcaForm ? 'guardar_marca_clinica_ajax' : 'guardar_tipo_cita_ajax');
            });
        }
        raiz.querySelector('[data-nuevo-tipo]').addEventListener('click', () => {
            if (estado.enviando) return;
            completar(tipoForm, {});
            abrirTipo('Nuevo tipo de cita');
        });
        renderizar();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})(typeof window !== 'undefined' ? window : globalThis);
