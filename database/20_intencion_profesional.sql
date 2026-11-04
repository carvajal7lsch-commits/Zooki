SET NAMES utf8mb4;

-- HU-0.1/0.5: elección pendiente; nunca sustituye el plan efectivo ni acredita pagos.

SET @zooki_intencion_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinicas' AND COLUMN_NAME='intencion_profesional_ciclo'), 'SELECT 1', 'ALTER TABLE clinicas ADD COLUMN intencion_profesional_ciclo ENUM(''mensual'',''anual'') DEFAULT NULL');
PREPARE zooki_intencion_stmt FROM @zooki_intencion_sql;
EXECUTE zooki_intencion_stmt;
DEALLOCATE PREPARE zooki_intencion_stmt;

SET @zooki_intencion_sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinicas' AND COLUMN_NAME='intencion_profesional_mostrada_en'), 'SELECT 1', 'ALTER TABLE clinicas ADD COLUMN intencion_profesional_mostrada_en DATETIME DEFAULT NULL');
PREPARE zooki_intencion_stmt FROM @zooki_intencion_sql;
EXECUTE zooki_intencion_stmt;
DEALLOCATE PREPARE zooki_intencion_stmt;
