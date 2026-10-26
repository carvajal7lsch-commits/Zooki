SET NAMES utf8mb4;
-- RN-007/014: aditiva y retomable tras cada DDL; no envía avisos ni cobra.
DROP PROCEDURE IF EXISTS zooki_s1_error;
CREATE PROCEDURE zooki_s1_error(IN mensaje VARCHAR(128)) SQL SECURITY INVOKER SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = mensaje;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinicas' AND COLUMN_NAME='suscripcion_gracia_dias'),'DO 0','ALTER TABLE clinicas ADD COLUMN suscripcion_gracia_dias INT NOT NULL DEFAULT 3');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinicas' AND COLUMN_NAME='aviso_vencimiento_1_dias'),'DO 0','ALTER TABLE clinicas ADD COLUMN aviso_vencimiento_1_dias INT NOT NULL DEFAULT 7');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinicas' AND COLUMN_NAME='aviso_vencimiento_2_dias'),'DO 0','ALTER TABLE clinicas ADD COLUMN aviso_vencimiento_2_dias INT NOT NULL DEFAULT 1');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='gestion_cobro'),'DO 0','ALTER TABLE suscripciones ADD COLUMN gestion_cobro ENUM(''legado'',''gestionada'') NOT NULL DEFAULT ''legado''');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='periodo_inicio'),'DO 0','ALTER TABLE suscripciones ADD COLUMN periodo_inicio DATETIME DEFAULT NULL');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='periodo_fin'),'DO 0','ALTER TABLE suscripciones ADD COLUMN periodo_fin DATETIME DEFAULT NULL');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='estado_cobro'),'DO 0','ALTER TABLE suscripciones ADD COLUMN estado_cobro ENUM(''sin_vigencia'',''al_dia'',''gracia'',''congelada'') NOT NULL DEFAULT ''sin_vigencia''');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='version_periodo'),'DO 0','ALTER TABLE suscripciones ADD COLUMN version_periodo INT NOT NULL DEFAULT 0');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='cobro_automatico'),'DO 0','ALTER TABLE suscripciones ADD COLUMN cobro_automatico TINYINT NOT NULL DEFAULT 0');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='id_fuente_activa'),'DO 0','ALTER TABLE suscripciones ADD COLUMN id_fuente_activa BIGINT DEFAULT NULL');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='autorizado_por'),'DO 0','ALTER TABLE suscripciones ADD COLUMN autorizado_por INT DEFAULT NULL');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='autorizado_en'),'DO 0','ALTER TABLE suscripciones ADD COLUMN autorizado_en DATETIME DEFAULT NULL');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='retirado_en'),'DO 0','ALTER TABLE suscripciones ADD COLUMN retirado_en DATETIME DEFAULT NULL');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='cambio_ciclo_siguiente'),'DO 0','ALTER TABLE suscripciones ADD COLUMN cambio_ciclo_siguiente ENUM(''mensual'',''anual'') DEFAULT NULL');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
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
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_suscripcion_fuente'),'DO 0','ALTER TABLE suscripciones ADD CONSTRAINT fk_suscripcion_fuente FOREIGN KEY (id_fuente_activa) REFERENCES fuentes_pago (id_fuente)');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_suscripcion_autor'),'DO 0','ALTER TABLE suscripciones ADD CONSTRAINT fk_suscripcion_autor FOREIGN KEY (autorizado_por) REFERENCES usuarios (id_usuario)');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='id_fuente' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND COLUMN_KEY='PRI' AND EXTRA LIKE '%auto_increment%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.id_fuente'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='id_clinica' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.id_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='entorno' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''test'',''prod'')'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.entorno'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='id_fuente_wompi' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=128 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.id_fuente_wompi'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='tipo' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''CARD'',''NEQUI'')'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.tipo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='marca' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=30),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.marca'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='ultimos_cuatro' AND DATA_TYPE IN ('char') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=4),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.ultimos_cuatro'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='estado' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''pendiente'',''disponible'',''retirada'',''error'')' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='pendiente'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.estado'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='creada_por' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.creada_por'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='creada_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.creada_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='retirada_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.retirada_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='consentida_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.consentida_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='version_contratos' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=128),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.version_contratos'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='contratos_json' AND DATA_TYPE IN ('json','longtext') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.contratos_json'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='version_autorizacion' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=128),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.version_autorizacion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='importe_mensual_autorizado_centavos' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible fuentes_pago.importe_mensual_autorizado_centavos'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND INDEX_NAME='uq_fuente_externa')='entorno,id_fuente_wompi' AND (SELECT MAX(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND INDEX_NAME='uq_fuente_externa')=0,'DO 0','CALL zooki_s1_error(''S1 indice incompatible fuentes_pago.uq_fuente_externa'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND INDEX_NAME='idx_fuente_clinica')='id_clinica,estado','DO 0','CALL zooki_s1_error(''S1 indice incompatible fuentes_pago.idx_fuente_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='id_clinica' AND REFERENCED_TABLE_NAME='clinicas' AND REFERENCED_COLUMN_NAME='id_clinica'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible fuentes_pago.id_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fuentes_pago' AND COLUMN_NAME='creada_por' AND REFERENCED_TABLE_NAME='usuarios' AND REFERENCED_COLUMN_NAME='id_usuario'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible fuentes_pago.creada_por'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='id_pago' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND COLUMN_KEY='PRI' AND EXTRA LIKE '%auto_increment%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.id_pago'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='id_clinica' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.id_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='id_suscripcion' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.id_suscripcion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='id_plan' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.id_plan'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='clave_obligacion' AND DATA_TYPE IN ('char') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=64 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.clave_obligacion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='version_periodo_base' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.version_periodo_base'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='ciclo' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''mensual'',''anual'')'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.ciclo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='moneda' AND DATA_TYPE IN ('char') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=3 AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='COP'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.moneda'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='subtotal_centavos' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.subtotal_centavos'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='impuesto_centavos' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='0'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.impuesto_centavos'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='total_centavos' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.total_centavos'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='tarifa_version' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=50),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.tarifa_version'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='clave_operacion' AND DATA_TYPE IN ('char') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=64 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.clave_operacion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='cotizacion_hasta' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.cotizacion_hasta'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='estado' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''pendiente'',''confirmado'',''cancelado'',''revision'')' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='pendiente'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.estado'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='proximo_intento' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.proximo_intento'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='id_usuario_solicitante' AND DATA_TYPE IN ('int') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.id_usuario_solicitante'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='creado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.creado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='confirmado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible pagos_suscripcion.confirmado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND INDEX_NAME='uq_pago_obligacion')='clave_obligacion' AND (SELECT MAX(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND INDEX_NAME='uq_pago_obligacion')=0,'DO 0','CALL zooki_s1_error(''S1 indice incompatible pagos_suscripcion.uq_pago_obligacion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND INDEX_NAME='uq_pago_operacion')='id_clinica,clave_operacion' AND (SELECT MAX(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND INDEX_NAME='uq_pago_operacion')=0,'DO 0','CALL zooki_s1_error(''S1 indice incompatible pagos_suscripcion.uq_pago_operacion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND INDEX_NAME='idx_pago_clinica')='id_clinica,creado_en','DO 0','CALL zooki_s1_error(''S1 indice incompatible pagos_suscripcion.idx_pago_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND INDEX_NAME='idx_pago_proximo')='estado,proximo_intento','DO 0','CALL zooki_s1_error(''S1 indice incompatible pagos_suscripcion.idx_pago_proximo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='id_clinica' AND REFERENCED_TABLE_NAME='clinicas' AND REFERENCED_COLUMN_NAME='id_clinica'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible pagos_suscripcion.id_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='id_suscripcion' AND REFERENCED_TABLE_NAME='suscripciones' AND REFERENCED_COLUMN_NAME='id_suscripcion'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible pagos_suscripcion.id_suscripcion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='id_plan' AND REFERENCED_TABLE_NAME='planes' AND REFERENCED_COLUMN_NAME='id_plan'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible pagos_suscripcion.id_plan'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pagos_suscripcion' AND COLUMN_NAME='id_usuario_solicitante' AND REFERENCED_TABLE_NAME='usuarios' AND REFERENCED_COLUMN_NAME='id_usuario'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible pagos_suscripcion.id_usuario_solicitante'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='id_intento' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND COLUMN_KEY='PRI' AND EXTRA LIKE '%auto_increment%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.id_intento'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='id_pago' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.id_pago'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='numero' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.numero'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='entorno' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''test'',''prod'')'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.entorno'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='referencia' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=128 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.referencia'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='id_transaccion_wompi' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=128 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.id_transaccion_wompi'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='id_fuente' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.id_fuente'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='origen' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''boton'',''automatico'')'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.origen'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='estado' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''creado'',''enviando'',''pendiente'',''aprobado'',''declinado'',''error'',''anulado'',''incierto'')' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='creado'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.estado'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='codigo_error' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=80),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.codigo_error'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='creado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.creado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='enviado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.enviado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='consultado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.consultado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='lease_token' AND DATA_TYPE IN ('char') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=64 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.lease_token'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='lease_hasta' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible intentos_pago.lease_hasta'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND INDEX_NAME='uq_intento_numero')='id_pago,numero' AND (SELECT MAX(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND INDEX_NAME='uq_intento_numero')=0,'DO 0','CALL zooki_s1_error(''S1 indice incompatible intentos_pago.uq_intento_numero'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND INDEX_NAME='uq_intento_referencia')='entorno,referencia' AND (SELECT MAX(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND INDEX_NAME='uq_intento_referencia')=0,'DO 0','CALL zooki_s1_error(''S1 indice incompatible intentos_pago.uq_intento_referencia'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND INDEX_NAME='uq_intento_transaccion')='entorno,id_transaccion_wompi' AND (SELECT MAX(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND INDEX_NAME='uq_intento_transaccion')=0,'DO 0','CALL zooki_s1_error(''S1 indice incompatible intentos_pago.uq_intento_transaccion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND INDEX_NAME='idx_intento_estado')='estado,lease_hasta','DO 0','CALL zooki_s1_error(''S1 indice incompatible intentos_pago.idx_intento_estado'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='id_pago' AND REFERENCED_TABLE_NAME='pagos_suscripcion' AND REFERENCED_COLUMN_NAME='id_pago'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible intentos_pago.id_pago'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='intentos_pago' AND COLUMN_NAME='id_fuente' AND REFERENCED_TABLE_NAME='fuentes_pago' AND REFERENCED_COLUMN_NAME='id_fuente'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible intentos_pago.id_fuente'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='id_evento' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND COLUMN_KEY='PRI' AND EXTRA LIKE '%auto_increment%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.id_evento'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='clave_evento' AND DATA_TYPE IN ('char') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=64 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.clave_evento'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='id_evento_proveedor' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=128 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.id_evento_proveedor'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='entorno' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''test'',''prod'')'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.entorno'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='tipo' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=80),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.tipo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='id_transaccion_wompi' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=128 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.id_transaccion_wompi'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='timestamp_proveedor' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.timestamp_proveedor'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='checksum' AND DATA_TYPE IN ('char') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=64 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.checksum'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='huella_datos_firmados' AND DATA_TYPE IN ('char') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=64 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.huella_datos_firmados'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='datos_minimos' AND DATA_TYPE IN ('json','longtext') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.datos_minimos'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='id_intento' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.id_intento'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='estado' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''pendiente'',''procesando'',''procesado'',''ignorado'',''error'',''revision'')' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='pendiente'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.estado'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='recibido_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.recibido_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='procesado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.procesado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='lease_token' AND DATA_TYPE IN ('char') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=64 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.lease_token'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='lease_hasta' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.lease_hasta'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='intentos_proceso' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='0'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.intentos_proceso'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='proxima_revision' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.proxima_revision'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='codigo_error' AND DATA_TYPE IN ('varchar') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=80),'DO 0','CALL zooki_s1_error(''S1 columna incompatible wompi_eventos.codigo_error'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND INDEX_NAME='uq_wompi_evento')='entorno,clave_evento' AND (SELECT MAX(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND INDEX_NAME='uq_wompi_evento')=0,'DO 0','CALL zooki_s1_error(''S1 indice incompatible wompi_eventos.uq_wompi_evento'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND INDEX_NAME='idx_wompi_transaccion')='entorno,id_transaccion_wompi','DO 0','CALL zooki_s1_error(''S1 indice incompatible wompi_eventos.idx_wompi_transaccion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND INDEX_NAME='idx_wompi_revision')='estado,proxima_revision','DO 0','CALL zooki_s1_error(''S1 indice incompatible wompi_eventos.idx_wompi_revision'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wompi_eventos' AND COLUMN_NAME='id_intento' AND REFERENCED_TABLE_NAME='intentos_pago' AND REFERENCED_COLUMN_NAME='id_intento'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible wompi_eventos.id_intento'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_periodo' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND COLUMN_KEY='PRI' AND EXTRA LIKE '%auto_increment%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.id_periodo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_clinica' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.id_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_suscripcion' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.id_suscripcion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_plan' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.id_plan'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_pago' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.id_pago'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='origen' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''wompi'',''administrativo'',''legado'')'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.origen'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='ciclo' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''mensual'',''anual'')'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.ciclo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='inicio' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.inicio'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='fin' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.fin'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='dia_ancla' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.dia_ancla'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='mes_ancla' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.mes_ancla'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='hora_ancla' AND DATA_TYPE IN ('time') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.hora_ancla'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='total_centavos' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.total_centavos'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='moneda' AND DATA_TYPE IN ('char') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=3 AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='COP'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.moneda'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='creado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.creado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_usuario_actor' AND DATA_TYPE IN ('int') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.id_usuario_actor'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='motivo' AND DATA_TYPE IN ('text') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripcion_periodos.motivo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND INDEX_NAME='uq_periodo_pago')='id_pago' AND (SELECT MAX(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND INDEX_NAME='uq_periodo_pago')=0,'DO 0','CALL zooki_s1_error(''S1 indice incompatible suscripcion_periodos.uq_periodo_pago'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND INDEX_NAME='idx_periodo_suscripcion')='id_suscripcion,inicio,fin','DO 0','CALL zooki_s1_error(''S1 indice incompatible suscripcion_periodos.idx_periodo_suscripcion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND INDEX_NAME='idx_periodo_clinica')='id_clinica,fin','DO 0','CALL zooki_s1_error(''S1 indice incompatible suscripcion_periodos.idx_periodo_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_clinica' AND REFERENCED_TABLE_NAME='clinicas' AND REFERENCED_COLUMN_NAME='id_clinica'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible suscripcion_periodos.id_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_suscripcion' AND REFERENCED_TABLE_NAME='suscripciones' AND REFERENCED_COLUMN_NAME='id_suscripcion'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible suscripcion_periodos.id_suscripcion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_plan' AND REFERENCED_TABLE_NAME='planes' AND REFERENCED_COLUMN_NAME='id_plan'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible suscripcion_periodos.id_plan'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_pago' AND REFERENCED_TABLE_NAME='pagos_suscripcion' AND REFERENCED_COLUMN_NAME='id_pago'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible suscripcion_periodos.id_pago'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripcion_periodos' AND COLUMN_NAME='id_usuario_actor' AND REFERENCED_TABLE_NAME='usuarios' AND REFERENCED_COLUMN_NAME='id_usuario'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible suscripcion_periodos.id_usuario_actor'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_aviso' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND COLUMN_KEY='PRI' AND EXTRA LIKE '%auto_increment%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.id_aviso'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_clinica' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.id_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_suscripcion' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.id_suscripcion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_pago' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.id_pago'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_intento' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.id_intento'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_usuario_destinatario' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.id_usuario_destinatario'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='tipo' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND (BINARY COLUMN_TYPE=BINARY 'enum(''vence_7'',''vence_1'',''congelada'',''cobro_fallido'')' OR BINARY COLUMN_TYPE=BINARY 'enum(''vence_7'',''vence_1'',''congelada'',''cobro_fallido'',''pago_confirmado'')')),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.tipo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='clave_aviso' AND DATA_TYPE IN ('char') AND IS_NULLABLE='NO' AND CHARACTER_MAXIMUM_LENGTH=64 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.clave_aviso'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='vencimiento_objetivo' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.vencimiento_objetivo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='programado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='NO'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.programado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='estado' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''pendiente'',''reclamado'',''enviado'',''error'',''incierto'',''obsoleto'')' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='pendiente'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.estado'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_notificacion' AND DATA_TYPE IN ('int') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.id_notificacion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='lease_token' AND DATA_TYPE IN ('char') AND IS_NULLABLE='YES' AND CHARACTER_MAXIMUM_LENGTH=64 AND COLLATION_NAME='ascii_bin'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.lease_token'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='lease_hasta' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.lease_hasta'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='intentos' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='0'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.intentos'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='enviado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible avisos_suscripcion.enviado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND INDEX_NAME='uq_aviso_suscripcion')='clave_aviso' AND (SELECT MAX(NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND INDEX_NAME='uq_aviso_suscripcion')=0,'DO 0','CALL zooki_s1_error(''S1 indice incompatible avisos_suscripcion.uq_aviso_suscripcion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND INDEX_NAME='idx_aviso_programado')='estado,programado_en','DO 0','CALL zooki_s1_error(''S1 indice incompatible avisos_suscripcion.idx_aviso_programado'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND INDEX_NAME='idx_aviso_clinica')='id_clinica,programado_en','DO 0','CALL zooki_s1_error(''S1 indice incompatible avisos_suscripcion.idx_aviso_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_clinica' AND REFERENCED_TABLE_NAME='clinicas' AND REFERENCED_COLUMN_NAME='id_clinica'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible avisos_suscripcion.id_clinica'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_suscripcion' AND REFERENCED_TABLE_NAME='suscripciones' AND REFERENCED_COLUMN_NAME='id_suscripcion'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible avisos_suscripcion.id_suscripcion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_pago' AND REFERENCED_TABLE_NAME='pagos_suscripcion' AND REFERENCED_COLUMN_NAME='id_pago'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible avisos_suscripcion.id_pago'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_intento' AND REFERENCED_TABLE_NAME='intentos_pago' AND REFERENCED_COLUMN_NAME='id_intento'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible avisos_suscripcion.id_intento'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_usuario_destinatario' AND REFERENCED_TABLE_NAME='usuarios' AND REFERENCED_COLUMN_NAME='id_usuario'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible avisos_suscripcion.id_usuario_destinatario'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='id_notificacion' AND REFERENCED_TABLE_NAME='notificaciones' AND REFERENCED_COLUMN_NAME='id_notificacion'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible avisos_suscripcion.id_notificacion'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinicas' AND COLUMN_NAME='suscripcion_gracia_dias' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='3'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible clinicas.suscripcion_gracia_dias'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinicas' AND COLUMN_NAME='aviso_vencimiento_1_dias' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='7'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible clinicas.aviso_vencimiento_1_dias'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinicas' AND COLUMN_NAME='aviso_vencimiento_2_dias' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='1'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible clinicas.aviso_vencimiento_2_dias'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='gestion_cobro' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''legado'',''gestionada'')' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='legado'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.gestion_cobro'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='periodo_inicio' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.periodo_inicio'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='periodo_fin' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.periodo_fin'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='estado_cobro' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='NO' AND BINARY COLUMN_TYPE=BINARY 'enum(''sin_vigencia'',''al_dia'',''gracia'',''congelada'')' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='sin_vigencia'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.estado_cobro'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='version_periodo' AND DATA_TYPE IN ('int') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='0'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.version_periodo'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='cobro_automatico' AND DATA_TYPE IN ('tinyint') AND IS_NULLABLE='NO' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND REPLACE(COLUMN_DEFAULT,CHAR(39),'')='0'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.cobro_automatico'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='id_fuente_activa' AND DATA_TYPE IN ('bigint') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.id_fuente_activa'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='autorizado_por' AND DATA_TYPE IN ('int') AND IS_NULLABLE='YES' AND COLUMN_TYPE NOT LIKE '%unsigned%'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.autorizado_por'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='autorizado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.autorizado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='retirado_en' AND DATA_TYPE IN ('datetime') AND IS_NULLABLE='YES'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.retirado_en'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='cambio_ciclo_siguiente' AND DATA_TYPE IN ('enum') AND IS_NULLABLE='YES' AND BINARY COLUMN_TYPE=BINARY 'enum(''mensual'',''anual'')'),'DO 0','CALL zooki_s1_error(''S1 columna incompatible suscripciones.cambio_ciclo_siguiente'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='id_fuente_activa' AND REFERENCED_TABLE_NAME='fuentes_pago' AND REFERENCED_COLUMN_NAME='id_fuente'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible suscripciones.id_fuente_activa'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
SET @s1_ddl=IF(EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='suscripciones' AND COLUMN_NAME='autorizado_por' AND REFERENCED_TABLE_NAME='usuarios' AND REFERENCED_COLUMN_NAME='id_usuario'),'DO 0','CALL zooki_s1_error(''S1 FK incompatible suscripciones.autorizado_por'')');
PREPARE s1_sentencia FROM @s1_ddl;
EXECUTE s1_sentencia;
DEALLOCATE PREPARE s1_sentencia;
UPDATE planes SET precio_mensual=89990,precio_anual=899900 WHERE nombre='Profesional' AND precio_mensual=100000 AND precio_anual=960000;
UPDATE suscripciones SET periodo_inicio=CAST(fecha_inicio AS DATETIME),periodo_fin=DATE_ADD(fecha_fin,INTERVAL 1 DAY),gestion_cobro='gestionada' WHERE gestion_cobro='legado' AND fecha_fin IS NOT NULL AND periodo_fin IS NULL;
DROP PROCEDURE IF EXISTS zooki_s1_error;
