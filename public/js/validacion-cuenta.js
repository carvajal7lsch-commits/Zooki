/**
 * D2 y D2.1: avisos junto al campo mientras se escribe.
 *
 * En cada tecla se aplican al instante las reglas que el navegador puede
 * comprobar (formato, longitud, requisitos de la contraseña de
 * password-policy.js y coincidencia de contraseñas). Lo que solo sabe el
 * servidor (formato y política completa con los datos del titular) se
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
    const camposCuenta = new Set(['nombre_completo', 'documento', 'email', 'telefono', 'password', 'confirm_password', 'nit', 'dv', 'nombre_clinica', 'direccion', 'email_clinica', 'email_administrador']);
    // El servidor comprueba formato y política completa; nunca informa si existe la cuenta.
    const consultanServidor = new Set(['documento', 'email', 'password', 'nit', 'dv', 'nombre_clinica', 'direccion', 'email_clinica', 'email_administrador']);
    const UNICOS = new Set(['documento', 'email']);

    // Mismos mensajes que helpers/ValidadorCuenta.php, ValidadorClinica y ValidadorTelefono (M0.1: una línea).
    const MENSAJES = {
        documento: 'Documento de 5 a 15 dígitos.',
        email: 'Escribe un correo válido.',
        nombre_completo: 'Nombre de 3 a 100 caracteres.',
        nombre_caracteres: "Nombre: letras, espacios, ' y -.",
        telefono: 'Teléfono de 7 a 20 caracteres.',
        nit: 'NIT de 1 a 14 dígitos, sin el DV.',
        dv: 'El DV es un solo dígito.',
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
        // M5 C4a: el teléfono con bandera queda dentro de .iti; el aviso va bajo el contenedor de afuera.
        const envoltura = campo.closest('.input-wrapper, .search-input-wrapper, .perfil-clave, .input-con-ojito') || campo.closest('.iti');
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
        // M0.1: el aviso ocupa una línea del ancho del campo; si no cabe, el texto completo queda al pasar el cursor.
        salida.title = salida.textContent;
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
    /**
     * M5 C4a: qué le falta al correo, con los mismos mensajes y el mismo orden
     * que ValidadorCuenta::correo() del servidor. null si el formato sirve.
     */
    function motivoCorreo(valor) {
        if (valor.length > 255) return 'El correo admite hasta 255 caracteres.';
        const arrobas = (valor.match(/@/g) || []).length;
        if (arrobas === 0) return 'Al correo le falta la @.';
        if (arrobas > 1) return 'El correo lleva una sola @.';
        const [usuario, dominio] = valor.split('@');
        if (usuario === '') return 'Escribe lo que va antes de la @.';
        if (!/^[^.\s]+(\.[^.\s]+)+$/.test(dominio)) return 'Escribe el dominio completo, por ejemplo gmail.com.';
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor) ? null : MENSAJES.email;
    }

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
        if (clave === 'email') {
            const motivo = motivoCorreo(valor.trim().toLowerCase());
            if (motivo) return { error: motivo };
            const actual = (campo.dataset.correoActual || '').toLowerCase();
            if (actual && valor.trim().toLowerCase() === actual) return { error: 'Ese ya es tu correo.' };
        }
        if (clave === 'nombre_completo' && (valor.trim().length < 3 || valor.trim().length > 100)) {
            return { error: MENSAJES.nombre_completo };
        }
        if (clave === 'nit' && !/^[0-9]{1,14}$/.test(valor.trim())) return { error: MENSAJES.nit };
        if (clave === 'dv' && !/^[0-9]$/.test(valor.trim())) return { error: MENSAJES.dv };
        if (clave === 'nombre_completo' && !/^[\p{L}\p{M} '’\-]+$/u.test(valor.trim())) {
            return { error: MENSAJES.nombre_caracteres };
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

    /**
     * D2.2 (RE-T.7.5): el alta de personal no pregunta si el documento o el
     * correo tienen cuenta; solo valida el formato. La respuesta del servidor
     * es la misma exista o no, así que el formulario tampoco lo revela.
     */
    function sinUnicidad(form, clave) {
        return UNICOS.has(clave) && form.dataset.cuentaUnicidad === 'solo-edicion' && !form.elements.id_usuario?.value;
    }

    async function comprobar(form, campo, final = false) {
        const estado = formularios.get(form);
        const clave = tipo(campo);
        const valor = campo.value;
        estado.limitados.delete(campo);
        const numero = (estado.secuencias.get(campo) || 0) + 1;
        estado.secuencias.set(campo, numero);
        if (!mostrarLocal(form, campo, final)) return false;
        if (valor === '' || clave === 'confirm_password' || sinUnicidad(form, clave)) return true;
        const firma = JSON.stringify([valor, ...campos(form).filter(elemento => ['documento', 'email', 'email_administrador', 'nombre_completo', 'nit', 'dv'].includes(tipo(elemento))).map(elemento => elemento.value), form.elements.id_usuario?.value]);
        if (estado.cache.get(campo)?.firma === firma) {
            return estado.cache.get(campo).valido;
        }
        const datos = new FormData();
        datos.set('cuenta_csrf', csrf);
        datos.set('campo', clave);
        datos.set('valor', valor);
        if (form.elements.nit) datos.set('nit', form.elements.nit.value);
        if (form.dataset.cuentaDatos === 'formulario') datos.set('solo_formulario', '1');
        campos(form).filter(elemento => ['documento', 'email', 'email_administrador', 'nombre_completo'].includes(tipo(elemento))).forEach(elemento => datos.set(tipo(elemento) === 'email_administrador' ? 'email' : tipo(elemento), elemento.value));
        ['id_usuario', 'id_enlace', 'token_enlace', 'token_id', 'token'].forEach(nombre => {
            if (form.elements[nombre]) datos.set(nombre, form.elements[nombre].value);
        });
        try {
            const respuesta = await fetch('index.php?action=validar_cuenta_ajax', { method: 'POST', body: datos });
            const resultado = await respuesta.json();
            // Una respuesta antigua nunca valida ni pisa el texto que el usuario ya reemplazó.
            if (estado.secuencias.get(campo) !== numero || campo.value !== valor) return false;
            if (respuesta.status === 429 && form.id === 'registroClinicaForm') {
                // RE-T.13.2: un freno temporal no invalida lo que la persona escribió.
                aviso(campo, '', false);
                estado.origen.set(campo, 'local');
                estado.cache.delete(campo);
                estado.limitados.add(campo);
                form.dispatchEvent(new CustomEvent('cuenta:limitada', { detail: {} }));
                return false;
            }
            estado.limitados.delete(campo);
            if (!respuesta.ok || !resultado.success) throw new Error(resultado.message || 'No se pudo comprobar el campo.');
            let error = resultado.error;
            let mensaje = error || '';
            aviso(campo, mensaje, Boolean(error));
            estado.origen.set(campo, mensaje ? 'servidor' : 'local');
            estado.cache.set(campo, { firma, valido: !error });
            form.dispatchEvent(new CustomEvent('cuenta:validada', { detail: { campo: clave, valido: !error, nitDuplicado: Boolean(resultado.nit_duplicado) } }));
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
        const cumple = mostrarLocal(form, campo);
        if (cumple && campo.value !== '' && consultanServidor.has(clave) && !sinUnicidad(form, clave)) {
            programar(form, campo);
        } else {
            clearTimeout(estado.temporizadores.get(campo));
        }
        // La confirmación depende de la contraseña: se revisa en la misma tecla.
        if (clave === 'password') {
            const confirmacion = campoDe(form, 'confirm_password');
            if (confirmacion && confirmacion.value) mostrarLocal(form, confirmacion);
        }
        if (clave === 'nit') {
            const dv = campoDe(form, 'dv');
            if (dv && dv.value) {
                estado.cache.delete(dv);
                estado.secuencias.set(dv, (estado.secuencias.get(dv) || 0) + 1);
                programar(form, dv);
            }
        }
        // El documento y el correo forman parte de la política completa de la contraseña.
        if (['documento', 'email', 'email_administrador', 'nombre_completo'].includes(clave)) {
            const password = campoDe(form, 'password');
            if (password && password.value && reglaLocal(form, password) === null) {
                estado.cache.delete(password);
                programar(form, password);
            }
        }
    }

    /**
     * M0.1: el resumen del formulario (registros) recibe los campos que no
     * pasaron, también los que solo valida el navegador (select, casilla), y
     * un formulario por pasos vuelve al paso del primero.
     */
    function avisarInvalidos(form, invalidos) {
        const nativos = Array.from(form.elements || []).filter(campo => campo.willValidate
            && typeof campo.checkValidity === 'function' && !campo.checkValidity() && !invalidos.includes(campo));
        const todos = invalidos.concat(nativos);
        if (todos.length === 0) return true;
        form.dispatchEvent(new CustomEvent('cuenta:invalida', { detail: { campos: todos } }));
        return false;
    }

    function vincular(form) {
        if (formularios.has(form)) return;
        const estado = {
            cache: new WeakMap(), secuencias: new WeakMap(), temporizadores: new Map(), origen: new WeakMap(),
            listo: false, enviando: false, limitados: new Set(),
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
            estado.limitados.clear();
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
            if (!avisarInvalidos(form, orden.filter((campo, indice) => !resultados[indice] && !estado.limitados.has(campo)))) {
                // Después del evento: un formulario por pasos ya mostró el paso del campo.
                form.reportValidity();
                return;
            }
            if (orden.some(campo => estado.limitados.has(campo))) return;
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
                        // M5 C4a: quien muestra el formulario en un modal lo cierra (portal.js).
                        form.dispatchEvent(new CustomEvent('cuenta:guardada', { bubbles: true, detail: datos }));
                    }
                } catch (error) {
                    salida.textContent = 'No se pudo completar la operación. Inténtalo nuevamente.';
                } finally {
                    boton.disabled = false;
                }
            });

        });
    });
    window.ZookiValidacionCuenta = { vincular, comprobar, reglaLocal, motivoCorreo };
}());
