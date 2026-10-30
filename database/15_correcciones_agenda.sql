SET NAMES utf8mb4;
-- RN-430 / HU-7.4: campos nuevos sin reescribir preferencias ni tipos existentes.
SET @agenda_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinicas' AND COLUMN_NAME='anticipacion_cambios_min'),'DO 0','ALTER TABLE clinicas ADD COLUMN anticipacion_cambios_min INT NOT NULL DEFAULT 30 AFTER tope_sobrecupos');
PREPARE agenda_sentencia FROM @agenda_ddl;
EXECUTE agenda_sentencia;
DEALLOCATE PREPARE agenda_sentencia;
SET @agenda_ddl=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tipos_cita' AND COLUMN_NAME='solo_personal'),'DO 0','ALTER TABLE tipos_cita ADD COLUMN solo_personal TINYINT(1) NOT NULL DEFAULT 0 AFTER pausable');
PREPARE agenda_sentencia FROM @agenda_ddl;
EXECUTE agenda_sentencia;
DEALLOCATE PREPARE agenda_sentencia;
INSERT INTO tipos_cita (id_clinica,nombre_tipo,duracion_minutos,margen_minutos,pausable,solo_personal,descripcion,color,activo)
SELECT c.id_clinica,'Cirugía programada',120,15,0,1,'Cirugía programada por el personal veterinario','#0C66E4',1
FROM clinicas c
-- Las pendientes reciben los siete tipos juntos al activarse; no iniciarles un catálogo parcial.
WHERE c.estado<>'pendiente_verificacion'
AND NOT EXISTS (SELECT 1 FROM tipos_cita t WHERE t.id_clinica=c.id_clinica AND TRIM(t.nombre_tipo)='Cirugía programada');
