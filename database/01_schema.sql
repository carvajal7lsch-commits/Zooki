-- ---------------------------------------------------------------------------
-- Zooki v2 — Esquema ejecutable de la base de datos (plan M0, etapa B).
--
-- Base limpia: la v2 no migra datos de la v1, que eran pruebas (plan M0, §6).
-- Este archivo crea las 50 tablas del MER (documentacion/MER.md); el diagrama
-- para drawDB está en database/modelo/drawdb_schema_v2.sql.
--
-- Cómo se carga:
--   · Docker: MySQL lo ejecuta solo al crear un volumen vacío, seguido de
--     02_semilla.sql y de las migraciones 03 en adelante.
--   · Local (XAMPP): se importa a mano en una base vacía, luego
--     02_semilla.sql y después `php scripts/migrar.php`.
--
-- Usa CREATE TABLE sin IF NOT EXISTS a propósito: sobre una base que ya tiene
-- tablas (por ejemplo, la v1) debe fallar en vez de dejar un esquema mezclado.
--
-- Debe cargar sin errores en MySQL 8 (producción) y en MariaDB 10.4 (XAMPP):
-- por eso la intercalación es utf8mb4_general_ci, que existe en las dos.
-- Sin datos: los catálogos van en 02_semilla.sql.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- ===========================================================================
-- Plataforma (SaaS)
-- ===========================================================================

CREATE TABLE `planes` (
  `id_plan` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50) NOT NULL,
  `precio_mensual` INT NOT NULL DEFAULT 0,
  `precio_anual` INT DEFAULT NULL,
  -- NULL = sin límite (RN-003)
  `limite_mascotas` INT DEFAULT NULL,
  `limite_citas_mes` INT DEFAULT NULL,
  `limite_personal` INT DEFAULT NULL,
  `estado` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_plan`),
  UNIQUE KEY `uq_planes_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `clinicas` (
  `id_clinica` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `nit` VARCHAR(15) NOT NULL,
  `direccion` VARCHAR(255) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `telefono_urgencias` VARCHAR(20) DEFAULT NULL,
  -- Parámetros de la agenda con el valor por defecto de su regla
  -- (RN-426, RN-428, RN-429, RN-422).
  `tolerancia_llegada_min` INT NOT NULL DEFAULT 10,
  `plazo_reasignacion_min` INT NOT NULL DEFAULT 10,
  `umbral_aviso_min` INT NOT NULL DEFAULT 15,
  `tope_sobrecupos` INT NOT NULL DEFAULT 2,
  `email_contacto` VARCHAR(255) DEFAULT NULL,
  `url_logo` VARCHAR(255) DEFAULT NULL,
  `id_plan` INT DEFAULT NULL,
  `estado` ENUM('pendiente_verificacion','activa','suspendida','baja') NOT NULL DEFAULT 'pendiente_verificacion',
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_clinica`),
  UNIQUE KEY `uq_clinicas_nit` (`nit`),
  KEY `idx_clinicas_estado` (`estado`),
  CONSTRAINT `fk_clinica_plan` FOREIGN KEY (`id_plan`) REFERENCES `planes` (`id_plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `suscripciones` (
  `id_suscripcion` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_plan` INT NOT NULL,
  `estado` ENUM('activa','suspendida','cancelada') NOT NULL DEFAULT 'activa',
  `ciclo` ENUM('mensual','anual') NOT NULL DEFAULT 'mensual',
  `al_dia` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE DEFAULT NULL,
  PRIMARY KEY (`id_suscripcion`),
  KEY `idx_susc_clinica` (`id_clinica`, `estado`),
  CONSTRAINT `fk_susc_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_susc_plan` FOREIGN KEY (`id_plan`) REFERENCES `planes` (`id_plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `plantillas_comunicacion` (
  `id_plantilla` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `asunto` VARCHAR(255) NOT NULL,
  `cuerpo` TEXT NOT NULL,
  `dias_anticipacion` INT DEFAULT NULL,
  `hora_envio` TIME DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_plantilla`),
  KEY `idx_plantilla_clinica` (`id_clinica`, `tipo`),
  CONSTRAINT `fk_plantilla_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
-- Acceso e identidad
-- ===========================================================================

CREATE TABLE `roles` (
  `id_rol` INT NOT NULL AUTO_INCREMENT,
  `nombre_rol` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `uq_roles_nombre` (`nombre_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Identidad única de cada persona. La clave es id_usuario: el documento y el
-- correo son datos corregibles y se anonimizan al suprimir la cuenta (RN-G16).
CREATE TABLE `usuarios` (
  `id_usuario` INT NOT NULL AUTO_INCREMENT,
  -- NULL mientras una cuenta de Google no completa su perfil
  `documento` VARCHAR(20) DEFAULT NULL,
  `tipo_documento` VARCHAR(20) DEFAULT NULL,
  `nombre_completo` VARCHAR(200) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  -- Único en la plataforma (RN-G06); NULL solo si la cuenta se suprimió
  `email` VARCHAR(255) DEFAULT NULL,
  -- NULL en cuentas de Google hasta que creen una contraseña
  `password` VARCHAR(255) DEFAULT NULL,
  `google_uid` VARCHAR(64) DEFAULT NULL,
  `perfil_completo` TINYINT(1) NOT NULL DEFAULT 1,
  `es_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `estado` TINYINT NOT NULL DEFAULT 1,
  `debe_cambiar_password` TINYINT(1) NOT NULL DEFAULT 0,
  -- Sube al crear o cambiar la contraseña: cierra las demás sesiones (RE-T.2.5)
  `version_sesion` INT NOT NULL DEFAULT 0,
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuarios_documento` (`documento`),
  UNIQUE KEY `uq_usuarios_email` (`email`),
  UNIQUE KEY `uq_usuarios_google_uid` (`google_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Rol de personal (1 administrador, 2 veterinario) de una persona en cada clínica.
CREATE TABLE `usuario_clinica` (
  `id_usuario` INT NOT NULL,
  `id_clinica` INT NOT NULL,
  `id_rol` INT NOT NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `fecha_vinculo` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`, `id_clinica`),
  KEY `idx_usuario_clinica_rol` (`id_clinica`, `id_rol`, `estado`),
  CONSTRAINT `fk_usuario_clinica_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_usuario_clinica_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_usuario_clinica_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vínculo del propietario (identidad global) con cada clínica.
CREATE TABLE `propietario_clinica` (
  `id_propietario` INT NOT NULL,
  `id_clinica` INT NOT NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `fecha_vinculo` DATETIME DEFAULT CURRENT_TIMESTAMP,
  -- Si esta clínica puede ver las consultas de otras clínicas (RN-113)
  `autoriza_historia_compartida` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_autorizacion` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_propietario`, `id_clinica`),
  KEY `idx_propietario_clinica_estado` (`id_clinica`, `estado`),
  CONSTRAINT `fk_propietario_clinica_usuario` FOREIGN KEY (`id_propietario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_propietario_clinica_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Prueba de la autorización de tratamiento de datos (RN-G19).
CREATE TABLE `consentimientos_datos` (
  `id_consentimiento` INT NOT NULL AUTO_INCREMENT,
  `id_usuario` INT NOT NULL,
  `version_politica` VARCHAR(20) NOT NULL,
  `medio` ENUM('formulario','google','alta_personal') NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_consentimiento`),
  CONSTRAINT `fk_consentimiento_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_usuario` INT DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_email` (`email`),
  CONSTRAINT `fk_password_resets_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sirve para el registro y para el cambio de correo (RN-G23).
CREATE TABLE `verificaciones_email` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_usuario` INT NOT NULL,
  `id_clinica_vinculo` INT DEFAULT NULL,
  -- Rol ofrecido en una invitación al personal (RE-T.7.5)
  `id_rol_vinculo` INT DEFAULT NULL,
  `proposito` VARCHAR(30) NOT NULL DEFAULT 'registro',
  `email` VARCHAR(255) NOT NULL,
  -- Lo que escribió el administrador al invitar; la clínica solo ve esto
  -- hasta que el titular acepta (D2.2)
  `nombre_invitado` VARCHAR(200) DEFAULT NULL,
  `tipo_documento_invitado` VARCHAR(20) DEFAULT NULL,
  `documento_invitado` VARCHAR(20) DEFAULT NULL,
  `email_invitado` VARCHAR(255) DEFAULT NULL,
  `telefono_invitado` VARCHAR(20) DEFAULT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_verif_pendiente` (`id_usuario`, `used`),
  CONSTRAINT `fk_verif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_verif_clinica_vinculo` FOREIGN KEY (`id_clinica_vinculo`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_verif_rol_vinculo` FOREIGN KEY (`id_rol_vinculo`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Contadores de intentos fallidos, sin clave foránea. Claves con prefijo:
-- 'ip:<dirección>', 'cuenta:<id_usuario>' (RN-G15) y 'chk:<dirección>'.
CREATE TABLE `intentos_login` (
  `id_intento` INT NOT NULL AUTO_INCREMENT,
  `identificador` VARCHAR(120) NOT NULL,
  `intentos` INT NOT NULL DEFAULT 0,
  `bloqueado_hasta` DATETIME DEFAULT NULL,
  `primer_intento` DATETIME NOT NULL,
  `ultimo_intento` DATETIME NOT NULL,
  PRIMARY KEY (`id_intento`),
  UNIQUE KEY `uq_intentos_identificador` (`identificador`),
  KEY `idx_intentos_ultimo` (`ultimo_intento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Casos que pasan al super-administrador (RN-G24, RN-012).
CREATE TABLE `casos_soporte` (
  `id_caso` INT NOT NULL AUTO_INCREMENT,
  `tipo` ENUM('documento_duplicado','cuenta_duplicada','clinica_duplicada','abuso_plan','otro') NOT NULL,
  `id_usuario` INT DEFAULT NULL,
  `id_clinica` INT DEFAULT NULL,
  `descripcion` TEXT NOT NULL,
  `estado` ENUM('abierto','resuelto','descartado') NOT NULL DEFAULT 'abierto',
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha_cierre` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_caso`),
  KEY `idx_casos_estado` (`estado`, `fecha`),
  CONSTRAINT `fk_caso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_caso_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
-- Grafo de conocimiento clínico (Grafo I) — global
-- ===========================================================================

CREATE TABLE `grafo_nodos` (
  `id_nodo` INT NOT NULL AUTO_INCREMENT,
  `tipo` ENUM('sintoma','diagnostico','farmaco','especie','raza','condicion') NOT NULL,
  `etiqueta` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id_nodo`),
  KEY `idx_grafo_nodos_tipo` (`tipo`, `etiqueta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Grafo con signo: lo positivo favorece, lo negativo contraindica o veta.
CREATE TABLE `grafo_aristas` (
  `id_arista` INT NOT NULL AUTO_INCREMENT,
  `id_origen` INT NOT NULL,
  `id_destino` INT NOT NULL,
  `tipo_relacion` VARCHAR(50) NOT NULL,
  `signo` ENUM('positivo','negativo') NOT NULL,
  `peso` DECIMAL(4,3) DEFAULT 1.000,
  PRIMARY KEY (`id_arista`),
  KEY `idx_grafo_aristas_origen` (`id_origen`, `tipo_relacion`),
  CONSTRAINT `fk_arista_origen` FOREIGN KEY (`id_origen`) REFERENCES `grafo_nodos` (`id_nodo`),
  CONSTRAINT `fk_arista_destino` FOREIGN KEY (`id_destino`) REFERENCES `grafo_nodos` (`id_nodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
-- Reputación de veterinarios (perfil y especialidades; las reseñas van
-- después de citas, a la que referencian)
-- ===========================================================================

CREATE TABLE `especialidades` (
  `id_especialidad` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id_especialidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `veterinario_perfil` (
  `id_veterinario` INT NOT NULL,
  `bio` TEXT DEFAULT NULL,
  `url_foto` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id_veterinario`),
  CONSTRAINT `fk_vetperfil_usuario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `veterinario_especialidades` (
  `id_veterinario` INT NOT NULL,
  `id_especialidad` INT NOT NULL,
  PRIMARY KEY (`id_veterinario`, `id_especialidad`),
  CONSTRAINT `fk_vetesp_usuario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_vetesp_esp` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id_especialidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
-- Catálogos taxonómicos — globales. Los nombres son únicos para que la
-- semilla se pueda aplicar varias veces sin duplicar filas.
-- ===========================================================================

CREATE TABLE `especies` (
  `id_especie` INT NOT NULL AUTO_INCREMENT,
  `nombre_especie` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id_especie`),
  UNIQUE KEY `uq_especies_nombre` (`nombre_especie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `razas` (
  `id_raza` INT NOT NULL AUTO_INCREMENT,
  `id_especie` INT NOT NULL,
  `nombre_raza` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id_raza`),
  UNIQUE KEY `uq_raza_especie_nombre` (`id_especie`, `nombre_raza`),
  CONSTRAINT `fk_raza_especie` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `colores_base` (
  `id_color` INT NOT NULL AUTO_INCREMENT,
  `nombre_color` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`id_color`),
  UNIQUE KEY `uq_colores_nombre` (`nombre_color`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
-- Pacientes — la mascota es global; se liga a cada clínica por mascota_clinica
-- ===========================================================================

CREATE TABLE `mascotas` (
  `id_mascota` INT NOT NULL AUTO_INCREMENT,
  -- NULL solo mientras se verifica una urgencia provisional (RE-4.15.3)
  `id_propietario` INT DEFAULT NULL,
  -- Junto con el propietario, la única que cambia especie, raza, sexo y nacimiento (RN-110)
  `id_clinica_registro` INT DEFAULT NULL,
  -- Aleatorio (≥ 128 bits); nunca expone id_mascota en la URL (RN-502)
  `token_carnet` CHAR(43) NOT NULL,
  `carnet_activo` TINYINT(1) NOT NULL DEFAULT 1,
  `token_carnet_fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  -- Datos faltantes permitidos solo con ficha_por_completar = 1
  `id_especie` INT DEFAULT NULL,
  `id_raza` INT DEFAULT NULL,
  `nombre` VARCHAR(255) DEFAULT NULL,
  `fecha_nacimiento` DATE DEFAULT NULL,
  `peso` DECIMAL(5,2) DEFAULT NULL,
  `sexo` ENUM('Macho','Hembra','Desconocido') NOT NULL DEFAULT 'Desconocido',
  -- NULL = no se sabe; lo usa el triage (RN-416)
  `esterilizado` TINYINT(1) DEFAULT NULL,
  `estado` TINYINT NOT NULL DEFAULT 1,
  `ficha_por_completar` TINYINT(1) NOT NULL DEFAULT 0,
  `url_foto` VARCHAR(255) DEFAULT NULL,
  -- Mismo largo que razas.nombre_raza, para pasar al catálogo sin recortarse
  `raza_indicada` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id_mascota`),
  UNIQUE KEY `uq_mascotas_token_carnet` (`token_carnet`),
  KEY `idx_mascotas_propietario` (`id_propietario`, `estado`),
  CONSTRAINT `fk_mascota_propietario` FOREIGN KEY (`id_propietario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_mascota_clinica_registro` FOREIGN KEY (`id_clinica_registro`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_mascota_especie` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`),
  CONSTRAINT `fk_mascota_raza` FOREIGN KEY (`id_raza`) REFERENCES `razas` (`id_raza`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Vínculo mascota↔clínica; base del límite de mascotas del plan (RN-003).
CREATE TABLE `mascota_clinica` (
  `id_mascota` INT NOT NULL,
  `id_clinica` INT NOT NULL,
  -- Se asigna al guardar la primera consulta en esa clínica (RN-102)
  `numero_historia_clinica` VARCHAR(255) DEFAULT NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `fecha_vinculo` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mascota`, `id_clinica`),
  UNIQUE KEY `uq_mascota_clinica_numero_hc` (`id_clinica`, `numero_historia_clinica`),
  KEY `idx_mascota_clinica_estado` (`id_clinica`, `estado`),
  CONSTRAINT `fk_mascota_clinica_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  CONSTRAINT `fk_mascota_clinica_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La única relación que borra en cascada: los colores no son dato clínico.
CREATE TABLE `mascota_colores` (
  `id_mascota` INT NOT NULL,
  `id_color` INT NOT NULL,
  PRIMARY KEY (`id_mascota`, `id_color`),
  CONSTRAINT `fk_mascota_colores_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mascota_colores_color` FOREIGN KEY (`id_color`) REFERENCES `colores_base` (`id_color`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Escaneos del carnet público (RN-504, RN-505).
CREATE TABLE `carnet_escaneos` (
  `id_escaneo` INT NOT NULL AUTO_INCREMENT,
  `id_mascota` INT NOT NULL,
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ip_hash` CHAR(64) NOT NULL,
  `latitud` DECIMAL(9,6) DEFAULT NULL,
  `longitud` DECIMAL(9,6) DEFAULT NULL,
  `notificado` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_escaneo`),
  KEY `idx_carnet_mascota` (`id_mascota`, `fecha`),
  KEY `idx_carnet_ip` (`ip_hash`, `fecha`),
  CONSTRAINT `fk_carnet_escaneo_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ingreso presencial provisional de una urgencia roja (RE-4.15.3).
CREATE TABLE `ingresos_emergencia` (
  `id_ingreso` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_mascota_provisional` INT NOT NULL,
  `id_mascota_final` INT DEFAULT NULL,
  `nombre_acompanante` VARCHAR(255) DEFAULT NULL,
  `documento_acompanante` VARCHAR(20) DEFAULT NULL,
  `telefono_acompanante` VARCHAR(30) DEFAULT NULL,
  `estado` ENUM('pendiente','completado','consolidado') NOT NULL DEFAULT 'pendiente',
  `fecha_ingreso` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_ingreso`),
  KEY `idx_ingreso_clinica` (`id_clinica`, `estado`),
  CONSTRAINT `fk_ingreso_emergencia_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_ingreso_emergencia_provisional` FOREIGN KEY (`id_mascota_provisional`) REFERENCES `mascotas` (`id_mascota`),
  CONSTRAINT `fk_ingreso_emergencia_final` FOREIGN KEY (`id_mascota_final`) REFERENCES `mascotas` (`id_mascota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Visibles para todas las clínicas vinculadas y el carnet (RN-113, RN-503);
-- id_clinica es la clínica que la registró. No se borran: se desactivan.
CREATE TABLE `alertas_medicas` (
  `id_alerta` INT NOT NULL AUTO_INCREMENT,
  `id_mascota` INT NOT NULL,
  `id_clinica` INT NOT NULL,
  `id_veterinario` INT DEFAULT NULL,
  `tipo` ENUM('alergia','condicion_cronica','medicacion_continua','otra') NOT NULL,
  `id_nodo` INT DEFAULT NULL,
  `descripcion` VARCHAR(255) NOT NULL,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_alerta`),
  KEY `idx_alertas_mascota` (`id_mascota`, `activa`),
  CONSTRAINT `fk_alerta_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  CONSTRAINT `fk_alerta_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_alerta_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_alerta_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `grafo_nodos` (`id_nodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
-- Agenda (por clínica)
-- ===========================================================================

CREATE TABLE `tipos_cita` (
  `id_tipo_cita` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `nombre_tipo` VARCHAR(100) NOT NULL,
  `duracion_minutos` INT NOT NULL,
  -- Colchón contra retrasos (Grafo II)
  `margen_minutos` INT NOT NULL DEFAULT 10,
  -- Si se puede interrumpir por una urgencia (RN-427)
  `pausable` TINYINT(1) NOT NULL DEFAULT 1,
  `descripcion` TEXT DEFAULT NULL,
  `color` VARCHAR(20) DEFAULT '#0C66E4',
  `activo` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_tipo_cita`),
  KEY `idx_tipos_cita_clinica` (`id_clinica`, `activo`),
  CONSTRAINT `fk_tipocita_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bloques de atención (mañana y tarde) por día y por clínica. 1 = lunes … 7 = domingo.
CREATE TABLE `horarios_clinica` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `dia_semana` INT NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `bloque_morning_activo` TINYINT(1) NOT NULL DEFAULT 1,
  `bloque_afternoon_activo` TINYINT(1) NOT NULL DEFAULT 1,
  `bloque_morning_inicio` TIME DEFAULT NULL,
  `bloque_morning_fin` TIME DEFAULT NULL,
  `bloque_afternoon_inicio` TIME DEFAULT NULL,
  `bloque_afternoon_fin` TIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_horario_clinica_dia` (`id_clinica`, `dia_semana`),
  CONSTRAINT `fk_horario_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Horario recurrente del veterinario en cada clínica (RN-706).
CREATE TABLE `horarios_veterinario` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_veterinario` INT NOT NULL,
  `id_clinica` INT NOT NULL,
  `dia_semana` TINYINT NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  `activo` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_horvet_clinica` (`id_clinica`, `id_veterinario`, `dia_semana`),
  -- Para comprobar choques con su horario en otra clínica
  KEY `idx_horvet_veterinario` (`id_veterinario`, `dia_semana`),
  CONSTRAINT `fk_horvet_usuario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_horvet_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `propuestas_horario` (
  `id_propuesta` INT NOT NULL AUTO_INCREMENT,
  `id_veterinario` INT NOT NULL,
  `id_clinica` INT NOT NULL,
  -- [{dia_semana, hora_inicio, hora_fin}, ...]
  `franjas` JSON NOT NULL,
  `estado` ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `id_revisor` INT DEFAULT NULL,
  `motivo_rechazo` VARCHAR(255) DEFAULT NULL,
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha_revision` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_propuesta`),
  KEY `idx_prophor_clinica` (`id_clinica`, `estado`),
  CONSTRAINT `fk_prophor_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_prophor_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_prophor_revisor` FOREIGN KEY (`id_revisor`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inicio inclusivo y fin exclusivo, en la zona horaria de la clínica.
CREATE TABLE `ausencias_veterinario` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_veterinario` INT NOT NULL,
  `id_clinica` INT NOT NULL,
  `fecha_hora_inicio` DATETIME NOT NULL,
  `fecha_hora_fin` DATETIME NOT NULL,
  `id_cobertura` INT DEFAULT NULL,
  `motivo` VARCHAR(255) DEFAULT NULL,
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ausvet_clinica` (`id_clinica`, `id_veterinario`, `fecha_hora_inicio`),
  CONSTRAINT `fk_ausvet_usuario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_ausvet_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_ausvet_cobertura` FOREIGN KEY (`id_cobertura`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `citas` (
  `id_cita` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_mascota` INT NOT NULL,
  `id_veterinario` INT NOT NULL,
  `id_tipo_cita` INT DEFAULT NULL,
  `fecha` DATE NOT NULL,
  `hora` TIME NOT NULL,
  `hora_fin` TIME DEFAULT NULL,
  `motivo` VARCHAR(255) NOT NULL,
  -- Valores del tipo de cita al reservar (RE-4.13.6)
  `duracion_minutos` INT DEFAULT NULL,
  `margen_minutos` INT NOT NULL DEFAULT 0,
  -- Triage de 4 niveles (RN-411); prioridad_calculada es la del Grafo I antes del ajuste (RN-419)
  `prioridad` ENUM('rojo','naranja','amarillo','verde') NOT NULL DEFAULT 'verde',
  `prioridad_calculada` ENUM('rojo','naranja','amarillo','verde') DEFAULT NULL,
  `motivo_ajuste_prioridad` VARCHAR(255) DEFAULT NULL,
  -- Cuenta contra clinicas.tope_sobrecupos (RN-422)
  `es_sobrecupo` TINYINT(1) NOT NULL DEFAULT 0,
  `orden_sobrecupo` INT DEFAULT NULL,
  `sintomas_texto` TEXT DEFAULT NULL,
  `inicio_sintomas` DATETIME DEFAULT NULL,
  `estado` ENUM('pendiente','confirmada','en_curso','pausada','sin_cerrar','cancelada','completada','no_asistio') NOT NULL DEFAULT 'pendiente',
  -- RN-401: 1 mientras la cita ocupa su horario; NULL si está libre o es
  -- sobrecupo. Un índice único admite varios NULL, así que las citas libres y
  -- los sobrecupos no chocan, y dos reservas que ocupan la misma hora sí.
  `ocupa_horario` TINYINT(1) GENERATED ALWAYS AS (
    CASE WHEN `estado` IN ('cancelada', 'no_asistio') OR `es_sobrecupo` = 1 THEN NULL ELSE 1 END
  ) STORED,
  `hora_llegada` DATETIME DEFAULT NULL,
  `hora_inicio_real` DATETIME DEFAULT NULL,
  `hora_fin_real` DATETIME DEFAULT NULL,
  -- Aviso de atención abierta, una sola vez (RN-409)
  `aviso_atencion_abierta` DATETIME DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cita`),
  -- Sin id_clinica a propósito: un veterinario tampoco se reserva dos veces entre clínicas
  UNIQUE KEY `uq_cita_veterinario_horario` (`id_veterinario`, `fecha`, `hora`, `ocupa_horario`),
  KEY `idx_citas_clinica_fecha` (`id_clinica`, `fecha`, `estado`),
  KEY `idx_citas_clinica_veterinario` (`id_clinica`, `id_veterinario`, `fecha`),
  KEY `idx_citas_mascota` (`id_mascota`, `fecha`),
  CONSTRAINT `fk_cita_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_cita_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  CONSTRAINT `fk_cita_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_cita_tipo` FOREIGN KEY (`id_tipo_cita`) REFERENCES `tipos_cita` (`id_tipo_cita`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Síntomas marcados del catálogo del grafo (RN-424).
CREATE TABLE `cita_sintomas` (
  `id_cita` INT NOT NULL,
  `id_nodo` INT NOT NULL,
  PRIMARY KEY (`id_cita`, `id_nodo`),
  CONSTRAINT `fk_citasint_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`),
  CONSTRAINT `fk_citasint_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `grafo_nodos` (`id_nodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reasignación en vivo; fecha_limite = ahora + clinicas.plazo_reasignacion_min (RN-428).
CREATE TABLE `reasignaciones` (
  `id_reasignacion` INT NOT NULL AUTO_INCREMENT,
  `id_cita` INT NOT NULL,
  `id_veterinario_origen` INT NOT NULL,
  `id_veterinario_destino` INT NOT NULL,
  `estado` ENUM('pendiente','aceptada','rechazada','vencida') NOT NULL DEFAULT 'pendiente',
  `fecha_limite` DATETIME NOT NULL,
  `fecha_respuesta` DATETIME DEFAULT NULL,
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reasignacion`),
  KEY `idx_reasig_estado` (`estado`, `fecha_limite`),
  CONSTRAINT `fk_reasig_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`),
  CONSTRAINT `fk_reasig_origen` FOREIGN KEY (`id_veterinario_origen`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_reasig_destino` FOREIGN KEY (`id_veterinario_destino`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Una reseña por cita atendida (RN-801); la clínica no la edita ni la borra (RN-804).
CREATE TABLE `resenas_veterinario` (
  `id_resena` INT NOT NULL AUTO_INCREMENT,
  `id_cita` INT NOT NULL,
  `id_veterinario` INT NOT NULL,
  `id_propietario` INT NOT NULL,
  `estrellas` TINYINT NOT NULL,
  `comentario` TEXT DEFAULT NULL,
  `oculta` TINYINT(1) NOT NULL DEFAULT 0,
  `motivo_moderacion` VARCHAR(255) DEFAULT NULL,
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_resena`),
  UNIQUE KEY `uq_resena_cita` (`id_cita`),
  KEY `idx_resena_veterinario` (`id_veterinario`, `oculta`),
  CONSTRAINT `fk_resena_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`),
  CONSTRAINT `fk_resena_vet` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_resena_prop` FOREIGN KEY (`id_propietario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
-- Historia clínica
-- ===========================================================================

CREATE TABLE `consultas` (
  `id_consulta` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_cita` INT DEFAULT NULL,
  `id_mascota` INT NOT NULL,
  `id_veterinario` INT NOT NULL,
  `fecha_hora` DATETIME NOT NULL,
  `motivo_consulta` TEXT NOT NULL,
  `anamnesis` TEXT NOT NULL,
  `peso` DECIMAL(5,2) DEFAULT NULL,
  `temperatura` DECIMAL(4,1) DEFAULT NULL,
  `frecuencia_cardiaca` INT DEFAULT NULL,
  `frecuencia_respiratoria` INT DEFAULT NULL,
  `diagnostico` TEXT NOT NULL,
  `plan_tratamiento` TEXT NOT NULL,
  `observaciones` TEXT DEFAULT NULL,
  PRIMARY KEY (`id_consulta`),
  -- Una consulta por cita: la pantalla de atención cuenta con ello
  UNIQUE KEY `uq_consulta_cita` (`id_cita`),
  KEY `idx_consultas_clinica_fecha` (`id_clinica`, `fecha_hora`),
  KEY `idx_consultas_mascota` (`id_mascota`, `fecha_hora`),
  CONSTRAINT `fk_consulta_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_consulta_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`),
  CONSTRAINT `fk_consulta_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  CONSTRAINT `fk_consulta_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `consulta_sintomas` (
  `id_consulta` INT NOT NULL,
  `id_nodo` INT NOT NULL,
  PRIMARY KEY (`id_consulta`, `id_nodo`),
  CONSTRAINT `fk_consint_consulta` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`),
  CONSTRAINT `fk_consint_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `grafo_nodos` (`id_nodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Hereda la clínica de su consulta. Vigente mientras fecha_fin no haya pasado (RN-211).
CREATE TABLE `tratamientos` (
  `id_tratamiento` INT NOT NULL AUTO_INCREMENT,
  `id_consulta` INT NOT NULL,
  `id_nodo_farmaco` INT DEFAULT NULL,
  `medicamento` VARCHAR(255) NOT NULL,
  `dosis` VARCHAR(255) NOT NULL,
  `via_administracion` VARCHAR(255) NOT NULL,
  `duracion` VARCHAR(100) NOT NULL,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_tratamiento`),
  CONSTRAINT `fk_trat_consulta` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`),
  CONSTRAINT `fk_trat_farmaco` FOREIGN KEY (`id_nodo_farmaco`) REFERENCES `grafo_nodos` (`id_nodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `archivos_clinicos` (
  `id_archivo` INT NOT NULL AUTO_INCREMENT,
  `id_consulta` INT NOT NULL,
  `nombre_original` VARCHAR(255) NOT NULL,
  `nombre_servidor` VARCHAR(255) NOT NULL,
  `ruta_archivo` VARCHAR(255) NOT NULL,
  `tipo_archivo` VARCHAR(255) NOT NULL,
  `extension` VARCHAR(20) NOT NULL,
  `tamano_bytes` INT NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `fecha_subida` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_archivo`),
  CONSTRAINT `fk_archivo_consulta` FOREIGN KEY (`id_consulta`) REFERENCES `consultas` (`id_consulta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
-- Prevención — catálogos por clínica (se copian al activarla, RE-0.2.5)
-- ===========================================================================

CREATE TABLE `vacunas_base` (
  `id_vacuna_base` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `nombre_vacuna` VARCHAR(150) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `estado` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_vacuna_base`),
  KEY `idx_vacbase_clinica` (`id_clinica`, `estado`),
  CONSTRAINT `fk_vacbase_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sin id_clinica: hereda la clínica de su vacuna base.
CREATE TABLE `especie_vacunas` (
  `id_especie_vacuna` INT NOT NULL AUTO_INCREMENT,
  `id_especie` INT NOT NULL,
  `id_vacuna_base` INT NOT NULL,
  PRIMARY KEY (`id_especie_vacuna`),
  UNIQUE KEY `uq_especie_vacuna` (`id_especie`, `id_vacuna_base`),
  CONSTRAINT `fk_especie_vacunas_especie` FOREIGN KEY (`id_especie`) REFERENCES `especies` (`id_especie`),
  CONSTRAINT `fk_especie_vacunas_vacuna` FOREIGN KEY (`id_vacuna_base`) REFERENCES `vacunas_base` (`id_vacuna_base`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `laboratorios_base` (
  `id_laboratorio` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `nombre_laboratorio` VARCHAR(150) NOT NULL,
  `estado` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_laboratorio`),
  KEY `idx_labbase_clinica` (`id_clinica`, `estado`),
  CONSTRAINT `fk_labbase_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `productos_desparasitacion_base` (
  `id_producto` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `nombre_producto` VARCHAR(150) NOT NULL,
  `tipo` ENUM('interna','externa','ambas') NOT NULL DEFAULT 'interna',
  `estado` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_producto`),
  KEY `idx_prodbase_clinica` (`id_clinica`, `estado`),
  CONSTRAINT `fk_prodbase_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Las ve toda clínica vinculada a la mascota (RN-113); solo la que las
-- registró (id_clinica) las modifica.
CREATE TABLE `vacunas` (
  `id_vacuna` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_mascota` INT NOT NULL,
  `id_veterinario` INT DEFAULT NULL,
  `nombre_vacuna` VARCHAR(150) NOT NULL,
  `laboratorio` VARCHAR(150) DEFAULT NULL,
  `lote` VARCHAR(100) DEFAULT NULL,
  `fecha_aplicacion` DATE NOT NULL,
  `fecha_proxima_dosis` DATE DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_vacuna`),
  KEY `idx_vacunas_clinica_proxima` (`id_clinica`, `fecha_proxima_dosis`),
  KEY `idx_vacunas_mascota` (`id_mascota`, `fecha_aplicacion`),
  CONSTRAINT `fk_vacuna_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_vacuna_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  CONSTRAINT `fk_vacuna_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `desparasitaciones` (
  `id_desparasitacion` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_mascota` INT NOT NULL,
  `id_veterinario` INT DEFAULT NULL,
  `tipo` ENUM('interna','externa') NOT NULL,
  `producto` VARCHAR(150) NOT NULL,
  `periodicidad` ENUM('mensual','trimestral','semestral') NOT NULL,
  `fecha_aplicacion` DATE NOT NULL,
  `fecha_proxima` DATE NOT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_desparasitacion`),
  KEY `idx_desp_clinica_proxima` (`id_clinica`, `fecha_proxima`),
  KEY `idx_desp_mascota` (`id_mascota`, `fecha_aplicacion`),
  CONSTRAINT `fk_desparasitacion_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_desparasitacion_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  CONSTRAINT `fk_desparasitacion_veterinario` FOREIGN KEY (`id_veterinario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================================================================
-- Comunicaciones y auditoría
-- ===========================================================================

-- Correos a las personas y bitácora de envíos. id_clinica es NULL en los
-- avisos de la plataforma (carnet, cambio de correo o de documento).
CREATE TABLE `notificaciones` (
  `id_notificacion` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT DEFAULT NULL,
  `id_usuario` INT NOT NULL,
  `tipo_entidad` VARCHAR(50) NOT NULL,
  `id_entidad` INT NOT NULL,
  `destinatario_email` VARCHAR(255) NOT NULL,
  `tipo_notificacion` VARCHAR(50) NOT NULL,
  `asunto` VARCHAR(255) DEFAULT NULL,
  `mensaje` TEXT DEFAULT NULL,
  `fecha_envio` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `estado` ENUM('pendiente','enviado','error') NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id_notificacion`),
  KEY `idx_notif_clinica_fecha` (`id_clinica`, `fecha_envio`),
  -- Para no repetir un recordatorio (tipo_entidad + id_entidad + tipo)
  KEY `idx_notif_entidad` (`tipo_entidad`, `id_entidad`, `tipo_notificacion`),
  CONSTRAINT `fk_notif_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Avisos al personal por usuario o por rol, dentro de una clínica.
CREATE TABLE `notificaciones_internas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_usuario` INT DEFAULT NULL,
  `id_rol_destino` INT DEFAULT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `enlace` VARCHAR(255) DEFAULT NULL,
  -- Cita que originó el aviso: se retira al cancelarla, reprogramarla o atenderla
  `id_cita` INT DEFAULT NULL,
  -- Desde cuándo deja de mostrarse; NULL = no caduca
  `vigente_hasta` DATETIME DEFAULT NULL,
  `leida` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notint_usuario` (`id_clinica`, `id_usuario`, `leida`),
  KEY `idx_notint_rol` (`id_clinica`, `id_rol_destino`, `leida`),
  KEY `idx_notint_vigencia` (`vigente_hasta`),
  CONSTRAINT `fk_notint_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_notint_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_notint_rol` FOREIGN KEY (`id_rol_destino`) REFERENCES `roles` (`id_rol`),
  CONSTRAINT `fk_notint_cita` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `auditoria_mascotas` (
  `id_auditoria` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT NOT NULL,
  `id_mascota` INT NOT NULL,
  `id_usuario` INT NOT NULL,
  `campo_modificado` VARCHAR(100) DEFAULT NULL,
  `valor_anterior` TEXT DEFAULT NULL,
  `valor_nuevo` TEXT DEFAULT NULL,
  `fecha_cambio` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_auditoria`),
  KEY `idx_audmasc_clinica` (`id_clinica`, `id_mascota`),
  CONSTRAINT `fk_audmasc_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_audmasc_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  CONSTRAINT `fk_audmasc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- id_clinica NULL en acciones de la plataforma; id_usuario NULL cuando el
-- intento no corresponde a una cuenta. Las cuentas no se borran, así que la
-- referencia siempre es válida.
CREATE TABLE `auditoria_sistema` (
  `id_auditoria` INT NOT NULL AUTO_INCREMENT,
  `id_clinica` INT DEFAULT NULL,
  `id_usuario` INT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `fecha_hora` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `accion` ENUM('LOGIN','LOGIN_FAIL','LOGOUT','INSERT','UPDATE','DELETE','VIEW','OTHER') NOT NULL,
  `tabla_afectada` VARCHAR(50) DEFAULT NULL,
  `registro_id` VARCHAR(50) DEFAULT NULL,
  `datos_anteriores` LONGTEXT DEFAULT NULL,
  `datos_nuevos` LONGTEXT DEFAULT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id_auditoria`),
  KEY `idx_audsis_clinica_fecha` (`id_clinica`, `fecha_hora`),
  KEY `idx_audsis_usuario` (`id_usuario`, `fecha_hora`),
  KEY `idx_audsis_accion` (`accion`),
  CONSTRAINT `fk_audsis_clinica` FOREIGN KEY (`id_clinica`) REFERENCES `clinicas` (`id_clinica`),
  CONSTRAINT `fk_audsis_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
