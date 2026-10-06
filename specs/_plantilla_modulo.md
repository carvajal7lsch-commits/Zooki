# M<módulo> — Nombre del módulo o bloque

> Estado: borrador | aprobado | en curso | terminado
> Entrega: v2.0 | v2.1 · Fecha: AAAA-MM-DD
> Responsable de escritura: por asignar · Revisión independiente: por asignar

## 1. Resultado y límites

**Resultado:** qué podrá hacer el usuario al terminar este módulo.

**Situación v1:** qué datos y funciones existen hoy y deben conservarse.

**Destino v2:** enlace al [MER](../documentacion/MER.md) y a los flujos pertinentes.

**Fuera de alcance:** qué corresponde a otro módulo o entrega.

## 2. Trazabilidad

| HU | RE incluidos | RN/RNF | Etapa | Evidencia de aceptación |
|---|---|---|---|---|
| HU-… | RE-… | RN-…, RNF-… | A | Prueba o revisión visual concreta |

Los criterios normativos permanecen en `documentacion/`; este plan los enlaza y organiza, sin duplicarlos ni cambiarlos en silencio.

## 3. Etapas y dependencias

| Etapa | Entregable comprobable | Depende de | Escribe | Revisa |
|---|---|---|---|---|
| A | … | … | … | … |

Cada etapa indica qué HU/RE cierra y qué queda pendiente. Una etapa preparatoria de datos no se publica como función terminada.

## 4. Datos y migraciones

- **Inventario v1:** tablas, claves y referencias que cambian.
- **Migraciones:** `database/NN_nombre.sql` nuevas, repetibles y ordenadas; no editar las publicadas.
- **Conservación:** cómo se hace el relleno de datos y se comprueba que no haya huérfanos ni pérdida.
- **Compatibilidad y despliegue:** momento del cambio de código, restricciones finales y recuperación ante fallo.
- **MER:** toda columna o relación nueva aparece también en `documentacion/MER.md` y `database/drawdb_schema_v2.sql` antes de implementarse.

## 5. Código, permisos y pantallas

- **Modelos y helpers:** …
- **Controladores y rutas:** …
- **Matriz de `helpers/Security.php`:** …
- **Vistas y JS/CSS:** …
- **Aislamiento:** consultas propias por `id_clinica` y excepciones globales con prueba.

## 6. Riesgos y decisiones pendientes

| Asunto | Efecto | Resolución o dato requerido antes de implementar |
|---|---|---|
| … | … | … |

## 7. Tareas y verificación

- [ ] Aprobar este plan antes de implementar.
- [ ] Completar el inventario y las decisiones pendientes de la primera etapa.
- [ ] Implementar cada etapa y marcar sus tareas.
- [ ] Probar cada RE nuevo o tocado, migración repetible y aislamiento entre clínicas.
- [ ] Ejecutar `vendor/bin/phpunit` y revisar el diff.
- [ ] Actualizar documentación, versión e historial cuando se cierre una entrega de código.
- [ ] Entregar al usuario los comandos de commit para PowerShell.

**Resultado de pruebas:** fechas, comandos, resultados y limitaciones comprobadas.
