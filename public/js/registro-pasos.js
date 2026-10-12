/**
 * M0.1 — Registro de clínica en tres pasos (HU-0.1): 1) clínica, 2)
 * administrador, 3) política y envío.
 *
 * Los pasos solo ocultan fieldsets del mismo formulario: sin JavaScript se
 * ven los tres y el formulario se envía completo; el servidor valida todo
 * igual. Cada paso valida lo suyo antes de avanzar (las mismas reglas de
 * validacion-cuenta.js y las del navegador) y volver atrás no borra nada.
 */
(function () {
    'use strict';

    function pasos(form) {
        return Array.from(form.querySelectorAll('[data-paso]'));
    }

    function actual(form) {
        return Number(form.dataset.pasoActual || 1);
    }

    function indicador(form) {
        return document.getElementById(form.dataset.registroPasos);
    }

    /** M0.2: la flecha junto al título es el único «Atrás», como en el registro del propietario. */
    function atras(form) {
        return document.getElementById(form.dataset.registroAtras);
    }

    function rotularAtras(form, numero) {
        const flecha = atras(form);
        if (!flecha) return;
        const texto = numero > 1 ? 'Volver al paso anterior' : 'Volver al inicio de sesión';
        flecha.setAttribute('title', texto);
        flecha.setAttribute('aria-label', texto);
    }

    function camposDe(paso) {
        return Array.from(paso.querySelectorAll('input, select, textarea'))
            .filter(campo => campo.type !== 'hidden' && !campo.disabled);
    }

    /** Muestra el paso, marca el indicador y lleva el foco a su primer campo. */
    function ir(form, numero, enfocar = true) {
        pasos(form).forEach(paso => {
            paso.hidden = Number(paso.dataset.paso) !== numero;
        });
        const lista = indicador(form);
        const items = lista ? Array.from(lista.querySelectorAll('[data-paso-indicador]')) : [];
        items.forEach(item => {
            const suyo = Number(item.dataset.pasoIndicador);
            item.classList.toggle('activo', suyo === numero);
            item.classList.toggle('hecho', suyo < numero);
            if (suyo === numero) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        });
        form.dataset.pasoActual = String(numero);
        rotularAtras(form, numero);
        const visible = pasos(form).find(paso => Number(paso.dataset.paso) === numero);
        const primero = visible ? camposDe(visible)[0] : null;
        if (enfocar && primero) primero.focus({ preventScroll: true });
    }

    /** true si todos los campos del paso cumplen; si no, se queda y avisa. */
    async function validarPaso(form, numero) {
        const paso = pasos(form).find(elemento => Number(elemento.dataset.paso) === numero);
        if (!paso) return true;
        const validacion = window.ZookiValidacionCuenta;
        const invalidos = [];
        for (const campo of camposDe(paso)) {
            const deCuenta = validacion && campo.closest('[data-validacion-cuenta]') === form && campo.name !== 'acepta_datos';
            const valido = deCuenta && campo.tagName === 'INPUT'
                ? await validacion.comprobar(form, campo, true)
                : campo.checkValidity();
            if (!valido) invalidos.push(campo);
        }
        if (invalidos.length === 0) {
            window.ZookiRegistro?.limpiarResumen(form);
            return true;
        }
        form.dispatchEvent(new CustomEvent('cuenta:invalida', { detail: { campos: invalidos } }));
        invalidos[0].focus();
        return false;
    }

    function vincular(form) {
        const total = pasos(form).length;
        if (total === 0) return;
        form.classList.add('registro-por-pasos');
        const lista = indicador(form);
        if (lista) lista.hidden = false;
        form.addEventListener('click', async evento => {
            const boton = evento.target.closest('[data-paso-ir]');
            if (!boton || !form.contains(boton)) return;
            evento.preventDefault();
            const destino = actual(form) + (boton.dataset.pasoIr === 'siguiente' ? 1 : -1);
            if (destino < 1 || destino > total) return;
            if (destino > actual(form)) {
                boton.disabled = true;
                const valido = await validarPaso(form, actual(form));
                boton.disabled = false;
                if (!valido) return;
            }
            ir(form, destino);
        });
        // En el paso 1 la flecha sigue su enlace al login (con el giro de acceso-giro.js).
        const flecha = atras(form);
        if (flecha) {
            flecha.addEventListener('click', evento => {
                if (actual(form) <= 1) return;
                evento.preventDefault();
                ir(form, actual(form) - 1);
            });
        }
        // Un campo inválido al enviar (por ejemplo, el NIT que el servidor rechazó) vuelve a su paso.
        form.addEventListener('cuenta:invalida', evento => {
            const campo = evento.detail.campos[0];
            const paso = campo ? campo.closest('[data-paso]') : null;
            if (paso && Number(paso.dataset.paso) !== actual(form)) ir(form, Number(paso.dataset.paso));
        });
        // Al abrir la página no se enfoca nada: en el celular abriría el teclado.
        ir(form, 1, false);
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[data-registro-pasos]').forEach(vincular);
    });

    window.ZookiPasos = { vincular, ir, validarPaso };
}());
