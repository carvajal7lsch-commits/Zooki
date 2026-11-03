/* HU-4.18: una elección clínica explícita evita redactar el motivo en cada revisión. */
(function (global) {
    'use strict';
    if (global.ZookiMotivosRevision) return;
    function crear(entrada) {
        const grupo = document.createElement('div');
        grupo.className = 'motivos-revision';
        grupo.setAttribute('role', 'group');
        grupo.setAttribute('aria-label', 'Motivo de la revisión');
        let elegido = '';
        for (const motivo of ['Valoración clínica realizada', 'Síntomas aclarados por el propietario', 'Señales de mayor urgencia', 'Paciente estable tras valoración']) {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.textContent = motivo;
            boton.setAttribute('aria-pressed', 'false');
            boton.addEventListener('click', () => {
                elegido = elegido === motivo ? '' : motivo;
                grupo.querySelectorAll('button').forEach(opcion => opcion.setAttribute('aria-pressed', String(opcion.textContent === elegido)));
                entrada.dispatchEvent(new Event('input', { bubbles: true }));
            });
            grupo.append(boton);
        }
        entrada.before(grupo);
        entrada.placeholder = 'Detalle adicional (opcional)';
        const obtener = () => [elegido, entrada.value.trim()].filter(Boolean).join(' · ');
        obtener.reiniciar = () => {
            elegido = '';
            grupo.querySelectorAll('button').forEach(boton => boton.setAttribute('aria-pressed', 'false'));
        };
        return obtener;
    }
    global.ZookiMotivosRevision = { crear };
})(globalThis);
