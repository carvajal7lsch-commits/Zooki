/**
 * Teléfono con bandera y prefijo (intl-tel-input): el mismo en Usuarios,
 * Pacientes, el registro del propietario, «Completar perfil» y el Perfil del
 * portal (M5 C4a, punto 11). Antes cada pantalla repetía la configuración.
 *
 * Las reglas del servidor no cambian (ValidadorTelefono): el número sale con
 * su prefijo («+573001234567») y cabe en sus límites. El campo conserva su
 * maxlength, pattern y data-caracteres, así que la validación en tiempo real
 * y la restricción de entrada siguen igual.
 *
 * Un campo con data-telefono-internacional se prepara solo; las pantallas que
 * necesitan la instancia (Usuarios, Pacientes) llaman a zookiTelefono(campo).
 */
(function () {
    'use strict';

    const UTILIDADES = 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/utils.js';

    function opciones(campo) {
        return {
            initialCountry: 'co',
            preferredCountries: ['co', 'us', 'mx', 'es'],
            nationalMode: false,
            autoInsertDialCode: true,
            strictMode: true,
            separateDialCode: campo.dataset?.telefonoPrefijoSeparado === '1',
            // Un diálogo nativo deja inerte el body: su lista de países debe vivir en la misma ventana.
            dropdownContainer: campo.closest?.('dialog') || document.body,
            utilsScript: UTILIDADES,
        };
    }

    /** Prepara el campo una sola vez y devuelve su instancia (null sin la biblioteca). */
    function zookiTelefono(campo) {
        if (!campo || !window.intlTelInput) return null;
        if (campo.zookiTelefono) return campo.zookiTelefono;
        const iti = window.intlTelInput(campo, opciones(campo));
        campo.zookiTelefono = iti;
        return iti;
    }

    /** El número para enviar: con prefijo si es válido; si no, lo escrito (el servidor decide). */
    function zookiNumeroTelefono(campo) {
        if (!campo) return '';
        const iti = campo.zookiTelefono;
        if (iti && iti.isValidNumber()) return iti.getNumber();
        return String(campo.value || '').trim();
    }

    /** Antes de enviar el formulario, el campo lleva el número completo con su prefijo. */
    function prepararMarcado(campo) {
        if (!zookiTelefono(campo) || !campo.form) return;
        campo.form.addEventListener('submit', () => {
            campo.value = zookiNumeroTelefono(campo);
        }, true);
    }

    window.zookiTelefono = zookiTelefono;
    window.zookiNumeroTelefono = zookiNumeroTelefono;

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[data-telefono-internacional]').forEach(prepararMarcado);
    });
}());
