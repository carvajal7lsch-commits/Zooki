# Dependencias de las pruebas del portal

Copias de las bibliotecas que el portal ya carga por CDN, fijadas para Chrome aislado sin red externa.
No cambian las dependencias ni la carga de producción.

- Flatpickr 4.6.13: https://github.com/flatpickr/flatpickr (MIT, flatpickr-LICENSE.txt).
- intl-tel-input 23.0.10: https://github.com/jackocnr/intl-tel-input (MIT, intl-LICENSE.txt).
- Remix Icon 3.5.0: https://github.com/Remix-Design/RemixIcon (licencia en remixicon-LICENSE.txt).

Origen de las copias: paquetes publicados en cdn.jsdelivr.net/npm con estas versiones exactas.
portal-movil-servidor.cjs sirve estas copias y sustituye solo para las pruebas las rutas de CDN, fuentes y banderas.
Los formularios, la validación, el calendario y el selector de país ejecutan sus scripts reales; los endpoints de datos y envíos están simulados.
