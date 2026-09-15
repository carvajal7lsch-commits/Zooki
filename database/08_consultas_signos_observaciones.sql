-- ---------------------------------------------------------------------------
-- Frecuencia respiratoria y observaciones en la consulta.
--
-- La pantalla de atención de la cita pedía "F.R." y "Observaciones", pero la
-- tabla no tenía dónde guardarlas: el veterinario las escribía y se perdían
-- sin aviso. Son datos clínicos del examen, así que se agregan columnas.
--
-- 01_schema.sql ya trae estas columnas para instalaciones nuevas, y Docker
-- ejecuta todos los .sql de esta carpeta al crear la base: un ADD COLUMN
-- directo fallaría con "Duplicate column" y abortaría el arranque. MySQL 8 no
-- admite ADD COLUMN IF NOT EXISTS, así que se consulta information_schema.
-- ---------------------------------------------------------------------------

SET @fr_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultas'
                     AND COLUMN_NAME = 'frecuencia_respiratoria');
SET @sql = IF(@fr_existe = 0,
  'ALTER TABLE `consultas` ADD COLUMN `frecuencia_respiratoria` int(11) DEFAULT NULL AFTER `frecuencia_cardiaca`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @obs_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultas'
                      AND COLUMN_NAME = 'observaciones');
SET @sql = IF(@obs_existe = 0,
  'ALTER TABLE `consultas` ADD COLUMN `observaciones` text DEFAULT NULL AFTER `plan_tratamiento`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
