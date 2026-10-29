/* S2: el navegador solo lleva a Wompi y consulta evidencia local; nunca acredita.
   Rediseño (2026-10-26): el pago usa el checkout web de Wompi a pantalla completa en vez del
   widget en ventana; vuelve por suscripcion_retorno con la misma referencia firmada. */
(() => {
    'use strict';
    const CHECKOUT_WEB = 'https://checkout.wompi.co/p/';

    // Mismos datos que firmó el servidor (PagoSuscripcion::reservar); sin ellos no se sale de Zooki.
    function urlCheckout(checkout) {
        const integridad = checkout?.signature?.integrity;
        if (!checkout?.publicKey || !checkout.reference || !integridad || !Number.isSafeInteger(checkout.amountInCents)) {
            throw new Error('No se pudo preparar el pago. Consulta el estado antes de intentarlo de nuevo.');
        }
        const parametros = new URLSearchParams({
            'public-key': checkout.publicKey,
            currency: checkout.currency,
            'amount-in-cents': String(checkout.amountInCents),
            reference: checkout.reference,
            'signature:integrity': integridad,
            'redirect-url': checkout.redirectUrl,
        });
        if (checkout.expirationTime) parametros.set('expiration-time', checkout.expirationTime);
        return CHECKOUT_WEB + '?' + parametros.toString();
    }

    function iniciar(raiz, servicios = {}) {
        if (!raiz) return;
        const boton = raiz.querySelector('.suscripcion__pagar');
        const consultar = raiz.querySelector('.suscripcion__consultar');
        const mensaje = raiz.querySelector('#suscripcionMensaje');
        const solicitar = servicios.fetch || globalThis.fetch;
        const redirigir = servicios.redirigir || (url => globalThis.location.assign(url));
        const habilitado = raiz.dataset?.pagoHabilitado === '1';
        let ocupado = false;
        let saliendo = false;
        let referencia = '';
        const avisar = texto => {
            if (mensaje) mensaje.textContent = texto;
        };
        if (boton) boton.disabled = !habilitado;
        async function estado() {
            try {
                const url = 'index.php?action=suscripcion_estado_ajax' + (referencia ? '&referencia=' + encodeURIComponent(referencia) : '');
                const respuesta = await solicitar(url, {credentials:'same-origin', headers:{Accept:'application/json'}});
                const datos = await respuesta.json();
                if (!respuesta.ok || !datos.ok) throw new Error('No se pudo consultar el pago.');
                const pago = datos.estado.pago;
                const etiquetas = {al_dia:'Al día',gracia:'En gracia',congelada:'Beneficios congelados',sin_vigencia:'Sin vigencia de pago'};
                for (const [selector,texto] of [['[data-suscripcion-plan]',datos.estado.plan],['[data-suscripcion-fin]',datos.estado.fin_incluido || 'Sin fecha'],['[data-suscripcion-estado]',etiquetas[datos.estado.situacion]]]) {
                    const nodo = raiz.querySelector(selector);
                    if (nodo && texto) nodo.textContent = texto;
                }
                let texto = 'Plan: ' + datos.estado.plan + ' · ' + (etiquetas[datos.estado.situacion] || 'Consulta el estado') + '.';
                if (pago) {
                    const estadosOrden = { pendiente: 'pendiente', confirmado: 'confirmada', cancelado: 'cancelada', revision: 'en revisión' };
                    texto += ' Última orden: ' + (estadosOrden[pago.estado] || 'en revisión') + '.';
                    const estadosIntento = { creado: 'sin confirmación', incierto: 'sin confirmación', enviando: 'en proceso', pendiente: 'pendiente en Wompi', aprobado: 'aprobado', anulado: 'anulado', declinado: 'rechazado', error: 'con error' };
                    texto += ' Intento: ' + (estadosIntento[pago.intento] || 'en revisión') + '.';
                    if (pago.estado === 'pendiente' && ['creado', 'incierto'].includes(pago.intento)) {
                        texto += pago.cotizacion_vencida
                            ? ' La cotización venció sin confirmación del pago. Al volver a pagar comprobaremos el intento con Wompi.'
                            : ' Aún no hay confirmación del pago.';
                    }
                }
                avisar(texto);
                return datos;
            } catch (error) {
                avisar('No se pudo consultar. La orden sigue reservada; vuelve a consultar antes de pagar.');
            }
        }
        async function pagar() {
            if (ocupado || !habilitado) return;
            ocupado = true;
            boton.disabled = true;
            avisar('Preparando el pago…');
            try {
                const ciclo = raiz.querySelector('input[name="suscripcion_ciclo"]:checked')?.value;
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const formulario = new URLSearchParams({ciclo:ciclo || '', version:raiz.dataset.version, csrf_token:token, desde:raiz.dataset.suscripcionOrigen || ''});
                const respuesta = await solicitar('index.php?action=suscripcion_pagar_ajax', {method:'POST', credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':token}, body:formulario});
                const datos = await respuesta.json();
                if (!respuesta.ok || !datos.ok) throw new Error(datos.mensaje || 'No se pudo preparar el pago.');
                avisar(datos.pago.mensaje);
                if (datos.pago.abrir) {
                    referencia = datos.pago.checkout.reference;
                    const url = urlCheckout(datos.pago.checkout);
                    avisar('Abriendo Wompi…');
                    saliendo = true;
                    redirigir(url);
                }
            } catch (error) {
                avisar(error.message || 'No se pudo abrir Wompi. Consulta el estado antes de intentarlo de nuevo.');
            } finally {
                // Una reapertura, incluso desde otra pestaña, obtiene la misma obligación del servidor.
                ocupado = false;
                boton.disabled = !habilitado || saliendo;
            }
        }
        boton?.addEventListener('click', pagar);
        consultar?.addEventListener('click', estado);
        // RE-0.5.17: Atrás puede restaurar el botón bloqueado al salir hacia Wompi; solo consultar.
        const eventos = servicios.eventos || globalThis;
        eventos.addEventListener?.('pageshow', evento => {
            if (!evento.persisted) return;
            saliendo = false;
            ocupado = false;
            if (boton) boton.disabled = !habilitado;
            return estado();
        });
        return {pagar, estado};
    }
    if (typeof module !== 'undefined') module.exports = { iniciar, urlCheckout };
    if (typeof document !== 'undefined') iniciar(document.querySelector('[data-suscripcion-pantalla]'));
})();
