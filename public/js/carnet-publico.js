/* RE-5.11.5: no se pide ni se transmite ubicación antes de un clic y del permiso. */
(function () {
    'use strict';
    document.addEventListener('click', async function (evento) {
        const boton = evento.target.closest('[data-carnet-ubicacion]');
        if (!boton || boton.disabled) {
            return;
        }
        const mensaje = document.querySelector('[data-carnet-ubicacion-mensaje]');
        if (!navigator.geolocation) {
            mensaje.textContent = 'Este navegador no permite compartir la ubicación.';
            return;
        }
        boton.disabled = true;
        mensaje.textContent = 'Acepta el permiso del navegador para compartir tu ubicación.';
        try {
            const posicion = await new Promise((resolver, rechazar) => navigator.geolocation.getCurrentPosition(resolver, rechazar, { timeout: 15000, maximumAge: 0, enableHighAccuracy: false }));
            const latitud = posicion.coords.latitude;
            const longitud = posicion.coords.longitude;
            if (!Number.isFinite(latitud) || !Number.isFinite(longitud) || Math.abs(latitud) > 90 || Math.abs(longitud) > 180) {
                throw new Error('Coordenadas inválidas');
            }
            const cuerpo = new URLSearchParams({ t: boton.dataset.token, comprobante: boton.dataset.comprobante, latitud: latitud.toFixed(6), longitud: longitud.toFixed(6) });
            const respuesta = await fetch('index.php?action=carnet_ubicacion', { method: 'POST', body: cuerpo, credentials: 'omit', referrerPolicy: 'no-referrer' });
            const datos = await respuesta.json();
            if (!respuesta.ok || !datos.success) {
                mensaje.textContent = datos.message || 'No se pudo enviar la ubicación.';
                return;
            }
            mensaje.textContent = datos.message;
        } catch (error) {
            mensaje.textContent = error.code === 1 ? 'No compartiste tu ubicación. Puedes seguir consultando el carnet.' : 'No se pudo compartir la ubicación. Intenta nuevamente.';
        } finally {
            boton.disabled = false;
        }
    });
})();
