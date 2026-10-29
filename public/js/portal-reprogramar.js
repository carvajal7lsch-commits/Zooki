/** C4b: cambia solo la cita propia, con snapshot y disponibilidad del servidor. */
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('portalReprogramarModal');
    if (!modal) return;
    const form = modal.querySelector('form');
    const fecha = form.elements.fecha;
    const vet = form.elements.id_veterinario;
    const hora = form.elements.hora;
    const guardar = form.querySelector('[type="submit"]');
    const error = form.querySelector('[data-error]');
    const comando = zookiCrearComandoAgenda();
    let cita = null;
    let turno = 0;
    let consulta = 0;
    let enviando = false;
    // HU-5.6 / RN-401: mismos días cerrados que Agendar, sin otra consulta ni calendario nativo.
    const calendario = typeof flatpickr !== 'undefined' ? flatpickr(fecha, {
        locale: window.zkLocale || 'es',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        minDate: 'today',
        disableMobile: true,
        monthSelectorType: 'static',
        disable: [dia => {
            const cerrados = JSON.parse(form.dataset.diasCerrados || '{}')[cita?.id_clinica] || [];
            return cerrados.includes(dia.getDay() === 0 ? 7 : dia.getDay());
        }],
        onReady: (fechas, texto, instancia) => { if (window.zkMesesAnios) window.zkMesesAnios(instancia); },
    }) : null;


    function cerrar() {
        turno++;
        consulta++;
        calendario?.close();
        closeModal('portalReprogramarModal');
    }

    async function cargar() {
        const actual = ++consulta;
        hora.replaceChildren(new Option('Elige un horario', ''));
        guardar.disabled = true;
        error.textContent = '';
        if (!cita || !fecha.value || !vet.value) return;
        const parametros = new URLSearchParams({ action: 'portal_get_sugerencias_ajax', id_clinica: cita.id_clinica, id_mascota: cita.id_mascota, id_tipo_cita: cita.id_tipo_cita || '', id_veterinario: vet.value, fecha: fecha.value, id_cita_excluir: cita.id_cita });
        try {
            const r = await (await fetch('index.php?' + parametros)).json();
            if (actual !== consulta) return;
            if (!r.success) {
                error.textContent = r.message || 'No se pudieron consultar horarios.';
                return;
            }
            for (const instante of r.sugerencias) hora.add(new Option(zookiHoraAgenda(instante, 'a. m.', 'p. m.'), instante));
            if (!r.sugerencias.length) error.textContent = 'No hay horarios compatibles para esta fecha.';
        } catch (fallo) {
            if (actual === consulta) error.textContent = 'No se pudieron consultar horarios.';
        }
    }

    document.addEventListener('click', async evento => {
        const boton = evento.target.closest('[data-reprogramar-cita]');
        if (!boton) return;
        evento.preventDefault();
        evento.stopPropagation();
        const actual = ++turno;
        consulta++;
        cita = null;
        error.textContent = '';
        guardar.disabled = true;
        comando.reiniciar();
        openModal('portalReprogramarModal');
        try {
            const r = await (await fetch('index.php?action=get_cita_ajax&id=' + encodeURIComponent(boton.dataset.reprogramarCita))).json();
            if (actual !== turno) return;
            if (!r.success) {
                error.textContent = r.message || 'La cita no está disponible.';
                return;
            }
            cita = r.cita;
            const veterinarios = await (await fetch('index.php?action=portal_get_vets_ajax&id_clinica=' + cita.id_clinica)).json();
            if (actual !== turno) return;
            vet.replaceChildren(new Option('Elige veterinario', ''));
            for (const veterinario of veterinarios) vet.add(new Option(veterinario.nombre_completo, veterinario.id_usuario));
            vet.value = String(cita.id_veterinario);
            if (calendario) {
                calendario.redraw();
                calendario.setDate(cita.fecha, false);
            } else fecha.value = cita.fecha;
            form.elements.id_cita.value = cita.id_cita;
            await cargar();
        } catch (fallo) {
            if (actual === turno) error.textContent = 'No se pudo cargar la cita.';
        }
    });
    fecha.addEventListener('change', cargar);
    vet.addEventListener('change', cargar);
    hora.addEventListener('change', () => { guardar.disabled = enviando || !hora.value; });
    document.getElementById('portalReprogramarCerrar').addEventListener('click', cerrar);
    modal.addEventListener('click', evento => { if (evento.target === modal) cerrar(); });
    form.addEventListener('submit', async evento => {
        evento.preventDefault();
        if (enviando || !hora.value || !cita) return;
        const actual = turno;
        const datos = new FormData(form);
        datos.set('clave_operacion', comando.clave(datos));
        enviando = true;
        guardar.disabled = true;
        try {
            const r = await (await fetch('index.php?action=reprogramar_cita_ajax', { method: 'POST', body: datos })).json();
            if (actual !== turno) return;
            if (!r.success) {
                error.textContent = r.message || 'No se pudo reprogramar. La cita original se conserva.';
                return;
            }
            cerrar();
            refrescarPortal();
            zookiToast(r.message, 'success');
            fetch('index.php?action=enviar_email_ajax', { method: 'POST', body: new URLSearchParams({ id_cita: cita.id_cita }) }).catch(() => {});
        } catch (fallo) {
            if (actual === turno) error.textContent = 'Respuesta no recibida. Reintenta para recuperar el mismo resultado.';
        } finally {
            enviando = false;
            guardar.disabled = !hora.value;
        }
    });
});
