/** RE-4.14.4/6: respuesta explícita del destino, doble envío y descarte de lecturas obsoletas. */
(function (raiz) {
    function crearControl() {
        let turno = 0;
        const enviando = new Set();
        const comandos = new Map();
        return {
            iniciarConsulta: () => ++turno,
            vigente: numero => numero === turno,
            tomar(id) {
                if (enviando.has(id)) return false;
                enviando.add(id);
                return true;
            },
            liberar: id => enviando.delete(id),
            comando(id, respuesta) {
                const indice = id + ':' + respuesta;
                if (!comandos.has(indice)) comandos.set(indice, raiz.zookiCrearComandoAgenda());
                return comandos.get(indice);
            }
        };
    }

    function iniciar() {
        const bloque = document.querySelector('[data-reasignaciones-agenda]');
        if (!bloque) return;
        const control = crearControl();
        const lista = bloque.querySelector('[data-reasignaciones-lista]');
        const mensaje = bloque.querySelector('[data-reasignaciones-mensaje]');

        async function cargar() {
            const numero = control.iniciarConsulta();
            try {
                const r = await (await fetch('index.php?action=listar_reasignaciones_ajax')).json();
                if (!control.vigente(numero)) return;
                if (!r.success) throw new Error(r.message || 'No se pudieron consultar las propuestas.');
                lista.replaceChildren();
                for (const propuesta of r.propuestas) {
                    const tarjeta = document.createElement('article');
                    tarjeta.className = 'ah-item ah-item--fila';
                    const texto = document.createElement('p');
                    texto.className = 'ah-item__texto';
                    const partes = [
                        ['nombre', propuesta.mascota],
                        ['hora', 'Nueva hora estimada: ' + propuesta.inicio_propuesto],
                        ['plazo', 'Responde antes de ' + propuesta.fecha_limite]
                    ];
                    partes.forEach(([clase, valor], indice) => {
                        if (indice) {
                            const separador = document.createElement('span');
                            separador.className = 'agenda-solo-escritorio';
                            separador.textContent = ' · ';
                            texto.append(separador);
                        }
                        const dato = document.createElement('span');
                        dato.className = 'agenda-reasignacion__' + clase;
                        const completo = document.createElement('span');
                        completo.className = 'agenda-solo-escritorio';
                        completo.textContent = valor;
                        const compacto = document.createElement('span');
                        compacto.className = 'agenda-solo-movil';
                        compacto.textContent = valor.replace(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})(?::\d{2})?/, (_, anio, mes, dia, hora) => `${dia}/${mes} · ${hora}`);
                        dato.append(completo, compacto);
                        texto.append(dato);
                    });
                    tarjeta.append(texto);
                    for (const respuesta of ['aceptar', 'rechazar']) {
                        const boton = document.createElement('button');
                        boton.type = 'button';
                        boton.className = respuesta === 'aceptar' ? 'zk-btn zk-btn--mini' : 'zk-btn zk-btn--sec zk-btn--mini';
                        boton.textContent = respuesta === 'aceptar' ? 'Aceptar' : 'Rechazar';
                        boton.addEventListener('click', async () => {
                            const id = String(propuesta.id_reasignacion);
                            if (!control.tomar(id)) return;
                            const botones = tarjeta.querySelectorAll('button');
                            botones.forEach(b => { b.disabled = true; });
                            const forma = new FormData();
                            forma.append('id_reasignacion', id);
                            forma.append('respuesta', respuesta);
                            forma.append('clave_operacion', control.comando(id, respuesta).clave(forma));
                            try {
                                const resultado = await (await fetch('index.php?action=responder_reasignacion_ajax', { method: 'POST', body: forma })).json();
                                mensaje.textContent = resultado.message || 'Respuesta registrada.';
                                await cargar();
                                if (typeof calendarInstance !== 'undefined') calendarInstance?.refetchEvents();
                            } catch (error) {
                                mensaje.textContent = 'No se recibió la respuesta. Reintenta la misma decisión.';
                            } finally {
                                control.liberar(id);
                                botones.forEach(b => { b.disabled = false; });
                            }
                        });
                        tarjeta.append(boton);
                    }
                    lista.append(tarjeta);
                }
                // Sin propuestas la lista queda vacía y el CSS muestra data-vacio.
            } catch (error) {
                if (control.vigente(numero)) mensaje.textContent = error.message || 'No se pudieron consultar las propuestas.';
            }
        }
        bloque.querySelector('[data-reasignaciones-actualizar]').addEventListener('click', cargar);
        cargar();
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { crearControl };
    else document.addEventListener('DOMContentLoaded', iniciar);
})(globalThis);
