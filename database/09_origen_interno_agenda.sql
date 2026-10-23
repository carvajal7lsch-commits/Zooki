SET NAMES utf8mb4;

-- C4, autorización del usuario 2026-10-20: interno no acredita presencia (B1).
DROP PROCEDURE IF EXISTS zooki_m4_09_error;
CREATE PROCEDURE zooki_m4_09_error() SQL SECURITY INVOKER SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='M4-09 origen de solicitud incompatible';
SET @m4_origen = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='solicitudes_agenda' AND COLUMN_NAME='origen' AND IS_NULLABLE='NO' AND COLUMN_DEFAULT IS NULL);
SET @m4_ddl = CASE WHEN @m4_origen='enum(''portal'',''llegada'')' THEN 'ALTER TABLE solicitudes_agenda MODIFY origen ENUM(''portal'',''llegada'',''interno'') NOT NULL' WHEN @m4_origen='enum(''portal'',''llegada'',''interno'')' THEN 'DO 0' ELSE 'CALL zooki_m4_09_error()' END;
PREPARE m4_sentencia FROM @m4_ddl;
EXECUTE m4_sentencia;
DEALLOCATE PREPARE m4_sentencia;
DROP PROCEDURE zooki_m4_09_error;
