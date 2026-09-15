-- ---------------------------------------------------------------------------
-- Asistencia y hora real de atención de las citas (HU-29, HU-27).
--
-- · estado 'no_asistio'  el paciente no llegó. La cita deja de ocupar su
--                        espacio en la agenda y cuenta para el ausentismo
--                        (VD-AGN-07). Sin este estado, una cita a la que nadie
--                        vino quedaba pendiente o confirmada para siempre.
-- · hora_inicio_real     se sella al iniciar la atención.
-- · hora_fin_real        se sella al registrar la consulta que la completa.
--                        Hasta ahora el sistema solo conocía las horas
--                        planificadas (VD-AGN-01).
--
-- 01_schema.sql ya trae estos cambios para instalaciones nuevas, y Docker
-- ejecuta todos los .sql de esta carpeta al crear la base: por eso las
-- columnas se agregan solo si no existen (MySQL 8 no admite
-- ADD COLUMN IF NOT EXISTS). El MODIFY del ENUM es idempotente.
-- ---------------------------------------------------------------------------

ALTER TABLE `citas`
  MODIFY `estado` enum('pendiente','confirmada','en_curso','cancelada','completada','no_asistio') DEFAULT 'pendiente';

SET @ini_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas'
                      AND COLUMN_NAME = 'hora_inicio_real');
SET @sql = IF(@ini_existe = 0,
  'ALTER TABLE `citas` ADD COLUMN `hora_inicio_real` datetime DEFAULT NULL AFTER `estado`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fin_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas'
                      AND COLUMN_NAME = 'hora_fin_real');
SET @sql = IF(@fin_existe = 0,
  'ALTER TABLE `citas` ADD COLUMN `hora_fin_real` datetime DEFAULT NULL AFTER `hora_inicio_real`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
