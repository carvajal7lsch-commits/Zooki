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
  `id_nodo` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `tipo` enum('sintoma','diagnostico','farmaco','especie','raza','condicion') NOT NULL,
  `etiqueta` varchar(150) NOT NULL
);

CREATE TABLE `grafo_aristas` (
  `id_arista` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_origen` int(11) NOT NULL,
  `id_destino` int(11) NOT NULL,
  `tipo_relacion` varchar(50) NOT NULL,
  `signo` enum('positivo','negativo') NOT NULL,
  `peso` decimal(4,3) DEFAULT 1.000
);

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
  `fecha_registro` datetime DEFAULT current_timestamp()
);

CREATE TABLE `consentimientos_datos` (
  `id_consentimiento` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `version_politica` varchar(20) NOT NULL,
  `medio` enum('formulario','google','alta_personal') NOT NULL,
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
  `email` varchar(255) NOT NULL,                             -- correo que se verifica (registro o cambio de correo, RN-G23)
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
  KEY `idx_ingreso_clinica` (`id_clinica`, `estado`)
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
  KEY `idx_ausvet_clinica` (`id_clinica`, `id_veterinario`, `fecha_hora_inicio`)
);
-- Ausencia del vet; id_cobertura = veterinario que reemplaza (intercambio = ausencia + cobertura).

CREATE TABLE `citas` (
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
  `ocupa_horario` tinyint(1) DEFAULT NULL,                   -- calculada en 01_schema.sql: NULL si está libre (cancelada, no_asistio) o es sobrecupo; 1 si ocupa el horario
  `hora_llegada` datetime DEFAULT NULL,
  `hora_inicio_real` datetime DEFAULT NULL,
  `hora_fin_real` datetime DEFAULT NULL,
  `aviso_atencion_abierta` datetime DEFAULT NULL,            -- cuándo se avisó que la atención seguía abierta (RN-409, una sola vez)
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  UNIQUE KEY `uq_cita_veterinario_horario` (`id_veterinario`, `fecha`, `hora`, `ocupa_horario`), -- doble reserva (RN-401), sin id_clinica a propósito
  KEY `idx_citas_clinica_fecha` (`id_clinica`, `fecha`, `estado`),
  KEY `idx_citas_clinica_veterinario` (`id_clinica`, `id_veterinario`, `fecha`)
);

CREATE TABLE `cita_sintomas` (
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
  KEY `idx_reasig_estado` (`estado`, `fecha_limite`)
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
  ADD CONSTRAINT `fk_verif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);
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
