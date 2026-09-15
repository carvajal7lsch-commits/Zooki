-- ---------------------------------------------------------------------------
-- Un horario cancelado vuelve a quedar libre.
--
-- El índice único `unique_cita_vet` (doc_veterinario, fecha, hora) contaba
-- también las citas canceladas y las de "no asistió". La agenda mostraba el
-- horario como libre, pero al agendar otra cita a esa misma hora MySQL
-- rechazaba el INSERT y el usuario solo veía "error de conexión".
--
-- · slot_activo            columna calculada: 1 si la cita ocupa su horario,
--                          NULL si está cancelada o no asistió.
-- · uq_cita_vet_activa     reemplaza a unique_cita_vet. Un índice único admite
--                          varios NULL, así que las citas canceladas no
--                          chocan entre sí ni con la nueva, y sigue siendo
--                          imposible tener dos citas activas en el mismo
--                          horario del mismo veterinario.
--
-- 01_schema.sql ya trae estos cambios para instalaciones nuevas, y Docker
-- ejecuta todos los .sql de esta carpeta al crear la base: por eso cada paso
-- comprueba antes si ya está aplicado.
-- ---------------------------------------------------------------------------

SET @col_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas'
                      AND COLUMN_NAME = 'slot_activo');
SET @sql = IF(@col_existe = 0,
  'ALTER TABLE `citas` ADD COLUMN `slot_activo` tinyint(1) GENERATED ALWAYS AS (CASE WHEN `estado` IN (''cancelada'', ''no_asistio'') THEN NULL ELSE 1 END) STORED AFTER `estado`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @viejo_existe = (SELECT COUNT(*) FROM information_schema.STATISTICS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas'
                        AND INDEX_NAME = 'unique_cita_vet');
SET @sql = IF(@viejo_existe > 0,
  'ALTER TABLE `citas` DROP INDEX `unique_cita_vet`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @nuevo_existe = (SELECT COUNT(*) FROM information_schema.STATISTICS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'citas'
                        AND INDEX_NAME = 'uq_cita_vet_activa');
SET @sql = IF(@nuevo_existe = 0,
  'ALTER TABLE `citas` ADD UNIQUE KEY `uq_cita_vet_activa` (`doc_veterinario`, `fecha`, `hora`, `slot_activo`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
