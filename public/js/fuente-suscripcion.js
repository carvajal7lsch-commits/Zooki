/* RE-0.5.18/19: el medio se captura únicamente en la ventana hospedada de Wompi. */
(() => {
    'use strict';
    function iniciar(raiz, servicios = {}) {
        if (!raiz) return;
        const buscar = nombre => raiz.querySelector('[data-' + nombre + ']');
        const solicitar = servicios.fetch || globalThis.fetch;
        const widget = servicios.widget || (datos => new globalThis.WidgetCheckout(datos));
        const toast = servicios.toast || (texto => globalThis.zookiToast?.(texto, 'success'));
        const confirmar = servicios.confirmar || (() => globalThis.zookiConfirmarCorto?.('¿Quitar el medio de pago? Los próximos cobros se detendrán.', 'Quitar medio'));
        const refrescar = servicios.refrescar || (() => {});
        const botones = ['fuente-preparar','fuente-guardar','automatico-autorizar','automatico-retirar','fuente-quitar','nequi-comprobar'];
        let preparacion = null;
        let pendiente = null;
        let fuente = null;
        let ocupado = false;
        const aviso = texto => {
            buscar('fuente-mensaje').textContent = texto;
            // Rediseño (2026-10-26): el mensaje también se ve en la tarjeta cuando la ventana está cerrada.
            const tarjeta = buscar('fuente-mensaje-tarjeta');
            if (tarjeta) tarjeta.textContent = texto;
        };
        const aceptadas = () => !!buscar('acepta-politica')?.checked && !!buscar('acepta-datos')?.checked;
        function validar() {
            if (buscar('fuente-guardar')) buscar('fuente-guardar').disabled = ocupado || !preparacion || !aceptadas();
            if (buscar('automatico-autorizar')) buscar('automatico-autorizar').disabled = ocupado || !preparacion || !aceptadas() || !buscar('acepta-mensual')?.checked
                || !fuente || fuente.estado !== 'disponible' || raiz.dataset.automaticoHabilitado !== '1';
            if (buscar('fuente-validacion')) buscar('fuente-validacion').textContent = preparacion && !aceptadas() ? 'Acepta las dos condiciones para guardar o autorizar.' : '';
        }
        function actualizar(datos) {
            if (!Object.hasOwn(datos, 'fuente')) return;
            fuente = datos.fuente;
            buscar('fuente-resumen').textContent = fuente ? fuente.tipo === 'NEQUI' ? 'Nequi' : (fuente.marca || 'Tarjeta') + (fuente.ultimos_cuatro ? ' · termina en ' + fuente.ultimos_cuatro : '') : 'No tienes un medio guardado.';
            buscar('fuente-estado').textContent = fuente ? fuente.estado === 'disponible' ? 'Disponible' : 'No disponible' : '';
            buscar('automatico-estado').textContent = fuente && Number(fuente.cobro_automatico) === 1 ? 'Cobro mensual autorizado.' : 'Cobro automático desactivado.';
            buscar('automatico-retirar').hidden = !fuente || Number(fuente.cobro_automatico) !== 1;
            buscar('fuente-quitar').hidden = !fuente;
            validar();
        }
        async function peticion(accion, datos = null) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const opciones = {credentials:'same-origin',headers:{Accept:'application/json'}};
            if (datos !== null) {
                opciones.method = 'POST';
                opciones.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                opciones.headers['X-CSRF-Token'] = csrf;
                opciones.body = new URLSearchParams({...datos,csrf_token:csrf});
            }
            const respuesta = await solicitar('index.php?action=' + accion, opciones);
            const cuerpo = await respuesta.json();
            if (!respuesta.ok || !cuerpo.ok) throw new Error(cuerpo.mensaje || 'No se pudo completar la solicitud.');
            return cuerpo.resultado;
        }
        async function operacion(trabajo) {
            if (ocupado) return;
            ocupado = true;
            const anteriores = botones.map(nombre => [buscar(nombre), buscar(nombre)?.disabled]);
            for (const [boton] of anteriores) {
                if (boton) boton.disabled = true;
            }
            try {
                return await trabajo();
            } catch (error) {
                aviso(error.message || 'No se pudo completar la solicitud.');
            } finally {
                ocupado = false;
                for (const [boton,anterior] of anteriores) {
                    if (boton) boton.disabled = anterior;
                }
                validar();
            }
        }
        async function preparar() {
            return operacion(async () => {
                preparacion = await peticion('suscripcion_fuente_preparar_ajax');
                if (!Number.isSafeInteger(preparacion.importe) || preparacion.importe <= 0) throw new Error('La tarifa necesita revisión.');
                const precio = '$' + (preparacion.importe / 100).toLocaleString('es-CO') + ' COP';
                buscar('importe-mensual').textContent = precio;
                buscar('mandato-importe').textContent = precio;
                for (const [nombre,contrato] of [['contrato-politica','acceptance_token'],['contrato-datos','accept_personal_auth']]) {
                    const enlace = preparacion.contratos[contrato].enlace;
                    if (!enlace.startsWith('https://')) throw new Error('No se pudieron consultar las condiciones.');
                    buscar(nombre).href = enlace;
                }
                for (const nombre of ['acepta-politica','acepta-datos','acepta-mensual']) buscar(nombre).checked = false;
                buscar('fuente-condiciones').hidden = false;
                actualizar(preparacion);
                aviso('Lee y acepta las condiciones. Guardar no autoriza cobros.');
            });
        }
        function prueba() {
            if (!preparacion || !aceptadas()) throw new Error('Acepta las dos condiciones de Wompi.');
            return {nonce:preparacion.nonce,acepta_politica:'1',acepta_datos:'1'};
        }
        async function guardarToken(medio) {
            return operacion(async () => {
                const datos = await peticion('suscripcion_fuente_ajax', {...prueba(),tipo:medio.type,token:medio.token});
                pendiente = datos.pendiente ? medio : null;
                buscar('nequi-comprobar').hidden = !pendiente;
                actualizar(datos);
                aviso(datos.mensaje);
                if (!pendiente) {
                    buscar('acepta-mensual').checked = false;
                    toast(datos.mensaje);
                    refrescar();
                }
            });
        }
        async function guardar() {
            return operacion(async () => {
                prueba();
                widget({publicKey:preparacion.publicKey,widgetOperation:'tokenize'}).open(resultado => {
                    const medio = resultado?.payment_source;
                    if (medio && ['CARD','NEQUI'].includes(medio.type) && typeof medio.token === 'string') guardarToken(medio);
                    else aviso('No se guardó ningún medio. Puedes volver a intentarlo.');
                });
            });
        }
        async function automatico(autorizar) {
            return operacion(async () => {
                if (autorizar && !buscar('acepta-mensual').checked) throw new Error('Autoriza expresamente el importe mensual.');
                const datos = await peticion('suscripcion_automatico_ajax', {autorizar:autorizar?'1':'0',...(autorizar?{...prueba(),acepta_mensual:'1'}:{})});
                actualizar(datos);
                aviso(datos.mensaje);
                toast(datos.mensaje);
            });
        }
        async function quitar() {
            if (ocupado) return;
            // Única confirmación grave de esta pantalla; retirar el mandato es directo.
            if (!await confirmar()) return;
            return operacion(async () => {
                const datos = await peticion('suscripcion_retirar_fuente_ajax', {});
                pendiente = null;
                buscar('nequi-comprobar').hidden = true;
                actualizar(datos);
                aviso(datos.mensaje);
                toast(datos.mensaje);
            });
        }
        const acciones = {'fuente-preparar':preparar,'fuente-guardar':guardar,'automatico-autorizar':()=>automatico(true),'automatico-retirar':()=>automatico(false),
            'fuente-quitar':quitar,'nequi-comprobar':()=>pendiente && guardarToken(pendiente)};
        for (const [nombre,accion] of Object.entries(acciones)) buscar(nombre)?.addEventListener('click',accion);
        for (const nombre of ['acepta-politica','acepta-datos','acepta-mensual']) buscar(nombre)?.addEventListener('change',validar);
        return {preparar,guardar,guardarToken,automatico,quitar,validar};
    }
    if (typeof module !== 'undefined') module.exports = {iniciar};
    if (typeof document !== 'undefined') iniciar(document.querySelector('[data-fuente-pantalla]'));
})();
