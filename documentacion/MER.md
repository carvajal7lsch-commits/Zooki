# Modelo Entidad-Relación (MER) - Zooki v2.0

> **Carnet B — 2026-10-23:** 59 tablas. `carnet_accesos` cuenta también tokens inválidos sin guardar mascota ni token; `carnet_escaneos` conserva solo vistas válidas y ubicaciones consentidas. La IP se seudonimiza con HMAC-SHA256 y un secreto del entorno.

> **Actualización de cierre M2 — 16/10/2026:** 2.0.0-alpha.4; conocimiento global con fuentes y validación veterinaria pendiente. Esquema de 52 tablas; integración del triage con agenda y urgencias pendiente de M4.

> **Preparación M4/B2 — 2026-10-18:** MER/drawDB y database/01_schema.sql describen las mismas 58 tablas; 08 actualiza alpha.4 de forma aditiva y repetible. El esquema queda preparado y probado en la base exclusiva de tests, sin despliegue a producción ni flujos C implementados; producción alpha.4 conserva 52 hasta desplegar. Sin cambio de revisión ni versión.

Este documento describe la estructura y relaciones de la base de datos relacional de **Zooki** en su versión **v2.0** (arquitectura SaaS multi-inquilino). El modelo incorpora el aislamiento de datos por clínica (`id_clinica`), el grafo de conocimiento clínico, la suscripción por planes y la reputación de veterinarios, además del historial clínico y la auditoría ya existentes.

---

## Aislamiento multi-inquilino

Cada clínica es un **inquilino** identificado por `id_clinica`. Las tablas de negocio incorporan esa columna y toda consulta la filtra. Son **globales** (compartidas por todas las clínicas, sin `id_clinica`): los catálogos taxonómicos (`especies`, `razas`, `colores_base`), el catálogo de `planes` y `especialidades`, y el **grafo de conocimiento clínico** (`grafo_nodos`, `grafo_aristas`, `grafo_sinonimos`, `grafo_reglas`). El **super-administrador** no pertenece a ninguna clínica (`usuarios.es_super_admin`).

Los **propietarios** también son **identidades globales**: un único registro por persona, con **correo único en toda la plataforma**. Se vinculan a una o varias clínicas mediante la tabla puente **`propietario_clinica`** (el correo se verifica al crear la cuenta; si ya inició sesión y está verificado, puede elegir otra clínica sin repetir la verificación). Una misma persona puede tener **varios roles**: su rol de propietario (por `propietario_clinica`) y, en cada clínica donde trabaja, un rol de personal (administrador o veterinario) por la tabla **`usuario_clinica`**. Así un veterinario que trabaja en dos clínicas y tiene su propio perro usa una sola cuenta. Las **mascotas** también son **globales**: pertenecen a su propietario, no a una clínica, y se vinculan a cada clínica donde se atienden mediante la tabla puente **`mascota_clinica`** (que guarda además el número de historia clínica de esa clínica). Así una mascota tiene **una sola ficha, un solo carnet y un solo QR** en toda la plataforma. La ficha provisional de urgencia es la excepción temporal: se registra sin propietario verificado y con carnet inactivo hasta completarla o consolidarla (RE-4.15.3). El aislamiento se mantiene en los **registros clínicos**: `consultas`, `vacunas` y `desparasitaciones` llevan el `id_clinica` (y el veterinario) de la clínica que los creó, y solo esa clínica puede modificarlos. Otra clínica vinculada ve siempre las alergias y alertas médicas (`alertas_medicas`), las vacunas y desparasitaciones; las consultas de otras clínicas solo si el propietario la autorizó (`propietario_clinica.autoriza_historia_compartida`). Ver [Reglas de Negocio RN-110 a RN-115](ReglasNegocio.md#módulo-1--mascotas-y-propietarios).

## Diagrama Entidad-Relación

Escrito en Mermaid; se versiona junto al código y se renderiza a cualquier nivel de zoom. Las líneas continuas representan relaciones con clave foránea declarada; las punteadas, vínculos lógicos que el esquema no obliga.

```mermaid
erDiagram
    fuentes_pago {
        bigint id_fuente PK
        int id_clinica
        enum entorno
        varchar id_fuente_wompi
        enum tipo
        varchar marca
        char ultimos_cuatro
        enum estado
        int creada_por
        datetime creada_en
        datetime retirada_en
        datetime consentida_en
        varchar version_contratos
        json contratos_json
        varchar version_autorizacion
        bigint importe_mensual_autorizado_centavos
    }
    pagos_suscripcion {
        bigint id_pago PK
        int id_clinica
        int id_suscripcion
        int id_plan
        char clave_obligacion
        int version_periodo_base
        enum ciclo
        char moneda
        bigint subtotal_centavos
        bigint impuesto_centavos
        bigint total_centavos
        varchar tarifa_version
        char clave_operacion
        datetime cotizacion_hasta
        enum estado
        datetime proximo_intento
        int id_usuario_solicitante
        datetime creado_en
        datetime confirmado_en
    }
    intentos_pago {
        bigint id_intento PK
        bigint id_pago
        int numero
        enum entorno
        varchar referencia
        varchar id_transaccion_wompi
        bigint id_fuente
        enum origen
        enum estado
        varchar codigo_error
        datetime creado_en
        datetime enviado_en
        datetime consultado_en
        char lease_token
        datetime lease_hasta
    }
    wompi_eventos {
        bigint id_evento PK
        char clave_evento
        varchar id_evento_proveedor
        enum entorno
        varchar tipo
        varchar id_transaccion_wompi
        bigint timestamp_proveedor
        char checksum
        char huella_datos_firmados
        json datos_minimos
        bigint id_intento
        enum estado
        datetime recibido_en
        datetime procesado_en
        char lease_token
        datetime lease_hasta
        int intentos_proceso
        datetime proxima_revision
        varchar codigo_error
    }
    suscripcion_periodos {
        bigint id_periodo PK
        int id_clinica
        int id_suscripcion
        int id_plan
        bigint id_pago
        enum origen
        enum ciclo
        datetime inicio
        datetime fin
        int dia_ancla
        int mes_ancla
        time hora_ancla
        bigint total_centavos
        char moneda
        datetime creado_en
        int id_usuario_actor
        text motivo
    }
    avisos_suscripcion {
        bigint id_aviso PK
        int id_clinica
        int id_suscripcion
        bigint id_pago
        bigint id_intento
        int id_usuario_destinatario
        enum tipo
        char clave_aviso
        datetime vencimiento_objetivo
        datetime programado_en
        enum estado
        int id_notificacion
        char lease_token
        datetime lease_hasta
        int intentos
        datetime enviado_en
    }
    planes {
        int id_plan PK
        varchar nombre
        int precio_mensual
        int precio_anual
        int limite_mascotas
        int limite_citas_mes
        int limite_personal
    }
    clinicas {
        int id_clinica PK
        varchar nombre
        varchar nit UK
        varchar direccion
        varchar telefono
        varchar telefono_urgencias
        int id_plan FK
        enum estado
        int tolerancia_llegada_min
        int plazo_reasignacion_min
        int umbral_aviso_min
        int tope_sobrecupos
        int suscripcion_gracia_dias
        int aviso_vencimiento_1_dias
        int aviso_vencimiento_2_dias
    }
    suscripciones {
        int id_suscripcion PK
        int id_clinica FK
        int id_plan FK
        enum estado
        enum ciclo
        tinyint al_dia
        date fecha_inicio
        date fecha_fin
        enum gestion_cobro
        datetime periodo_inicio
        datetime periodo_fin
        enum estado_cobro
        int version_periodo
        int cobro_automatico
        int id_fuente_activa
        int autorizado_por
        datetime autorizado_en
        datetime retirado_en
        enum cambio_ciclo_siguiente
    }
    plantillas_comunicacion {
        int id_plantilla PK
        int id_clinica FK
        varchar tipo
        varchar asunto
        text cuerpo
        int dias_anticipacion
        time hora_envio
    }
    roles {
        int id_rol PK
        varchar nombre_rol
    }
    usuarios {
        int id_usuario PK
        varchar documento UK
        varchar nombre_completo
        varchar email UK
        varchar google_uid UK
        tinyint perfil_completo
        tinyint es_super_admin
        tinyint estado
        int version_sesion
    }
    consentimientos_datos {
        int id_consentimiento PK
        int id_usuario FK
        varchar version_politica
        enum medio
        datetime fecha
    }
    usuario_clinica {
        int id_usuario PK
        int id_clinica PK
        int id_rol FK
        enum estado
        datetime fecha_vinculo
    }
    propietario_clinica {
        int id_propietario PK
        int id_clinica PK
        enum estado
        datetime fecha_vinculo
        tinyint autoriza_historia_compartida
    }
    mascota_clinica {
        int id_mascota PK
        int id_clinica PK
        varchar numero_historia_clinica
        enum estado
        datetime fecha_vinculo
    }
    password_resets {
        int id PK
        int id_usuario FK
        varchar token_hash
        datetime expires_at
        tinyint used
    }
    verificaciones_email {
        int id PK
        int id_usuario FK
        int id_clinica_vinculo FK
        int id_rol_vinculo FK
        varchar proposito
        varchar email
        varchar nombre_invitado
        varchar documento_invitado
        varchar email_invitado
        varchar token_hash
        datetime expires_at
        tinyint used
    }
    intentos_login {
        int id_intento PK
        varchar identificador UK
        int intentos
        datetime bloqueado_hasta
        datetime ultimo_intento
    }
    casos_soporte {
        int id_caso PK
        enum tipo
        int id_usuario FK
        int id_clinica FK
        text descripcion
        enum estado
        datetime fecha
    }
    carnet_escaneos {
        int id_escaneo PK
        int id_mascota FK
        datetime fecha
        char ip_hash
        decimal latitud
        decimal longitud
        tinyint notificado
    }
    carnet_accesos {
        int id_acceso PK
        char ip_hash
        datetime fecha
    }
    horarios_veterinario {
        int id PK
        int id_veterinario FK
        int id_clinica FK
        int dia_semana
        time hora_inicio
        time hora_fin
        tinyint activo
    }
    ausencias_veterinario {
        int id PK
        int id_veterinario FK
        int id_clinica FK
        datetime fecha_hora_inicio
        datetime fecha_hora_fin
        int id_cobertura FK
        varchar motivo
        enum estado
        int id_solicitante FK
        int id_revisor FK
        datetime fecha_revision
        varchar motivo_resolucion
    }
    propuestas_horario {
        int id_propuesta PK
        int id_veterinario FK
        int id_clinica FK
        json franjas
        enum estado
        int id_revisor FK
        datetime fecha
    }
    reasignaciones {
        int id_reasignacion PK
        int id_cita FK
        int id_veterinario_origen FK
        int id_veterinario_destino FK
        enum estado
        datetime fecha_limite
        datetime fecha_respuesta
        int id_clinica FK
        datetime inicio_propuesto
        datetime fin_propuesto
        int version_cita
        int ciclo
        varchar motivo
        int cita_pendiente
    }
    especialidades {
        int id_especialidad PK
        varchar nombre
    }
    veterinario_perfil {
        int id_veterinario PK
        text bio
        varchar url_foto
    }
    veterinario_especialidades {
        int id_veterinario PK
        int id_especialidad PK
    }
    resenas_veterinario {
        int id_resena PK
        int id_cita FK
        int id_veterinario FK
        int id_propietario FK
        tinyint estrellas
        text comentario
        tinyint oculta
        datetime fecha
    }
    grafo_nodos {
        int id_nodo PK
        varchar codigo
        tinyint activo
        varchar version_conocimiento
        json fuentes
        enum estado_validacion
        enum tipo
        varchar etiqueta
        enum nivel_triage
        tinyint alarma_universal
        tinyint moderado
        json metadatos
        int id_especie FK
        int id_raza FK
    }
    grafo_aristas {
        int id_arista PK
        varchar codigo
        tinyint activo
        varchar version_conocimiento
        json fuentes
        enum estado_validacion
        int id_origen FK
        int id_destino FK
        varchar tipo_relacion
        enum signo
        decimal peso
        enum efecto
        json condiciones
    }
    grafo_sinonimos {
        int id_sinonimo PK
        varchar codigo
        tinyint activo
        varchar version_conocimiento
        json fuentes
        enum estado_validacion
        int id_nodo FK
        varchar texto
        varchar normalizado
    }
    grafo_reglas {
        int id_regla PK
        varchar codigo
        tinyint activo
        varchar version_conocimiento
        json fuentes
        enum estado_validacion
        enum motor
        json condiciones
        json resultado
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
        int id_propietario FK
        int id_clinica_registro FK
        char token_carnet UK
        tinyint carnet_activo
        tinyint ficha_por_completar
        int id_especie FK
        int id_raza FK
        varchar nombre
        decimal peso
        enum sexo
        tinyint esterilizado
        tinyint estado
    }
    ingresos_emergencia {
        int id_ingreso PK
        int id_clinica FK
        int id_mascota_provisional FK
        int id_mascota_final FK
        varchar nombre_acompanante
        varchar documento_acompanante
        varchar telefono_acompanante
        enum estado
        datetime fecha_ingreso
        int id_conciliador FK
        datetime fecha_conciliacion
        json evidencia_conciliacion
        datetime necesidad_contacto_resuelta_en
        varchar motivo_conservacion_contacto
        datetime contacto_anonimizado_en
    }
    alertas_medicas {
        int id_alerta PK
        int id_mascota FK
        int id_clinica FK
        int id_veterinario FK
        enum tipo
        int id_nodo FK
        varchar descripcion
        tinyint activa
        datetime fecha
    }
    mascota_colores {
        int id_mascota PK
        int id_color PK
    }
    tipos_cita {
        int id_tipo_cita PK
        int id_clinica FK
        varchar nombre_tipo
        int duracion_minutos
        int margen_minutos
        tinyint pausable
    }
    citas {
        int id_cita PK
        int id_clinica FK
        int id_mascota FK
        int id_veterinario FK
        int id_tipo_cita FK
        date fecha
        time hora
        int duracion_minutos
        int margen_minutos
        enum prioridad
        enum prioridad_calculada
        varchar motivo_ajuste_prioridad
        tinyint es_sobrecupo
        int orden_sobrecupo
        text sintomas_texto
        datetime inicio_sintomas
        enum estado
        tinyint ocupa_horario "calculada: NULL si está libre, es sobrecupo o urgencia"
        datetime hora_llegada
        datetime hora_inicio_real
        datetime hora_fin_real
        datetime aviso_atencion_abierta
        json contexto_triage
        enum origen
        int id_bloque FK
        datetime inicio_estimado
        datetime fin_estimado
        int duracion_activa_objetivo_segundos
        varchar politica_temporal
        enum estado_reajuste
        varchar motivo_reajuste
        int version_agenda
        datetime fecha_ultimo_calculo
        datetime ultima_hora_comunicada
        datetime ultimo_aviso_estimacion
    }
    cita_sintomas {
        int id_cita PK
        int id_nodo PK
        json detalles
    }
    consultas {
        int id_consulta PK
        int id_clinica FK
        int id_mascota FK
        int id_veterinario FK
        int id_cita FK
        text diagnostico
        text plan_tratamiento
    }
    consulta_sintomas {
        int id_consulta PK
        int id_nodo PK
        json detalles
    }
    tratamientos {
        int id_tratamiento PK
        int id_consulta FK
        int id_nodo_farmaco FK
        varchar medicamento
        varchar dosis
        date fecha_inicio
        date fecha_fin
    }
    archivos_clinicos {
        int id_archivo PK
        int id_consulta FK
        varchar nombre_original
        varchar ruta_archivo
    }
    vacunas_base {
        int id_vacuna_base PK
        int id_clinica FK
        varchar nombre_vacuna
    }
    especie_vacunas {
        int id_especie_vacuna PK
        int id_especie FK
        int id_vacuna_base FK
    }
    vacunas {
        int id_vacuna PK
        int id_clinica FK
        int id_mascota FK
        int id_veterinario FK
        varchar nombre_vacuna
        date fecha_aplicacion
        date fecha_proxima_dosis
    }
    desparasitaciones {
        int id_desparasitacion PK
        int id_clinica FK
        int id_mascota FK
        int id_veterinario FK
        enum tipo
        date fecha_aplicacion
        date fecha_proxima
    }
    laboratorios_base {
        int id_laboratorio PK
        int id_clinica FK
        varchar nombre_laboratorio
    }
    productos_desparasitacion_base {
        int id_producto PK
        int id_clinica FK
        varchar nombre_producto
    }
    notificaciones {
        int id_notificacion PK
        int id_clinica FK "NULL en avisos de la plataforma"
        int id_usuario FK
        varchar tipo_entidad
        int id_entidad
        enum estado
        datetime fecha_envio
    }
    notificaciones_internas {
        int id PK
        int id_clinica FK
        int id_usuario FK
        int id_rol_destino FK
        int id_cita FK
        varchar tipo
        tinyint leida
        datetime vigente_hasta
    }
    horarios_clinica {
        int id PK
        int id_clinica FK
        int dia_semana
        tinyint activo
    }
    auditoria_mascotas {
        int id_auditoria PK
        int id_clinica FK
        int id_mascota FK
        int id_usuario FK
        varchar campo_modificado
    }
    auditoria_sistema {
        int id_auditoria PK
        int id_clinica FK
        int id_usuario FK
        enum accion
        varchar tabla_afectada
    }

    planes    ||--o{ clinicas                : "clasifica"
    planes    ||--o{ suscripciones           : "se contrata en"
    clinicas  ||--o{ suscripciones           : "tiene"
    clinicas  ||--o{ usuario_clinica         : "emplea"
    usuarios  ||--o{ usuario_clinica         : "trabaja en"
    usuarios  ||--o{ consentimientos_datos    : "acepta la politica"
    usuarios  ||--o{ password_resets         : "recupera su clave"
    usuarios  ||--o{ verificaciones_email    : "verifica su correo"
    clinicas  |o--o{ verificaciones_email    : "confirma vinculos y alta de clinica"
    roles     |o--o{ verificaciones_email    : "rol ofrecido al invitar"
    usuarios  ||--o{ casos_soporte           : "origina"
    clinicas  ||--o{ casos_soporte           : "involucra"
    clinicas  ||--o{ mascota_clinica         : "atiende"
    mascotas  ||--o{ mascota_clinica         : "se vincula a"
    clinicas  ||--o{ vacunas                 : "aplica"
    clinicas  ||--o{ desparasitaciones       : "aplica"
    mascotas  ||--o{ carnet_escaneos         : "registra escaneos"
    clinicas  ||--o{ citas                   : "agenda"
    clinicas  ||--o{ consultas               : "registra"
    clinicas  ||--o{ tipos_cita              : "configura"
    clinicas  ||--o{ horarios_clinica        : "define"
    clinicas  ||--o{ vacunas_base            : "cataloga"
    clinicas  ||--o{ laboratorios_base       : "cataloga"
    clinicas  ||--o{ productos_desparasitacion_base : "cataloga"
    clinicas  ||--o{ notificaciones          : "emite"
    clinicas  ||--o{ notificaciones_internas : "emite"
    clinicas  ||--o{ plantillas_comunicacion : "personaliza"
    clinicas  ||--o{ auditoria_mascotas      : "audita"
    clinicas  ||--o{ auditoria_sistema       : "audita"

    roles     ||--o{ usuario_clinica         : "define el rol en la clinica"
    usuarios  |o--o{ mascotas                : "es propietario de (salvo provisional)"
    mascotas  ||--o{ ingresos_emergencia     : "ficha provisional"
    mascotas  |o--o{ ingresos_emergencia     : "ficha final conciliada"
    clinicas  ||--o{ ingresos_emergencia     : "recibe urgencia"
    usuarios  ||--o| veterinario_perfil      : "tiene perfil"
    usuarios  ||--o{ veterinario_especialidades : "domina"
    especialidades ||--o{ veterinario_especialidades : "clasifica"
    usuarios  ||--o{ resenas_veterinario     : "es calificado en"
    citas     ||--o| resenas_veterinario     : "origina reseña"

    usuarios  ||--o{ propietario_clinica     : "se vincula (propietario)"
    clinicas  ||--o{ propietario_clinica     : "vincula propietarios"
    usuarios  ||--o{ horarios_veterinario    : "tiene horario (vet)"
    clinicas  ||--o{ horarios_veterinario    : "fija el horario"
    usuarios  ||--o{ ausencias_veterinario   : "registra ausencia (vet)"
    clinicas  ||--o{ ausencias_veterinario   : "registra"
    usuarios  ||--o{ ausencias_veterinario   : "cubre como reemplazo"
    usuarios  ||--o{ propuestas_horario      : "propone (vet)"
    clinicas  ||--o{ propuestas_horario      : "recibe"
    usuarios  ||--o{ propuestas_horario      : "aprueba o rechaza (admin)"
    citas     ||--o{ reasignaciones          : "se reasigna"
    usuarios  ||--o{ reasignaciones          : "cede (vet origen)"
    usuarios  ||--o{ reasignaciones          : "confirma (vet destino)"

    especies  |o--o| grafo_nodos : "taxon especie unico"
    razas     |o--o| grafo_nodos : "taxon raza unico"
    grafo_nodos ||--o{ grafo_sinonimos : "tiene sinonimos"
    grafo_nodos ||--o{ grafo_aristas         : "es origen de"
    grafo_nodos ||--o{ grafo_aristas         : "es destino de"
    grafo_nodos ||--o{ alertas_medicas       : "codifica"
    grafo_nodos ||--o{ cita_sintomas         : "identifica"
    grafo_nodos ||--o{ consulta_sintomas     : "identifica"
    grafo_nodos ||--o{ tratamientos          : "codifica el farmaco"

    especies  ||--o{ razas                   : "agrupa"
    especies  ||--o{ mascotas                : "clasifica"
    razas     ||--o{ mascotas                : "clasifica"
    mascotas  ||--o{ mascota_colores         : "tiene"
    colores_base ||--o{ mascota_colores      : "compone"
    mascotas  ||--o{ citas                   : "es agendada en"
    mascotas  ||--o{ alertas_medicas         : "tiene"
    clinicas  ||--o{ alertas_medicas         : "registra"
    usuarios  ||--o{ alertas_medicas         : "registra (vet)"
    citas     ||--o{ cita_sintomas           : "reporta"
    consultas ||--o{ consulta_sintomas       : "registra"
    usuarios  ||--o{ citas                   : "atiende como veterinario"
    mascotas  ||--o{ consultas               : "recibe"
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
    citas     |o--o{ notificaciones_internas : "origina el aviso"
    mascotas  ||--o{ auditoria_mascotas      : "genera"

    tipos_cita ||--o{ citas                  : "define duracion"
    citas      ||..o{ notificaciones         : "recuerda (tipo_entidad, sin FK)"
    vacunas    ||..o{ notificaciones         : "recuerda (tipo_entidad, sin FK)"
    usuarios   ||--o{ auditoria_sistema      : "ejecuta"
    cierres_clinica {
        int id_cierre PK
        int id_clinica FK
        datetime inicio
        datetime fin
        varchar motivo
        tinyint activo
        int id_autor FK
        datetime fecha_registro
    }
    bloques_agenda {
        int id_bloque PK
        int id_clinica FK
        int id_veterinario FK
        date fecha
        char franja_origen
        datetime inicio_original
        datetime fin_original
        enum estado
    }
    solicitudes_agenda {
        int id_solicitud PK
        int id_clinica FK
        int id_mascota FK
        int id_actor FK
        enum origen
        datetime fecha_registro
        datetime fecha_llegada
        int id_receptor FK
        enum estado
        enum estado_revision
        enum nivel_calculado
        enum nivel_aprobado
        json contexto_triage
        char version_conocimiento
        char huella_entrada
        char huella_revision
        int id_revisor FK
        datetime fecha_revision
        varchar motivo_revision
        tinyint contagio
        bigint orden_espera
        int id_cita FK
        int id_ingreso FK
        int version_solicitud
        enum fuente_nivel
    }
    pausas_cita {
        int id_pausa PK
        int id_clinica FK
        int id_cita FK
        int id_solicitud_roja FK
        datetime inicio
        datetime fin
        int id_autor FK
        varchar motivo
        int cita_abierta
    }
    operaciones_agenda {
        int id_operacion PK
        int id_clinica FK
        int id_actor FK
        varchar actor_clave
        varchar accion
        char clave_operacion
        char huella_entrada
        json resultado
        datetime fecha
    }
    eventos_agenda {
        int id_evento PK
        int id_clinica FK
        int id_cita FK
        int id_solicitud FK
        varchar tipo
        char clave_evento
        varchar destino_clave
        int id_destinatario FK
        int id_rol_destino FK
        enum canal
        int version_recurso
        datetime hora_a_comunicar
        enum estado
        char reclamacion
        datetime fecha_reclamacion
        datetime fecha_resultado
        int intentos
        varchar error_tecnico
        datetime fecha
    }
    clinicas ||--o{ cierres_clinica : "id_clinica"
    usuarios ||--o{ cierres_clinica : "id_autor"
    clinicas ||--o{ bloques_agenda : "id_clinica"
    usuarios ||--o{ bloques_agenda : "id_veterinario"
    usuario_clinica ||--o{ bloques_agenda : "id_veterinario,id_clinica"
    clinicas ||--o{ solicitudes_agenda : "id_clinica"
    mascotas ||--o{ solicitudes_agenda : "id_mascota"
    usuarios ||--o{ solicitudes_agenda : "id_actor"
    usuarios |o--o{ solicitudes_agenda : "id_receptor"
    usuarios |o--o{ solicitudes_agenda : "id_revisor"
    citas |o--o| solicitudes_agenda : "id_cita,id_clinica"
    ingresos_emergencia |o--o{ solicitudes_agenda : "id_ingreso,id_clinica"
    clinicas ||--o{ pausas_cita : "id_clinica"
    citas ||--o{ pausas_cita : "id_cita,id_clinica"
    solicitudes_agenda ||--o{ pausas_cita : "id_solicitud_roja,id_clinica"
    usuarios ||--o{ pausas_cita : "id_autor"
    clinicas ||--o{ operaciones_agenda : "id_clinica"
    usuarios |o--o{ operaciones_agenda : "id_actor"
    clinicas ||--o{ eventos_agenda : "id_clinica"
    citas |o--o{ eventos_agenda : "id_cita,id_clinica"
    solicitudes_agenda |o--o{ eventos_agenda : "id_solicitud,id_clinica"
    usuarios |o--o{ eventos_agenda : "id_destinatario"
    roles |o--o{ eventos_agenda : "id_rol_destino"
    bloques_agenda |o--o{ citas : "id_bloque,id_clinica"
    usuarios |o--o{ ausencias_veterinario : "id_solicitante"
    usuarios |o--o{ ausencias_veterinario : "id_revisor"
    clinicas ||--o{ reasignaciones : "id_clinica"
    citas ||--o{ reasignaciones : "id_cita,id_clinica"
    usuarios |o--o{ ingresos_emergencia : "id_conciliador"
    clinicas ||--o{ fuentes_pago : "id_clinica"
    usuarios ||--o{ fuentes_pago : "creada_por"
    clinicas ||--o{ pagos_suscripcion : "id_clinica"
    suscripciones ||--o{ pagos_suscripcion : "id_suscripcion"
    planes ||--o{ pagos_suscripcion : "id_plan"
    usuarios o|--o{ pagos_suscripcion : "id_usuario_solicitante"
    pagos_suscripcion ||--o{ intentos_pago : "id_pago"
    fuentes_pago o|--o{ intentos_pago : "id_fuente"
    intentos_pago o|--o{ wompi_eventos : "id_intento"
    clinicas ||--o{ suscripcion_periodos : "id_clinica"
    suscripciones ||--o{ suscripcion_periodos : "id_suscripcion"
    planes ||--o{ suscripcion_periodos : "id_plan"
    pagos_suscripcion o|--o{ suscripcion_periodos : "id_pago"
    usuarios o|--o{ suscripcion_periodos : "id_usuario_actor"
    clinicas ||--o{ avisos_suscripcion : "id_clinica"
    suscripciones ||--o{ avisos_suscripcion : "id_suscripcion"
    pagos_suscripcion o|--o{ avisos_suscripcion : "id_pago"
    intentos_pago o|--o{ avisos_suscripcion : "id_intento"
    usuarios ||--o{ avisos_suscripcion : "id_usuario_destinatario"
    notificaciones o|--o{ avisos_suscripcion : "id_notificacion"
    fuentes_pago o|--o{ suscripciones : "fuente_activa"
    usuarios o|--o{ suscripciones : "autoriza"
```

---

## Detalle de Entidades Principales

### 1. Plataforma y suscripción (SaaS) — nuevo
*   **clinicas**: Cada inquilino de la plataforma; su `id_clinica` aísla los datos. Incluye nombre, `nit` (único, validado con el dígito de verificación de la DIAN; RN-010), dirección, teléfono, logo y el plan vigente. Su `estado` es `pendiente_verificacion` hasta que confirma el correo (RN-002), y luego `activa`, `suspendida` o `baja` (RN-012). Guarda también los parámetros de la agenda, con valores por defecto: `telefono_urgencias` (RN-421), `tope_sobrecupos` (2, RN-422), `tolerancia_llegada_min` (10, RN-426), `plazo_reasignacion_min` (10, RN-428) y `umbral_aviso_min` (15, RN-429).
*   **planes**: Catálogo **global** de planes (gratuito y profesional) con sus precios (mensual/anual) y límites (`limite_mascotas`, `limite_citas_mes`, `limite_personal`; NULL significa sin límite; RN-003).
*   **suscripciones**: Estado de la suscripción de cada clínica (`activa`/`suspendida`/`cancelada`), ciclo de cobro, indicador `al_dia` y vigencia.
*   **plantillas_comunicacion**: Plantillas de correo/notificación por clínica (asunto, cuerpo, formato) y parámetros de envío (días de anticipación, hora). Es la base de las comunicaciones del Módulo 8 (reputación y comunicaciones); la bitácora de envíos reutiliza `notificaciones`.

### 2. Acceso y usuarios
*   **roles**: Perfiles del sistema: `1 Administrador`, `2 Veterinario`, `4 Propietario`, `5 Super-administrador`. **El rol 3 (Recepcionista) se elimina en v2.0.** Los roles 1 y 2 se asignan por clínica en `usuario_clinica`; el 4 lo da el vínculo en `propietario_clinica` y el 5 la marca `usuarios.es_super_admin`. El catálogo conserva el 4 y el 5 para nombrar el contexto activo de la sesión (RN-G01) y para dirigir avisos internos por rol.
*   **usuarios**: Identidad única de cada persona. Su clave es `id_usuario`, un número que no cambia y no revela datos personales; el **documento** y el **correo** son únicos en toda la plataforma pero son datos corregibles, no la clave. Así el documento puede cambiar (de tarjeta de identidad a cédula, un error de digitación) o anonimizarse (RN-G16) sin tocar las demás tablas, que se relacionan por `id_usuario`. `documento` es NULL mientras una cuenta creada con Google no completa su perfil (`perfil_completo`); `password` es NULL hasta que esa cuenta cree una contraseña; `google_uid` vincula la cuenta de Google. Ya no lleva `id_clinica` ni `id_rol`: los roles se asignan por contexto. `es_super_admin` marca al operador de la plataforma, que no tiene roles de clínica. Al suprimir una cuenta (RN-G16) sus datos personales se reemplazan, `documento`, `email` y `google_uid` quedan en NULL y el `id_usuario` se conserva, por lo que la historia clínica y la auditoría siguen íntegras. `version_sesion` sube cada vez que se crea o cambia la contraseña: cada sesión guarda la versión con la que entró y, si ya no coincide, se cierra (RE-T.2.5).
*   **consentimientos_datos**: Prueba de la autorización de tratamiento de datos (Ley 1581 de 2012 y Decreto 1377 de 2013): quién aceptó, qué versión de la política, por qué medio, cuándo y desde qué IP (RN-G19). `medio`: `formulario`, `google`, `alta_personal` (el personal activa su cuenta) y, desde M5 (migración 06), `activacion_propietario` (el propietario activa por enlace la cuenta que le creó la clínica; si activa con Google, `google`).
*   **usuario_clinica**: Roles de personal de una persona en cada clínica (`id_rol`: 1 administrador o 2 veterinario), con su estado. Inactivar a alguien en una clínica no afecta sus otros roles (RN-G08).
*   **propietario_clinica**: Tabla puente que vincula un propietario (global) con una o varias clínicas. Un propietario se crea una sola vez (correo único) y se liga a cada clínica mediante este registro, previa **verificación del correo** al ligar a una clínica nueva. Esto permite las tres vías de registro (alta por el personal, enlace/QR de la clínica, autoregistro directo eligiendo clínica) sin duplicar la persona ni romper la regla de correo único. `autoriza_historia_compartida` guarda si el propietario permite que esa clínica vea las consultas registradas por otras clínicas (revocable; RN-113).
*   **password_resets** y **verificaciones_email**: Enlaces de un solo uso, guardados como hash y con vencimiento, ligados a `id_usuario` (antes al documento). `verificaciones_email` guarda el correo que se verifica, así que sirve para el registro y para el cambio de correo: el correo nuevo solo reemplaza al anterior cuando se verifica (RN-G23). Para vincular un propietario existente, `proposito = vinculo_clinica` e `id_clinica_vinculo` identifican la solicitud: solo su confirmación crea `propietario_clinica` (RN-109); un enlace de registro no sirve para este fin. Para invitar al personal (RE-T.7.5), `proposito = activacion_personal` es la invitación a una cuenta nueva y `proposito = invitacion_personal` la invitación a una persona que ya tiene cuenta: `id_clinica_vinculo` e `id_rol_vinculo` dicen a qué clínica y con qué rol, y solo la aceptación del titular crea `usuario_clinica`. `nombre_invitado`, `tipo_documento_invitado`, `documento_invitado`, `email_invitado` y `telefono_invitado` guardan lo que escribió el administrador: es lo único que la clínica ve mientras la invitación está pendiente, así que la lista no revela si la cuenta existe; `email` es el correo de la cuenta al que se envió. En D3, `registro_clinica` identifica al administrador nuevo y `vinculo_admin_clinica` al preexistente; el enlace confirma por POST el rol y la activación atómica con catálogos. Si otra solicitud vigente impide eliminar una identidad nueva vencida, `alta_clinica_vencida`, usada y sin clínica, conserva su procedencia hasta que la limpieza pueda eliminarla; nunca es un enlace válido ni identifica una cuenta preexistente.
*   **intentos_login**: Contadores de intentos fallidos, sin clave foránea. La clave del contador (`identificador`, única) lleva prefijo: `ip:<dirección>` para el bloqueo por IP, `cuenta:<id_usuario>` para exigir el CAPTCHA por cuenta (RN-G15) y `chk:<dirección>` para limitar la ayuda de formato de documento y correo (nunca devuelve existencia). `alta-clinica:<AAAA-MM-DD>:<IP>` limita a tres registros diarios de clínica por IP real; el incremento se confirma en la misma transacción del alta (RE-0.1.6). El contador por cuenta usa `id_usuario` y no el documento, porque la persona puede entrar con su documento, su correo o Google y los tres deben sumar al mismo contador.
*   **casos_soporte**: Casos que pasan al super-administrador: un documento que ya pertenece a otra cuenta (RN-G24), una posible cuenta o clínica duplicada y el abuso del plan gratuito (RN-012). Guarda el tipo, la persona y la clínica involucradas, la descripción y el estado (`abierto`, `resuelto`, `descartado`).

### 3. Reputación de veterinarios — nuevo
*   **especialidades**: Catálogo global de especialidades veterinarias.
*   **veterinario_perfil**: Datos públicos del veterinario (biografía, foto).
*   **veterinario_especialidades**: Relación N:M entre veterinarios y especialidades.
*   **resenas_veterinario**: Calificación (`estrellas` 1–5 y comentario opcional) atada a una **cita atendida**; una reseña por cita. Alimenta el promedio del perfil y el emparejamiento del Grafo II. `oculta` marca una reseña ocultada por moderación del super-administrador; la clínica no puede editarla ni borrarla (RN-804).

### 4. Grafo de conocimiento clínico (Grafo I) — nuevo, global
*   **grafo_nodos**: Conceptos clínicos con su `tipo` (`sintoma`, `diagnostico`, `farmaco`, `especie`, `raza`, `condicion`, `sustancia`), con fuentes, validación, código estable, versión y activo.
*   **grafo_aristas**: Relaciones dirigidas entre nodos con `tipo_relacion`, `signo` (`positivo`/`negativo`) y `peso`. Es un **grafo con signo**: lo positivo favorece; un efecto negativo puede advertir o bloquear según su semántica, no solo por su signo. Compartido por todas las clínicas.
*   Los datos de cada paciente se cruzan con el grafo por cuatro puntos: `alertas_medicas.id_nodo`, `cita_sintomas`, `consulta_sintomas` y `tratamientos.id_nodo_farmaco`. Sin esos enlaces el triage y las advertencias de prescripción no tendrían de dónde leer.

> El **Grafo II (agenda)** no guarda el grafo: lo construye en tiempo real a partir de `citas` (estado, nivel de triage, horas reales, sobrecupos), `tipos_cita` (duración, margen, pausable), `horarios_clinica`, `horarios_veterinario`, `ausencias_veterinario` y los parámetros de la clínica. Persiste hechos/estados recuperables necesarios de agenda, no sus aristas: solicitudes/revisión/espera, bloques, pausas, idempotencia y eventos, además de reasignaciones/propuestas. Ver el contrato B1/B1.1 y su materialización de datos B2 al final; sin declarar operativos sus flujos.

### 5. Pacientes y catálogos taxonómicos
*   **mascotas**: Ficha **global** del animal, del propietario y no de una clínica (sin `id_clinica`). FKs a `especies`, `razas` y `usuarios` (propietario). En una urgencia roja sin identificación verificable, `id_propietario`, especie, raza, nombre y peso pueden quedar NULL solo con `ficha_por_completar = 1`; la aplicación exige esos datos al completar la ficha ordinaria (RE-4.15.3). `raza_indicada` guarda la raza escrita cuando no está en la lista, con el mismo largo que `razas.nombre_raza` (50 caracteres) para que pueda pasar al catálogo sin recortarse. `esterilizado` es NULL si no se sabe; el triage lo usa. `token_carnet` es aleatorio (≥ 128 bits) y nunca expone `id_mascota` en la URL (RN-502); el carnet de una ficha provisional permanece inactivo. `id_clinica_registro` guarda la clínica que la registró: junto con el propietario, es la única que puede cambiar especie, raza, sexo y fecha de nacimiento (RN-110). `carnet_activo` apaga el carnet sin borrar el token, cuando la mascota se inactiva o su propietario suprime la cuenta (RN-502, RN-G16).
*   **ingresos_emergencia**: Guarda el ingreso **presencial** provisional de una urgencia roja con `id_clinica`, la mascota provisional y los datos disponibles del acompañante, sin atribuirle la propiedad ni crearle una cuenta. `id_mascota_final` identifica la misma mascota al completar la ficha o una ficha existente después de verificar su titularidad y consolidar los actos clínicos en una transacción; la ficha provisional queda inactiva y el ingreso conserva la trazabilidad. Un rojo iniciado en el portal autenticado usa el propietario de la sesión y la mascota seleccionada. También en un ingreso presencial con relación verificada se usan los registros existentes: ninguno de esos casos crea un ingreso provisional (RE-4.15.3, RN-G19, RN-420).
*   **alertas_medicas**: Alergias, condiciones crónicas y medicación continua de la mascota (`tipo`). Cada alerta guarda la clínica y el veterinario que la registró y, si existe, el nodo del Grafo I al que corresponde (`id_nodo`), para que el triage y la prescripción la crucen con el grafo. Es global para la mascota: la ven todas las clínicas vinculadas y el carnet público (RN-113, RN-416, RN-503). Una alerta no se borra: se desactiva (`activa = 0`).
*   **mascota_clinica**: Tabla puente mascota↔clínica. Se crea al registrar o vincular la mascota en una clínica; `numero_historia_clinica` se asigna al guardar la primera consulta en esa clínica (RN-102) y es único por `id_clinica`. Es la base del límite de mascotas del plan.
*   **carnet_escaneos**: Registro de cada vista válida del carnet público: fecha, IP seudonimizada con HMAC y ubicación solo si quien escanea la comparte. `notificado = 1` indica que se procesó el aviso, también cuando se agrupó con el anterior (30 minutos). Alimenta los últimos diez escaneos visibles solo para el dueño (RN-504).
*   **carnet_accesos**: `id_acceso`, `ip_hash CHAR(64)`, `fecha DATETIME` e índices `(ip_hash, fecha)` y `(fecha)`. Cuenta toda petición a carnet y ubicación antes de resolver el token, incluso las inválidas. Sin FK, mascota ni token; máximo 30 por hora; por encima del límite no se registra la petición, y se eliminan oportunistamente las de más de 24 horas (RN-505).
*   **especies**, **razas**, **colores_base** y **mascota_colores**: Catálogos **globales** de taxonomía y la relación N:M de colores. _Desde M5 (decisión del usuario, 2026-10-13) los formularios ya no piden el color: `colores_base` y `mascota_colores` quedan en la base sin uso, sin migración (RN-107)._

### 6. Operación clínica
*   **citas**: Agenda por clínica (`id_clinica`). El campo `prioridad` guarda el **nivel de triage de 4 niveles** — 🔴 rojo (crítico), 🟠 naranja (urgente), 🟡 amarillo (prioritario), 🟢 verde (no urgente) — **calculado por el Grafo I** a partir de los síntomas; conserva estados, horas reales y control de solapamientos. `prioridad_calculada` conserva el nivel que dio el Grafo I cuando el veterinario lo ajusta (RN-419); `hora_llegada` registra la llegada del propietario (RN-426); los estados incluyen `sin_cerrar` y `pausada` (RN-427). Los síntomas se guardan de forma estructurada (RN-424): los marcados del catálogo del grafo en `cita_sintomas`, el texto libre en `sintomas_texto` y su comienzo en `inicio_sintomas`. `motivo_ajuste_prioridad` es obligatorio cuando el veterinario cambia el nivel (RN-419); `es_sobrecupo` marca las citas que cuentan contra el tope de sobrecupos del bloque y `orden_sobrecupo` conserva su posición, inicialmente por llegada y modificable por veterinario con motivo auditado (RN-422). `id_tipo_cita` pasa a ser clave foránea. `duracion_minutos` y `margen_minutos` guardan los valores aplicados al reservar; cambiar después el tipo no altera las citas existentes. `aviso_atencion_abierta` guarda cuándo se avisó que la atención seguía abierta después de su hora de fin, para avisar una sola vez (RN-409). `ocupa_horario` es una columna calculada que vale 1 mientras la cita ocupa su horario y NULL cuando está libre (`cancelada` o `no_asistio`) o es sobrecupo o tiene `origen=urgencia`; con ella, el índice único `(id_veterinario, fecha, hora, ocupa_horario)` impide en la base dos reservas del mismo veterinario a la misma hora (RN-401). El índice no lleva `id_clinica`, así que tampoco deja reservar dos veces a un veterinario que trabaja en dos clínicas; el tope de sobrecupos (RN-422) se controla en la transacción de reserva.
*   **tipos_cita**: Servicios y su duración base, ahora por clínica. Suma `margen_minutos`: el **colchón (buffer)** por tipo de cita que absorbe los retrasos y alimenta el cálculo de disponibilidad y el reajuste en vivo (Grafo II). `pausable` indica si una cita de ese tipo puede interrumpirse por una urgencia (RN-427).
*   **horarios_veterinario**: Horario **recurrente** de cada veterinario por clínica (`id_clinica`) y día de la semana (franjas `hora_inicio`–`hora_fin`); un veterinario que trabaja en dos clínicas tiene un horario en cada una, y no pueden chocar entre sí. Lo configura el administrador de esa clínica (RN-706). Reemplaza el supuesto de disponibilidad 24/7: la disponibilidad real es `horario de la clínica ∩ horario del veterinario − ausencias`.
*   **ausencias_veterinario**: Excepciones por clínica y rango de fecha y hora (permiso, incapacidad, "ese día no vino") que tapan la disponibilidad del veterinario en ese rango. `fecha_hora_inicio` es inclusiva y `fecha_hora_fin` exclusiva, en la zona horaria de la clínica; un día completo va de las 00:00 de ese día a las 00:00 del siguiente. `id_cobertura` es la clave foránea del veterinario que lo reemplaza; así un "intercambio de turnos" queda registrado como **ausencia del titular + cobertura del reemplazo** (reasignación de sus citas). La trazabilidad de quién atendió realmente la da la historia clínica, no la agenda.
*   **propuestas_horario**: Cambios de horario que propone el veterinario (`franjas`), con estado `pendiente`, `aprobada` o `rechazada` y el administrador que la revisó (`id_revisor`). Al aprobarse, las franjas pasan a `horarios_veterinario` y las citas que queden fuera se marcan para reajuste (RN-706).
*   **reasignaciones**: Cada reasignación en vivo de una cita: veterinario de origen y de destino, plazo para confirmar (`fecha_limite`) y estado (`pendiente`, `aceptada`, `rechazada`, `vencida`). Si vence o se rechaza, se crea la propuesta al siguiente veterinario (RN-428).
*   **consultas**, **tratamientos**, **archivos_clinicos**: Historia clínica; `consultas` lleva `id_clinica`, y `tratamientos`/`archivos_clinicos` heredan la clínica por su consulta. `consulta_sintomas` guarda los síntomas registrados en la consulta, a partir de los cuales el Grafo I sugiere diagnósticos (RN-210). `tratamientos` enlaza el fármaco con su nodo del grafo (`id_nodo_farmaco`) y guarda `fecha_inicio` y `fecha_fin`: la **medicación vigente** es la de los tratamientos aún no terminados más la medicación continua de `alertas_medicas`. El motor evalúa internamente esa medicación de todas las clínicas para RN-211; sin autorización para ver un tratamiento ajeno, la respuesta solo da una alerta genérica y no expone los datos del tratamiento.

### 7. Prevención
*   **vacunas**, **desparasitaciones**: Historial por mascota; cada registro lleva el `id_clinica` y el veterinario que lo aplicó, para mostrar en el carnet y en la historia compartida qué clínica lo hizo (RN-112).
*   **vacunas_base**, **laboratorios_base**, **productos_desparasitacion_base**: Catálogos configurables **por clínica** (`id_clinica`). **especie_vacunas** no lleva `id_clinica`: hereda la clínica de su vacuna base. Al activarse, cada clínica recibe una copia de los valores por defecto de estos catálogos, de `tipos_cita` y de `horarios_clinica` (RE-0.2.5).

### 8. Comunicaciones y auditoría
*   **notificaciones**: Correos a las personas (`id_usuario`, antes `id_propietario`), por clínica; `estado` (`enviado`/`error`); sirve además de bitácora de envíos (Módulo 8). `id_clinica` es NULL en los avisos de la plataforma que no pertenecen a una clínica: escaneo del carnet, cambio de correo o de documento.
*   **notificaciones_internas**: Avisos al personal por usuario o rol, por clínica. `id_cita` liga el aviso a su cita para retirarlo cuando la cita se cancela, se reprograma o se atiende, y `vigente_hasta` marca desde cuándo deja de mostrarse (NULL: no caduca).
*   **horarios_clinica**: Bloques de atención (mañana/tarde) por día y por clínica.
*   **auditoria_mascotas**, **auditoria_sistema**: Trazabilidad de cambios y de seguridad, con `id_clinica`. `auditoria_sistema.id_usuario` pasa a ser clave foránea (NULL cuando el intento no corresponde a una cuenta): como las cuentas nunca se borran, solo se anonimizan, la referencia siempre es válida.

### 9. Infraestructura
*   **schema_migraciones**: Migraciones aplicadas (archivo y fecha); la crea y mantiene `scripts/migrar.php`. En la v2 ya no guarda el modo «línea base»: la base nace con `database/01_schema.sql` y las migraciones empiezan en `03`. Los datos semilla (`02_semilla.sql`) no se anotan aquí, porque el migrador los aplica en cada arranque.

### 10. Índices y restricciones

Además de las claves primarias, foráneas y únicas del diagrama, el esquema ejecutable (`database/01_schema.sql`) declara:

*   **Unicidad:** `consultas.id_cita` (una consulta por cita), `especie_vacunas (id_especie, id_vacuna_base)`, `horarios_clinica (id_clinica, dia_semana)`, `roles.nombre_rol`, `planes.nombre`, `especies.nombre_especie`, `razas (id_especie, nombre_raza)` y `colores_base.nombre_color`. Los de los catálogos globales hacen que la semilla se pueda aplicar varias veces sin duplicar filas.
*   **Doble reserva:** `citas (id_veterinario, fecha, hora, ocupa_horario)`, único y sin `id_clinica` (ver §6).
*   **Aislamiento:** índices compuestos que empiezan por `id_clinica` en las tablas de negocio (`citas`, `consultas`, `vacunas`, `desparasitaciones`, `tipos_cita`, `horarios_veterinario`, `ausencias_veterinario`, `propuestas_horario`, catálogos por clínica, `notificaciones`, `notificaciones_internas`, `auditoria_*`, `usuario_clinica`, `propietario_clinica`, `mascota_clinica`, `suscripciones`, `plantillas_comunicacion` e `ingresos_emergencia`), para que el filtro por clínica (RNF-11) no recorra la tabla completa.
*   Todas las tablas usan InnoDB y `utf8mb4` (`utf8mb4_general_ci`). Ninguna clave foránea borra en cascada salvo `mascota_colores`: las personas, las citas y la historia clínica no se borran (RN-G08, RN-405, RN-206).

### Ampliación del Grafo I — M2 B1

El conocimiento es global (RN-212), con códigos estables, retiro lógico y versión SHA-256 del conjunto cargado.
Nodos, aristas, sinónimos y reglas conservan fuentes JSON y estado de validación; quién y cuándo validó se mantienen en el archivo versionado, sin columnas de persona en la base.
`grafo_reglas` conserva condiciones y resultado declarativos; el evaluador pertenece a C2, no a B1.
El nodo de especie referencia solo `id_especie`; el de raza solo `id_raza`, cuya especie se obtiene de `razas`.
Las FK, la unicidad y `ck_nodo_taxonomia` impiden enlaces inválidos o mezclados en conocimiento nuevo; los conceptos legados inactivos conservan su tipo e ID aunque aún no tengan taxonomía; el cargador comprueba la pareja especie/raza declarada contra el catálogo.
`consulta_sintomas.detalles`, `cita_sintomas.detalles` y `citas.contexto_triage` conservan contexto estructurado, sin interpretar notas ni copiar medicación protegida.
El tipo `sustancia` distingue tóxicos de fármacos; ningún signo negativo implica por sí solo un bloqueo.


## Destino preparatorio M4/B1 — diccionario mínimo y restricciones

El destino amplía seis entidades y reutiliza las existentes; las siete tablas inicialmente sugeridas en A no se adoptan en bloque.
Evaluación, derivación y espera se unifican en solicitudes_agenda, con estado de revisión separado del estado de atención.
Citas mantiene id_veterinario obligatorio; una espera sin profesional aún no es una cita.
Toda nueva entidad se confina por id_clinica y conserva sus datos al inactivar; ninguna FK nueva borra actos clínicos en cascada.
Las FK compuestas de recurso+clínica evitan vincular recursos de clínicas diferentes; ser miembro activo y su rol se revalida en servidor.

### cierres_clinica — esquema preparado B2, flujo pendiente

Cierre puntual no representable por recurrencia ni ausencia personal. RE-4.8.2/3.

| Campo | Tipo de referencia |
|---|---|
| id_cierre | INT NOT NULL PRIMARY KEY AUTO_INCREMENT |
| id_clinica | INT NOT NULL |
| inicio | DATETIME NOT NULL |
| fin | DATETIME NOT NULL |
| motivo | VARCHAR(255) NOT NULL |
| activo | TINYINT(1) NOT NULL DEFAULT 1 |
| id_autor | INT NOT NULL |
| fecha_registro | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

**Índices:** KEY idx_cierre_rango (id_clinica, activo, inicio, fin).
**Relaciones:** id_clinica → clinicas(id_clinica); id_autor → usuarios(id_usuario).
**Restricciones y transición:** fin>inicio; [inicio,fin); baja lógica, sin borrar cierres usados. Transición: vacía, no inventar festivos.

### bloques_agenda — esquema preparado B2, flujo pendiente

Identidad estable de franja original/fecha que no se multiplica al restar ausencias. RE-4.13.7.

| Campo | Tipo de referencia |
|---|---|
| id_bloque | INT NOT NULL PRIMARY KEY AUTO_INCREMENT |
| id_clinica | INT NOT NULL |
| id_veterinario | INT NOT NULL |
| fecha | DATE NOT NULL |
| franja_origen | CHAR(64) NOT NULL |
| inicio_original | DATETIME NOT NULL |
| fin_original | DATETIME NOT NULL |
| estado | ENUM('vigente','revisar','retirado') NOT NULL DEFAULT 'vigente' |

**Índices:** UNIQUE KEY uq_bloque_origen (id_clinica, id_veterinario, fecha, franja_origen); UNIQUE KEY uq_bloque_clinica (id_bloque, id_clinica); KEY idx_bloque_vet_fecha (id_veterinario, fecha).
**Relaciones:** id_clinica → clinicas(id_clinica); id_veterinario → usuarios(id_usuario); id_veterinario,id_clinica → usuario_clinica(id_usuario,id_clinica).
**Restricciones y transición:** Identificador opaco servidor de franja original; no cambia por recortes de ausencia ni se borra/recrea para regalar cupo. Cambios recurrentes preservan bloque ya usado de esa fecha, marcan revisar y no reinician contador. Contar citas naranja salvo cancelada/no_asistio; no persistir contador duplicado ni tope histórico distinto al parámetro vigente. Transición: vacía; sobrecupos legados sin origen demostrable se marcan pendientes.

### solicitudes_agenda — esquema preparado B2, flujo pendiente

Una sola entidad para captura/revisión, derivación remota y espera física sin cita ni veterinario; evita tablas separadas de evaluación y urgencia. RE-2.2.5, RE-2.11.5, RE-4.15.1/2/3.

| Campo | Tipo de referencia |
|---|---|
| id_solicitud | INT NOT NULL PRIMARY KEY AUTO_INCREMENT |
| id_clinica | INT NOT NULL |
| id_mascota | INT NOT NULL |
| id_actor | INT NOT NULL |
| origen | ENUM('portal','llegada','interno') NOT NULL |
| fecha_registro | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |
| fecha_llegada | DATETIME DEFAULT NULL |
| id_receptor | INT DEFAULT NULL |
| estado | ENUM('abierta','derivada','espera_presencial','asignada','en_atencion','finalizada','pendiente_reprogramacion','retirada') NOT NULL DEFAULT 'abierta' |
| estado_revision | ENUM('no_requerida','pendiente','resuelta') NOT NULL |
| nivel_calculado | ENUM('rojo','naranja','amarillo','verde') DEFAULT NULL |
| nivel_aprobado | ENUM('rojo','naranja','amarillo','verde') DEFAULT NULL |
| contexto_triage | JSON NOT NULL |
| version_conocimiento | CHAR(64) DEFAULT NULL |
| huella_entrada | CHAR(64) NOT NULL |
| huella_revision | CHAR(64) DEFAULT NULL |
| id_revisor | INT DEFAULT NULL |
| fecha_revision | DATETIME DEFAULT NULL |
| motivo_revision | VARCHAR(255) DEFAULT NULL |
| contagio | TINYINT(1) NOT NULL DEFAULT 0 |
| orden_espera | BIGINT DEFAULT NULL |
| id_cita | INT DEFAULT NULL |
| id_ingreso | INT DEFAULT NULL |
| version_solicitud | INT NOT NULL DEFAULT 0 |
| fuente_nivel | ENUM('grafo','clinico_presencial') DEFAULT NULL |

**Índices:** UNIQUE KEY uq_solicitud_clinica (id_solicitud, id_clinica); UNIQUE KEY uq_solicitud_cita (id_cita); KEY idx_solicitud_espera (id_clinica, estado, fecha_llegada, id_solicitud); KEY idx_solicitud_revision (id_clinica, estado_revision, fecha_registro); KEY idx_solicitud_mascota (id_mascota, estado).
**Relaciones:** id_clinica → clinicas(id_clinica); id_mascota → mascotas(id_mascota); id_actor → usuarios(id_usuario); id_receptor → usuarios(id_usuario); id_revisor → usuarios(id_usuario); id_cita,id_clinica → citas(id_cita,id_clinica); id_ingreso,id_clinica → ingresos_emergencia(id_ingreso,id_clinica).
**Restricciones y transición:** Remoto no lleva llegada/receptor ni activa atención; presencia se acredita por personal autorizado. Revisión resuelta requiere revisor/fecha/huella vigente; ajuste requiere motivo. Sin asignación id_cita=NULL y no hay vet ficticio; al asignar se crea cita en la misma transacción. Ficha provisional solo para rojo presencial sin identidad. JSON guarda episodios/tiempos y contexto permitido, nunca medicación protegida ni contacto del acompañante. orden_espera es orden explícito motivado para excepciones clínicas rojas, no puntaje; la espera no urgente se deriva de nivel aprobado (amarillo antes de verde), fecha_llegada e id_solicitud ascendentes, sin sobrecupo. Transición: vacía, sin inventar evaluaciones/presencia. Rojo presencial clínicamente declarado conserva fuente clinico_presencial/nivel_aprobado rojo/revisor y motivo, sin fingir cálculo ni versión de grafo; un registro aún sin triage no es reservable. Flujo ordinario exige cálculo del motor, huella y versión válidos.

### pausas_cita — esquema preparado B2, flujo pendiente

Reconstruir múltiples interrupciones y tiempo activo de política M. RE-4.15.8, RE-4.5.1/2/3.

| Campo | Tipo de referencia |
|---|---|
| id_pausa | INT NOT NULL PRIMARY KEY AUTO_INCREMENT |
| id_clinica | INT NOT NULL |
| id_cita | INT NOT NULL |
| id_solicitud_roja | INT NOT NULL |
| inicio | DATETIME NOT NULL |
| fin | DATETIME DEFAULT NULL |
| id_autor | INT NOT NULL |
| motivo | VARCHAR(255) NOT NULL |
| cita_abierta | INT DEFAULT NULL |

**Índices:** UNIQUE KEY uq_pausa_abierta (cita_abierta); KEY idx_pausa_cita (id_clinica, id_cita, inicio).
**Relaciones:** id_clinica → clinicas(id_clinica); id_cita,id_clinica → citas(id_cita,id_clinica); id_solicitud_roja,id_clinica → solicitudes_agenda(id_solicitud,id_clinica); id_autor → usuarios(id_usuario).
**Restricciones y transición:** cita_abierta es calculada en B2: id_cita solo si fin IS NULL; drawDB la representa convencional por compatibilidad. fin>inicio, sin solapes, una pausa abierta, tipo pausable y rojo presente en esa clínica; cerrar antes de completar. R/N/L se derivan, sin saldo mutable ni margen por pausa. Transición: vacía; una pausada legada sin segmentos queda para revisión, sin fabricar horas.

### operaciones_agenda — esquema preparado B2, flujo pendiente

Idempotencia atómica de comandos; no puede sustituirse por avisos con otra vida útil. RE-4.9.3, RE-4.15.3.

| Campo | Tipo de referencia |
|---|---|
| id_operacion | INT NOT NULL PRIMARY KEY AUTO_INCREMENT |
| id_clinica | INT NOT NULL |
| id_actor | INT DEFAULT NULL |
| actor_clave | VARCHAR(80) NOT NULL |
| accion | VARCHAR(80) NOT NULL |
| clave_operacion | CHAR(64) NOT NULL |
| huella_entrada | CHAR(64) NOT NULL |
| resultado | JSON NOT NULL |
| fecha | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

**Índices:** UNIQUE KEY uq_operacion_clave (id_clinica, actor_clave, accion, clave_operacion).
**Relaciones:** id_clinica → clinicas(id_clinica); id_actor → usuarios(id_usuario).
**Restricciones y transición:** actor_clave se deriva en servidor (usuario o tarea), nunca del cliente; resultado solo referencias internas/código, no datos personales. Cita/solicitud/ingreso del resultado se validan en esa clínica; relación lógica por resultado, sin FK polimórfica falsa. Misma clave/huella devuelve mismo resultado; otra huella conflicto. Comando fallido revierte su fila. Transición: vacía, claves no derivadas de CSRF.

### eventos_agenda — esquema preparado B2, flujo pendiente

Evento durable tras commit, separación de SMTP e idempotencia de avisos, no nueva bitácora general M8. RE-4.4.1/3/4, RE-4.14.7, RE-4.12.5, RE-2.11.6.

| Campo | Tipo de referencia |
|---|---|
| id_evento | INT NOT NULL PRIMARY KEY AUTO_INCREMENT |
| id_clinica | INT NOT NULL |
| id_cita | INT DEFAULT NULL |
| id_solicitud | INT DEFAULT NULL |
| tipo | VARCHAR(80) NOT NULL |
| clave_evento | CHAR(64) NOT NULL |
| destino_clave | VARCHAR(80) NOT NULL |
| id_destinatario | INT DEFAULT NULL |
| id_rol_destino | INT DEFAULT NULL |
| canal | ENUM('interno','correo') NOT NULL |
| version_recurso | INT NOT NULL |
| hora_a_comunicar | DATETIME DEFAULT NULL |
| estado | ENUM('pendiente','reclamado','enviado','error','incierto','omitido') NOT NULL DEFAULT 'pendiente' |
| reclamacion | CHAR(64) DEFAULT NULL |
| fecha_reclamacion | DATETIME DEFAULT NULL |
| fecha_resultado | DATETIME DEFAULT NULL |
| intentos | INT NOT NULL DEFAULT 0 |
| error_tecnico | VARCHAR(255) DEFAULT NULL |
| fecha | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |

**Índices:** UNIQUE KEY uq_evento_destino (id_clinica, clave_evento, destino_clave, canal); KEY idx_evento_pendiente (estado, fecha, id_evento); KEY idx_evento_cita (id_clinica, id_cita, tipo).
**Relaciones:** id_clinica → clinicas(id_clinica); id_cita,id_clinica → citas(id_cita,id_clinica); id_solicitud,id_clinica → solicitudes_agenda(id_solicitud,id_clinica); id_destinatario → usuarios(id_usuario); id_rol_destino → roles(id_rol).
**Restricciones y transición:** Una referencia al menos; destinatario usuario o rol legítimo, destino_clave generada en servidor. No guardar HTML/contacto provisional; reconstruir marca y contacto permitido al despachar. Clave semántica y versión evitan evento doble. Reclamar fuera de SMTP; incierto no reenvía automáticamente hasta resolución operativa. notificaciones registra resultado de correo, notificaciones_internas sigue entregando internos. Transición: vacía, no fabricar envíos previos.

### Ampliaciones de entidades existentes — destino pendiente de B2

**citas — RE-2.2.1/4, RE-4.5.1–3, RE-4.13.7, RE-4.14.7.**

| Campo propuesto | Tipo |
|---|---|
| origen | ENUM('reserva','llegada_sin_reserva','urgencia','legado') NOT NULL DEFAULT 'legado' |
| id_bloque | INT DEFAULT NULL |
| inicio_estimado | DATETIME DEFAULT NULL |
| fin_estimado | DATETIME DEFAULT NULL |
| duracion_activa_objetivo_segundos | INT DEFAULT NULL |
| politica_temporal | VARCHAR(16) DEFAULT NULL |
| estado_reajuste | ENUM('ninguno','pendiente_calculo','pendiente_revision','pendiente_reasignacion','pendiente_reprogramacion') NOT NULL DEFAULT 'ninguno' |
| motivo_reajuste | VARCHAR(255) DEFAULT NULL |
| version_agenda | INT NOT NULL DEFAULT 0 |
| fecha_ultimo_calculo | DATETIME DEFAULT NULL |
| ultima_hora_comunicada | DATETIME DEFAULT NULL |
| ultimo_aviso_estimacion | DATETIME DEFAULT NULL |

Índices: UNIQUE KEY uq_cita_clinica (id_cita, id_clinica); KEY idx_cita_bloque (id_bloque, es_sobrecupo, estado, fecha_registro, id_cita); KEY idx_cita_reajuste (id_clinica, estado_reajuste, fecha); KEY idx_cita_vet_estimada (id_veterinario, inicio_estimado); KEY idx_cita_mascota_estimada (id_mascota, inicio_estimado).
Relaciones añadidas: id_bloque,id_clinica → bloques_agenda(id_bloque,id_clinica).
Conservar fecha/hora/hora_fin y sellos. D>0 si conocido; F clínico no incluye margen, N/L derivados con M identificada. Sin nuevas prioridades ni estados clínicos. Bloque obligatorio para naranja nuevo y de ese vet/fecha; no mezclar demanda extra con simultaneidad. Valores nuevos por defecto no acreditan historia. Legado sin dato verificable conserva NULL/legado y futura conflictiva queda pendiente; no copiar valores actuales de tipo como antiguos.

**ausencias_veterinario — RE-4.12.1/2/3.**

| Campo propuesto | Tipo |
|---|---|
| estado | ENUM('solicitada','aprobada','rechazada','anulada') NOT NULL DEFAULT 'aprobada' |
| id_solicitante | INT DEFAULT NULL |
| id_revisor | INT DEFAULT NULL |
| fecha_revision | DATETIME DEFAULT NULL |
| motivo_resolucion | VARCHAR(255) DEFAULT NULL |

Índices: KEY idx_ausencia_revision (id_clinica, estado, fecha_hora_inicio); KEY idx_ausencia_vet (id_veterinario, estado, fecha_hora_inicio, fecha_hora_fin).
Relaciones añadidas: id_solicitante → usuarios(id_usuario); id_revisor → usuarios(id_usuario).
Solo aprobada bloquea. Nuevas solicitudes envían solicitada explícita; admin registra aprobada con autor/revisor. Legacy ya era bloqueo efectivo: aprobada sin inventar autores. Rango exacto parcial/completo, sin eliminar registros.

**reasignaciones — RE-4.14.4/6.**

| Campo propuesto | Tipo |
|---|---|
| id_clinica | INT NOT NULL |
| inicio_propuesto | DATETIME DEFAULT NULL |
| fin_propuesto | DATETIME DEFAULT NULL |
| version_cita | INT DEFAULT NULL |
| ciclo | INT NOT NULL DEFAULT 0 |
| motivo | VARCHAR(255) DEFAULT NULL |
| cita_pendiente | INT DEFAULT NULL |

Índices: KEY idx_reasig_clinica_limite (id_clinica, estado, fecha_limite); KEY idx_reasig_intentos (id_cita, ciclo, id_veterinario_destino); UNIQUE KEY uq_reasig_pendiente (cita_pendiente).
Relaciones añadidas: id_clinica → clinicas(id_clinica); id_cita,id_clinica → citas(id_cita,id_clinica).
cita_pendiente calculada en B2=id_cita si estado pendiente, NULL en resto; drawDB convencional comentada. id_clinica demostrable desde cita; nuevas pendientes requieren intervalo y versión, no apartan capacidad. Destino miembro activo; aceptar antes del límite y releer todo. Legacy sin intervalo/versión no puede aceptarse ciegamente; se marca cita para revisión. Duplicados pendientes preexistentes hacen detener actualización con diagnóstico, no borrado automático.

**ingresos_emergencia — RE-4.15.3/9/10.**

| Campo propuesto | Tipo |
|---|---|
| id_conciliador | INT DEFAULT NULL |
| fecha_conciliacion | DATETIME DEFAULT NULL |
| evidencia_conciliacion | JSON DEFAULT NULL |
| necesidad_contacto_resuelta_en | DATETIME DEFAULT NULL |
| motivo_conservacion_contacto | VARCHAR(255) DEFAULT NULL |
| contacto_anonimizado_en | DATETIME DEFAULT NULL |

Índices: UNIQUE KEY uq_ingreso_clinica (id_ingreso, id_clinica).
Relaciones añadidas: id_conciliador → usuarios(id_usuario).
Solo admin/vet autorizado verificando titularidad; evidencia referencias a sesión/vínculos legítimos y mapa de IDs/HC, sin nuevo reto, documento obligatorio ni datos de contacto. Campos contacto existentes opcionales; documento_acompanante queda legado, no captura por defecto. Al cerrar atención y resolver identidad verificar necesidad: contacto a NULL salvo conservación necesaria motivada; registrar fecha/actor por auditoría sin guardar valores, limpiar copias operativas y no borrar ingreso/actos. Legacy sin evidencia no se declara conciliado ni limpiado retrospectivamente.

### Reconstrucción clínica, contacto y datos legados

Política temporal M: duración pactada/sellos reales permanecen; duración activa objetivo y pausas reales permiten reconstruir nominal y extremo con un solo margen. fin_estimado es clínico, no incluye margen. Datos desconocidos de alpha.4 permanecen desconocidos; no se fabrican pausas, evaluaciones, autores, llegada ni horario recurrente.
La ficha provisional se concilia solo desde su clínica, verificando titularidad con las identidades/vínculos vigentes. Se trasladan citas, consultas, alertas, vacunas y desparasitaciones cuando corresponda; tratamientos/archivos/síntomas siguen consulta o cita. Auditoría y escaneos históricos mantienen referencias de origen, con el mapa provisional→final en ingreso. La ficha provisional queda inactiva y su carnet no se reactiva.
La relación mascota_clinica se une conservando los dos números HC como procedencia: si final ya tiene HC, se conserva la antigua fila provisional inactiva como alias con mapa al final; si final carece de HC y la provisional tiene uno, se transfiere bajo candado clínico y la evidencia registra el origen, sin duplicar el índice único. No se inventa un HC por conciliar; se asigna en primera consulta según RN-102. Una provisional con referencias clínicas de otra clínica exige detener/derivar la conciliación, sin apropiarse de actos ajenos.
Atención cerrada y titularidad resuelta disparan comprobación de fin de necesidad del contacto; sin necesidad o excepción justificada, nombre/teléfono y documento legado de acompañante se ponen a NULL conservando ingreso y actos. Las copias operativas/temporales/avisos no retienen esos valores; los respaldos siguen su política vigente y no se promete borrado retroactivo de copias inmutables, que permanecen restringidas y se purgan según esa política. No se introduce plazo de retención nuevo.

### B1.1 — Conciliación de identidad y referencias operativas (2026-10-18)

Precisión documental de RE-4.15.3/RN-110/206/207, sin ampliación de tablas, campos, relaciones o índices.
Al completar P se conserva la misma ficha, se regulariza ficha_por_completar=0 con datos/titularidad legítimos y el ingreso guarda provisional=P/final=P, estado completado, sin activar carnet automáticamente.
Al consolidar P sobre F, ingreso conserva provisional=P/final=F, estado consolidado; P queda inactiva/carnet inactivo.
En ambos casos se conservan id_ingreso/id_solicitud/id_cita y los IDs de actos.
No se sobrescriben atributos/titularidad/alertas vigentes de F con datos parciales de P; todos los ingresos afectados deben conservar un mapa compatible, o se aborta por incoherencia.
**solicitudes_agenda.id_mascota significa identidad vigente para todas las solicitudes, incluidas las terminales.**
Se trasladan a F todas las S de P de la clínica, con o sin C, y sus citas/actos en una transacción; si una S enlaza C, ambas deben terminar con el mismo paciente vigente del ingreso.
Se comprueban además las S que enlazan los recursos trasladados; una contradicción con otro paciente exige rollback, no reasignación por inferencia.
Otras solicitudes de F ajenas a P permanecen intactas.
El mapa de ingreso sirve para procedencia/detectar pedidos obsoletos, nunca para otorgar acceso a F ni a actos ajenos.

Cada conciliación efectiva incrementa version_solicitud y version_agenda de S/C afectados una sola vez, incluso al completar P→P; un reintento no incrementa nada.
Activas son abierta/derivada/espera_presencial/asignada/en_atencion/pendiente_reprogramacion: conservan estado operativo, pero estado_revision pasa a pendiente y huella_revision a NULL.
Finalizada/retirada conservan estado y revisión histórica, sin reabrirse ni habilitar nuevas órdenes.
contexto_triage añade metadatos dentro de su JSON existente: id_mascota_evaluada y version_solicitud_evaluada, identificando el episodio de huella_entrada.
La conciliación conserva contexto, huella de entrada, versión de conocimiento, niveles, contagio, fuente y autor/fecha/motivo clínicos originales; en una S activa los datos del revisor siguen siendo históricos, sin vigencia mientras la huella de revisión es NULL.
En la terminal la revisión se conserva íntegra, ligada al episodio antiguo, no al paciente nuevo.
La procedencia demostrable previa y el mapa de IDs/versiones se registran en evidencia_conciliacion y auditoria_sistema; no se inventan atributos ni se copia contacto provisional/medicación protegida.
Si posteriormente el veterinario evalúa explícitamente el episodio operativo actual, el snapshot sustituido permanece en auditoría.
No se recalculan decisiones pasadas ni se sustituye especie/raza/alertas del snapshot por los datos de F al leerlo.

Los consumidores resuelven IDs estables por clínica y exigen coherencia S/C/ingreso; muestran identidad actual separada de identidad evaluada.
Para un comando nuevo se releen identidad/titular/vínculos, datos clínicos permitidos, marcas/tiempos, conocimiento y versiones; huella vigente liga todo ese contexto.
Un token de P/contexto anterior falla incluso con mismo actor/color; comparar atributos actuales también detecta cambios que no aumentaron la versión S.
Solo vet resuelve la revisión necesaria; admin marca invalidez mecánica al regularizar identidad, sin permiso de triage/consulta.
Cita operativa queda pendiente_revision en estado_reajuste, conservando estado clínico/horas/asignación; no se cancela atención roja en curso.
Propuesta pendiente anterior conserva su versión y límite: aceptación obsoleta falla; rechazo del destino o vencimiento original la resuelven antes de otra, sin nueva espera configurable.
Cita completada conserva sus decisiones, contexto y sellos; no se reabre.
No se habilita HU-4.17.

Eventos conservan ID/clave/refs/destino/canal; version_recurso refiere a C si existe y, si no, S.
clave_evento conserva la causa/versión causal original; actualizar version_recurso por identidad no crea otra causa ni otro evento y el reintento reconoce la clave original.
Pendiente/error solo se actualiza a la versión vigente tras revalidar misma causa, coherencia, destinatario original legítimo y contacto actual; el contenido se reconstruye desde identidad vigente sin afirmar una revisión vencida.
Si depende de revisión pendiente, espera su resolución; si causa/destino ya no valen, omitido con motivo saneado, sin redirigir a otro titular.
Enviado/incierto conservan historia y no se reenvían por conciliar.
El worker reclama con candados comunes y relectura vigente, commit antes de SMTP.
Conciliación con evento afectado reclamado/incierto sin resolver devuelve conflicto sin escrituras; resolución operativa exige que el worker anterior ya no pueda continuar antes de liberar la barrera.
Así no se confirma P→F mientras sigue autorizado un envío de la identidad anterior; no se mantiene un candado SQL durante SMTP ni se bloquea atención roja.
Las notificaciones previas conservan procedencia y sus enlaces revalidan acceso.

operaciones_agenda conserva huella/carga original y resultado como referencias, no datos renderizados.
Reintento exacto ya confirmado no escribe: reconstruye acuse con identidad/versión y acceso actuales; sin permiso, deniega sin datos.
Carga distinta con misma clave da conflicto; clave nueva requiere autorización actual, sin redirigir silenciosamente una orden vieja sobre P.
Dos conciliadores serializan en clínica y releen ingreso: destino ya confirmado igual produce acuse sin otro traslado; diferente, conflicto.

Orden común: clínica→usuarios→mascotas→vínculos→configuración/horarios/ausencias/cierres→bloques→citas→solicitudes→reasignaciones→ingresos→pausas→consultas/alertas/vacunas/desparasitaciones y sus hijos→operaciones→eventos; claves ascendentes dentro de cada tabla.
Incluir todas las S activas/terminales, C, I, propuestas, pausas, eventos y recursos de identidad de origen/destino; si falta recurso, rollback/reinicio ordenado.
Fallo después de trasladar C y antes de S/I revierte también mapa/HC, actos, versiones, revisiones, eventos, auditoría nueva y operación: nunca queda confirmado S→P/C→F.
Se mantiene la conservación/limpieza de contacto ya definida, sin destinatarios, plazo ni facultades administrativas nuevas.

### Materialización B2 — restricciones de datos (2026-10-18)

01_schema.sql y 08_agenda_grafo_ii.sql contienen los mismos campos, índices y FK del destino B1/B1.1; no se agregan entidades ni campos.
pausas_cita.cita_abierta es INT STORED calculado: id_cita si fin IS NULL, NULL en el resto.
reasignaciones.cita_pendiente es INT STORED calculado: id_cita si estado=pendiente, NULL en el resto.
Ambas tienen unicidad con varios NULL permitidos; drawDB mantiene su representación convencional por compatibilidad.
Las FK compuestas comprueban clínica de cita/solicitud/ingreso/bloque; el vínculo del bloque con usuario_clinica existe, pero actividad/rol y coincidencia exacta de vet/fecha requieren el escritor transaccional futuro.

| CHECK instalado | Predicado local |
|---|---|
| cierres_clinica.ck_m4_cierre_intervalo | fin > inicio |
| bloques_agenda.ck_m4_bloque_intervalo | fin_original > inicio_original |
| pausas_cita.ck_m4_pausa_intervalo | fin IS NULL OR fin > inicio |
| citas.ck_m4_cita_objetivo | duracion_activa_objetivo_segundos IS NULL OR duracion_activa_objetivo_segundos > 0 |
| ausencias_veterinario.ck_m4_ausencia_intervalo | fecha_hora_fin > fecha_hora_inicio |
| reasignaciones.ck_m4_reasig_intervalo | (inicio_propuesto IS NULL AND fin_propuesto IS NULL) OR (inicio_propuesto IS NOT NULL AND fin_propuesto IS NOT NULL AND fin_propuesto > inicio_propuesto) |
| solicitudes_agenda.ck_m4_solicitud_presencia | (fecha_llegada IS NULL AND id_receptor IS NULL) OR (fecha_llegada IS NOT NULL AND id_receptor IS NOT NULL) |
| solicitudes_agenda.ck_m4_solicitud_espera | estado NOT IN ('espera_presencial','en_atencion') OR (fecha_llegada IS NOT NULL AND id_receptor IS NOT NULL) |
| solicitudes_agenda.ck_m4_solicitud_revision | estado_revision <> 'resuelta' OR (id_revisor IS NOT NULL AND fecha_revision IS NOT NULL AND huella_revision IS NOT NULL) |
| solicitudes_agenda.ck_m4_solicitud_grafo | fuente_nivel IS NULL OR fuente_nivel <> 'grafo' OR (nivel_calculado IS NOT NULL AND version_conocimiento IS NOT NULL) |
| solicitudes_agenda.ck_m4_solicitud_clinica | fuente_nivel IS NULL OR fuente_nivel <> 'clinico_presencial' OR (nivel_aprobado IS NOT NULL AND nivel_aprobado = 'rojo' AND id_revisor IS NOT NULL AND motivo_revision IS NOT NULL) |
| eventos_agenda.ck_m4_evento_recurso | id_cita IS NOT NULL OR id_solicitud IS NOT NULL |
| eventos_agenda.ck_m4_evento_destino | (id_destinatario IS NULL AND id_rol_destino IS NOT NULL) OR (id_destinatario IS NOT NULL AND id_rol_destino IS NULL) |

La revisión pendiente puede conservar autor/fecha del episodio anterior conforme B1.1; no se exige que metadatos históricos de JSON coincidan con la identidad/versión actual.
Presencia acreditada y revisión completa se comprueban localmente por campos, no prueban titularidad, huellas verdaderas ni decisiones clínicas legítimas.
No se crean triggers para solapes, tope naranja, orden, igualdad de paciente S/C, grafo, conciliación ni barrera SMTP; requieren servicios/candados de C.

**Transición:** ausencia previamente efectiva queda aprobada, autores desconocidos NULL; reasignación recibe clínica desde su cita, nunca una clínica elegida.
D se rellena solo para snapshot propio positivo con fin-inicio pactados coincidentes, sin inicio/fin real ni llegada y pendiente/confirmada; no se lee el tipo vigente.
Completadas, pausadas o datos no demostrables conservan D NULL, sin fabricar pausas, presencia, origen, conocimiento o revisiones.
Futuras activas con datos faltantes quedan pendiente_revision; política temporal previa desconocida permanece NULL.
La columna origen se agrega al cerrar el relleno: su presencia distingue una pasada ya rellenada para no retocar registros/personalizaciones posteriores; un corte anterior se reanuda con predicados de NULL/estado.
No se declara rollback global de DDL: se inspecciona definición antes de cada paso, y conflictos producen M4-08 con objeto/ID; no hay borrado o elección de duplicado.
No se recalculan ni consolidan datos clínicos durante 08; los contratos funcionales B1.1 siguen pendientes de C.


## Suscripción y cobro — M0-S S1 (65 tablas)

La suscripción expresa contrato y acceso efectivo. Una orden representa una obligación con varios intentos y concede un solo periodo inmutable. Las estructuras de pago, evento y fuente quedan vacías hasta S2/S3. No se guarda PAN, CVC ni token del medio; solo id externo, tipo, marca/últimos cuatro y metadatos de vínculo/consentimiento. Outbox por clínica, vencimiento, tipo y destinatario. Dinero BIGINT en centavos COP, impuesto cero; fechas America/Bogota con inicio incluido y fin excluido. Claves/referencias externas ASCII con comparación binaria; FKs RESTRICT. El trabajador y plataforma son excepciones expresas para metadatos RN-G13/019; sin datos clínicos.

**Atributos nuevos existentes:**

- clinicas: `suscripcion_gracia_dias` INT NOT NULL DEFAULT 3, `aviso_vencimiento_1_dias` INT NOT NULL DEFAULT 7, `aviso_vencimiento_2_dias` INT NOT NULL DEFAULT 1.
- suscripciones: `gestion_cobro` ENUM('legado','gestionada') NOT NULL DEFAULT 'legado', `periodo_inicio` DATETIME DEFAULT NULL, `periodo_fin` DATETIME DEFAULT NULL, `estado_cobro` ENUM('sin_vigencia','al_dia','gracia','congelada') NOT NULL DEFAULT 'sin_vigencia', `version_periodo` INT NOT NULL DEFAULT 0, `cobro_automatico` TINYINT NOT NULL DEFAULT 0, `id_fuente_activa` BIGINT DEFAULT NULL, `autorizado_por` INT DEFAULT NULL, `autorizado_en` DATETIME DEFAULT NULL, `retirado_en` DATETIME DEFAULT NULL, `cambio_ciclo_siguiente` ENUM('mensual','anual') DEFAULT NULL.

Hora 07:00, reintentos +24/+48 h (tres totales), cotización 15 minutos: constantes de PoliticaSuscripcion, RN-014/016/017. El legado sin fecha conserva condiciones, no inventa pago ni vencimiento. Al final se enlazan suscripciones.id_fuente_activa con fuentes_pago.id_fuente y autorizado_por con usuarios.id_usuario.

```sql
CREATE TABLE IF NOT EXISTS fuentes_pago (
 id_fuente BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 id_clinica INT NOT NULL,
 entorno ENUM('test','prod') NOT NULL,
 id_fuente_wompi VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tipo ENUM('CARD','NEQUI') NOT NULL,
 marca VARCHAR(30) DEFAULT NULL,
 ultimos_cuatro CHAR(4) DEFAULT NULL,
 estado ENUM('pendiente','disponible','retirada','error') NOT NULL DEFAULT 'pendiente',
 creada_por INT NOT NULL,
 creada_en DATETIME NOT NULL,
 retirada_en DATETIME DEFAULT NULL,
 consentida_en DATETIME DEFAULT NULL,
 version_contratos VARCHAR(128) DEFAULT NULL,
 contratos_json JSON DEFAULT NULL,
 version_autorizacion VARCHAR(128) DEFAULT NULL,
 importe_mensual_autorizado_centavos BIGINT DEFAULT NULL,
 UNIQUE KEY uq_fuente_externa (entorno,id_fuente_wompi),
 KEY idx_fuente_clinica (id_clinica,estado),
 CONSTRAINT fk_fuente_clinica FOREIGN KEY (id_clinica) REFERENCES clinicas (id_clinica),
 CONSTRAINT fk_fuente_actor FOREIGN KEY (creada_por) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pagos_suscripcion (
 id_pago BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 id_clinica INT NOT NULL,
 id_suscripcion INT NOT NULL,
 id_plan INT NOT NULL,
 clave_obligacion CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 version_periodo_base INT NOT NULL,
 ciclo ENUM('mensual','anual') NOT NULL,
 moneda CHAR(3) NOT NULL DEFAULT 'COP',
 subtotal_centavos BIGINT NOT NULL,
 impuesto_centavos BIGINT NOT NULL DEFAULT 0,
 total_centavos BIGINT NOT NULL,
 tarifa_version VARCHAR(50) NOT NULL,
 clave_operacion CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 cotizacion_hasta DATETIME NOT NULL,
 estado ENUM('pendiente','confirmado','cancelado','revision') NOT NULL DEFAULT 'pendiente',
 proximo_intento DATETIME DEFAULT NULL,
 id_usuario_solicitante INT DEFAULT NULL,
 creado_en DATETIME NOT NULL,
 confirmado_en DATETIME DEFAULT NULL,
 UNIQUE KEY uq_pago_obligacion (clave_obligacion),
 UNIQUE KEY uq_pago_operacion (id_clinica,clave_operacion),
 KEY idx_pago_clinica (id_clinica,creado_en),
 KEY idx_pago_proximo (estado,proximo_intento),
 CONSTRAINT fk_pago_clinica FOREIGN KEY (id_clinica) REFERENCES clinicas (id_clinica),
 CONSTRAINT fk_pago_suscripcion FOREIGN KEY (id_suscripcion) REFERENCES suscripciones (id_suscripcion),
 CONSTRAINT fk_pago_plan FOREIGN KEY (id_plan) REFERENCES planes (id_plan),
 CONSTRAINT fk_pago_actor FOREIGN KEY (id_usuario_solicitante) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS intentos_pago (
 id_intento BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 id_pago BIGINT NOT NULL,
 numero INT NOT NULL,
 entorno ENUM('test','prod') NOT NULL,
 referencia VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 id_transaccion_wompi VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 id_fuente BIGINT DEFAULT NULL,
 origen ENUM('boton','automatico') NOT NULL,
 estado ENUM('creado','enviando','pendiente','aprobado','declinado','error','anulado','incierto') NOT NULL DEFAULT 'creado',
 codigo_error VARCHAR(80) DEFAULT NULL,
 creado_en DATETIME NOT NULL,
 enviado_en DATETIME DEFAULT NULL,
 consultado_en DATETIME DEFAULT NULL,
 lease_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 lease_hasta DATETIME DEFAULT NULL,
 UNIQUE KEY uq_intento_numero (id_pago,numero),
 UNIQUE KEY uq_intento_referencia (entorno,referencia),
 UNIQUE KEY uq_intento_transaccion (entorno,id_transaccion_wompi),
 KEY idx_intento_estado (estado,lease_hasta),
 CONSTRAINT fk_intento_pago FOREIGN KEY (id_pago) REFERENCES pagos_suscripcion (id_pago),
 CONSTRAINT fk_intento_fuente FOREIGN KEY (id_fuente) REFERENCES fuentes_pago (id_fuente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS wompi_eventos (
 id_evento BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 clave_evento CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 id_evento_proveedor VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 entorno ENUM('test','prod') NOT NULL,
 tipo VARCHAR(80) NOT NULL,
 id_transaccion_wompi VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 timestamp_proveedor BIGINT NOT NULL,
 checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 huella_datos_firmados CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 datos_minimos JSON DEFAULT NULL,
 id_intento BIGINT DEFAULT NULL,
 estado ENUM('pendiente','procesando','procesado','ignorado','error','revision') NOT NULL DEFAULT 'pendiente',
 recibido_en DATETIME NOT NULL,
 procesado_en DATETIME DEFAULT NULL,
 lease_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 lease_hasta DATETIME DEFAULT NULL,
 intentos_proceso INT NOT NULL DEFAULT 0,
 proxima_revision DATETIME DEFAULT NULL,
 codigo_error VARCHAR(80) DEFAULT NULL,
 UNIQUE KEY uq_wompi_evento (entorno,clave_evento),
 KEY idx_wompi_transaccion (entorno,id_transaccion_wompi),
 KEY idx_wompi_revision (estado,proxima_revision),
 CONSTRAINT fk_evento_intento FOREIGN KEY (id_intento) REFERENCES intentos_pago (id_intento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS suscripcion_periodos (
 id_periodo BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 id_clinica INT NOT NULL,
 id_suscripcion INT NOT NULL,
 id_plan INT NOT NULL,
 id_pago BIGINT DEFAULT NULL,
 origen ENUM('wompi','administrativo','legado') NOT NULL,
 ciclo ENUM('mensual','anual') NOT NULL,
 inicio DATETIME NOT NULL,
 fin DATETIME NOT NULL,
 dia_ancla INT NOT NULL,
 mes_ancla INT NOT NULL,
 hora_ancla TIME NOT NULL,
 total_centavos BIGINT DEFAULT NULL,
 moneda CHAR(3) NOT NULL DEFAULT 'COP',
 creado_en DATETIME NOT NULL,
 id_usuario_actor INT DEFAULT NULL,
 motivo TEXT DEFAULT NULL,
 UNIQUE KEY uq_periodo_pago (id_pago),
 KEY idx_periodo_suscripcion (id_suscripcion,inicio,fin),
 KEY idx_periodo_clinica (id_clinica,fin),
 CONSTRAINT fk_periodo_clinica FOREIGN KEY (id_clinica) REFERENCES clinicas (id_clinica),
 CONSTRAINT fk_periodo_suscripcion FOREIGN KEY (id_suscripcion) REFERENCES suscripciones (id_suscripcion),
 CONSTRAINT fk_periodo_plan FOREIGN KEY (id_plan) REFERENCES planes (id_plan),
 CONSTRAINT fk_periodo_pago FOREIGN KEY (id_pago) REFERENCES pagos_suscripcion (id_pago),
 CONSTRAINT fk_periodo_actor FOREIGN KEY (id_usuario_actor) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS avisos_suscripcion (
 id_aviso BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 id_clinica INT NOT NULL,
 id_suscripcion INT NOT NULL,
 id_pago BIGINT DEFAULT NULL,
 id_intento BIGINT DEFAULT NULL,
 id_usuario_destinatario INT NOT NULL,
 tipo ENUM('vence_7','vence_1','congelada','cobro_fallido') NOT NULL,
 clave_aviso CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 vencimiento_objetivo DATETIME NOT NULL,
 programado_en DATETIME NOT NULL,
 estado ENUM('pendiente','reclamado','enviado','error','incierto','obsoleto') NOT NULL DEFAULT 'pendiente',
 id_notificacion INT DEFAULT NULL,
 lease_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 lease_hasta DATETIME DEFAULT NULL,
 intentos INT NOT NULL DEFAULT 0,
 enviado_en DATETIME DEFAULT NULL,
 UNIQUE KEY uq_aviso_suscripcion (clave_aviso),
 KEY idx_aviso_programado (estado,programado_en),
 KEY idx_aviso_clinica (id_clinica,programado_en),
 CONSTRAINT fk_aviso_clinica FOREIGN KEY (id_clinica) REFERENCES clinicas (id_clinica),
 CONSTRAINT fk_aviso_suscripcion FOREIGN KEY (id_suscripcion) REFERENCES suscripciones (id_suscripcion),
 CONSTRAINT fk_aviso_pago FOREIGN KEY (id_pago) REFERENCES pagos_suscripcion (id_pago),
 CONSTRAINT fk_aviso_intento FOREIGN KEY (id_intento) REFERENCES intentos_pago (id_intento),
 CONSTRAINT fk_aviso_usuario FOREIGN KEY (id_usuario_destinatario) REFERENCES usuarios (id_usuario),
 CONSTRAINT fk_aviso_notificacion FOREIGN KEY (id_notificacion) REFERENCES notificaciones (id_notificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE suscripciones ADD CONSTRAINT fk_suscripcion_fuente FOREIGN KEY (id_fuente_activa) REFERENCES fuentes_pago (id_fuente);
ALTER TABLE suscripciones ADD CONSTRAINT fk_suscripcion_autor FOREIGN KEY (autorizado_por) REFERENCES usuarios (id_usuario);
```
