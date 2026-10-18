# HU-4.15 — Ingreso presencial de emergencia sin identidad verificada

> Estado: aprobado por el usuario el 2026-10-06; pendiente de implementación
> Alineación B1 de M4 (2026-10-17): decisiones confirmadas de identidad/contacto/presencia; diseño de datos pendiente de revisión y migración B2, sin implementar este flujo.
> Versión prevista: v2.0 · Fecha: 2026-10-06

## 1. Especificación (qué y por qué)

**Problema:** una urgencia roja que llega físicamente a la clínica puede no tener mascota registrada, cuenta del propietario o una relación verificable entre el acompañante y la mascota. Exigir una cuenta y una ficha completas bloquearía la atención; crear una cuenta sin consentimiento contradice RN-G19. En el portal autenticado, la sesión ya identifica al propietario y a la mascota seleccionada.

**Historia:** [HU-4.15](../documentacion/HistoriasUsuario.md#hu-415--atender-una-urgencia-triage-rojo-o-llegada-directa). La clínica atiende primero y concilia la identidad después.

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| RE-4.15.3 | En el portal autenticado, reutilizar la identidad de la sesión y la mascota seleccionada. En un ingreso presencial, reutilizar cuenta y mascota existentes si se verifica la relación sin retrasar la atención; de lo contrario crear ficha e ingreso provisionales sin atribuir propiedad ni crear cuenta. Después de verificar titularidad, completar o consolidar la ficha. | Un caso rojo del portal conserva `id_usuario` e `id_mascota` sin pedir otra verificación. En ingreso presencial con cuenta verificada se reutilizan los mismos registros; sin verificación, la consulta se guarda sobre una mascota provisional y no se crea usuario. Al conciliar, los actos clínicos conservan su clínica y veterinario. |

**Reglas de negocio:** RN-G07, RN-G16, RN-G19, RN-110, RN-206, RN-207, RN-420, RN-421 y RN-427.

**Fuera de alcance:** pedir una nueva verificación de identidad al propietario autenticado en el portal; crear cuentas automáticamente para acompañantes; activar el carnet público de una ficha provisional; usar el ingreso provisional para el agendamiento ordinario.

## 2. Plan (cómo)

- **Archivos:** adaptar modelos y controladores de mascota, propietario y urgencia; actualizar el flujo en `documentacion/Modelos.md` y la matriz de `helpers/Security.php` para las acciones nuevas. La clínica solo consulta sus ingresos provisionales.
- **Datos:** ampliación aditiva e idempotente de la base v2 alpha.4 desplegada; no se migran datos v1 ni se recrean mascotas/ingresos_emergencia existentes. `mascotas` admite campos incompletos solo con `ficha_por_completar = 1`; `ingresos_emergencia` guarda clínica, ficha provisional, ficha final al conciliar, datos disponibles del acompañante y estado. El esquema de referencia está en `database/modelo/drawdb_schema_v2.sql` y el MER. La migración real se numera cuando se implemente esta historia.
- **Transacción de conciliación:** verificar titularidad; si la mascota ya existía, trasladar las referencias clínicas de la provisional a la ficha final, conservar `id_clinica` y `id_veterinario`, marcar la provisional inactiva y registrar el vínculo en `ingresos_emergencia`; si era nueva, completar la misma ficha. Auditar ambos caminos.
- **Rutas y permisos:** la urgencia iniciada en el portal usa la sesión y la mascota seleccionada. Solo personal clínico autorizado crea un ingreso provisional presencial; solo administrador o veterinario autorizado de la clínica completa/consolida tras verificar titularidad con los mecanismos vigentes; propietario aporta la prueba pero no fusiona; admin no crea citas ni registra/modifica actos clínicos; ninguna petición pública obtiene los datos del acompañante.
- **Pantallas:** captura mínima y aviso de «ficha por completar» en Atención/Urgencias; tarea de conciliación visible al personal en móvil, tablet y escritorio.
- **Riesgos:** fuga de datos del acompañante, atribución errónea de propiedad, duplicación de mascota, pérdida de actos clínicos y bloqueo de urgencias por límite del plan. Se evitan con permisos, transacción, auditoría y pruebas específicas.

## 3. Tareas

- [x] Alinear HU, RE, MER, esquema de referencia y flujo documental.
- [x] Aprobar el flujo: portal autenticado con identidad conocida; ingreso presencial con ficha existente verificada o ficha provisional.
- [x] Criterios documentales de captura/contacto/permisos definidos por decisiones del usuario; implementación y pruebas pendientes de C7b, no aprobadas por esta casilla.
- [ ] Escribir migración idempotente sin alterar migraciones publicadas.
- [ ] Implementar ingreso provisional y conciliación.
- [ ] Añadir rutas y permisos a `public/index.php` y `helpers/Security.php`.
- [ ] Probar las ramas: portal autenticado con mascota seleccionada, ingreso presencial con cuenta y mascota verificadas, cuenta existente sin relación aún verificada, y propietario sin cuenta.
- [ ] Probar aislamiento por clínica, ausencia de cuenta sin consentimiento, conservación de actos clínicos y excepción roja al límite del plan.
- [ ] Pasar `vendor/bin/phpunit`; actualizar versión y `documentacion/HistorialVersiones.md` cuando se implemente.

## Contrato documental B1, pendiente de implementación

- Rojo remoto conserva IDs/aviso/derivación, sin presencia física, pausa ni consulta; una llegada real enlaza la solicitud y el veterinario crea la cita antes de atender.
- Nombre/teléfono opcionales y sin documento por defecto. Al cerrar atención y resolver identidad se comprueba necesidad; si terminó, contacto a NULL y sello/autor, sin copiarlo a auditoría/avisos. Conservación excepcional requiere motivo, sin días nuevos ni borrar actos.
- Solo admin/vet autorizado verifica titularidad mediante identidad autenticada/cuenta y vínculos legítimos vigentes; no se crea reto, documento ni plazo nuevos. ID conocido o contacto del acompañante no son prueba. Sin verificación queda provisional/carnet inactivo.
- Conciliación bloquea clínica/usuarios/mascotas/actos en orden común, relee y preserva todas las referencias: citas/consultas/alertas/vacunas/desparasitaciones se trasladan; tratamientos/archivos/síntomas siguen sus actos. Auditoría/escaneos históricos conservan origen y el ingreso guarda mapa IDs/HC. Se conserva HC existente del final y alias/procedencia provisional, o se transfiere HC sin duplicarlo cuando final carece de él; no se inventa uno por conciliar. Una provisional con actos de otra clínica no se consolida desde esta por inferencia.
- MER/drawDB proponen solicitudes_agenda para revisión/derivación/espera, con enlace opcional a ingreso provisional y cita; id_veterinario de cita no se vuelve NULL. Los RE-4.15.3/9/10 y el plan M4 son su aceptación trazable; no ejecutar drawDB como migración.

## Precisión B1.1 — identidad de solicitudes y comandos

Contrato documental de 2026-10-18, pendiente de revisión independiente e implementación.
Se completa P→P (ingreso completado) o se consolida P→F (consolidado), con una sola identidad vigente para todas las solicitudes y citas afectadas de la clínica.
Completar conserva P y regulariza ficha_por_completar=0 con datos/titularidad legítimos, sin activar carnet automáticamente; consolidar no sobrescribe datos/titularidad/alertas de F con datos parciales de P.
Todos los ingresos afectados conservan mapa compatible; otro destino ya confirmado detiene la unidad.
Incluso S finalizada/retirada actualiza id_mascota al destino y conserva su episodio clínico como procedencia; no queda S→P/C→F ni se reabre historia.
Se conservan IDs S/C/I y actos, clínica/autor/horas, mapa P→destino/HC en ingreso y auditoría; P consolidada queda inactiva.
Las otras S de F no pertenecientes a P no se mezclan; enlaces incoherentes o actos ajenos detienen la unidad.
Cada S/C afectada aumenta su versión una sola vez; P→P también invalida el contexto anterior regularizado.
Activas (abierta, derivada, espera_presencial, asignada, en_atencion, pendiente_reprogramacion) conservan estado operativo y pasan revisión a pendiente/huella_revision NULL.
Terminales conservan revisión histórica íntegra, sin autorizar comandos nuevos.
contexto_triage conserva id_mascota_evaluada/version_solicitud_evaluada en JSON, huella, versión de conocimiento, atributos y decisiones de su episodio; no se recalcula ni sobrescribe desde F.
En activas, autor/fecha/motivo previos describen revisión histórica; solo vet confirma/evalúa explícitamente el nuevo contexto y conserva en auditoría el episodio sustituido.
Admin no adquiere permiso clínico, propietario no obtiene HU-4.17 y atención roja no espera regularización de identidad.
Nuevo comando resuelve S/C/ingreso actual, comprueba acceso/titularidad/datos permitidos/versiones y huella; revisión vieja no habilita nueva reserva, reasignación ni consulta sobre F.
Consulta en curso sigue el flujo clínico vigente con relectura/revalidación por veterinario; una finalizada no se reabre.

Eventos mantienen refs/destino/canal/clave; pendiente/error solo se reconstruye con identidad/contacto/causa y permisos actuales para el mismo destinatario.
Con revisión clínica vencida necesaria para el mensaje, esperar resolución; con causa/destinatario inválido, omitido, sin nuevo destinatario ni aviso por el solo cambio de identidad.
Reclamado/incierto pendiente de resolución impide confirmar conciliación y produce conflicto sin traslado; no se deja un worker enviando P tras confirmar F.
Worker reclama con candados y corte vigente, libera SQL antes de SMTP; la resolución operativa debe excluir que el envío anterior continúe antes de liberar esa barrera.
Enviado/incierto no se reenvía automáticamente.
Reintento idempotente exacto conserva carga/resultado histórico por IDs, pero reconstruye acuse solo con acceso/identidad actuales y no repite cambios/versiones/eventos; clave nueva necesita revisión vigente y otra carga con misma clave es conflicto.
Sin permiso actual no se devuelve el resultado antiguo.

La unidad usa clínica primero y el orden común del plan M4, incluyendo S activas/terminales, C, I, propuestas, pausas, actos y eventos.
Fallo después de mover C pero antes de S/I revierte todos los cambios/HC/versiones/revisión/auditoría nueva/operación.
Dos operadores esperan/releen: mismo destino confirmado, sin segundo traslado; otro destino, conflicto.
La ampliación P15.1–P15.12 del Anexo B1.1 concreta ejemplos de solicitud activa/finalizada, contexto distinto, evento, reintento y fallo intermedio; son propuestas, no tests implementados.
No cambian columnas/relaciones del MER/drawDB ni SQL ejecutable.

## 4. Verificación

RE-4.15.3 requiere pruebas de integración de las cuatro ramas y de la conciliación bajo fallo transaccional. Un caso del portal conserva la identidad de la sesión sin repetir la verificación. Un propietario de otra clínica no debe consultar el ingreso ni el contacto provisional. El carnet público debe responder con su mensaje genérico hasta que la ficha esté completa y activada. El flujo visual se revisa en 375 px, 820 px y 1280 px.
