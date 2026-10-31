/** RE-2.8.1, RN-110/113: completar ficha sin confundirla con los datos de la evaluación. */
(function () {
    'use strict';
    function crear(form, idMascota, alGuardar) {
        const raiz = form.querySelector('[data-ficha-mini]');
        if (!raiz) return null;
        const campos = [...raiz.querySelectorAll('[data-ficha-campo]')];
        const representantes = [...new Map(campos.map(campo => [campo.dataset.fichaCampo, campo])).values()];
        const valor = campo => campo.type === 'radio' ? raiz.querySelector(`[data-ficha-campo="${campo.dataset.fichaCampo}"]:checked`)?.value || '' : campo.value;
        const boton = raiz.querySelector('[data-guardar-ficha]');
        const aviso = raiz.querySelector('[data-ficha-aviso]');
        let generacion = 0;
        let ficha = null;
        function validar(campo) {
            let error = '';
            const nombre = campo.dataset.fichaCampo;
            const dato = valor(campo);
            if (!dato) error = 'Este campo es obligatorio.';
            else if (nombre === 'sexo' && !['Macho', 'Hembra'].includes(dato)) error = 'Elige macho o hembra.';
            else if (nombre === 'esterilizado' && !['0', '1'].includes(dato)) error = 'Elige sí o no.';
            else if (nombre === 'fecha_nacimiento' && (!/^\d{4}-\d{2}-\d{2}$/.test(campo.value) || campo.value < campo.min || campo.value > campo.max)) error = 'Entre hoy y hace ' + raiz.dataset.edadMaxima + ' años.';
            raiz.querySelector(`[data-ficha-error="${nombre}"]`).textContent = error;
            for (const miembro of campos.filter(c => c.dataset.fichaCampo === nombre)) miembro.setAttribute('aria-invalid', String(Boolean(error)));
            return !error;
        }
        function actualizar(datos, conservarAviso = false) {
            generacion++;
            ficha = datos;
            let visibles = 0;
            for (const campo of campos) {
                const nombre = campo.dataset.fichaCampo;
                const falta = datos && (datos[nombre] === null || datos[nombre] === undefined || datos[nombre] === '' || datos[nombre] === 'Desconocido');
                const editable = nombre === 'esterilizado' || datos?.identidad_editable === true;
                campo.closest('[data-ficha-fila]').hidden = !falta || !editable;
                campo.disabled = !falta || !editable;
                if (campo.type === 'radio') campo.checked = false;
                else campo.value = '';
                campo.removeAttribute('aria-invalid');
                raiz.querySelector(`[data-ficha-error="${nombre}"]`).textContent = '';
                if (falta && editable) visibles++;
            }
            raiz.hidden = visibles === 0;
            boton.disabled = !datos;
            if (!conservarAviso) aviso.textContent = '';
        }
        for (const campo of campos) {
            campo.addEventListener('input', () => validar(campo));
            campo.addEventListener('change', () => validar(campo));
        }
        boton.addEventListener('click', async () => {
            const activos = representantes.filter(c => !c.disabled);
            const validaciones = activos.map(validar);
            if (validaciones.includes(false)) {
                activos[validaciones.indexOf(false)].focus();
                return;
            }
            const vigente = generacion;
            const mascota = String(idMascota());
            boton.disabled = true;
            const datos = Object.fromEntries(activos.map(c => [c.dataset.fichaCampo, valor(c)]));
            try {
                const r = await (await fetch('index.php?action=completar_ficha_consulta_ajax', { method: 'POST', body: new URLSearchParams({ id_mascota: mascota, ...datos }) })).json();
                if (vigente !== generacion || mascota !== String(idMascota())) return;
                if (!r.success) {
                    const campo = activos.find(c => c.dataset.fichaCampo === r.campo);
                    if (campo) {
                        raiz.querySelector(`[data-ficha-error="${r.campo}"]`).textContent = r.aviso || r.message;
                        for (const miembro of campos.filter(c => c.dataset.fichaCampo === r.campo)) miembro.setAttribute('aria-invalid', 'true');
                        campo.focus();
                    }
                    throw new Error(r.message || 'No se pudo actualizar la ficha.');
                }
                aviso.textContent = 'Ficha actualizada.';
                actualizar({ ...ficha, ...datos }, true);
                alGuardar();
                form.dispatchEvent(new CustomEvent('zooki:ficha-actualizada', { bubbles: true, detail: { idMascota: mascota, datos } }));
            } catch (error) {
                if (vigente !== generacion) return;
                aviso.textContent = error.message || 'No se pudo actualizar. Intenta de nuevo.';
            } finally {
                if (vigente === generacion) boton.disabled = false;
            }
        });
        actualizar(null);
        return {
            actualizar,
            async cargar() {
                const vigente = ++generacion;
                const mascota = String(idMascota());
                try {
                    const r = await (await fetch('index.php?action=get_mascota_ajax&' + new URLSearchParams({ id: mascota, id_mascota: mascota }))).json();
                    if (vigente !== generacion || mascota !== String(idMascota()) || String(r.id_mascota) !== mascota) return;
                    actualizar(r);
                } catch (error) {
                    // La lectura de la ficha no condiciona la captura clínica ni el reintento del control.
                }
            },
        };
    }
    window.ZookiFichaConsulta = { crear };
})();
