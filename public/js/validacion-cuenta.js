/** D2: avisos junto al campo; las reglas finales se consultan al servidor. */
(function () {
    'use strict';
    const formularios = new WeakMap();
    const fuente = document.querySelector('script[data-cuenta-csrf]');
    const csrf = fuente?.dataset.cuentaCsrf || '';
    const equivalencias = {
        nueva_password: 'password', password_nueva: 'password', new_password: 'password',
        perfilPwdNueva: 'password', portal_new_password: 'password',
        perfilEmail: 'email', perfilTelefono: 'telefono',
        confirmar_password: 'confirm_password', password_confirmation: 'confirm_password',
        perfilPwdConfirmar: 'confirm_password', portal_confirm_password: 'confirm_password'
    };
    const camposCuenta = new Set(['nombre_completo', 'documento', 'email', 'telefono', 'password', 'confirm_password']);

    function tipo(campo) {
        return campo.dataset.cuentaCampo || equivalencias[campo.name || campo.id] || campo.name;
    }

    function campos(form) {
        return Array.from(form.querySelectorAll('input')).filter(campo => camposCuenta.has(tipo(campo))
            && !campo.disabled && !campo.readOnly && campo.type !== 'hidden');
    }

    function aviso(campo, mensaje, error) {
        // El contenedor visual del input también contiene el icono o el botón del ojo.
        // La ayuda va debajo de ese contenedor, nunca dentro de su fila.
        const envoltura = campo.closest('.input-wrapper, .search-input-wrapper, .perfil-clave, .input-con-ojito');
        const contenedor = envoltura ? envoltura.parentElement : campo.parentElement;
        let salida = campo.dataset.cuentaAyuda ? document.getElementById(campo.dataset.cuentaAyuda) : null;
        salida ??= contenedor.querySelector('[data-cuenta-error="' + campo.id + '"]');
        if (!salida) {
            salida = document.createElement('small');
            salida.dataset.cuentaError = campo.id;
            salida.id = campo.id + '-cuenta-error';
            salida.setAttribute('aria-live', 'polite');
            contenedor.appendChild(salida);
        }
        const descripciones = (campo.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
        campo.setAttribute('aria-describedby', Array.from(new Set([...descripciones, salida.id])).join(' '));
        if (salida.dataset.cuentaTextoInicial === undefined) {
            salida.dataset.cuentaTextoInicial = salida.textContent || '';
            salida.dataset.cuentaClaseInicial = salida.className || '';
        }
        salida.className = salida.dataset.cuentaClaseInicial + ' cuenta-validacion' + (error ? ' cuenta-validacion--error' : '');
        salida.textContent = mensaje || salida.dataset.cuentaTextoInicial;
        campo.setCustomValidity(error ? mensaje : '');
        campo.setAttribute('aria-invalid', error ? 'true' : 'false');
    }

    async function comprobar(form, campo, final = false) {
        const estado = formularios.get(form);
        const clave = tipo(campo);
        const valor = campo.value;
        const numero = (estado.secuencias.get(campo) || 0) + 1;
        estado.secuencias.set(campo, numero);
        if (valor === '') {
            aviso(campo, final && campo.required ? 'Este campo es obligatorio.' : '', final && campo.required);
            return !campo.required;
        }
        if (clave === 'confirm_password') {
            const password = campos(form).find(elemento => tipo(elemento) === 'password');
            const error = password && valor !== password.value;
            aviso(campo, error ? 'Las contraseñas no coinciden.' : '', Boolean(error));
            return !error;
        }
        const firma = JSON.stringify([valor, ...campos(form).filter(elemento => ['documento', 'email', 'nombre_completo'].includes(tipo(elemento))).map(elemento => elemento.value), form.elements.id_usuario?.value, estado.correoExistente]);
        if (estado.cache.get(campo)?.firma === firma) {
            return estado.cache.get(campo).valido;
        }
        const datos = new FormData();
        datos.set('cuenta_csrf', csrf);
        datos.set('campo', clave);
        datos.set('valor', valor);
        campos(form).filter(elemento => ['documento', 'email', 'nombre_completo'].includes(tipo(elemento))).forEach(elemento => datos.set(tipo(elemento), elemento.value));
        ['id_usuario', 'id_enlace', 'token_enlace', 'token_id', 'token'].forEach(nombre => {
            if (form.elements[nombre]) datos.set(nombre, form.elements[nombre].value);
        });
        aviso(campo, 'Comprobando…', false);
        try {
            const respuesta = await fetch('index.php?action=validar_cuenta_ajax', { method: 'POST', body: datos });
            const resultado = await respuesta.json();
            // Una respuesta antigua nunca valida el texto que el usuario ya reemplazó.
            if (estado.secuencias.get(campo) !== numero || campo.value !== valor) return false;
            if (!respuesta.ok || !resultado.success) throw new Error(resultado.message || 'No se pudo comprobar el campo.');
            let error = resultado.error;
            let mensaje = error || '';
            if (clave === 'email') estado.correoExistente = Boolean(resultado.exists);
            const puedeVincular = !form.elements.id_usuario?.value && (form.dataset.cuentaVincular === 'true'
                || (form.dataset.cuentaVincular === 'correo' && (clave === 'email' || estado.correoExistente)));
            if (!error && resultado.exists && ['documento', 'email'].includes(clave)) {
                mensaje = puedeVincular ? 'Cuenta existente: se vinculará a la clínica.' : 'Este dato ya está registrado.';
                error = puedeVincular ? null : mensaje;
            }
            if (form.dataset.identidadAccion === 'cambiar_documento_ajax' && resultado.exists && !resultado.error) {
                // RN-G24: el envío autenticado abre el caso de soporte; la ayuda no lo crea.
                error = null;
                mensaje = 'Documento registrado: al enviar se abrirá un caso para soporte.';
            }
            aviso(campo, mensaje, Boolean(error));
            estado.cache.set(campo, { firma, valido: !error });
            form.dispatchEvent(new CustomEvent('cuenta:validada', { detail: { campo: clave, valido: !error, existe: resultado.exists } }));
            return !error;
        } catch (error) {
            if (estado.secuencias.get(campo) !== numero) return false;
            aviso(campo, error.message || 'No se pudo comprobar el campo.', true);
            return false;
        }
    }

    function vincular(form) {
        if (formularios.has(form)) return;
        const estado = { cache: new WeakMap(), secuencias: new WeakMap(), temporizador: null, listo: false, enviando: false, correoExistente: false };
        formularios.set(form, estado);
        campos(form).forEach((campo, indice) => {
            if (!campo.id) campo.id = 'cuenta-' + Array.from(document.forms).indexOf(form) + '-' + indice;
        });
        form.addEventListener('input', evento => {
            if (!campos(form).includes(evento.target)) return;
            estado.cache.delete(evento.target);
            if (tipo(evento.target) === 'email') estado.correoExistente = false;
            aviso(evento.target, '', false);
            clearTimeout(estado.temporizador);
            estado.temporizador = setTimeout(async () => {
                await comprobar(form, evento.target);
                if (['documento', 'email', 'nombre_completo', 'password'].includes(tipo(evento.target))) {
                    for (const campo of campos(form).filter(elemento => ['password', 'confirm_password'].includes(tipo(elemento)) && elemento.value)) {
                        await comprobar(form, campo);
                    }
                }
            }, 400);
        });
        form.addEventListener('focusout', evento => {
            if (campos(form).includes(evento.target)) comprobar(form, evento.target);
        });
        form.addEventListener('reset', () => {
            estado.cache = new WeakMap();
            estado.correoExistente = false;
            campos(form).forEach(campo => aviso(campo, '', false));
        });
        form.addEventListener('submit', async evento => {
            if (estado.listo) {
                estado.listo = false;
                return;
            }
            evento.preventDefault();
            evento.stopImmediatePropagation();
            if (estado.enviando) return;
            estado.enviando = true;
            clearTimeout(estado.temporizador);
            const resultados = [];
            // Secuencial: evita gastar simultáneamente el límite de comprobaciones.
            const orden = campos(form);
            if (form.dataset.cuentaVincular === 'correo') orden.sort((a, b) => Number(tipo(b) === 'email') - Number(tipo(a) === 'email'));
            for (const campo of orden) resultados.push(await comprobar(form, campo, true));
            estado.enviando = false;
            if (!resultados.every(Boolean) || !form.reportValidity()) return;
            estado.listo = true;
            form.requestSubmit(evento.submitter || undefined);
        }, true);
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[data-validacion-cuenta]').forEach(vincular);
        document.querySelectorAll('[data-identidad-accion]').forEach(form => {
            form.addEventListener('submit', async evento => {
                evento.preventDefault();
                const salida = form.querySelector('[data-identidad-resultado]');
                const boton = evento.submitter || form.querySelector('button[type="submit"]');
                boton.disabled = true;
                try {
                    const respuesta = await fetch('index.php?action=' + form.dataset.identidadAccion, { method: 'POST', body: new FormData(form) });
                    const datos = await respuesta.json();
                    salida.textContent = datos.message;
                    if (datos.success) {
                        if (datos.documento) {
                            document.querySelectorAll('[data-documento-cuenta]').forEach(elemento => { elemento.textContent = datos.documento; });
                        }
                        form.reset();
                    }
                } catch (error) {
                    salida.textContent = 'No se pudo completar la operación. Inténtalo nuevamente.';
                } finally {
                    boton.disabled = false;
                }
            });
            const googleBoton = form.querySelector('[data-confirmar-google]');
            googleBoton?.addEventListener('click', () => {
                const salida = form.querySelector('[data-identidad-resultado]');
                if (!window.google?.accounts?.oauth2 || !form.dataset.googleClient) {
                    salida.textContent = 'No se pudo cargar Google. Usa tu contraseña o vuelve a intentar.';
                    return;
                }
                window.google.accounts.oauth2.initTokenClient({
                    client_id: form.dataset.googleClient,
                    scope: 'openid email',
                    callback: resultado => {
                        form.elements.access_token.value = resultado.access_token || '';
                        salida.textContent = resultado.access_token ? 'Identidad de Google recibida. Envía el cambio para verificarla.' : 'No se pudo confirmar con Google.';
                    }
                }).requestAccessToken({ prompt: 'select_account' });
            });
        });
    });
    window.ZookiValidacionCuenta = { vincular, comprobar };
}());
