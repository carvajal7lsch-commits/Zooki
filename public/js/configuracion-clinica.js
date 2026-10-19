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
            if (!/^\d{1,10}$/.test(valor) || Number(valor) < (nombre === 'duracion_minutos' ? 1 : 0) || Number(valor) > reglas.enteroMax) return nombre === 'duracion_minutos' ? 'Duración entera positiva.' : 'Margen entero no negativo.';
        }
        if ((nombre === 'pausable' || nombre === 'activo') && !['0', '1'].includes(valor)) return 'Elige Sí o No.';
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
        const lista = raiz.querySelector('[data-lista-tipos]');
        const estado = { enviando: false };
        const versiones = new WeakMap();
        let tipos = JSON.parse(raiz.dataset.tipos);

        function mostrarError(form, campo, mensaje) {
            const aviso = form.querySelector(`[data-error-campo="${campo}"]`);
            const entrada = form.elements.namedItem(campo);
            if (aviso) aviso.textContent = mensaje;
            if (entrada) entrada.setAttribute('aria-invalid', mensaje ? 'true' : 'false');
        }

        function completar(form, datos) {
            for (const entrada of form.querySelectorAll('[data-config-campo], [name="id_tipo_cita"]')) {
                entrada.value = datos[entrada.name] ?? '';
                versiones.set(entrada, (versiones.get(entrada) || 0) + 1);
                mostrarError(form, entrada.name, '');
            }
            form.querySelector('[data-aviso]').textContent = '';
        }

        function renderizar() {
            lista.replaceChildren();
            for (const tipo of tipos) {
                const fila = document.createElement('article');
                fila.className = 'configuracion-tipo';
                const datos = document.createElement('div');
                const nombre = document.createElement('strong');
                nombre.textContent = tipo.nombre_tipo;
                const detalle = document.createElement('p');
                detalle.textContent = `${tipo.duracion_minutos} min · Margen ${tipo.margen_minutos} min · ${Number(tipo.pausable) ? 'Pausable' : 'No pausable'} · ${Number(tipo.activo) ? 'Activo' : 'Inactivo'}`;
                datos.append(nombre, detalle);
                const acciones = document.createElement('div');
                acciones.className = 'configuracion-tipo-acciones';
                for (const [texto, ejecutar] of [
                    ['Editar', () => {
                        completar(tipoForm, tipo);
                        tipoForm.querySelector('[data-tipo-titulo]').textContent = 'Editar tipo de cita';
                        tipoForm.elements.namedItem('nombre_tipo').focus();
                    }],
                    [Number(tipo.activo) ? 'Desactivar' : 'Reactivar', () => {
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
                    boton.className = 'horarios-btn horarios-btn--sec';
                    boton.textContent = texto;
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
                    const vista = raiz.querySelector('[data-vista-marca]');
                    vista.replaceChildren();
                    if (datos.marca.url_logo) {
                        const logo = document.createElement('img');
                        logo.className = 'marca-clinica-logo';
                        logo.src = datos.marca.url_logo;
                        logo.alt = `Logo de ${datos.marca.nombre}`;
                        vista.append(logo);
                    }
                    const texto = document.createElement('p');
                    texto.textContent = [datos.marca.nombre, datos.marca.direccion, datos.marca.telefono, datos.marca.email_contacto, datos.marca.telefono_urgencias].filter(Boolean).join(' · ');
                    vista.append(texto);
                }
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
                    form.querySelector('[data-aviso]').textContent = datos.message || 'No se pudo guardar.';
                    if (datos.campo && form.elements.namedItem(datos.campo)) mostrarError(form, datos.campo, datos.aviso || datos.message);
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
                if (!valido) return;
                guardar(form, new FormData(form), form === marcaForm ? 'guardar_marca_clinica_ajax' : 'guardar_tipo_cita_ajax');
            });
        }
        raiz.querySelector('[data-nuevo-tipo]').addEventListener('click', () => {
            if (estado.enviando) return;
            completar(tipoForm, {});
            tipoForm.querySelector('[data-tipo-titulo]').textContent = 'Nuevo tipo de cita';
            tipoForm.elements.namedItem('nombre_tipo').focus();
        });
        renderizar();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})(typeof window !== 'undefined' ? window : globalThis);
