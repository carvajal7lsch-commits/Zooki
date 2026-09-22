-- ---------------------------------------------------------------------------
-- Razas en el portal del propietario (HU-15, RE-15.9).
--
-- El portal ya no deja crear razas con texto libre: así se llenó la base de
-- valores sueltos. El propietario tiene tres salidas cuando su raza no es una
-- de la lista:
--
-- · «Criollo» (perros y gatos): la mascota es mestiza.
-- · «No sé la raza»: se guarda como «Sin raza definida».
-- · «Mi raza no está en la lista»: escribe cuál es. Se guarda como «Sin raza
--   definida» y el texto queda en mascotas.raza_indicada, que el personal ve
--   en la ficha para confirmarla o agregarla al catálogo. No se crea ninguna
--   raza desde el portal.
--
-- Se puede ejecutar varias veces: solo agrega lo que falta.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- 1. La raza que escribió el propietario, pendiente de revisar por la clínica.
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mascotas' AND COLUMN_NAME = 'raza_indicada');
SET @sql = IF(@c = 0,
  'ALTER TABLE `mascotas` ADD COLUMN `raza_indicada` varchar(50) DEFAULT NULL AFTER `id_raza`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. «Sin raza definida» en todas las especies que no la tienen.
INSERT INTO razas (id_especie, nombre_raza)
SELECT e.id_especie, 'Sin raza definida'
FROM especies e
WHERE NOT EXISTS (
    SELECT 1 FROM razas r
    WHERE r.id_especie = e.id_especie AND r.nombre_raza = 'Sin raza definida'
);
