-- HU-2.8/2.9, RN-212: pauta declarativa; el plan vive en metadatos del diagnóstico.
SET NAMES utf8mb4;
SET @pauta_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grafo_aristas' AND COLUMN_NAME = 'pauta') = 0,
    'ALTER TABLE grafo_aristas ADD COLUMN pauta JSON DEFAULT NULL AFTER condiciones',
    'SELECT 1'
);
PREPARE pauta_sentencia FROM @pauta_sql;
EXECUTE pauta_sentencia;
DEALLOCATE PREPARE pauta_sentencia;
