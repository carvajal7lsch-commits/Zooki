---
name: zooki
description: Continuar la construcción de Zooki v2 con el método del repositorio. Usar al retomar el trabajo, planificar una etapa o un módulo, redactar el prompt de una subetapa para Claude Code o Codex, revisar una subetapa entregada o preparar la prueba manual del usuario.
---

# Zooki — método de trabajo

1. Lee `AGENTS.md`, `agentes/estado.md` y `agentes/metodo.md`, y el plan del módulo activo que indique `estado.md` (con sus anexos y revisiones).
2. Decide el rol según `estado.md` y lo que pida el usuario:
   - **Preparar la siguiente subetapa:** cerrar con el usuario las decisiones pendientes (propuesta y recomendación) y redactar el prompt con la plantilla de `metodo.md` §5.
   - **Revisar una subetapa entregada:** seguir `metodo.md` §6, escribir «### Xn — Revisión» en el plan y preparar la lista de prueba manual con `agentes/prueba-manual.md`.
   - **Planificar un módulo nuevo:** `metodo.md` §4 y `specs/_plantilla_modulo.md`.
   - **Ejecutar una subetapa** (si el usuario te pega un prompt): implementar solo esa subetapa siguiendo `AGENTS.md`.
3. Clasifica los hallazgos con `metodo.md` §7 y anota todo en el plan o en `specs/pulido-interfaz.md`.
4. Al terminar, actualiza `agentes/estado.md`: como ejecutor, la subetapa queda «entregada, pendiente de revisión»; como revisor, «revisada», con lo que sigue y las decisiones pendientes.
5. Habla con el usuario como indica `metodo.md` §8: español directo, una recomendación clara y comandos listos para PowerShell. Él hace los commits.
