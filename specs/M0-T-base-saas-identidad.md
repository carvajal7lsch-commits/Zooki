# M0-T — Base SaaS, identidad y aislamiento

> Estado: borrador para aprobación antes de implementar
> Entrega: v2.0 · Fecha: 2026-10-06
> Reparto propuesto: Codex lidera datos y seguridad; Claude lidera formularios y paneles; cada entrega la revisa el otro agente

## 1. Resultado y límites

**Resultado:** la instalación v1.12.0 pasa a ser la primera clínica de Zooki SaaS sin perder pacientes ni historia. Las personas tienen un `id_usuario` estable, pueden tener contextos por clínica y el servidor aísla cada operación según el contexto activo. Se puede registrar y activar una clínica nueva, respetar el consentimiento y aplicar el plan gratuito. Este bloque deja una base funcional para los dos grafos, pero no implementa los motores clínico ni de agenda.

**Situación v1:** `usuarios.documento` es la clave primaria y referencia de otras tablas; `usuarios.id_rol` representa un solo papel, existe recepcionista y las tablas de negocio carecen de `id_clinica`. Las migraciones publicadas llegan a `database/13_razas_portal.sql`. El migrador ejecuta las nuevas al desplegar. Antes de escribir SQL se inventarian todas las claves foráneas, índices, consultas y datos que dependen del documento o del rol.

**Destino v2:** [MER](../documentacion/MER.md), [reglas](../documentacion/ReglasNegocio.md), [requisitos](../documentacion/RequisitosEspecificos.md) y [plan de entregas](../documentacion/HistoriasUsuario.md#plan-de-entregas-de-la-v2). `database/drawdb_schema_v2.sql` es referencia para drawDB; **el migrador no lo ejecuta**. El Grafo I almacenará después nodos y aristas globales; el Grafo II se calculará después con los datos de agenda. Por eso no se crean todas sus tablas en la primera migración.

**Fuera de alcance de este bloque:** cálculo y carga clínica del Grafo I, agenda del Grafo II, urgencias de HU-4.15, interfaz de reseñas, comunicaciones y cobro real. Las tablas de negocio actuales sí reciben las claves necesarias para no exponer datos entre clínicas al cambiar de arquitectura. [El plan aprobado de HU-4.15](HU-4.15-ingreso-emergencia.md) se incorporará al módulo de agenda/urgencias cuando corresponda.

## 2. Trazabilidad

| HU de v2.0 | RE incluidos | RN/RNF principales | Etapa | Evidencia de aceptación |
|---|---|---|---|---|
| HU-T.15 | RE-T.15.1–5 | RN-G13, RN-G14, RN-001, RN-004, RNF-11 | C | Dos clínicas: petición cruzada 403, auditoría y ninguna consulta o modificación ajena. |
| HU-T.16 | RE-T.16.1–4 | RN-G17, RN-G05, RNF-13, RNF-14 | D | Sesión vencida, AJAX 401, cookie segura y auditoría. |
| HU-T.17 | RE-T.17.1–5 | RN-G01, RN-G06, RN-G13, RN-G18 | C | Una persona con rol de propietario y personal en dos clínicas cambia contexto sin mezclar permisos. |
| HU-T.19 | RE-T.19.1–4 | RN-G19, RN-G20 | D | Ninguna cuenta nueva sin aceptación del titular; prueba de versión, medio, fecha e IP. |
| HU-5.8 | RE-5.8.1–5 | RN-109, RN-G06 | D | Una identidad global se vincula a clínicas elegidas, sin duplicar correo ni exponerla a otra clínica. |
| HU-0.1 | RE-0.1.1–10 | RN-001, RN-002, RN-010, RN-011, RN-G19, RN-G20 | D | Alta pública con NIT válido, CAPTCHA, límites, consentimiento y clínica pendiente. |
| HU-0.2 | RE-0.2.1–4 | RN-002, RN-003, RN-005, RN-G11 | D | Verificación activa la clínica y asigna el plan gratuito; enlace vencido no la activa. |
| HU-0.3 | RE-0.3.1–6 | RN-004, RN-012, RN-804, RN-G13, RN-G14 | E | Panel opera sobre clínicas sin abrir historia clínica; moderación comprobada según la decisión de dependencia con HU-8.1. |
| HU-0.4 | RE-0.4.1–7 | RN-003, RN-005, RN-006, RN-420 | E | Topes gratuitos por clínica; mascota vinculada cuenta; urgencia roja nunca se bloquea. |

Los RE completos y sus criterios están en `documentacion/RequisitosEspecificos.md`; esta tabla solo organiza la entrega. No se marca una HU como terminada por haber creado sus tablas: necesita también el flujo y las pruebas que le corresponden.

## 3. Etapas y dependencias

| Etapa | Entregable comprobable | Depende de | Escritura | Revisión |
|---|---|---|---|---|
| A. Inventario y decisiones de datos | Mapa de todas las FK y consultas por documento/rol, duplicados y huérfanos, datos de la clínica legada, respaldo restaurable y decisiones pendientes resueltas. | v1.12.0 y MER v2 | Codex | Claude, solo lectura |
| B. Esquema y relleno conservador | Migraciones nuevas a partir de `14_...sql`: tablas SaaS, claves `id_usuario`/`id_clinica`, puentes, índices y relleno de datos existentes; conteos y FK comprobados. No se publica sola como funcionalidad. | A | Codex | Claude, solo lectura |
| C. Cambio completo de identidad y aislamiento | Modelos, sesión, permisos, rutas y flujos v1 adaptados a las claves nuevas; dos clínicas aisladas. Cierra HU-T.15 y HU-T.17 al pasar pruebas. | B | Codex | Claude, solo lectura |
| D. Consentimiento, registro y activación | Cierra HU-T.16, HU-T.19, HU-5.8, HU-0.1 y HU-0.2 con formularios, correo, auditoría y pruebas. | C | Claude | Codex, solo lectura |
| E. Administración y límites | Cierra HU-0.3 y HU-0.4 solo tras cumplir todos sus RE y resolver la dependencia de moderación. | D | Claude | Codex, solo lectura |

Este reparto es una propuesta que el usuario puede cambiar antes de cada etapa. Se entrega el diff y el resultado de pruebas al revisor mediante este plan y el checkout; si Claude opera en una app sin acceso al repositorio, el usuario le comparte el plan y el diff. En el mismo checkout el segundo agente revisa sin editar; solo se trabaja en paralelo en checkouts separados y sin archivos compartidos. El usuario hace ramas y commits.

## 4. Datos y migraciones

1. **Inventario previo:** listar todas las tablas que hoy apuntan a `usuarios.documento` (mascotas, citas, consultas, notificaciones, auditoría, tokens y demás), los registros con referencias faltantes, correos/documentos duplicados y cuentas recepcionistas. Registrar conteos por tabla antes de migrar. No leer ni versionar `.env`.
2. **Primera clínica:** crear el registro de la clínica legada y vincularle todos los datos de negocio existentes. Su NIT y contacto reales deben conocerse antes del despliegue; no usar un NIT inventado. Definir qué cuenta será superadministradora sin elevar silenciosamente a un usuario de v1.
3. **Identidad:** agregar `usuarios.id_usuario` estable y poblarlo para cada persona; crear `usuario_clinica` y `propietario_clinica` según los roles y vínculos comprobados. Agregar columnas `id_usuario` a las tablas que hoy referencian documento; rellenarlas por unión controlada, verificar igualdad de conteos y ausencia de huérfanos, y cambiar las FK. Solo después se retiran las dependencias del documento como clave. Conservar documento y correo como datos corregibles y únicos según el MER.
4. **Aislamiento:** agregar `id_clinica` a los registros propios de clínica y rellenarlo con la clínica legada. Las excepciones globales son las enumeradas por RNF-11 y RN-113; cada una lleva prueba. Crear índices y restricciones tras comprobar datos. No confundir `mascotas` global con sus registros clínicos por clínica.
5. **Tablas SaaS y permisos:** crear las tablas del MER necesarias en este bloque (`planes`, `clinicas`, `suscripciones`, `usuario_clinica`, `propietario_clinica`, `consentimientos_datos` y apoyo requerido), con valores por defecto de la especificación. Las cuentas de recepcionista no se convierten automáticamente en administradores ni veterinarios: su transición se define antes del cambio de código.
6. **Orden y repetibilidad:** numerar las migraciones desde 14, separando preparación, relleno y restricciones cuando haga falta. Cada archivo consulta existencia de columnas/índices y puede ejecutarse dos veces sin error; nunca modifica las migraciones 01–13. Probar en una copia restaurable de v1 con MySQL 8 y en MariaDB local. Como MySQL confirma automáticamente muchas operaciones DDL, documentar recuperación desde respaldo ante fallo intermedio.
7. **Cambio de aplicación:** pasar modelos, controladores, rutas, sesión y `helpers/Security.php` al nuevo esquema en una entrega coherente. No dejar una función de v1 consultando documentos como FK ni una ruta que omita la clínica. Las interfaces y pruebas se cierran con los RE de la etapa; una migración de tablas sola no completa una HU.

## 5. Código, permisos y pantallas

- **Modelos y helpers:** `models/Usuario.php`, `models/Mascota.php` y todos los modelos que hoy relacionan personas por documento o leen datos propios de clínica; `helpers/Security.php` centraliza contexto y autorización. El inventario de A define la lista exhaustiva antes de implementar.
- **Controladores y rutas:** `controllers/AuthController.php`, `UsuarioController.php`, `PropietarioController.php`, `MascotaController.php` y rutas correspondientes en `public/index.php`; añadir cada acción nueva a la matriz de permisos.
- **Pantallas:** selector de contexto, registro/activación de clínica, consentimiento, acceso del propietario y panel de plataforma. Verificación visual en móvil, tablet y escritorio; sin CSS o JS en línea.
- **Aislamiento:** las consultas de negocio usan el `id_clinica` activo desde modelos/seguridad. Una mascota y una identidad globales se acceden solo mediante el vínculo y permiso pertinente. El superadministrador no obtiene historia clínica por ser superadministrador.

## 6. Riesgos y decisiones antes de la etapa B

| Asunto | Efecto | Resolución requerida |
|---|---|---|
| NIT/contacto de la clínica v1 | `clinicas.nit` es obligatorio y único en el MER. | Obtener los datos reales de la clínica legada; no fabricar un valor. |
| Cuenta inicial de superadministrador | Una elevación equivocada da acceso de plataforma. | Identificar explícitamente la cuenta o un mecanismo de alta seguro; no elevar automáticamente al admin v1. |
| Cuentas con rol recepcionista | El rol desaparece en v2. | Decidir para cada cuenta si pasa a administrador, veterinario o queda inactiva, sin perder auditoría. |
| Duplicados/huérfanos en datos v1 | Bloquean índices únicos, relleno y FK. | Preflight de solo lectura y resolución documentada antes de imponer restricciones. |
| Consentimientos anteriores | No se puede inventar una aceptación histórica. | Conservar lo que exista y solicitar aceptación vigente al titular donde falte prueba, sin crearla retroactivamente. |
| HU-0.3, RE-0.3.6 frente a HU-8.1 en v2.1 | La moderación de reseñas depende de una función aún no entregada. | Antes de cerrar HU-0.3, decidir si se prueba el moderador con datos de prueba en v2.0 o se mueve ese RE a la entrega de reputación y se actualiza la trazabilidad. |
| Despliegue de cambios DDL | Una falla a mitad de migración puede dejar esquema parcial. | Ensayo de restauración, migraciones repetibles y comprobación posterior antes de habilitar la aplicación v2. |

## 7. Tareas y verificación

- [ ] Aprobar este plan antes de implementar código o migraciones.
- [ ] Completar A: inventario de FK/consultas, integridad, cuentas y datos de la clínica legada sin tocar producción.
- [ ] Resolver y registrar las decisiones de la sección 6 que afecten B.
- [ ] Implementar B con migraciones nuevas y comprobar dos ejecuciones y restauración en una copia v1.
- [ ] Implementar C y sus pruebas de aislamiento, cambio de contexto y conservación de datos.
- [ ] Implementar D y sus pruebas de consentimiento, alta, verificación, sesiones y vínculos.
- [ ] Implementar E y sus pruebas de permisos, topes y excepción de urgencia roja.
- [ ] Ejecutar `vendor/bin/phpunit` completo y revisar cada RE de la tabla con evidencia.
- [ ] Actualizar HU, RE, MER, diagramas, `config/App.php` e `HistorialVersiones.md` cuando corresponda a la entrega implementada; regenerar descargas del portal si cambian documentos publicados.
- [ ] Entregar al usuario comandos de commit por etapa para PowerShell; el usuario crea ramas, commits, push y tags.

**Verificación del plan documental:** comprobar que las HU/RE enumeradas existen, que el plan no propone ejecutar `drawdb_schema_v2.sql` y que no incluye una migración publicada modificada. Pruebas de aplicación y migración: pendientes hasta la implementación.
