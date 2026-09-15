-- ---------------------------------------------------------------------------
-- Saber si el dueño de una cuenta conoce su contraseña (HU-39).
--
-- Una cuenta creada con Google nace con una contraseña aleatoria que nadie
-- conoce, y en la base se ve igual que una real. Por eso el cambio de
-- contraseña decidía según cómo se inició sesión (login_method): bastaba
-- entrar con Google para cambiarla sin escribir la actual, aunque la cuenta
-- tuviera una contraseña conocida.
--
-- · password_definida   1 si la cuenta tiene una contraseña que su dueño
--                       conoce; 0 si solo tiene la aleatoria de Google.
--                       Pasa a 1 cada vez que se guarda una contraseña
--                       (cambio desde el perfil, restablecimiento por correo
--                       o contraseña temporal del administrador).
--
-- Las cuentas que ya existen quedan en 1: hoy no hay forma de distinguir las
-- que se crearon con Google. Si una de ellas nunca tuvo contraseña, su dueño
-- la define una vez con "¿Olvidaste tu contraseña?".
--
-- 01_schema.sql ya trae la columna para instalaciones nuevas, y Docker
-- ejecuta todos los .sql de esta carpeta al crear la base: por eso se agrega
-- solo si no existe (MySQL 8 no admite ADD COLUMN IF NOT EXISTS).
-- ---------------------------------------------------------------------------

SET @col_existe = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
                      AND COLUMN_NAME = 'password_definida');
SET @sql = IF(@col_existe = 0,
  'ALTER TABLE `usuarios` ADD COLUMN `password_definida` tinyint(1) NOT NULL DEFAULT 1 AFTER `password`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
