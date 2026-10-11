# HU-4.15 — Ingreso presencial de emergencia sin identidad verificada

> Estado: aprobado por el usuario el 2026-10-06; pendiente de implementación
> Versión prevista: v2.0 · Fecha: 2026-10-06

## 1. Especificación (qué y por qué)

**Problema:** una urgencia roja que llega físicamente a la clínica puede no tener mascota registrada, cuenta del propietario o una relación verificable entre el acompañante y la mascota. Exigir una cuenta y una ficha completas bloquearía la atención; crear una cuenta sin consentimiento contradice RN-G19. En el portal autenticado, la sesión ya identifica al propietario y a la mascota seleccionada.

**Historia:** [HU-4.15](../documentacion/HistoriasUsuario.md#hu-415--atender-una-urgencia-triage-rojo-o-llegada-directa). La clínica atiende primero y concilia la identidad después.

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| RE-4.15.3 | En el portal autenticado, reutilizar la identidad de la sesión y la mascota seleccionada. En un ingreso presencial, reutilizar cuenta y mascota existentes si se verifica la relación sin retrasar la atención; de lo contrario crear ficha e ingreso provisionales sin atribuir propiedad ni crear cuenta. Después de verificar titularidad, completar o consolidar la ficha. | Un caso rojo del portal conserva `id_usuario` e `id_mascota` sin pedir otra verificación. En ingreso presencial con cuenta verificada se reutilizan los mismos registros; sin verificación, la consulta se guarda sobre una mascota provisional y no se crea usuario. Al conciliar, los actos clínicos conservan su clínica y veterinario. |

**Reglas de negocio:** RN-G19, RN-110, RN-206, RN-207, RN-420, RN-421 y RN-427.

**Fuera de alcance:** pedir una nueva verificación de identidad al propietario autenticado en el portal; crear cuentas automáticamente para acompañantes; activar el carnet público de una ficha provisional; usar el ingreso provisional para el agendamiento ordinario.

## 2. Plan (cómo)

- **Archivos:** adaptar modelos y controladores de mascota, propietario y urgencia; actualizar el flujo en `documentacion/Modelos.md` y la matriz de `helpers/Security.php` para las acciones nuevas. La clínica solo consulta sus ingresos provisionales.
- **Datos:** migración nueva, idempotente y conservadora de datos v1. `mascotas` admite campos incompletos solo con `ficha_por_completar = 1`; `ingresos_emergencia` guarda clínica, ficha provisional, ficha final al conciliar, datos disponibles del acompañante y estado. El esquema de referencia está en `database/modelo/drawdb_schema_v2.sql` y el MER. La migración real se numera cuando se implemente esta historia.
- **Transacción de conciliación:** verificar titularidad; si la mascota ya existía, trasladar las referencias clínicas de la provisional a la ficha final, conservar `id_clinica` y `id_veterinario`, marcar la provisional inactiva y registrar el vínculo en `ingresos_emergencia`; si era nueva, completar la misma ficha. Auditar ambos caminos.
- **Rutas y permisos:** la urgencia iniciada en el portal usa la sesión y la mascota seleccionada. Solo personal clínico autorizado crea un ingreso provisional presencial; solo el propietario verificado o personal autorizado completa o consolida; ninguna petición pública obtiene los datos del acompañante.
- **Pantallas:** captura mínima y aviso de «ficha por completar» en Atención/Urgencias; tarea de conciliación visible al personal en móvil, tablet y escritorio.
- **Riesgos:** fuga de datos del acompañante, atribución errónea de propiedad, duplicación de mascota, pérdida de actos clínicos y bloqueo de urgencias por límite del plan. Se evitan con permisos, transacción, auditoría y pruebas específicas.

## 3. Tareas

- [x] Alinear HU, RE, MER, esquema de referencia y flujo documental.
- [x] Aprobar el flujo: portal autenticado con identidad conocida; ingreso presencial con ficha existente verificada o ficha provisional.
- [ ] Definir en la implementación la captura mínima, acceso y retención de los datos del acompañante conforme a la política de datos de la plataforma.
- [ ] Escribir migración idempotente sin alterar migraciones publicadas.
- [ ] Implementar ingreso provisional y conciliación.
- [ ] Añadir rutas y permisos a `public/index.php` y `helpers/Security.php`.
- [ ] Probar las ramas: portal autenticado con mascota seleccionada, ingreso presencial con cuenta y mascota verificadas, cuenta existente sin relación aún verificada, y propietario sin cuenta.
- [ ] Probar aislamiento por clínica, ausencia de cuenta sin consentimiento, conservación de actos clínicos y excepción roja al límite del plan.
- [ ] Pasar `vendor/bin/phpunit`; actualizar versión y `documentacion/HistorialVersiones.md` cuando se implemente.

## 4. Verificación

RE-4.15.3 requiere pruebas de integración de las cuatro ramas y de la conciliación bajo fallo transaccional. Un caso del portal conserva la identidad de la sesión sin repetir la verificación. Un propietario de otra clínica no debe consultar el ingreso ni el contacto provisional. El carnet público debe responder con su mensaje genérico hasta que la ficha esté completa y activada. El flujo visual se revisa en 375 px, 820 px y 1280 px.
