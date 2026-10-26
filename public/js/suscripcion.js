/* S2: el navegador abre el Widget; solo consulta evidencia local, nunca acredita. */
(() => {
    'use strict';
    function iniciar(raiz, servicios = {}) {
        if (!raiz) return;
        const boton = raiz.querySelector('.suscripcion__pagar');
        const consultar = raiz.querySelector('.suscripcion__consultar');
        const mensaje = raiz.querySelector('#suscripcionMensaje');
        const solicitar = servicios.fetch || globalThis.fetch;
        const widget = servicios.widget || (datos => new globalThis.WidgetCheckout(datos));
        const habilitado = raiz.dataset?.pagoHabilitado === '1';
        let ocupado = false;
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
                avisar(pago ? 'Pago: ' + pago.estado + ' · ' + pago.intento + '. Actualiza para ver el historial completo.' : 'Plan: ' + datos.estado.plan + ' · ' + (etiquetas[datos.estado.situacion] || 'Consulta el estado') + '.');
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
                const formulario = new URLSearchParams({ciclo:ciclo || '', version:raiz.dataset.version, csrf_token:token});
                const respuesta = await solicitar('index.php?action=suscripcion_pagar_ajax', {method:'POST', credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':token}, body:formulario});
                const datos = await respuesta.json();
                if (!respuesta.ok || !datos.ok) throw new Error(datos.mensaje || 'No se pudo preparar el pago.');
                avisar(datos.pago.mensaje);
                if (datos.pago.abrir) {
                    referencia = datos.pago.checkout.reference;
                    const checkout = widget(datos.pago.checkout);
                    checkout.open(() => {
                        avisar('La ventana terminó. Consultando la confirmación…');
                        estado();
                    });
                }
            } catch (error) {
                avisar(error.message || 'No se pudo abrir Wompi. Consulta el estado antes de intentarlo de nuevo.');
            } finally {
                // Una reapertura, incluso desde otra pestaña, obtiene la misma obligación del servidor.
                ocupado = false;
                boton.disabled = !habilitado;
            }
        }
        boton?.addEventListener('click', pagar);
        consultar?.addEventListener('click', estado);
        return {pagar, estado};
    }
    if (typeof module !== 'undefined') module.exports = { iniciar };
    if (typeof document !== 'undefined') iniciar(document.querySelector('[data-suscripcion-pantalla]'));
})();
