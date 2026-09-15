/**
 * Avisos al usuario (TR-02).
 *
 * ZOOKI_REGLAS §4 prohíbe alert() y prompt() nativos: bloquean la página, no se
 * pueden estilizar, y en varias llamadas del proyecto se usaban además con dos
 * argumentos, `alert(mensaje, "success")`, como si fueran un toast — el segundo
 * se descartaba en silencio.
 *
 * Va en su propio archivo y no dentro de extras.js porque lo necesitan los
 * cuatro layouts, incluido el portal del propietario, que no carga las
 * notificaciones push del navegador.
 *
 * Requiere SweetAlert2 cargado antes que este archivo. Si no está, cada función
 * degrada a consola en vez de romper el flujo.
 */

const ZOOKI_COLOR_OK = '#5560FF';
const ZOOKI_COLOR_ERR = '#EF4444';

/** Aviso breve, no bloqueante, en la esquina superior derecha. */
function zookiToast(mensaje, tipo = 'success') {
    if (!window.Swal) { console.log(`[${tipo}] ${mensaje}`); return; }

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: tipo,
        title: mensaje,
        showConfirmButton: false,
        timer: tipo === 'error' ? 4000 : 2600,
        timerProgressBar: true,
    });
}

/** Aviso que exige confirmación; para errores que el usuario debe leer. */
function zookiAviso(mensaje, tipo = 'error', titulo = null) {
    if (!window.Swal) { console.log(`[${tipo}] ${mensaje}`); return; }

    Swal.fire({
        icon: tipo,
        title: titulo || (tipo === 'error' ? 'Algo salió mal' : 'Listo'),
        text: mensaje,
        confirmButtonColor: tipo === 'error' ? ZOOKI_COLOR_ERR : ZOOKI_COLOR_OK,
    });
}

/** Confirmación sí/no. Devuelve una promesa que resuelve a booleano. */
async function zookiConfirmar(mensaje, titulo = '¿Confirmas?', textoBoton = 'Sí, continuar') {
    if (!window.Swal) return window.confirm(mensaje);

    const r = await Swal.fire({
        icon: 'question',
        title: titulo,
        text: mensaje,
        showCancelButton: true,
        confirmButtonText: textoBoton,
        cancelButtonText: 'Cancelar',
        confirmButtonColor: ZOOKI_COLOR_OK,
        cancelButtonColor: '#94A3B8',
    });

    return r.isConfirmed;
}

/**
 * Escapa texto para insertarlo en HTML sin abrir un XSS.
 *
 * Vive aquí porque lo necesitan tanto el portal como el módulo clínico, y
 * estaba declarada solo en portal.js: el área del veterinario no carga ese
 * archivo, así que cualquier uso desde allí habría dado ReferenceError.
 */
function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}
