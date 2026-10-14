/**
 * M5 C2b — Formularios de mascota del portal (registrar y editar).
 *
 * Cada campo se valida mientras se escribe, con un aviso de una línea debajo
 * (M0.1) y nunca en una ventana. Las reglas llegan en el propio campo,
 * generadas por ValidadorMascota::atributosHtml(): required, maxlength,
 * minlength, pattern, data-maximo y el aviso corto en data-aviso-formato. El
 * servidor valida lo mismo y, si rechaza, dice el campo y su aviso (CampoInvalido).
 * Los campos clínicos no usan las reglas de cuenta (validacion-cuenta.js).
 */
(function () {
    function cumplePatron(campo, valor) {
        if (!campo.pattern) return true;
        return new RegExp('^(?:' + campo.pattern + ')$', 'u').test(valor);
    }

    function obligatorio(form) {
        return form.dataset.avisoObligatorio || 'Este campo es obligatorio.';
    }

    /**
     * El aviso del campo, o '' si cumple. `final` es al salir del campo o al
     * enviar: solo entonces un campo vacío y obligatorio se avisa (no mientras
     * se empieza a escribir).
     */
    function regla(campo, final) {
        const form = campo.form;
        if (campo.disabled || campo.type === 'hidden' || campo.type === 'file') return '';
        if (campo.type === 'radio') {
            const marcado = Array.from(form.querySelectorAll(`[name="${campo.name}"]`)).some(r => r.checked);
            return !marcado && campo.required && final ? (campo.dataset.avisoFormato || obligatorio(form)) : '';
        }
        const valor = String(campo.value || '').trim();
        if (valor === '') {
            if (!campo.required || !final) return '';
            return campo.tagName === 'SELECT' ? (campo.dataset.avisoFormato || obligatorio(form)) : obligatorio(form);
        }
        const formato = campo.dataset.avisoFormato || obligatorio(form);
        if (campo.maxLength > 0 && valor.length > campo.maxLength) return formato;
        if (campo.minLength > 0 && valor.length < campo.minLength && final) return formato;
        if (!cumplePatron(campo, valor)) return formato;
        if (campo.dataset.maximo !== undefined) {
            const numero = Number(valor.replace(',', '.'));
            if (!(numero > 0) || numero > Number(campo.dataset.maximo)) return formato;
        }
        return '';
    }

    /** La línea de aviso reservada bajo el campo (o el grupo de radios). */
    function avisoDe(campo) {
        const form = campo.form;
        const primero = campo.type === 'radio' ? form.querySelector(`[name="${campo.name}"]`) : campo;
        return document.getElementById(primero.id + '_aviso');
    }

    function marcar(campo, mensaje) {
        const aviso = avisoDe(campo);
        if (aviso) {
            aviso.textContent = mensaje;
            aviso.title = mensaje;
            aviso.classList.toggle('cuenta-validacion--error', mensaje !== '');
        }
        if (typeof campo.setAttribute === 'function') campo.setAttribute('aria-invalid', mensaje ? 'true' : 'false');
    }

    function revisar(campo, final) {
        const mensaje = regla(campo, final);
        marcar(campo, mensaje);
        return mensaje === '';
    }

    function campos(form) {
        const vistos = new Set();
        return Array.from(form.elements).filter(campo => {
            if (!campo.name || campo.type === 'hidden' || campo.type === 'file' || campo.tagName === 'BUTTON') return false;
            if (campo.type === 'radio') {
                if (vistos.has(campo.name)) return false;
                vistos.add(campo.name);
            }
            return true;
        });
    }

    function resumen(form, texto) {
        const salida = form.querySelector('[data-resumen-mascota]');
        if (!salida) return;
        salida.textContent = texto;
        salida.hidden = texto === '';
    }

    /** Todos los campos al enviar; el foco va al primero que no cumple. */
    function validar(form) {
        resumen(form, '');
        const invalidos = campos(form).filter(campo => !revisar(campo, true));
        if (invalidos.length && typeof invalidos[0].focus === 'function') invalidos[0].focus();
        return invalidos.length === 0;
    }

    /** El rechazo del servidor: el aviso corto bajo su campo y el detalle en el resumen. */
    function mostrarServidor(form, respuesta) {
        const campo = respuesta.campo ? form.querySelector(`[name="${respuesta.campo}"]`) : null;
        if (campo && campo.type === 'file') {
            const ayuda = form.querySelector('.foto-campo__ayuda');
            if (ayuda) {
                ayuda.textContent = respuesta.aviso || respuesta.message || '';
                ayuda.classList.add('is-error');
            }
        } else if (campo) {
            marcar(campo, respuesta.aviso || respuesta.message || '');
            if (typeof campo.focus === 'function') campo.focus();
        }
        resumen(form, respuesta.message || 'No se pudo guardar. Revisa los datos.');
    }

    function limpiar(form) {
        campos(form).forEach(campo => marcar(campo, ''));
        resumen(form, '');
    }

    document.addEventListener('input', (e) => {
        if (e.target.form && e.target.form.matches('[data-validacion-mascota]')) revisar(e.target, false);
    });
    document.addEventListener('change', (e) => {
        if (e.target.form && e.target.form.matches('[data-validacion-mascota]')) revisar(e.target, true);
    });
    document.addEventListener('focusout', (e) => {
        if (e.target.form && e.target.form.matches('[data-validacion-mascota]')) revisar(e.target, true);
    });

    window.ZookiValidacionMascota = { regla, marcar, validar, mostrarServidor, limpiar };
})();
