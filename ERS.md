  -----------------------------------------------------------------------

  -----------------------------------------------------------------------

Especificación de requisitos de software

Proyecto: Zooki- Propuesta 16 -- App Registro y Seguimiento de Mascotas

> Revisión 1.0

  -----------------------------------------------------------------------
  ![](./image1.emf)             Abril
  ------------------------- --------- -----------------------------------

  -----------------------------------------------------------------------

# Ficha del documento {#ficha-del-documento .Titulo-1-sin-numeracion .unnumbered}

  ---------------------------------------------------------------------------------
  **Fecha**    **Revisión**   **Autor**                 **Verificado dep.
                                                        calidad.**
  ------------ -------------- ------------------------- ---------------------------
  27/04/2026   1.0            Juan Sebastian Carvajal

  ---------------------------------------------------------------------------------

Documento validado por las partes en fecha:

  -----------------------------------------------------------------------
  Por el cliente                      Por la empresa suministradora
  ----------------------------------- -----------------------------------


  Fdo. D./ Dña                        Fdo. D./Dña
  -----------------------------------------------------------------------

# Contenido {#contenido .Titulo-1-sin-numeracion .unnumbered}

[Ficha del documento [2](#ficha-del-documento)](#ficha-del-documento)

[Contenido [3](#contenido)](#contenido)

[1 Introducción [5](#introducción)](#introducción)

[1.1 Propósito [5](#propósito)](#propósito)

[1.2 Alcance [5](#alcance)](#alcance)

[1.3 Personal involucrado
[5](#personal-involucrado)](#personal-involucrado)

[1.4 Definiciones, acrónimos y abreviaturas
[6](#definiciones-acrónimos-y-abreviaturas)](#definiciones-acrónimos-y-abreviaturas)

[1.5 Referencias Normativas
[6](#referencias-normativas)](#referencias-normativas)

[2 Descripción general [7](#descripción-general)](#descripción-general)

[2.1 Perspectiva del producto
[7](#perspectiva-del-producto)](#perspectiva-del-producto)

[2.2 Funciones principales
[7](#funciones-principales)](#funciones-principales)

[2.3 Características de los usuarios
[8](#características-de-los-usuarios)](#características-de-los-usuarios)

[2.4 Restricciones [8](#restricciones)](#restricciones)

[3 Requisitos específicos
[8](#requisitos-específicos)](#requisitos-específicos)

[3.1 Requisitos comunes de los interfaces
[8](#requisitos-comunes-de-los-interfaces)](#requisitos-comunes-de-los-interfaces)

[3.1.1 Interfaces de usuario
[8](#interfaces-de-usuario)](#interfaces-de-usuario)

[3.1.2 Interfaces de Software y Comunicación
[9](#interfaces-de-software-y-comunicación)](#interfaces-de-software-y-comunicación)

[3.2 Requisitos funcionales
[9](#requisitos-funcionales)](#requisitos-funcionales)

[3.2.1 Módulo 1: Registro y Gestión de Mascotas (Sprint 1)
[9](#módulo-1-registro-y-gestión-de-mascotas-sprint-1)](#módulo-1-registro-y-gestión-de-mascotas-sprint-1)

[3.2.2 Módulo 2: Historia Clínica Veterinaria (Sprint 2)
[10](#módulo-2-historia-clínica-veterinaria-sprint-2)](#módulo-2-historia-clínica-veterinaria-sprint-2)

[3.2.3 Módulo 3: Calendario de Vacunación y Recordatorios (Sprint 3)
[10](#módulo-3-calendario-de-vacunación-y-recordatorios-sprint-3)](#módulo-3-calendario-de-vacunación-y-recordatorios-sprint-3)

[3.2.4 Módulo 4: Agenda de Citas y Portal del Propietario (Sprint 4)
[11](#módulo-4-agenda-de-citas-y-portal-del-propietario-sprint-4)](#módulo-4-agenda-de-citas-y-portal-del-propietario-sprint-4)

[3.3 Requisitos no funcionales
[11](#requisitos-no-funcionales)](#requisitos-no-funcionales)

[3.3.1 Rendimiento [11](#rendimiento)](#rendimiento)

[3.3.2 Seguridad y Privacidad
[12](#seguridad-y-privacidad)](#seguridad-y-privacidad)

[3.3.3 Usabilidad y accesibilidad
[12](#usabilidad-y-accesibilidad)](#usabilidad-y-accesibilidad)

[3.3.4 Confiabilidad y Disponibilidad
[13](#confiabilidad-y-disponibilidad)](#confiabilidad-y-disponibilidad)

[3.3.5 Mantenibilidad y Portabilidad
[13](#mantenibilidad-y-portabilidad)](#mantenibilidad-y-portabilidad)

[4 Apéndices [13](#apéndices)](#apéndices)

[4.1 Casos de Uso [13](#casos-de-uso)](#casos-de-uso)

[4.2 Trazabilidad Sprint -- Requisitos
[14](#trazabilidad-sprint-requisitos)](#trazabilidad-sprint-requisitos)

[4.3 Criterios de Aceptación Global
[14](#criterios-de-aceptación-global)](#criterios-de-aceptación-global)

#  Introducción

## Propósito

> El presente documento constituye la Especificación de Requisitos de
> Software (ERS) del sistema **Zooki**, elaborado conforme al estándar
> IEEE 830-1998. Su propósito es describir de manera precisa, completa y
> verificable los requisitos funcionales y no funcionales del software,
> sirviendo como acuerdo formal entre el equipo de desarrollo y los
> interesados del proyecto. Este ERS será el referente principal para
> las fases de diseño, implementación, pruebas y validación durante los
> cuatro sprints del ciclo Scrum.

## Alcance

**Zooki** es una aplicación web diseñada para digitalizar y optimizar la
gestión clínica de pequeñas veterinarias en Neiva, Huila. El sistema
abarca:

-   Registro y actualización de fichas de mascotas con información del
    propietario.

-   Gestión completa de historias clínicas: consultas, diagnósticos,
    tratamientos y archivos adjuntos.

-   Calendario inteligente de vacunación y desparasitación con alertas
    automáticas.

-   Módulo de agenda de citas con control de disponibilidad del
    veterinario.

-   Envío automatizado de recordatorios vía correo electrónico y
    WhatsApp.

-   Portal web para que los propietarios consulten la ficha digital de
    su mascota.

-   Búsqueda avanzada de pacientes y generación de reportes básicos.

> **Fuera de alcance:** facturación electrónica, inventario de
> medicamentos, integración con laboratorios externos y nómina de
> personal.

## Personal involucrado

  -----------------------------------------------------------------------
  Nombre                Juan Sebastian Carvajal Home
  --------------------- -------------------------------------------------
  Rol                   Desarollador / Scrum Master / Development Team

  Categoría profesional Estudiante

  Responsabilidades     Análisis, diseño, desarrollo y documentación del
                        sistema web

  Información de        Carvajal7lsch@gmail.com
  contacto

  Aprobación            Responsable de validar la versión final del
                        sistema y entregar el documento de requisitos.
  -----------------------------------------------------------------------

## Definiciones, acrónimos y abreviaturas

  -----------------------------------------------------------------------
  **Termino / Sigla**                **Definición**
  ---------------------------------- ------------------------------------
  **IEEE**                           Institute of Electrical and
                                     Electronics Engineers

  **RF / RNF**                       Requisito Funcional / Requisito No
                                     Funcional

  **ERS**                            Especificación de Requisitos de
                                     Software

  **ADSO**                           Análisis y Desarrollo de Software

  **CRUD**                           Create, Read, Update, Delete --
                                     operaciones básicas de datos

  **API**                            Interfaz de Programación de
                                     Aplicaciones

  **Sprint**                         Iteración de desarrollo en
                                     metodología Scrum (1 semana)

  **Propietario/Tenedor**            Persona responsable legal de la
                                     mascota registrada

  **Historia Clínica**               Registro digital de todas las
                                     atenciones médicas del paciente

  **Zooki**                          Nombre comercial del sistema de
                                     software a desarrollar

  **Scrum Master**                   líder servicial y facilitador que
                                     guía al equipo de desarrollo y a la
                                     organización en la adopción del
                                     marco de trabajo Scrum

  **Development Team**               grupo autoorganizado y
                                     multifuncional de profesionales
                                     dedicados a crear incrementos de
                                     producto funcionales, generalmente
                                     en entornos ágiles como Scrum
  -----------------------------------------------------------------------

## Referencias Normativas

-   IEEE Std 830-1998 -- Práctica recomendada para ERS.

-   Ley 84 de 1989 -- Estatuto Nacional de Protección de los Animales
    > (Colombia).

-   Ley 1712 de 2014 -- Transparencia y Acceso a la Información Pública.

-   Resolución 1995 de 1999 (Min. Salud) -- Manejo de historias
    > clínicas.

# Descripción general

## Perspectiva del producto

> **Zooki** nace como respuesta a una problemática real documentada en
> clínicas veterinarias de pequeña escala en Neiva: la dependencia de
> fichas en cartón, agendas físicas y la ausencia total de mecanismos de
> seguimiento proactivo al paciente animal.

| **Contexto Del problema** |
| --- |
| - **Ubicación:** Clínicas veterinarias de pequeña escala en Neiva Huila, Colombia. |
| - **Problema central:** Historias clínicas en papel → pérdida de información y trazabilidad nula. |
| - **Consecuencia directa:** Los propietarios olvidan fechas de vacunación y desparasitación. |
| - **Impacto en el negocio:** La veterinaria pierde oportunidades de seguimiento y fidelización |
| - **Solución propuesta:** Aplicación web con recordatorios automáticos y portal del propietario. |

## Funciones principales

| ■ **Gestión de Pacientes** - Registro de mascotas y propietarios - Fotografía y datos biométricos - Búsqueda en tiempo real - Ficha completa del propietario | ■ **Historia Clín - Consultas y diagnósticos - Tratamientos y medicamentos - Archivos clínicos (imágenes) ```{=html} <!-- --> ``` - Trazabilidad de atenciones | ca** **■ Agenda Inteligente** - Citas con disponibilidad - Calendario de vacunas - Desparasitació programada ```{=html} <!-- --> ``` - Recordatorios automáticos |
| --- | --- | --- |
| ■ **Notificaciones** - Envío por email - Mensajes WhatsApp - Alertas configurables - Historial de envíos | ■ **Portal Propietario** - Acceso a ficha digital - Historial de consultas - Próximas citas ```{=html} <!-- --> ``` - Descarga de documentos | **■ Reportes** - Pacientes por especie - Citas del período - Vacunaciones pendientes - Exportación PDF |

## Características de los usuarios

  -----------------------------------------------------------------------
  **ROL**              **Descripción y Nivel de Acceso**
  -------------------- --------------------------------------------------
  **Veterinario /      Usuario principal. Gestiona pacientes, historias
  Admin**              clínicas, agenda y reportes. Nivel técnico
                       medio-bajo; interfaz debe ser intuitiva.

  **Recepcionista**    Gestiona citas y registra nuevos pacientes. Manejo
                       básico de computador

  **Propietario**      Accede solo al portal externo con sus mascotas.
                       Interfaz simple y guiada.

  **Admin del          Perfil técnico para mantenimiento, backups y
  Sistema**            gestión de usuarios.
  -----------------------------------------------------------------------

## Restricciones

-   El sistema debe ser accesible desde Chrome, Firefox y Edge sin
    instalación adicional.

-   El desarrollo se limita a cuatro sprints de una semana cada uno.

-   La información clínica debe almacenarse en servidores compatibles
    con la normativa colombiana de protección de datos.

-   Los mensajes de WhatsApp se gestionarán a través de la API oficial
    de WhatsApp Business. • El sistema debe operar con conexión mínima
    de 1 Mbps.

# Requisitos específicos

## Requisitos comunes de los interfaces

### Interfaces de usuario

  -----------------------------------------------------------------------
  **Vista / Módulo**   **Descripción de la Interfaz**
  -------------------- --------------------------------------------------
  **Dashboard          Panel con resumen del día: citas, vacunaciones
  principal**          próximas y alertas. Acceso rápido a funciones
                       frecuentes.

  **Gestión de         Listado con buscador en tiempo real, filtros por
  pacientes**          especie y formulario de registro con validación en
                       línea.

  **Historia clínica** Vista cronológica con acordeón. Formulario de
                       consulta con campos dinámicos y visor de archivos
                       integrado.

  **Calendario**       Vista mensual/semanal con citas y vacunas marcadas
                       en colores diferenciados.

  **Portal del         Interfaz simplificada mobile-first con tarjetas
  propietario**        visuales por mascota.

  **Reportes**         Filtros de fecha, previsualización en pantalla y
                       exportación a PDF.
  -----------------------------------------------------------------------

### Interfaces de Software y Comunicación

  -----------------------------------------------------------------------
  **Vista / Módulo**   **Descripción de la Interfaz**
  -------------------- --------------------------------------------------
  **API WhatsApp       Envío de recordatorios vía Meta. Requiere número
  Business**           empresarial verificado y plantillas aprobadas. A
                       CORREGIR

  **SMTP / SendGrid**  Correos transaccionales: confirmación de citas y
                       recordatorios de vacunas.

  **Almacenamiento     Fotografías y archivos clínicos con URLs firmadas
  (S3/Cloudinary)**    y tiempo de expiración.

  **Navegadores        Chrome ≥ 90, Firefox ≥ 88, Edge ≥ 90, Safari ≥ 14.
  soportados**

  **Base de datos**    MySQL/PostgreSQL o MongoDB, según decisión del
                       equipo en Sprint 1.
  -----------------------------------------------------------------------

## Requisitos funcionales

### Módulo 1: Registro y Gestión de Mascotas (Sprint 1)

  -----------------------------------------------------------------------------------
  **ID**      **Descripción del            **Prioridad**   **Criterio de
              Requisito**                                  Verificación**
  ----------- ---------------------------- --------------- --------------------------
  **RF-01**   El sistema debe permitir     **Alta**        Registro aparece en
              registrar una mascota con:                   búsqueda con todos los
              nombre, especie, raza, fecha                 campos correctos.
              de nacimiento, peso, sexo,
              color y fotografía
              principal.

  **RF-02**   Cada mascota debe vincularse **Alta**        No es posible guardar una
              a un propietario con: nombre                 mascota sin propietario
              completo, documento,                         asignado.
              dirección, teléfono, correo
              y WhatsApp (opcional).

  **RF-03**   Permitir actualizar datos de **Alta**        Auditoría de cambios
              la ficha registrando                         visible en la ficha.
              automáticamente fecha y
              usuario del cambio.

  **RF-04**   Buscar mascotas por nombre,  **Alta**        Resultados en \< 2 s con
              propietario, número de                       mínimo 3 caracteres
              documento o ficha interna,                   ingresados.
              con resultados en tiempo
              real.

  **RF-05**   Registrar múltiples mascotas **Media**       Desde el perfil del
              para un mismo propietario,                   propietario se listan
              visualizadas agrupadas en su                 todas sus mascotas.
              perfil.

  **RF-06**   Marcar mascota como inactiva **Media**       Mascota inactiva no
              (fallecida/retirada) sin                     aparece en búsquedas
              eliminar su historial                        activas pero su historial
              clínico.                                     permanece.
  -----------------------------------------------------------------------------------

### Módulo 2: Historia Clínica Veterinaria (Sprint 2)

  -----------------------------------------------------------------------------------
  **ID**      **Descripción del            **Prioridad**   **Criterio de
              Requisito**                                  Verificación**
  ----------- ---------------------------- --------------- --------------------------
  **RF-07**   Registrar consultas con:     **Alta**        Consulta visible en
              fecha/hora, motivo,                          historial cronológico de
              anamnesis, examen físico                     la mascota.
              (peso, temperatura, FC),
              diagnóstico y plan de
              tratamiento.

  **RF-08**   Adjuntar archivos clínicos   **Alta**        Archivos se almacenan y
              (JPG, PNG, PDF) de máximo 10                 son descargables desde la
              MB por archivo en cada                       consulta.
              consulta.

  **RF-09**   Registrar tratamientos con:  **Alta**        Tratamiento queda
              medicamento, dosis, vía de                   vinculado a la consulta.
              administración, duración y
              observaciones.

  **RF-10**   Generar número de historia   **Media**       Número no se repite en
              clínica único y consecutivo                  toda la base de datos.
              por mascota en su primera
              consulta.

  **RF-11**   Visualizar resumen           **Media**       Vista carga en \< 3 s para
              cronológico de todas las                     historiales de hasta 100
              consultas en una vista única                 consultas.
              con acordeón expandible.
  -----------------------------------------------------------------------------------

### Módulo 3: Calendario de Vacunación y Recordatorios (Sprint 3)

  -----------------------------------------------------------------------------------
  **ID**      **Descripción del            **Prioridad**   **Criterio de
              Requisito**                                  Verificación**
  ----------- ---------------------------- --------------- --------------------------
  **RF-12**   Registrar vacunas con:       **Alta**        Vacuna aparece en
              nombre, laboratorio, lote,                   calendario y genera alerta
              fecha de aplicación y fecha                  7 días antes del
              de próxima dosis.                            vencimiento.

  **RF-13**   Enviar recordatorios         **Alta**        Correos recibidos en inbox
              automáticos por email al                     con datos correctos sin
              propietario 7 días y 1 día                   caer en spam.
              antes del vencimiento de
              vacuna o desparasitación.

  **RF-14**   Enviar recordatorios         **Alta**        Mensaje entregado
              opcionales vía WhatsApp                      correctamente en cuenta de
              Business API con el mismo                    prueba.
              contenido del correo.

  **RF-15**   Panel de control con         **Media**       Panel muestra datos
              vacunaciones pendientes de                   correctos vs. base de
              la semana, agrupadas por día                 datos.
              y especie.

  **RF-16**   Registrar esquema de         **Media**       Alertas se generan según
              desparasitación                              la periodicidad
              (interna/externa) con                        configurada.
              periodicidad configurable
              (mensual, trimestral,
              semestral).
  -----------------------------------------------------------------------------------

### Módulo 4: Agenda de Citas y Portal del Propietario (Sprint 4)

  -----------------------------------------------------------------------------------
  **ID**      **Descripción del            **Prioridad**   **Criterio de
              Requisito**                                  Verificación**
  ----------- ---------------------------- --------------- --------------------------
  **RF-17**   Agendar citas con: fecha,    **Alta**        No permite doble cita al
              hora, mascota, motivo y                      mismo veterinario en el
              veterinario asignado,                        mismo horario.
              verificando disponibilidad
              automáticamente.

  **RF-18**   Enviar confirmación          **Alta**        Correo de confirmación
              automática de la cita al                     recibido en \< 5 minutos
              propietario por correo en el                 con datos correctos.
              momento de su creación.

  **RF-19**   Portal web con credenciales  **Alta**        Propietario solo ve sus
              propias para el propietario:                 mascotas; acceso a datos
              ficha de mascota(s),                         de terceros devuelve 403.
              historial, citas y
              calendario de vacunas.

  **RF-20**   Cancelar o reprogramar       **Media**       Propietario recibe
              citas, notificando                           notificación de cambio en
              automáticamente al                           \< 5 minutos.
              propietario.

  **RF-21**   Generar reportes exportables **Media**       PDF descargado contiene
              en PDF: listado de                           datos fidedignos.
              pacientes, citas del período
              y vacunaciones pendientes.

  **RF-22**   Permitir el auto-registro de  **Alta**        El propietario puede crearse
              propietarios y el agendado                   cuenta de forma autónoma y
              de citas directamente desde                  agendar citas desde su
              su propio portal.                            propia sesión.
  -----------------------------------------------------------------------------------

## Requisitos no funcionales

### Rendimiento

  ------------------------------------------------------------------------------------
  **ID**       **Descripción del            **Prioridad**   **Criterio de
               Requisito**                                  Verificación**
  ------------ ---------------------------- --------------- --------------------------
  **RNF-01**   El tiempo de respuesta para  **Alta**        Prueba de carga con JMeter
               consultas y búsquedas no                     a 10 usuarios
               debe superar los 3 segundos                  concurrentes.
               con red ≥ 5 Mbps.

  **RNF-02**   El sistema debe soportar al  **Media**       . Prueba de 20 usuarios
               menos 20 usuarios                            simultáneos sin errores
               concurrentes sin degradación                 HTTP 5xx.
               visible.
  ------------------------------------------------------------------------------------

### Seguridad y Privacidad

  ------------------------------------------------------------------------------------
  **ID**       **Descripción del            **Prioridad**   **Criterio de
               Requisito**                                  Verificación**
  ------------ ---------------------------- --------------- --------------------------
  **RNF-03**   Comunicación cifrada con     **Alta**        Headers confirman HTTPS
               HTTPS (TLS 1.2+).                            activo; contraseñas no
               Contraseñas almacenadas con                  legibles en BD.
               hashing bcrypt + salt.

  **RNF-04**   Control de acceso basado en  **Alta**        Usuario \'Propietario\' no
               roles (RBAC): veterinario,                   accede a rutas de
               recepcionista, propietario y                 administración (devuelve
               administrador.                               403).

  **RNF-05**   Archivos clínicos            **Alta**        URL directa sin token
               almacenados con acceso solo                  devuelve error 401.
               para usuarios autenticados y
               autorizados.

  **RNF-06**   Log de auditoría de          **Media**       Log muestra entradas
               operaciones críticas:                        correctas tras operaciones
               creación, edición y                          de prueba.
               eliminación de registros,
               con usuario, fecha e IP
  ------------------------------------------------------------------------------------

### Usabilidad y accesibilidad

  ------------------------------------------------------------------------------------
  **ID**       **Descripción del            **Prioridad**   **Criterio de
               Requisito**                                  Verificación**
  ------------ ---------------------------- --------------- --------------------------
  **RNF-07**   Interfaz responsiva:         **Alta**        Prueba visual en Chrome
               escritorio (≥1024px), tablet                 DevTools sin superposición
               (768--1023px) y móvil                        de elementos.
               (320--767px).

  **RNF-08**   Usuario nuevo capaz de       **Media**       Prueba con 3 usuarios
               registrar una mascota y                      reales; tiempo promedio \<
               agendar cita en \< 10                        10 min.
               minutos sin asistencia.

  **RNF-09**   Mensajes de error en español **Media**       Ningún error muestra texto
               colombiano, descriptivos y                   en inglés o códigos
               con acción correctiva                        técnicos al usuario final.
               propuesta.
  ------------------------------------------------------------------------------------

### Confiabilidad y Disponibilidad

  ------------------------------------------------------------------------------------
  **ID**       **Descripción del            **Prioridad**   **Criterio de
               Requisito**                                  Verificación**
  ------------ ---------------------------- --------------- --------------------------
  **RNF-10**   Disponibilidad mínima del    **Alta**        Monitoreo de uptime
               95% en horario de operación                  durante el período de
               (lunes a sábado, 7:00 a.m.                   pruebas del Sprint 4.
               -- 8:00 p.m.).

  **RNF-11**   Copias de seguridad          **Alta**        Verificación de backups
               automáticas de la BD cada 24                 tras 72 horas de operación
               horas en servicio externo al                 continua.
               servidor principal.
  ------------------------------------------------------------------------------------

### Mantenibilidad y Portabilidad

  ------------------------------------------------------------------------------------
  **ID**       **Descripción del            **Prioridad**   **Criterio de
               Requisito**                                  Verificación**
  ------------ ---------------------------- --------------- --------------------------
  **RNF-12**   Código fuente documentado en **Media**       Reporte de cobertura
               README; cobertura mínima de                  generado con la
               pruebas unitarias del 60%.                   herramienta del stack
                                                            seleccionado.

  **RNF-13**   Sistema desplegable en       **Media**       Despliegue exitoso en
               entornos de desarrollo y                     ambos entornos sin
               producción usando variables                  modificar código fuente.
               de entorno.
  ------------------------------------------------------------------------------------

# Apéndices

## Casos de Uso

  ------------------------------------------------------------------------
  **CU-ID**         **Nombre del Caso **Actor           **RF
                    de Uso**          Principal**       Relacionados**
  ----------------- ----------------- ----------------- ------------------
  CU-01             Registrar mascota Vet. /            RF-01, RF-02
                    y propietario     Recepcionista

  CU-02             Actualizar ficha  Veterinario       RF-03, RF-06
                    de mascota

  CU-03             Buscar paciente   Vet. /            RF-04
                                      Recepcionista

  CU-04             Registrar         Veterinario       RF-07, RF-08,
                    consulta clínica                    RF-09, RF-10

  CU-05             Ver historia      Veterinario       RF-11
                    clínica completa

  CU-06             Registrar         Veterinario       RF-12, RF-15
                    vacunación

  CU-07             Enviar            Sistema           RF-13, RF-14
                    recordatorio
                    automático

  CU-08             Agendar cita      Recep. /          RF-17, RF-18
                                      Veterinario

  CU-09             Cancelar o        Vet. /            RF-20
                    reprogramar cita  Recepcionista

  CU-10             Acceder al portal Propietario       RF-19
                    del propietario

  CU-11             Generar reporte   Veterinario       RF-21
  ------------------------------------------------------------------------

## Trazabilidad Sprint -- Requisitos

  ------------------------------------------------------------------------
  **Sprint**        **Nombre**        **Requisitos      **Entregable**
                                      Cubiertos**
  ----------------- ----------------- ----------------- ------------------
  Sprint 1          Registro de       RF-01 a RF-06 ·   CRUD de mascotas y
                    mascotas y        RNF-03, RNF-04,   propietarios +
                    propietarios      RNF-07            búsqueda activa

  Sprint 2          Historia clínica  RF-07 a RF-11 ·   Módulo historia
                    y consultas       RNF-01, RNF-05,   clínica con
                                      RNF-06            archivos adjuntos

  Sprint 3          Calendario y      RF-12 a RF-16 ·   Alertas
                    recordatorios     RNF-08, RNF-10    automáticas
                                                        email/WhatsApp
                                                        funcionando

  Sprint 4          Portal            RF-17 a RF-21 ·   Portal externo
                    propietario y     RNF-09,           activo, generación
                    reportes          RNF-11--13        de reportes PDF
  ------------------------------------------------------------------------

## Criterios de Aceptación Global

El sistema se considera listo para entrega al final del Sprint 4 si y
solo si:

-   El 100% de los RF de prioridad Alta han sido implementados y
    superado sus pruebas de aceptación.

-   El 80% de los RF de prioridad Media han sido implementados.

-   No existen defectos críticos (Severity 1) ni mayores (Severity 2)
    sin cerrar.

-   El portal del propietario ha sido validado con al menos un usuario
    real externo al equipo.

-   Los recordatorios automáticos (email y WhatsApp) fueron probados en
    entorno de producción.

-   La documentación técnica (README, manual de usuario y este ERS
    actualizado) está en el repositorio.
