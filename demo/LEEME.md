# Guion de navegador para la sustentación

Escribió Codex; revisa Claude (arquitecto).
El archivo `zooki-demo.user.js` vive fuera de `public/` y se instala solo en el navegador del presentador.
No contiene claves, no simula respuestas y no se publica como parte de la aplicación.

## Instalar en Chrome

1. Instala Tampermonkey y habilita **Permitir scripts de usuario** en sus detalles de extensión; si tu versión de Chrome no ofrece esa opción, habilita el modo de desarrollador, según la [ayuda oficial](https://www.tampermonkey.net/faq.php#Q209).
2. Abre Tampermonkey → **Crear un nuevo script** y reemplaza todo el contenido con el archivo `demo/zooki-demo.user.js`.
3. Guarda con Ctrl+S, comprueba que quede habilitado y recarga Zooki.

Solo se activa en producción, zooki-pruebas y `http://localhost:3000/`.
Usa el contexto real de la página para acceder al calendario y a los controles existentes de Atención, mediante [`@sandbox raw`](https://www.tampermonkey.net/documentation.php#meta:sandbox).
El CSS del panel está aislado en un shadow DOM; no cambia hojas de estilo ni archivos de la aplicación.

## Preparar y presentar en cinco minutos

Despliega primero el ajuste de escena y corre la semilla con los comandos de `specs/semilla-demo.md`.
Sin entrar al servidor: pon `ZOOKI_DEMO_HABILITADA=1` en las variables del entorno, entra como super-administrador y usa Plataforma → pestaña **Demo**: «Crear o rehacer demo» (fecha y clave común), «Preparar escena ahora» y «Borrar demo».
En producción se enciende antes de la sustentación y se apaga (vuelve a `0`) después; apagada, la pestaña y sus rutas no existen.
Ejecuta `--escena` (o «Preparar escena ahora» en la pestaña Demo) minutos antes de presentar, de lunes a sábado entre 8:00 y 18:00.
Rocco queda en curso desde H−35, con llegada anterior y duración 30; Mila queda completada.
Mishi es la primera reserva después de Rocco, con llegada registrada y sin iniciar; las reservas y el sobrecupo posterior caben antes de las20:00.
Simón no tiene una solicitud roja precargada.
Las cuentas y sus claves las imprime `--si`; desde la pestaña Demo, las cuentas usan la clave que escribiste y el panel muestra sus correos, nunca la clave. El userscript no inicia sesión ni cambia de cuenta.

**Shift+S** ejecuta un solo paso; **Shift+Q** reinicia el contador del escenario.
También están los botones Siguiente/Reiniciar y el botón − para minimizar a un punto azul.
Al mostrar el resultado de Simón se minimiza automáticamente para no tapar el aviso de emergencia real; el punto permite volver a abrirlo.
Reiniciar no borra campos, borradores ni solicitudes.
Si acabas de hacer clic manualmente en un campo, durante 3,5 segundos esas combinaciones permiten escribir normalmente en ese campo.
Un paso con un control o respuesta ausente muestra el motivo y termina su espera a los 8 segundos.

| Tiempo | Pantalla y acción |
|---|---|
| 0:00–1:00 | Veterinaria principal de San Roque → Agenda de hoy → Shift+S resalta Rocco y las siguientes horas estimadas; abre Atención con el enlace del panel. |
| 1:00–2:15 | Atención de Rocco → Shift+S marca picazón, costras y pérdida de pelo; otra Shift+S usa sarna sarcóptica y muestra selamectina y la fila de descarte MDR1 con Ver fuente; la tercera Shift+S finaliza la consulta real y vuelve a Agenda. |
| 2:15–2:40 | Agenda → Shift+S resalta Mishi y las nuevas estimaciones calculadas; abre su cita y pulsa Iniciar atención, sin reservar una cita nueva. |
| 2:40–3:45 | Atención de Mishi → Shift+S marca fiebre, decaimiento y vómito; otra Shift+S pasa a tratamientos y selecciona paracetamol, dejando visible el bloqueo felino con su fuente. No guarda. |
| 3:45–5:00 | Otra sesión de Chrome con la propietaria `zooki.vet+demo-portal@gmail.com` → Portal → Shift+S evalúa Simón con los síntomas urinarios, muestra rojo y derivación sin reservar. |

Orden: **Agenda → Rocco (3 pasos) → Agenda (Mishi) → Mishi (2 pasos) → Portal**.
El guion detecta Rocco completado en los datos reales del calendario para ofrecer el paso de Mishi; inicia su atención mediante el botón de la cita.
Los autocompletados se escriben carácter por carácter y se eligen por código del catálogo.
La tercera pulsación de Rocco sí guarda la consulta y completa su cita; es la única consulta que se guarda (decisión del dueño,2026-11-03).
Deja el próximo control sin solicitar para que ese cierre no genere una reserva adicional.
El descarte aparece completo debajo de los medicamentos, con escudo, aviso rojo suave y Ver fuente; el guion resalta esa fila.
Al navegar, Zooki puede pedir confirmar que dejas un formulario sin guardar; responde manualmente.
Para el Portal móvil usa la emulación de 390×844 en Chrome del computador: aquí no se promete compatibilidad con extensiones de Chrome para Android.

Las escrituras autorizadas son finalizar la consulta de Rocco, iniciar la cita existente de Mishi y evaluar realmente a Simón.
Nunca guarda la consulta de Mishi ni pulsa Agendar; no escribe resultados clínicos simulados.
Para repetir el recorrido, ejecuta --escena: elimina la consulta demo de Rocco y sus tratamientos, restaura Rocco en curso y Mishi en sala; --si recrea toda la demo.
La pestaña recuerda que solicitó esa evaluación incluso si se pierde la respuesta, para evitar un duplicado al reintentar.
Para repetirla, rehace `--escena` y usa una pestaña nueva; `--si` también elimina esa solicitud al recrear la demo.

## Comprobaciones manuales del dueño

1. Agenda: Shift+S muestra Rocco en curso y las siguientes horas corridas, sin iniciar ni cerrar citas; minimizar y abrir el panel funciona.
2. Rocco: dos pulsaciones muestran sarna, selamectina y el descarte completo con Ver fuente; la tercera guarda Rocco y vuelve a Agenda. Una pulsación allí muestra el recálculo e inicia la cita de Mishi.
3. Atención de Mishi: dos pulsaciones muestran diagnósticos y bloqueo de paracetamol con una fuente consultable; no guarda la consulta.
4. Portal, con la cuenta indicada: una pulsación muestra rojo/emergencia y derivación; reiniciar el guion y pulsar otra vez no crea una segunda solicitud en esa pestaña.
5. Haz clic en un campo y prueba Shift+S inmediatamente: permite teclear; después de 3,5 segundos la combinación vuelve a ejecutar el guion sin escribir la letra.

## Verificación automatizada

`tests/Frontend/ZookiDemo.test.cjs` ejecuta este mismo archivo en Chrome headless por CDP, a 1366×768 y 390×844.
El servidor del arnés usa `localhost:31300` e inyecta el archivo completo; los `@match` de instalación conservan los tres destinos pedidos, incluido `localhost:3000`.
Usa MySQL/MariaDB temporal con `zooki_test_base_v2`, controladores/vistas/motores reales y reloj inyectado en lunes para poder ensayar sin alterar el reloj del sistema.
El arnés rechaza reservas y mutaciones ajenas al guion: solo permite registrar la consulta de Rocco e iniciar la cita de Mishi, además de evaluar a Simón.
Las dependencias JS se sirven con los mismos bytes del CDN en el entorno hermético de Chrome; no se sustituyen respuestas clínicas.
La instalación de la extensión Tampermonkey se comprueba manualmente con los pasos anteriores.
Las capturas y la evidencia quedan en `scratch/r/userscript/`, fuera de Git.
Verificación2026-11-03: los cuatro casos de SemillaDemoTest comprobados, con repetición solo de escena (nueve relojes,1/1182 aserciones); recorrido Chrome CDP correcto sobre semilla temporal.
Solo se guardó Rocco y se inició Mishi sin guardar su consulta; cero reservas nuevas, una derivación de Simón y --escena devuelve los conteos iniciales.
Descarte completo a1366×768/390×844, página sin scroll en escritorio y sin excepciones JS; evidencia en scratch/r/userscript/resultado.json.
No se ejecutaron suites adicionales; la siguiente orden de JS queda disponible para CI o un ensayo posterior.

Con el motor temporal de pruebas configurado, ambos comandos usan exclusivamente `zooki_test_base_v2`:

```powershell
php vendor/bin/phpunit tests/Integration/SemillaDemoTest.php
node --test tests/Frontend/ZookiDemo.test.cjs
```
