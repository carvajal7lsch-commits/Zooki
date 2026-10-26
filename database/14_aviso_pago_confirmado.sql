SET NAMES utf8mb4;
-- RN-017 / RE-0.5.22: ampliar la outbox existente sin reescribir migraciones publicadas.
SET @s4_correo_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='medio_pago'),'DO 0','ALTER TABLE avisos_suscripcion ADD COLUMN medio_pago VARCHAR(32) DEFAULT NULL');
PREPARE s4_correo_sentencia FROM @s4_correo_ddl;
EXECUTE s4_correo_sentencia;
DEALLOCATE PREPARE s4_correo_sentencia;
SET @s4_correo_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avisos_suscripcion' AND COLUMN_NAME='tipo' AND COLUMN_TYPE LIKE '%pago_confirmado%'),'DO 0',"ALTER TABLE avisos_suscripcion MODIFY COLUMN tipo ENUM('vence_7','vence_1','congelada','cobro_fallido','pago_confirmado') NOT NULL");
PREPARE s4_correo_sentencia FROM @s4_correo_ddl;
EXECUTE s4_correo_sentencia;
DEALLOCATE PREPARE s4_correo_sentencia;
