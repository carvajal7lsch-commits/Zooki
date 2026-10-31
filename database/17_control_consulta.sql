-- RN-431: plazos por clínica; repetible y sin sobrescribir configuraciones existentes.
SET NAMES utf8mb4;
SET @control_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinicas' AND COLUMN_NAME = 'control_busqueda_dias') = 0,
    'ALTER TABLE clinicas ADD COLUMN control_busqueda_dias INT NOT NULL DEFAULT 30 AFTER anticipacion_cambios_min',
    'SELECT 1'
);
PREPARE control_sentencia FROM @control_sql;
EXECUTE control_sentencia;
DEALLOCATE PREPARE control_sentencia;
SET @control_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinicas' AND COLUMN_NAME = 'control_dias_sin_duracion') = 0,
    'ALTER TABLE clinicas ADD COLUMN control_dias_sin_duracion INT NOT NULL DEFAULT 7 AFTER control_busqueda_dias',
    'SELECT 1'
);
PREPARE control_sentencia FROM @control_sql;
EXECUTE control_sentencia;
DEALLOCATE PREPARE control_sentencia;
