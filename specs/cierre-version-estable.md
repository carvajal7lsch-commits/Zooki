# Cierre de la versión estable antes del cambio de arquitectura

> Estado: terminado (falta el despliegue)
> Versión base: v1.10.1 · Versión resultante: v1.11.0 · Fecha: 2026-09-22

Inventario de lo que faltaba para dar la versión por completa, tal como se encontró en v1.10.1 (§1 a §6), y lo que se hizo (§7). Sale de cruzar `HistoriasUsuario.md`, `RequisitosEspecificos.md`, `ReglasNegocio.md`, `ERS.md`, `MER.md` y `AuditoriaModulos.md` con el código. La suite está en verde (202 pruebas, 793 aserciones).

## 1. Resumen

| Estado declarado en las HU | Cantidad | Historias |
|---|---|---|
| Implementada | 44 | 3 de ellas no cumplen sus criterios (ver §2) |
| Parcial | 4 | HU-27, HU-29, HU-31, HU-50 |
| Pendiente | 7 | HU-28, HU-30, HU-37, HU-51, HU-52, HU-53, HU-55 |
| Futuro | 1 | HU-11 |
| Retirada | 1 | HU-16 |

Los RE no llevan estado propio: heredan el de su HU. Por eso una HU «Implementada» con un RE sin cumplir no se nota en el documento de requisitos.

## 2. Marcadas como implementadas, pero no cumplen

| HU / RN | Qué dice la documentación | Qué hay en el código |
|---|---|---|
| **HU-44** Gestionar catálogos (RE-44.x, RN-501, RN-502) | El administrador gestiona especies, razas, colores, vacunas base, laboratorios y productos. | No existe pantalla de catálogos: `admin_configuracion` solo carga `views/admin/horarios.php`. No hay rutas para crear especies, razas ni colores. Vacunas, laboratorios y productos solo se crean de paso al registrar el acto clínico, y la matriz los da **solo al veterinario** (`$soloVet`), al contrario de RN-501, que los reserva al administrador. |
| **HU-23** Respaldo automático (RNF-11) | Respaldo diario con retención. | `scripts/backup.php` usa `mysqldump`, que la imagen `php:8.2-apache` no trae. El README de v1.9.0 pide crear en Dokploy los Schedules de recordatorios y del vigilante, pero no el de respaldo. Tampoco hay volumen para `BACKUP_DIR`. En producción no se está respaldando. |
| **HU-20** Panel de vacunaciones (RE-20.2) | Agrupa por día **y especie**. | La nota de la HU ya admite que la agrupación por especie no se muestra. El estado debería ser Parcial, o hay que quitar el criterio. |
| **RN-401 / RN-402** | «Aplicada». | Tienen los huecos de HU-31 (ver §3): la validación de horario no considera la duración y no hay protección contra la doble reserva de horarios que se solapan sin empezar a la misma hora. |

## 3. Parciales: qué falta en cada una

| HU | RE cumplidos | Falta |
|---|---|---|
| **HU-31** Validación de disponibilidad (Alta) | — | **RE-31.1:** `Cita::getSugerenciasHorario` recorre un horario fijo de 08:00 a 18:00 y no lee `horarios_clinica`. El calendario interno y el portal (`portal.js`) ofrecen horas que después el servidor rechaza, y no ofrecen las que caen fuera de ese rango. **RE-31.2:** `HorarioClinicaController::validarHorarioLaboral` solo mira la hora de inicio: una cita de 60 min a las 11:45 pasa el cierre de las 12:00. Además ignora `bloque_*_activo`, así que un bloque desactivado que conserva sus horas sigue aceptando citas. **RE-31.3:** el índice `uq_cita_vet_activa` solo impide dos citas que empiezan a la misma hora. Dos reservas simultáneas de 10:00 (60 min) y 10:30 pasan las dos. |
| **HU-50** Cancelar o reprogramar desde el portal (Alta) | RE-50.1, RE-50.3 | **RE-50.2:** no existe reprogramar en el portal (ni en `portal.js` ni en `views/portal/`). **RE-50.4:** `cancelarAjax` avisa a la clínica, pero no escribe en `auditoria_sistema`. |
| **HU-27** Hora real y retrasos (Alta) | RE-27.1 | **RE-27.2:** no hay detección del exceso sobre la duración planificada. **RE-27.3:** tampoco hay aviso de corrimiento a las citas siguientes. El `VigilanteAtenciones` ya avisa a los 10 min de la hora de fin, así que RE-27.2 podría darse por cubierto si se redacta como «aviso al veterinario», y dejar RE-27.3 como un aviso a recepción. |
| **HU-29** No asistió y ausentismo (Media) | RE-29.1, RE-29.2 | **RE-29.3:** no hay tasa de ausentismo. Solo existe el conteo de `no_asistidas` en `models/Panel.php`, en valor absoluto y sin la tasa. |

## 4. Pendientes

| HU | Prioridad | Observación |
|---|---|---|
| **HU-37** Robustez de recordatorios | Media | Está peor de lo que dice la HU. `send_reminders.php` busca fechas exactas (`= CURDATE() + 7` y `+ 1`), así que un día sin cron se pierde. Un envío fallido queda en `notificaciones` con estado `error`, y la comprobación de duplicado lo encuentra y lo **salta para siempre**: no hay reintento posible. Las fechas salen de `CURDATE()` de MySQL, no de la zona de la clínica. |
| **HU-30** Bloqueos y festivos | **Alta** | No hay tabla ni pantalla. Es la única pendiente de prioridad Alta. |
| **HU-28** Buffer entre citas | Media | Depende de dónde se guarde el parámetro: es el primer uso natural de HU-53. |
| **HU-53** Parámetros configurables | Media | Deseable. |
| **HU-51** Confirmar asistencia desde el recordatorio | Media | Deseable. Necesita un enlace firmado sin sesión. |
| **HU-52** Notificaciones del propietario | Media | Deseable. Hoy el propietario no tiene nada en el portal. |
| **HU-55** Exportar a Excel/CSV | Media | **Incoherente:** su criterio es «cada reporte disponible en PDF» y depende de HU-16, que se retiró en v1.9.1. Hay que reescribirla o retirarla. |
| **HU-11** WhatsApp | Futuro | Ya está fuera de alcance, no requiere nada. |

## 5. Otros hallazgos de esta revisión

Estos no tienen HU propia. El módulo 3 (vacunación y recordatorios) no se ha auditado todavía.

- **Recordatorios a mascotas inactivas:** la consulta de vacunas y desparasitaciones no filtra `mascotas.estado`. Una mascota desactivada, por ejemplo fallecida, le sigue generando correos al propietario. Corresponde a HU-10 y HU-04.
- **Recordatorios de dosis ya renovadas:** al registrar una dosis nueva, la anterior conserva su `fecha_proxima_dosis`. Si la mascota se vacuna antes de tiempo, el recordatorio de la dosis vieja sale igual. Corresponde a HU-10.
- **RN-305** dice «estado fallido»; la columna `notificaciones.estado` usa `error`. Es solo de redacción.
- **Contraseña en archivo versionado:** `docker-compose.yml` lleva escrita la clave de `MYSQL_ROOT_PASSWORD`. Debe salir al `.env` (y a `.env.example`) según AGENTS.md.
- **Cron:** `scripts/zooki.cron` no dice que en producción se reemplaza por Schedules de Dokploy. La hora de las 7:00 depende de `TZ` en el servicio `web`.

## 6. Documentación desalineada

| Documento | Problema |
|---|---|
| `ERS.md` §4.2 | Dice 24 tablas. Faltan `intentos_login` (migración 04) y `verificaciones_email` (05), y la tabla de control de migraciones. |
| `ERS.md` §4.3 | Titulada «(v1.8.0)». RF-27 (catálogos) figura como Implementado (ver HU-44). |
| `MER.md` | No refleja las migraciones 09–13: estados `sin_cerrar` y `cerrada_sin_consulta`, `slot_activo`, `raza_indicada`, `password_definida`, columnas de aviso. Tampoco incluye `intentos_login` ni `verificaciones_email`. |
| `ReglasNegocio.md` | RN-501 (solo el administrador gestiona catálogos) choca con HU-44 («administrador o veterinario») y con la matriz actual (solo veterinario). Hay que elegir una. |
| `AuditoriaModulos.md` | Los módulos 3, 5, 6, 7 y 8 siguen «Pendiente». M4-20 figura Abierto, pero HU-18 (v1.9.1) ya lista las atenciones sin cerrar: debería cerrarse. TR-01 bajó de 692/230/29 a 319 `style=`, 248 `onclick=`/`onchange=`/`onsubmit=` y 19 `<script>`, y la tabla no está al día. |

## 7. Alcance acordado y tareas

**Decisión (2026-09-22):** todo lo que toca agendamiento de citas, consultas y catálogos queda **fuera**, porque es lo que más cambia con la arquitectura nueva. Esas historias pasan a estado «diferida» con sus criterios intactos: HU-20, HU-27, HU-28, HU-29, HU-30, HU-31, HU-44, HU-50, HU-51 y HU-53. Lo demás se cierra en v1.11.0.

**Respaldos (HU-23)**

- [x] Volcado con PDO en `models/Respaldo.php` en lugar de `mysqldump`. El cliente de Debian es el de MariaDB y no garantiza autenticarse contra MySQL 8 sin TLS, y aquí no había Docker para probarlo. El volcado corre en una instantánea (`START TRANSACTION WITH CONSISTENT SNAPSHOT`) y omite las columnas generadas.
- [x] `scripts/backup.php` usa el modelo y conserva la verificación, la rotación y el log.
- [x] Volumen `respaldos` en `docker-compose.yml` y carpeta `/var/backups/zooki` en el `Dockerfile`.
- [x] `BACKUP_DIR` documentado en `.env.example`; `zooki.cron` aclara que en producción se usan Schedules de Dokploy.
- [ ] **Despliegue:** crear el Schedule del respaldo en Dokploy y ejecutarlo una vez (pasos en el README, v1.11.0).

**Recordatorios (HU-37)**

- [x] `helpers/VentanaRecordatorio.php`: ventana del primer aviso (día 7 al 2) y del último (día 1 y 0), y «hoy» en la zona de la clínica (RE-37.1, RE-37.3).
- [x] `models/Recordatorio.php`: selección de dosis y citas, y reintento hasta 3 intentos (RE-37.2).
- [x] RE-37.4 (nuevo): excluir mascotas inactivas y dosis ya renovadas. **Pendiente de tu aprobación**, porque es un requisito agregado en este cierre.
- [x] `scripts/send_reminders.php` usa el modelo; los correos no cambian.

**Seguridad**

- [x] `MYSQL_ROOT_PASSWORD` sale de `DB_ROOT_PASS` en el `.env`.

**Documentación**

- [x] HU: estado «diferida» (definido en el encabezado), HU-37 implementada, notas en HU-20, HU-23, HU-27 a HU-31, HU-44, HU-50, HU-51 y HU-53.
- [x] RE-23.1, RE-23.2, RE-37.2 y RE-37.4, y la matriz de trazabilidad.
- [x] RN-305 (estado de error y reintento); RN-401 y RN-402 en estado «Parcial».
- [x] ERS: 27 tablas y trazabilidad v1.11.0, con RF-27 parcial.
- [x] MER: tablas y columnas de las migraciones 04 a 13; entidad `notificaciones` corregida.
- [x] Auditoría: M4-20 cerrado, conteo de TR-01 actualizado y sección de cierre del módulo 3 (M3-01 a M3-05).
- [x] README v1.11.0 y `config/App.php`.

**Sin decidir**

- HU-52 (notificaciones del propietario en el portal) y HU-55 (exportar a Excel/CSV) no son de agenda, pero son funciones nuevas. HU-55 depende de HU-16, que está retirada.
- La deuda de TR-01 (estilos y manejadores en línea) y TR-05 (front controller) se deja para la arquitectura nueva.

## 8. Verificación

| Punto | Cómo | Resultado |
|---|---|---|
| RE-37.1, RE-37.3 | `tests/Unit/VentanaRecordatorioTest.php` | 5 pruebas en verde |
| RE-37.2, RE-37.4, RN-304 | `tests/Integration/RecordatorioTest.php` (SQLite) | 5 pruebas en verde |
| Consultas de recordatorios en MariaDB | Script de solo lectura contra la base local, sin enviar correos | Sin errores; selecciona las dosis esperadas |
| RE-23.1 | Volcado de la base local y restauración en una base temporal | 26 tablas con las mismas filas; contenido idéntico en `citas`, `usuarios`, `mascotas` y `consultas` |
| RE-23.2, RE-23.3 | Manual en Dokploy tras desplegar | Pendiente |
| Suite completa | `vendor/bin/phpunit` | 212 pruebas, 813 aserciones, en verde |
