/**
 * D2 y D2.1: avisos junto al campo mientras se escribe.
 *
 * En cada tecla se aplican al instante las reglas que el navegador puede
 * comprobar (formato, longitud, requisitos de la contraseña de
 * password-policy.js y coincidencia de contraseñas). Lo que solo sabe el
 * servidor (unicidad, política completa con los datos del titular) se
 * consulta con una pausa corta; mientras responde no se borra el aviso
 * vigente y una respuesta vieja nunca pisa la nueva. Al enviar se vuelve a
 * comprobar todo con el servidor.
 */
(function () {
    'use strict';
    const formularios = new WeakMap();
    const fuente = document.querySelector('script[data-cuenta-csrf]');
    const csrf = fuente?.dataset.cuentaCsrf || '';
    const PAUSA_MS = 400;
    const equivalencias = {
        nueva_password: 'password', password_nueva: 'password', new_password: 'password',
        perfilPwdNueva: 'password', portal_new_password: 'password',
        perfilEmail: 'email', perfilTelefono: 'telefono',
        confirmar_password: 'confirm_password', password_confirmation: 'confirm_password',
        perfilPwdConfirmar: 'confirm_password', portal_confirm_password: 'confirm_password'
    };
    const camposCuenta = new Set(['nombre_completo', 'documento', 'email', 'telefono', 'password', 'confirm_password']);
    // Solo el servidor sabe si existe la cuenta o si la contraseña contiene tus datos.
    const consultanServidor = new Set(['documento', 'email', 'password']);
    const NOMBRES_UNICOS = { documento: 'documento', email: 'correo' };

    // Mismos mensajes que helpers/ValidadorCuenta.php y ValidadorTelefono.
    const MENSAJES = {
        documento: 'El documento debe tener entre 5 y 15 dígitos.',
        email: 'Escribe un correo electrónico válido.',
        nombre_completo: 'El nombre debe tener entre 3 y 100 caracteres.',
        telefono: 'El teléfono solo puede tener números, espacios, + y guiones (de 7 a 20 caracteres).',
    };

    function tipo(campo) {
        return campo.dataset.cuentaCampo || equivalencias[campo.name || campo.id] || campo.name;
    }

    function campos(form) {
        return Array.from(form.querySelectorAll('input')).filter(campo => camposCuenta.has(tipo(campo))
            && !campo.disabled && !campo.readOnly && campo.type !== 'hidden');
    }

    function campoDe(form, clave) {
        return campos(form).find(elemento => tipo(elemento) === clave);
    }

    function aviso(campo, mensaje, error, bien = false) {
        // El contenedor visual del input también contiene el icono o el botón del ojo.
        // La ayuda va debajo de ese contenedor, nunca dentro de su fila.
        const envoltura = campo.closest('.input-wrapper, .search-input-wrapper, .perfil-clave, .input-con-ojito, .iti');
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
        const estilo = error ? ' cuenta-validacion--error' : (bien ? ' cuenta-validacion--ok' : '');
        salida.className = salida.dataset.cuentaClaseInicial + ' cuenta-validacion' + estilo;
        salida.textContent = mensaje || salida.dataset.cuentaTextoInicial;
        campo.setCustomValidity(error ? mensaje : '');
        campo.setAttribute('aria-invalid', error ? 'true' : 'false');
    }

    function cumplePatron(campo, valor) {
        if (!campo.pattern) return true;
        try {
            return new RegExp('^(?:' + campo.pattern + ')$', 'v').test(valor);
        } catch (error) {
            return new RegExp('^(?:' + campo.pattern + ')$').test(valor);
        }
    }

    /**
     * Regla del navegador para el valor actual. Devuelve { error, mensaje }:
     * error con texto si no cumple; mensaje positivo cuando aplica (las
     * contraseñas coinciden); null si cumple y no hay nada que decir.
     */
    function reglaLocal(form, campo, final) {
        const clave = tipo(campo);
        const valor = campo.value;
        if (valor === '') {
            return final && campo.required ? { error: 'Este campo es obligatorio.' } : null;
        }
        if (clave === 'confirm_password') {
            const password = campoDe(form, 'password');
            const iguales = !password || valor === password.value;
            return iguales ? { mensaje: 'Las contraseñas coinciden.' } : { error: 'Las contraseñas no coinciden.' };
        }
        if (clave === 'documento' && !/^\d{5,15}$/.test(valor.trim())) {
            return { error: MENSAJES.documento };
        }
        if (clave === 'email' && (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor.trim()) || valor.trim().length > 255)) {
            return { error: MENSAJES.email };
        }
        if (clave === 'nombre_completo' && (valor.trim().length < 3 || valor.trim().length > 100)) {
            return { error: MENSAJES.nombre_completo };
        }
        if (clave === 'telefono') {
            const limpio = valor.trim().replace(/\s+/g, ' ');
            const minimo = Number(campo.getAttribute('minlength')) || 7;
            if (limpio.length < minimo || !cumplePatron(campo, limpio)) {
                return { error: campo.title || MENSAJES.telefono };
            }
        }
        if (clave === 'password') {
            const motivo = typeof window.motivoPasswordInvalida === 'function'
                ? window.motivoPasswordInvalida(valor)
                : (valor.length < 8 ? 'Mínimo 8 caracteres' : null);
            if (motivo) return { error: motivo };
        }
        return null;
    }

    /** Aplica la regla local; si cumple y ya había un aviso del servidor, lo deja hasta que responda. */
    function mostrarLocal(form, campo, final = false) {
        const estado = formularios.get(form);
        const resultado = reglaLocal(form, campo, final);
        if (resultado?.error) {
            aviso(campo, resultado.error, true);
            estado.origen.set(campo, 'local');
            return false;
        }
        if (resultado?.mensaje) {
            aviso(campo, resultado.mensaje, false, true);
            estado.origen.set(campo, 'local');
            return true;
        }
        // Un campo vacío vuelve a su ayuda inicial: el aviso anterior ya no le aplica.
        if (campo.value === '' || estado.origen.get(campo) !== 'servidor') {
            aviso(campo, '', false);
            estado.origen.set(campo, 'local');
        }
        return true;
    }

    function mensajeDeExistencia(form, clave, existe) {
        const estado = formularios.get(form);
        const puedeVincular = !form.elements.id_usuario?.value && (form.dataset.cuentaVincular === 'true'
            || (form.dataset.cuentaVincular === 'correo' && (clave === 'email' || estado.correoExistente)));
        if (form.dataset.identidadAccion === 'cambiar_documento_ajax') {
            // RN-G24: el envío autenticado abre el caso de soporte; la ayuda no lo crea.
            return { error: null, mensaje: 'Documento registrado: al enviar se abrirá un caso para soporte.' };
        }
        if (puedeVincular) {
            return { error: null, mensaje: 'Cuenta existente: se vinculará a la clínica.' };
        }
        const mensaje = 'Este ' + NOMBRES_UNICOS[clave] + ' ya está registrado.';
        return { error: mensaje, mensaje };
    }

    async function comprobar(form, campo, final = false) {
        const estado = formularios.get(form);
        const clave = tipo(campo);
        const valor = campo.value;
        const numero = (estado.secuencias.get(campo) || 0) + 1;
        estado.secuencias.set(campo, numero);
        if (!mostrarLocal(form, campo, final)) return false;
        if (valor === '' || clave === 'confirm_password') return true;
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
        try {
            const respuesta = await fetch('index.php?action=validar_cuenta_ajax', { method: 'POST', body: datos });
            const resultado = await respuesta.json();
            // Una respuesta antigua nunca valida ni pisa el texto que el usuario ya reemplazó.
            if (estado.secuencias.get(campo) !== numero || campo.value !== valor) return false;
            if (!respuesta.ok || !resultado.success) throw new Error(resultado.message || 'No se pudo comprobar el campo.');
            let error = resultado.error;
            let mensaje = error || '';
            if (clave === 'email') estado.correoExistente = Boolean(resultado.exists);
            if (!error && resultado.exists && NOMBRES_UNICOS[clave]) {
                ({ error, mensaje } = mensajeDeExistencia(form, clave, resultado.exists));
            }
            aviso(campo, mensaje, Boolean(error));
            estado.origen.set(campo, mensaje ? 'servidor' : 'local');
            estado.cache.set(campo, { firma, valido: !error });
            form.dispatchEvent(new CustomEvent('cuenta:validada', { detail: { campo: clave, valido: !error, existe: resultado.exists } }));
            return !error;
        } catch (error) {
            if (estado.secuencias.get(campo) !== numero) return false;
            aviso(campo, error.message || 'No se pudo comprobar el campo.', true);
            estado.origen.set(campo, 'servidor');
            return false;
        }
    }

    /** Consulta al servidor tras una pausa; cada campo tiene su propio temporizador. */
    function programar(form, campo) {
        const estado = formularios.get(form);
        clearTimeout(estado.temporizadores.get(campo));
        estado.temporizadores.set(campo, setTimeout(() => comprobar(form, campo), PAUSA_MS));
    }

    function alEscribir(form, campo) {
        const estado = formularios.get(form);
        const clave = tipo(campo);
        estado.cache.delete(campo);
        // Un valor nuevo invalida cualquier respuesta pendiente del anterior.
        estado.secuencias.set(campo, (estado.secuencias.get(campo) || 0) + 1);
        if (clave === 'email') estado.correoExistente = false;
        const cumple = mostrarLocal(form, campo);
        if (cumple && campo.value !== '' && consultanServidor.has(clave)) {
            programar(form, campo);
        } else {
            clearTimeout(estado.temporizadores.get(campo));
        }
        // La confirmación depende de la contraseña: se revisa en la misma tecla.
        if (clave === 'password') {
            const confirmacion = campoDe(form, 'confirm_password');
            if (confirmacion && confirmacion.value) mostrarLocal(form, confirmacion);
        }
        // El documento y el correo forman parte de la política completa de la contraseña.
        if (['documento', 'email', 'nombre_completo'].includes(clave)) {
            const password = campoDe(form, 'password');
            if (password && password.value && reglaLocal(form, password) === null) {
                estado.cache.delete(password);
                programar(form, password);
            }
        }
    }

    function vincular(form) {
        if (formularios.has(form)) return;
        const estado = {
            cache: new WeakMap(), secuencias: new WeakMap(), temporizadores: new Map(), origen: new WeakMap(),
            listo: false, enviando: false, correoExistente: false,
        };
        formularios.set(form, estado);
        campos(form).forEach((campo, indice) => {
            if (!campo.id) campo.id = 'cuenta-' + Array.from(document.forms).indexOf(form) + '-' + indice;
        });
        form.addEventListener('input', evento => {
            if (campos(form).includes(evento.target)) alEscribir(form, evento.target);
        });
        form.addEventListener('focusout', evento => {
            if (campos(form).includes(evento.target)) comprobar(form, evento.target);
        });
        form.addEventListener('reset', () => {
            estado.cache = new WeakMap();
            estado.origen = new WeakMap();
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
            estado.temporizadores.forEach(temporizador => clearTimeout(temporizador));
            const resultados = [];
            // Secuencial: evita gastar simultáneamente el límite de comprobaciones.
            const orden = campos(form);
            if (form.dataset.cuentaVincular === 'correo') orden.sort((a, b) => Number(tipo(b) === 'email') - Number(tipo(a) === 'email'));
            for (const campo of orden) resultados.push(await comprobar(form, campo, true));
            estado.enviando = false;
            if (!resultados.every(Boolean) || !form.reportValidity()) return;
            estado.listo = true;
            // Revisión D2.1: el navegador ignora requestSubmit mientras dispara el submit
            // original (pasaba con las comprobaciones ya en caché): se envía en otra tarea.
            setTimeout(() => form.requestSubmit(evento.submitter || undefined), 0);
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
    window.ZookiValidacionCuenta = { vincular, comprobar, reglaLocal };
}());
