# Modelo Entidad-Relación (MER) - Zooki

Este documento describe la estructura y relaciones de la base de datos relacional de **Zooki**, diseñada para garantizar la integridad referencial, el historial clínico de los pacientes y la auditoría completa de los movimientos del sistema.

---

## Diagrama Entidad-Relación

El siguiente diagrama describe las tablas del sistema y sus relaciones. Está escrito en Mermaid, por lo que se versiona junto al código y se renderiza de forma nítida a cualquier nivel de zoom.

Las líneas continuas representan relaciones con clave foránea declarada; las punteadas, vínculos lógicos que el esquema no obliga a nivel de base de datos.

```mermaid
erDiagram
    roles {
        int id_rol PK
        varchar nombre_rol
    }
    usuarios {
        varchar documento PK
        int id_rol FK
        varchar nombre_completo
        varchar email
        tinyint estado
    }
    password_resets {
        int id PK
        varchar usuario_documento FK
        varchar token
        datetime expira
    }
    especies {
        int id_especie PK
        varchar nombre_especie
    }
    razas {
        int id_raza PK
        int id_especie FK
        varchar nombre_raza
    }
    colores_base {
        int id_color PK
        varchar nombre_color
    }
    mascotas {
        int id_mascota PK
        varchar numero_historia_clinica
        varchar doc_propietario FK
        int id_especie FK
        int id_raza FK
        varchar nombre
        decimal peso
        enum sexo
    }
    mascota_colores {
        int id PK
        int id_mascota FK
        int id_color FK
    }
    tipos_cita {
        int id_tipo_cita PK
        varchar nombre_tipo
        int duracion_minutos
    }
    citas {
        int id_cita PK
        int id_mascota FK
        varchar doc_veterinario FK
        int id_tipo_cita
        date fecha
        time hora
        varchar estado
    }
    consultas {
        int id_consulta PK
        int id_mascota FK
        varchar doc_veterinario FK
        int id_cita FK
        text diagnostico
        text plan_tratamiento
    }
    tratamientos {
        int id_tratamiento PK
        int id_consulta FK
        varchar medicamento
        varchar dosis
    }
    archivos_clinicos {
        int id_archivo PK
        int id_consulta FK
        varchar nombre_archivo
        varchar ruta
    }
    vacunas_base {
        int id_vacuna_base PK
        varchar nombre_vacuna
    }
    especie_vacunas {
        int id PK
        int id_especie FK
        int id_vacuna_base FK
    }
    vacunas {
        int id_vacuna PK
        int id_mascota FK
        varchar nombre_vacuna
        date fecha_aplicacion
        date fecha_proxima_dosis
    }
    desparasitaciones {
        int id_desparasitacion PK
        int id_mascota FK
        varchar tipo
        date fecha_aplicacion
        date fecha_proxima
    }
    laboratorios_base {
        int id_laboratorio PK
        varchar nombre_laboratorio
    }
    productos_desparasitacion_base {
        int id_producto PK
        varchar nombre_producto
    }
    notificaciones {
        int id_notificacion PK
        varchar doc_propietario FK
        varchar mensaje
        tinyint leida
    }
    notificaciones_internas {
        int id PK
        varchar doc_usuario FK
        int id_rol_destino FK
        varchar mensaje
    }
    auditoria_mascotas {
        int id_auditoria PK
        int id_mascota FK
        varchar usuario_doc FK
        varchar campo_modificado
        text valor_anterior
        text valor_nuevo
    }
    auditoria_sistema {
        int id_auditoria PK
        varchar usuario_doc
        varchar accion
        varchar tabla_afectada
        text datos_anteriores
        text datos_nuevos
    }
    horarios_clinica {
        int id PK
        varchar dia_semana
        tinyint activo
    }

    roles     ||--o{ usuarios                : "define el perfil de"
    usuarios  ||--o{ password_resets         : "solicita"
    usuarios  ||--o{ mascotas                : "es propietario de"
    especies  ||--o{ razas                   : "agrupa"
    especies  ||--o{ mascotas                : "clasifica"
    razas     ||--o{ mascotas                : "clasifica"
    mascotas  ||--o{ mascota_colores         : "tiene"
    colores_base ||--o{ mascota_colores      : "compone"
    mascotas  ||--o{ citas                   : "es agendada en"
    usuarios  ||--o{ citas                   : "atiende como veterinario"
    mascotas  ||--o{ consultas               : "recibe"
    usuarios  ||--o{ consultas               : "registra como veterinario"
    citas     ||--o| consultas               : "origina"
    consultas ||--o{ tratamientos            : "receta"
    consultas ||--o{ archivos_clinicos       : "adjunta"
    mascotas  ||--o{ vacunas                 : "recibe"
    mascotas  ||--o{ desparasitaciones       : "recibe"
    especies  ||--o{ especie_vacunas         : "requiere"
    vacunas_base ||--o{ especie_vacunas      : "aplica a"
    usuarios  ||--o{ notificaciones          : "recibe"
    usuarios  ||--o{ notificaciones_internas : "recibe"
    roles     ||--o{ notificaciones_internas : "segmenta"
    mascotas  ||--o{ auditoria_mascotas      : "genera"
    usuarios  ||--o{ auditoria_mascotas      : "ejecuta"

    tipos_cita ||..o{ citas                  : "define duracion (sin FK)"
    usuarios   ||..o{ auditoria_sistema      : "ejecuta (sin FK)"
    laboratorios_base ||..o{ vacunas         : "catalogo por nombre"
    productos_desparasitacion_base ||..o{ desparasitaciones : "catalogo por nombre"
```

---

## Detalle de Entidades Principales

### 1. Gestión de Acceso y Usuarios
*   **roles**: Define los roles de usuario (`1: Administrador`, `2: Veterinario`, `3: Recepcionista`, `4: Propietario`).
*   **usuarios**: Almacena datos personales, credenciales cifradas (o UID de Google Identity) y estado de activación (`activo`/`inactivo`). Clave foránea: `id_rol` referenciando a `roles`.
*   **password_resets**: Almacena tokens de recuperación temporales vinculados al documento del usuario.

### 2. Pacientes (Mascotas) y Catálogos
*   **mascotas**: Ficha del animal (nombre, especie, raza, sexo, peso, fecha de nacimiento, estado). Claves foráneas referenciando a `especies`, `razas` y `usuarios` (propietario).
*   **especies** y **razas**: Catálogos dinámicos que restringen los tipos de mascota disponibles y organizan la taxonomía animal.
*   **colores_base** y **mascota_colores**: Relación de muchos a muchos (`N:M`) para permitir que una mascota posea múltiples colores de pelaje registrados de manera independiente.

### 3. Operación Clínica
*   **citas**: Control de agenda veterinaria. Registra la fecha, hora, duración estimada, tipo de cita y veterinario asignado. Previene cruces de horarios.
*   **tipos_cita**: Configura la duración base y nombre de los servicios médicos (`Consulta general`, `Control`, `Vacunación`, `Cirugía`, etc.).
*   **consultas**: Ficha clínica generada por el veterinario. Almacena anamnesis, constantes fisiológicas (peso, temperatura, frecuencia cardíaca), diagnóstico y plan de tratamiento. Vinculada opcionalmente a una cita previa.
*   **tratamientos**: Medicamentos recetados, dosis y frecuencia asociados a una consulta médica específica.
*   **archivos_clinicos**: Indexación de exámenes médicos externos, imágenes de soporte o radiografías vinculadas a la historia clínica.

### 4. Monitoreo y Prevención
*   **vacunas**: Historial de vacunas aplicadas a cada mascota con la fecha de aplicación y la próxima dosis obligatoria.
*   **desparasitaciones**: Control de tratamientos preventivos internos/externos con fecha de próxima aplicación.
*   **vacunas_base** y **especie_vacunas**: Relación paramétrica que asocia qué vacunas corresponden a qué especies biológicas.

### 5. Auditoría y Control
*   **auditoria_mascotas**: Log de cambios específicos sobre las fichas de las mascotas (quién modificó, qué campo, valor anterior y nuevo).
*   **auditoria_sistema**: Historial de seguridad y operaciones del sistema completo, registrando acciones (`LOGIN`, `LOGOUT`, `INSERT`, `UPDATE`, `DELETE`) con los payloads JSON de datos anteriores y nuevos para una auditoría forense íntegra.

### 6. Configuración y Parámetros
*   **horarios_clinica**: Define los horarios de atención y bloques de disponibilidad (mañana y tarde) por día de la semana para la gestión de citas.
*   **laboratorios_base**: Catálogo de laboratorios farmacéuticos fabricantes de vacunas y medicamentos.
*   **productos_desparasitacion_base**: Catálogo parametrizado de productos desparasitantes disponibles, clasificados por tipo (interna, externa o ambas).

