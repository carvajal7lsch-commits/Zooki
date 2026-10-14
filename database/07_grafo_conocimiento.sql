SET NAMES utf8mb4;
-- M2 B1: RN-212 / RN-424. DDL compatible con MySQL 8 y MariaDB 10.4.
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'codigo'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `codigo` VARCHAR(100) COLLATE utf8mb4_bin DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'activo'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `activo` TINYINT(1) NOT NULL DEFAULT 0');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'version_conocimiento'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `version_conocimiento` VARCHAR(64) NOT NULL DEFAULT ''legado''');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'fuentes'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `fuentes` JSON DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'estado_validacion'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `estado_validacion` ENUM(''pendiente'',''validado'',''rechazado'') NOT NULL DEFAULT ''pendiente''');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'nivel_triage'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `nivel_triage` ENUM(''rojo'',''naranja'',''amarillo'',''verde'') DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'alarma_universal'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `alarma_universal` TINYINT(1) NOT NULL DEFAULT 0');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'moderado'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `moderado` TINYINT(1) NOT NULL DEFAULT 0');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'metadatos'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `metadatos` JSON DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'id_especie'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `id_especie` INT DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'id_raza'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD `id_raza` INT DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
UPDATE grafo_nodos SET codigo = CONCAT('legado.grafo_nodos.', id_nodo) WHERE codigo IS NULL;
UPDATE grafo_nodos SET fuentes = '[]' WHERE fuentes IS NULL;
SET @ddl = IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'codigo' AND IS_NULLABLE = 'YES'), 'SELECT 1', 'ALTER TABLE grafo_nodos MODIFY codigo VARCHAR(100) COLLATE utf8mb4_bin NOT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'fuentes' AND IS_NULLABLE = 'YES'), 'SELECT 1', 'ALTER TABLE grafo_nodos MODIFY fuentes JSON NOT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'activo' AND COLUMN_DEFAULT = '1'), 'SELECT 1', 'ALTER TABLE grafo_nodos ALTER activo SET DEFAULT 1');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND INDEX_NAME = 'uq_grafo_nodos_codigo'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD UNIQUE KEY uq_grafo_nodos_codigo (codigo)');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'codigo'), 'SELECT 1', 'ALTER TABLE grafo_aristas ADD `codigo` VARCHAR(100) COLLATE utf8mb4_bin DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'activo'), 'SELECT 1', 'ALTER TABLE grafo_aristas ADD `activo` TINYINT(1) NOT NULL DEFAULT 0');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'version_conocimiento'), 'SELECT 1', 'ALTER TABLE grafo_aristas ADD `version_conocimiento` VARCHAR(64) NOT NULL DEFAULT ''legado''');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'fuentes'), 'SELECT 1', 'ALTER TABLE grafo_aristas ADD `fuentes` JSON DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'estado_validacion'), 'SELECT 1', 'ALTER TABLE grafo_aristas ADD `estado_validacion` ENUM(''pendiente'',''validado'',''rechazado'') NOT NULL DEFAULT ''pendiente''');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'efecto'), 'SELECT 1', 'ALTER TABLE grafo_aristas ADD `efecto` ENUM(''evidencia'',''bloqueo'',''advertencia'') NOT NULL DEFAULT ''evidencia''');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'condiciones'), 'SELECT 1', 'ALTER TABLE grafo_aristas ADD `condiciones` JSON DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
UPDATE grafo_aristas SET codigo = CONCAT('legado.grafo_aristas.', id_arista) WHERE codigo IS NULL;
UPDATE grafo_aristas SET fuentes = '[]' WHERE fuentes IS NULL;
SET @ddl = IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'codigo' AND IS_NULLABLE = 'YES'), 'SELECT 1', 'ALTER TABLE grafo_aristas MODIFY codigo VARCHAR(100) COLLATE utf8mb4_bin NOT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'fuentes' AND IS_NULLABLE = 'YES'), 'SELECT 1', 'ALTER TABLE grafo_aristas MODIFY fuentes JSON NOT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'activo' AND COLUMN_DEFAULT = '1'), 'SELECT 1', 'ALTER TABLE grafo_aristas ALTER activo SET DEFAULT 1');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND INDEX_NAME = 'uq_grafo_aristas_codigo'), 'SELECT 1', 'ALTER TABLE grafo_aristas ADD UNIQUE KEY uq_grafo_aristas_codigo (codigo)');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'tipo' AND COLUMN_TYPE LIKE '%sustancia%'), 'SELECT 1', 'ALTER TABLE grafo_nodos MODIFY tipo ENUM(''sintoma'',''diagnostico'',''farmaco'',''especie'',''raza'',''condicion'',''sustancia'') NOT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND INDEX_NAME = 'uq_grafo_nodos_especie'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD UNIQUE KEY uq_grafo_nodos_especie (id_especie)');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND CONSTRAINT_NAME = 'fk_nodo_especie'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD CONSTRAINT fk_nodo_especie FOREIGN KEY (id_especie) REFERENCES especies (id_especie)');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND INDEX_NAME = 'uq_grafo_nodos_raza'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD UNIQUE KEY uq_grafo_nodos_raza (id_raza)');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND CONSTRAINT_NAME = 'fk_nodo_raza'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD CONSTRAINT fk_nodo_raza FOREIGN KEY (id_raza) REFERENCES razas (id_raza)');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND CONSTRAINT_NAME = 'ck_nodo_taxonomia'), 'SELECT 1', 'ALTER TABLE grafo_nodos ADD CONSTRAINT ck_nodo_taxonomia CHECK ((activo = 0 AND codigo LIKE ''legado.%'') OR (tipo = ''especie'' AND id_especie IS NOT NULL AND id_raza IS NULL)
    OR (tipo = ''raza'' AND id_raza IS NOT NULL AND id_especie IS NULL)
    OR (tipo NOT IN (''especie'',''raza'') AND id_especie IS NULL AND id_raza IS NULL))');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
CREATE TABLE IF NOT EXISTS `grafo_sinonimos` (
  `id_sinonimo` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `version_conocimiento` VARCHAR(64) NOT NULL,
  `fuentes` JSON NOT NULL,
  `estado_validacion` ENUM('pendiente','validado','rechazado') NOT NULL DEFAULT 'pendiente',
  `id_nodo` INT NOT NULL,
  `texto` VARCHAR(150) NOT NULL,
  `normalizado` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id_sinonimo`),
  UNIQUE KEY `uq_grafo_sinonimos_codigo` (`codigo`),
  KEY `idx_grafo_sinonimos_busqueda` (`normalizado`),
  CONSTRAINT `fk_sinonimo_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `grafo_nodos` (`id_nodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `grafo_reglas` (
  `id_regla` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `version_conocimiento` VARCHAR(64) NOT NULL,
  `fuentes` JSON NOT NULL,
  `estado_validacion` ENUM('pendiente','validado','rechazado') NOT NULL DEFAULT 'pendiente',
  `motor` ENUM('diagnosticos','prescripcion','triage') NOT NULL,
  `condiciones` JSON NOT NULL,
  `resultado` JSON NOT NULL,
  PRIMARY KEY (`id_regla`),
  UNIQUE KEY `uq_grafo_reglas_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consulta_sintomas' AND COLUMN_NAME = 'detalles'), 'SELECT 1', 'ALTER TABLE consulta_sintomas ADD `detalles` JSON DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cita_sintomas' AND COLUMN_NAME = 'detalles'), 'SELECT 1', 'ALTER TABLE cita_sintomas ADD `detalles` JSON DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas' AND COLUMN_NAME = 'contexto_triage'), 'SELECT 1', 'ALTER TABLE citas ADD `contexto_triage` JSON DEFAULT NULL');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
INSERT IGNORE INTO razas (id_raza, id_especie, nombre_raza) VALUES (55, 1, 'Gran danés');
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_nodos' AND COLUMN_NAME = 'version_conocimiento' AND COLUMN_DEFAULT IS NOT NULL), 'ALTER TABLE grafo_nodos ALTER version_conocimiento DROP DEFAULT', 'SELECT 1');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'version_conocimiento' AND COLUMN_DEFAULT IS NOT NULL), 'ALTER TABLE grafo_aristas ALTER version_conocimiento DROP DEFAULT', 'SELECT 1');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
UPDATE grafo_aristas SET peso = 1.000 WHERE peso IS NULL;
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'peso' AND IS_NULLABLE = 'YES'), 'ALTER TABLE grafo_aristas MODIFY peso DECIMAL(4,3) NOT NULL DEFAULT 1.000', 'SELECT 1');
PREPARE sentencia FROM @ddl;
EXECUTE sentencia;
DEALLOCATE PREPARE sentencia;
