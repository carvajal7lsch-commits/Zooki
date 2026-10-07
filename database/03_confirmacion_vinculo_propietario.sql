SET NAMES utf8mb4;
-- RN-109 / RE-1.2.2: propósito y clínica quedan ligados al hash de un solo uso.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND COLUMN_NAME = 'id_clinica_vinculo'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD id_clinica_vinculo INT DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND CONSTRAINT_NAME = 'fk_verif_clinica_vinculo'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD CONSTRAINT fk_verif_clinica_vinculo FOREIGN KEY (id_clinica_vinculo) REFERENCES clinicas (id_clinica)');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificaciones_email' AND COLUMN_NAME = 'proposito'), 'SELECT 1', 'ALTER TABLE verificaciones_email ADD proposito VARCHAR(30) NOT NULL DEFAULT ''registro''');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
