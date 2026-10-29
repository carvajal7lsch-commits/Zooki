/** RE-7.4.1: elegir o soltar un logo prepara la vista previa; el formulario lo envía solo al Guardar. */
(function (global) {
    'use strict';

    function errorArchivo(archivo, maximo) {
        if (!archivo || !['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type)) return 'Elige una imagen JPG, PNG o WebP válida.';
        if (!archivo.size || archivo.size > maximo) return 'El logo debe pesar como máximo 5 MB.';
        return '';
    }

    function iniciar(form, reglas) {
        const entrada = form.querySelector('[data-logo-archivo]');
        const zona = form.querySelector('[data-logo-soltar]');
        const vista = form.querySelector('[data-logo-vista]');
        const icono = form.querySelector('[data-logo-icono]');
        const cancelar = form.querySelector('[data-logo-cancelar]');
        const aviso = form.querySelector('[data-error-campo="logo_archivo"]');
        let temporal = null;
        let correcto = true;

        function error(mensaje) {
            correcto = !mensaje;
            aviso.textContent = mensaje;
            entrada.setAttribute('aria-invalid', mensaje ? 'true' : 'false');
            zona.setAttribute('aria-invalid', mensaje ? 'true' : 'false');
        }

        function liberar() {
            if (temporal) global.URL.revokeObjectURL(temporal);
            temporal = null;
        }

        function mostrar(url) {
            vista.hidden = !url;
            icono.hidden = !!url;
            if (url) vista.src = url;
            else vista.removeAttribute('src');
        }

        function restablecer() {
            vista.onload = null;
            vista.onerror = null;
            liberar();
            entrada.value = '';
            cancelar.hidden = true;
            error('');
            mostrar(form.elements.namedItem('url_logo').value);
        }

        function elegir(archivos) {
            if (zona.disabled) return;
            if (!archivos.length) return;
            const mensaje = archivos.length > 1 ? 'Elige una sola imagen para el logo.' : errorArchivo(archivos[0], reglas.logoMaxBytes);
            if (mensaje) {
                error(mensaje);
                cancelar.hidden = false;
                return;
            }
            liberar();
            const transferencia = new global.DataTransfer();
            transferencia.items.add(archivos[0]);
            entrada.files = transferencia.files;
            correcto = false;
            cancelar.hidden = false;
            temporal = global.URL.createObjectURL(archivos[0]);
            vista.onload = () => error('');
            vista.onerror = () => error('No se puede leer esa imagen. Elige otro logo.');
            mostrar(temporal);
        }

        zona.addEventListener('click', () => entrada.click());
        entrada.addEventListener('change', () => elegir(entrada.files));
        for (const evento of ['dragenter', 'dragover']) zona.addEventListener(evento, accion => {
            accion.preventDefault();
            if (!zona.disabled) zona.dataset.arrastrando = 'true';
        });
        zona.addEventListener('dragleave', () => { delete zona.dataset.arrastrando; });
        zona.addEventListener('drop', evento => {
            evento.preventDefault();
            delete zona.dataset.arrastrando;
            elegir(evento.dataTransfer?.files || []);
        });
        // Soltar fuera del área tampoco debe sacar al dueño del formulario y abrir el archivo como página.
        form.addEventListener('dragover', evento => evento.preventDefault());
        form.addEventListener('drop', evento => evento.preventDefault());
        cancelar.addEventListener('click', restablecer);
        form.closest('dialog').addEventListener('close', restablecer);
        restablecer();
        return { valido: () => correcto, restablecer };
    }

    global.LogoClinicaUI = { iniciar, errorArchivo };
    if (typeof module !== 'undefined') module.exports = { iniciar, errorArchivo };
})(typeof window !== 'undefined' ? window : globalThis);
