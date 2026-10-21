(function (global) {
    'use strict';

    function errores(datos) {
        const resultado = {};
        const instante = valor => /^\d{4}-\d\d-\d\dT\d\d:\d\d(?::\d\d)?$/.test(valor) && Number.isFinite(Date.parse(valor));
        if (!instante(datos.inicio || '')) resultado.inicio = 'Escribe fecha y hora válidas.';
        if (!instante(datos.fin || '')) resultado.fin = 'Escribe fecha y hora válidas.';
        if (!resultado.inicio && !resultado.fin && datos.fin <= datos.inicio) resultado.fin = 'El fin debe ser posterior al inicio.';
        if (Array.from(datos.motivo || '').length > 255 || /\p{C}/u.test(datos.motivo || '')) resultado.motivo = 'Máximo 255 caracteres de texto.';
        return resultado;
    }

    function clave() {
        return Array.from(global.crypto.getRandomValues(new Uint8Array(32)), n => n.toString(16).padStart(2, '0')).join('');
    }

    const enviar = typeof module !== 'undefined' && module.exports ? require('./configuracion-clinica.js').enviar : global.ConfiguracionClinicaUI.enviar;
    if (typeof module !== 'undefined' && module.exports) module.exports = { errores, enviar };
    if (typeof document === 'undefined') return;

    function iniciar() {
        const raiz = document.querySelector('[data-ausencias-agenda]');
        if (!raiz) return;
        const form = raiz.querySelector('[data-form-ausencia]');
        const lista = raiz.querySelector('[data-lista-ausencias]');
        const estado = { enviando: false };
        const admin = raiz.dataset.admin === '1';
        const comandos = new WeakMap();
        let lectura = 0;

        const ESTADOS = { solicitada: 'Solicitada', aprobada: 'Aprobada', rechazada: 'Rechazada' };
        const fecha = valor => {
            const m = String(valor || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T]?(\d{2}:\d{2})?/);
            return m ? `${m[3]}/${m[2]}/${m[1]}${m[4] ? ' ' + m[4] : ''}` : String(valor || '');
        };

        function texto(padre, etiqueta, contenido) {
            const nodo = document.createElement(etiqueta);
            nodo.textContent = contenido;
            padre.append(nodo);
            return nodo;
        }

        function validar(f) {
            const datos = Object.fromEntries(new FormData(f));
            const fallos = f === form ? errores(datos) : (!datos.motivo.trim() ? { motivo: 'Indica el motivo del rechazo.' } : errores({ inicio: '2030-01-01T00:00', fin: '2030-01-02T00:00', motivo: datos.motivo }));
            // Pulido de interfaz: el error de un campo aparece cuando ya se tocó o se intentó enviar, no al abrir.
            f.querySelectorAll('[data-error-campo]').forEach(nodo => {
                const campo = f.elements.namedItem(nodo.dataset.errorCampo);
                const visible = f.dataset.intento === '1' || (campo && campo.dataset.tocado === '1');
                nodo.textContent = visible ? (fallos[nodo.dataset.errorCampo] || '') : '';
            });
            const boton = f.querySelector('[type="submit"]');
            boton.disabled = estado.enviando || Object.keys(fallos).length > 0;
            return !Object.keys(fallos).length;
        }

        async function guardar(f, accion, id) {
            f.dataset.intento = '1';
            if (estado.enviando || !validar(f)) return;
            const cuerpo = new FormData(f);
            cuerpo.set('id_clinica', raiz.dataset.clinica);
            if (id) cuerpo.set('id_ausencia', id);
            // La misma carga conserva su clave ante respuesta perdida; editar inicia un comando nuevo.
            const huella = JSON.stringify(Array.from(cuerpo.entries()));
            const previo = comandos.get(f);
            const comando = previo && previo.huella === huella && previo.accion === accion ? previo : { huella, accion, clave: clave() };
            comandos.set(f, comando);
            cuerpo.set('clave_operacion', comando.clave);
            await enviar(estado, cuerpo, accion, datos => {
                comandos.delete(f);
                f.querySelector('[data-aviso]').textContent = datos.message;
                if (f === form && global.zookiCerrarDialogo) {
                    global.zookiCerrarDialogo(f);
                    if (global.zookiToast) global.zookiToast(datos.message);
                }
                recargar();
                global.dispatchEvent(new Event('agenda:ausencia'));
            }, {
                fetch: global.fetch.bind(global),
                bloquear: valor => {
                    raiz.querySelectorAll('button,input,select').forEach(e => { e.disabled = valor; });
                    if (!valor) raiz.querySelectorAll('form').forEach(validar);
                },
                aviso: () => {},
                error: datos => { f.querySelector('[data-aviso]').textContent = datos.message || 'No se pudo guardar.'; }
            });
        }

        function preparar(f) {
            f.addEventListener('input', evento => {
                if (evento.target.name) evento.target.dataset.tocado = '1';
                f.querySelectorAll('input[name="motivo"]').forEach(e => { e.value = e.value.replace(/\p{C}/gu, '').slice(0, 255); });
                validar(f);
            });
            validar(f);
        }

        async function recargar() {
            const turno = ++lectura;
            try {
                const respuesta = await global.fetch('index.php?action=listar_ausencias_ajax');
                const datos = await respuesta.json();
                if (turno !== lectura) return;
                if (!respuesta.ok || datos.success === false) throw new Error(datos.message || 'No se pudieron consultar las ausencias.');
                lista.replaceChildren();
                for (const a of datos.ausencias) {
                    const tarjeta = texto(lista, 'article', '');
                    tarjeta.className = 'coord-item';
                    texto(tarjeta, 'strong', `${a.veterinario} · ${ESTADOS[a.estado] || a.estado}`);
                    texto(tarjeta, 'p', `${fecha(a.fecha_hora_inicio)} → ${fecha(a.fecha_hora_fin)}`);
                    if (a.motivo) texto(tarjeta, 'p', a.motivo);
                    if (!admin || a.estado !== 'solicitada') continue;
                    const resolver = document.createElement('form');
                    resolver.noValidate = true;
                    const csrf = form.querySelector('input[type="hidden"]').cloneNode(true);
                    resolver.append(csrf);
                    const motivo = document.createElement('input');
                    motivo.name = 'motivo';
                    motivo.maxLength = 255;
                    motivo.setAttribute('data-espacios', 'normalizar');
                    const etiqueta = texto(resolver, 'label', 'Motivo del rechazo');
                    etiqueta.append(motivo);
                    const error = texto(resolver, 'span', '');
                    error.dataset.errorCampo = 'motivo';
                    error.className = 'coordinacion-error';
                    const rechazar = texto(resolver, 'button', 'Rechazar');
                    rechazar.type = 'submit';
                    const aprobar = texto(resolver, 'button', 'Aprobar');
                    aprobar.type = 'button';
                    resolver.className = 'coord-item__acciones';
                    rechazar.className = 'coord-btn coord-btn--sec coord-btn--mini';
                    aprobar.className = 'coord-btn coord-btn--mini';
                    motivo.placeholder = 'Motivo si la rechazas';
                    texto(resolver, 'p', '').setAttribute('data-aviso', '');
                    resolver.addEventListener('submit', e => {
                        e.preventDefault();
                        guardar(resolver, 'rechazar_ausencia_ajax', a.id);
                    });
                    aprobar.addEventListener('click', () => {
                        // Aprobar no exige motivo; se conserva la misma política de doble envío y replay.
                        motivo.value = motivo.value.trim() || 'Aprobación administrativa';
                        guardar(resolver, 'aprobar_ausencia_ajax', a.id);
                    });
                    tarjeta.append(resolver);
                    preparar(resolver);
                }
            } catch (error) {
                if (turno === lectura) form.querySelector('[data-aviso]').textContent = error.message;
            }
        }

        preparar(form);
        form.addEventListener('submit', e => {
            e.preventDefault();
            guardar(form, 'registrar_ausencia_ajax');
        });
        if (admin) {
            global.fetch('index.php?action=get_coordinacion_horarios_ajax').then(r => r.json()).then(datos => {
                if (datos.success === false) throw new Error(datos.message);
                const selector = form.elements.namedItem('id_veterinario');
                for (const vet of datos.veterinarios) {
                    const opcion = texto(selector, 'option', vet.nombre_completo);
                    opcion.value = vet.id_usuario;
                }
            }).catch(error => { form.querySelector('[data-aviso]').textContent = error.message; });
        }
        recargar();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})(typeof window !== 'undefined' ? window : globalThis);
