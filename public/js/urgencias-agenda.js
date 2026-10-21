(function (global) {
    'use strict';

    function errorCampo(nombre, valor, requerido, reglas) {
        const texto = String(valor || '').trim();
        if (!texto) return requerido ? 'Este campo es obligatorio.' : '';
        if (['motivo', 'decision', 'evidencia', 'motivo_conservacion'].includes(nombre) && (Array.from(texto).length > 255 || /\p{C}/u.test(texto))) return 'Máximo 255 caracteres de texto.';
        if (nombre === 'nombre_acompanante' && (Array.from(texto).length < 3 || Array.from(texto).length > reglas.nombreMax || !new RegExp(`^[${reglas.nombreCaracteres}]+$`, 'u').test(texto))) return 'Nombre de 3 a 100 caracteres.';
        if (nombre === 'telefono_acompanante' && (!/^[0-9+ \-]+$/.test(texto) || texto.length < reglas.telefonoMin || texto.length > reglas.telefonoMax)) return 'Revisa el teléfono.';
        if (['nombre', 'nombre_mascota'].includes(nombre) && (Array.from(texto).length > reglas.mascotaMax || !/^[\p{L}\p{N}][\p{L}\p{N} .'\-]*$/u.test(texto))) return 'Revisa el nombre de la mascota.';
        if (nombre === 'peso' && (!/^\d{1,3}([.,]\d{1,2})?$/.test(texto) || Number(texto.replace(',', '.')) <= 0 || Number(texto.replace(',', '.')) > 150)) return 'Peso mayor que 0 y hasta 150 kg.';
        if (nombre === 'fecha_nacimiento') {
            const hoy = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Bogota' }).format(new Date());
            const minimo = new Date(`${hoy}T12:00:00Z`);
            minimo.setUTCFullYear(minimo.getUTCFullYear() - 40);
            if (!/^\d{4}-\d\d-\d\d$/.test(texto) || !Number.isFinite(Date.parse(texto)) || texto > hoy || texto < minimo.toISOString().slice(0,10)) return 'Revisa la fecha de nacimiento.';
        }
        return '';
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { errorCampo };
    if (typeof document === 'undefined') return;

    function iniciar() {
        const raiz = document.querySelector('[data-urgencias-agenda]');
        if (!raiz) return;
        const reglas = JSON.parse(raiz.dataset.reglas);
        const vet = raiz.dataset.vet === '1';
        const aviso = raiz.querySelector('[data-urgencias-aviso]');
        const comandos = new WeakMap();
        let escribiendo = false;
        let lectura = 0;
        let catalogos;

        function texto(padre, etiqueta, contenido) {
            const nodo = document.createElement(etiqueta);
            nodo.textContent = contenido;
            padre.append(nodo);
            return nodo;
        }

        function opciones(selector, lista, id, nombre) {
            selector.replaceChildren();
            const vacia = texto(selector, 'option', 'Elige');
            vacia.value = '';
            lista.forEach(dato => {
                const opcion = texto(selector, 'option', dato[nombre] || `Ficha #${dato[id]}`);
                opcion.value = dato[id];
            });
        }

        function campo(form, nombre, etiqueta, requerido = false, tipo = 'text') {
            const label = texto(form, 'label', etiqueta);
            const entrada = document.createElement(tipo === 'select' ? 'select' : 'input');
            entrada.name = nombre;
            entrada.required = requerido;
            if (tipo !== 'select') {
                entrada.type = tipo;
                entrada.maxLength = 255;
                entrada.dataset.espacios = 'normalizar';
                entrada.dataset.caracteres = '\\P{C}';
            }
            label.append(entrada);
            const error = texto(label, 'span', '');
            error.dataset.errorCampo = nombre;
            error.setAttribute('aria-live', 'polite');
            return entrada;
        }

        function validar(form) {
            let valido = true;
            form.querySelectorAll('[name]').forEach(entrada => {
                if (entrada.type === 'hidden' || entrada.disabled || entrada.closest('fieldset[disabled]')) return;
                const error = errorCampo(entrada.name, entrada.value, entrada.required, reglas);
                const nodo = form.querySelector(`[data-error-campo="${entrada.name}"]`);
                if (nodo) nodo.textContent = error;
                entrada.setAttribute('aria-invalid', error ? 'true' : 'false');
                valido = valido && !error;
            });
            form.querySelector('[type="submit"]').disabled = escribiendo || !valido;
            return valido;
        }

        function preparar(form, accion, extras = () => ({})) {
            const boton = texto(form, 'button', form.dataset.etiqueta || 'Guardar');
            boton.type = 'submit';
            boton.className = 'btn-secondary';
            texto(form, 'p', '').setAttribute('data-aviso', '');
            form.noValidate = true;
            const comando = global.zookiCrearComandoAgenda();
            comandos.set(form, comando);
            form.addEventListener('input', () => validar(form));
            form.addEventListener('change', () => validar(form));
            form.addEventListener('submit', async evento => {
                evento.preventDefault();
                if (escribiendo || !validar(form)) return;
                const cuerpo = new FormData(form);
                const token = raiz.querySelector('[data-urgencias-csrf] input');
                cuerpo.set(token.name, token.value);
                cuerpo.set('id_clinica', raiz.dataset.clinica);
                for (const [nombre, valor] of Object.entries(extras())) cuerpo.set(nombre, valor);
                cuerpo.set('clave_operacion', comando.clave(cuerpo));
                escribiendo = true;
                ++lectura;
                raiz.querySelectorAll('button').forEach(b => { b.disabled = true; });
                try {
                    const respuesta = await global.fetch(`index.php?action=${accion}`, { method: 'POST', body: cuerpo });
                    const datos = await respuesta.json();
                    if (!respuesta.ok || datos.success === false) throw new Error(datos.message || 'No se pudo guardar.');
                    comando.reiniciar();
                    aviso.textContent = datos.message;
                    // La lista autoritativa se relee antes de anunciar el cambio al calendario.
                    await recargar();
                    global.dispatchEvent(new Event('agenda:urgencia'));
                } catch (error) {
                    form.querySelector('[data-aviso]').textContent = error.message;
                } finally {
                    escribiendo = false;
                    raiz.querySelectorAll('button').forEach(b => { b.disabled = false; });
                    raiz.querySelectorAll('form').forEach(validar);
                }
            });
            validar(form);
        }

        function formulario(padre, titulo, accion, datos) {
            const form = document.createElement('form');
            form.className = 'perfil-card';
            form.dataset.etiqueta = titulo;
            padre.append(form);
            form.extras = () => datos;
            return form;
        }

        function rojos(lista) {
            const contenedor = raiz.querySelector('[data-rojos-lista]');
            contenedor.replaceChildren();
            lista.forEach(s => {
                const tarjeta = texto(contenedor, 'article', '');
                tarjeta.className = 'perfil-card';
                texto(tarjeta, 'h3', s.nombre || `Ficha #${s.id_mascota}`);
                texto(tarjeta, 'p', s.fecha_llegada ? `Llegada ${s.fecha_llegada} · ${s.estado}` : 'Derivación remota; sin llegada acreditada');
                const datos = { id_solicitud: s.id_solicitud, version: s.version_solicitud };
                if (!s.fecha_llegada) {
                    const f = formulario(tarjeta, 'Acreditar llegada física', 'llegada_rojo_ajax', datos);
                    preparar(f, 'llegada_rojo_ajax', f.extras);
                }
                if (!vet) return;
                if (s.estado_revision === 'pendiente' || s.revision_vigente === false) {
                    const f = formulario(tarjeta, 'Revisar contexto actual', 'revisar_rojo_ajax', datos);
                    campo(f, 'motivo', 'Revisión clínica y contexto vigente', true);
                    preparar(f, 'revisar_rojo_ajax', f.extras);
                }
                if (s.estado !== 'espera_presencial' || !s.fecha_llegada) return;
                const atender = formulario(tarjeta, 'Atender ahora', 'atender_rojo_ajax', datos);
                opciones(campo(atender, 'id_tipo_cita', 'Tipo de atención', true, 'select'), catalogos.tipos, 'id_tipo_cita', 'nombre_tipo');
                campo(atender, 'decision', 'Decisión clínica si ya hay una atención pausada (opcional)');
                preparar(atender, 'atender_rojo_ajax', atender.extras);
                const ordenar = formulario(tarjeta, 'Priorizar este rojo', 'ordenar_rojo_ajax', datos);
                campo(ordenar, 'motivo', 'Motivo clínico del cambio de orden', true);
                preparar(ordenar, 'ordenar_rojo_ajax', ordenar.extras);
            });
        }

        function ingresos(lista) {
            const contenedor = raiz.querySelector('[data-ingresos-lista]');
            contenedor.replaceChildren();
            lista.forEach(i => {
                const tarjeta = texto(contenedor, 'article', '');
                tarjeta.className = 'perfil-card';
                texto(tarjeta, 'h3', `Ingreso #${i.id_ingreso} · ${i.nombre || 'Ficha provisional'}`);
                texto(tarjeta, 'p', [i.nombre_acompanante, i.telefono_acompanante].filter(Boolean).join(' · '));
                if (i.estado !== 'pendiente') {
                    texto(tarjeta, 'p', 'Identidad resuelta; contacto pendiente del fin de su necesidad o conservación justificada.');
                    return;
                }
                const f = formulario(tarjeta, 'Conciliar identidad verificada', 'conciliar_rojo_ajax', {});
                const modo = campo(f, 'modo', 'Resolver', true, 'select');
                opciones(modo, [{ id: 'consolidar', nombre: 'Consolidar sobre ficha existente' }, { id: 'completar', nombre: 'Completar esta ficha' }], 'id', 'nombre');
                modo.value = 'consolidar';
                const titular = campo(f, 'id_propietario', 'Titular con cuenta y vínculo verificados', true, 'select');
                opciones(titular, catalogos.propietarios, 'id_usuario', 'nombre_completo');
                const final = campo(f, 'id_mascota_final', 'Ficha definitiva del titular', true, 'select');
                const fragmento = raiz.querySelector('[data-completar-ficha]').content.cloneNode(true);
                f.append(fragmento);
                const ficha = f.querySelector('fieldset');
                const especie = f.elements.namedItem('especie');
                const raza = f.elements.namedItem('raza');
                opciones(especie, catalogos.especies, 'id_especie', 'nombre_especie');
                especie.addEventListener('change', () => {
                    opciones(raza, catalogos.razas.filter(r => String(r.id_especie) === especie.value), 'id_raza', 'nombre_raza');
                    validar(f);
                });
                campo(f, 'evidencia', 'Referencia de cuenta, vínculo o confirmación usada y verificación de relación', true);
                campo(f, 'motivo_conservacion', 'Motivo excepcional para conservar contacto (opcional)');
                const ajustar = () => {
                    ficha.disabled = modo.value !== 'completar';
                    ficha.hidden = ficha.disabled;
                    final.disabled = modo.value === 'completar';
                    final.closest('label').hidden = final.disabled;
                    opciones(final, catalogos.mascotas.filter(m => String(m.id_propietario) === titular.value), 'id_mascota', 'nombre');
                    validar(f);
                };
                preparar(f, 'conciliar_rojo_ajax', () => ({ id_ingreso: i.id_ingreso, ...(modo.value === 'completar' ? { id_mascota_final: i.id_mascota_provisional } : {}) }));
                modo.addEventListener('change', ajustar);
                titular.addEventListener('change', ajustar);
                ajustar();
            });
        }

        async function recargar() {
            const turno = ++lectura;
            const respuesta = await global.fetch('index.php?action=listar_urgencias_ajax');
            const datos = await respuesta.json();
            if (turno !== lectura) return;
            if (!respuesta.ok || datos.success === false) throw new Error(datos.message || 'No se pudieron consultar las urgencias.');
            catalogos = datos.catalogos;
            const form = raiz.querySelector('[data-ingreso-rojo]');
            if (form) {
                const select = form.elements.namedItem('id_mascota');
                const previo = select.value;
                opciones(select, catalogos.mascotas, 'id_mascota', 'nombre');
                select.value = previo;
            }
            rojos(datos.rojos);
            ingresos(datos.ingresos);
        }

        const ingreso = raiz.querySelector('[data-ingreso-rojo]');
        if (ingreso) {
            // El botón ya pertenece al HTML inicial; preparar crea uno solo por formulario.
            ingreso.querySelector('[type="submit"]').remove();
            ingreso.querySelector('[data-aviso]').remove();
            ingreso.dataset.etiqueta = 'Registrar llegada roja';
            preparar(ingreso, 'ingresar_rojo_ajax');
            ingreso.elements.namedItem('identidad').addEventListener('change', () => {
                const provisional = ingreso.elements.namedItem('identidad').value === 'provisional';
                const ficha = ingreso.querySelector('[data-identidad-provisional]');
                ficha.disabled = !provisional;
                ficha.hidden = !provisional;
                const conocida = ingreso.querySelector('[data-identidad-conocida]');
                conocida.hidden = provisional;
                conocida.querySelector('select').disabled = provisional;
                validar(ingreso);
            });
        }
        raiz.querySelector('[data-urgencias-actualizar]').addEventListener('click', () => {
            if (!escribiendo) recargar().catch(error => { aviso.textContent = error.message; });
        });
        recargar().catch(error => { aviso.textContent = error.message; });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})(typeof window !== 'undefined' ? window : globalThis);
