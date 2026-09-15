-- ---------------------------------------------------------------------------
-- Atenciones que se quedan abiertas (RN-408, RN-410, RN-411).
--
-- Una atención iniciada solo se cerraba registrando su consulta. Si el
-- veterinario salía sin terminar, la cita quedaba "en curso" para siempre y
-- nadie se enteraba.
--
-- · estado 'sin_cerrar'            terminó el día de la cita y la atención
--                                  seguía en curso. Se completa registrando
--                                  la consulta o se cierra sin consulta.
-- · estado 'cerrada_sin_consulta'  el veterinario la cerró sin consulta, con
--                                  motivo. No se reabre y libera su horario.
-- · aviso_atencion_abierta         cuándo se avisó que la atención seguía
--                                  abierta 10 minutos después de su hora de
--                                  fin. Evita avisar dos veces.
-- · motivo_cierre                  motivo del cierre sin consulta.
-- · slot_activo                    ahora también es NULL en
--                                  'cerrada_sin_consulta', para liberar el
--                                  horario (ver 10_citas_horario_unico_activas).
--
-- 01_schema.sql ya trae estos cambios para instalaciones nuevas, y Docker
-- ejecuta todos los .sql de esta carpeta al crear la base: por eso cada paso
-- comprueba antes si ya está aplicado. slot_activo depende de `estado`, así
-- que se retira antes de ampliar el ENUM y se vuelve a crear después.
-- ---------------------------------------------------------------------------

-- 1. Columnas nuevas
SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND COLUMN_NAME = 'aviso_atencion_abierta');
SET @sql = IF(@existe = 0,
  'ALTER TABLE `citas` ADD COLUMN `aviso_atencion_abierta` datetime DEFAULT NULL AFTER `hora_fin_real`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND COLUMN_NAME = 'motivo_cierre');
SET @sql = IF(@existe = 0,
  'ALTER TABLE `citas` ADD COLUMN `motivo_cierre` varchar(255) DEFAULT NULL AFTER `aviso_atencion_abierta`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Estado de lo ya aplicado
SET @enum_listo = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND COLUMN_NAME = 'estado'
                      AND COLUMN_TYPE LIKE '%cerrada_sin_consulta%');
SET @slot_listo = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND COLUMN_NAME = 'slot_activo'
                      AND GENERATION_EXPRESSION LIKE '%cerrada_sin_consulta%');

-- 3. Retirar el índice y la columna calculada si hay que rehacerlos
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND INDEX_NAME = 'uq_cita_vet_activa');
SET @sql = IF((@enum_listo = 0 OR @slot_listo = 0) AND @idx > 0,
  'ALTER TABLE `citas` DROP INDEX `uq_cita_vet_activa`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND COLUMN_NAME = 'slot_activo');
SET @sql = IF((@enum_listo = 0 OR @slot_listo = 0) AND @col > 0,
  'ALTER TABLE `citas` DROP COLUMN `slot_activo`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Ampliar el ENUM de estados
SET @sql = IF(@enum_listo = 0,
  'ALTER TABLE `citas` MODIFY `estado` enum(''pendiente'',''confirmada'',''en_curso'',''cancelada'',''completada'',''no_asistio'',''sin_cerrar'',''cerrada_sin_consulta'') DEFAULT ''pendiente''',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Volver a crear la columna calculada y el índice único
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND COLUMN_NAME = 'slot_activo');
SET @sql = IF(@col = 0,
  'ALTER TABLE `citas` ADD COLUMN `slot_activo` tinyint(1) GENERATED ALWAYS AS (CASE WHEN `estado` IN (''cancelada'', ''no_asistio'', ''cerrada_sin_consulta'') THEN NULL ELSE 1 END) STORED AFTER `estado`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND INDEX_NAME = 'uq_cita_vet_activa');
SET @sql = IF(@idx = 0,
  'ALTER TABLE `citas` ADD UNIQUE KEY `uq_cita_vet_activa` (`doc_veterinario`, `fecha`, `hora`, `slot_activo`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
