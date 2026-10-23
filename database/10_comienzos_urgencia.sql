SET NAMES utf8mb4;
-- C6-C7: ajuste del índice autorizado por el usuario (2026-10-20).
-- Ningún AND/NOT ni operador adicional se admite. En esta disyunción de átomos
-- positivos los paréntesis no cambian la semántica; la lista de átomos y literales permanece cerrada; sus columnas usan general_ci.
DROP PROCEDURE IF EXISTS zooki_m4_10_error;
CREATE PROCEDURE zooki_m4_10_error() SQL SECURITY INVOKER SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='M4-10 índice de comienzos incompatible';
SET @m4_generada = (SELECT GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='citas' AND COLUMN_NAME='ocupa_horario' AND COLUMN_TYPE IN ('tinyint(1)','tinyint') AND EXTRA LIKE '%STORED GENERATED%');
-- MySQL 8 serializa las comillas de los literales como \' (igual que CHECK_CLAUSE en B2.2); MariaDB no.
SET @m4_generada = REPLACE(@m4_generada, CONCAT(CHAR(92),CHAR(39)), CHAR(39));
-- Verificar los literales ANTES de quitar espacios/backticks/prefijos de la metadata.
-- Así 'ur gen cia' nunca se convierte por normalización en el literal válido.
SET @m4_sin_literales = REPLACE(REPLACE(REPLACE(LOWER(@m4_generada), CONCAT(CHAR(39),'cancelada',CHAR(39)), ''), CONCAT(CHAR(39),'no_asistio',CHAR(39)), ''), CONCAT(CHAR(39),'urgencia',CHAR(39)), '');
SET @m4_literales_validos = LOCATE(CHAR(39),@m4_sin_literales)=0;
SET @m4_atomos = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(@m4_generada), '`', ''), '_utf8mb4', ''), ' ', ''), CHAR(10), ''), CHAR(13), ''), '(', ''), ')', '');
SET @m4_previo = 'casewhenestadoin''cancelada'',''no_asistio''ores_sobrecupo=1thennullelse1end';
SET @m4_actual = 'casewhenestadoin''cancelada'',''no_asistio''ores_sobrecupo=1ororigen=''urgencia''thennullelse1end';
SET @m4_indice = (SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='citas' AND INDEX_NAME='uq_cita_veterinario_horario' AND NON_UNIQUE=0 AND SUB_PART IS NULL);
SET @m4_textos = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='citas' AND COLUMN_NAME IN ('estado','origen') AND COLLATION_NAME='utf8mb4_general_ci');
SET @m4_total_indice = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='citas' AND INDEX_NAME='uq_cita_veterinario_horario');
SET @m4_ddl = IF(@m4_literales_validos AND @m4_textos=2 AND @m4_total_indice=4 AND BINARY @m4_indice='id_veterinario,fecha,hora,ocupa_horario' AND (BINARY @m4_atomos=BINARY @m4_previo OR BINARY @m4_atomos=BINARY @m4_actual), 'DO 0', 'CALL zooki_m4_10_error()');
PREPARE m4_sentencia FROM @m4_ddl;
EXECUTE m4_sentencia;
DEALLOCATE PREPARE m4_sentencia;
SET @m4_ddl = IF(BINARY @m4_atomos=BINARY @m4_actual, 'DO 0', 'ALTER TABLE citas MODIFY ocupa_horario TINYINT(1) GENERATED ALWAYS AS (CASE WHEN estado IN (''cancelada'', ''no_asistio'') OR es_sobrecupo = 1 OR origen = ''urgencia'' THEN NULL ELSE 1 END) STORED');
PREPARE m4_sentencia FROM @m4_ddl;
EXECUTE m4_sentencia;
DEALLOCATE PREPARE m4_sentencia;
DROP PROCEDURE zooki_m4_10_error;
