-- ============================================================
-- Zooki v2.0 — Esquema para importar en drawDB
-- Multi-inquilino (id_clinica), grafos, suscripción y reputación.
-- Catálogos taxonómicos y grafo clínico: GLOBALES (sin id_clinica).
--
-- Es solo la referencia para el diagrama (documentacion/MER.md). Vive en
-- database/modelo/ a propósito: ni MySQL al crear el volumen ni el migrador
-- recorren subcarpetas, y ejecutarlo encima de 01_schema.sql fallaría por
-- tablas repetidas. El esquema ejecutable es database/01_schema.sql, que
-- además declara los índices compuestos por id_clinica y calcula
-- citas.ocupa_horario (aquí va como columna simple para que drawDB la importe).
-- ============================================================

-- M4 B2: 58 tablas coherentes con 01_schema.sql y migracion 08.
-- Esquema preparado/probado, no despliegue ni flujos C implementados.
-- Produccion alpha.4 conserva 52 hasta desplegar. No ejecutar este archivo.
-- cita_abierta/cita_pendiente son convencionales aqui; 01/08 las calculan STORED.

-- M0-S S1: 65 tablas; tres parametros clinicos y estructuras de pago vacias.
-- ---------- Plataforma / SaaS (nuevo) ----------

CREATE TABLE `planes` (
  `id_plan` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL UNIQUE,
  `precio_mensual` int(11) NOT NULL DEFAULT 0,
  `precio_anual` int(11) DEFAULT NULL,
  `limite_mascotas` int(11) DEFAULT NULL,
  `limite_citas_mes` int(11) DEFAULT NULL,
  `limite_personal` int(11) DEFAULT NULL,                    -- NULL = sin límite (RN-003)
  `estado` tinyint(4) DEFAULT 1
);

CREATE TABLE `clinicas` (
  `id_clinica` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `suscripcion_gracia_dias` INT NOT NULL DEFAULT 3,
  `aviso_vencimiento_1_dias` INT NOT NULL DEFAULT 7,
  `aviso_vencimiento_2_dias` INT NOT NULL DEFAULT 1,
  `nombre` varchar(150) NOT NULL,
  `nit` varchar(15) NOT NULL UNIQUE,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `telefono_urgencias` varchar(20) DEFAULT NULL,
  `tolerancia_llegada_min` int(11) NOT NULL DEFAULT 10,
  `plazo_reasignacion_min` int(11) NOT NULL DEFAULT 10,
  `umbral_aviso_min` int(11) NOT NULL DEFAULT 15,
  `tope_sobrecupos` int(11) NOT NULL DEFAULT 2,
  `email_contacto` varchar(255) DEFAULT NULL,
  `url_logo` varchar(255) DEFAULT NULL,
  `id_plan` int(11) DEFAULT NULL,
  `estado` enum('pendiente_verificacion','activa','suspendida','baja') NOT NULL DEFAULT 'pendiente_verificacion',
  `fecha_registro` datetime DEFAULT current_timestamp()
);

CREATE TABLE `suscripciones` (
  `id_suscripcion` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `gestion_cobro` ENUM('legado','gestionada') NOT NULL DEFAULT 'legado',
  `periodo_inicio` DATETIME DEFAULT NULL,
  `periodo_fin` DATETIME DEFAULT NULL,
  `estado_cobro` ENUM('sin_vigencia','al_dia','gracia','congelada') NOT NULL DEFAULT 'sin_vigencia',
  `version_periodo` INT NOT NULL DEFAULT 0,
  `cobro_automatico` TINYINT NOT NULL DEFAULT 0,
  `id_fuente_activa` BIGINT DEFAULT NULL,
  `autorizado_por` INT DEFAULT NULL,
  `autorizado_en` DATETIME DEFAULT NULL,
  `retirado_en` DATETIME DEFAULT NULL,
  `cambio_ciclo_siguiente` ENUM('mensual','anual') DEFAULT NULL,
  `id_clinica` int(11) NOT NULL,
  `id_plan` int(11) NOT NULL,
  `estado` enum('activa','suspendida','cancelada') NOT NULL DEFAULT 'activa',
  `ciclo` enum('mensual','anual') NOT NULL DEFAULT 'mensual',
  `al_dia` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  KEY `idx_susc_clinica` (`id_clinica`, `estado`)
);

CREATE TABLE `plantillas_comunicacion` (
  `id_plantilla` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `asunto` varchar(255) NOT NULL,
  `cuerpo` text NOT NULL,
  `dias_anticipacion` int(11) DEFAULT NULL,
  `hora_envio` time DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  KEY `idx_plantilla_clinica` (`id_clinica`, `tipo`)
);

-- ---------- Grafo de conocimiento clínico (Grafo I) — GLOBAL ----------

CREATE TABLE `grafo_nodos` (
  `id_nodo` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `version_conocimiento` VARCHAR(64) NOT NULL,
  `fuentes` JSON NOT NULL,
  `estado_validacion` ENUM('pendiente','validado','rechazado') NOT NULL DEFAULT 'pendiente',
  `tipo` ENUM('sintoma','diagnostico','farmaco','especie','raza','condicion','sustancia') NOT NULL,
  `etiqueta` VARCHAR(150) NOT NULL,
  `nivel_triage` ENUM('rojo','naranja','amarillo','verde') DEFAULT NULL,
  `alarma_universal` TINYINT(1) NOT NULL DEFAULT 0,
  `moderado` TINYINT(1) NOT NULL DEFAULT 0,
  `metadatos` JSON DEFAULT NULL,
  `id_especie` INT DEFAULT NULL,
  `id_raza` INT DEFAULT NULL,
  PRIMARY KEY (`id_nodo`),
  UNIQUE KEY `uq_grafo_nodos_codigo` (`codigo`),
  UNIQUE KEY `uq_grafo_nodos_especie` (`id_especie`),
  UNIQUE KEY `uq_grafo_nodos_raza` (`id_raza`),
  KEY `idx_grafo_nodos_tipo` (`tipo`, `etiqueta`),
  CONSTRAINT `ck_nodo_taxonomia` CHECK (
    (activo = 0 AND codigo LIKE 'legado.%')
    OR (tipo = 'especie' AND id_especie IS NOT NULL AND id_raza IS NULL)
    OR (tipo = 'raza' AND id_raza IS NOT NULL AND id_especie IS NULL)
    OR (tipo NOT IN ('especie','raza') AND id_especie IS NULL AND id_raza IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `grafo_aristas` (
  `id_arista` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `version_conocimiento` VARCHAR(64) NOT NULL,
  `fuentes` JSON NOT NULL,
  `estado_validacion` ENUM('pendiente','validado','rechazado') NOT NULL DEFAULT 'pendiente',
  `id_origen` INT NOT NULL,
  `id_destino` INT NOT NULL,
  `tipo_relacion` VARCHAR(50) NOT NULL,
  `signo` ENUM('positivo','negativo') NOT NULL,
  `peso` DECIMAL(4,3) NOT NULL DEFAULT 1.000,
  `efecto` ENUM('evidencia','bloqueo','advertencia') NOT NULL DEFAULT 'evidencia',
  `condiciones` JSON DEFAULT NULL,
  PRIMARY KEY (`id_arista`),
  UNIQUE KEY `uq_grafo_aristas_codigo` (`codigo`),
  KEY `idx_grafo_aristas_origen` (`id_origen`, `tipo_relacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------- Reputación de veterinarios (nuevo) ----------

CREATE TABLE `especialidades` (
  `id_especialidad` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL
);

CREATE TABLE `veterinario_perfil` (
  `id_veterinario` int(11) NOT NULL PRIMARY KEY,
  `bio` text DEFAULT NULL,
  `url_foto` varchar(255) DEFAULT NULL
);

CREATE TABLE `veterinario_especialidades` (
  `id_veterinario` int(11) NOT NULL,
  `id_especialidad` int(11) NOT NULL,
  PRIMARY KEY (`id_veterinario`, `id_especialidad`)
);

CREATE TABLE `resenas_veterinario` (
  `id_resena` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_cita` int(11) NOT NULL UNIQUE,                         -- una reseña por cita (RN-801)
  `id_veterinario` int(11) NOT NULL,
  `id_propietario` int(11) NOT NULL,
  `estrellas` tinyint(4) NOT NULL,
  `comentario` text DEFAULT NULL,
  `oculta` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_moderacion` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
);

-- ---------- Acceso y usuarios (identidad única; roles por clínica en usuario_clinica) ----------

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL UNIQUE
);
-- Datos: 1 Administrador, 2 Veterinario, 4 Propietario, 5 Super-administrador.
-- El rol 3 (Recepcionista) se elimina en v2.0.

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT, -- clave sustituta: el documento puede cambiar y se anonimiza (RN-G16)
  `documento` varchar(20) DEFAULT NULL UNIQUE,                -- NULL mientras el perfil de una cuenta de Google está incompleto
  `tipo_documento` varchar(20) DEFAULT NULL,
  `nombre_completo` varchar(200) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL UNIQUE,                  -- único en la plataforma (RN-G06); NULL solo si la cuenta se suprimió
  `password` varchar(255) DEFAULT NULL,                      -- NULL en cuentas creadas con Google hasta que creen una contraseña
  `google_uid` varchar(64) DEFAULT NULL UNIQUE,
  `perfil_completo` tinyint(1) NOT NULL DEFAULT 1,
  `es_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `estado` tinyint(4) DEFAULT 1,
  `debe_cambiar_password` tinyint(1) DEFAULT 0,
  `version_sesion` int(11) NOT NULL DEFAULT 0,               -- sube al crear o cambiar la contraseña: cierra las demás sesiones (RE-T.2.5)
  `fecha_registro` datetime DEFAULT current_timestamp()
);

CREATE TABLE `consentimientos_datos` (
  `id_consentimiento` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `version_politica` varchar(20) NOT NULL,
  `medio` enum('formulario','google','alta_personal','activacion_propietario') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
);

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
);

CREATE TABLE `verificaciones_email` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_clinica_vinculo` INT DEFAULT NULL,
  `id_rol_vinculo` INT DEFAULT NULL,                         -- rol ofrecido en una invitación al personal (RE-T.7.5)
  `proposito` VARCHAR(30) NOT NULL DEFAULT 'registro',
  `email` varchar(255) NOT NULL,                             -- correo que se verifica (registro o cambio de correo, RN-G23)
  `nombre_invitado` varchar(200) DEFAULT NULL,               -- lo que escribió el administrador al invitar (D2.2)
  `tipo_documento_invitado` varchar(20) DEFAULT NULL,
  `documento_invitado` varchar(20) DEFAULT NULL,
  `email_invitado` varchar(255) DEFAULT NULL,
  `telefono_invitado` varchar(20) DEFAULT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
);

CREATE TABLE `intentos_login` (
  `id_intento` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `identificador` varchar(120) NOT NULL UNIQUE,              -- 'ip:<dirección>', 'cuenta:<id_usuario>' (RN-G15) o 'chk:<dirección>' (verificaciones del registro)
  `intentos` int(11) NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `primer_intento` datetime NOT NULL,
  `ultimo_intento` datetime NOT NULL
);

CREATE TABLE `casos_soporte` (
  `id_caso` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `tipo` enum('documento_duplicado','cuenta_duplicada','clinica_duplicada','abuso_plan','otro') NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_clinica` int(11) DEFAULT NULL,
  `descripcion` text NOT NULL,
  `estado` enum('abierto','resuelto','descartado') NOT NULL DEFAULT 'abierto',
  `fecha` datetime DEFAULT current_timestamp(),
  `fecha_cierre` datetime DEFAULT NULL
);
-- Casos que pasan al super-administrador (RN-G24, RN-012).

-- ---------- Propietario multi-clínica (identidad global) ----------

CREATE TABLE `usuario_clinica` (
  `id_usuario` int(11) NOT NULL,
  `id_clinica` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `fecha_vinculo` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`, `id_clinica`),
  KEY `idx_usuario_clinica_rol` (`id_clinica`, `id_rol`, `estado`)
);
CREATE TABLE `propietario_clinica` (
  `id_propietario` int(11) NOT NULL,
  `id_clinica` int(11) NOT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `fecha_vinculo` datetime DEFAULT current_timestamp(),
  `autoriza_historia_compartida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_autorizacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_propietario`, `id_clinica`),
  KEY `idx_propietario_clinica_estado` (`id_clinica`, `estado`)
);
CREATE TABLE `mascota_clinica` (
  `id_mascota` int(11) NOT NULL,
  `id_clinica` int(11) NOT NULL,
  `numero_historia_clinica` varchar(255) DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `fecha_vinculo` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_mascota`, `id_clinica`),
  UNIQUE KEY `uq_mascota_clinica_numero_hc` (`id_clinica`, `numero_historia_clinica`),
  KEY `idx_mascota_clinica_estado` (`id_clinica`, `estado`)
);
CREATE TABLE `carnet_accesos` (
  `id_acceso` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `ip_hash` char(64) NOT NULL,
  `fecha` datetime NOT NULL,
  KEY `idx_carnet_accesos_ip_fecha` (`ip_hash`, `fecha`),
  KEY `idx_carnet_accesos_fecha` (`fecha`)
);

CREATE TABLE `carnet_escaneos` (
  `id_escaneo` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_mascota` int(11) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `ip_hash` char(64) NOT NULL,
  `latitud` decimal(9,6) DEFAULT NULL,
  `longitud` decimal(9,6) DEFAULT NULL,
  `notificado` tinyint(1) NOT NULL DEFAULT 0
);
-- El propietario es identidad global (usuarios.email único); se vincula a una o varias
-- clínicas por propietario_clinica; una cuenta ya verificada que inicia sesión
-- puede elegir otra clínica sin repetir la verificación del correo (HU-5.13).

-- ---------- Catálogos taxonómicos — GLOBALES ----------

CREATE TABLE `especies` (
  `id_especie` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_especie` varchar(50) NOT NULL UNIQUE
);

CREATE TABLE `razas` (
  `id_raza` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_especie` int(11) NOT NULL,
  `nombre_raza` varchar(50) NOT NULL,
  UNIQUE KEY `uq_raza_especie_nombre` (`id_especie`, `nombre_raza`)
);

CREATE TABLE `colores_base` (
  `id_color` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nombre_color` varchar(30) NOT NULL UNIQUE
);

-- ---------- Pacientes (con id_clinica) ----------

CREATE TABLE `mascotas` (
  `id_mascota` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_propietario` int(11) DEFAULT NULL,                    -- NULL solo mientras se verifica una urgencia provisional (RE-4.15.3)
  `id_clinica_registro` int(11) DEFAULT NULL,
  `token_carnet` char(43) NOT NULL UNIQUE,
  `carnet_activo` tinyint(1) NOT NULL DEFAULT 1,              -- 0 si la mascota se inactiva o el propietario suprime su cuenta
  `token_carnet_fecha` datetime DEFAULT current_timestamp(),
  `id_especie` int(11) DEFAULT NULL,                       -- datos faltantes permitidos solo en ficha provisional
  `id_raza` int(11) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `peso` decimal(5,2) DEFAULT NULL,
  `sexo` enum('Macho','Hembra','Desconocido') NOT NULL DEFAULT 'Desconocido',
  `esterilizado` tinyint(1) DEFAULT NULL,                     -- NULL = no se sabe; lo usa el triage (RN-416)
  `estado` tinyint(4) NOT NULL DEFAULT 1,
  `ficha_por_completar` tinyint(1) NOT NULL DEFAULT 0,     -- una urgencia puede atenderse antes de completar identidad y datos
  `url_foto` varchar(255) DEFAULT NULL,
  `raza_indicada` varchar(50) DEFAULT NULL                   -- mismo largo que razas.nombre_raza
);

-- Solo para llegadas presenciales sin ficha verificable a tiempo; el portal usa la mascota de la sesión.
CREATE TABLE `ingresos_emergencia` (
  `id_ingreso` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `id_mascota_provisional` int(11) NOT NULL,
  `id_mascota_final` int(11) DEFAULT NULL,                 -- misma mascota al completar o ficha existente al consolidar
  `nombre_acompanante` varchar(255) DEFAULT NULL,
  `documento_acompanante` varchar(20) DEFAULT NULL,
  `telefono_acompanante` varchar(30) DEFAULT NULL,
  `estado` enum('pendiente','completado','consolidado') NOT NULL DEFAULT 'pendiente',
  `fecha_ingreso` datetime NOT NULL DEFAULT current_timestamp(),
  KEY `idx_ingreso_clinica` (`id_clinica`, `estado`),
  `id_conciliador` INT DEFAULT NULL,
  `fecha_conciliacion` DATETIME DEFAULT NULL,
  `evidencia_conciliacion` JSON DEFAULT NULL,
  `necesidad_contacto_resuelta_en` DATETIME DEFAULT NULL,
  `motivo_conservacion_contacto` VARCHAR(255) DEFAULT NULL,
  `contacto_anonimizado_en` DATETIME DEFAULT NULL,
  UNIQUE KEY uq_ingreso_clinica (id_ingreso, id_clinica)
);

CREATE TABLE `alertas_medicas` (
  `id_alerta` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_mascota` int(11) NOT NULL,
  `id_clinica` int(11) NOT NULL,                             -- clínica que la registró; la alerta es visible para todas (RN-113)
  `id_veterinario` int(11) DEFAULT NULL,
  `tipo` enum('alergia','condicion_cronica','medicacion_continua','otra') NOT NULL,
  `id_nodo` int(11) DEFAULT NULL,                            -- nodo del Grafo I, si existe
  `descripcion` varchar(255) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `fecha` datetime DEFAULT current_timestamp()
);

CREATE TABLE `mascota_colores` (
  `id_mascota` int(11) NOT NULL,
  `id_color` int(11) NOT NULL,
  PRIMARY KEY (`id_mascota`, `id_color`)
);

-- ---------- Operación clínica (con id_clinica) ----------

CREATE TABLE `tipos_cita` (
  `id_tipo_cita` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `nombre_tipo` varchar(100) NOT NULL,
  `duracion_minutos` int(11) NOT NULL,
  `margen_minutos` int(11) NOT NULL DEFAULT 10,
  `pausable` tinyint(1) NOT NULL DEFAULT 1,
  `descripcion` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#0C66E4',
  `activo` tinyint(4) DEFAULT 1,
  KEY `idx_tipos_cita_clinica` (`id_clinica`, `activo`)
);

CREATE TABLE `horarios_veterinario` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_veterinario` int(11) NOT NULL,
  `id_clinica` int(11) NOT NULL,
  `dia_semana` tinyint(4) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  KEY `idx_horvet_clinica` (`id_clinica`, `id_veterinario`, `dia_semana`),
  KEY `idx_horvet_veterinario` (`id_veterinario`, `dia_semana`)
);
-- Horario recurrente por veterinario y clínica; disponibilidad = horario clínica ∩ horario vet − ausencias.
-- Lo configura el administrador; el veterinario propone cambios en propuestas_horario (RN-706).

CREATE TABLE `propuestas_horario` (
  `id_propuesta` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_veterinario` int(11) NOT NULL,
  `id_clinica` int(11) NOT NULL,
  `franjas` json NOT NULL,                                   -- [{dia_semana, hora_inicio, hora_fin}, ...]
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `id_revisor` int(11) DEFAULT NULL,
  `motivo_rechazo` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `fecha_revision` datetime DEFAULT NULL,
  KEY `idx_prophor_clinica` (`id_clinica`, `estado`)
);

CREATE TABLE `ausencias_veterinario` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_veterinario` int(11) NOT NULL,
  `id_clinica` int(11) NOT NULL,
  `fecha_hora_inicio` datetime NOT NULL,
  `fecha_hora_fin` datetime NOT NULL,
  `id_cobertura` int(11) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  KEY `idx_ausvet_clinica` (`id_clinica`, `id_veterinario`, `fecha_hora_inicio`),
  `estado` ENUM('solicitada','aprobada','rechazada','anulada') NOT NULL DEFAULT 'aprobada',
  `id_solicitante` INT DEFAULT NULL,
  `id_revisor` INT DEFAULT NULL,
  `fecha_revision` DATETIME DEFAULT NULL,
  `motivo_resolucion` VARCHAR(255) DEFAULT NULL,
  KEY idx_ausencia_revision (id_clinica, estado, fecha_hora_inicio),
  KEY idx_ausencia_vet (id_veterinario, estado, fecha_hora_inicio, fecha_hora_fin)
);
-- Ausencia del vet; id_cobertura = veterinario que reemplaza (intercambio = ausencia + cobertura).

CREATE TABLE `citas` (
  `contexto_triage` JSON DEFAULT NULL,
  `id_cita` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `id_veterinario` int(11) NOT NULL,
  `id_tipo_cita` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `hora_fin` time DEFAULT NULL,
  `motivo` varchar(255) NOT NULL,
  `duracion_minutos` int(11) DEFAULT NULL,
  `margen_minutos` int(11) NOT NULL DEFAULT 0,              -- valor del tipo de cita al reservar (RE-4.13.6)
  `prioridad` enum('rojo','naranja','amarillo','verde') NOT NULL DEFAULT 'verde', -- nivel de triage final
  `prioridad_calculada` enum('rojo','naranja','amarillo','verde') DEFAULT NULL, -- nivel del Grafo I antes del ajuste del veterinario
  `motivo_ajuste_prioridad` varchar(255) DEFAULT NULL,       -- obligatorio si prioridad <> prioridad_calculada (RN-419)
  `es_sobrecupo` tinyint(1) NOT NULL DEFAULT 0,              -- cuenta contra clinicas.tope_sobrecupos (RN-422)
  `orden_sobrecupo` int(11) DEFAULT NULL,                     -- orden persistido; el cambio manual exige motivo y auditoría
  `sintomas_texto` text DEFAULT NULL,                        -- texto libre opcional (RN-424)
  `inicio_sintomas` datetime DEFAULT NULL,                   -- cuándo empezaron o cuándo fue la ingesta del tóxico
  `estado` enum('pendiente','confirmada','en_curso','pausada','sin_cerrar','cancelada','completada','no_asistio') DEFAULT 'pendiente',
  `ocupa_horario` tinyint(1) DEFAULT NULL,                   -- calculada en 01_schema.sql: NULL si está libre (cancelada, no_asistio) o es sobrecupo o tiene origen urgencia; 1 si ocupa el horario
  `hora_llegada` datetime DEFAULT NULL,
  `hora_inicio_real` datetime DEFAULT NULL,
  `hora_fin_real` datetime DEFAULT NULL,
  `aviso_atencion_abierta` datetime DEFAULT NULL,            -- cuándo se avisó que la atención seguía abierta (RN-409, una sola vez)
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  UNIQUE KEY `uq_cita_veterinario_horario` (`id_veterinario`, `fecha`, `hora`, `ocupa_horario`), -- doble reserva (RN-401), sin id_clinica a propósito
  KEY `idx_citas_clinica_fecha` (`id_clinica`, `fecha`, `estado`),
  KEY `idx_citas_clinica_veterinario` (`id_clinica`, `id_veterinario`, `fecha`),
  `origen` ENUM('reserva','llegada_sin_reserva','urgencia','legado') NOT NULL DEFAULT 'legado',
  `id_bloque` INT DEFAULT NULL,
  `inicio_estimado` DATETIME DEFAULT NULL,
  `fin_estimado` DATETIME DEFAULT NULL,
  `duracion_activa_objetivo_segundos` INT DEFAULT NULL,
  `politica_temporal` VARCHAR(16) DEFAULT NULL,
  `estado_reajuste` ENUM('ninguno','pendiente_calculo','pendiente_revision','pendiente_reasignacion','pendiente_reprogramacion') NOT NULL DEFAULT 'ninguno',
  `motivo_reajuste` VARCHAR(255) DEFAULT NULL,
  `version_agenda` INT NOT NULL DEFAULT 0,
  `fecha_ultimo_calculo` DATETIME DEFAULT NULL,
  `ultima_hora_comunicada` DATETIME DEFAULT NULL,
  `ultimo_aviso_estimacion` DATETIME DEFAULT NULL,
  UNIQUE KEY uq_cita_clinica (id_cita, id_clinica),
  KEY idx_cita_bloque (id_bloque, es_sobrecupo, estado, fecha_registro, id_cita),
  KEY idx_cita_reajuste (id_clinica, estado_reajuste, fecha),
  KEY idx_cita_vet_estimada (id_veterinario, inicio_estimado),
  KEY idx_cita_mascota_estimada (id_mascota, inicio_estimado)
);

CREATE TABLE `cita_sintomas` (
  `detalles` JSON DEFAULT NULL,
  `id_cita` int(11) NOT NULL,
  `id_nodo` int(11) NOT NULL,                                -- nodo tipo 'sintoma'
  PRIMARY KEY (`id_cita`, `id_nodo`)
);

CREATE TABLE `reasignaciones` (
  `id_reasignacion` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_cita` int(11) NOT NULL,
  `id_veterinario_origen` int(11) NOT NULL,
  `id_veterinario_destino` int(11) NOT NULL,
  `estado` enum('pendiente','aceptada','rechazada','vencida') NOT NULL DEFAULT 'pendiente',
  `fecha_limite` datetime NOT NULL,                          -- ahora + clinicas.plazo_reasignacion_min (RN-428)
  `fecha_respuesta` datetime DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  KEY `idx_reasig_estado` (`estado`, `fecha_limite`),
  `id_clinica` INT NOT NULL,
  `inicio_propuesto` DATETIME DEFAULT NULL,
  `fin_propuesto` DATETIME DEFAULT NULL,
  `version_cita` INT DEFAULT NULL,
  `ciclo` INT NOT NULL DEFAULT 0,
  `motivo` VARCHAR(255) DEFAULT NULL,
  `cita_pendiente` INT DEFAULT NULL,
  KEY idx_reasig_clinica_limite (id_clinica, estado, fecha_limite),
  KEY idx_reasig_intentos (id_cita, ciclo, id_veterinario_destino),
  UNIQUE KEY uq_reasig_pendiente (cita_pendiente)
);

CREATE TABLE `consultas` (
  `id_consulta` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `id_cita` int(11) DEFAULT NULL UNIQUE,                     -- una consulta por cita
  `id_mascota` int(11) NOT NULL,
  `id_veterinario` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `motivo_consulta` text NOT NULL,
  `anamnesis` text NOT NULL,
  `peso` decimal(5,2) DEFAULT NULL,
  `temperatura` decimal(4,1) DEFAULT NULL,
  `frecuencia_cardiaca` int(11) DEFAULT NULL,
  `frecuencia_respiratoria` int(11) DEFAULT NULL,
  `diagnostico` text NOT NULL,
  `plan_tratamiento` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  KEY `idx_consultas_clinica_fecha` (`id_clinica`, `fecha_hora`),
  KEY `idx_consultas_mascota` (`id_mascota`, `fecha_hora`)
);

CREATE TABLE `consulta_sintomas` (
  `detalles` JSON DEFAULT NULL,
  `id_consulta` int(11) NOT NULL,
  `id_nodo` int(11) NOT NULL,                                -- nodo tipo 'sintoma'
  PRIMARY KEY (`id_consulta`, `id_nodo`)
);

CREATE TABLE `tratamientos` (
  `id_tratamiento` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_consulta` int(11) NOT NULL,
  `id_nodo_farmaco` int(11) DEFAULT NULL,                    -- nodo tipo 'farmaco' para cruzar contraindicaciones (RN-211)
  `medicamento` varchar(255) NOT NULL,
  `dosis` varchar(255) NOT NULL,
  `via_administracion` varchar(255) NOT NULL,
  `duracion` varchar(100) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,                             -- NULL = indefinido; vigente mientras no haya pasado
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
);

CREATE TABLE `archivos_clinicos` (
  `id_archivo` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_consulta` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_servidor` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `tipo_archivo` varchar(255) NOT NULL,
  `extension` varchar(20) NOT NULL,
  `tamano_bytes` int(11) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp()
);

-- ---------- Prevención (con id_clinica en catálogos por clínica) ----------

CREATE TABLE `vacunas` (
  `id_vacuna` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `id_veterinario` int(11) DEFAULT NULL,
  `nombre_vacuna` varchar(150) NOT NULL,
  `laboratorio` varchar(150) DEFAULT NULL,
  `lote` varchar(100) DEFAULT NULL,
  `fecha_aplicacion` date NOT NULL,
  `fecha_proxima_dosis` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  KEY `idx_vacunas_clinica_proxima` (`id_clinica`, `fecha_proxima_dosis`),
  KEY `idx_vacunas_mascota` (`id_mascota`, `fecha_aplicacion`)
);

CREATE TABLE `desparasitaciones` (
  `id_desparasitacion` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `id_veterinario` int(11) DEFAULT NULL,
  `tipo` enum('interna','externa') NOT NULL,
  `producto` varchar(150) NOT NULL,
  `periodicidad` enum('mensual','trimestral','semestral') NOT NULL,
  `fecha_aplicacion` date NOT NULL,
  `fecha_proxima` date NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  KEY `idx_desp_clinica_proxima` (`id_clinica`, `fecha_proxima`),
  KEY `idx_desp_mascota` (`id_mascota`, `fecha_aplicacion`)
);

CREATE TABLE `vacunas_base` (
  `id_vacuna_base` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `nombre_vacuna` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  KEY `idx_vacbase_clinica` (`id_clinica`, `estado`)
);

CREATE TABLE `especie_vacunas` (
  `id_especie_vacuna` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_especie` int(11) NOT NULL,
  `id_vacuna_base` int(11) NOT NULL,                         -- hereda la clínica de su vacuna base
  UNIQUE KEY `uq_especie_vacuna` (`id_especie`, `id_vacuna_base`)
);

CREATE TABLE `laboratorios_base` (
  `id_laboratorio` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `nombre_laboratorio` varchar(150) NOT NULL,
  `estado` tinyint(4) DEFAULT 1,
  KEY `idx_labbase_clinica` (`id_clinica`, `estado`)
);

CREATE TABLE `productos_desparasitacion_base` (
  `id_producto` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `tipo` enum('interna','externa','ambas') DEFAULT 'interna',
  `estado` tinyint(4) DEFAULT 1,
  KEY `idx_prodbase_clinica` (`id_clinica`, `estado`)
);

-- ---------- Comunicaciones y auditoría (con id_clinica) ----------

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) DEFAULT NULL,                         -- NULL en avisos de la plataforma (carnet, cambio de correo)
  `id_usuario` int(11) NOT NULL,                             -- destinatario (antes id_propietario)
  `tipo_entidad` varchar(50) NOT NULL,
  `id_entidad` int(11) NOT NULL,
  `destinatario_email` varchar(255) NOT NULL,
  `tipo_notificacion` varchar(50) NOT NULL,
  `asunto` varchar(255) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `fecha_envio` datetime DEFAULT current_timestamp(),
  `estado` enum('pendiente','enviado','error') DEFAULT 'pendiente',
  KEY `idx_notif_clinica_fecha` (`id_clinica`, `fecha_envio`),
  KEY `idx_notif_entidad` (`tipo_entidad`, `id_entidad`, `tipo_notificacion`)
);

CREATE TABLE `notificaciones_internas` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_rol_destino` int(11) DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `enlace` varchar(255) DEFAULT NULL,
  `id_cita` int(11) DEFAULT NULL,                            -- cita que originó el aviso; se retira al cancelarla, reprogramarla o atenderla
  `vigente_hasta` datetime DEFAULT NULL,                     -- desde cuándo deja de mostrarse; NULL = no caduca
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  KEY `idx_notint_usuario` (`id_clinica`, `id_usuario`, `leida`),
  KEY `idx_notint_rol` (`id_clinica`, `id_rol_destino`, `leida`),
  KEY `idx_notint_vigencia` (`vigente_hasta`)
);

CREATE TABLE `horarios_clinica` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `dia_semana` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `bloque_morning_activo` tinyint(1) NOT NULL DEFAULT 1,
  `bloque_afternoon_activo` tinyint(1) NOT NULL DEFAULT 1,
  `bloque_morning_inicio` time DEFAULT NULL,
  `bloque_morning_fin` time DEFAULT NULL,
  `bloque_afternoon_inicio` time DEFAULT NULL,
  `bloque_afternoon_fin` time DEFAULT NULL,
  UNIQUE KEY `uq_horario_clinica_dia` (`id_clinica`, `dia_semana`)
);

CREATE TABLE `auditoria_mascotas` (
  `id_auditoria` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `campo_modificado` varchar(100) DEFAULT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_nuevo` text DEFAULT NULL,
  `fecha_cambio` datetime DEFAULT current_timestamp(),
  KEY `idx_audmasc_clinica` (`id_clinica`, `id_mascota`)
);

CREATE TABLE `auditoria_sistema` (
  `id_auditoria` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha_hora` datetime DEFAULT current_timestamp(),
  `accion` enum('LOGIN','LOGIN_FAIL','LOGOUT','INSERT','UPDATE','DELETE','VIEW','OTHER') NOT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` varchar(50) DEFAULT NULL,
  `datos_anteriores` longtext DEFAULT NULL,
  `datos_nuevos` longtext DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  KEY `idx_audsis_clinica_fecha` (`id_clinica`, `fecha_hora`),
  KEY `idx_audsis_usuario` (`id_usuario`, `fecha_hora`)
);

-- ---------- Destino preparatorio M4/B1 (sin instalacion en alpha.4) ----------

-- Destino M4/B1: RE-4.8.2/3
CREATE TABLE `cierres_clinica` (
  `id_cierre` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `inicio` DATETIME NOT NULL,
  `fin` DATETIME NOT NULL,
  `motivo` VARCHAR(255) NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `id_autor` INT NOT NULL,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cierre_rango (id_clinica, activo, inicio, fin)
);

-- Destino M4/B1: RE-4.13.7
CREATE TABLE `bloques_agenda` (
  `id_bloque` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_veterinario` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `franja_origen` CHAR(64) NOT NULL,
  `inicio_original` DATETIME NOT NULL,
  `fin_original` DATETIME NOT NULL,
  `estado` ENUM('vigente','revisar','retirado') NOT NULL DEFAULT 'vigente',
  UNIQUE KEY uq_bloque_origen (id_clinica, id_veterinario, fecha, franja_origen),
  UNIQUE KEY uq_bloque_clinica (id_bloque, id_clinica),
  KEY idx_bloque_vet_fecha (id_veterinario, fecha)
);

-- Destino M4/B1: RE-2.2.5, RE-2.11.5, RE-4.15.1/2/3
CREATE TABLE `solicitudes_agenda` (
  `id_solicitud` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_mascota` INT NOT NULL,
  `id_actor` INT NOT NULL,
  `origen` ENUM('portal','llegada','interno') NOT NULL,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_llegada` DATETIME DEFAULT NULL,
  `id_receptor` INT DEFAULT NULL,
  `estado` ENUM('abierta','derivada','espera_presencial','asignada','en_atencion','finalizada','pendiente_reprogramacion','retirada') NOT NULL DEFAULT 'abierta',
  `estado_revision` ENUM('no_requerida','pendiente','resuelta') NOT NULL,
  `nivel_calculado` ENUM('rojo','naranja','amarillo','verde') DEFAULT NULL,
  `nivel_aprobado` ENUM('rojo','naranja','amarillo','verde') DEFAULT NULL,
  `contexto_triage` JSON NOT NULL,
  `version_conocimiento` CHAR(64) DEFAULT NULL,
  `huella_entrada` CHAR(64) NOT NULL,
  `huella_revision` CHAR(64) DEFAULT NULL,
  `id_revisor` INT DEFAULT NULL,
  `fecha_revision` DATETIME DEFAULT NULL,
  `motivo_revision` VARCHAR(255) DEFAULT NULL,
  `contagio` TINYINT(1) NOT NULL DEFAULT 0,
  `orden_espera` BIGINT DEFAULT NULL,
  `id_cita` INT DEFAULT NULL,
  `id_ingreso` INT DEFAULT NULL,
  `version_solicitud` INT NOT NULL DEFAULT 0,
  `fuente_nivel` ENUM('grafo','clinico_presencial') DEFAULT NULL,
  UNIQUE KEY uq_solicitud_clinica (id_solicitud, id_clinica),
  UNIQUE KEY uq_solicitud_cita (id_cita),
  KEY idx_solicitud_espera (id_clinica, estado, fecha_llegada, id_solicitud),
  KEY idx_solicitud_revision (id_clinica, estado_revision, fecha_registro),
  KEY idx_solicitud_mascota (id_mascota, estado)
);

-- Destino M4/B1: RE-4.15.8, RE-4.5.1/2/3
CREATE TABLE `pausas_cita` (
  `id_pausa` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_cita` INT NOT NULL,
  `id_solicitud_roja` INT NOT NULL,
  `inicio` DATETIME NOT NULL,
  `fin` DATETIME DEFAULT NULL,
  `id_autor` INT NOT NULL,
  `motivo` VARCHAR(255) NOT NULL,
  `cita_abierta` INT DEFAULT NULL,
  UNIQUE KEY uq_pausa_abierta (cita_abierta),
  KEY idx_pausa_cita (id_clinica, id_cita, inicio)
);

-- Destino M4/B1: RE-4.9.3, RE-4.15.3
CREATE TABLE `operaciones_agenda` (
  `id_operacion` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_actor` INT DEFAULT NULL,
  `actor_clave` VARCHAR(80) NOT NULL,
  `accion` VARCHAR(80) NOT NULL,
  `clave_operacion` CHAR(64) NOT NULL,
  `huella_entrada` CHAR(64) NOT NULL,
  `resultado` JSON NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_operacion_clave (id_clinica, actor_clave, accion, clave_operacion)
);

-- Destino M4/B1: RE-4.4.1/3/4, RE-4.14.7, RE-4.12.5, RE-2.11.6
CREATE TABLE `eventos_agenda` (
  `id_evento` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_cita` INT DEFAULT NULL,
  `id_solicitud` INT DEFAULT NULL,
  `tipo` VARCHAR(80) NOT NULL,
  `clave_evento` CHAR(64) NOT NULL,
  `destino_clave` VARCHAR(80) NOT NULL,
  `id_destinatario` INT DEFAULT NULL,
  `id_rol_destino` INT DEFAULT NULL,
  `canal` ENUM('interno','correo') NOT NULL,
  `version_recurso` INT NOT NULL,
  `hora_a_comunicar` DATETIME DEFAULT NULL,
  `estado` ENUM('pendiente','reclamado','enviado','error','incierto','omitido') NOT NULL DEFAULT 'pendiente',
  `reclamacion` CHAR(64) DEFAULT NULL,
  `fecha_reclamacion` DATETIME DEFAULT NULL,
  `fecha_resultado` DATETIME DEFAULT NULL,
  `intentos` INT NOT NULL DEFAULT 0,
  `error_tecnico` VARCHAR(255) DEFAULT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_evento_destino (id_clinica, clave_evento, destino_clave, canal),
  KEY idx_evento_pendiente (estado, fecha, id_evento),
  KEY idx_evento_cita (id_clinica, id_cita, tipo)
);

-- ============================================================
-- Claves foráneas
-- ============================================================

ALTER TABLE `clinicas`
  ADD CONSTRAINT `fk_clinica_plan` FOREIGN KEY (`id_plan`) REFERENCES `planes` (`id_plan`);
ALTER TABLE `suscripciones`
  ADD CONSTRAINT `fk_susc_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_susc_plan` FOREIGN KEY (`id_plan`) REFERENCES `planes` (`id_plan`);
ALTER TABLE `plantillas_comunicacion`
  ADD CONSTRAINT `fk_plantilla_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `grafo_aristas`
  ADD CONSTRAINT `fk_arista_origen` FOREIGN KEY (`id_origen`) REFERENCES `grafo_nodos` (`id_nodo`),
  ADD CONSTRAINT `fk_arista_destino` FOREIGN KEY (`id_destino`) REFERENCES `grafo_nodos` (`id_nodo`);
ALTER TABLE `veterinario_perfil`
  ADD CONSTRAINT `fk_vetperfil_usuario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `veterinario_especialidades`
  ADD CONSTRAINT `fk_vetesp_usuario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_vetesp_esp` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id_especialidad`);
ALTER TABLE `resenas_veterinario`
  ADD CONSTRAINT `fk_resena_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`),
  ADD CONSTRAINT `fk_resena_vet` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_resena_prop` FOREIGN KEY (`id_propietario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `usuario_clinica`
  ADD CONSTRAINT `fk_usuario_clinica_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_usuario_clinica_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_usuario_clinica_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);
ALTER TABLE `consentimientos_datos`
  ADD CONSTRAINT `fk_consentimiento_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `verificaciones_email`
  ADD CONSTRAINT `fk_verif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_verif_clinica_vinculo` FOREIGN KEY (`id_clinica_vinculo`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_verif_rol_vinculo` FOREIGN KEY (`id_rol_vinculo`) REFERENCES `roles` (`id_rol`);
ALTER TABLE `casos_soporte`
  ADD CONSTRAINT `fk_caso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_caso_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `propietario_clinica`
  ADD CONSTRAINT `fk_propietario_clinica_usuario` FOREIGN KEY (`id_propietario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_propietario_clinica_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `razas`
  ADD CONSTRAINT `razas_ibfk_1` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`);
ALTER TABLE `mascota_clinica`
  ADD CONSTRAINT `fk_mascota_clinica_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `fk_mascota_clinica_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `carnet_escaneos`
  ADD CONSTRAINT `fk_carnet_escaneo_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`);
ALTER TABLE `mascotas`
  ADD CONSTRAINT `fk_mascota_clinica_registro` FOREIGN KEY (`id_clinica_registro`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_mascota_especie` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`),
  ADD CONSTRAINT `fk_mascota_raza` FOREIGN KEY (`id_raza`) REFERENCES `razas` (`id_raza`),
  ADD CONSTRAINT `mascotas_ibfk_1` FOREIGN KEY (`id_propietario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `ingresos_emergencia`
  ADD CONSTRAINT `fk_ingreso_emergencia_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_ingreso_emergencia_provisional` FOREIGN KEY (`id_mascota_provisional`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `fk_ingreso_emergencia_final` FOREIGN KEY (`id_mascota_final`) REFERENCES `mascotas` (`id_mascota`);
ALTER TABLE `alertas_medicas`
  ADD CONSTRAINT `fk_alerta_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `fk_alerta_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_alerta_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_alerta_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `grafo_nodos` (`id_nodo`);
ALTER TABLE `mascota_colores`
  ADD CONSTRAINT `fk_mascota_colores_color` FOREIGN KEY (`id_color`) REFERENCES `colores_base` (`id_color`),
  ADD CONSTRAINT `fk_mascota_colores_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`);
ALTER TABLE `tipos_cita`
  ADD CONSTRAINT `fk_tipocita_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `horarios_veterinario`
  ADD CONSTRAINT `fk_horvet_usuario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_horvet_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `propuestas_horario`
  ADD CONSTRAINT `fk_prophor_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_prophor_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_prophor_revisor` FOREIGN KEY (`id_revisor`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `ausencias_veterinario`
  ADD CONSTRAINT `fk_ausvet_usuario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_ausvet_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_ausvet_cobertura` FOREIGN KEY (`id_cobertura`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `citas`
  ADD CONSTRAINT `fk_cita_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_cita_tipo` FOREIGN KEY (`id_tipo_cita`) REFERENCES `tipos_cita` (`id_tipo_cita`);
ALTER TABLE `cita_sintomas`
  ADD CONSTRAINT `fk_citasint_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`),
  ADD CONSTRAINT `fk_citasint_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `grafo_nodos` (`id_nodo`);
ALTER TABLE `reasignaciones`
  ADD CONSTRAINT `fk_reasig_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`),
  ADD CONSTRAINT `fk_reasig_origen` FOREIGN KEY (`id_veterinario_origen`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_reasig_destino` FOREIGN KEY (`id_veterinario_destino`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `consultas`
  ADD CONSTRAINT `fk_consulta_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `consultas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `consultas_ibfk_2` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `consultas_ibfk_3` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`);
ALTER TABLE `consulta_sintomas`
  ADD CONSTRAINT `fk_consint_consulta` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`),
  ADD CONSTRAINT `fk_consint_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `grafo_nodos` (`id_nodo`);
ALTER TABLE `tratamientos`
  ADD CONSTRAINT `tratamientos_ibfk_1` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`),
  ADD CONSTRAINT `fk_trat_farmaco` FOREIGN KEY (`id_nodo_farmaco`) REFERENCES `grafo_nodos` (`id_nodo`);
ALTER TABLE `archivos_clinicos`
  ADD CONSTRAINT `archivos_clinicos_ibfk_1` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`);
ALTER TABLE `vacunas`
  ADD CONSTRAINT `vacunas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `fk_vacuna_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_vacuna_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `desparasitaciones`
  ADD CONSTRAINT `desparasitaciones_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `fk_desparasitacion_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_desparasitacion_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `vacunas_base`
  ADD CONSTRAINT `fk_vacbase_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `especie_vacunas`
  ADD CONSTRAINT `fk_especie_vacunas_especie` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`),
  ADD CONSTRAINT `fk_especie_vacunas_vacuna` FOREIGN KEY (`id_vacuna_base`) REFERENCES `vacunas_base` (`id_vacuna_base`);
ALTER TABLE `laboratorios_base`
  ADD CONSTRAINT `fk_labbase_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `productos_desparasitacion_base`
  ADD CONSTRAINT `fk_prodbase_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notif_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `notificaciones_internas`
  ADD CONSTRAINT `fk_notint_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_noti_rol` FOREIGN KEY (`id_rol_destino`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `fk_noti_usr` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_notint_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`);
ALTER TABLE `horarios_clinica`
  ADD CONSTRAINT `fk_horario_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `auditoria_mascotas`
  ADD CONSTRAINT `fk_audmasc_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `auditoria_mascotas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `auditoria_mascotas_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `auditoria_sistema`
  ADD CONSTRAINT `fk_audsis_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  ADD CONSTRAINT `fk_audsis_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

CREATE TABLE IF NOT EXISTS `grafo_sinonimos` (
  `id_sinonimo` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `version_conocimiento` VARCHAR(64) NOT NULL,
  `fuentes` JSON NOT NULL,
  `estado_validacion` ENUM('pendiente','validado','rechazado') NOT NULL DEFAULT 'pendiente',
  `id_nodo` INT NOT NULL,
  `texto` VARCHAR(150) NOT NULL,
  `normalizado` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id_sinonimo`),
  UNIQUE KEY `uq_grafo_sinonimos_codigo` (`codigo`),
  KEY `idx_grafo_sinonimos_busqueda` (`normalizado`),
  CONSTRAINT `fk_sinonimo_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `grafo_nodos` (`id_nodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `grafo_reglas` (
  `id_regla` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `version_conocimiento` VARCHAR(64) NOT NULL,
  `fuentes` JSON NOT NULL,
  `estado_validacion` ENUM('pendiente','validado','rechazado') NOT NULL DEFAULT 'pendiente',
  `motor` ENUM('diagnosticos','prescripcion','triage') NOT NULL,
  `condiciones` JSON NOT NULL,
  `resultado` JSON NOT NULL,
  PRIMARY KEY (`id_regla`),
  UNIQUE KEY `uq_grafo_reglas_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE grafo_nodos ADD CONSTRAINT fk_nodo_especie FOREIGN KEY (id_especie) REFERENCES especies (id_especie), ADD CONSTRAINT fk_nodo_raza FOREIGN KEY (id_raza) REFERENCES razas (id_raza);


-- Relaciones preparatorias M4/B1; validacion de rol/estado/rango en servidor.
ALTER TABLE `cierres_clinica` ADD CONSTRAINT `fk_m4_cierresclinica_0` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `cierres_clinica` ADD CONSTRAINT `fk_m4_cierresclinica_1` FOREIGN KEY (`id_autor`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `bloques_agenda` ADD CONSTRAINT `fk_m4_bloquesagenda_0` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `bloques_agenda` ADD CONSTRAINT `fk_m4_bloquesagenda_1` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `bloques_agenda` ADD CONSTRAINT `fk_m4_bloquesagenda_2` FOREIGN KEY (`id_veterinario`, `id_clinica`) REFERENCES `usuario_clinica` (`id_usuario`, `id_clinica`);
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `fk_m4_solicitudesagenda_0` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `fk_m4_solicitudesagenda_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`);
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `fk_m4_solicitudesagenda_2` FOREIGN KEY (`id_actor`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `fk_m4_solicitudesagenda_3` FOREIGN KEY (`id_receptor`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `fk_m4_solicitudesagenda_4` FOREIGN KEY (`id_revisor`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `fk_m4_solicitudesagenda_5` FOREIGN KEY (`id_cita`, `id_clinica`) REFERENCES `citas` (`id_cita`, `id_clinica`);
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `fk_m4_solicitudesagenda_6` FOREIGN KEY (`id_ingreso`, `id_clinica`) REFERENCES `ingresos_emergencia` (`id_ingreso`, `id_clinica`);
ALTER TABLE `pausas_cita` ADD CONSTRAINT `fk_m4_pausascita_0` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `pausas_cita` ADD CONSTRAINT `fk_m4_pausascita_1` FOREIGN KEY (`id_cita`, `id_clinica`) REFERENCES `citas` (`id_cita`, `id_clinica`);
ALTER TABLE `pausas_cita` ADD CONSTRAINT `fk_m4_pausascita_2` FOREIGN KEY (`id_solicitud_roja`, `id_clinica`) REFERENCES `solicitudes_agenda` (`id_solicitud`, `id_clinica`);
ALTER TABLE `pausas_cita` ADD CONSTRAINT `fk_m4_pausascita_3` FOREIGN KEY (`id_autor`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `operaciones_agenda` ADD CONSTRAINT `fk_m4_operacionesagenda_0` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `operaciones_agenda` ADD CONSTRAINT `fk_m4_operacionesagenda_1` FOREIGN KEY (`id_actor`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `eventos_agenda` ADD CONSTRAINT `fk_m4_eventosagenda_0` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `eventos_agenda` ADD CONSTRAINT `fk_m4_eventosagenda_1` FOREIGN KEY (`id_cita`, `id_clinica`) REFERENCES `citas` (`id_cita`, `id_clinica`);
ALTER TABLE `eventos_agenda` ADD CONSTRAINT `fk_m4_eventosagenda_2` FOREIGN KEY (`id_solicitud`, `id_clinica`) REFERENCES `solicitudes_agenda` (`id_solicitud`, `id_clinica`);
ALTER TABLE `eventos_agenda` ADD CONSTRAINT `fk_m4_eventosagenda_3` FOREIGN KEY (`id_destinatario`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `eventos_agenda` ADD CONSTRAINT `fk_m4_eventosagenda_4` FOREIGN KEY (`id_rol_destino`) REFERENCES `roles` (`id_rol`);
ALTER TABLE `citas` ADD CONSTRAINT `fk_m4_citas_0` FOREIGN KEY (`id_bloque`, `id_clinica`) REFERENCES `bloques_agenda` (`id_bloque`, `id_clinica`);
ALTER TABLE `ausencias_veterinario` ADD CONSTRAINT `fk_m4_ausenciasveterinario_0` FOREIGN KEY (`id_solicitante`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `ausencias_veterinario` ADD CONSTRAINT `fk_m4_ausenciasveterinario_1` FOREIGN KEY (`id_revisor`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `reasignaciones` ADD CONSTRAINT `fk_m4_reasignaciones_0` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`);
ALTER TABLE `reasignaciones` ADD CONSTRAINT `fk_m4_reasignaciones_1` FOREIGN KEY (`id_cita`, `id_clinica`) REFERENCES `citas` (`id_cita`, `id_clinica`);
ALTER TABLE `ingresos_emergencia` ADD CONSTRAINT `fk_m4_ingresosemergencia_0` FOREIGN KEY (`id_conciliador`) REFERENCES `usuarios` (`id_usuario`);

-- M4 B2: restricciones locales; los invariantes entre filas pertenecen a C.
ALTER TABLE `cierres_clinica` ADD CONSTRAINT `ck_m4_cierre_intervalo` CHECK (fin > inicio);
ALTER TABLE `bloques_agenda` ADD CONSTRAINT `ck_m4_bloque_intervalo` CHECK (fin_original > inicio_original);
ALTER TABLE `pausas_cita` ADD CONSTRAINT `ck_m4_pausa_intervalo` CHECK (fin IS NULL OR fin > inicio);
ALTER TABLE `citas` ADD CONSTRAINT `ck_m4_cita_objetivo` CHECK (duracion_activa_objetivo_segundos IS NULL OR duracion_activa_objetivo_segundos > 0);
ALTER TABLE `ausencias_veterinario` ADD CONSTRAINT `ck_m4_ausencia_intervalo` CHECK (fecha_hora_fin > fecha_hora_inicio);
ALTER TABLE `reasignaciones` ADD CONSTRAINT `ck_m4_reasig_intervalo` CHECK ((inicio_propuesto IS NULL AND fin_propuesto IS NULL) OR (inicio_propuesto IS NOT NULL AND fin_propuesto IS NOT NULL AND fin_propuesto > inicio_propuesto));
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `ck_m4_solicitud_presencia` CHECK ((fecha_llegada IS NULL AND id_receptor IS NULL) OR (fecha_llegada IS NOT NULL AND id_receptor IS NOT NULL));
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `ck_m4_solicitud_espera` CHECK (estado NOT IN ('espera_presencial','en_atencion') OR (fecha_llegada IS NOT NULL AND id_receptor IS NOT NULL));
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `ck_m4_solicitud_revision` CHECK (estado_revision <> 'resuelta' OR (id_revisor IS NOT NULL AND fecha_revision IS NOT NULL AND huella_revision IS NOT NULL));
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `ck_m4_solicitud_grafo` CHECK (fuente_nivel IS NULL OR fuente_nivel <> 'grafo' OR (nivel_calculado IS NOT NULL AND version_conocimiento IS NOT NULL));
ALTER TABLE `solicitudes_agenda` ADD CONSTRAINT `ck_m4_solicitud_clinica` CHECK (fuente_nivel IS NULL OR fuente_nivel <> 'clinico_presencial' OR (nivel_aprobado IS NOT NULL AND nivel_aprobado = 'rojo' AND id_revisor IS NOT NULL AND motivo_revision IS NOT NULL));
ALTER TABLE `eventos_agenda` ADD CONSTRAINT `ck_m4_evento_recurso` CHECK (id_cita IS NOT NULL OR id_solicitud IS NOT NULL);
ALTER TABLE `eventos_agenda` ADD CONSTRAINT `ck_m4_evento_destino` CHECK ((id_destinatario IS NULL AND id_rol_destino IS NOT NULL) OR (id_destinatario IS NOT NULL AND id_rol_destino IS NULL));

-- M0-S S1: estructuras futuras vacías.
CREATE TABLE fuentes_pago (
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

CREATE TABLE pagos_suscripcion (
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

CREATE TABLE intentos_pago (
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

CREATE TABLE wompi_eventos (
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

CREATE TABLE suscripcion_periodos (
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

CREATE TABLE avisos_suscripcion (
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
