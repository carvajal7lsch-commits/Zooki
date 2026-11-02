# Guion de navegador para la sustentación

Escribió Codex; revisa Claude (arquitecto).
El archivo `zooki-demo.user.js` vive fuera de `public/` y se instala solo en el navegador del presentador.
No contiene claves, no simula respuestas y no se publica como parte de la aplicación.

## Instalar en Chrome

1. Instala Tampermonkey y habilita **Permitir scripts de usuario** en sus detalles de extensión; si tu versión de Chrome no ofrece esa opción, habilita el modo de desarrollador, según la [ayuda oficial](https://www.tampermonkey.net/faq.php#Q209).
2. Abre Tampermonkey → **Crear un nuevo script** y reemplaza todo el contenido con el archivo `demo/zooki-demo.user.js`.
3. Guarda con Ctrl+S, comprueba que quede habilitado y recarga Zooki.

Solo se activa en producción, zooki-pruebas y `http://localhost:3000/`.
Usa el contexto real de la página para acceder al calendario y al modal existente de Pacientes, mediante [`@sandbox raw`](https://www.tampermonkey.net/documentation.php#meta:sandbox).
El CSS del panel está aislado en un shadow DOM; no cambia hojas de estilo ni archivos de la aplicación.

## Preparar y presentar en cinco minutos

Despliega primero el ajuste de escena y corre la semilla con los comandos de `specs/semilla-demo.md`.
Ejecuta `--escena` minutos antes de presentar, de lunes a sábado entre 8:00 y 18:00.
Rocco queda en curso desde H−35, con llegada anterior y duración 30; Mila queda completada.
Simón no tiene una solicitud roja precargada.
Las cuentas y sus claves las imprime `--si`; el userscript no inicia sesión ni cambia de cuenta.

**Shift+S** ejecuta un solo paso; **Shift+Q** reinicia el contador del escenario.
También están los botones Siguiente/Reiniciar y el botón − para minimizar a un punto azul.
Al mostrar el resultado de Simón se minimiza automáticamente para no tapar el aviso de emergencia real; el punto permite volver a abrirlo.
Reiniciar no borra campos, borradores ni solicitudes.
Si acabas de hacer clic manualmente en un campo, durante 3,5 segundos esas combinaciones permiten escribir normalmente en ese campo.
Un paso con un control o respuesta ausente muestra el motivo y termina su espera a los 8 segundos.

| Tiempo | Pantalla y acción |
|---|---|
| 0:00–1:00 | Veterinaria principal de San Roque → Agenda de hoy → Shift+S resalta Rocco y las siguientes horas estimadas; abre Atención con el enlace del panel. |
| 1:00–2:00 | Atención de Rocco → Shift+S marca picazón, costras y pérdida de pelo; otra Shift+S usa sarna sarcóptica y muestra selamectina e ivermectina descartada por MDR1. |
| 2:00–3:30 | Pacientes → Shift+S abre el modal real de Mishi y marca fiebre, decaimiento y vómito; otra Shift+S selecciona paracetamol y deja visible el bloqueo felino con su fuente. |
| 3:30–5:00 | Otra sesión de Chrome con la propietaria `zooki.vet+demo-portal@gmail.com` → Portal → Shift+S evalúa Simón con los síntomas urinarios, muestra rojo y derivación sin reservar. |

El modal de Mishi se abre con la función existente de Pacientes y el identificador leído de su ficha real.
Los autocompletados se escriben carácter por carácter y se eligen por código del catálogo.
No pulses Guardar ni cierres la consulta de Rocco como completada durante este guion.
El pie actual de Atención recorta la línea larga de descartadas a1366 px; el userscript la resalta, y conserva ese diseño para revisión de Claude.
Al navegar, Zooki puede pedir confirmar que dejas un formulario sin guardar; responde manualmente.
Para el Portal móvil usa la emulación de 390×844 en Chrome del computador: aquí no se promete compatibilidad con extensiones de Chrome para Android.

La única escritura del userscript es la evaluación real de Simón aprobada por el dueño; no pulsa Agendar ni envía formularios.
La pestaña recuerda que solicitó esa evaluación incluso si se pierde la respuesta, para evitar un duplicado al reintentar.
Para repetirla, rehace `--escena` y usa una pestaña nueva; `--si` también elimina esa solicitud al recrear la demo.

## Comprobaciones manuales del dueño

1. Agenda: Shift+S muestra Rocco en curso y las siguientes horas corridas, sin iniciar ni cerrar citas; minimizar y abrir el panel funciona.
2. Rocco: dos pulsaciones muestran sarna, selamectina y el descarte de ivermectina; no guardes la consulta.
3. Mishi: dos pulsaciones muestran diagnósticos y bloqueo de paracetamol con una fuente consultable; no guardes la consulta.
4. Portal, con la cuenta indicada: una pulsación muestra rojo/emergencia y derivación; reiniciar el guion y pulsar otra vez no crea una segunda solicitud en esa pestaña.
5. Haz clic en un campo y prueba Shift+S inmediatamente: permite teclear; después de 3,5 segundos la combinación vuelve a ejecutar el guion sin escribir la letra.

## Verificación automatizada

`tests/Frontend/ZookiDemo.test.cjs` ejecuta este mismo archivo en Chrome headless por CDP, a 1366×768 y 390×844.
El servidor del arnés usa `localhost:31300` e inyecta el archivo completo; los `@match` de instalación conservan los tres destinos pedidos, incluido `localhost:3000`.
Usa MySQL/MariaDB temporal con `zooki_test_base_v2`, controladores/vistas/motores reales y reloj inyectado en lunes para poder ensayar sin alterar el reloj del sistema.
El arnés de pruebas rechaza guardar consultas, reservar o iniciar citas.
Las dependencias JS se sirven con los mismos bytes del CDN en el entorno hermético de Chrome; no se sustituyen respuestas clínicas.
La instalación de la extensión Tampermonkey se comprueba manualmente con los pasos anteriores.
Las capturas y la evidencia quedan en `scratch/r/userscript/`, fuera de Git.
Resultado: `SemillaDemoTest`4/1230 y recorrido Chrome1/1, sin excepciones JS, con cero consultas/tratamientos guardados y cero reservas creadas.

Con el motor temporal de pruebas configurado, ambos comandos usan exclusivamente `zooki_test_base_v2`:

```powershell
php vendor/bin/phpunit tests/Integration/SemillaDemoTest.php
node --test tests/Frontend/ZookiDemo.test.cjs
```
