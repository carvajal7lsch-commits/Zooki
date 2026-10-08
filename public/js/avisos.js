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
    if (!window.Swal) {
        console.log(`[${tipo}] ${mensaje}`);
        return Promise.resolve();
    }

    return Swal.fire({
        toast: true,
        position: 'top-end',
        icon: tipo,
        title: mensaje,
        showConfirmButton: false,
        timer: tipo === 'error' ? 4000 : 2600,
        timerProgressBar: true,
    });
}

/**
 * C9.1: recarga en cuanto responde el servidor y muestra el aviso al volver.
 *
 * Antes se esperaba a que el toast se cerrara (2–3 s) para recargar, y la
 * pantalla parecía no haber hecho nada. El aviso viaja en sessionStorage; si
 * el navegador no lo permite, se muestra igual y la recarga no espera.
 */
const ZOOKI_AVISO_PENDIENTE = 'zooki.avisoPendiente';

function zookiRecargarConAviso(mensaje, tipo = 'success') {
    try {
        sessionStorage.setItem(ZOOKI_AVISO_PENDIENTE, JSON.stringify({ mensaje, tipo }));
    } catch (error) {
        zookiToast(mensaje, tipo);
    }
    window.location.reload();
}

function zookiMostrarAvisoPendiente() {
    let aviso = null;
    try {
        aviso = JSON.parse(sessionStorage.getItem(ZOOKI_AVISO_PENDIENTE) || 'null');
        sessionStorage.removeItem(ZOOKI_AVISO_PENDIENTE);
    } catch (error) {
        return;
    }
    if (aviso && typeof aviso.mensaje === 'string' && aviso.mensaje !== '') {
        const tipos = ['success', 'error', 'warning', 'info'];
        zookiToast(aviso.mensaje, tipos.includes(aviso.tipo) ? aviso.tipo : 'success');
    }
}

document.addEventListener('DOMContentLoaded', zookiMostrarAvisoPendiente);

/** Aviso que exige confirmación; para errores que el usuario debe leer. */
function zookiAviso(mensaje, tipo = 'error', titulo = null) {
    if (!window.Swal) {
        console.log(`[${tipo}] ${mensaje}`);
        return;
    }

    Swal.fire({
        icon: tipo,
        title: titulo || (tipo === 'error' ? 'Algo salió mal' : 'Listo'),
        text: mensaje,
        confirmButtonColor: tipo === 'error' ? ZOOKI_COLOR_ERR : ZOOKI_COLOR_OK,
    });
}

/** Confirmación sí/no. Devuelve una promesa que resuelve a booleano. */
async function zookiConfirmar(mensaje, titulo = '¿Confirmas?', textoBoton = 'Sí, continuar') {
    if (!window.Swal) {
        console.error('No está disponible la confirmación de la acción.');
        return false;
    }

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
