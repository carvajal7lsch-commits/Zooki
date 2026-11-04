/**
 * Tarjeta «Modo demo» del panel de plataforma (decisión del dueño, 2026-11-03).
 * Crear, preparar la escena o borrar llaman a SemillaDemo en el servidor.
 * Mientras una corre, los tres botones quedan deshabilitados; el servidor
 * también rechaza una segunda ejecución con su candado.
 * La clave se valida al escribir con la misma política del servidor
 * (password-policy.js, espejo de PoliticaPassword) y nunca se muestra.
 */
(function () {
    'use strict';
    const ETIQUETAS = {
        clinicas: 'Clínicas', usuarios: 'Cuentas', mascotas: 'Mascotas', usuario_clinica: 'Personal',
        propietario_clinica: 'Propietarios', mascota_clinica: 'Vínculos de mascotas', consultas: 'Consultas',
        vacunas: 'Vacunas', desparasitaciones: 'Desparasitaciones', alertas_medicas: 'Alertas médicas',
        citas: 'Citas', solicitudes_agenda: 'Solicitudes de agenda'
    };

    function aviso(campo, mensaje) {
        campo.setCustomValidity(mensaje);
        campo.setAttribute('aria-invalid', String(mensaje !== ''));
        const salida = campo.closest('label')?.querySelector('[data-error-campo]');
        if (salida) salida.textContent = mensaje;
        return mensaje === '';
    }

    function validarClave(campo) {
        const motivo = typeof window.motivoPasswordInvalida === 'function' ? window.motivoPasswordInvalida(campo.value) : '';
        return aviso(campo, motivo || '');
    }

    function validarDia(campo) {
        const valido = /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(campo.value) && !Number.isNaN(new Date(campo.value + 'T00:00:00Z').getTime());
        return aviso(campo, valido ? '' : 'Elige una fecha válida.');
    }

    function validar(form) {
        const dia = form.querySelector('[data-demo-dia]');
        const clave = form.querySelector('[data-demo-clave]');
        const resultados = [dia ? validarDia(dia) : true, clave ? validarClave(clave) : true];
        const invalido = form.querySelector('[aria-invalid="true"]');
        invalido?.focus();
        return resultados.every(Boolean);
    }

    function fila(lista, etiqueta, valor) {
        const dt = document.createElement('dt');
        dt.textContent = etiqueta;
        const dd = document.createElement('dd');
        dd.textContent = valor;
        lista.append(dt, dd);
    }

    function mostrar(destino, datos) {
        destino.replaceChildren();
        if (!datos.success) {
            const error = document.createElement('p');
            error.dataset.demoError = '';
            error.textContent = datos.message || 'No se pudo completar la operación.';
            destino.append(error);
            return;
        }
        const resumen = datos.resultado || {};
        const lista = document.createElement('dl');
        if (resumen.hora) fila(lista, 'Escena', (resumen.dia ? resumen.dia + ' · ' : '') + resumen.hora);
        Object.entries(resumen.niveles || {}).forEach(([nombre, nivel]) => {
            fila(lista, nombre, nivel.nivel + (nivel.revision ? ' · con revisión' : ''));
        });
        Object.entries(resumen.conteos || {}).forEach(([clave, valor]) => {
            fila(lista, ETIQUETAS[clave] || clave, String(valor));
        });
        (resumen.cuentas || []).forEach(cuenta => {
            fila(lista, cuenta.rol, cuenta.clinica + ' · ' + cuenta.correo);
        });
        const titulo = document.createElement('p');
        titulo.textContent = datos.message;
        destino.append(titulo, lista);
    }

    function ocupar(tarjeta, boton, ocupado) {
        tarjeta.querySelectorAll('[data-demo-boton]').forEach(otro => { otro.disabled = ocupado; });
        const texto = boton.querySelector('span');
        if (ocupado) {
            boton.dataset.textoOriginal = texto.textContent;
            texto.textContent = 'Preparando…';
        } else if (boton.dataset.textoOriginal) {
            texto.textContent = boton.dataset.textoOriginal;
        }
        if (ocupado) tarjeta.dataset.ocupado = '1';
        else delete tarjeta.dataset.ocupado;
    }

    async function enviar(form) {
        const tarjeta = form.closest('[data-demo]');
        if (tarjeta.dataset.ocupado === '1' || !validar(form)) return;
        if (form.dataset.demoConfirmar && !(await zookiConfirmarCorto('¿Borrar la demo?', 'Borrar demo', [], 'Cancelar', form.dataset.demoConfirmar))) return;
        const boton = form.querySelector('[data-demo-boton]');
        const destino = tarjeta.querySelector('[data-demo-resultado]');
        ocupar(tarjeta, boton, true);
        try {
            const respuesta = await fetch('index.php?action=' + form.dataset.demoAccion, { method: 'POST', body: new FormData(form) });
            const datos = await respuesta.json();
            if (datos.campo) {
                const campo = form.querySelector('[name="' + datos.campo + '"]');
                if (campo) aviso(campo, datos.aviso || datos.message);
            }
            mostrar(destino, datos);
            if (datos.success) {
                const clave = form.querySelector('[data-demo-clave]');
                if (clave) clave.value = '';
                window.zookiToast(datos.message);
            }
        } catch (error) {
            mostrar(destino, { success: false, message: 'No se pudo completar la operación. Revisa la conexión e inténtalo de nuevo.' });
        } finally {
            ocupar(tarjeta, boton, false);
        }
    }

    document.addEventListener('input', evento => {
        if (evento.target.matches?.('[data-demo-clave]')) validarClave(evento.target);
        if (evento.target.matches?.('[data-demo-dia]')) validarDia(evento.target);
    });
    document.addEventListener('submit', evento => {
        const form = evento.target;
        if (!form.dataset?.demoAccion) return;
        evento.preventDefault();
        enviar(form);
    });
}());
