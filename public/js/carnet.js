/* HU-5.10: compartir, imprimir y renovar la tarjeta privada del propietario. */
(function (global) {
    'use strict';
    let carga = 0;
    let ocupada = false;

    async function compartir(enlace) {
        try {
            if (typeof navigator.share === 'function') {
                await navigator.share({ title: 'Carnet de emergencia', url: enlace });
            } else {
                await navigator.clipboard.writeText(enlace);
                zookiToast('Enlace del carnet copiado.', 'success');
            }
        } catch (error) {
            if (error.name !== 'AbortError') zookiToast('No se pudo compartir el enlace. Intenta nuevamente.', 'error');
        }
    }

    async function cargar(idMascota) {
        const panel = document.getElementById('carnetContenido');
        if (!panel) return;
        const turno = ++carga;
        panel.textContent = 'Cargando carnet…';
        try {
            const respuesta = await fetch(`index.php?action=portal_carnet_ajax&id_mascota=${encodeURIComponent(idMascota)}`);
            const datos = await respuesta.json();
            if (turno !== carga) return;
            if (!respuesta.ok || !datos.success) throw new Error('No disponible');
            panel.innerHTML = datos.html;
        } catch (error) {
            if (turno === carga) panel.textContent = 'No se pudo cargar el carnet. Vuelve a abrir la ficha para reintentar.';
        }
    }

    async function regenerar(boton) {
        if (ocupada) return;
        const tarjeta = boton.closest('[data-carnet-datos]');
        const panel = document.getElementById('carnetContenido');
        if (!tarjeta || !panel) return;
        const turno = carga;
        ocupada = true;
        boton.disabled = true;
        try {
            const confirmado = await zookiConfirmarCorto('¿Regenerar el QR? Los impresos y enlaces anteriores dejarán de servir.', 'Regenerar QR');
            if (!confirmado || turno !== carga) return;
            const datos = await postPortal('portal_regenerar_carnet_ajax', {
                id_mascota: tarjeta.dataset.idMascota,
                token_visto: tarjeta.dataset.tokenVisto
            });
            if (!datos.success) throw new Error(datos.message || 'No se pudo regenerar el QR.');
            if (turno === carga) panel.innerHTML = datos.html;
            zookiToast(datos.regenerado ? 'QR regenerado. Los enlaces anteriores ya no sirven.' : 'El QR ya había sido regenerado. Se muestra el actual.', 'success');
        } catch (error) {
            zookiToast(error.message || 'No se pudo regenerar el QR.', 'error');
        } finally {
            ocupada = false;
            boton.disabled = false;
        }
    }

    document.addEventListener('click', function (evento) {
        const boton = evento.target.closest('[data-carnet-compartir], [data-carnet-regenerar], [data-carnet-imprimir]');
        if (!boton) return;
        if (boton.hasAttribute('data-carnet-imprimir')) {
            global.print();
        } else if (boton.hasAttribute('data-carnet-compartir')) {
            compartir(boton.closest('[data-carnet-datos]').dataset.enlace);
        } else {
            regenerar(boton);
        }
    });
    global.ZookiCarnet = { cargar, compartir, regenerar };
})(window);
