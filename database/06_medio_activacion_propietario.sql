SET NAMES utf8mb4;
-- M5 C4b (decisión del usuario, 2026-10-13; RN-G19): el propietario que activa
-- la cuenta que le creó la clínica deja su propio medio en la prueba del
-- consentimiento ('activacion_propietario'); antes quedaba como 'alta_personal'.
-- Repetible: solo cambia la columna si todavía no admite el valor nuevo.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consentimientos_datos' AND COLUMN_NAME = 'medio' AND COLUMN_TYPE LIKE '%activacion_propietario%'), 'SELECT 1', 'ALTER TABLE consentimientos_datos MODIFY medio ENUM(''formulario'',''google'',''alta_personal'',''activacion_propietario'') NOT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
